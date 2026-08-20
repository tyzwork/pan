<?php
/**
 * 存储管理器（工厂）
 * 根据存储类型创建对应驱动实例，并负责配置的读写
 */

namespace lib\CloudStorage;

class StorageManager
{
    private static $typeMap = array(
        'local'   => 'LocalStorage',
        's3'      => 'S3Storage',
        'webdav'  => 'WebDAVStorage',
        'aliyun'  => 'AliyunOSS',
        'tencent' => 'TencentCOS',
        'huawei'  => 'HuaweiOBS',
        'upyun'   => 'Upyun',
        'qiniu'   => 'Qiniu',
    );

    // 支持的存储类型及中文名
    public static function types()
    {
        return array(
            'local'   => '本地服务器存储',
            's3'      => '通用 S3 兼容存储',
            'webdav'  => 'WebDAV 存储',
            'aliyun'  => '阿里云 OSS',
            'tencent' => '腾讯云 COS',
            'huawei'  => '华为云 OBS',
            'upyun'   => '又拍云',
            'qiniu'   => '七牛云',
        );
    }

    // 获取驱动实例
    public static function getInstance($type = null)
    {
        if ($type === null || $type === '') {
            $type = get_setting('storage_type', 'local');
        }
        $class = isset(self::$typeMap[$type]) ? self::$typeMap[$type] : 'LocalStorage';
        $fullClass = '\\lib\\CloudStorage\\' . $class;
        if (!class_exists($fullClass)) {
            throw new \Exception('存储驱动不存在: ' . $type);
        }
        return new $fullClass(self::getConfig($type));
    }

    // 获取存储配置
    public static function getConfig($type)
    {
        $row = \DB::instance()->fetch(
            'SELECT `config` FROM `' . \DB_PREFIX . 'storage_config` WHERE `type` = ?',
            array($type)
        );
        if (!$row) {
            return array();
        }
        $config = json_decode($row['config'], true);
        return is_array($config) ? $config : array();
    }

    // 保存存储配置
    public static function saveConfig($type, $config)
    {
        $json = json_encode($config, JSON_UNESCAPED_UNICODE);
        \DB::instance()->execute(
            'INSERT INTO `' . \DB_PREFIX . 'storage_config` (`type`,`config`,`is_default`) VALUES (?,?,0) ON DUPLICATE KEY UPDATE `config` = VALUES(`config`)',
            array($type, $json)
        );
    }

    // 设置默认存储
    public static function setDefault($type)
    {
        if (!isset(self::$typeMap[$type])) {
            return false;
        }
        \DB::instance()->execute('UPDATE `' . \DB_PREFIX . 'storage_config` SET `is_default` = 0');
        \DB::instance()->execute(
            'UPDATE `' . \DB_PREFIX . 'storage_config` SET `is_default` = 1 WHERE `type` = ?',
            array($type)
        );
        set_setting('storage_type', $type);
        return true;
    }

    // 存储是否已配置
    public static function isConfigured($type)
    {
        $config = self::getConfig($type);
        return count($config) > 0;
    }
}
