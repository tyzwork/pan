<?php
/**
 * 文件上传核心逻辑
 * 支持: 分块上传 + 断点续传、秒传（Hash 去重）、网站中转、浏览器直传
 */

require_once dirname(__FILE__) . '/functions.php';

class Uploader
{
    private $tmpDir;

    public function __construct()
    {
        $this->tmpDir = dirname(dirname(__FILE__)) . '/data/tmp';
        if (!is_dir($this->tmpDir)) {
            mkdir($this->tmpDir, 0777, true);
        }
    }

    // 校验文件扩展名（白名单 + 黑名单）
    public function checkExt($ext)
    {
        $ext = strtolower(trim($ext));
        if ($ext === '') {
            return array(false, '无法识别文件扩展名');
        }
        $forbidden = array_filter(array_map('trim', explode(',', get_setting('forbidden_extensions', FORBIDDEN_EXTENSIONS))));
        if (in_array($ext, $forbidden, true)) {
            return array(false, '禁止上传该类型的文件');
        }
        $allow = get_setting('allow_extensions', ALLOW_EXTENSIONS);
        if ($allow !== '' && strtolower($allow) !== 'all') {
            $list = array_filter(array_map('trim', explode(',', $allow)));
            if (!empty($list) && !in_array($ext, $list, true)) {
                return array(false, '不允许上传该类型的文件');
            }
        }
        return array(true, '');
    }

    // 生成安全文件名
    public function safeName($filename)
    {
        $filename = trim($filename);
        $filename = str_replace(array('\\', '/', ':', '*', '?', '"', '<', '>', '|'), '_', $filename);
        $filename = preg_replace('/[\x00-\x1f]/', '', $filename);
        $filename = mb_substr($filename, 0, 200);
        if ($filename === '' || $filename === '.') {
            $filename = 'file_' . date('YmdHis') . '_' . random_str(6);
        }
        return $filename;
    }

    // 秒传检测：返回相同 hash 的文件记录
    public function findDuplicated($hash)
    {
        if ($hash === '') {
            return null;
        }
        return DB::instance()->fetch('SELECT * FROM `' . DB_PREFIX . 'files` WHERE `hash` = ? LIMIT 1', array($hash));
    }

    // 处理单个分块
    public function handleChunk($uid, $filename, $index, $total, $hash, $chunkFile)
    {
        $session = md5($uid . '|' . $filename . '|' . $hash);
        $chunkDir = $this->tmpDir . '/' . $session;
        if (!is_dir($chunkDir)) {
            mkdir($chunkDir, 0777, true);
        }
        $target = $chunkDir . '/' . $index . '.part';
        if (is_file($target)) {
            @unlink($target);
        }
        if (!rename($chunkFile, $target) && !copy($chunkFile, $target)) {
            return array(false, '分块保存失败');
        }
        @unlink($chunkFile);

        // 检查是否所有分块已上传
        for ($i = 0; $i < $total; $i++) {
            if (!is_file($chunkDir . '/' . $i . '.part')) {
                return array(true, array('done' => false, 'received' => $this->receivedCount($chunkDir, $total)));
            }
        }
        return array(true, array('done' => true, 'dir' => $chunkDir, 'total' => $total));
    }

    private function receivedCount($dir, $total)
    {
        $count = 0;
        for ($i = 0; $i < $total; $i++) {
            if (is_file($dir . '/' . $i . '.part')) {
                $count++;
            }
        }
        return $count;
    }

    // 合并分块并返回最终文件路径
    public function mergeChunks($dir, $total, $destFile)
    {
        $fp = fopen($destFile, 'wb');
        if (!$fp) {
            return false;
        }
        for ($i = 0; $i < $total; $i++) {
            $part = $dir . '/' . $i . '.part';
            if (!is_file($part)) {
                fclose($fp);
                return false;
            }
            $partFp = fopen($part, 'rb');
            while (!feof($partFp)) {
                fwrite($fp, fread($partFp, 1048576));
            }
            fclose($partFp);
            @unlink($part);
        }
        fclose($fp);
        @rmdir($dir);
        return true;
    }

    // 获取上传元信息（浏览器直传时获取直传参数）
    public function directUploadData($storageType, $savePath, $maxSize = null)
    {
        $storage = \lib\CloudStorage\StorageManager::getInstance($storageType);
        if (method_exists($storage, 'getDirectUploadData')) {
            return $storage->getDirectUploadData($savePath, $maxSize);
        }
        return null;
    }

    // 清理临时目录
    public function cleanup()
    {
        $dirs = glob($this->tmpDir . '/*');
        foreach ($dirs as $dir) {
            if (is_dir($dir) && filemtime($dir) < time() - 86400) {
                array_map('unlink', glob($dir . '/*'));
                @rmdir($dir);
            }
        }
    }
}
