<?php
/**
 * 文本内容 API
 * 支持: 读取文本文件内容、保存修改（写回存储后端）
 */

require_once dirname(__FILE__) . '/../config.php';
require_once dirname(__FILE__) . '/../lib/db.php';
require_once dirname(__FILE__) . '/../lib/functions.php';
require_once dirname(__FILE__) . '/../lib/auth.php';

$user = current_user();
if (!$user) {
    json_response(null, 401, '请先登录');
}

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

$file = DB::instance()->fetch('SELECT * FROM `' . DB_PREFIX . 'files` WHERE `id` = ?', array($id));
if (!$file) {
    json_response(null, 404, '文件不存在');
}

if (!is_text_ext($file['ext'])) {
    json_response(null, 400, '该文件不是文本类型');
}

$isOwner = (int)$file['uid'] === (int)$user['id'];
$isAdmin = !empty($user['is_admin']);

if ($action === 'get') {
    $storage = \lib\CloudStorage\StorageManager::getInstance($file['storage_type']);
    $content = readRemoteText($storage, $file['storage_path']);
    if ($content === false) {
        json_response(null, 500, '读取文本内容失败');
    }
    json_response(array(
        'content' => $content,
        'filename' => $file['filename'],
        'ext' => $file['ext'],
        'can_edit' => $isOwner || $isAdmin,
    ), 0, 'ok');
}

if ($action === 'save') {
    if (!$isOwner && !$isAdmin) {
        json_response(null, 403, '无权编辑该文件');
    }
    check_csrf();
    $content = isset($_POST['content']) ? $_POST['content'] : '';
    if (strlen($content) > 10 * 1024 * 1024) {
        json_response(null, 400, '内容过大');
    }
    $storage = \lib\CloudStorage\StorageManager::getInstance($file['storage_type']);
    if (!writeRemoteText($storage, $file['storage_path'], $content)) {
        json_response(null, 500, '写入存储失败');
    }
    json_response(null, 0, '保存成功');
}

json_response(null, 400, '未知操作');

// 读取远端文本内容（本地走文件，远端走 cURL GET）
function readRemoteText($storage, $path)
{
    if (get_class($storage) === 'lib\CloudStorage\LocalStorage') {
        $cfg = \lib\CloudStorage\StorageManager::getConfig('local');
        $root = rtrim(isset($cfg['base_path']) ? $cfg['base_path'] : (dirname(dirname(__FILE__)) . '/uploads'), '/');
        $full = $root . '/' . $path;
        if (!is_file($full)) {
            return false;
        }
        return file_get_contents($full);
    }
    $url = $storage->getDownloadUrl($path, null, 300);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code === 200 ? $res : false;
}

// 写回远端文本内容（本地直接写文件，远端 PUT）
function writeRemoteText($storage, $path, $content)
{
    if (get_class($storage) === 'lib\CloudStorage\LocalStorage') {
        $cfg = \lib\CloudStorage\StorageManager::getConfig('local');
        $root = rtrim(isset($cfg['base_path']) ? $cfg['base_path'] : (dirname(dirname(__FILE__)) . '/uploads'), '/');
        $full = $root . '/' . $path;
        if (!is_dir(dirname($full))) {
            mkdir(dirname($full), 0777, true);
        }
        return file_put_contents($full, $content) !== false;
    }
    $url = $storage->getUrl($path);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/plain; charset=utf-8'));
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code >= 200 && $code < 300;
}
