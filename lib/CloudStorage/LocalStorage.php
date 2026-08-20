<?php
/**
 * 本地服务器存储驱动
 */

namespace lib\CloudStorage;

class LocalStorage implements CloudStorageInterface
{
    private $config;
    private $basePath;
    private $baseUrl;

    public function __construct($config)
    {
        $this->config = $config;
        $root = dirname(dirname(dirname(__FILE__)));
        $this->basePath = rtrim(isset($config['base_path']) && $config['base_path'] !== '' ? $config['base_path'] : ($root . '/uploads'), '/');
        $this->baseUrl = rtrim(isset($config['base_url']) && $config['base_url'] !== '' ? $config['base_url'] : (site_url('uploads')), '/');
    }

    public function upload($file_path, $save_path)
    {
        $dest = $this->basePath . '/' . $save_path;
        if (!is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0777, true);
        }
        if (!rename($file_path, $dest)) {
            if (!copy($file_path, $dest)) {
                throw new \Exception('本地存储写入失败');
            }
            @unlink($file_path);
        }
        @chmod($dest, 0644);
        return $this->getUrl($save_path);
    }

    public function delete($file_path)
    {
        $full = $this->basePath . '/' . $file_path;
        if (is_file($full)) {
            return @unlink($full);
        }
        return true;
    }

    public function getUrl($file_path)
    {
        return $this->baseUrl . '/' . ltrim($file_path, '/');
    }

    public function getDownloadUrl($file_path, $filename = null)
    {
        $url = site_url('download.php') . '?f=' . rawurlencode($file_path);
        if ($filename !== null && $filename !== '') {
            $url .= '&n=' . rawurlencode($filename);
        }
        return $url;
    }

    public function exists($file_path)
    {
        return file_exists($this->basePath . '/' . $file_path);
    }

    public function getFileInfo($file_path)
    {
        $full = $this->basePath . '/' . $file_path;
        if (!is_file($full)) {
            return null;
        }
        return array(
            'size' => filesize($full),
            'mtime' => filemtime($full),
            'path' => $file_path,
        );
    }
}
