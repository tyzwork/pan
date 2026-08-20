<?php
/**
 * 前台公共底部模板
 */
$footerAds = DB::instance()->fetchAll(
    'SELECT * FROM `' . DB_PREFIX . 'ads` WHERE `position` = ? AND `status` = 1 ORDER BY `sort` ASC, `id` DESC',
    array('sidebar')
);
?>
</main>

<?php if (!empty($footerAds)): ?>
<div class="ads-section container">
    <?php foreach ($footerAds as $ad): ?>
    <div class="ad-card" data-ad>
        <?php if ($ad['type'] === 'image' && $ad['image'] !== ''): ?>
            <?php if ($ad['link'] !== ''): ?>
                <a href="<?php echo e($ad['link']); ?>" target="_blank" rel="noopener"><img src="<?php echo e($ad['image']); ?>" alt="<?php echo e($ad['name']); ?>"></a>
            <?php else: ?>
                <img src="<?php echo e($ad['image']); ?>" alt="<?php echo e($ad['name']); ?>">
            <?php endif; ?>
        <?php elseif ($ad['type'] === 'code'): ?>
            <?php echo $ad['content']; ?>
        <?php else: ?>
            <p class="ad-text"><?php echo nl2br(e($ad['content'])); ?></p>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<footer class="site-footer">
    <div class="container">
        <p>&copy; <?php echo date('Y'); ?> <?php echo e(get_setting('site_name', APP_NAME)); ?> - <?php echo e(APP_VERSION); ?></p>
        <p class="footer-sub"><?php echo e(get_setting('site_desc', '')); ?></p>
    </div>
</footer>

<div class="toast" id="toast" role="status"></div>

<script src="<?php echo e(site_url('assets/js/gsap.min.js')); ?>"></script>
<script>
if (typeof gsap === 'undefined') {
    document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"><\/script>');
}
</script>
<script src="<?php echo e(site_url('assets/js/ScrollTrigger.min.js')); ?>"></script>
<script>
if (typeof ScrollTrigger === 'undefined') {
    document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"><\/script>');
}
</script>
<script src="<?php echo e(site_url('assets/js/animations.js')); ?>"></script>
<script src="<?php echo e(site_url('assets/js/main.js')); ?>"></script>
<script>
window.PAN = {
    baseUrl: <?php echo json_encode(rtrim(site_url(''), '/')); ?>,
    csrfToken: <?php echo json_encode(csrf_token()); ?>,
    page: <?php echo json_encode($page); ?>
};
</script>
</body>
</html>
