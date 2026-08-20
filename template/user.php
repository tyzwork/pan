<?php
/**
 * 用户中心模板：我的文件
 */
$pageInfo = get_page(1, 20);
$where = '`uid` = ?';
$params = array((int)$user['id']);
$total = DB::instance()->count('files', $where, $params);
$pager = new Pager($total, $pageInfo['page'], $pageInfo['size']);
$files = DB::instance()->fetchAll(
    'SELECT * FROM `' . DB_PREFIX . 'files` WHERE ' . $where . ' ORDER BY `created_at` DESC LIMIT ' . (int)$pager->offset() . ',' . (int)$pager->limit(),
    $params
);
?>
<div class="page-head" data-page-head>
    <h1 class="page-title">我的文件</h1>
    <p class="page-sub">你好，<?php echo e($user['username']); ?>，共 <?php echo (int)$total; ?> 个文件</p>
</div>

<div class="table-card" data-table-card>
    <table class="data-table">
        <thead>
            <tr>
                <th>文件名</th>
                <th>大小</th>
                <th>上传时间</th>
                <th>下载</th>
                <th>状态</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($files)): ?>
            <tr><td colspan="6" class="text-center">暂无文件</td></tr>
            <?php else: ?>
                <?php foreach ($files as $f): ?>
                <tr data-file-row="<?php echo (int)$f['id']; ?>">
                    <td><a href="<?php echo e(site_url('index.php?p=view&id=' . $f['id'])); ?>"><?php echo e(mb_substr($f['filename'], 0, 40)); ?></a></td>
                    <td><?php echo e(format_size($f['filesize'])); ?></td>
                    <td><?php echo e($f['created_at']); ?></td>
                    <td><?php echo (int)$f['dl_count']; ?></td>
                    <td><?php echo (int)$f['status'] === 1 ? '<span class="badge badge-green">公开</span>' : '<span class="badge">私有</span>'; ?></td>
                    <td>
                        <button class="btn btn-ghost btn-sm" type="button" data-user-del="<?php echo (int)$f['id']; ?>">删除</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php echo $pager->html(); ?>
