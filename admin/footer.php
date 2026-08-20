<?php
/**
 * 后台公共底部模板
 */
?>
    </div>
</div>

</div>

<div class="admin-modal-mask" id="modalMask">
    <div class="admin-modal" id="modalBox">
        <div class="modal-head">
            <h4 id="modalTitle">提示</h4>
            <button type="button" class="modal-close" data-close-modal>&times;</button>
        </div>
        <div class="modal-body" id="modalBody"></div>
        <div class="modal-foot" id="modalFoot"></div>
    </div>
</div>

<script>
window.PAN_ADMIN = {
    baseUrl: <?php echo json_encode(rtrim(site_url(''), '/')); ?>,
    csrfToken: <?php echo json_encode(csrf_token()); ?>,
    page: <?php echo json_encode($page); ?>
};
</script>
<script src="<?php echo e(site_url('assets/js/gsap.min.js')); ?>"></script>
<script>
if (typeof gsap === 'undefined') {
    document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"><\/script>');
}
</script>
<script src="<?php echo e(site_url('assets/js/admin.js')); ?>"></script>
</body>
</html>
