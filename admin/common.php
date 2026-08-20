<?php
/**
 * 彩虹外链网盘二开版 - 后台公共入口
 * 所有后台页面（login 除外）先引入本文件
 */

define('PAN_ADMIN', true);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/lib/db.php';
require_once dirname(__DIR__) . '/lib/functions.php';
require_once dirname(__DIR__) . '/lib/auth.php';
require_once dirname(__DIR__) . '/lib/pager.php';

// 禁止 iframe 嵌套，防点击劫持
header('X-Frame-Options: SAMEORIGIN');

// 登录失败次数限制
function login_fail_check()
{
    $limit = (int)get_setting('login_fail_limit', '5');
    if ($limit <= 0) {
        return array(true, '');
    }
    $ip = get_ip();
    $key = 'login_fail_' . md5($ip);
    $count = isset($_SESSION[$key]) ? (int)$_SESSION[$key] : 0;
    if ($count >= $limit) {
        return array(false, '登录失败次数过多，请稍后再试');
    }
    return array(true, '');
}

function login_fail_record($success)
{
    $limit = (int)get_setting('login_fail_limit', '5');
    if ($limit <= 0) {
        return;
    }
    $key = 'login_fail_' . md5(get_ip());
    if ($success) {
        unset($_SESSION[$key]);
        return;
    }
    $count = isset($_SESSION[$key]) ? (int)$_SESSION[$key] : 0;
    $_SESSION[$key] = $count + 1;
}

// 后台顶部当前管理员信息
function admin_info()
{
    static $info = null;
    if ($info === null) {
        $info = DB::instance()->fetch('SELECT * FROM `' . DB_PREFIX . 'users` WHERE `id` = ?', array((int)$_SESSION['admin_id']));
    }
    return $info;
}

// 统计信息
function admin_stats()
{
    return array(
        'files'     => (int)DB::instance()->count('files'),
        'users'     => (int)DB::instance()->count('users', '`is_admin` = 0'),
        'today'     => (int)DB::instance()->count('files', '`created_at` >= ?', array(date('Y-m-d 00:00:00'))),
        'downloads' => (int)DB::instance()->fetchColumn('SELECT IFNULL(SUM(`dl_count`),0) FROM `' . DB_PREFIX . 'files`'),
        'storage'   => (int)DB::instance()->fetchColumn('SELECT IFNULL(SUM(`filesize`),0) FROM `' . DB_PREFIX . 'files`'),
        'levels'    => (int)DB::instance()->count('user_levels'),
    );
}

// 最近 7 天上传统计
function upload_trend()
{
    $rows = array();
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime('-' . $i . ' day'));
        $rows[$date] = (int)DB::instance()->count('files', 'DATE(`created_at`) = ?', array($date));
    }
    return $rows;
}
