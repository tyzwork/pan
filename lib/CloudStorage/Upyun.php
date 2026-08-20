<?php
/**
 * 又拍云存储驱动（基于 cURL）
 *
 * 配置参数:
 *   operator      操作员名
 *   password      操作员密码
 *   bucket        服务名（存储桶）
 *   domain        访问域名（CDN/加速域名，用于生成外链）
 *   endpoint      上传 API 地址，默认 https://v0.api.upyun.com
 *   prefix        目录前缀（默认 file/）
 */

namespace lib\CloudStorage;

class Upyun implements CloudStorageInterface
{
    private $operator;
    private $password;
    private $bucket;
    private $domain;
    private $endpoint;
    private $prefix;

    public function __construct($config)
    {
        $this->operator = isset($config['operator']) ? $config['operator'] : '';
        $this->password = isset($config['password']) ? $config['password'] : '';
        $this->bucket = isset($config['bucket']) ? $config['bucket'] : '';
        $this->domain = rtrim(isset($config['domain']) ? $config['domain'] : '', '/');
        $this->endpoint = rtrim(isset($config['endpoint']) && $config['endpoint'] !== '' ? $config['endpoint'] : 'https://v0.api.upyun.com', '/');
        $this->prefix = (isset($config['prefix']) && $config['prefix'] !== '') ? trim($config['prefix'], '/') . '/' : '';
    }

    private function objectKey($file_path)
    {
        return $this->prefix . ltrim($file_path, '/');
    }

    private function auth($method, $contentMd5 = '', $length = 0)
    {
        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $signature = md5(md5($this->password) . '&' . $date . '&' . $contentMd5 . '&' . $length);
        return array('date' => $date, 'signature' => $signature);
    }

    private function request($method, $key, $body = null, $bodyFile = null, $timeout = 3600)
    {
        $url = $this->endpoint . '/' . $this->bucket . '/' . $key;
        $contentMd5 = '';
        $length = 0;
        if ($bodyFile !== null) {
            $length = filesize($bodyFile);
        } elseif ($body !== null) {
            $length = strlen($body);
            $contentMd5 = md5($body);
        }
        $auth = $this->auth($method, $contentMd5, $length);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);

        $headers = array(
            'Date: ' . $auth['date'],
            'Authorization: UPYUN ' . $this->operator . ':' . $auth['signature'],
            'Content-Length: ' . $length,
            'Content-Type: ' . get_mime(pathinfo($key, PATHINFO_EXTENSION)),
        );

        if ($bodyFile !== null) {
            $fp = fopen($bodyFile, 'rb');
            if (!$fp) {
                throw new \Exception('无法读取待上传文件');
            }
            curl_setopt($ch, CURLOPT_PUT, true);
            curl_setopt($ch, CURLOPT_INFILE, $fp);
            curl_setopt($ch, CURLOPT_INFILESIZE, $length);
        } elseif ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
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
            throw new \Exception('又拍云上传失败 HTTP ' . $res['code'] . ': ' . $res['error']);
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
            throw new \Exception('又拍云删除失败 HTTP ' . $res['code']);
        }
        return true;
    }

    public function getUrl($file_path)
    {
        $domain = $this->domain !== '' ? $this->domain : ($this->endpoint . '/' . $this->bucket);
        return $domain . '/' . $this->objectKey($file_path);
    }

    public function getDownloadUrl($file_path, $filename = null, $expires = 3600)
    {
        $url = $this->getUrl($file_path);
        $etime = time() + (int)$expires;
        $signature = md5(md5($this->password) . '&' . $etime);
        $auth = base64_encode($this->operator . ':' . $signature);
        $url .= (strpos($url, '?') === false ? '?' : '&') . '_upt=' . rawurlencode($auth) . '&_upd=' . $etime;
        return $url;
    }

    public function exists($file_path)
    {
        $key = $this->objectKey($file_path);
        $res = $this->request('HEAD', $key, '', null, 30);
        return $res['code'] === 200;
    }

    public function getFileInfo($file_path)
    {
        $key = $this->objectKey($file_path);
        $url = $this->endpoint . '/' . $this->bucket . '/' . $key;
        $auth = $this->auth('HEAD', '', 0);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Date: ' . $auth['date'],
            'Authorization: UPYUN ' . $this->operator . ':' . $auth['signature'],
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
