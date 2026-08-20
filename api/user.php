<?php
/**
 * 用户认证 API
 * 支持: 登录、注册、登出、当前用户信息
 */

require_once dirname(__FILE__) . '/../config.php';
require_once dirname(__FILE__) . '/../lib/db.php';
require_once dirname(__FILE__) . '/../lib/functions.php';
require_once dirname(__FILE__) . '/../lib/auth.php';

$action = isset($_POST['action']) ? $_POST['action'] : 'me';

if ($action === 'me') {
    $user = current_user();
    json_response($user ? array(
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'level' => user_level($user),
        'is_admin' => !empty($user['is_admin']),
    ) : null, 0, 'ok');
}

if ($action === 'login') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    // 登录失败次数限制
    $failKey = 'login_fail_' . get_ip();
    $fails = isset($_SESSION[$failKey]) ? (int)$_SESSION[$failKey] : 0;
    $limit = (int)get_setting('login_fail_limit', '5');
    if ($limit > 0 && $fails >= $limit) {
        json_response(null, 429, '尝试次数过多，请稍后再试');
    }
    if ($username === '' || $password === '') {
        json_response(null, 400, '请输入用户名和密码');
    }
    list($ok, $msg) = do_login($username, $password);
    if (!$ok) {
        $_SESSION[$failKey] = $fails + 1;
        json_response(null, 400, $msg);
    }
    unset($_SESSION[$failKey]);
    json_response(array('id' => $_SESSION['uid']), 0, '登录成功');
}

if ($action === 'register') {
    if (get_setting('register_open', '1') !== '1') {
        json_response(null, 403, '系统暂未开放注册');
    }
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $password2 = isset($_POST['password2']) ? $_POST['password2'] : '';

    if (!preg_match('/^[a-zA-Z0-9_\x{4e00}-\x{9fa5}]{3,30}$/u', $username)) {
        json_response(null, 400, '用户名须为 3-30 位字母、数字、下划线或中文');
    }
    if (strlen($password) < 6) {
        json_response(null, 400, '密码长度不能少于 6 位');
    }
    if ($password !== $password2) {
        json_response(null, 400, '两次输入的密码不一致');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(null, 400, '邮箱格式不正确');
    }
    if (DB::instance()->count('users', '`username` = ?', array($username)) > 0) {
        json_response(null, 400, '用户名已存在');
    }
    $salt = bin2hex(random_bytes(8));
    $id = DB::instance()->insert('users', array(
        'username' => $username,
        'password' => sha1($salt . $password),
        'salt' => $salt,
        'email' => $email,
        'level_id' => 0,
        'status' => 1,
        'is_admin' => 0,
        'created_at' => date('Y-m-d H:i:s'),
    ));
    $_SESSION['uid'] = $id;
    json_response(array('id' => $id), 0, '注册成功');
}

if ($action === 'logout') {
    unset($_SESSION['uid']);
    json_response(null, 0, '已退出');
}

json_response(null, 400, '未知操作');
