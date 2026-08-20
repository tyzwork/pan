<?php
/**
 * 彩虹外链网盘二开版 - 全局配置文件
 * 版本: 2.0.0
 * 协议: MIT
 */

define('APP_NAME', '彩虹外链网盘');
define('APP_VERSION', '2.0.0');
define('APP_DEBUG', false);

// 数据库配置（支持环境变量覆盖，便于容器化部署）
define('DB_HOST', getenv('PAN_DB_HOST') !== false ? getenv('PAN_DB_HOST') : '127.0.0.1');
define('DB_PORT', getenv('PAN_DB_PORT') !== false ? getenv('PAN_DB_PORT') : '3306');
define('DB_USER', getenv('PAN_DB_USER') !== false ? getenv('PAN_DB_USER') : 'root');
define('DB_PASS', getenv('PAN_DB_PASS') !== false ? getenv('PAN_DB_PASS') : '');
define('DB_NAME', getenv('PAN_DB_NAME') !== false ? getenv('PAN_DB_NAME') : 'pan');
define('DB_PREFIX', getenv('PAN_DB_PREFIX') !== false ? getenv('PAN_DB_PREFIX') : 'pan_');

// 站点地址（部署后修改为实际域名，用于生成外链；留空自动探测）
define('SITE_URL', getenv('PAN_SITE_URL') !== false ? rtrim(getenv('PAN_SITE_URL'), '/') : '');

// 上传限制
define('UPLOAD_MAX_SIZE', 500 * 1024 * 1024);          // 网站中转单文件上限 500MB
define('DEFAULT_CHUNK_SIZE', 2 * 1024 * 1024);         // 分块大小 2MB

// 允许上传的扩展名白名单（空表示全部允许，仅受黑名单约束）
define('ALLOW_EXTENSIONS', 'jpg,jpeg,png,gif,webp,bmp,svg,ico,txt,text,log,md,markdown,json,js,mjs,css,less,scss,html,htm,xml,csv,ini,conf,config,yaml,yml,sql,vue,ts,tsx,jsx,zip,rar,7z,tar,gz,bz2,iso,mp3,wav,ogg,flac,aac,m4a,mp4,mkv,webm,avi,mov,wmv,flv,mpg,mpeg,pdf,doc,docx,xls,xlsx,ppt,pptx,apk,ipa,exe,msi,deb,rpm');

// 禁止上传的扩展名黑名单（始终生效）
define('FORBIDDEN_EXTENSIONS', 'php,php3,php4,php5,phtml,pht,phar,asp,aspx,jsp,jspx,cgi,pl,py,sh,htaccess,conf,sql,exe,msi,bat,cmd,com,scr,vbs,js');

date_default_timezone_set(getenv('PAN_TZ') !== false ? getenv('PAN_TZ') : 'Asia/Shanghai');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 自动加载 lib\ 命名空间（存储驱动等），映射到 lib/ 目录
spl_autoload_register(function ($class) {
    $prefix = 'lib\\';
    if (strncmp($class, $prefix, strlen($prefix)) === 0) {
        $relative = substr($class, strlen($prefix));
        $file = __DIR__ . '/lib/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});
