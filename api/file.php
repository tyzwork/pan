<?php
/**
 * 文件操作 API
 * 支持: 删除（校验归属）、重命名、状态切换、文件详情（后台/前台）
 */

require_once dirname(__FILE__) . '/../config.php';
require_once dirname(__FILE__) . '/../lib/db.php';
require_once dirname(__FILE__) . '/../lib/functions.php';
require_once dirname(__FILE__) . '/../lib/auth.php';

// 当前身份：前台用户或后台管理员
$user = current_user();
$isAdmin = admin_logged();
if (!$user && !$isAdmin) {
    json_response(null, 401, '请先登录');
}

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');
$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

$file = DB::instance()->fetch('SELECT * FROM `' . DB_PREFIX . 'files` WHERE `id` = ?', array($id));
if (!$file) {
    json_response(null, 404, '文件不存在');
}
$isOwner = $user && (int)$file['uid'] === (int)$user['id'];
$canManage = $isOwner || $isAdmin;

// 文件详情（外链、下载链接等）
if ($action === 'info') {
    $ext = strtolower($file['ext']);
    try {
        $storage = \lib\CloudStorage\StorageManager::getInstance($file['storage_type']);
        $url = $storage->getUrl($file['storage_path']);
        $downloadUrl = $storage->getDownloadUrl($file['storage_path'], $file['filename']);
    } catch (Exception $ex) {
        $url = '';
        $downloadUrl = '';
    }
    $uploader = DB::instance()->fetch('SELECT `username` FROM `' . DB_PREFIX . 'users` WHERE `id` = ?', array($file['uid']));
    json_response(array(
        'id' => (int)$file['id'],
        'filename' => $file['filename'],
        'filesize' => (int)$file['filesize'],
        'size' => format_size($file['filesize']),
        'mime' => $file['mime'],
        'ext' => $ext,
        'username' => $uploader ? $uploader['username'] : '游客',
        'storage_type' => $file['storage_type'],
        'storage_path' => $file['storage_path'],
        'dl_count' => (int)$file['dl_count'],
        'dl_limit' => (int)$file['dl_limit'],
        'status' => (int)$file['status'],
        'created_at' => $file['created_at'],
        'url' => $url,
        'download_url' => $downloadUrl,
        'previewable' => is_preview_ext($ext),
    ), 0, 'ok');
}

// 需要文件归属权/管理员才能执行写操作
if (!$canManage) {
    json_response(null, 403, '无权操作该文件');
}

if ($action === 'delete') {
    check_csrf();
    // 删除存储对象（若没有其他记录引用同一对象）
    $refCount = DB::instance()->count(
        'files',
        '`storage_type` = ? AND `storage_path` = ? AND `id` != ?',
        array($file['storage_type'], $file['storage_path'], $id)
    );
    if ($refCount === 0) {
        try {
            $storage = \lib\CloudStorage\StorageManager::getInstance($file['storage_type']);
            $storage->delete($file['storage_path']);
        } catch (Exception $ex) {
            // 存储删除失败不阻塞记录删除
        }
    }
    DB::instance()->delete('files', '`id` = ?', array($id));
    json_response(null, 0, '删除成功');
}

if ($action === 'rename') {
    check_csrf();
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    if ($name === '' || mb_strlen($name) > 200) {
        json_response(null, 400, '文件名不合法');
    }
    $name = str_replace(array('\\', '/', ':', '*', '?', '"', '<', '>', '|'), '_', $name);
    DB::instance()->update('files', array('filename' => $name), '`id` = ?', array($id));
    json_response(array('filename' => $name), 0, '重命名成功');
}

if ($action === 'toggle') {
    check_csrf();
    $status = (int)$file['status'] === 1 ? 0 : 1;
    DB::instance()->update('files', array('status' => $status), '`id` = ?', array($id));
    json_response(array('status' => $status), 0, '操作成功');
}

json_response(null, 400, '未知操作');
