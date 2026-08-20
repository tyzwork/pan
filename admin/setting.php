<?php
/**
 * 后台系统设置
 * 功能：站点设置、上传设置、安全设置、管理员密码修改
 */
require_once __DIR__ . '/common.php';
require_admin();

$title = '系统设置';
$page = 'setting';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'save_site') {
        set_setting('register_open', isset($_POST['register_open']) ? '1' : '0');
        set_setting('upload_open', isset($_POST['upload_open']) ? '1' : '0');
        set_setting('default_dl_limit', (string)max(0, (int)(isset($_POST['default_dl_limit']) ? $_POST['default_dl_limit'] : 0)));
        set_setting('global_daily_upload_limit', (string)max(0, (int)(isset($_POST['global_daily_upload_limit']) ? $_POST['global_daily_upload_limit'] : 0)));
        json_response(null, 0, '保存成功');
    }

    if ($action === 'save_upload') {
        set_setting('allow_extensions', trim(isset($_POST['allow_extensions']) ? $_POST['allow_extensions'] : ''));
        set_setting('forbidden_extensions', trim(isset($_POST['forbidden_extensions']) ? $_POST['forbidden_extensions'] : ''));
        set_setting('max_file_size', (string)max(0, (int)(isset($_POST['max_file_size']) ? $_POST['max_file_size'] : 0)));
        json_response(null, 0, '保存成功');
    }

    if ($action === 'save_security') {
        set_setting('safety_check', isset($_POST['safety_check']) ? '1' : '0');
        set_setting('safety_api_url', trim(isset($_POST['safety_api_url']) ? $_POST['safety_api_url'] : ''));
        set_setting('safety_api_key', trim(isset($_POST['safety_api_key']) ? $_POST['safety_api_key'] : ''));
        set_setting('login_fail_limit', (string)max(0, (int)(isset($_POST['login_fail_limit']) ? $_POST['login_fail_limit'] : 0)));
        json_response(null, 0, '保存成功');
    }

    if ($action === 'change_password') {
        $old = isset($_POST['old_password']) ? $_POST['old_password'] : '';
        $new = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        if (strlen($new) < 6) {
            json_response(null, 1, '新密码长度至少 6 位');
        }
        if ($new !== $confirm) {
            json_response(null, 1, '两次输入的新密码不一致');
        }
        $admin = admin_info();
        $hash = sha1($admin['salt'] . $old);
        if (!hash_equals($admin['password'], $hash)) {
            json_response(null, 1, '原密码错误');
        }
        $salt = bin2hex(random_bytes(8));
        DB::instance()->update('users', array('salt' => $salt, 'password' => sha1($salt . $new)), '`id` = ?', array($admin['id']));
        json_response(null, 0, '密码修改成功，请重新登录');
    }

    json_response(null, 404, '未知操作');
}

$registerOpen = get_setting('register_open', '1');
$uploadOpen = get_setting('upload_open', '1');
$defaultDlLimit = get_setting('default_dl_limit', '0');
$globalDailyLimit = get_setting('global_daily_upload_limit', '100');
$allowExt = get_setting('allow_extensions', ALLOW_EXTENSIONS);
$forbiddenExt = get_setting('forbidden_extensions', FORBIDDEN_EXTENSIONS);
$maxFileSize = get_setting('max_file_size', (string)UPLOAD_MAX_SIZE);
$safetyCheck = get_setting('safety_check', '0');
$safetyApiUrl = get_setting('safety_api_url', '');
$safetyApiKey = get_setting('safety_api_key', '');
$loginFailLimit = get_setting('login_fail_limit', '5');
$dbInfo = DB::instance()->pdo()->getAttribute(PDO::ATTR_SERVER_VERSION);

include __DIR__ . '/header.php';
?>
<div class="admin-card">
    <div class="card-head"><h3>站点设置</h3></div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group">
                <label>是否开放注册</label>
                <label class="form-switch"><input type="checkbox" id="registerOpenInput" <?php echo $registerOpen === '1' ? 'checked' : ''; ?>><span class="slider"></span></label>
            </div>
            <div class="form-group">
                <label>是否开放上传</label>
                <label class="form-switch"><input type="checkbox" id="uploadOpenInput" <?php echo $uploadOpen === '1' ? 'checked' : ''; ?>><span class="slider"></span></label>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>全站每日上传限制（个）</label>
                <input type="number" class="form-control" id="globalDailyLimitInput" value="<?php echo (int)$globalDailyLimit; ?>" min="0">
                <div class="form-help">0 表示不限；高级用户不受此限制</div>
            </div>
            <div class="form-group">
                <label>默认下载次数限制（0 不限）</label>
                <input type="number" class="form-control" id="defaultDlLimitInput" value="<?php echo (int)$defaultDlLimit; ?>" min="0">
            </div>
        </div>
        <button type="button" class="btn btn-primary" data-save-group="site">保存站点设置</button>
    </div>
</div>

<div class="admin-card">
    <div class="card-head"><h3>上传设置</h3></div>
    <div class="card-body">
        <div class="form-group">
            <label>单文件最大大小（字节）</label>
            <input type="number" class="form-control" id="maxFileSizeInput" value="<?php echo (int)$maxFileSize; ?>" min="0">
        </div>
        <div class="form-group">
            <label>允许上传的扩展名（逗号分隔，留空表示不限制）</label>
            <textarea class="form-control" id="allowExtInput" style="min-height:70px;font-family:monospace"><?php echo e($allowExt); ?></textarea>
        </div>
        <div class="form-group">
            <label>禁止上传的扩展名（黑名单，始终生效）</label>
            <textarea class="form-control" id="forbiddenExtInput" style="min-height:70px;font-family:monospace"><?php echo e($forbiddenExt); ?></textarea>
        </div>
        <button type="button" class="btn btn-primary" data-save-group="upload">保存上传设置</button>
    </div>
</div>

<div class="admin-card">
    <div class="card-head"><h3>安全设置</h3></div>
    <div class="card-body">
        <div class="form-group">
            <label>内容安全审核（对接第三方 API 扫描图片）</label>
            <label class="form-switch"><input type="checkbox" id="safetyCheckInput" <?php echo $safetyCheck === '1' ? 'checked' : ''; ?>><span class="slider"></span></label>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>审核 API 地址</label>
                <input type="text" class="form-control" id="safetyApiUrlInput" value="<?php echo e($safetyApiUrl); ?>" placeholder="https://api.example.com/moderation">
            </div>
            <div class="form-group">
                <label>审核 API 密钥</label>
                <input type="password" class="form-control" id="safetyApiKeyInput" value="<?php echo e($safetyApiKey); ?>">
            </div>
        </div>
        <div class="form-group">
            <label>后台登录失败次数限制</label>
            <input type="number" class="form-control" id="loginFailLimitInput" value="<?php echo (int)$loginFailLimit; ?>" min="0" style="max-width:200px">
            <div class="form-help">0 表示不限制</div>
        </div>
        <button type="button" class="btn btn-primary" data-save-group="security">保存安全设置</button>
    </div>
</div>

<div class="admin-card">
    <div class="card-head"><h3>修改管理员密码</h3></div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group">
                <label>原密码</label>
                <input type="password" class="form-control" id="oldPassInput">
            </div>
            <div class="form-group">
                <label>新密码</label>
                <input type="password" class="form-control" id="newPassInput">
            </div>
            <div class="form-group">
                <label>确认新密码</label>
                <input type="password" class="form-control" id="confirmPassInput">
            </div>
        </div>
        <button type="button" class="btn btn-danger" id="changePassBtn">修改密码</button>
    </div>
</div>

<div class="admin-card">
    <div class="card-head"><h3>环境信息</h3></div>
    <div class="card-body">
        <div class="admin-table-wrap">
            <table class="admin-table">
                <tbody>
                    <tr><td style="width:200px">系统版本</td><td><?php echo e(APP_NAME); ?> v<?php echo e(APP_VERSION); ?></td></tr>
                    <tr><td>PHP 版本</td><td><?php echo e(PHP_VERSION); ?></td></tr>
                    <tr><td>MySQL 版本</td><td><?php echo e($dbInfo); ?></td></tr>
                    <tr><td>上传大小限制</td><td><?php echo e(ini_get('upload_max_filesize')); ?> / <?php echo e(ini_get('post_max_size')); ?></td></tr>
                    <tr><td>时区</td><td><?php echo e(date_default_timezone_get()); ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('[data-save-group]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var group = btn.getAttribute('data-save-group');
        var data = { action: 'save_' + group };
        if (group === 'site') {
            data.register_open = document.getElementById('registerOpenInput').checked ? '1' : '0';
            data.upload_open = document.getElementById('uploadOpenInput').checked ? '1' : '0';
            data.global_daily_upload_limit = document.getElementById('globalDailyLimitInput').value;
            data.default_dl_limit = document.getElementById('defaultDlLimitInput').value;
        } else if (group === 'upload') {
            data.allow_extensions = document.getElementById('allowExtInput').value;
            data.forbidden_extensions = document.getElementById('forbiddenExtInput').value;
            data.max_file_size = document.getElementById('maxFileSizeInput').value;
        } else if (group === 'security') {
            data.safety_check = document.getElementById('safetyCheckInput').checked ? '1' : '0';
            data.safety_api_url = document.getElementById('safetyApiUrlInput').value;
            data.safety_api_key = document.getElementById('safetyApiKeyInput').value;
            data.login_fail_limit = document.getElementById('loginFailLimitInput').value;
        }
        adminPost('setting.php', data, function () { adminToast('保存成功'); });
    });
});
document.getElementById('changePassBtn').addEventListener('click', function () {
    var data = {
        action: 'change_password',
        old_password: document.getElementById('oldPassInput').value,
        new_password: document.getElementById('newPassInput').value,
        confirm_password: document.getElementById('confirmPassInput').value
    };
    adminPost('setting.php', data, function () { adminToast('密码修改成功，请重新登录'); setTimeout(function () { location.href = 'logout.php'; }, 800); });
});
</script>
<?php include __DIR__ . '/footer.php'; ?>
