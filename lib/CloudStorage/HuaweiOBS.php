<?php
/**
 * 华为云 OBS 存储驱动（AWS 风格 V2 签名，HMAC-SHA1，基于 cURL）
 *
 * 配置参数:
 *   access_key    Access Key
 *   secret_key    Secret Key
 *   endpoint      OBS 域名，如 https://obs.cn-north-4.myhuaweicloud.com
 *   bucket        存储桶名称
 *   prefix        对象前缀（默认 file/）
 */

namespace lib\CloudStorage;

class HuaweiOBS implements CloudStorageInterface
{
    private $accessKey;
    private $secretKey;
    private $endpoint;
    private $bucket;
    private $prefix;

    public function __construct($config)
    {
        $this->accessKey = isset($config['access_key']) ? $config['access_key'] : '';
        $this->secretKey = isset($config['secret_key']) ? $config['secret_key'] : '';
        $this->endpoint = rtrim(isset($config['endpoint']) ? $config['endpoint'] : '', '/');
        $this->bucket = isset($config['bucket']) ? $config['bucket'] : '';
        $this->prefix = (isset($config['prefix']) && $config['prefix'] !== '') ? trim($config['prefix'], '/') . '/' : '';
    }

    private function objectKey($file_path)
    {
        return $this->prefix . ltrim($file_path, '/');
    }

    private function resource($key)
    {
        return '/' . $this->bucket . '/' . $key;
    }

    private function baseUrl()
    {
        return $this->endpoint . '/' . $this->bucket;
    }

    private function sign($stringToSign)
    {
        return base64_encode(hash_hmac('sha1', $stringToSign, $this->secretKey, true));
    }

    private function request($method, $key, $body = null, $bodyFile = null, $timeout = 3600)
    {
        $contentType = get_mime(pathinfo($key, PATHINFO_EXTENSION));
        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $stringToSign = $method . "\n\n" . $contentType . "\n" . $date . "\n" . $this->resource($key);
        $signature = $this->sign($stringToSign);

        $ch = curl_init($this->baseUrl() . '/' . $key);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);

        $headers = array(
            'Date: ' . $date,
            'Content-Type: ' . $contentType,
            'Authorization: OBS ' . $this->accessKey . ':' . $signature,
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
            $headers[] = 'Content-Length: ' . $size;
        } elseif ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            $headers[] = 'Content-Length: ' . strlen($body);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

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
            throw new \Exception('OBS 上传失败 HTTP ' . $res['code'] . ': ' . $res['error']);
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
            throw new \Exception('OBS 删除失败 HTTP ' . $res['code']);
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
        $expire = time() + (int)$expires;
        $stringToSign = "GET\n\n\n" . $expire . "\n" . $this->resource($key);
        $signature = $this->sign($stringToSign);
        $url = $this->getUrl($file_path) . (strpos($this->getUrl($file_path), '?') === false ? '?' : '&')
            . 'AccessKeyId=' . rawurlencode($this->accessKey)
            . '&Expires=' . $expire
            . '&Signature=' . rawurlencode($signature);
        if ($filename !== null && $filename !== '') {
            $url .= '&response-content-disposition=' . rawurlencode('attachment; filename="' . str_replace(array('"', "\r", "\n"), '', $filename) . '"');
        }
        return $url;
    }

    public function exists($file_path)
    {
        $key = $this->objectKey($file_path);
        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $stringToSign = "HEAD\n\n\n" . $date . "\n" . $this->resource($key);
        $signature = $this->sign($stringToSign);
        $ch = curl_init($this->baseUrl() . '/' . $key);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Date: ' . $date,
            'Authorization: OBS ' . $this->accessKey . ':' . $signature,
        ));
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code === 200;
    }

    public function getFileInfo($file_path)
    {
        $key = $this->objectKey($file_path);
        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $stringToSign = "HEAD\n\n\n" . $date . "\n" . $this->resource($key);
        $signature = $this->sign($stringToSign);
        $ch = curl_init($this->baseUrl() . '/' . $key);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Date: ' . $date,
            'Authorization: OBS ' . $this->accessKey . ':' . $signature,
        ));
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
