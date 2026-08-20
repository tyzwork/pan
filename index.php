<?php
/**
 * 前台入口（前端控制器）
 * 通过 ?p= 参数路由到模板
 */

require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/lib/db.php';
require_once dirname(__FILE__) . '/lib/functions.php';
require_once dirname(__FILE__) . '/lib/auth.php';
require_once dirname(__FILE__) . '/lib/pager.php';

// 检查是否已安装
$isInstalled = false;
try {
    $isInstalled = DB::instance()->count('settings') >= 0 || true;
    DB::instance()->fetch('SELECT `v` FROM `' . DB_PREFIX . 'settings` LIMIT 1');
    $isInstalled = true;
} catch (Exception $ex) {
    $isInstalled = false;
}
if (!$isInstalled) {
    redirect('install.php');
}

$page = isset($_GET['p']) ? $_GET['p'] : 'index';
$allowed = array('index', 'upload', 'view', 'login', 'register', 'user', 'logout', 'search');
if (!in_array($page, $allowed, true)) {
    $page = 'index';
}

$user = current_user();

// 需要登录的页面
if (in_array($page, array('upload', 'user'), true)) {
    if (!$user) {
        redirect('index.php?p=login');
    }
    if (get_setting('upload_open', '1') !== '1' && $page === 'upload' && empty($user['is_admin'])) {
        $errorMsg = '系统暂未开放上传';
        $page = 'index';
    }
}

// 登出
if ($page === 'logout') {
    unset($_SESSION['uid']);
    redirect('index.php');
}

$siteName = get_setting('site_name', APP_NAME);
$theme = get_setting('theme', 'default');
$themeSync = get_setting('theme_sync', '1');

include dirname(__FILE__) . '/template/header.php';
include dirname(__FILE__) . '/template/' . $page . '.php';
include dirname(__FILE__) . '/template/footer.php';
