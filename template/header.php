<?php
/**
 * 前台公共头部模板
 * 变量: $siteName, $theme, $page, $user
 */
$siteDesc = get_setting('site_desc', '');
$siteLogo = get_setting('site_logo', '');
$bgType = get_setting('bg_type', 'gradient');
$bgValue = get_setting('bg_value', '');
$customCss = get_setting('custom_css', '');

$bgStyle = '';
if ($bgType === 'color' && $bgValue !== '') {
    $bgStyle = 'background:' . e($bgValue) . ';';
} elseif ($bgType === 'gradient' && $bgValue !== '') {
    $bgStyle = 'background:' . e($bgValue) . ';background-attachment:fixed;';
} elseif ($bgType === 'image' && $bgValue !== '') {
    $bgStyle = 'background:url(' . e($bgValue) . ') center/cover fixed no-repeat;';
}

$announcement = DB::instance()->fetch(
    'SELECT * FROM `' . DB_PREFIX . 'announcements` WHERE `position` = ? AND `status` = 1 ORDER BY `sort` ASC, `id` DESC LIMIT 1',
    array('top')
);
$sidebarAds = DB::instance()->fetchAll(
    'SELECT * FROM `' . DB_PREFIX . 'ads` WHERE `position` = ? AND `status` = 1 ORDER BY `sort` ASC, `id` DESC',
    array('sidebar')
);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo $page === 'index' ? e($siteName) . (get_setting('site_desc') ? ' - ' . e($siteDesc) : '') : e($siteName); ?></title>
<meta name="description" content="<?php echo e($siteDesc); ?>">
<link rel="stylesheet" href="<?php echo e(site_url('assets/css/main.css')); ?>">
<link rel="stylesheet" href="<?php echo e(site_url('assets/css/themes/' . e($theme) . '.css')); ?>">
<?php if ($customCss !== ''): ?>
<style><?php echo $customCss; ?></style>
<?php endif; ?>
</head>
<body data-page="<?php echo e($page); ?>" style="<?php echo $bgStyle; ?>">
<div class="bg-canvas" aria-hidden="true"></div>

<?php if ($announcement): ?>
<div class="announce-bar" id="announceBar">
    <span class="announce-label">公告</span>
    <span class="announce-text"><?php echo e($announcement['title']); ?>：<?php echo e($announcement['content']); ?></span>
    <button class="announce-close" type="button" data-close-announce aria-label="关闭">x</button>
</div>
<?php endif; ?>

<header class="site-header" id="siteHeader">
    <nav class="nav container">
        <a class="logo" href="<?php echo e(site_url('index.php')); ?>">
            <?php if ($siteLogo !== ''): ?>
                <img src="<?php echo e($siteLogo); ?>" alt="logo">
            <?php else: ?>
                <span class="logo-mark">彩</span>
            <?php endif; ?>
            <span class="logo-text"><?php echo e($siteName); ?></span>
        </a>
        <ul class="nav-links">
            <li><a href="<?php echo e(site_url('index.php')); ?>" class="<?php echo $page === 'index' ? 'active' : ''; ?>" data-nav-link>首页</a></li>
            <li><a href="<?php echo e(site_url('index.php?p=upload')); ?>" class="<?php echo $page === 'upload' ? 'active' : ''; ?>" data-nav-link>上传文件</a></li>
            <?php if ($user): ?>
            <li><a href="<?php echo e(site_url('index.php?p=user')); ?>" class="<?php echo $page === 'user' ? 'active' : ''; ?>" data-nav-link>我的文件</a></li>
            <?php endif; ?>
        </ul>
        <div class="nav-right">
            <form class="search-box" action="<?php echo e(site_url('index.php?p=search')); ?>" method="get">
                <input type="hidden" name="p" value="search">
                <input type="text" name="q" placeholder="搜索文件" value="<?php echo e(isset($_GET['q']) ? $_GET['q'] : ''); ?>" data-search-input>
                <button type="submit" class="search-btn" aria-label="搜索">搜索</button>
            </form>
            <?php if ($user): ?>
                <div class="user-box">
                    <span class="user-name"><?php echo e($user['username']); ?></span>
                    <a class="btn btn-ghost btn-sm" href="<?php echo e(site_url('index.php?p=logout')); ?>">退出</a>
                </div>
            <?php else: ?>
                <a class="btn btn-ghost btn-sm" href="<?php echo e(site_url('index.php?p=login')); ?>">登录</a>
                <?php if (get_setting('register_open', '1') === '1'): ?>
                <a class="btn btn-primary btn-sm" href="<?php echo e(site_url('index.php?p=register')); ?>">注册</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </nav>
</header>

<main class="main container">
