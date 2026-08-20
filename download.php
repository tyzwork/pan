<?php
/**
 * 本地存储文件下载（强制下载，支持防盗链计数）
 */

require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/lib/db.php';
require_once dirname(__FILE__) . '/lib/functions.php';

$filePath = isset($_GET['f']) ? $_GET['f'] : '';
$filename = isset($_GET['n']) ? $_GET['n'] : '';

if ($filePath === '' || strpos($filePath, '..') !== false) {
    http_response_code(400);
    exit('invalid');
}

$row = DB::instance()->fetch('SELECT * FROM `' . DB_PREFIX . 'files` WHERE `storage_path` = ? AND `storage_type` = ?', array($filePath, 'local'));
if (!$row) {
    http_response_code(404);
    exit('not found');
}

$cfg = \lib\CloudStorage\StorageManager::getConfig('local');
$base = rtrim(isset($cfg['base_path']) && $cfg['base_path'] !== '' ? $cfg['base_path'] : (dirname(__FILE__) . '/uploads'), '/');
$full = $base . '/' . $filePath;
if (!is_file($full)) {
    http_response_code(404);
    exit('file missing');
}
// 下载次数限制
if ((int)$row['dl_limit'] > 0 && (int)$row['dl_count'] >= (int)$row['dl_limit']) {
    http_response_code(403);
    exit('download limit reached');
}

// 下载计数
DB::instance()->execute('UPDATE `' . DB_PREFIX . 'files` SET `dl_count` = `dl_count` + 1 WHERE `id` = ?', array($row['id']));

$name = $filename !== '' ? $filename : $row['filename'];
$name = str_replace(array("\r", "\n", '"'), '', $name);

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Content-Length: ' . filesize($full));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache');

$fp = fopen($full, 'rb');
while (!feof($fp)) {
    echo fread($fp, 8192);
    flush();
}
fclose($fp);
exit;
