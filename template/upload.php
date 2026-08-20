<?php
/**
 * 上传页面模板
 * 支持: 拖拽上传、分块上传、断点续传、秒传（Hash 去重）、浏览器直传
 */
$level = user_level($user);
$maxSize = $level['max_file_size'] > 0 ? $level['max_file_size'] : (int)get_setting('max_file_size', UPLOAD_MAX_SIZE);
$dailyLimit = $level['daily_upload_limit'];
$allowExt = get_setting('allow_extensions', ALLOW_EXTENSIONS);
$uploadMode = 'relay';
try {
    $storage = \lib\CloudStorage\StorageManager::getInstance();
    if (method_exists($storage, 'uploadMode')) {
        $uploadMode = $storage->uploadMode();
    }
} catch (Exception $ex) {
    $uploadMode = 'relay';
}
?>
<div class="page-head" data-page-head>
    <h1 class="page-title">上传文件</h1>
    <p class="page-sub">
        当前账号等级：<?php echo e($level['name']); ?> |
        单文件上限：<?php echo $maxSize > 0 ? e(format_size($maxSize)) : '不限'; ?> |
        <?php echo $dailyLimit > 0 ? '每日上限：' . (int)$dailyLimit . ' 个' : '每日上传不限量'; ?>
    </p>
</div>

<div class="upload-wrap">
    <div class="dropzone" id="dropzone" data-upload-zone>
        <div class="dropzone-inner">
            <div class="dropzone-icon">+</div>
            <p class="dropzone-title">拖拽文件到此处，或点击选择文件</p>
            <p class="dropzone-sub">支持分块上传与断点续传，单个文件最大 <?php echo $maxSize > 0 ? e(format_size($maxSize)) : '500MB'; ?></p>
        </div>
        <input type="file" id="fileInput" multiple hidden>
    </div>

    <div class="upload-config" data-upload-config>
        <label class="checkbox">
            <input type="checkbox" id="publishPublic" checked>
            <span>上传完成后公开展示（首页可见）</span>
        </label>
    </div>

    <div class="upload-list" id="uploadList"></div>
</div>

<script>
window.UPLOAD_CONFIG = {
    maxSize: <?php echo (int)$maxSize; ?>,
    dailyLimit: <?php echo (int)$dailyLimit; ?>,
    chunkSize: <?php echo (int)DEFAULT_CHUNK_SIZE; ?>,
    mode: <?php echo json_encode($uploadMode); ?>,
    accept: <?php echo json_encode($allowExt); ?>
};
</script>
