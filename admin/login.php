<?php
/**
 * 后台登录
 */
require_once __DIR__ . '/common.php';

// 已登录则直接进入
if (admin_logged()) {
    redirect('index.php');
}

$error = '';
$locked = '';

// 登录失败限制
list($canLogin, $locked) = login_fail_check();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canLogin) {
    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    if ($username === '' || $password === '') {
        $error = '请输入账号和密码';
    } else {
        list($ok, $msg) = admin_login($username, $password);
        login_fail_record($ok);
        if ($ok) {
            redirect('index.php');
        } else {
            $error = $msg;
        }
    }
}

$siteName = get_setting('site_name', APP_NAME);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>后台登录 - <?php echo e($siteName); ?></title>
<link rel="stylesheet" href="<?php echo e(site_url('assets/css/admin.css')); ?>">
</head>
<body>
<div class="admin-login-wrap">
    <div class="admin-login-card">
        <div class="login-logo">
            <h1><?php echo e($siteName); ?></h1>
            <p>管理后台登录</p>
        </div>
        <?php if ($error !== ''): ?>
            <div class="admin-login-alert"><?php echo e($error); ?></div>
        <?php endif; ?>
        <?php if ($locked !== ''): ?>
            <div class="admin-login-alert"><?php echo e($locked); ?></div>
        <?php endif; ?>
        <?php if ($canLogin): ?>
        <form method="post" autocomplete="off">
            <div class="field">
                <label for="username">管理员账号</label>
                <input type="text" id="username" name="username" value="<?php echo e(isset($_POST['username']) ? $_POST['username'] : ''); ?>" required>
            </div>
            <div class="field">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn-login">登 录</button>
        </form>
        <div class="admin-login-info">默认账号 admin，密码请在安装后及时修改</div>
        <?php else: ?>
        <div class="admin-login-info"><a href="login.php">点击刷新页面重试</a></div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
