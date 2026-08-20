<?php
/**
 * 七牛云存储驱动（基于 cURL，HMAC-SHA1）
 *
 * 配置参数:
 *   access_key    AccessKey
 *   secret_key    SecretKey
 *   bucket        空间名称
 *   domain        访问域名（用于生成外链，如 https://cdn.example.com）
 *   region        上传区域: z0 华东 / z1 华北 / z2 华南 / na0 北美 / as0 东南亚
 *   prefix        目录前缀（默认 file/）
 */

namespace lib\CloudStorage;

class Qiniu implements CloudStorageInterface
{
    private $accessKey;
    private $secretKey;
    private $bucket;
    private $domain;
    private $region;
    private $prefix;

    private $uploadHosts = array(
        'z0' => 'upload.qiniup.com',
        'z1' => 'upload-z1.qiniup.com',
        'z2' => 'upload-z2.qiniup.com',
        'na0' => 'upload-na0.qiniup.com',
        'as0' => 'upload-as0.qiniup.com',
    );

    public function __construct($config)
    {
        $this->accessKey = isset($config['access_key']) ? $config['access_key'] : '';
        $this->secretKey = isset($config['secret_key']) ? $config['secret_key'] : '';
        $this->bucket = isset($config['bucket']) ? $config['bucket'] : '';
        $this->domain = rtrim(isset($config['domain']) ? $config['domain'] : '', '/');
        $this->region = isset($config['region']) && $config['region'] !== '' ? $config['region'] : 'z0';
        $this->prefix = (isset($config['prefix']) && $config['prefix'] !== '') ? trim($config['prefix'], '/') . '/' : '';
    }

    private function objectKey($file_path)
    {
        return $this->prefix . ltrim($file_path, '/');
    }

    private function base64Url($data)
    {
        return str_replace(array('+', '/'), array('-', '_'), base64_encode($data));
    }

    private function sign($data)
    {
        return $this->base64Url(hash_hmac('sha1', $data, $this->secretKey, true));
    }

    // 生成上传凭证
    private function uploadToken($key)
    {
        $scope = $this->bucket . ':' . $key;
        $deadline = time() + 3600;
        $putPolicy = json_encode(array('scope' => $scope, 'deadline' => $deadline));
        $encodedPolicy = $this->base64Url($putPolicy);
        $sign = $this->sign($encodedPolicy);
        return $this->accessKey . ':' . $sign . ':' . $encodedPolicy;
    }

    // 管理凭证（delete/stat）
    private function manageToken($url)
    {
        $sign = $this->sign($url);
        return $this->accessKey . ':' . $sign;
    }

    private function uploadHost()
    {
        return isset($this->uploadHosts[$this->region]) ? $this->uploadHosts[$this->region] : 'upload.qiniup.com';
    }

    public function upload($file_path, $save_path)
    {
        $key = $this->objectKey($save_path);
        $token = $this->uploadToken($key);
        $url = 'https://' . $this->uploadHost() . '/put/' . $this->base64Url($key);

        $fp = fopen($file_path, 'rb');
        if (!$fp) {
            throw new \Exception('无法读取待上传文件');
        }
        $size = filesize($file_path);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3600);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_PUT, true);
        curl_setopt($ch, CURLOPT_INFILE, $fp);
        curl_setopt($ch, CURLOPT_INFILESIZE, $size);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: UpToken ' . $token,
            'Content-Type: application/octet-stream',
            'Content-Length: ' . $size,
        ));
        $response = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        fclose($fp);
        curl_close($ch);
        if ($code < 200 || $code >= 300) {
            throw new \Exception('七牛云上传失败 HTTP ' . $code . ': ' . ($error !== '' ? $error : $response));
        }
        return $this->getUrl($save_path);
    }

    public function delete($file_path)
    {
        $key = $this->objectKey($file_path);
        $encoded = $this->base64Url($this->bucket . ':' . $key);
        $url = 'https://rs.qiniu.com/delete/' . $encoded;
        $token = $this->manageToken($url);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: QBox ' . $token));
        $response = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code === 612) {
            return true;
        }
        if ($code < 200 || $code >= 300) {
            throw new \Exception('七牛云删除失败 HTTP ' . $code . ': ' . $response);
        }
        return true;
    }

    public function getUrl($file_path)
    {
        return $this->domain . '/' . $this->objectKey($file_path);
    }

    public function getDownloadUrl($file_path, $filename = null, $expires = 3600)
    {
        $url = $this->getUrl($file_path);
        $deadline = time() + (int)$expires;
        $sign = $this->sign($url . '?e=' . $deadline);
        $token = $this->accessKey . ':' . $sign;
        return $url . '?e=' . $deadline . '&token=' . $token;
    }

    public function exists($file_path)
    {
        $info = $this->getFileInfo($file_path);
        return $info !== null;
    }

    public function getFileInfo($file_path)
    {
        $key = $this->objectKey($file_path);
        $encoded = $this->base64Url($this->bucket . ':' . $key);
        $url = 'https://rs.qiniu.com/stat/' . $encoded;
        $token = $this->manageToken($url);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: QBox ' . $token));
        $response = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200) {
            return null;
        }
        $data = json_decode($response, true);
        return array(
            'size' => isset($data['fsize']) ? (int)$data['fsize'] : 0,
            'mime' => isset($data['mimeType']) ? $data['mimeType'] : '',
            'hash' => isset($data['hash']) ? $data['hash'] : '',
            'path' => $file_path,
        );
    }
}
