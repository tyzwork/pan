<?php
/**
 * 通用 S3 兼容存储驱动（AWS Signature Version 4，手写签名，无需 SDK）
 * 支持: AWS S3 / MinIO / Cloudflare R2 / Backblaze B2 等兼容服务
 *
 * 配置参数:
 *   access_key    访问密钥 ID
 *   secret_key    访问密钥密码
 *   endpoint      S3 API 地址（含协议，如 https://s3.us-east-1.amazonaws.com）
 *   region        区域，如 us-east-1
 *   bucket        存储桶名称
 *   prefix        对象前缀（默认 file/）
 *   style         地址风格: virtual-host 虚拟主机风格 / path 路径风格
 *   upload_mode   上传方式: relay 网站中转 / direct 浏览器直传
 */

namespace lib\CloudStorage;

class S3Storage implements CloudStorageInterface
{
    private $accessKey;
    private $secretKey;
    private $endpoint;
    private $region;
    private $bucket;
    private $prefix;
    private $style;
    private $uploadMode;
    private $service = 's3';

    public function __construct($config)
    {
        $this->accessKey = isset($config['access_key']) ? $config['access_key'] : '';
        $this->secretKey = isset($config['secret_key']) ? $config['secret_key'] : '';
        $this->endpoint = rtrim(isset($config['endpoint']) ? $config['endpoint'] : '', '/');
        $this->region = isset($config['region']) && $config['region'] !== '' ? $config['region'] : 'us-east-1';
        $this->bucket = isset($config['bucket']) ? $config['bucket'] : '';
        $this->prefix = (isset($config['prefix']) && $config['prefix'] !== '') ? trim($config['prefix'], '/') . '/' : '';
        $this->style = (isset($config['style']) && $config['style'] === 'virtual-host') ? 'virtual-host' : 'path';
        $this->uploadMode = (isset($config['upload_mode']) && $config['upload_mode'] === 'direct') ? 'direct' : 'relay';
    }

    public function uploadMode()
    {
        return $this->uploadMode;
    }

    private function hostOnly()
    {
        return preg_replace('#^https?://#', '', $this->endpoint);
    }

    private function host()
    {
        if ($this->style === 'virtual-host') {
            return $this->bucket . '.' . $this->hostOnly();
        }
        return $this->hostOnly();
    }

    private function objectKey($file_path)
    {
        return $this->prefix . ltrim($file_path, '/');
    }

    // 构造对象 URL（用于公开访问）
    private function buildUrl($key, $query = array())
    {
        if ($this->style === 'virtual-host') {
            $base = 'https://' . $this->host();
        } else {
            $base = $this->endpoint . '/' . $this->bucket;
        }
        $url = $base . '/' . $key;
        if (!empty($query)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
        }
        return $url;
    }

    // 对象在请求中的 URI（不含查询串）
    private function objectUri($key)
    {
        if ($this->style === 'virtual-host') {
            return '/' . ltrim($key, '/');
        }
        return '/' . $this->bucket . '/' . ltrim($key, '/');
    }

    private function hmac($key, $data)
    {
        return hash_hmac('sha256', $data, $key, true);
    }

    private function signingKey($date)
    {
        $kDate = $this->hmac('AWS4' . $this->secretKey, $date);
        $kRegion = $this->hmac($kDate, $this->region);
        $kService = $this->hmac($kRegion, $this->service);
        return $this->hmac($kService, 'aws4_request');
    }

    // 构造规范查询字符串（AWS 要求按字母序、rawurlencode）
    private function canonicalQuery($query)
    {
        if (empty($query)) {
            return '';
        }
        ksort($query);
        $parts = array();
        foreach ($query as $k => $v) {
            $parts[] = rawurlencode($k) . '=' . rawurlencode($v);
        }
        return implode('&', $parts);
    }

    /**
     * 计算 Authorization 头
     */
    private function signHeaders($method, $uri, $headers, $payloadHash)
    {
        $amzDate = gmdate('Ymd\THis\Z');
        $date = substr($amzDate, 0, 8);

        $lower = array();
        foreach ($headers as $k => $v) {
            $lower[strtolower($k)] = trim($v);
        }
        ksort($lower);
        $signedHeaders = array();
        $canonical = '';
        foreach ($lower as $k => $v) {
            $signedHeaders[] = $k;
            $canonical .= $k . ':' . $v . "\n";
        }
        $signedHeadersStr = implode(';', $signedHeaders);

        $canonicalRequest = $method . "\n" . $uri . "\n\n" . $canonical . "\n" . $signedHeadersStr . "\n" . $payloadHash;

        $credentialScope = $date . '/' . $this->region . '/' . $this->service . '/aws4_request';
        $stringToSign = "AWS4-HMAC-SHA256\n" . $amzDate . "\n" . $credentialScope . "\n" . hash('sha256', $canonicalRequest);
        $signature = hash_hmac('sha256', $stringToSign, $this->signingKey($date));

        return 'AWS4-HMAC-SHA256 Credential=' . $this->accessKey . '/' . $credentialScope
            . ', SignedHeaders=' . $signedHeadersStr . ', Signature=' . $signature;
    }

    // 发起签名 HTTP 请求
    private function request($method, $key, $headers = array(), $body = null, $bodyFile = null, $timeout = 300)
    {
        $host = $this->host();
        $uri = $this->objectUri($key);
        if ($bodyFile !== null) {
            $payloadHash = hash_file('sha256', $bodyFile);
        } elseif ($body === null || $body === '') {
            $payloadHash = hash('sha256', '');
        } else {
            $payloadHash = hash('sha256', $body);
        }

        $allHeaders = array('host' => $host, 'x-amz-date' => gmdate('Ymd\THis\Z'), 'x-amz-content-sha256' => $payloadHash);
        foreach ($headers as $k => $v) {
            $allHeaders[$k] = $v;
        }
        $allHeaders['authorization'] = $this->signHeaders($method, $uri, $allHeaders, $payloadHash);

        $url = $this->buildUrl($key);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);

        $headerLines = array();
        foreach ($allHeaders as $k => $v) {
            if ($k === 'host') {
                continue;
            }
            $headerLines[] = $k . ': ' . $v;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);

        if ($bodyFile !== null) {
            $fp = fopen($bodyFile, 'rb');
            if (!$fp) {
                throw new \Exception('无法读取待上传文件');
            }
            curl_setopt($ch, CURLOPT_PUT, true);
            curl_setopt($ch, CURLOPT_INFILE, $fp);
            curl_setopt($ch, CURLOPT_INFILESIZE, filesize($bodyFile));
        } elseif ($body !== null && $body !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        if (isset($fp) && is_resource($fp)) {
            fclose($fp);
        }
        curl_close($ch);
        return array('code' => $code, 'body' => $response, 'error' => $error);
    }

    public function upload($file_path, $save_path)
    {
        $key = $this->objectKey($save_path);
        $res = $this->request('PUT', $key, array('content-type' => get_mime(pathinfo($save_path, PATHINFO_EXTENSION))), null, $file_path, 3600);
        if ($res['code'] < 200 || $res['code'] >= 300) {
            throw new \Exception('S3 上传失败 HTTP ' . $res['code'] . ': ' . $this->errorMsg($res));
        }
        return $this->getUrl($save_path);
    }

    public function delete($file_path)
    {
        $key = $this->objectKey($file_path);
        $res = $this->request('DELETE', $key, array(), null, null, 60);
        if ($res['code'] === 404) {
            return true;
        }
        if ($res['code'] < 200 || $res['code'] >= 300) {
            throw new \Exception('S3 删除失败 HTTP ' . $res['code']);
        }
        return true;
    }

    public function getUrl($file_path)
    {
        return $this->buildUrl($this->objectKey($file_path));
    }

    // 预签名下载 URL（AWS Signature V4 Query）
    public function getDownloadUrl($file_path, $filename = null, $expires = 3600)
    {
        $key = $this->objectKey($file_path);
        $amzDate = gmdate('Ymd\THis\Z');
        $date = substr($amzDate, 0, 8);
        $host = $this->host();

        $query = array(
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $this->accessKey . '/' . $date . '/' . $this->region . '/' . $this->service . '/aws4_request',
            'X-Amz-Date' => $amzDate,
            'X-Amz-Expires' => (int)$expires,
            'X-Amz-SignedHeaders' => 'host',
        );
        if ($filename !== null && $filename !== '') {
            $query['response-content-disposition'] = 'attachment; filename="' . str_replace(array('"', "\r", "\n"), '', $filename) . '"';
        }

        $canonicalQuery = $this->canonicalQuery($query);
        $uri = $this->objectUri($key);
        $canonicalRequest = "GET\n" . $uri . "\n" . $canonicalQuery . "\nhost:" . $host . "\n\nhost\nUNSIGNED-PAYLOAD";
        $credentialScope = $date . '/' . $this->region . '/' . $this->service . '/aws4_request';
        $stringToSign = "AWS4-HMAC-SHA256\n" . $amzDate . "\n" . $credentialScope . "\n" . hash('sha256', $canonicalRequest);
        $signature = hash_hmac('sha256', $stringToSign, $this->signingKey($date));
        $query['X-Amz-Signature'] = $signature;

        return $this->buildUrl($key, $query);
    }

    public function exists($file_path)
    {
        try {
            $res = $this->request('HEAD', $this->objectKey($file_path), array(), null, null, 30);
            return $res['code'] === 200;
        } catch (\Exception $ex) {
            return false;
        }
    }

    public function getFileInfo($file_path)
    {
        $key = $this->objectKey($file_path);
        $ch = curl_init($this->buildUrl($key));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        $payloadHash = hash('sha256', '');
        $host = $this->host();
        $authorization = $this->signHeaders('HEAD', $this->objectUri($key), array(
            'host' => $host,
            'x-amz-date' => gmdate('Ymd\THis\Z'),
            'x-amz-content-sha256' => $payloadHash,
        ), $payloadHash);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'x-amz-date: ' . gmdate('Ymd\THis\Z'),
            'x-amz-content-sha256: ' . $payloadHash,
            'Authorization: ' . $authorization,
        ));
        $res = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        $type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if ($code !== 200) {
            return null;
        }
        return array(
            'size' => (int)$size,
            'mime' => $type,
            'path' => $file_path,
        );
    }

    // 浏览器直传：生成 POST Policy 表单字段
    public function getDirectUploadData($save_path, $maxSize = null)
    {
        $key = $this->objectKey($save_path);
        $amzDate = gmdate('Ymd\THis\Z');
        $date = substr($amzDate, 0, 8);
        $expiration = gmdate('Y-m-d\TH:i:s\Z', time() + 3600);

        $conditions = array(
            array('bucket' => $this->bucket),
            array('key' => $key),
            array('x-amz-algorithm' => 'AWS4-HMAC-SHA256'),
            array('x-amz-credential' => $this->accessKey . '/' . $date . '/' . $this->region . '/' . $this->service . '/aws4_request'),
            array('x-amz-date' => $amzDate),
        );
        if ($maxSize !== null && $maxSize > 0) {
            $conditions[] = array('content-length-range', 0, (int)$maxSize);
        }
        $policy = base64_encode(json_encode(array('expiration' => $expiration, 'conditions' => $conditions)));
        $signature = hash_hmac('sha256', $policy, $this->signingKey($date));

        if ($this->style === 'virtual-host') {
            $endpoint = 'https://' . $this->host();
        } else {
            $endpoint = $this->endpoint . '/' . $this->bucket;
        }

        return array(
            'endpoint' => $endpoint,
            'key' => $key,
            'fields' => array(
                'key' => $key,
                'policy' => $policy,
                'x-amz-algorithm' => 'AWS4-HMAC-SHA256',
                'x-amz-credential' => $this->accessKey . '/' . $date . '/' . $this->region . '/' . $this->service . '/aws4_request',
                'x-amz-date' => $amzDate,
                'x-amz-signature' => $signature,
            ),
        );
    }

    private function errorMsg($res)
    {
        $body = $res['body'];
        if ($body !== '' && is_string($body)) {
            $xml = @simplexml_load_string($body);
            if ($xml !== false && isset($xml->Message)) {
                return (string)$xml->Message;
            }
        }
        return $res['error'] !== '' ? $res['error'] : '未知错误';
    }
}
