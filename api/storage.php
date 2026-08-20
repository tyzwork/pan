<?php
/**
 * 存储信息 API
 * 返回当前存储类型、上传方式及浏览器直传所需参数
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

$storageType = isset($_GET['type']) && $_GET['type'] !== '' ? $_GET['type'] : get_setting('storage_type', 'local');

try {
    $storage = \lib\CloudStorage\StorageManager::getInstance($storageType);
    $mode = method_exists($storage, 'uploadMode') ? $storage->uploadMode() : 'relay';

    $data = array(
        'type' => $storageType,
        'mode' => $mode,
    );
    if ($mode === 'direct' && method_exists($storage, 'getDirectUploadData')) {
        $ext = isset($_GET['ext']) ? preg_replace('/[^a-zA-Z0-9]/', '', $_GET['ext']) : 'file';
        $savePath = date('Y/m/d') . '/' . random_str(16) . '.' . $ext;
        $data['direct'] = $storage->getDirectUploadData($savePath);
        $data['direct']['save_path'] = $savePath;
    }
    json_response($data, 0, 'ok');
} catch (Exception $ex) {
    json_response(null, 500, $ex->getMessage());
}
