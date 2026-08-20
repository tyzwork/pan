<?php
/**
 * 通用函数库
 */

// JSON 输出并终止
function json_response($data = null, $code = 0, $msg = 'ok')
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(array('code' => $code, 'msg' => $msg, 'data' => $data), JSON_UNESCAPED_UNICODE);
    exit;
}

// HTML 转义
function e($str)
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// 跳转
function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

// 获取客户端 IP
function get_ip()
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    foreach (array('HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'HTTP_X_REAL_IP') as $key) {
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            $list = explode(',', $_SERVER[$key]);
            $first = trim($list[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                $ip = $first;
                break;
            }
        }
    }
    return $ip;
}

// 读取配置
function get_setting($key, $default = '')
{
    static $cache = null;
    if ($cache === null) {
        $cache = array();
        try {
            $rows = DB::instance()->fetchAll('SELECT `k`,`v` FROM `' . DB_PREFIX . 'settings`');
            foreach ($rows as $row) {
                $cache[$row['k']] = $row['v'];
            }
        } catch (Exception $ex) {
            // 未安装时返回默认值
        }
    }
    return isset($cache[$key]) ? $cache[$key] : $default;
}

// 写入配置
function set_setting($key, $value)
{
    DB::instance()->execute(
        'INSERT INTO `' . DB_PREFIX . 'settings` (`k`,`v`) VALUES (?,?) ON DUPLICATE KEY UPDATE `v` = VALUES(`v`)',
        array($key, $value)
    );
}

// CSRF 令牌
function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

// 校验 CSRF（AJAX 场景直接输出 JSON 失败）
function check_csrf()
{
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if ($token === '' && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
    }
    $sessionToken = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        json_response(null, 403, 'CSRF 校验失败，请刷新页面重试');
    }
}

// 生成随机字符串
function random_str($length = 16)
{
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $out = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, $max)];
    }
    return $out;
}

// 文件大小格式化
function format_size($size)
{
    $size = (float)$size;
    if ($size < 1024) return $size . ' B';
    if ($size < 1048576) return round($size / 1024, 2) . ' KB';
    if ($size < 1073741824) return round($size / 1048576, 2) . ' MB';
    return round($size / 1073741824, 2) . ' GB';
}

// 根据扩展名猜测 MIME
function get_mime($ext)
{
    $map = array(
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif',
        'webp' => 'image/webp', 'bmp' => 'image/bmp', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon',
        'txt' => 'text/plain', 'text' => 'text/plain', 'log' => 'text/plain', 'md' => 'text/plain',
        'markdown' => 'text/plain', 'json' => 'application/json', 'js' => 'text/javascript',
        'mjs' => 'text/javascript', 'css' => 'text/css', 'less' => 'text/css', 'scss' => 'text/css',
        'html' => 'text/html', 'htm' => 'text/html', 'xml' => 'text/xml', 'csv' => 'text/csv',
        'ini' => 'text/plain', 'conf' => 'text/plain', 'config' => 'text/plain', 'yaml' => 'text/yaml',
        'yml' => 'text/yaml', 'sql' => 'text/plain', 'vue' => 'text/plain', 'ts' => 'text/typescript',
        'tsx' => 'text/typescript', 'jsx' => 'text/jsx',
        'zip' => 'application/zip', 'rar' => 'application/x-rar-compressed', '7z' => 'application/x-7z-compressed',
        'tar' => 'application/x-tar', 'gz' => 'application/gzip', 'bz2' => 'application/x-bzip2',
        'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg', 'flac' => 'audio/flac',
        'aac' => 'audio/aac', 'm4a' => 'audio/mp4',
        'mp4' => 'video/mp4', 'mkv' => 'video/x-matroska', 'webm' => 'video/webm', 'avi' => 'video/x-msvideo',
        'mov' => 'video/quicktime', 'wmv' => 'video/x-ms-wmv', 'flv' => 'video/x-flv', 'mpg' => 'video/mpeg',
        'mpeg' => 'video/mpeg',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'apk' => 'application/vnd.android.package-archive', 'ipa' => 'application/octet-stream',
        'exe' => 'application/x-msdownload', 'msi' => 'application/x-msdownload',
        'iso' => 'application/x-iso9660-image', 'deb' => 'application/x-deb', 'rpm' => 'application/x-rpm',
    );
    $ext = strtolower($ext);
    return isset($map[$ext]) ? $map[$ext] : 'application/octet-stream';
}

// 类型判断
function is_image_ext($ext)
{
    return in_array(strtolower($ext), array('jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'ico'));
}

function is_audio_ext($ext)
{
    return in_array(strtolower($ext), array('mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a'));
}

function is_video_ext($ext)
{
    return in_array(strtolower($ext), array('mp4', 'mkv', 'webm', 'avi', 'mov', 'wmv', 'flv', 'mpg', 'mpeg'));
}

function is_text_ext($ext)
{
    return in_array(strtolower($ext), array(
        'txt', 'text', 'log', 'md', 'markdown', 'json', 'js', 'mjs', 'css', 'less', 'scss',
        'html', 'htm', 'xml', 'svg', 'csv', 'ini', 'conf', 'config', 'yaml', 'yml', 'sql',
        'vue', 'ts', 'tsx', 'jsx',
    ));
}

// 可预览类型
function is_preview_ext($ext)
{
    return is_image_ext($ext) || is_audio_ext($ext) || is_video_ext($ext) || is_text_ext($ext) || strtolower($ext) === 'pdf';
}

// 分页参数
function get_page($default = 1, $size = 20)
{
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : $default;
    return array('page' => $page, 'offset' => ($page - 1) * $size, 'size' => $size);
}

// 站点地址
function site_url($path = '')
{
    $base = SITE_URL;
    if ($base === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $base = $https . '://' . $host;
    }
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

// 计算文件 hash
function file_hash($path)
{
    if (is_file($path) && filesize($path) < 10485760) {
        return md5_file($path);
    }
    return '';
}

// 文件类型图标分类（用于前端卡片图标样式）
function icon_class($ext)
{
    $ext = strtolower($ext);
    if (is_image_ext($ext)) return 'image';
    if (is_audio_ext($ext)) return 'audio';
    if (is_video_ext($ext)) return 'video';
    if (is_text_ext($ext)) return 'text';
    if ($ext === 'pdf') return 'pdf';
    if (in_array($ext, array('zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'iso'), true)) return 'zip';
    if (in_array($ext, array('doc', 'docx'), true)) return 'doc';
    if (in_array($ext, array('xls', 'xlsx', 'csv'), true)) return 'xls';
    if (in_array($ext, array('ppt', 'pptx'), true)) return 'ppt';
    if (in_array($ext, array('apk', 'ipa', 'exe', 'msi', 'deb', 'rpm'), true)) return 'app';
    return 'file';
}
