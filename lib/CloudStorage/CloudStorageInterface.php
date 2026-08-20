<?php
/**
 * 云存储接口定义
 * 所有存储驱动必须实现该接口
 */

namespace lib\CloudStorage;

interface CloudStorageInterface
{
    // 上传文件（$file_path 为本地临时文件路径，$save_path 为远端相对路径）
    public function upload($file_path, $save_path);

    // 删除文件
    public function delete($file_path);

    // 获取文件访问 URL
    public function getUrl($file_path);

    // 获取文件下载 URL（带签名/认证）
    public function getDownloadUrl($file_path, $filename = null);

    // 判断文件是否存在
    public function exists($file_path);

    // 获取文件信息
    public function getFileInfo($file_path);
}
