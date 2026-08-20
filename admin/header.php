<?php
/**
 * 后台公共头部模板
 * 变量: $page (当前页面标识), $title (页面标题)
 */
$adminUser = admin_info();
$siteName = get_setting('site_name', APP_NAME);
$curTheme = get_setting('theme', 'default');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo e($title); ?> - <?php echo e($siteName); ?> 后台</title>
<link rel="stylesheet" href="<?php echo e(site_url('assets/css/admin.css')); ?>">
<style>
body.admin-body.themed { --admin-sidebar: #151b2e; --admin-accent: #4f46e5; }
</style>
</head>
<body class="admin-body">
<div class="admin-layout">

<aside class="admin-sidebar" id="adminSidebar">
    <div class="side-brand">
        <div class="brand-mark">彩</div>
        <div>
            <div class="brand-name"><?php echo e($siteName); ?></div>
            <div class="brand-sub">管理后台</div>
        </div>
    </div>
    <nav class="side-nav">
        <div class="nav-group-title">概览</div>
        <a href="index.php" class="<?php echo $page === 'dashboard' ? 'active' : ''; ?>"><span class="nav-ico">图</span>仪表盘</a>
        <a href="file.php" class="<?php echo $page === 'file' ? 'active' : ''; ?>"><span class="nav-ico">档</span>文件管理</a>
        <a href="user.php" class="<?php echo $page === 'user' ? 'active' : ''; ?>"><span class="nav-ico">人</span>用户管理</a>

        <div class="nav-group-title">存储</div>
        <a href="storage.php" class="<?php echo $page === 'storage' ? 'active' : ''; ?>"><span class="nav-ico">存</span>存储设置</a>

        <div class="nav-group-title">外观与内容</div>
        <a href="theme.php" class="<?php echo $page === 'theme' ? 'active' : ''; ?>"><span class="nav-ico">饰</span>外观设置</a>
        <a href="announcement.php" class="<?php echo $page === 'announcement' ? 'active' : ''; ?>"><span class="nav-ico">告</span>广告公告位</a>

        <div class="nav-group-title">系统</div>
        <a href="setting.php" class="<?php echo $page === 'setting' ? 'active' : ''; ?>"><span class="nav-ico">设</span>系统设置</a>
        <a href="logout.php"><span class="nav-ico">退</span>退出登录</a>
    </nav>
    <div class="side-foot">
        <div><?php echo e(APP_NAME); ?> v<?php echo e(APP_VERSION); ?></div>
        <div style="margin-top:4px">MIT License</div>
    </div>
</aside>

<div class="admin-main">
    <header class="admin-topbar">
        <div class="top-title"><?php echo e($title); ?></div>
        <div class="top-actions">
            <span class="top-user"><?php echo $adminUser ? e($adminUser['username']) : ''; ?></span>
            <a href="../index.php" target="_blank">查看前台</a>
        </div>
    </header>
    <div class="admin-content">
