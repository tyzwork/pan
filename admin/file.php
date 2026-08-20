<?php
/**
 * 后台文件管理
 * 支持：列表、搜索、按类型筛选、删除、批量删除、下载
 */
require_once __DIR__ . '/common.php';
require_admin();

$title = '文件管理';
$page = 'file';

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

// 处理操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if ($action === 'delete') {
        $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
        $file = DB::instance()->fetch('SELECT * FROM `' . DB_PREFIX . 'files` WHERE `id` = ?', array($id));
        if ($file) {
            // 尝试删除存储对象，失败不影响记录删除
            try {
                $storage = \lib\CloudStorage\StorageManager::getInstance($file['storage_type']);
                if (method_exists($storage, 'delete')) {
                    $storage->delete($file['storage_path']);
                }
            } catch (Exception $ex) {
                // 忽略存储删除异常
            }
            DB::instance()->delete('files', '`id` = ?', array($id));
        }
        json_response(null, 0, '删除成功');
    }
    if ($action === 'batch_delete') {
        $ids = isset($_POST['ids']) ? $_POST['ids'] : array();
        $ids = array_map('intval', (array)$ids);
        if (!empty($ids)) {
            $files = DB::instance()->fetchAll('SELECT * FROM `' . DB_PREFIX . 'files` WHERE `id` IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids);
            foreach ($files as $f) {
                try {
                    $storage = \lib\CloudStorage\StorageManager::getInstance($f['storage_type']);
                    if (method_exists($storage, 'delete')) {
                        $storage->delete($f['storage_path']);
                    }
                } catch (Exception $ex) {
                    // 忽略
                }
                DB::instance()->delete('files', '`id` = ?', array($f['id']));
            }
        }
        json_response(array('count' => count($ids)), 0, '批量删除成功');
    }
    json_response(null, 404, '未知操作');
}

// 列表查询
$q = trim(isset($_GET['q']) ? $_GET['q'] : '');
$type = isset($_GET['type']) ? $_GET['type'] : '';
$where = array();
$params = array();
if ($q !== '') {
    $where[] = '`filename` LIKE ?';
    $params[] = '%' . $q . '%';
}
if ($type !== '' && $type !== 'all') {
    if ($type === 'image') {
        $where[] = "`ext` IN ('jpg','jpeg','png','gif','webp','bmp','svg','ico')";
    } elseif ($type === 'video') {
        $where[] = "`ext` IN ('mp4','mkv','webm','avi','mov','wmv','flv','mpg','mpeg')";
    } elseif ($type === 'audio') {
        $where[] = "`ext` IN ('mp3','wav','ogg','flac','aac','m4a')";
    } elseif ($type === 'text') {
        $where[] = "`ext` IN ('txt','text','log','md','markdown','json','js','mjs','css','less','scss','html','htm','xml','svg','csv','ini','conf','config','yaml','yml','sql','vue','ts','tsx','jsx')";
    } else {
        $where[] = '`ext` = ?';
        $params[] = $type;
    }
}

$pageInfo = get_page(1, 15);
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$total = (int)DB::instance()->fetchColumn(
    'SELECT COUNT(*) FROM `' . DB_PREFIX . 'files`' . $whereSql,
    $params
);
$files = DB::instance()->fetchAll(
    'SELECT f.*, u.username FROM `' . DB_PREFIX . 'files` f LEFT JOIN `' . DB_PREFIX . 'users` u ON u.id = f.uid' .
    $whereSql . ' ORDER BY f.id DESC LIMIT ' . (int)$pageInfo['offset'] . ',' . (int)$pageInfo['size'],
    $params
);
$pager = new Pager($total, $pageInfo['page'], $pageInfo['size']);

include __DIR__ . '/header.php';
?>
<div class="admin-card">
    <div class="card-head">
        <h3>全部文件</h3>
        <span class="badge badge-info">共 <?php echo $total; ?> 个文件</span>
    </div>
    <div class="card-body">
        <form class="toolbar" method="get" action="file.php">
            <input type="text" name="q" class="form-control" style="width:240px" placeholder="搜索文件名" value="<?php echo e($q); ?>">
            <select name="type" class="form-control" style="width:160px">
                <option value="all">全部类型</option>
                <option value="image" <?php echo $type === 'image' ? 'selected' : ''; ?>>图片</option>
                <option value="video" <?php echo $type === 'video' ? 'selected' : ''; ?>>视频</option>
                <option value="audio" <?php echo $type === 'audio' ? 'selected' : ''; ?>>音频</option>
                <option value="text" <?php echo $type === 'text' ? 'selected' : ''; ?>>文本</option>
            </select>
            <button type="submit" class="btn btn-primary">搜索</button>
            <div class="spacer"></div>
            <button type="button" class="btn btn-danger" id="batchDeleteBtn">批量删除</button>
        </form>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="checkAll" class="admin-checkbox"></th>
                        <th>文件名</th>
                        <th>大小</th>
                        <th>上传者</th>
                        <th>存储</th>
                        <th>下载</th>
                        <th>时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($files)): ?>
                    <tr><td colspan="8"><div class="empty-state"><p>没有找到相关文件</p></div></td></tr>
                    <?php else: foreach ($files as $f): ?>
                    <tr>
                        <td><input type="checkbox" class="admin-checkbox row-check" value="<?php echo (int)$f['id']; ?>"></td>
                        <td>
                            <span class="file-thumb"><?php echo e(strtoupper(substr($f['ext'], 0, 3))); ?></span>
                            <span style="margin-left:8px"><?php echo e($f['filename']); ?></span>
                        </td>
                        <td><?php echo e(format_size($f['filesize'])); ?></td>
                        <td><?php echo $f['username'] ? e($f['username']) : '游客'; ?></td>
                        <td><span class="badge badge-muted"><?php echo e($f['storage_type']); ?></span></td>
                        <td><?php echo (int)$f['dl_count']; ?></td>
                        <td><?php echo e($f['created_at']); ?></td>
                        <td>
                            <a href="file.php?action=detail&id=<?php echo (int)$f['id']; ?>" class="btn btn-sm btn-primary" onclick="event.preventDefault();showDetail(<?php echo (int)$f['id']; ?>)">详情</a>
                            <button type="button" class="btn btn-sm btn-danger" data-del="<?php echo (int)$f['id']; ?>" data-name="<?php echo e($f['filename']); ?>">删除</button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php echo $pager->html(); ?>
    </div>
</div>

<script>
function showDetail(id) {
    fetch(PAN_ADMIN.baseUrl + '/api/file.php?action=info&id=' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.code !== 0) { alert(res.msg); return; }
            var d = res.data;
            var html = '<div style="font-size:13px;line-height:2;color:#374151">' +
                '<p>文件名：' + d.filename + '</p>' +
                '<p>大小：' + d.size + '</p>' +
                '<p>类型：' + d.ext + '（' + d.mime + '）</p>' +
                '<p>上传者：' + d.username + '</p>' +
                '<p>存储：' + d.storage_type + '</p>' +
                '<p>下载：' + d.dl_count + ' 次</p>' +
                '<p>上传时间：' + d.created_at + '</p>' +
                '<p>外链：<a href="' + d.url + '" target="_blank">' + d.url + '</a></p>' +
                '</div>';
            openModal('文件详情', html);
        });
}
document.addEventListener('click', function (e) {
    var del = e.target.closest('[data-del]');
    if (del) {
        var id = del.getAttribute('data-del');
        var name = del.getAttribute('data-name');
        adminConfirm('删除文件', '确定删除文件 "' + name + '" 吗？此操作不可恢复。', function () {
            adminPost('file.php', { action: 'delete', id: id }, function () {
                adminToast('删除成功');
                setTimeout(function () { location.reload(); }, 400);
            });
        });
    }
});
document.getElementById('batchDeleteBtn').addEventListener('click', function () {
    var ids = Array.prototype.slice.call(document.querySelectorAll('.row-check:checked')).map(function (c) { return c.value; });
    if (ids.length === 0) { alert('请先勾选要删除的文件'); return; }
    adminConfirm('批量删除', '确定删除选中的 ' + ids.length + ' 个文件吗？', function () {
        adminPost('file.php', { action: 'batch_delete', ids: ids }, function () {
            adminToast('批量删除成功');
            setTimeout(function () { location.reload(); }, 400);
        });
    });
});
</script>
<?php include __DIR__ . '/footer.php'; ?>
