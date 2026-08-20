<?php
/**
 * 后台仪表盘
 */
require_once __DIR__ . '/common.php';
require_admin();

$title = '仪表盘';
$page = 'dashboard';
$stats = admin_stats();
$trend = upload_trend();
$latestFiles = DB::instance()->fetchAll(
    'SELECT f.*, u.username FROM `' . DB_PREFIX . 'files` f LEFT JOIN `' . DB_PREFIX . 'users` u ON u.id = f.uid ORDER BY f.id DESC LIMIT 8'
);
$storageType = get_setting('storage_type', 'local');
$storageNames = \lib\CloudStorage\StorageManager::types();
$storageLabel = isset($storageNames[$storageType]) ? $storageNames[$storageType] : $storageType;

include __DIR__ . '/header.php';
?>
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-ico blue">件</div>
        <div class="stat-label">文件总数</div>
        <div class="stat-value" data-count="<?php echo (int)$stats['files']; ?>">0</div>
        <div class="stat-sub">累计上传文件数量</div>
    </div>
    <div class="stat-card">
        <div class="stat-ico purple">人</div>
        <div class="stat-label">注册用户</div>
        <div class="stat-value" data-count="<?php echo (int)$stats['users']; ?>">0</div>
        <div class="stat-sub">前台注册用户数量</div>
    </div>
    <div class="stat-card">
        <div class="stat-ico green">今</div>
        <div class="stat-label">今日上传</div>
        <div class="stat-value" data-count="<?php echo (int)$stats['today']; ?>">0</div>
        <div class="stat-sub">今天新增的文件</div>
    </div>
    <div class="stat-card">
        <div class="stat-ico orange">载</div>
        <div class="stat-label">下载次数</div>
        <div class="stat-value" data-count="<?php echo (int)$stats['downloads']; ?>">0</div>
        <div class="stat-sub">全部文件累计下载</div>
    </div>
    <div class="stat-card">
        <div class="stat-ico red">储</div>
        <div class="stat-label">占用空间</div>
        <div class="stat-value" data-count="<?php echo (int)$stats['storage']; ?>" data-suffix=""></div>
        <div class="stat-sub"><?php echo e(format_size($stats['storage'])); ?></div>
    </div>
</div>

<div class="admin-card">
    <div class="card-head">
        <h3>近 7 天上传趋势</h3>
        <span class="badge badge-info">当前存储：<?php echo e($storageLabel); ?></span>
    </div>
    <div class="card-body">
        <div class="mini-chart" id="miniChart">
            <?php foreach ($trend as $date => $count): ?>
            <div class="bar" data-v="<?php echo (int)$count; ?>" title="<?php echo e($date); ?>">
                <span><?php echo (int)$count; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:12px;color:#6b7280">
            <?php foreach ($trend as $date => $count): ?>
            <span><?php echo substr($date, 5); ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="card-head">
        <h3>最近上传</h3>
        <a href="file.php" class="btn btn-sm">查看全部</a>
    </div>
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>文件名</th>
                    <th>大小</th>
                    <th>上传者</th>
                    <th>下载次数</th>
                    <th>上传时间</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($latestFiles)): ?>
                <tr><td colspan="5" style="text-align:center;color:#9ca3af">暂无文件</td></tr>
                <?php else: foreach ($latestFiles as $f): ?>
                <tr>
                    <td>
                        <span class="file-thumb"><?php echo e(strtoupper(substr($f['ext'], 0, 3))); ?></span>
                        <span style="margin-left:8px"><?php echo e($f['filename']); ?></span>
                    </td>
                    <td><?php echo e(format_size($f['filesize'])); ?></td>
                    <td><?php echo $f['username'] ? e($f['username']) : '游客'; ?></td>
                    <td><?php echo (int)$f['dl_count']; ?></td>
                    <td><?php echo e($f['created_at']); ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
