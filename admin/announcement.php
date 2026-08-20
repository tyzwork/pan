<?php
/**
 * 后台广告公告位设置
 * 功能：公告管理（顶部公告）、广告管理（文字/图片/代码，侧边栏等位置）
 */
require_once __DIR__ . '/common.php';
require_admin();

$title = '广告公告位';
$page = 'announcement';

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'announcements';
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    if ($tab === 'announcements') {
        if ($action === 'save') {
            $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
            $titleA = trim(isset($_POST['title']) ? $_POST['title'] : '');
            $content = trim(isset($_POST['content']) ? $_POST['content'] : '');
            $position = isset($_POST['position']) ? $_POST['position'] : 'top';
            $status = (int)(isset($_POST['status']) ? $_POST['status'] : 1);
            $sort = (int)(isset($_POST['sort']) ? $_POST['sort'] : 0);
            if ($titleA === '') {
                json_response(null, 1, '公告标题不能为空');
            }
            $data = array('title' => $titleA, 'content' => $content, 'position' => $position, 'status' => $status, 'sort' => $sort);
            if ($id > 0) {
                DB::instance()->update('announcements', $data, '`id` = ?', array($id));
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                DB::instance()->insert('announcements', $data);
            }
            json_response(null, 0, '保存成功');
        }
        if ($action === 'delete') {
            $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
            DB::instance()->delete('announcements', '`id` = ?', array($id));
            json_response(null, 0, '删除成功');
        }
        json_response(null, 404, '未知操作');
    }

    if ($tab === 'ads') {
        if ($action === 'save') {
            $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
            $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
            $type = isset($_POST['type']) ? $_POST['type'] : 'text';
            $content = isset($_POST['content']) ? $_POST['content'] : '';
            $image = trim(isset($_POST['image']) ? $_POST['image'] : '');
            $link = trim(isset($_POST['link']) ? $_POST['link'] : '');
            $position = isset($_POST['position']) ? $_POST['position'] : 'sidebar';
            $status = (int)(isset($_POST['status']) ? $_POST['status'] : 1);
            $sort = (int)(isset($_POST['sort']) ? $_POST['sort'] : 0);
            if ($name === '') {
                json_response(null, 1, '广告名称不能为空');
            }
            $data = array('name' => $name, 'type' => $type, 'content' => $content, 'image' => $image, 'link' => $link, 'position' => $position, 'status' => $status, 'sort' => $sort);
            if ($id > 0) {
                DB::instance()->update('ads', $data, '`id` = ?', array($id));
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                DB::instance()->insert('ads', $data);
            }
            json_response(null, 0, '保存成功');
        }
        if ($action === 'delete') {
            $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
            DB::instance()->delete('ads', '`id` = ?', array($id));
            json_response(null, 0, '删除成功');
        }
        json_response(null, 404, '未知操作');
    }
}

$announcements = DB::instance()->fetchAll('SELECT * FROM `' . DB_PREFIX . 'announcements` ORDER BY `sort` ASC, `id` DESC');
$ads = DB::instance()->fetchAll('SELECT * FROM `' . DB_PREFIX . 'ads` ORDER BY `sort` ASC, `id` DESC');

include __DIR__ . '/header.php';
?>
<div class="tabs" data-tabs="adtabs">
    <div class="tab-item <?php echo $tab === 'announcements' ? 'active' : ''; ?>" data-target="pane-ann">公告管理</div>
    <div class="tab-item <?php echo $tab === 'ads' ? 'active' : ''; ?>" data-target="pane-ads">广告管理</div>
</div>

<div id="pane-ann" data-pane-group="adtabs" class="tab-pane <?php echo $tab === 'announcements' ? 'active' : ''; ?>">
    <div class="admin-card">
        <div class="card-head"><h3>公告列表</h3><button type="button" class="btn btn-primary" id="addAnnBtn">新增公告</button></div>
        <div class="card-body">
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>ID</th><th>标题</th><th>展示位置</th><th>排序</th><th>状态</th><th>创建时间</th><th>操作</th></tr></thead>
                    <tbody>
                        <?php if (empty($announcements)): ?>
                        <tr><td colspan="7"><div class="empty-state"><p>暂无公告</p></div></td></tr>
                        <?php else: foreach ($announcements as $a): ?>
                        <tr>
                            <td><?php echo (int)$a['id']; ?></td>
                            <td><?php echo e($a['title']); ?></td>
                            <td><span class="badge badge-info"><?php echo e($a['position']); ?></span></td>
                            <td><?php echo (int)$a['sort']; ?></td>
                            <td><?php echo (int)$a['status'] === 1 ? '<span class="badge badge-success">显示</span>' : '<span class="badge badge-muted">隐藏</span>'; ?></td>
                            <td><?php echo e($a['created_at']); ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" data-edit-ann='<?php echo e(json_encode(array('id' => (int)$a['id'], 'title' => $a['title'], 'content' => $a['content'], 'position' => $a['position'], 'status' => (int)$a['status'], 'sort' => (int)$a['sort']), JSON_UNESCAPED_UNICODE)); ?>'>编辑</button>
                                <button type="button" class="btn btn-sm btn-danger" data-del-ann="<?php echo (int)$a['id']; ?>">删除</button>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="pane-ads" data-pane-group="adtabs" class="tab-pane <?php echo $tab === 'ads' ? 'active' : ''; ?>">
    <div class="admin-card">
        <div class="card-head"><h3>广告列表</h3><button type="button" class="btn btn-primary" id="addAdBtn">新增广告</button></div>
        <div class="card-body">
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>ID</th><th>名称</th><th>类型</th><th>展示位置</th><th>排序</th><th>状态</th><th>操作</th></tr></thead>
                    <tbody>
                        <?php if (empty($ads)): ?>
                        <tr><td colspan="7"><div class="empty-state"><p>暂无广告</p></div></td></tr>
                        <?php else: foreach ($ads as $ad): ?>
                        <tr>
                            <td><?php echo (int)$ad['id']; ?></td>
                            <td><?php echo e($ad['name']); ?></td>
                            <td><span class="badge badge-purple"><?php echo e($ad['type']); ?></span></td>
                            <td><span class="badge badge-info"><?php echo e($ad['position']); ?></span></td>
                            <td><?php echo (int)$ad['sort']; ?></td>
                            <td><?php echo (int)$ad['status'] === 1 ? '<span class="badge badge-success">显示</span>' : '<span class="badge badge-muted">隐藏</span>'; ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" data-edit-ad='<?php echo e(json_encode(array('id' => (int)$ad['id'], 'name' => $ad['name'], 'type' => $ad['type'], 'content' => $ad['content'], 'image' => $ad['image'], 'link' => $ad['link'], 'position' => $ad['position'], 'status' => (int)$ad['status'], 'sort' => (int)$ad['sort']), JSON_UNESCAPED_UNICODE)); ?>'>编辑</button>
                                <button type="button" class="btn btn-sm btn-danger" data-del-ad="<?php echo (int)$ad['id']; ?>">删除</button>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function annFormHtml(d) {
    d = d || {};
    return '<div class="form-group"><label>公告标题</label><input type="text" class="form-control" name="title" value="' + (d.title || '') + '"></div>' +
        '<div class="form-group"><label>公告内容</label><textarea class="form-control" name="content" style="min-height:90px">' + (d.content || '') + '</textarea></div>' +
        '<div class="form-row">' +
        '<div class="form-group"><label>展示位置</label><select class="form-control" name="position"><option value="top" ' + (d.position === 'top' ? 'selected' : '') + '>顶部公告栏</option><option value="sidebar" ' + (d.position === 'sidebar' ? 'selected' : '') + '>侧边栏</option></select></div>' +
        '<div class="form-group"><label>排序</label><input type="number" class="form-control" name="sort" value="' + (d.sort || 0) + '"></div>' +
        '<div class="form-group"><label>状态</label><select class="form-control" name="status"><option value="1" ' + (d.status == 1 ? 'selected' : '') + '>显示</option><option value="0" ' + (d.status == 0 ? 'selected' : '') + '>隐藏</option></select></div>' +
        '</div>';
}
function adFormHtml(d) {
    d = d || {};
    return '<div class="form-group"><label>广告名称</label><input type="text" class="form-control" name="name" value="' + (d.name || '') + '"></div>' +
        '<div class="form-row">' +
        '<div class="form-group"><label>类型</label><select class="form-control" name="type"><option value="text" ' + (d.type === 'text' ? 'selected' : '') + '>文字</option><option value="image" ' + (d.type === 'image' ? 'selected' : '') + '>图片</option><option value="code" ' + (d.type === 'code' ? 'selected' : '') + '>代码</option></select></div>' +
        '<div class="form-group"><label>展示位置</label><select class="form-control" name="position"><option value="sidebar" ' + (d.position === 'sidebar' ? 'selected' : '') + '>侧边栏</option><option value="top" ' + (d.position === 'top' ? 'selected' : '') + '>顶部</option><option value="bottom" ' + (d.position === 'bottom' ? 'selected' : '') + '>底部</option></select></div>' +
        '</div>' +
        '<div class="form-group"><label>图片地址（图片类型时填写）</label><input type="text" class="form-control" name="image" value="' + (d.image || '') + '"></div>' +
        '<div class="form-group"><label>跳转链接</label><input type="text" class="form-control" name="link" value="' + (d.link || '') + '"></div>' +
        '<div class="form-group"><label>内容（文字/代码类型时填写，代码类型直接输出 HTML）</label><textarea class="form-control" name="content" style="min-height:90px">' + (d.content || '') + '</textarea></div>' +
        '<div class="form-row">' +
        '<div class="form-group"><label>排序</label><input type="number" class="form-control" name="sort" value="' + (d.sort || 0) + '"></div>' +
        '<div class="form-group"><label>状态</label><select class="form-control" name="status"><option value="1" ' + (d.status == 1 ? 'selected' : '') + '>显示</option><option value="0" ' + (d.status == 0 ? 'selected' : '') + '>隐藏</option></select></div>' +
        '</div>';
}

document.getElementById('addAnnBtn').addEventListener('click', function () {
    openModal('新增公告', annFormHtml({ position: 'top', status: 1 }), '<button class="btn" data-close-modal>取消</button><button class="btn btn-primary" id="saveAnnBtn">保存</button>');
    bindSaveAnn(null);
});
document.getElementById('addAdBtn').addEventListener('click', function () {
    openModal('新增广告', adFormHtml({ type: 'text', position: 'sidebar', status: 1 }), '<button class="btn" data-close-modal>取消</button><button class="btn btn-primary" id="saveAdBtn">保存</button>');
    bindSaveAd(null);
});
document.addEventListener('click', function (e) {
    var ea = e.target.closest('[data-edit-ann]');
    if (ea) { var d = JSON.parse(ea.getAttribute('data-edit-ann')); openModal('编辑公告', annFormHtml(d), '<button class="btn" data-close-modal>取消</button><button class="btn btn-primary" id="saveAnnBtn">保存</button>'); bindSaveAnn(d); }
    var da = e.target.closest('[data-del-ann]');
    if (da) { adminConfirm('删除公告', '确定删除该公告吗？', function () { adminPost('announcement.php?tab=announcements', { action: 'delete', id: da.getAttribute('data-del-ann') }, function () { adminToast('删除成功'); setTimeout(function () { location.reload(); }, 400); }); }); }
    var ead = e.target.closest('[data-edit-ad]');
    if (ead) { var d2 = JSON.parse(ead.getAttribute('data-edit-ad')); openModal('编辑广告', adFormHtml(d2), '<button class="btn" data-close-modal>取消</button><button class="btn btn-primary" id="saveAdBtn">保存</button>'); bindSaveAd(d2); }
    var dad = e.target.closest('[data-del-ad]');
    if (dad) { adminConfirm('删除广告', '确定删除该广告吗？', function () { adminPost('announcement.php?tab=ads', { action: 'delete', id: dad.getAttribute('data-del-ad') }, function () { adminToast('删除成功'); setTimeout(function () { location.reload(); }, 400); }); }); }
});
function bindSaveAnn(d) {
    var btn = document.getElementById('saveAnnBtn');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var data = { action: 'save', id: d ? d.id : 0 };
        ['title', 'content', 'position', 'sort', 'status'].forEach(function (k) { var el = document.querySelector('[name="' + k + '"]'); data[k] = el ? el.value : ''; });
        adminPost('announcement.php?tab=announcements', data, function () { adminToast('保存成功'); setTimeout(function () { location.reload(); }, 400); });
    });
}
function bindSaveAd(d) {
    var btn = document.getElementById('saveAdBtn');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var data = { action: 'save', id: d ? d.id : 0 };
        ['name', 'type', 'content', 'image', 'link', 'position', 'sort', 'status'].forEach(function (k) { var el = document.querySelector('[name="' + k + '"]'); data[k] = el ? el.value : ''; });
        adminPost('announcement.php?tab=ads', data, function () { adminToast('保存成功'); setTimeout(function () { location.reload(); }, 400); });
    });
}
</script>
<?php include __DIR__ . '/footer.php'; ?>
