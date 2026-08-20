<?php
/**
 * 后台外观设置
 * 功能：主题切换、站点 Logo、站点名称/副标题、自定义 CSS、前台背景、前后台联动
 */
require_once __DIR__ . '/common.php';
require_admin();

$title = '外观设置';
$page = 'theme';

$themes = array(
    'default'          => '默认蓝白',
    'dark-tech'        => '深色科技',
    'gradient-purple'  => '渐变紫蓝',
    'pure-black'       => '纯黑极简',
    'light-fresh'      => '浅色清新',
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'save_theme') {
        $theme = isset($_POST['theme']) && isset($themes[$_POST['theme']]) ? $_POST['theme'] : 'default';
        $siteName = trim(isset($_POST['site_name']) ? $_POST['site_name'] : '');
        $siteDesc = trim(isset($_POST['site_desc']) ? $_POST['site_desc'] : '');
        $siteLogo = trim(isset($_POST['site_logo']) ? $_POST['site_logo'] : '');
        $customCss = isset($_POST['custom_css']) ? $_POST['custom_css'] : '';
        $bgType = in_array(isset($_POST['bg_type']) ? $_POST['bg_type'] : '', array('color', 'gradient', 'image'), true) ? $_POST['bg_type'] : 'gradient';
        $bgValue = trim(isset($_POST['bg_value']) ? $_POST['bg_value'] : '');
        $themeSync = isset($_POST['theme_sync']) ? '1' : '0';

        set_setting('theme', $theme);
        if ($siteName !== '') set_setting('site_name', $siteName);
        set_setting('site_desc', $siteDesc);
        set_setting('site_logo', $siteLogo);
        set_setting('custom_css', $customCss);
        set_setting('bg_type', $bgType);
        set_setting('bg_value', $bgValue);
        set_setting('theme_sync', $themeSync);
        json_response(null, 0, '外观设置已保存');
    }

    if ($action === 'upload_logo') {
        if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            json_response(null, 1, '上传失败');
        }
        $tmp = $_FILES['logo']['tmp_name'];
        $size = $_FILES['logo']['size'];
        if ($size > 2 * 1024 * 1024) {
            json_response(null, 1, 'Logo 大小不能超过 2MB');
        }
        $info = @getimagesize($tmp);
        if ($info === false) {
            json_response(null, 1, '仅支持上传图片文件');
        }
        $ext = array(
            1 => 'gif', 2 => 'jpg', 3 => 'png', 6 => 'bmp', 18 => 'webp',
        );
        $extName = isset($ext[$info[2]]) ? $ext[$info[2]] : 'png';
        $dir = dirname(__DIR__) . '/uploads/logo';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $name = 'logo_' . date('Ymd_His') . '_' . random_str(4) . '.' . $extName;
        if (!move_uploaded_file($tmp, $dir . '/' . $name)) {
            json_response(null, 1, '保存文件失败');
        }
        $url = site_url('uploads/logo/' . $name);
        set_setting('site_logo', $url);
        json_response(array('url' => $url), 0, 'Logo 上传成功');
    }

    json_response(null, 404, '未知操作');
}

$theme = get_setting('theme', 'default');
$siteName = get_setting('site_name', APP_NAME);
$siteDesc = get_setting('site_desc', '');
$siteLogo = get_setting('site_logo', '');
$customCss = get_setting('custom_css', '');
$bgType = get_setting('bg_type', 'gradient');
$bgValue = get_setting('bg_value', '');
$themeSync = get_setting('theme_sync', '1');

include __DIR__ . '/header.php';
?>
<div class="admin-card">
    <div class="card-head"><h3>主题切换</h3></div>
    <div class="card-body">
        <div class="form-group">
            <label>前台主题</label>
            <select class="form-control" id="themeSelect" style="max-width:320px">
                <?php foreach ($themes as $key => $name): ?>
                <option value="<?php echo e($key); ?>" <?php echo $key === $theme ? 'selected' : ''; ?>><?php echo e($name); ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-help">主题配置文件位于 assets/css/themes/ 目录，可自行新增</div>
        </div>
        <div class="form-group">
            <label>前后台主题联动</label>
            <label class="form-switch">
                <input type="checkbox" id="themeSyncInput" <?php echo $themeSync === '1' ? 'checked' : ''; ?>>
                <span class="slider"></span>
            </label>
            <div class="form-help">开启后，后台页面主题与前台保持一致</div>
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="card-head"><h3>站点信息</h3></div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group">
                <label>站点名称</label>
                <input type="text" class="form-control" id="siteNameInput" value="<?php echo e($siteName); ?>">
            </div>
            <div class="form-group">
                <label>站点副标题</label>
                <input type="text" class="form-control" id="siteDescInput" value="<?php echo e($siteDesc); ?>">
            </div>
        </div>
        <div class="form-group">
            <label>站点 Logo</label>
            <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
                <?php if ($siteLogo !== ''): ?>
                <img src="<?php echo e($siteLogo); ?>" alt="logo" style="height:44px;border-radius:8px;border:1px solid #e5e7eb;padding:4px">
                <?php endif; ?>
                <input type="file" id="logoInput" accept="image/*" class="form-control" style="max-width:280px">
                <button type="button" class="btn" id="uploadLogoBtn">上传 Logo</button>
            </div>
            <div class="form-help">建议尺寸 200x60，支持 jpg/png/gif/webp，大小不超过 2MB</div>
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="card-head"><h3>前台背景</h3></div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group">
                <label>背景类型</label>
                <select class="form-control" id="bgTypeSelect">
                    <option value="gradient" <?php echo $bgType === 'gradient' ? 'selected' : ''; ?>>渐变</option>
                    <option value="color" <?php echo $bgType === 'color' ? 'selected' : ''; ?>>纯色</option>
                    <option value="image" <?php echo $bgType === 'image' ? 'selected' : ''; ?>>图片</option>
                </select>
            </div>
            <div class="form-group" style="flex:2">
                <label>背景值</label>
                <input type="text" class="form-control" id="bgValueInput" value="<?php echo e($bgValue); ?>" placeholder="渐变如 linear-gradient(135deg,#1e1b4b,#7c3aed)；纯色如 #0f172a；图片填 URL">
            </div>
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="card-head"><h3>自定义 CSS</h3></div>
    <div class="card-body">
        <div class="form-group">
            <label>自定义样式（将追加到前台页面）</label>
            <textarea class="form-control" id="customCssInput" style="min-height:160px;font-family:monospace"><?php echo e($customCss); ?></textarea>
        </div>
    </div>
</div>

<div style="display:flex;gap:10px;margin-bottom:24px">
    <button type="button" class="btn btn-primary" id="saveThemeBtn" style="min-width:140px">保存外观设置</button>
    <a class="btn" href="../index.php" target="_blank">预览前台效果</a>
</div>

<script>
function collectTheme() {
    return {
        action: 'save_theme',
        theme: document.getElementById('themeSelect').value,
        site_name: document.getElementById('siteNameInput').value,
        site_desc: document.getElementById('siteDescInput').value,
        custom_css: document.getElementById('customCssInput').value,
        bg_type: document.getElementById('bgTypeSelect').value,
        bg_value: document.getElementById('bgValueInput').value,
        theme_sync: document.getElementById('themeSyncInput').checked ? '1' : '0'
    };
}
document.getElementById('saveThemeBtn').addEventListener('click', function () {
    adminPost('theme.php', collectTheme(), function () { adminToast('外观设置已保存'); });
});
document.getElementById('uploadLogoBtn').addEventListener('click', function () {
    var file = document.getElementById('logoInput').files[0];
    if (!file) { alert('请先选择图片文件'); return; }
    var fd = new FormData();
    fd.append('action', 'upload_logo');
    fd.append('logo', file);
    fd.append('csrf_token', PAN_ADMIN.csrfToken);
    fetch('theme.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.code === 0) { adminToast('Logo 上传成功'); setTimeout(function () { location.reload(); }, 500); }
            else alert(res.msg);
        });
});
</script>
<?php include __DIR__ . '/footer.php'; ?>
