<?php
/**
 * WebDAV 存储驱动（基于 cURL，无需 SDK）
 *
 * 配置参数:
 *   server     WebDAV 服务器地址（含协议）
 *   username   认证用户名
 *   password   认证密码
 *   root       远程目录前缀
 *   upload_mode 上传方式: relay 网站中转 / direct 直传
 */

namespace lib\CloudStorage;

class WebDAVStorage implements CloudStorageInterface
{
    private $server;
    private $username;
    private $password;
    private $root;
    private $uploadMode;

    public function __construct($config)
    {
        $this->server = rtrim(isset($config['server']) ? $config['server'] : '', '/');
        $this->username = isset($config['username']) ? $config['username'] : '';
        $this->password = isset($config['password']) ? $config['password'] : '';
        $this->root = isset($config['root']) ? trim($config['root'], '/') : '';
        $this->uploadMode = (isset($config['upload_mode']) && $config['upload_mode'] === 'direct') ? 'direct' : 'relay';
    }

    public function uploadMode()
    {
        return $this->uploadMode;
    }

    private function remotePath($file_path)
    {
        $path = '/' . $this->root;
        if ($this->root !== '') {
            $path .= '/';
        }
        $path .= ltrim($file_path, '/');
        return $path;
    }

    private function url($file_path)
    {
        return $this->server . $this->remotePath($file_path);
    }

    private function request($method, $url, $body = null, $bodyFile = null, $headers = array(), $timeout = 120)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        if ($this->username !== '') {
            curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        }

        $hs = array();
        foreach ($headers as $k => $v) {
            $hs[] = $k . ': ' . $v;
        }
        if ($bodyFile !== null) {
            $fp = fopen($bodyFile, 'rb');
            if (!$fp) {
                throw new \Exception('无法读取待上传文件');
            }
            curl_setopt($ch, CURLOPT_PUT, true);
            curl_setopt($ch, CURLOPT_INFILE, $fp);
            $size = filesize($bodyFile);
            curl_setopt($ch, CURLOPT_INFILESIZE, $size);
            $hs[] = 'Content-Length: ' . $size;
        } elseif ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            $hs[] = 'Content-Length: ' . strlen($body);
        }
        if (!empty($hs)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $hs);
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
        $res = $this->request('PUT', $this->url($save_path), null, $file_path, array(
            'Content-Type' => get_mime(pathinfo($save_path, PATHINFO_EXTENSION)),
        ), 3600);
        if ($res['code'] < 200 || $res['code'] >= 300) {
            throw new \Exception('WebDAV 上传失败 HTTP ' . $res['code'] . ': ' . ($res['error'] !== '' ? $res['error'] : $this->stripBody($res['body'])));
        }
        return $this->getUrl($save_path);
    }

    public function delete($file_path)
    {
        $res = $this->request('DELETE', $this->url($file_path), null, null, array(), 60);
        if ($res['code'] === 404) {
            return true;
        }
        if ($res['code'] < 200 || $res['code'] >= 300) {
            throw new \Exception('WebDAV 删除失败 HTTP ' . $res['code']);
        }
        return true;
    }

    public function getUrl($file_path)
    {
        return $this->url($file_path);
    }

    public function getDownloadUrl($file_path, $filename = null)
    {
        $url = $this->url($file_path);
        if ($this->username !== '') {
            $auth = rawurlencode($this->username) . ':' . rawurlencode($this->password);
            $url = preg_replace('#^(https?)://#', '$1://' . $auth . '@', $url);
        }
        if ($filename !== null && $filename !== '') {
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'download=1&filename=' . rawurlencode($filename);
        }
        return $url;
    }

    public function exists($file_path)
    {
        $res = $this->propfind($file_path);
        return $res !== null;
    }

    public function getFileInfo($file_path)
    {
        $xml = $this->propfind($file_path);
        if ($xml === null) {
            return null;
        }
        $ns = array(
            'd' => 'DAV:',
        );
        $data = array('path' => $file_path, 'size' => 0, 'mtime' => '', 'mime' => '');
        $sizes = $xml->xpath('//d:getcontentlength');
        if (!empty($sizes)) {
            $data['size'] = (int)$sizes[0];
        }
        $mtimes = $xml->xpath('//d:getlastmodified');
        if (!empty($mtimes)) {
            $data['mtime'] = (string)$mtimes[0];
        }
        $types = $xml->xpath('//d:getcontenttype');
        if (!empty($types)) {
            $data['mime'] = (string)$types[0];
        }
        return $data;
    }

    private function propfind($file_path)
    {
        $body = '<?xml version="1.0" encoding="utf-8"?>'
            . '<d:propfind xmlns:d="DAV:">'
            . '<d:prop><d:getcontentlength/><d:getlastmodified/><d:getcontenttype/></d:prop>'
            . '</d:propfind>';
        $res = $this->request('PROPFIND', $this->url($file_path), $body, null, array(
            'Depth' => '0',
            'Content-Type' => 'application/xml',
        ), 30);
        if ($res['code'] !== 207) {
            return null;
        }
        $xml = @simplexml_load_string($res['body']);
        if ($xml === false) {
            return null;
        }
        $xml->registerXPathNamespace('d', 'DAV:');
        return $xml;
    }

    private function stripBody($body)
    {
        if (!is_string($body)) {
            return '';
        }
        $body = preg_replace('/\s+/', ' ', $body);
        return mb_substr(trim($body), 0, 200);
    }
}
