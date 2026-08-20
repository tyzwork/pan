<?php
/**
 * 登录 / 注册模板
 */
$isRegister = $page === 'register';
?>
<div class="auth-wrap">
    <div class="auth-card" data-auth-card>
        <h1 class="auth-title"><?php echo $isRegister ? '注册账号' : '用户登录'; ?></h1>
        <p class="auth-sub"><?php echo e(get_setting('site_name', APP_NAME)); ?></p>

        <form class="auth-form" data-auth-form="<?php echo $isRegister ? 'register' : 'login'; ?>">
            <div class="form-group">
                <label>用户名</label>
                <input type="text" name="username" required maxlength="30" placeholder="请输入用户名">
            </div>
            <?php if ($isRegister): ?>
            <div class="form-group">
                <label>邮箱（选填）</label>
                <input type="email" name="email" placeholder="请输入邮箱">
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" required minlength="6" placeholder="请输入密码">
            </div>
            <?php if ($isRegister): ?>
            <div class="form-group">
                <label>确认密码</label>
                <input type="password" name="password2" required minlength="6" placeholder="请再次输入密码">
            </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary btn-block"><?php echo $isRegister ? '立即注册' : '登 录'; ?></button>
        </form>

        <p class="auth-switch">
            <?php if ($isRegister): ?>
                已有账号？<a href="<?php echo e(site_url('index.php?p=login')); ?>">去登录</a>
            <?php else: ?>
                还没有账号？<a href="<?php echo e(site_url('index.php?p=register')); ?>">去注册</a>
            <?php endif; ?>
        </p>
    </div>
</div>
