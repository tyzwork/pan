<?php
/**
 * 后台用户管理
 * 功能：用户列表/搜索/封禁/解封/删除/查看文件、用户等级管理
 */
require_once __DIR__ . '/common.php';
require_admin();

$title = '用户管理';
$page = 'user';

$action = isset($_POST['action']) ? $_POST['action'] : '';
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'users';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    // ---------- 用户操作 ----------
    if ($tab === 'users') {
        if ($action === 'save_user') {
            $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
            $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
            $password = isset($_POST['password']) ? $_POST['password'] : '';
            $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
            $levelId = (int)(isset($_POST['level_id']) ? $_POST['level_id'] : 0);
            $fileSizeLimit = (int)(isset($_POST['file_size_limit']) ? $_POST['file_size_limit'] : 0);
            $dailyUploadLimit = (int)(isset($_POST['daily_upload_limit']) ? $_POST['daily_upload_limit'] : 0);
            $expireDate = isset($_POST['expire_date']) && $_POST['expire_date'] !== '' ? $_POST['expire_date'] : null;
            $status = (int)(isset($_POST['status']) ? $_POST['status'] : 1);

            if ($username === '') {
                json_response(null, 1, '用户名不能为空');
            }
            $exists = DB::instance()->fetch('SELECT `id` FROM `' . DB_PREFIX . 'users` WHERE `username` = ? AND `id` <> ? AND `is_admin` = 0', array($username, $id));
            if ($exists) {
                json_response(null, 1, '用户名已存在');
            }

            $data = array(
                'username' => $username,
                'email' => $email,
                'level_id' => $levelId,
                'file_size_limit' => $fileSizeLimit,
                'daily_upload_limit' => $dailyUploadLimit,
                'expire_date' => $expireDate,
                'status' => $status,
            );
            if ($password !== '') {
                $salt = bin2hex(random_bytes(8));
                $data['salt'] = $salt;
                $data['password'] = sha1($salt . $password);
            }
            if ($id > 0) {
                DB::instance()->update('users', $data, '`id` = ? AND `is_admin` = 0', array($id));
            } else {
                if ($password === '') {
                    json_response(null, 1, '新建用户必须设置密码');
                }
                $data['salt'] = bin2hex(random_bytes(8));
                $data['password'] = sha1($data['salt'] . $password);
                $data['is_admin'] = 0;
                $data['created_at'] = date('Y-m-d H:i:s');
                DB::instance()->insert('users', $data);
            }
            json_response(null, 0, '保存成功');
        }
        if ($action === 'toggle_status') {
            $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
            $user = DB::instance()->fetch('SELECT * FROM `' . DB_PREFIX . 'users` WHERE `id` = ? AND `is_admin` = 0', array($id));
            if (!$user) {
                json_response(null, 1, '用户不存在');
            }
            $newStatus = (int)$user['status'] === 1 ? 0 : 1;
            DB::instance()->update('users', array('status' => $newStatus), '`id` = ?', array($id));
            json_response(null, 0, $newStatus === 1 ? '已解封' : '已封禁');
        }
        if ($action === 'delete_user') {
            $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
            $user = DB::instance()->fetch('SELECT * FROM `' . DB_PREFIX . 'users` WHERE `id` = ? AND `is_admin` = 0', array($id));
            if (!$user) {
                json_response(null, 1, '用户不存在');
            }
            DB::instance()->delete('files', '`uid` = ?', array($id));
            DB::instance()->delete('users', '`id` = ?', array($id));
            json_response(null, 0, '删除成功');
        }
        if ($action === 'reset_password') {
            $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
            $newPass = trim(isset($_POST['password']) ? $_POST['password'] : '');
            if ($newPass === '') {
                json_response(null, 1, '密码不能为空');
            }
            $salt = bin2hex(random_bytes(8));
            DB::instance()->update('users', array('salt' => $salt, 'password' => sha1($salt . $newPass)), '`id` = ? AND `is_admin` = 0', array($id));
            json_response(null, 0, '密码已重置');
        }
        json_response(null, 404, '未知操作');
    }

    // ---------- 等级操作 ----------
    if ($tab === 'levels') {
        if ($action === 'save_level') {
            $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
            $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
            $maxFileSize = (int)(isset($_POST['max_file_size']) ? $_POST['max_file_size'] : 0);
            $dailyUploadLimit = (int)(isset($_POST['daily_upload_limit']) ? $_POST['daily_upload_limit'] : 0);
            $expireDate = isset($_POST['expire_date']) && $_POST['expire_date'] !== '' ? $_POST['expire_date'] : null;
            $remark = trim(isset($_POST['remark']) ? $_POST['remark'] : '');
            if ($name === '') {
                json_response(null, 1, '等级名称不能为空');
            }
            $data = array(
                'name' => $name,
                'max_file_size' => $maxFileSize,
                'daily_upload_limit' => $dailyUploadLimit,
                'expire_date' => $expireDate,
                'remark' => $remark,
            );
            if ($id > 0) {
                DB::instance()->update('user_levels', $data, '`id` = ?', array($id));
            } else {
                DB::instance()->insert('user_levels', $data);
            }
            json_response(null, 0, '保存成功');
        }
        if ($action === 'delete_level') {
            $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
            DB::instance()->update('users', array('level_id' => 0), '`level_id` = ?', array($id));
            DB::instance()->delete('user_levels', '`id` = ?', array($id));
            json_response(null, 0, '删除成功');
        }
        json_response(null, 404, '未知操作');
    }
}

// 查询数据
$q = trim(isset($_GET['q']) ? $_GET['q'] : '');
$pageInfo = get_page(1, 15);
$where = '';
$params = array();
if ($q !== '') {
    $where = ' WHERE u.`username` LIKE ? AND u.`is_admin` = 0';
    $params[] = '%' . $q . '%';
} else {
    $where = ' WHERE u.`is_admin` = 0';
}
$total = (int)DB::instance()->fetchColumn('SELECT COUNT(*) FROM `' . DB_PREFIX . 'users` u' . $where, $params);
$users = DB::instance()->fetchAll(
    'SELECT u.*, l.name AS level_name FROM `' . DB_PREFIX . 'users` u LEFT JOIN `' . DB_PREFIX . 'user_levels` l ON l.id = u.level_id' .
    $where . ' ORDER BY u.id DESC LIMIT ' . (int)$pageInfo['offset'] . ',' . (int)$pageInfo['size'],
    $params
);
$levels = DB::instance()->fetchAll('SELECT * FROM `' . DB_PREFIX . 'user_levels` ORDER BY `id` ASC');
$pager = new Pager($total, $pageInfo['page'], $pageInfo['size']);

include __DIR__ . '/header.php';
?>
<div class="tabs" data-tabs="usertabs">
    <div class="tab-item <?php echo $tab === 'users' ? 'active' : ''; ?>" data-target="pane-users">用户列表</div>
    <div class="tab-item <?php echo $tab === 'levels' ? 'active' : ''; ?>" data-target="pane-levels">用户等级</div>
</div>

<div id="pane-users" data-pane-group="usertabs" class="tab-pane <?php echo $tab === 'users' ? 'active' : ''; ?>">
    <div class="admin-card">
        <div class="card-head">
            <h3>用户列表</h3>
            <button type="button" class="btn btn-primary" id="addUserBtn">新增用户</button>
        </div>
        <div class="card-body">
            <form class="toolbar" method="get" action="user.php">
                <input type="hidden" name="tab" value="users">
                <input type="text" name="q" class="form-control" style="width:240px" placeholder="搜索用户名" value="<?php echo e($q); ?>">
                <button type="submit" class="btn btn-primary">搜索</button>
            </form>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>用户名</th>
                            <th>等级</th>
                            <th>文件数</th>
                            <th>个人限制</th>
                            <th>到期时间</th>
                            <th>状态</th>
                            <th>注册时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                        <tr><td colspan="9"><div class="empty-state"><p>暂无用户</p></div></td></tr>
                        <?php else: foreach ($users as $u): ?>
                        <?php
                            $fileCount = (int)DB::instance()->count('files', '`uid` = ?', array($u['id']));
                            $expired = $u['expire_date'] && $u['expire_date'] !== '0000-00-00' && strtotime($u['expire_date']) < time();
                        ?>
                        <tr>
                            <td><?php echo (int)$u['id']; ?></td>
                            <td><?php echo e($u['username']); ?></td>
                            <td><span class="badge badge-purple"><?php echo $u['level_name'] ? e($u['level_name']) : '普通'; ?></span></td>
                            <td><?php echo $fileCount; ?></td>
                            <td>
                                <?php if ($u['file_size_limit'] > 0 || $u['daily_upload_limit'] > 0): ?>
                                    <span class="badge badge-info"><?php echo e(format_size($u['file_size_limit'])); ?> / 日<?php echo (int)$u['daily_upload_limit']; ?>个</span>
                                <?php else: ?>
                                    <span class="badge badge-muted">默认</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['expire_date'] && $u['expire_date'] !== '0000-00-00'): ?>
                                    <span class="badge <?php echo $expired ? 'badge-danger' : 'badge-warning'; ?>"><?php echo e($u['expire_date']); ?></span>
                                <?php else: ?>
                                    <span class="badge badge-muted">永久</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int)$u['status'] === 1): ?>
                                    <span class="badge badge-success">正常</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">已封禁</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($u['created_at']); ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" data-edit-user='<?php echo e(json_encode(array(
                                    'id' => (int)$u['id'], 'username' => $u['username'], 'email' => $u['email'],
                                    'level_id' => (int)$u['level_id'], 'file_size_limit' => (int)$u['file_size_limit'],
                                    'daily_upload_limit' => (int)$u['daily_upload_limit'], 'expire_date' => $u['expire_date'] === '0000-00-00' ? '' : $u['expire_date'],
                                    'status' => (int)$u['status'],
                                ), JSON_UNESCAPED_UNICODE)); ?>'>编辑</button>
                                <button type="button" class="btn btn-sm <?php echo (int)$u['status'] === 1 ? 'btn-warning' : 'btn-success'; ?>" data-toggle-user="<?php echo (int)$u['id']; ?>"><?php echo (int)$u['status'] === 1 ? '封禁' : '解封'; ?></button>
                                <button type="button" class="btn btn-sm btn-danger" data-del-user="<?php echo (int)$u['id']; ?>" data-name="<?php echo e($u['username']); ?>">删除</button>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <?php echo $pager->html(); ?>
        </div>
    </div>
</div>

<div id="pane-levels" data-pane-group="usertabs" class="tab-pane <?php echo $tab === 'levels' ? 'active' : ''; ?>">
    <div class="admin-card">
        <div class="card-head">
            <h3>用户等级</h3>
            <button type="button" class="btn btn-primary" id="addLevelBtn">新增等级</button>
        </div>
        <div class="card-body">
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>等级名称</th>
                            <th>最大文件大小</th>
                            <th>每日上传限制</th>
                            <th>权限有效期</th>
                            <th>备注</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($levels)): ?>
                        <tr><td colspan="7"><div class="empty-state"><p>暂无等级</p></div></td></tr>
                        <?php else: foreach ($levels as $lv): ?>
                        <tr>
                            <td><?php echo (int)$lv['id']; ?></td>
                            <td><span class="badge badge-purple"><?php echo e($lv['name']); ?></span></td>
                            <td><?php echo $lv['max_file_size'] > 0 ? e(format_size($lv['max_file_size'])) : '不限'; ?></td>
                            <td><?php echo (int)$lv['daily_upload_limit'] > 0 ? (int)$lv['daily_upload_limit'] . ' 个/天' : '不限'; ?></td>
                            <td><?php echo $lv['expire_date'] ? e($lv['expire_date']) : '永久'; ?></td>
                            <td><?php echo e($lv['remark']); ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" data-edit-level='<?php echo e(json_encode(array(
                                    'id' => (int)$lv['id'], 'name' => $lv['name'], 'max_file_size' => (int)$lv['max_file_size'],
                                    'daily_upload_limit' => (int)$lv['daily_upload_limit'], 'expire_date' => $lv['expire_date'],
                                    'remark' => $lv['remark'],
                                ), JSON_UNESCAPED_UNICODE)); ?>'>编辑</button>
                                <button type="button" class="btn btn-sm btn-danger" data-del-level="<?php echo (int)$lv['id']; ?>">删除</button>
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
var levels = <?php echo json_encode($levels, JSON_UNESCAPED_UNICODE); ?>;

function userFormHtml(d) {
    d = d || {};
    var levelOpts = levels.map(function (l) {
        return '<option value="' + l.id + '" ' + (parseInt(d.level_id) === parseInt(l.id) ? 'selected' : '') + '>' + l.name + '</option>';
    }).join('');
    return '<div class="form-group"><label>用户名</label><input type="text" class="form-control" name="username" value="' + (d.username || '') + '"></div>' +
        '<div class="form-group"><label>密码' + (d.id ? '（留空不修改）' : '') + '</label><input type="password" class="form-control" name="password" placeholder="请输入密码"></div>' +
        '<div class="form-group"><label>邮箱</label><input type="text" class="form-control" name="email" value="' + (d.email || '') + '"></div>' +
        '<div class="form-row">' +
        '<div class="form-group"><label>用户等级</label><select class="form-control" name="level_id">' + levelOpts + '</select></div>' +
        '<div class="form-group"><label>状态</label><select class="form-control" name="status"><option value="1" ' + (d.status == 1 ? 'selected' : '') + '>正常</option><option value="0" ' + (d.status == 0 ? 'selected' : '') + '>封禁</option></select></div>' +
        '</div>' +
        '<div class="form-row">' +
        '<div class="form-group"><label>单文件大小限制（字节，0 为不限）</label><input type="number" class="form-control" name="file_size_limit" value="' + (d.file_size_limit || 0) + '"></div>' +
        '<div class="form-group"><label>每日上传数量（0 为不限）</label><input type="number" class="form-control" name="daily_upload_limit" value="' + (d.daily_upload_limit || 0) + '"></div>' +
        '</div>' +
        '<div class="form-group"><label>权限有效期</label><input type="date" class="form-control" name="expire_date" value="' + (d.expire_date || '') + '"></div>';
}
function levelFormHtml(d) {
    d = d || {};
    return '<div class="form-group"><label>等级名称</label><input type="text" class="form-control" name="name" value="' + (d.name || '') + '"></div>' +
        '<div class="form-row">' +
        '<div class="form-group"><label>最大文件大小（字节，0 为不限）</label><input type="number" class="form-control" name="max_file_size" value="' + (d.max_file_size || 0) + '"></div>' +
        '<div class="form-group"><label>每日上传数量（0 为不限）</label><input type="number" class="form-control" name="daily_upload_limit" value="' + (d.daily_upload_limit || 0) + '"></div>' +
        '</div>' +
        '<div class="form-group"><label>权限有效期</label><input type="date" class="form-control" name="expire_date" value="' + (d.expire_date || '') + '"></div>' +
        '<div class="form-group"><label>备注</label><input type="text" class="form-control" name="remark" value="' + (d.remark || '') + '"></div>';
}

document.getElementById('addUserBtn').addEventListener('click', function () {
    openModal('新增用户', userFormHtml({ status: 1 }), '<button class="btn" data-close-modal>取消</button><button class="btn btn-primary" id="saveUserBtn">保存</button>');
    bindSaveUser(null);
});
document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-edit-user]');
    if (btn) {
        var d = JSON.parse(btn.getAttribute('data-edit-user'));
        openModal('编辑用户', userFormHtml(d), '<button class="btn" data-close-modal>取消</button><button class="btn btn-primary" id="saveUserBtn">保存</button>');
        bindSaveUser(d);
    }
});
function bindSaveUser(d) {
    var btn = document.getElementById('saveUserBtn');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var data = { action: 'save_user', id: d ? d.id : 0 };
        ['username', 'password', 'email', 'level_id', 'status', 'file_size_limit', 'daily_upload_limit', 'expire_date'].forEach(function (k) {
            var el = document.querySelector('[name="' + k + '"]');
            data[k] = el ? el.value : '';
        });
        adminPost('user.php?tab=users', data, function () { adminToast('保存成功'); setTimeout(function () { location.reload(); }, 400); });
    });
}

document.getElementById('addLevelBtn').addEventListener('click', function () {
    openModal('新增等级', levelFormHtml({}), '<button class="btn" data-close-modal>取消</button><button class="btn btn-primary" id="saveLevelBtn">保存</button>');
    bindSaveLevel(null);
});
document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-edit-level]');
    if (btn) {
        var d = JSON.parse(btn.getAttribute('data-edit-level'));
        openModal('编辑等级', levelFormHtml(d), '<button class="btn" data-close-modal>取消</button><button class="btn btn-primary" id="saveLevelBtn">保存</button>');
        bindSaveLevel(d);
    }
    var del = e.target.closest('[data-del-level]');
    if (del) {
        adminConfirm('删除等级', '确定删除该等级吗？关联用户将变为普通等级。', function () {
            adminPost('user.php?tab=levels', { action: 'delete_level', id: del.getAttribute('data-del-level') }, function () {
                adminToast('删除成功'); setTimeout(function () { location.reload(); }, 400);
            });
        });
    }
    var tu = e.target.closest('[data-toggle-user]');
    if (tu) {
        adminPost('user.php?tab=users', { action: 'toggle_status', id: tu.getAttribute('data-toggle-user') }, function () {
            adminToast('操作成功'); setTimeout(function () { location.reload(); }, 400);
        });
    }
    var du = e.target.closest('[data-del-user]');
    if (du) {
        adminConfirm('删除用户', '确定删除用户 "' + du.getAttribute('data-name') + '" 吗？其名下所有文件将一并删除。', function () {
            adminPost('user.php?tab=users', { action: 'delete_user', id: du.getAttribute('data-del-user') }, function () {
                adminToast('删除成功'); setTimeout(function () { location.reload(); }, 400);
            });
        });
    }
});
function bindSaveLevel(d) {
    var btn = document.getElementById('saveLevelBtn');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var data = { action: 'save_level', id: d ? d.id : 0 };
        ['name', 'max_file_size', 'daily_upload_limit', 'expire_date', 'remark'].forEach(function (k) {
            var el = document.querySelector('[name="' + k + '"]');
            data[k] = el ? el.value : '';
        });
        adminPost('user.php?tab=levels', data, function () { adminToast('保存成功'); setTimeout(function () { location.reload(); }, 400); });
    });
}
</script>
<?php include __DIR__ . '/footer.php'; ?>
