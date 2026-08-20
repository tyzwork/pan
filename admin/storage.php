<?php
/**
 * 后台存储设置
 * 支持：本地/S3/WebDAV/阿里云 OSS/腾讯云 COS/华为云 OBS/又拍云/七牛云
 */
require_once __DIR__ . '/common.php';
require_admin();

$title = '存储设置';
$page = 'storage';

use lib\CloudStorage\StorageManager;

$types = StorageManager::types();
$current = get_setting('storage_type', 'local');
$activeType = isset($_GET['type']) && isset($types[$_GET['type']]) ? $_GET['type'] : $current;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'save_config') {
        $type = isset($_POST['type']) ? $_POST['type'] : '';
        if (!isset($types[$type])) {
            json_response(null, 1, '存储类型无效');
        }
        // 各存储配置字段定义
        $fields = array(
            'local'   => array('base_path', 'base_url'),
            's3'      => array('access_key', 'secret_key', 'endpoint', 'region', 'bucket', 'prefix', 'style', 'upload_mode'),
            'webdav'  => array('server', 'username', 'password', 'root', 'upload_mode'),
            'aliyun'  => array('access_key', 'secret_key', 'endpoint', 'bucket', 'prefix'),
            'tencent' => array('access_key', 'secret_key', 'endpoint', 'bucket', 'region', 'prefix'),
            'huawei'  => array('access_key', 'secret_key', 'endpoint', 'bucket', 'prefix'),
            'upyun'   => array('operator', 'password', 'bucket', 'domain', 'endpoint', 'prefix'),
            'qiniu'   => array('access_key', 'secret_key', 'bucket', 'domain', 'region', 'prefix'),
        );
        $config = array();
        $defs = $fields[$type];
        foreach ($defs as $f) {
            $config[$f] = isset($_POST[$f]) ? trim($_POST[$f]) : '';
        }
        StorageManager::saveConfig($type, $config);
        json_response(null, 0, '配置已保存');
    }

    if ($action === 'set_default') {
        $type = isset($_POST['type']) ? $_POST['type'] : '';
        if (!StorageManager::setDefault($type)) {
            json_response(null, 1, '存储类型无效');
        }
        json_response(null, 0, '已设为默认存储');
    }

    if ($action === 'test_storage') {
        $type = isset($_POST['type']) ? $_POST['type'] : '';
        if (!isset($types[$type])) {
            json_response(null, 1, '存储类型无效');
        }
        if (!StorageManager::isConfigured($type)) {
            json_response(null, 1, '该存储尚未保存配置，请先填写并保存');
        }
        try {
            $storage = StorageManager::getInstance($type);
            $testPath = '.pan_test_' . random_str(6) . '.tmp';
            if (method_exists($storage, 'exists')) {
                $storage->exists($testPath);
            }
            json_response(null, 0, '连接成功：配置有效');
        } catch (Exception $ex) {
            json_response(null, 1, '连接失败：' . $ex->getMessage());
        }
    }

    json_response(null, 404, '未知操作');
}

$configs = array();
foreach ($types as $t => $name) {
    $configs[$t] = StorageManager::getConfig($t);
}

include __DIR__ . '/header.php';
?>
<div class="tabs" data-tabs="storageTabs">
    <?php foreach ($types as $t => $name): ?>
    <div class="tab-item <?php echo $t === $activeType ? 'active' : ''; ?>" data-target="pane-<?php echo e($t); ?>"><?php echo e($name); ?></div>
    <?php endforeach; ?>
</div>

<?php foreach ($types as $t => $name): $cfg = $configs[$t]; $isDefault = $t === $current; ?>
<div id="pane-<?php echo e($t); ?>" data-pane-group="storageTabs" class="tab-pane <?php echo $t === $activeType ? 'active' : ''; ?>">
    <div class="admin-card">
        <div class="card-head">
            <h3><?php echo e($name); ?> 配置</h3>
            <?php if ($isDefault): ?>
                <span class="badge badge-success">当前默认</span>
            <?php else: ?>
                <button type="button" class="btn btn-sm btn-gradient" data-set-default="<?php echo e($t); ?>">设为默认存储</button>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form class="storage-form" data-type="<?php echo e($t); ?>">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="type" value="<?php echo e($t); ?>">

                <?php if ($t === 'local'): ?>
                <div class="form-group">
                    <label>本地存储目录</label>
                    <input type="text" class="form-control" name="base_path" value="<?php echo e(isset($cfg['base_path']) ? $cfg['base_path'] : ''); ?>" placeholder="例如 /var/www/html/pan/uploads">
                    <div class="form-help">文件保存的本地绝对路径，需保证 PHP 进程有写入权限</div>
                </div>
                <div class="form-group">
                    <label>访问基础 URL</label>
                    <input type="text" class="form-control" name="base_url" value="<?php echo e(isset($cfg['base_url']) ? $cfg['base_url'] : ''); ?>" placeholder="留空自动使用站点地址/uploads">
                </div>

                <?php elseif ($t === 's3'): ?>
                <div class="form-row">
                    <div class="form-group">
                        <label>Access Key</label>
                        <input type="text" class="form-control" name="access_key" value="<?php echo e(isset($cfg['access_key']) ? $cfg['access_key'] : ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Secret Key</label>
                        <input type="password" class="form-control" name="secret_key" value="<?php echo e(isset($cfg['secret_key']) ? $cfg['secret_key'] : ''); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Endpoint（S3 API 地址）</label>
                        <input type="text" class="form-control" name="endpoint" value="<?php echo e(isset($cfg['endpoint']) ? $cfg['endpoint'] : ''); ?>" placeholder="https://s3.region.amazonaws.com">
                    </div>
                    <div class="form-group">
                        <label>Region</label>
                        <input type="text" class="form-control" name="region" value="<?php echo e(isset($cfg['region']) ? $cfg['region'] : 'us-east-1'); ?>" placeholder="us-east-1">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Bucket（存储桶）</label>
                        <input type="text" class="form-control" name="bucket" value="<?php echo e(isset($cfg['bucket']) ? $cfg['bucket'] : ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>对象前缀</label>
                        <input type="text" class="form-control" name="prefix" value="<?php echo e(isset($cfg['prefix']) ? $cfg['prefix'] : 'file/'); ?>" placeholder="file/">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>地址风格</label>
                        <select class="form-control" name="style">
                            <option value="path" <?php echo (isset($cfg['style']) && $cfg['style'] === 'path') ? 'selected' : ''; ?>>路径风格</option>
                            <option value="virtual-host" <?php echo (isset($cfg['style']) && $cfg['style'] === 'virtual-host') ? 'selected' : ''; ?>>虚拟主机风格</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>上传方式</label>
                        <select class="form-control" name="upload_mode">
                            <option value="relay" <?php echo (isset($cfg['upload_mode']) && $cfg['upload_mode'] === 'relay') || !isset($cfg['upload_mode']) ? 'selected' : ''; ?>>网站中转</option>
                            <option value="direct" <?php echo isset($cfg['upload_mode']) && $cfg['upload_mode'] === 'direct' ? 'selected' : ''; ?>>浏览器直传</option>
                        </select>
                    </div>
                </div>
                <div class="form-help" style="margin-bottom:8px">支持 AWS S3、MinIO、Cloudflare R2、Backblaze B2 等 S3 兼容对象存储</div>

                <?php elseif ($t === 'webdav'): ?>
                <div class="form-group">
                    <label>WebDAV 服务器地址</label>
                    <input type="text" class="form-control" name="server" value="<?php echo e(isset($cfg['server']) ? $cfg['server'] : ''); ?>" placeholder="https://dav.example.com/remote.php/dav/files/user/">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>用户名</label>
                        <input type="text" class="form-control" name="username" value="<?php echo e(isset($cfg['username']) ? $cfg['username'] : ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>密码</label>
                        <input type="password" class="form-control" name="password" value="<?php echo e(isset($cfg['password']) ? $cfg['password'] : ''); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>根目录前缀</label>
                        <input type="text" class="form-control" name="root" value="<?php echo e(isset($cfg['root']) ? $cfg['root'] : 'pan'); ?>" placeholder="pan">
                    </div>
                    <div class="form-group">
                        <label>上传方式</label>
                        <select class="form-control" name="upload_mode">
                            <option value="relay" <?php echo (isset($cfg['upload_mode']) && $cfg['upload_mode'] === 'relay') || !isset($cfg['upload_mode']) ? 'selected' : ''; ?>>网站中转</option>
                            <option value="direct" <?php echo isset($cfg['upload_mode']) && $cfg['upload_mode'] === 'direct' ? 'selected' : ''; ?>>直传</option>
                        </select>
                    </div>
                </div>

                <?php elseif ($t === 'aliyun' || $t === 'tencent' || $t === 'huawei'): ?>
                <div class="form-row">
                    <div class="form-group">
                        <label>Access Key</label>
                        <input type="text" class="form-control" name="access_key" value="<?php echo e(isset($cfg['access_key']) ? $cfg['access_key'] : ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Secret Key</label>
                        <input type="password" class="form-control" name="secret_key" value="<?php echo e(isset($cfg['secret_key']) ? $cfg['secret_key'] : ''); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Endpoint</label>
                        <input type="text" class="form-control" name="endpoint" value="<?php echo e(isset($cfg['endpoint']) ? $cfg['endpoint'] : ''); ?>">
                    </div>
                    <?php if ($t === 'tencent'): ?>
                    <div class="form-group">
                        <label>Region</label>
                        <input type="text" class="form-control" name="region" value="<?php echo e(isset($cfg['region']) ? $cfg['region'] : 'ap-guangzhou'); ?>">
                    </div>
                    <?php endif; ?>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Bucket</label>
                        <input type="text" class="form-control" name="bucket" value="<?php echo e(isset($cfg['bucket']) ? $cfg['bucket'] : ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>对象前缀</label>
                        <input type="text" class="form-control" name="prefix" value="<?php echo e(isset($cfg['prefix']) ? $cfg['prefix'] : 'file/'); ?>">
                    </div>
                </div>

                <?php elseif ($t === 'upyun'): ?>
                <div class="form-row">
                    <div class="form-group">
                        <label>操作员</label>
                        <input type="text" class="form-control" name="operator" value="<?php echo e(isset($cfg['operator']) ? $cfg['operator'] : ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>操作员密码</label>
                        <input type="password" class="form-control" name="password" value="<?php echo e(isset($cfg['password']) ? $cfg['password'] : ''); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Bucket 名称</label>
                        <input type="text" class="form-control" name="bucket" value="<?php echo e(isset($cfg['bucket']) ? $cfg['bucket'] : ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>访问域名</label>
                        <input type="text" class="form-control" name="domain" value="<?php echo e(isset($cfg['domain']) ? $cfg['domain'] : ''); ?>" placeholder="https://xxx.b0.upaiyun.com">
                    </div>
                </div>
                <div class="form-group">
                    <label>API Endpoint</label>
                    <input type="text" class="form-control" name="endpoint" value="<?php echo e(isset($cfg['endpoint']) ? $cfg['endpoint'] : 'https://v0.api.upyun.com'); ?>">
                </div>

                <?php elseif ($t === 'qiniu'): ?>
                <div class="form-row">
                    <div class="form-group">
                        <label>Access Key</label>
                        <input type="text" class="form-control" name="access_key" value="<?php echo e(isset($cfg['access_key']) ? $cfg['access_key'] : ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Secret Key</label>
                        <input type="password" class="form-control" name="secret_key" value="<?php echo e(isset($cfg['secret_key']) ? $cfg['secret_key'] : ''); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Bucket</label>
                        <input type="text" class="form-control" name="bucket" value="<?php echo e(isset($cfg['bucket']) ? $cfg['bucket'] : ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>访问域名</label>
                        <input type="text" class="form-control" name="domain" value="<?php echo e(isset($cfg['domain']) ? $cfg['domain'] : ''); ?>" placeholder="https://cdn.example.com">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>区域（z0/z1/z2/na0/as0）</label>
                        <input type="text" class="form-control" name="region" value="<?php echo e(isset($cfg['region']) ? $cfg['region'] : 'z0'); ?>">
                    </div>
                    <div class="form-group">
                        <label>对象前缀</label>
                        <input type="text" class="form-control" name="prefix" value="<?php echo e(isset($cfg['prefix']) ? $cfg['prefix'] : 'file/'); ?>">
                    </div>
                </div>
                <?php endif; ?>

                <div style="margin-top:8px">
                    <button type="submit" class="btn btn-primary">保存配置</button>
                    <button type="button" class="btn" data-test-storage="<?php echo e($t); ?>">测试连接</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
document.querySelectorAll('.storage-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var type = form.getAttribute('data-type');
        var data = { action: 'save_config' };
        Array.prototype.slice.call(form.elements).forEach(function (el) {
            if (el.name) data[el.name] = el.value;
        });
        adminPost('storage.php', data, function () { adminToast('配置已保存'); });
    });
});
document.addEventListener('click', function (e) {
    var sd = e.target.closest('[data-set-default]');
    if (sd) {
        adminPost('storage.php', { action: 'set_default', type: sd.getAttribute('data-set-default') }, function () {
            adminToast('已设为默认存储');
            setTimeout(function () { location.reload(); }, 400);
        });
    }
    var ts = e.target.closest('[data-test-storage]');
    if (ts) {
        var type = ts.getAttribute('data-test-storage');
        ts.textContent = '测试中...';
        adminPost('storage.php?action=test', { action: 'test_storage', type: type }, function (res) {
            ts.textContent = '测试连接';
            adminToast(res.msg || '连接成功', res.code === 0 ? 'success' : 'error');
        });
    }
});
</script>
<?php include __DIR__ . '/footer.php'; ?>
