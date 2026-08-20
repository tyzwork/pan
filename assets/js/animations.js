/**
 * GSAP 动画脚本
 * 依赖: gsap.min.js + ScrollTrigger.min.js
 */
(function () {
    'use strict';

    function hasGsap() {
        return typeof gsap !== 'undefined';
    }

    window.PanAnims = {
        // 背景粒子动画
        initCanvas: function () {
            var canvas = document.querySelector('.bg-canvas');
            if (!canvas || !hasGsap()) return;
            // 由 CSS 负责背景渐变，JS 只做轻量浮动光斑
            var count = 12;
            for (var i = 0; i < count; i++) {
                var dot = document.createElement('div');
                dot.className = 'bg-dot';
                dot.style.width = (10 + Math.random() * 40) + 'px';
                dot.style.height = dot.style.width;
                dot.style.left = (Math.random() * 100) + '%';
                dot.style.top = (Math.random() * 100) + '%';
                canvas.appendChild(dot);
                gsap.to(dot, {
                    x: (Math.random() - 0.5) * 160,
                    y: (Math.random() - 0.5) * 160,
                    opacity: 0.05 + Math.random() * 0.15,
                    duration: 6 + Math.random() * 6,
                    repeat: -1,
                    yoyo: true,
                    ease: 'sine.inOut'
                });
            }
        },

        // 导航栏滚动毛玻璃
        initNavbar: function () {
            var header = document.getElementById('siteHeader');
            if (!header) return;
            var onScroll = function () {
                if (window.scrollY > 20) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
            };
            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();
        },

        // Logo 悬停
        initLogo: function () {
            var logo = document.querySelector('.logo');
            if (!logo || !hasGsap()) return;
            logo.addEventListener('mouseenter', function () {
                gsap.to(logo.querySelector('.logo-mark, img'), { scale: 1.15, rotate: 8, duration: 0.3 });
            });
            logo.addEventListener('mouseleave', function () {
                gsap.to(logo.querySelector('.logo-mark, img'), { scale: 1, rotate: 0, duration: 0.3 });
            });
        },

        // 导航项下划线滑入
        initNavLinks: function () {
            var links = document.querySelectorAll('[data-nav-link]');
            if (!links.length || !hasGsap()) return;
            links.forEach(function (link) {
                var line = document.createElement('span');
                line.className = 'nav-underline';
                link.appendChild(line);
                link.addEventListener('mouseenter', function () {
                    gsap.to(line, { scaleX: 1, duration: 0.25, ease: 'power2.out' });
                });
                link.addEventListener('mouseleave', function () {
                    gsap.to(line, { scaleX: 0, duration: 0.25, ease: 'power2.in' });
                });
                if (link.classList.contains('active')) {
                    line.style.transform = 'scaleX(1)';
                }
            });
        },

        // 页面标题入场
        initPageHead: function () {
            var head = document.querySelector('[data-page-head]');
            if (!head || !hasGsap()) return;
            gsap.from(head.children, { y: 24, opacity: 0, duration: 0.5, stagger: 0.1, ease: 'power2.out' });
        },

        // 文件列表 stagger 入场
        initFileGrid: function () {
            var cards = document.querySelectorAll('[data-file-card]');
            if (!cards.length || !hasGsap()) return;
            gsap.from(cards, {
                y: 30,
                opacity: 0,
                duration: 0.5,
                stagger: 0.08,
                ease: 'power2.out',
                onComplete: function () {
                    window.PanAnims.initCardHover();
                }
            });
        },

        // 卡片悬停放大 + 阴影
        initCardHover: function () {
            var cards = document.querySelectorAll('[data-file-card]');
            if (!hasGsap()) return;
            cards.forEach(function (card) {
                card.addEventListener('mouseenter', function () {
                    gsap.to(card, { scale: 1.02, y: -4, boxShadow: '0 14px 34px rgba(0,0,0,.22)', duration: 0.3 });
                });
                card.addEventListener('mouseleave', function () {
                    gsap.to(card, { scale: 1, y: 0, boxShadow: '0 2px 12px rgba(0,0,0,.08)', duration: 0.3 });
                });
            });
        },

        // 搜索框聚焦
        initSearchFocus: function () {
            var input = document.querySelector('[data-search-input]');
            if (!input || !hasGsap()) return;
            input.addEventListener('focus', function () {
                input.closest('.search-box').classList.add('focus');
                if (hasGsap()) {
                    gsap.to(input.closest('.search-box'), { scale: 1.04, duration: 0.25, ease: 'power2.out' });
                }
            });
            input.addEventListener('blur', function () {
                input.closest('.search-box').classList.remove('focus');
                if (hasGsap()) {
                    gsap.to(input.closest('.search-box'), { scale: 1, duration: 0.25, ease: 'power2.out' });
                }
            });
        },

        // 分页切换过渡
        initPager: function () {
            var pager = document.querySelector('[data-pager]');
            if (!pager || !hasGsap()) return;
            pager.addEventListener('click', function (ev) {
                var btn = ev.target.closest('[data-page]');
                if (!btn || btn.classList.contains('disabled')) return;
                var page = btn.getAttribute('data-page');
                ev.preventDefault();
                if (hasGsap()) {
                    gsap.to('.file-grid', {
                        opacity: 0,
                        y: 12,
                        duration: 0.2,
                        onComplete: function () {
                            window.location.href = btn.getAttribute('href');
                        }
                    });
                } else {
                    window.location.href = btn.getAttribute('href');
                }
                void page;
            });
        },

        // Toast 动画
        showToast: function (msg, type) {
            var toast = document.getElementById('toast');
            if (!toast) return;
            toast.textContent = msg;
            toast.className = 'toast show ' + (type || 'success');
            if (hasGsap()) {
                gsap.fromTo(toast, { y: 20, opacity: 0 }, { y: 0, opacity: 1, duration: 0.3, ease: 'power2.out' });
            }
            clearTimeout(toast._timer);
            toast._timer = setTimeout(function () {
                if (hasGsap()) {
                    gsap.to(toast, { y: 20, opacity: 0, duration: 0.3, onComplete: function () { toast.classList.remove('show'); } });
                } else {
                    toast.classList.remove('show');
                }
            }, 2400);
        },

        // Tab 切换动画
        initTabs: function () {
            var tabs = document.querySelectorAll('[data-tabs]');
            tabs.forEach(function (wrap) {
                var buttons = wrap.querySelectorAll('.tab');
                var panels = wrap.parentElement.querySelectorAll('[data-panel]');
                buttons.forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        buttons.forEach(function (b) { b.classList.remove('active'); });
                        btn.classList.add('active');
                        var key = btn.getAttribute('data-tab');
                        panels.forEach(function (p) {
                            var show = p.getAttribute('data-panel') === key;
                            p.classList.toggle('active', show);
                            if (show && hasGsap()) {
                                gsap.fromTo(p, { opacity: 0, y: 8 }, { opacity: 1, y: 0, duration: 0.25 });
                            }
                        });
                    });
                });
            });
        },

        // 图片查看器开合
        initLightbox: function () {
            var box = document.getElementById('lightbox');
            if (!box) return;
            var img = box.querySelector('img');
            document.querySelectorAll('[data-lightbox]').forEach(function (el) {
                el.addEventListener('click', function () {
                    img.src = el.getAttribute('src');
                    box.hidden = false;
                    if (hasGsap()) {
                        gsap.fromTo(box, { opacity: 0 }, { opacity: 1, duration: 0.3 });
                        gsap.fromTo(img, { scale: 0.9, opacity: 0 }, { scale: 1, opacity: 1, duration: 0.3, ease: 'power2.out' });
                    }
                });
            });
            box.addEventListener('click', function () {
                if (hasGsap()) {
                    gsap.to(box, { opacity: 0, duration: 0.2, onComplete: function () { box.hidden = true; } });
                } else {
                    box.hidden = true;
                }
            });
        },

        // 上传拖拽高亮
        initDropzoneGlow: function () {
            var zone = document.querySelector('[data-upload-zone]');
            if (!zone) return;
            var glow = function (active) {
                zone.classList.toggle('dragover', active);
                if (hasGsap()) {
                    gsap.to(zone, { boxShadow: active ? '0 0 0 3px var(--accent), 0 0 40px rgba(99,102,241,.35)' : '0 0 0 1px var(--border)', duration: 0.25 });
                }
            };
            ['dragenter', 'dragover'].forEach(function (ev) {
                zone.addEventListener(ev, function (e) { e.preventDefault(); glow(true); });
            });
            ['dragleave', 'drop'].forEach(function (ev) {
                zone.addEventListener(ev, function (e) { e.preventDefault(); glow(false); });
            });
        },

        // 统计数字滚动（后台仪表盘）
        initCounters: function () {
            var counters = document.querySelectorAll('[data-counter]');
            if (!counters.length || !hasGsap()) return;
            counters.forEach(function (el) {
                var target = parseInt(el.getAttribute('data-counter'), 10) || 0;
                var obj = { v: 0 };
                gsap.to(obj, {
                    v: target,
                    duration: 1.2,
                    ease: 'power1.out',
                    onUpdate: function () {
                        el.textContent = Math.round(obj.v).toLocaleString();
                    }
                });
            });
        },

        // 卡片 3D 倾斜（后台仪表盘）
        initTilt: function () {
            var cards = document.querySelectorAll('[data-tilt]');
            if (!cards.length || !hasGsap()) return;
            cards.forEach(function (card) {
                card.addEventListener('mousemove', function (ev) {
                    var rect = card.getBoundingClientRect();
                    var x = (ev.clientX - rect.left) / rect.width - 0.5;
                    var y = (ev.clientY - rect.top) / rect.height - 0.5;
                    gsap.to(card, { rotateY: x * 6, rotateX: -y * 6, duration: 0.2 });
                });
                card.addEventListener('mouseleave', function () {
                    gsap.to(card, { rotateY: 0, rotateX: 0, duration: 0.4 });
                });
            });
        },

        // 进度条动画
        animateProgress: function (el, value) {
            if (!el) return;
            if (hasGsap()) {
                gsap.to(el, { width: value + '%', duration: 0.4, ease: 'power2.out' });
            } else {
                el.style.width = value + '%';
            }
        },

        // 初始化
        init: function () {
            if (!hasGsap()) {
                // 无 GSAP 时仍然保证交互可用
                this.initNavbar();
                this.initSearchFocus();
                this.initTabs();
                this.initLightbox();
                return;
            }
            this.initCanvas();
            this.initNavbar();
            this.initLogo();
            this.initNavLinks();
            this.initPageHead();
            this.initFileGrid();
            this.initSearchFocus();
            this.initPager();
            this.initTabs();
            this.initLightbox();
            this.initDropzoneGlow();
            this.initCounters();
            this.initTilt();
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        window.PanAnims.init();
    });
})();
