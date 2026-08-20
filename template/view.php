<?php
/**
 * 文件查看页模板
 * 左侧预览 + 右侧信息面板
 */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$file = DB::instance()->fetch('SELECT f.*, u.username AS uname FROM `' . DB_PREFIX . 'files` f LEFT JOIN `' . DB_PREFIX . 'users` u ON f.uid = u.id WHERE f.id = ?', array($id));
if (!$file || (int)$file['status'] !== 1) {
    echo '<div class="empty-state"><p>文件不存在或已删除</p><a class="btn btn-primary" href="' . e(site_url('index.php')) . '">返回首页</a></div>';
    return;
}

$ext = strtolower($file['ext']);
$isOwner = $user && (int)$user['id'] === (int)$file['uid'];
$isAdmin = $user && !empty($user['is_admin']);
$canManage = $isOwner || $isAdmin;

try {
    $storage = \lib\CloudStorage\StorageManager::getInstance($file['storage_type']);
    $directUrl = $storage->getUrl($file['storage_path']);
    $downloadUrl = $storage->getDownloadUrl($file['storage_path'], $file['filename']);
} catch (Exception $ex) {
    $directUrl = '';
    $downloadUrl = '';
}

$isImage = is_image_ext($ext);
$isAudio = is_audio_ext($ext);
$isVideo = is_video_ext($ext);
$isText = is_text_ext($ext);
$isPdf = $ext === 'pdf';
$previewable = $isImage || $isAudio || $isVideo || $isText || $isPdf;

// 外链代码
$ubb = '[url=' . e($directUrl) . ']' . e($file['filename']) . '[/url]';
$html = '<a href="' . e($directUrl) . '" target="_blank">' . e($file['filename']) . '</a>';
$md = '[' . e($file['filename']) . '](' . e($directUrl) . ')';
?>
<div class="view-layout">
    <div class="view-main" data-view-main>
        <div class="view-toolbar">
            <a class="btn btn-ghost btn-sm" href="<?php echo e(site_url('index.php')); ?>">&lt; 返回列表</a>
            <div class="view-toolbar-right">
                <a class="btn btn-ghost btn-sm" href="<?php echo e($directUrl); ?>" target="_blank" rel="noopener">打开</a>
                <a class="btn btn-primary btn-sm" href="<?php echo e($downloadUrl); ?>" data-download-link>下载</a>
            </div>
        </div>

        <div class="preview-area" data-preview-area>
            <?php if ($isImage): ?>
                <img src="<?php echo e($directUrl); ?>" alt="<?php echo e($file['filename']); ?>" data-lightbox class="preview-image" loading="lazy">
            <?php elseif ($isAudio): ?>
                <div class="preview-audio">
                    <p class="preview-name"><?php echo e($file['filename']); ?></p>
                    <audio controls src="<?php echo e($directUrl); ?>"></audio>
                </div>
            <?php elseif ($isVideo): ?>
                <video class="preview-video" controls preload="metadata" src="<?php echo e($directUrl); ?>"></video>
            <?php elseif ($isText): ?>
                <div class="preview-text">
                    <div class="preview-text-head">
                        <p class="preview-name"><?php echo e($file['filename']); ?></p>
                        <button class="btn btn-primary btn-sm" type="button" id="viewTextBtn" data-load-text>查看内容</button>
                        <?php if ($canManage): ?>
                        <button class="btn btn-ghost btn-sm" type="button" id="editTextBtn" data-edit-text hidden>编辑</button>
                        <?php endif; ?>
                    </div>
                    <pre class="preview-code" id="previewCode" data-code-view></pre>
                    <div class="editor-wrap" id="editorWrap" hidden>
                        <textarea id="textEditor" data-text-editor rows="20"></textarea>
                        <div class="editor-actions">
                            <button class="btn btn-primary btn-sm" type="button" data-save-text>保存</button>
                            <button class="btn btn-ghost btn-sm" type="button" data-cancel-edit>取消</button>
                        </div>
                    </div>
                </div>
            <?php elseif ($isPdf): ?>
                <iframe class="preview-pdf" src="<?php echo e($directUrl); ?>"></iframe>
            <?php else: ?>
                <div class="preview-placeholder">
                    <p class="preview-name"><?php echo e($file['filename']); ?></p>
                    <p class="preview-sub">该类型暂不支持在线预览</p>
                    <a class="btn btn-primary" href="<?php echo e($downloadUrl); ?>">下载文件</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <aside class="view-side" data-view-side>
        <div class="side-card">
            <h3 class="side-title"><?php echo e($file['filename']); ?></h3>
            <ul class="file-detail">
                <li><span>大小</span><b><?php echo e(format_size($file['filesize'])); ?></b></li>
                <li><span>类型</span><b><?php echo e($file['ext']); ?></b></li>
                <li><span>上传者</span><b><?php echo e($file['uname'] ? $file['uname'] : '游客'); ?></b></li>
                <li><span>上传时间</span><b><?php echo e($file['created_at']); ?></b></li>
                <li><span>下载次数</span><b><?php echo (int)$file['dl_count']; ?><?php echo (int)$file['dl_limit'] > 0 ? ' / ' . (int)$file['dl_limit'] : ''; ?></b></li>
                <li><span>存储</span><b><?php echo e($file['storage_type']); ?></b></li>
            </ul>
            <?php if ($canManage): ?>
            <div class="manage-actions">
                <button class="btn btn-ghost btn-sm btn-block" type="button" data-del-file="<?php echo (int)$file['id']; ?>">删除文件</button>
            </div>
            <?php endif; ?>
        </div>

        <div class="side-card">
            <div class="tabs" data-tabs>
                <button class="tab active" type="button" data-tab="link">外链</button>
                <button class="tab" type="button" data-tab="code">代码调用</button>
                <button class="tab" type="button" data-tab="info">详情</button>
            </div>
            <div class="tab-panels">
                <div class="tab-panel active" data-panel="link">
                    <div class="link-row">
                        <label>直链</label>
                        <div class="copy-row">
                            <input type="text" readonly value="<?php echo e($directUrl); ?>" data-copy-value>
                            <button type="button" class="btn btn-ghost btn-sm" data-copy>复制</button>
                        </div>
                    </div>
                    <div class="link-row">
                        <label>下载链接</label>
                        <div class="copy-row">
                            <input type="text" readonly value="<?php echo e($downloadUrl); ?>" data-copy-value>
                            <button type="button" class="btn btn-ghost btn-sm" data-copy>复制</button>
                        </div>
                    </div>
                </div>
                <div class="tab-panel" data-panel="code">
                    <div class="link-row">
                        <label>UBB 代码</label>
                        <div class="copy-row">
                            <input type="text" readonly value="<?php echo e($ubb); ?>" data-copy-value>
                            <button type="button" class="btn btn-ghost btn-sm" data-copy>复制</button>
                        </div>
                    </div>
                    <div class="link-row">
                        <label>HTML 代码</label>
                        <div class="copy-row">
                            <input type="text" readonly value="<?php echo e($html); ?>" data-copy-value>
                            <button type="button" class="btn btn-ghost btn-sm" data-copy>复制</button>
                        </div>
                    </div>
                    <div class="link-row">
                        <label>Markdown</label>
                        <div class="copy-row">
                            <input type="text" readonly value="<?php echo e($md); ?>" data-copy-value>
                            <button type="button" class="btn btn-ghost btn-sm" data-copy>复制</button>
                        </div>
                    </div>
                </div>
                <div class="tab-panel" data-panel="info">
                    <pre class="info-json"><?php echo e(json_encode(array(
                        'id' => $file['id'],
                        'filename' => $file['filename'],
                        'size' => (int)$file['filesize'],
                        'mime' => $file['mime'],
                        'ext' => $file['ext'],
                        'storage' => $file['storage_type'],
                        'path' => $file['storage_path'],
                        'hash' => $file['hash'],
                    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                </div>
            </div>
        </div>
    </aside>
</div>

<div class="lightbox" id="lightbox" hidden>
    <img src="" alt="preview">
</div>
