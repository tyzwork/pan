<?php
/**
 * 彩虹外链网盘二开版 - 安装程序
 * 浏览器访问 install.php 完成安装，安装后建议删除本文件
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '1');

define('PAN_INSTALLING', true);
require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/lib/functions.php';

$installed = false;
$error = '';

// 检查是否已安装（存在 settings 表且有数据）
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ));
    $stmt = $pdo->query('SHOW TABLES LIKE "' . DB_PREFIX . 'settings"');
    $installed = $stmt->fetch() !== false;
} catch (Exception $ex) {
    $error = $ex->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_install'])) {
    try {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ));
        $p = DB_PREFIX;

        $sqls = array(
            "CREATE TABLE IF NOT EXISTS `{$p}users` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `username` varchar(50) NOT NULL DEFAULT '',
                `password` varchar(64) NOT NULL DEFAULT '',
                `salt` varchar(32) NOT NULL DEFAULT '',
                `email` varchar(100) NOT NULL DEFAULT '',
                `level_id` int(11) NOT NULL DEFAULT 0,
                `file_size_limit` bigint(20) NOT NULL DEFAULT 0,
                `daily_upload_limit` int(11) NOT NULL DEFAULT 0,
                `expire_date` date DEFAULT NULL,
                `status` tinyint(1) NOT NULL DEFAULT 1,
                `is_admin` tinyint(1) NOT NULL DEFAULT 0,
                `created_at` datetime DEFAULT NULL,
                `last_login` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS `{$p}user_levels` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(50) NOT NULL DEFAULT '',
                `max_file_size` bigint(20) NOT NULL DEFAULT 0,
                `daily_upload_limit` int(11) NOT NULL DEFAULT 0,
                `expire_date` date DEFAULT NULL,
                `remark` varchar(255) NOT NULL DEFAULT '',
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS `{$p}files` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `uid` int(11) NOT NULL DEFAULT 0,
                `filename` varchar(255) NOT NULL DEFAULT '',
                `filesize` bigint(20) NOT NULL DEFAULT 0,
                `mime` varchar(100) NOT NULL DEFAULT '',
                `ext` varchar(20) NOT NULL DEFAULT '',
                `storage_type` varchar(20) NOT NULL DEFAULT 'local',
                `storage_path` varchar(500) NOT NULL DEFAULT '',
                `hash` varchar(64) NOT NULL DEFAULT '',
                `dl_count` int(11) NOT NULL DEFAULT 0,
                `dl_limit` int(11) NOT NULL DEFAULT 0,
                `status` tinyint(1) NOT NULL DEFAULT 1,
                `created_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `uid` (`uid`),
                KEY `hash` (`hash`),
                KEY `created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS `{$p}storage_config` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `type` varchar(20) NOT NULL DEFAULT '',
                `config` text,
                `is_default` tinyint(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `type` (`type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS `{$p}settings` (
                `k` varchar(100) NOT NULL DEFAULT '',
                `v` text,
                PRIMARY KEY (`k`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS `{$p}announcements` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `title` varchar(200) NOT NULL DEFAULT '',
                `content` text,
                `position` varchar(50) NOT NULL DEFAULT 'top',
                `status` tinyint(1) NOT NULL DEFAULT 1,
                `sort` int(11) NOT NULL DEFAULT 0,
                `created_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS `{$p}ads` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(200) NOT NULL DEFAULT '',
                `type` varchar(20) NOT NULL DEFAULT 'text',
                `content` text,
                `image` varchar(500) NOT NULL DEFAULT '',
                `link` varchar(500) NOT NULL DEFAULT '',
                `position` varchar(50) NOT NULL DEFAULT 'sidebar',
                `status` tinyint(1) NOT NULL DEFAULT 1,
                `sort` int(11) NOT NULL DEFAULT 0,
                `created_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

            "INSERT INTO `{$p}user_levels` (`name`,`max_file_size`,`daily_upload_limit`,`expire_date`,`remark`) VALUES
                ('普通用户', 104857600, 50, NULL, '默认等级，单文件最大 100MB，每日 50 个'),
                ('高级用户', 1073741824, 0, NULL, '单文件最大 1GB，不限每日数量');",
        );

        foreach ($sqls as $sql) {
            $pdo->exec($sql);
        }

        // 默认设置
        $settings = array(
            'site_name' => '彩虹外链网盘',
            'site_desc' => '简单好用的文件外链网盘',
            'site_logo' => '',
            'theme' => 'default',
            'theme_sync' => '1',
            'custom_css' => '',
            'bg_type' => 'gradient',
            'bg_value' => '',
            'register_open' => '1',
            'upload_open' => '1',
            'global_daily_upload_limit' => '100',
            'default_dl_limit' => '0',
            'max_file_size' => (string)UPLOAD_MAX_SIZE,
            'allow_extensions' => ALLOW_EXTENSIONS,
            'forbidden_extensions' => FORBIDDEN_EXTENSIONS,
            'safety_check' => '0',
            'safety_api_url' => '',
            'safety_api_key' => '',
            'login_fail_limit' => '5',
            'storage_type' => 'local',
        );
        $stmt = $pdo->prepare("INSERT INTO `{$p}settings` (`k`,`v`) VALUES (?,?) ON DUPLICATE KEY UPDATE `v` = VALUES(`v`)");
        foreach ($settings as $k => $v) {
            $stmt->execute(array($k, $v));
        }

        // 本地存储配置
        $root = dirname(__FILE__);
        $localConfig = json_encode(array(
            'base_path' => $root . '/uploads',
            'base_url' => '',
        ), JSON_UNESCAPED_UNICODE);
        $pdo->exec("INSERT INTO `{$p}storage_config` (`type`,`config`,`is_default`) VALUES ('local','" . addslashes($localConfig) . "',1)");

        // 默认管理员 admin/123456
        $salt = bin2hex(random_bytes(8));
        $password = sha1($salt . '123456');
        $stmt = $pdo->prepare("INSERT INTO `{$p}users` (`username`,`password`,`salt`,`email`,`level_id`,`status`,`is_admin`,`created_at`) VALUES ('admin','{$password}','{$salt}','','0',1,1,NOW())");
        $stmt->execute();

        header('Location: install.php?ok=1');
        exit;
    } catch (Exception $ex) {
        $error = $ex->getMessage();
    }
}

$ok = isset($_GET['ok']) ? (int)$_GET['ok'] : 0;
$hasPdo = class_exists('PDO') && in_array('mysql', PDO::getAvailableDrivers(), true);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>安装 - <?php echo e(APP_NAME); ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;background:linear-gradient(135deg,#4f46e5,#8b5cf6);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.card{background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.25);max-width:560px;width:100%;padding:40px}
h1{font-size:22px;color:#111827;margin-bottom:6px}
.sub{color:#6b7280;font-size:14px;margin-bottom:24px}
.info{background:#f3f4f6;border-radius:10px;padding:16px;font-size:13px;color:#374151;line-height:1.9;margin-bottom:24px}
.info b{color:#111827}
.warn{background:#fef3c7;color:#92400e;border-radius:10px;padding:12px 16px;font-size:13px;margin-bottom:24px}
.btn{display:inline-block;background:linear-gradient(135deg,#4f46e5,#8b5cf6);color:#fff;border:0;padding:12px 32px;border-radius:10px;font-size:15px;cursor:pointer;transition:opacity .2s}
.btn:hover{opacity:.85}
.err{background:#fee2e2;color:#b91c1c;border-radius:10px;padding:12px 16px;font-size:13px;margin-bottom:16px;word-break:break-all}
.done{text-align:center}
.done h2{color:#059669;margin-bottom:12px}
.done a{color:#4f46e5}
</style>
</head>
<body>
<div class="card">
<?php if ($ok): ?>
    <div class="done">
        <h2>安装成功</h2>
        <p class="sub">默认管理员账号：admin　密码：123456<br>登录后请及时修改密码，并删除 install.php</p>
        <p style="margin-top:16px"><a class="btn" href="admin/login.php">进入后台</a> <a class="btn" style="background:#6b7280" href="index.php">访问前台</a></p>
    </div>
<?php elseif ($installed): ?>
    <h1>系统已安装</h1>
    <p class="sub">数据库已存在数据，如需重新安装请先清空数据库。</p>
    <p><a href="index.php">返回首页</a> | <a href="admin/login.php">进入后台</a></p>
<?php else: ?>
    <h1>安装<?php echo e(APP_NAME); ?></h1>
    <p class="sub">版本 <?php echo e(APP_VERSION); ?>，MIT 开源协议</p>
    <?php if ($error !== ''): ?>
        <div class="err">数据库连接或初始化失败：<?php echo e($error); ?></div>
    <?php endif; ?>
    <?php if (!$hasPdo): ?>
        <div class="err">PHP 未启用 PDO MySQL 扩展，请先安装 pdo_mysql</div>
    <?php else: ?>
        <div class="info">
            数据库地址：<b><?php echo e(DB_HOST); ?>:<?php echo e(DB_PORT); ?></b><br>
            数据库名：<b><?php echo e(DB_NAME); ?></b>（表前缀：<?php echo e(DB_PREFIX); ?>）<br>
            数据库账号：<b><?php echo e(DB_USER); ?></b><br>
            请确认 config.php 中数据库配置正确，且数据库已创建。
        </div>
        <?php if ($error !== ''): ?>
            <div class="warn">提示：若提示数据库不存在，请先在 MySQL 中执行 CREATE DATABASE `<?php echo e(DB_NAME); ?>` DEFAULT CHARSET utf8mb4;</div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="do_install" value="1">
            <button type="submit" class="btn">开始安装</button>
        </form>
    <?php endif; ?>
<?php endif; ?>
</div>
</body>
</html>
