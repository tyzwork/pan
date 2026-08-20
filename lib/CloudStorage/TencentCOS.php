<?php
/**
 * 腾讯云 COS 存储驱动（XML API V5，HMAC-SHA1 签名，基于 cURL）
 *
 * 配置参数:
 *   access_key    SecretId
 *   secret_key    SecretKey
 *   endpoint      COS 域名，如 https://cos.ap-guangzhou.myqcloud.com
 *   bucket        存储桶名称（含 APPID，如 mybucket-1250000000）
 *   region        区域，如 ap-guangzhou
 *   prefix        对象前缀（默认 file/）
 */

namespace lib\CloudStorage;

class TencentCOS implements CloudStorageInterface
{
    private $accessKey;
    private $secretKey;
    private $endpoint;
    private $bucket;
    private $region;
    private $prefix;

    public function __construct($config)
    {
        $this->accessKey = isset($config['access_key']) ? $config['access_key'] : '';
        $this->secretKey = isset($config['secret_key']) ? $config['secret_key'] : '';
        $this->endpoint = rtrim(isset($config['endpoint']) ? $config['endpoint'] : '', '/');
        $this->bucket = isset($config['bucket']) ? $config['bucket'] : '';
        $this->region = isset($config['region']) ? $config['region'] : 'ap-guangzhou';
        $this->prefix = (isset($config['prefix']) && $config['prefix'] !== '') ? trim($config['prefix'], '/') . '/' : '';
    }

    private function objectKey($file_path)
    {
        return $this->prefix . ltrim($file_path, '/');
    }

    private function host()
    {
        return $this->bucket . '.cos.' . $this->region . '.myqcloud.com';
    }

    private function baseUrl()
    {
        if ($this->endpoint !== '') {
            return $this->endpoint . '/' . $this->bucket;
        }
        return 'https://' . $this->host();
    }

    /**
     * COS V5 签名
     */
    private function sign($method, $uri, $query = array(), $headers = array())
    {
        $now = time();
        $keyTime = ($now - 60) . ';' . ($now + 3600);
        $signKey = hash_hmac('sha1', $keyTime, $this->secretKey);

        $method = strtolower($method);
        ksort($query);
        $queryParts = array();
        foreach ($query as $k => $v) {
            $queryParts[] = rawurlencode(strtolower($k)) . '=' . rawurlencode($v);
        }
        $queryString = implode('&', $queryParts);

        $lowerHeaders = array();
        foreach ($headers as $k => $v) {
            $lowerHeaders[strtolower($k)] = trim($v);
        }
        ksort($lowerHeaders);
        $headerParts = array();
        $headerList = array();
        foreach ($lowerHeaders as $k => $v) {
            if ($k === 'host' || $k === 'content-type' || strpos($k, 'x-cos-') === 0) {
                $headerParts[] = $k . '=' . $v;
                $headerList[] = $k;
            }
        }
        $headerString = implode('&', $headerParts);
        $headerListString = implode(';', $headerList);

        $urlParamList = implode(';', array_map(function ($k) {
            return strtolower($k);
        }, array_keys($query)));

        $httpString = $method . "\n" . $uri . "\n" . $queryString . "\n" . $headerString . "\n";
        $stringToSign = 'sha1' . "\n" . $keyTime . "\n" . sha1($httpString) . "\n";
        $signature = hash_hmac('sha1', $stringToSign, $signKey);

        return 'q-sign-algorithm=sha1&q-ak=' . rawurlencode($this->accessKey)
            . '&q-sign-time=' . $keyTime
            . '&q-key-time=' . $keyTime
            . '&q-header-list=' . $headerListString
            . '&q-url-param-list=' . $urlParamList
            . '&q-signature=' . $signature;
    }

    private function request($method, $key, $body = null, $bodyFile = null, $timeout = 3600)
    {
        $uri = '/' . $key;
        $headers = array('Host' => $this->host(), 'Content-Type' => get_mime(pathinfo($key, PATHINFO_EXTENSION)));
        $authorization = $this->sign($method, $uri, array(), $headers);

        $url = $this->baseUrl() . '/' . $key;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);

        $headerLines = array(
            'Host: ' . $this->host(),
            'Authorization: ' . $authorization,
            'Content-Type: ' . get_mime(pathinfo($key, PATHINFO_EXTENSION)),
        );

        if ($bodyFile !== null) {
            $fp = fopen($bodyFile, 'rb');
            if (!$fp) {
                throw new \Exception('无法读取待上传文件');
            }
            $size = filesize($bodyFile);
            curl_setopt($ch, CURLOPT_PUT, true);
            curl_setopt($ch, CURLOPT_INFILE, $fp);
            curl_setopt($ch, CURLOPT_INFILESIZE, $size);
            $headerLines[] = 'Content-Length: ' . $size;
        } elseif ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            $headerLines[] = 'Content-Length: ' . strlen($body);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);

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
        $res = $this->request('PUT', $key, null, $file_path);
        if ($res['code'] < 200 || $res['code'] >= 300) {
            throw new \Exception('COS 上传失败 HTTP ' . $res['code'] . ': ' . $res['error']);
        }
        return $this->getUrl($save_path);
    }

    public function delete($file_path)
    {
        $key = $this->objectKey($file_path);
        $res = $this->request('DELETE', $key, '', null, 60);
        if ($res['code'] === 404) {
            return true;
        }
        if ($res['code'] < 200 || $res['code'] >= 300) {
            throw new \Exception('COS 删除失败 HTTP ' . $res['code']);
        }
        return true;
    }

    public function getUrl($file_path)
    {
        return $this->baseUrl() . '/' . $this->objectKey($file_path);
    }

    public function getDownloadUrl($file_path, $filename = null, $expires = 3600)
    {
        $key = $this->objectKey($file_path);
        $query = array();
        if ($filename !== null && $filename !== '') {
            $query['response-content-disposition'] = 'attachment; filename="' . str_replace(array('"', "\r", "\n"), '', $filename) . '"';
        }
        $uri = '/' . $key;
        $headers = array('Host' => $this->host());
        $authorization = $this->sign('GET', $uri, $query, $headers);
        $url = $this->getUrl($file_path) . (strpos($this->getUrl($file_path), '?') === false ? '?' : '&') . http_build_query($query) . '&' . $authorization;
        return $url;
    }

    public function exists($file_path)
    {
        $key = $this->objectKey($file_path);
        $uri = '/' . $key;
        $headers = array('Host' => $this->host());
        $authorization = $this->sign('HEAD', $uri, array(), $headers);
        $ch = curl_init($this->baseUrl() . '/' . $key);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Host: ' . $this->host(), 'Authorization: ' . $authorization));
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code === 200;
    }

    public function getFileInfo($file_path)
    {
        $key = $this->objectKey($file_path);
        $uri = '/' . $key;
        $headers = array('Host' => $this->host());
        $authorization = $this->sign('HEAD', $uri, array(), $headers);
        $ch = curl_init($this->baseUrl() . '/' . $key);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Host: ' . $this->host(), 'Authorization: ' . $authorization));
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        $type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        if ($code !== 200) {
            return null;
        }
        return array('size' => (int)$size, 'mime' => $type, 'path' => $file_path);
    }
}
