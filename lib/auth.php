<?php
/**
 * 前台用户认证
 */

// 获取当前登录用户
function current_user()
{
    if (empty($_SESSION['uid'])) {
        return null;
    }
    $user = DB::instance()->fetch('SELECT * FROM `' . DB_PREFIX . 'users` WHERE `id` = ?', array($_SESSION['uid']));
    if (!$user || (int)$user['status'] !== 1) {
        unset($_SESSION['uid']);
        return null;
    }
    return $user;
}

// 需要登录
function require_login()
{
    $user = current_user();
    if (!$user) {
        redirect('index.php?p=login');
    }
    return $user;
}

// 登录
function do_login($username, $password)
{
    $user = DB::instance()->fetch(
        'SELECT * FROM `' . DB_PREFIX . 'users` WHERE `username` = ? AND `is_admin` = 0',
        array($username)
    );
    if (!$user) {
        return array(false, '用户名或密码错误');
    }
    if ((int)$user['status'] !== 1) {
        return array(false, '账号已被封禁');
    }
    if ((int)$user['expire_date'] > 0 && $user['expire_date'] !== null && strtotime($user['expire_date']) < time()) {
        return array(false, '账号权限已过期，请联系管理员');
    }
    $hash = sha1($user['salt'] . $password);
    if (!hash_equals($user['password'], $hash)) {
        return array(false, '用户名或密码错误');
    }
    $_SESSION['uid'] = (int)$user['id'];
    DB::instance()->update('users', array('last_login' => date('Y-m-d H:i:s')), '`id` = ?', array($user['id']));
    return array(true, '登录成功');
}

// 获取用户等级
function user_level($user)
{
    $level = array(
        'name' => '普通用户',
        'max_file_size' => 0,
        'daily_upload_limit' => 0,
        'expire_date' => null,
    );
    if (!empty($user['level_id'])) {
        $row = DB::instance()->fetch('SELECT * FROM `' . DB_PREFIX . 'user_levels` WHERE `id` = ?', array($user['level_id']));
        if ($row) {
            $level['name'] = $row['name'];
            $level['max_file_size'] = (int)$row['max_file_size'];
            $level['daily_upload_limit'] = (int)$row['daily_upload_limit'];
            $level['expire_date'] = $row['expire_date'];
        }
    }
    // 用户个人覆盖
    if (!empty($user['file_size_limit'])) {
        $level['max_file_size'] = (int)$user['file_size_limit'];
    }
    if (!empty($user['daily_upload_limit'])) {
        $level['daily_upload_limit'] = (int)$user['daily_upload_limit'];
    }
    if (!empty($user['expire_date'])) {
        $level['expire_date'] = $user['expire_date'];
    }
    return $level;
}

// 今日已上传数量
function today_upload_count($uid)
{
    $today = date('Y-m-d 00:00:00');
    return DB::instance()->count(
        'files',
        '`uid` = ? AND `created_at` >= ?',
        array($uid, $today)
    );
}

// 管理员登录
function admin_logged()
{
    return !empty($_SESSION['admin_id']);
}

function require_admin()
{
    if (!admin_logged()) {
        redirect('login.php');
    }
}

function admin_login($username, $password)
{
    $user = DB::instance()->fetch(
        'SELECT * FROM `' . DB_PREFIX . 'users` WHERE `username` = ? AND `is_admin` = 1',
        array($username)
    );
    if (!$user) {
        return array(false, '管理员账号或密码错误');
    }
    $hash = sha1($user['salt'] . $password);
    if (!hash_equals($user['password'], $hash)) {
        return array(false, '管理员账号或密码错误');
    }
    $_SESSION['admin_id'] = (int)$user['id'];
    DB::instance()->update('users', array('last_login' => date('Y-m-d H:i:s')), '`id` = ?', array($user['id']));
    return array(true, '登录成功');
}
