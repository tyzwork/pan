/* ============================================================
   彩虹外链网盘二开版 - 后台脚本
   ============================================================ */
(function () {
    'use strict';

    var hasGsap = typeof gsap !== 'undefined';

    // ---------- 通用工具 ----------
    function qs(sel, ctx) { return (ctx || document).querySelector(sel); }
    function qsa(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

    // 弹窗
    var mask = document.getElementById('modalMask');
    function openModal(title, bodyHtml, footHtml) {
        if (!mask) return;
        document.getElementById('modalTitle').textContent = title;
        document.getElementById('modalBody').innerHTML = bodyHtml;
        document.getElementById('modalFoot').innerHTML = footHtml || '';
        mask.classList.add('show');
    }
    function closeModal() {
        if (mask) mask.classList.remove('show');
    }
    document.addEventListener('click', function (e) {
        if (e.target.hasAttribute('data-close-modal')) closeModal();
        if (e.target === mask) closeModal();
    });

    // 确认框
    window.adminConfirm = function (title, body, onOk) {
        openModal(title, '<p style="color:#6b7280">' + body + '</p>', '' +
            '<button class="btn" data-close-modal>取消</button>' +
            '<button class="btn btn-danger" id="confirmOk">确认</button>');
        var ok = document.getElementById('confirmOk');
        if (ok) ok.addEventListener('click', function () { closeModal(); if (onOk) onOk(); });
    };

    // 表单请求（带 CSRF）
    window.adminPost = function (url, data, onSuccess) {
        data = data || {};
        data.csrf_token = PAN_ADMIN.csrfToken;
        var body = Object.keys(data).map(function (k) {
            return encodeURIComponent(k) + '=' + encodeURIComponent(data[k] == null ? '' : data[k]);
        }).join('&');
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: body
        }).then(function (r) { return r.json(); }).then(function (res) {
            if (res.code === 0) {
                if (onSuccess) onSuccess(res);
            } else {
                alert(res.msg || '操作失败');
            }
        }).catch(function () { alert('网络错误，请重试'); });
    };

    // 提示条
    window.adminToast = function (msg, type) {
        var box = document.getElementById('toastBox');
        if (!box) {
            box = document.createElement('div');
            box.id = 'toastBox';
            box.style.cssText = 'position:fixed;top:20px;right:20px;z-index:2000;display:flex;flex-direction:column;gap:10px;';
            document.body.appendChild(box);
        }
        var el = document.createElement('div');
        el.textContent = msg;
        var color = type === 'error' ? '#b91c1c' : '#047857';
        el.style.cssText = 'background:#fff;border-left:4px solid ' + color + ';padding:10px 16px;border-radius:8px;box-shadow:0 6px 20px rgba(0,0,0,.12);font-size:13px;';
        box.appendChild(el);
        setTimeout(function () { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; }, 2400);
        setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 2800);
    };

    // ---------- 数字滚动动画 ----------
    qsa('.stat-value[data-count]').forEach(function (el) {
        var target = parseFloat(el.getAttribute('data-count')) || 0;
        var suffix = el.getAttribute('data-suffix') || '';
        if (hasGsap) {
            var obj = { v: 0 };
            gsap.to(obj, {
                v: target, duration: 1.2, ease: 'power2.out',
                onUpdate: function () { el.textContent = Math.round(obj.v).toLocaleString() + suffix; }
            });
        } else {
            el.textContent = target.toLocaleString() + suffix;
        }
    });

    // ---------- 迷你柱状图 ----------
    var chart = document.getElementById('miniChart');
    if (chart) {
        var bars = qsa('.bar', chart);
        var max = 1;
        qsa('.bar', chart).forEach(function (b) { max = Math.max(max, parseFloat(b.getAttribute('data-v') || '0')); });
        bars.forEach(function (b, i) {
            var v = parseFloat(b.getAttribute('data-v') || '0');
            var h = Math.round((v / max) * 160);
            b.style.height = '0px';
            setTimeout(function () {
                b.style.height = Math.max(4, h) + 'px';
                b.querySelector('span').textContent = v;
            }, 150 + i * 90);
        });
    }

    // ---------- 选项卡 ----------
    qsa('[data-tabs]').forEach(function (tabs) {
        tabs.addEventListener('click', function (e) {
            var item = e.target.closest('.tab-item');
            if (!item) return;
            qsa('.tab-item', tabs).forEach(function (x) { x.classList.remove('active'); });
            item.classList.add('active');
            var group = tabs.getAttribute('data-tabs');
            qsa('[data-pane-group="' + group + '"]').forEach(function (p) {
                p.classList.toggle('active', p.id === item.getAttribute('data-target'));
            });
        });
    });

    // ---------- 表格批量选择 ----------
    var allCheck = document.getElementById('checkAll');
    if (allCheck) {
        allCheck.addEventListener('change', function () {
            qsa('.row-check').forEach(function (c) {
                c.checked = allCheck.checked;
                var tr = c.closest('tr');
                if (tr) tr.classList.toggle('selected', c.checked);
            });
        });
    }
    document.addEventListener('change', function (e) {
        if (e.target.classList && e.target.classList.contains('row-check')) {
            var tr = e.target.closest('tr');
            if (tr) tr.classList.toggle('selected', e.target.checked);
        }
    });

    // ---------- 侧边栏（移动端） ----------
    window.toggleSidebar = function () {
        var sb = document.getElementById('adminSidebar');
        if (sb) sb.classList.toggle('open');
    };
})();
