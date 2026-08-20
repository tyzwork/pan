<?php
/**
 * 首页模板：文件列表
 * 支持搜索（p=search&q=xxx）
 */
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$pageInfo = get_page(1, 18);
$where = '`status` = 1';
$params = array();
if ($q !== '') {
    $where .= ' AND `filename` LIKE ?';
    $params[] = '%' . $q . '%';
}
$total = DB::instance()->count('files', $where, $params);
$pager = new Pager($total, $pageInfo['page'], $pageInfo['size']);
// SELECT 查询使用别名 f，需将 where 中的列名加上 f. 前缀避免与 users.status 歧义
$whereSel = str_replace('`status`', 'f.`status`', $where);
$whereSel = str_replace('`filename`', 'f.`filename`', $whereSel);
$files = DB::instance()->fetchAll(
    'SELECT f.*, u.username AS uname FROM `' . DB_PREFIX . 'files` f LEFT JOIN `' . DB_PREFIX . 'users` u ON f.uid = u.id WHERE ' . $whereSel . ' ORDER BY f.created_at DESC LIMIT ' . (int)$pager->offset() . ',' . (int)$pager->limit(),
    $params
);
?>
<div class="page-head" data-page-head>
    <h1 class="page-title"><?php echo $q !== '' ? '搜索：' . e($q) : '文件列表'; ?></h1>
    <p class="page-sub"><?php echo $q !== '' ? '共找到 ' . $total . ' 个文件' : '最新上传的公开文件'; ?></p>
</div>

<div class="file-grid">
    <?php if (empty($files)): ?>
        <div class="empty-state" data-empty>
            <p>暂无文件</p>
            <?php if ($user): ?>
            <a class="btn btn-primary" href="<?php echo e(site_url('index.php?p=upload')); ?>">去上传</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php foreach ($files as $f): ?>
        <a class="file-card" href="<?php echo e(site_url('index.php?p=view&id=' . $f['id'])); ?>" data-file-card data-type="<?php echo e($f['ext']); ?>">
            <div class="file-icon icon-<?php echo e(icon_class($f['ext'])); ?>"><?php echo e(strtoupper($f['ext'])); ?></div>
            <div class="file-info">
                <h3 class="file-name" title="<?php echo e($f['filename']); ?>"><?php echo e(mb_substr($f['filename'], 0, 40)); ?></h3>
                <p class="file-meta">
                    <span><?php echo e(format_size($f['filesize'])); ?></span>
                    <span>下载 <?php echo (int)$f['dl_count']; ?></span>
                    <span><?php echo e(substr($f['created_at'], 0, 10)); ?></span>
                </p>
            </div>
            <div class="file-actions">
                <span class="tag"><?php echo e($f['uname'] ? $f['uname'] : '游客'); ?></span>
            </div>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php echo $pager->html(); ?>
