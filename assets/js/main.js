/**
 * 前台交互脚本
 * 依赖: PAN 全局配置、PanAnims
 */
(function () {
    'use strict';

    var PAN = window.PAN || {};
    var BASE = PAN.baseUrl || '';
    var CSRF = PAN.csrfToken || '';

    function postForm(url, formData, cb) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        if (CSRF) xhr.setRequestHeader('X-CSRF-Token', CSRF);
        xhr.onload = function () {
            var data = null;
            try { data = JSON.parse(xhr.responseText); } catch (e) { data = null; }
            cb(data, xhr.status);
        };
        xhr.onerror = function () {
            cb(null, 0);
        };
        xhr.send(formData);
    }

    function getJSON(url, cb) {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        if (CSRF) xhr.setRequestHeader('X-CSRF-Token', CSRF);
        xhr.onload = function () {
            var data = null;
            try { data = JSON.parse(xhr.responseText); } catch (e) { data = null; }
            cb(data, xhr.status);
        };
        xhr.send();
    }

    function toast(msg, type) {
        if (window.PanAnims) {
            window.PanAnims.showToast(msg, type || 'success');
        } else {
            var t = document.getElementById('toast');
            if (t) { t.textContent = msg; t.className = 'toast show'; setTimeout(function () { t.classList.remove('show'); }, 2400); }
        }
    }

    /* ---------- 认证表单 ---------- */
    function initAuth() {
        var form = document.querySelector('[data-auth-form]');
        if (!form) return;
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var action = form.getAttribute('data-auth-form');
            var fd = new FormData(form);
            fd.append('action', action);
            var btn = form.querySelector('button[type=submit]');
            btn.disabled = true;
            postForm(BASE + '/api/user.php', fd, function (data) {
                btn.disabled = false;
                if (data && data.code === 0) {
                    toast(data.msg || '成功');
                    setTimeout(function () { window.location.href = BASE + '/index.php'; }, 600);
                } else {
                    toast((data && data.msg) || '操作失败', 'error');
                }
            });
        });
    }

    /* ---------- 上传逻辑 ---------- */
    var cfg = window.UPLOAD_CONFIG || { maxSize: 0, dailyLimit: 0, chunkSize: 2097152, mode: 'relay', accept: '' };

    // 极简 md5（用于小文件秒传）
    function md5(input) {
        // 兼容 ArrayBuffer
        var bytes = new Uint8Array(input);
        function toHex(n) { var s = '', i; for (i = 0; i < 4; i++) s += ('0' + ((n >>> (i * 8)) & 255).toString(16)).slice(-2); return s; }
        function add32(a, b) { return (a + b) & 0xffffffff; }
        function cmn(q, a, b, x, s, t) { a = add32(add32(a, q), add32(x, t)); return add32((a << s) | (a >>> (32 - s)), b); }
        function ff(a, b, c, d, x, s, t) { return cmn((b & c) | (~b & d), a, b, x, s, t); }
        function gg(a, b, c, d, x, s, t) { return cmn((b & d) | (c & ~d), a, b, x, s, t); }
        function hh(a, b, c, d, x, s, t) { return cmn(b ^ c ^ d, a, b, x, s, t); }
        function ii(a, b, c, d, x, s, t) { return cmn(c ^ (b | ~d), a, b, x, s, t); }
        function md5cycle(x, k) {
            var a = x[0], b = x[1], c = x[2], d = x[3];
            a = ff(a, b, c, d, k[0], 7, -680876936); d = ff(d, a, b, c, k[1], 12, -389564586); c = ff(c, d, a, b, k[2], 17, 606105819); b = ff(b, c, d, a, k[3], 22, -1044525330);
            a = ff(a, b, c, d, k[4], 7, -176418897); d = ff(d, a, b, c, k[5], 12, 1200080426); c = ff(c, d, a, b, k[6], 17, -1473231341); b = ff(b, c, d, a, k[7], 22, -45705983);
            a = ff(a, b, c, d, k[8], 7, 1770035416); d = ff(d, a, b, c, k[9], 12, -1958414417); c = ff(c, d, a, b, k[10], 17, -42063); b = ff(b, c, d, a, k[11], 22, -1990404162);
            a = ff(a, b, c, d, k[12], 7, 1804603682); d = ff(d, a, b, c, k[13], 12, -40341101); c = ff(c, d, a, b, k[14], 17, -1502002290); b = ff(b, c, d, a, k[15], 22, 1236535329);
            a = gg(a, b, c, d, k[1], 5, -165796510); d = gg(d, a, b, c, k[6], 9, -1069501632); c = gg(c, d, a, b, k[11], 14, 643717713); b = gg(b, c, d, a, k[0], 20, -373897302);
            a = gg(a, b, c, d, k[5], 5, -701558691); d = gg(d, a, b, c, k[10], 9, 38016083); c = gg(c, d, a, b, k[15], 14, -660478335); b = gg(b, c, d, a, k[4], 20, -405537848);
            a = gg(a, b, c, d, k[9], 5, 568446438); d = gg(d, a, b, c, k[14], 9, -1019803690); c = gg(c, d, a, b, k[3], 14, -187363961); b = gg(b, c, d, a, k[8], 20, 1163531501);
            a = gg(a, b, c, d, k[13], 5, -1444681467); d = gg(d, a, b, c, k[2], 9, -51403784); c = gg(c, d, a, b, k[7], 14, 1735328473); b = gg(b, c, d, a, k[12], 20, -1926607734);
            a = hh(a, b, c, d, k[5], 4, -378558); d = hh(d, a, b, c, k[8], 11, -2022574463); c = hh(c, d, a, b, k[11], 16, 1839030562); b = hh(b, c, d, a, k[14], 23, -35309556);
            a = hh(a, b, c, d, k[1], 4, -1530992060); d = hh(d, a, b, c, k[4], 11, 1272893353); c = hh(c, d, a, b, k[7], 16, -155497632); b = hh(b, c, d, a, k[10], 23, -1094730640);
            a = hh(a, b, c, d, k[13], 4, 681279174); d = hh(d, a, b, c, k[0], 11, -358537222); c = hh(c, d, a, b, k[3], 16, -722521979); b = hh(b, c, d, a, k[6], 23, 76029189);
            a = hh(a, b, c, d, k[9], 4, -640364487); d = hh(d, a, b, c, k[12], 11, -421815835); c = hh(c, d, a, b, k[15], 16, 530742520); b = hh(b, c, d, a, k[2], 23, -995338651);
            a = ii(a, b, c, d, k[0], 6, -198630844); d = ii(d, a, b, c, k[7], 10, 1126891415); c = ii(c, d, a, b, k[14], 15, -1416354905); b = ii(b, c, d, a, k[5], 21, -57434055);
            a = ii(a, b, c, d, k[12], 6, 1700485571); d = ii(d, a, b, c, k[3], 10, -1894986606); c = ii(c, d, a, b, k[10], 15, -1051523); b = ii(b, c, d, a, k[1], 21, -2054922799);
            a = ii(a, b, c, d, k[8], 6, 1873313359); d = ii(d, a, b, c, k[15], 10, -30611744); c = ii(c, d, a, b, k[6], 15, -1560198380); b = ii(b, c, d, a, k[13], 21, 1309151649);
            a = ii(a, b, c, d, k[4], 6, -145523070); d = ii(d, a, b, c, k[11], 10, -1120210379); c = ii(c, d, a, b, k[2], 15, 718787259); b = ii(b, c, d, a, k[9], 21, -343485551);
            x[0] = add32(a, x[0]); x[1] = add32(b, x[1]); x[2] = add32(c, x[2]); x[3] = add32(d, x[3]);
        }
        var len = bytes.length;
        var padLen = ((len + 8) >> 6 << 6) + 64;
        var buf = new Uint8Array(padLen);
        buf.set(bytes);
        buf[len] = 0x80;
        var bits = len * 8;
        var dv = new DataView(buf.buffer);
        dv.setUint32(padLen - 8, bits >>> 0, true);
        dv.setUint32(padLen - 4, Math.floor(bits / 0x100000000), true);
        var x = [1732584193, -271733879, -1732584194, 271733878];
        for (var i = 0; i < padLen; i += 64) {
            var k = new Array(16);
            for (var j = 0; j < 16; j++) k[j] = dv.getUint32(i + j * 4, true);
            md5cycle(x, k);
        }
        return toHex(x[0]) + toHex(x[1]) + toHex(x[2]) + toHex(x[3]);
    }

    function extOf(name) {
        var i = name.lastIndexOf('.');
        return i >= 0 ? name.slice(i + 1).toLowerCase() : '';
    }

    function checkAccept(name) {
        if (!cfg.accept || cfg.accept === 'all') return true;
        var ext = extOf(name);
        var list = cfg.accept.split(',');
        return list.indexOf(ext) >= 0;
    }

    function uploadFile(file, publish, listEl) {
        var ext = extOf(file.name);
        if (!checkAccept(file.name)) {
            addUploadItem(listEl, file.name, 0, 'error', '不允许上传该类型文件');
            return;
        }
        if (cfg.maxSize > 0 && file.size > cfg.maxSize) {
            addUploadItem(listEl, file.name, 0, 'error', '文件超过大小限制');
            return;
        }

        var item = addUploadItem(listEl, file.name, file.size, 'uploading', '计算文件特征...');
        var bar = item.querySelector('[data-bar]');
        var statusEl = item.querySelector('[data-status]');

        var finish = function (data) {
            if (data && data.code === 0) {
                statusEl.textContent = data.data && data.data.instant ? '秒传成功' : '上传成功';
                item.classList.remove('uploading');
                item.classList.add('success');
                var link = document.createElement('a');
                link.href = BASE + '/index.php?p=view&id=' + (data.data ? data.data.id : '');
                link.textContent = '查看';
                link.className = 'btn btn-primary btn-sm';
                item.querySelector('[data-actions]').appendChild(link);
            } else {
                statusEl.textContent = (data && data.msg) || '上传失败';
                item.classList.remove('uploading');
                item.classList.add('error');
            }
        };

        // 浏览器直传
        if (cfg.mode === 'direct') {
            getJSON(BASE + '/api/storage.php?ext=' + encodeURIComponent(ext || 'file'), function (info) {
                if (!info || info.code !== 0 || !info.data.direct) {
                    statusEl.textContent = '获取直传参数失败';
                    item.classList.add('error');
                    return;
                }
                var d = info.data.direct;
                statusEl.textContent = '直传存储中...';
                var fd = new FormData();
                Object.keys(d.fields).forEach(function (k) { fd.append(k, d.fields[k]); });
                fd.append('file', file, file.name);
                var xhr = new XMLHttpRequest();
                xhr.open('POST', d.endpoint, true);
                xhr.onload = function () {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        // 通知服务器记录
                        var fd2 = new FormData();
                        fd2.append('action', 'direct_done');
                        fd2.append('filename', file.name);
                        fd2.append('path', d.save_path);
                        fd2.append('size', file.size);
                        fd2.append('publish', publish ? '1' : '0');
                        postForm(BASE + '/api/upload.php', fd2, finish);
                    } else {
                        statusEl.textContent = '直传存储失败 HTTP ' + xhr.status;
                        item.classList.add('error');
                    }
                };
                xhr.onerror = function () {
                    statusEl.textContent = '直传网络错误';
                    item.classList.add('error');
                };
                xhr.send(fd);
            });
            return;
        }

        // 计算 hash（仅小文件）
        var computeHash = function (cb) {
            if (file.size > 10 * 1024 * 1024) { cb(''); return; }
            var reader = new FileReader();
            reader.onload = function () {
                try { cb(md5(reader.result)); } catch (e) { cb(''); }
            };
            reader.onerror = function () { cb(''); };
            reader.readAsArrayBuffer(file);
        };

        computeHash(function (hash) {
            var chunkSize = cfg.chunkSize || 2097152;
            var total = Math.max(1, Math.ceil(file.size / chunkSize));
            var idx = 0;

            var sendChunk = function () {
                if (idx >= total) return;
                var start = idx * chunkSize;
                var blob = file.slice(start, Math.min(start + chunkSize, file.size));
                var fd = new FormData();
                fd.append('action', 'chunk');
                fd.append('filename', file.name);
                fd.append('index', idx);
                fd.append('total', total);
                fd.append('hash', hash);
                fd.append('publish', publish ? '1' : '0');
                fd.append('file', blob, 'chunk.part');

                postForm(BASE + '/api/upload.php', fd, function (data) {
                    if (!data) {
                        statusEl.textContent = '网络错误';
                        item.classList.add('error');
                        return;
                    }
                    if (data.code !== 0) {
                        statusEl.textContent = data.msg || '上传失败';
                        item.classList.add('error');
                        return;
                    }
                    if (data.data && data.data.done === false) {
                        idx++;
                        var pct = Math.min(99, Math.round(idx / total * 100));
                        statusEl.textContent = '上传中 ' + pct + '%';
                        if (window.PanAnims) window.PanAnims.animateProgress(bar, pct);
                        sendChunk();
                    } else {
                        if (window.PanAnims) window.PanAnims.animateProgress(bar, 100);
                        finish(data);
                    }
                });
            };
            sendChunk();
        });
    }

    function addUploadItem(listEl, name, size, state, statusText) {
        var div = document.createElement('div');
        div.className = 'upload-item ' + state;
        div.innerHTML =
            '<div class="upload-item-head">' +
                '<span class="upload-item-name"></span>' +
                '<span class="upload-item-status" data-status></span>' +
            '</div>' +
            '<div class="progress-track"><div class="progress-bar" data-bar style="width:0"></div></div>' +
            '<div class="upload-item-actions" data-actions></div>';
        div.querySelector('.upload-item-name').textContent = name + ' (' + (size ? fmtSize(size) : '?') + ')';
        div.querySelector('[data-status]').textContent = statusText || '';
        listEl.appendChild(div);
        if (window.PanAnims && window.gsap) {
            window.gsap.from(div, { opacity: 0, y: 14, duration: 0.35 });
        }
        return div;
    }

    function fmtSize(size) {
        if (size < 1024) return size + ' B';
        if (size < 1048576) return (size / 1024).toFixed(1) + ' KB';
        if (size < 1073741824) return (size / 1048576).toFixed(1) + ' MB';
        return (size / 1073741824).toFixed(2) + ' GB';
    }

    function initUpload() {
        var zone = document.querySelector('[data-upload-zone]');
        if (!zone) return;
        var input = document.getElementById('fileInput');
        var listEl = document.getElementById('uploadList');
        var publishBox = document.getElementById('publishPublic');

        zone.addEventListener('click', function () { input.click(); });
        input.addEventListener('change', function () {
            if (!input.files.length) return;
            var files = Array.prototype.slice.call(input.files);
            files.forEach(function (f) { uploadFile(f, publishBox.checked, listEl); });
            input.value = '';
        });
        zone.addEventListener('drop', function (ev) {
            ev.preventDefault();
            var files = Array.prototype.slice.call(ev.dataTransfer.files);
            files.forEach(function (f) { uploadFile(f, publishBox.checked, listEl); });
        });
    }

    /* ---------- 文件查看页 ---------- */
    function initView() {
        // 复制
        document.querySelectorAll('[data-copy]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var input = btn.closest('.copy-row').querySelector('[data-copy-value]');
                var val = input ? input.value : '';
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(val).then(function () { toast('复制成功'); });
                } else {
                    input.select();
                    document.execCommand('copy');
                    toast('复制成功');
                }
            });
        });

        // 删除文件
        var delBtn = document.querySelector('[data-del-file]');
        if (delBtn) {
            delBtn.addEventListener('click', function () {
                if (!window.confirm('确定删除该文件吗？')) return;
                var fd = new FormData();
                fd.append('action', 'delete');
                fd.append('id', delBtn.getAttribute('data-del-file'));
                postForm(BASE + '/api/file.php', fd, function (data) {
                    if (data && data.code === 0) {
                        toast('删除成功');
                        setTimeout(function () { window.location.href = BASE + '/index.php'; }, 700);
                    } else {
                        toast((data && data.msg) || '删除失败', 'error');
                    }
                });
            });
        }

        // 文本查看 / 编辑
        var loadBtn = document.getElementById('viewTextBtn');
        var editBtn = document.getElementById('editTextBtn');
        var codeView = document.getElementById('previewCode');
        var editorWrap = document.getElementById('editorWrap');
        var editor = document.getElementById('textEditor');
        var fileId = new URLSearchParams(window.location.search).get('id');

        if (loadBtn && codeView) {
            loadBtn.addEventListener('click', function () {
                getJSON(BASE + '/api/text.php?action=get&id=' + fileId, function (data) {
                    if (data && data.code === 0) {
                        codeView.textContent = data.data.content;
                        if (editBtn) editBtn.hidden = !data.data.can_edit;
                        loadBtn.hidden = true;
                        if (window.PanAnims && window.gsap) {
                            window.gsap.from(codeView, { opacity: 0, y: 10, duration: 0.3 });
                        }
                    } else {
                        toast((data && data.msg) || '读取失败', 'error');
                    }
                });
            });
        }
        if (editBtn) {
            editBtn.addEventListener('click', function () {
                codeView.hidden = true;
                editor.value = codeView.textContent;
                editorWrap.hidden = false;
            });
        }
        var saveBtn = document.querySelector('[data-save-text]');
        var cancelBtn = document.querySelector('[data-cancel-edit]');
        if (saveBtn) {
            saveBtn.addEventListener('click', function () {
                var fd = new FormData();
                fd.append('action', 'save');
                fd.append('id', fileId);
                fd.append('content', editor.value);
                postForm(BASE + '/api/text.php', fd, function (data) {
                    if (data && data.code === 0) {
                        codeView.textContent = editor.value;
                        codeView.hidden = false;
                        editorWrap.hidden = true;
                        toast('保存成功');
                    } else {
                        toast((data && data.msg) || '保存失败', 'error');
                    }
                });
            });
        }
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                codeView.hidden = false;
                editorWrap.hidden = true;
            });
        }
    }

    /* ---------- 用户中心 ---------- */
    function initUserCenter() {
        document.querySelectorAll('[data-user-del]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!window.confirm('确定删除该文件吗？')) return;
                var fd = new FormData();
                fd.append('action', 'delete');
                fd.append('id', btn.getAttribute('data-user-del'));
                postForm(BASE + '/api/file.php', fd, function (data) {
                    if (data && data.code === 0) {
                        toast('删除成功');
                        var row = btn.closest('[data-file-row]');
                        if (row) row.remove();
                    } else {
                        toast((data && data.msg) || '删除失败', 'error');
                    }
                });
            });
        });
    }

    /* ---------- 公告关闭 ---------- */
    function initAnnounce() {
        var closeBtn = document.querySelector('[data-close-announce]');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                var bar = document.getElementById('announceBar');
                if (bar) {
                    if (window.gsap) {
                        window.gsap.to(bar, { height: 0, opacity: 0, duration: 0.3, onComplete: function () { bar.remove(); } });
                    } else {
                        bar.remove();
                    }
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        initAuth();
        initUpload();
        initView();
        initUserCenter();
        initAnnounce();
    });
})();
