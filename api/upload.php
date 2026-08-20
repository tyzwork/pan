<?php
/**
 * 上传 API
 * 支持: 分块上传 + 断点续传、秒传（Hash 去重）、浏览器直传记录
 */

require_once dirname(__FILE__) . '/../config.php';
require_once dirname(__FILE__) . '/../lib/db.php';
require_once dirname(__FILE__) . '/../lib/functions.php';
require_once dirname(__FILE__) . '/../lib/auth.php';
require_once dirname(__FILE__) . '/../lib/upload.php';

$user = current_user();
if (!$user) {
    json_response(null, 401, '请先登录');
}

$action = isset($_POST['action']) ? $_POST['action'] : 'chunk';
$uploader = new Uploader();

// 秒传检测
if (isset($_POST['hash']) && $_POST['hash'] !== '') {
    $dup = $uploader->findDuplicated($_POST['hash']);
    if ($dup && (int)$dup['status'] === 1) {
        // 校验扩展名
        $filename = isset($_POST['filename']) ? $uploader->safeName($_POST['filename']) : '';
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        list($ok, $msg) = $uploader->checkExt($ext);
        if (!$ok) {
            json_response(null, 400, $msg);
        }
        $newId = DB::instance()->insert('files', array(
            'uid' => (int)$user['id'],
            'filename' => $filename,
            'filesize' => (int)$dup['filesize'],
            'mime' => get_mime($ext),
            'ext' => $ext,
            'storage_type' => $dup['storage_type'],
            'storage_path' => $dup['storage_path'],
            'hash' => $dup['hash'],
            'dl_count' => 0,
            'dl_limit' => (int)get_setting('default_dl_limit', '0'),
            'status' => isset($_POST['publish']) && $_POST['publish'] === '1' ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
        ));
        json_response(array('id' => $newId, 'instant' => true), 0, '上传成功（秒传）');
    }
}

// 每日上传限制
if ($action !== 'direct_done') {
    $level = user_level($user);
    $daily = $level['daily_upload_limit'];
    if ($daily <= 0) {
        $global = (int)get_setting('global_daily_upload_limit', '0');
        $daily = $global;
    }
    if ($daily > 0 && today_upload_count((int)$user['id']) >= $daily) {
        json_response(null, 400, '今日上传数量已达上限');
    }
}

if ($action === 'chunk') {
    // 参数
    $filename = isset($_POST['filename']) ? $uploader->safeName($_POST['filename']) : '';
    $index = isset($_POST['index']) ? (int)$_POST['index'] : 0;
    $total = isset($_POST['total']) ? (int)$_POST['total'] : 0;
    $hash = isset($_POST['hash']) ? $_POST['hash'] : '';
    $publish = isset($_POST['publish']) ? $_POST['publish'] : '1';

    if ($filename === '' || $total <= 0) {
        json_response(null, 400, '参数错误');
    }
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    list($ok, $msg) = $uploader->checkExt($ext);
    if (!$ok) {
        json_response(null, 400, $msg);
    }

    // 单文件大小限制
    $level = user_level($user);
    $maxSize = $level['max_file_size'] > 0 ? $level['max_file_size'] : (int)get_setting('max_file_size', UPLOAD_MAX_SIZE);
    if ($maxSize > 0 && isset($_FILES['file']) && $_FILES['file']['size'] > $maxSize) {
        json_response(null, 400, '文件超过大小限制');
    }

    if (!isset($_FILES['file'])) {
        json_response(null, 400, '未接收到分块文件');
    }
    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        json_response(null, 400, '分块上传失败，错误码 ' . $_FILES['file']['error']);
    }

    $chunkFile = $_FILES['file']['tmp_name'];
    list($ok, $result) = $uploader->handleChunk((int)$user['id'], $filename, $index, $total, $hash, $chunkFile);
    if (!$ok) {
        json_response(null, 500, $result);
    }

    if (empty($result['done'])) {
        json_response(array('done' => false, 'received' => $result['received'], 'total' => $total), 0, '分块已接收');
    }

    // 合并分块
    $finalFile = dirname(dirname(__FILE__)) . '/data/tmp/final_' . random_str(16) . '_' . $filename;
    if (!$uploader->mergeChunks($result['dir'], $result['total'], $finalFile)) {
        @unlink($finalFile);
        json_response(null, 500, '分块合并失败');
    }

    $fileId = finalizeUpload($user, $filename, $finalFile, $ext, $hash, $publish);
    @unlink($finalFile);
    if (!$fileId) {
        json_response(null, 500, '文件保存失败');
    }
    json_response(array('id' => $fileId, 'instant' => false), 0, '上传成功');

} elseif ($action === 'direct_done') {
    // 浏览器直传完成，仅记录
    $filename = isset($_POST['filename']) ? $uploader->safeName($_POST['filename']) : '';
    $path = isset($_POST['path']) ? $_POST['path'] : '';
    $size = isset($_POST['size']) ? (int)$_POST['size'] : 0;
    $publish = isset($_POST['publish']) ? $_POST['publish'] : '1';
    if ($filename === '' || $path === '') {
        json_response(null, 400, '参数错误');
    }
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    list($ok, $msg) = $uploader->checkExt($ext);
    if (!$ok) {
        json_response(null, 400, $msg);
    }
    $storageType = get_setting('storage_type', 'local');
    $id = DB::instance()->insert('files', array(
        'uid' => (int)$user['id'],
        'filename' => $filename,
        'filesize' => $size,
        'mime' => get_mime($ext),
        'ext' => $ext,
        'storage_type' => $storageType,
        'storage_path' => $path,
        'hash' => isset($_POST['hash']) ? $_POST['hash'] : '',
        'dl_count' => 0,
        'dl_limit' => (int)get_setting('default_dl_limit', '0'),
        'status' => $publish === '1' ? 1 : 0,
        'created_at' => date('Y-m-d H:i:s'),
    ));
    json_response(array('id' => $id, 'instant' => false), 0, '上传成功');

} else {
    json_response(null, 400, '未知操作');
}

// 上传完成后处理：生成存储路径并上传到远端
function finalizeUpload($user, $filename, $localFile, $ext, $hash, $publish)
{
    $storageType = get_setting('storage_type', 'local');
    $savePath = date('Y/m/d') . '/' . random_str(16) . '.' . $ext;

    // 存储上传可能移动/重命名本地文件，先记录大小
    $fileSize = is_file($localFile) ? (int)filesize($localFile) : 0;

    try {
        $storage = \lib\CloudStorage\StorageManager::getInstance($storageType);
        $url = $storage->upload($localFile, $savePath);
    } catch (Exception $ex) {
        json_response(null, 500, '存储上传失败：' . $ex->getMessage());
    }

    return DB::instance()->insert('files', array(
        'uid' => (int)$user['id'],
        'filename' => $filename,
        'filesize' => $fileSize,
        'mime' => get_mime($ext),
        'ext' => $ext,
        'storage_type' => $storageType,
        'storage_path' => $savePath,
        'hash' => $hash !== '' ? $hash : file_hash($localFile),
        'dl_count' => 0,
        'dl_limit' => (int)get_setting('default_dl_limit', '0'),
        'status' => $publish === '1' ? 1 : 0,
        'created_at' => date('Y-m-d H:i:s'),
    ));
}
