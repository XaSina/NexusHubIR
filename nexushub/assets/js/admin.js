/* NEXUS | HUB — Admin panel scripts */
(function () {
    'use strict';
    var A = window.NXA || {};
    var $ = function (s, c) { return (c || document).querySelector(s); };
    var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
    var fa = function (s) { return String(s).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; }); };
    var en = function (s) { return String(s).replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); }).replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); }); };
    A.$ = $; A.$$ = $$; A.fa = fa;

    /* ---------- Toast ---------- */
    A.toast = function (msg, type) {
        var t = document.createElement('div');
        t.className = 'toast ' + (type || 'success');
        t.textContent = msg;
        $('#toasts').appendChild(t);
        setTimeout(function () { t.classList.add('out'); setTimeout(function () { t.remove(); }, 400); }, 3800);
    };
    (A.flashes || []).forEach(function (f, i) { setTimeout(function () { A.toast(f.msg, f.type); }, 200 + i * 250); });

    /* ---------- Ajax ---------- */
    A.post = function (action, data, onProgress) {
        return new Promise(function (resolve, reject) {
            var fd = data instanceof FormData ? data : new FormData();
            if (!(data instanceof FormData)) Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
            fd.append('_csrf', A.csrf);
            fd.append('action', action);
            var x = new XMLHttpRequest();
            x.open('POST', A.ajax);
            x.setRequestHeader('X-CSRF-Token', A.csrf);
            if (onProgress && x.upload) x.upload.onprogress = function (e) { if (e.lengthComputable) onProgress(e.loaded / e.total); };
            x.onload = function () { try { resolve(JSON.parse(x.responseText)); } catch (e) { reject(x.responseText); } };
            x.onerror = function () { reject('network'); };
            x.send(fd);
        });
    };
    A.upload = function (file, onProgress) {
        var fd = new FormData(); fd.append('file', file);
        return A.post('upload', fd, onProgress);
    };

    /* ---------- Sidebar ---------- */
    var side = $('#admSide'), ov = $('#admOverlay');
    if ($('#admBurger')) $('#admBurger').addEventListener('click', function () { side.classList.add('open'); ov.classList.add('open'); });
    if (ov) ov.addEventListener('click', function () { side.classList.remove('open'); ov.classList.remove('open'); });

    /* ---------- Confirm ---------- */
    document.addEventListener('click', function (e) {
        var el = e.target.closest('[data-confirm]');
        if (el && !confirm(el.dataset.confirm)) { e.preventDefault(); e.stopPropagation(); }
    }, true);
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (f.dataset.confirmSubmit && !confirm(f.dataset.confirmSubmit)) e.preventDefault();
    });

    /* ---------- Tabs ---------- */
    $$('[data-tabs]').forEach(function (wrap) {
        var btns = $$('[data-tab]', wrap), key = 'nxa_tab_' + location.pathname;
        var show = function (name) {
            btns.forEach(function (b) { b.classList.toggle('active', b.dataset.tab === name); });
            $$('[data-pane]').forEach(function (p) { p.hidden = p.dataset.pane !== name; });
            try { sessionStorage.setItem(key, name); } catch (e) {}
        };
        btns.forEach(function (b) { b.addEventListener('click', function (e) { e.preventDefault(); show(b.dataset.tab); }); });
        var saved = null; try { saved = sessionStorage.getItem(key); } catch (e) {}
        show(saved && btns.some(function (b) { return b.dataset.tab === saved; }) ? saved : btns[0].dataset.tab);
    });

    /* ---------- Colors / ranges ---------- */
    $$('input[type=color][data-sync]').forEach(function (c) {
        var t = c.parentNode.querySelector('input[name="' + c.dataset.sync + '"]');
        c.addEventListener('input', function () { t.value = c.value; t.dispatchEvent(new Event('input', { bubbles: true })); });
        t.addEventListener('input', function () { if (/^#[0-9a-f]{6}$/i.test(t.value)) c.value = t.value; });
    });
    $$('.af-range input').forEach(function (r) {
        var o = r.closest('.af-range').querySelector('output');
        r.addEventListener('input', function () { o.textContent = r.value; });
    });

    /* ---------- Select all ---------- */
    $$('[data-check-all]').forEach(function (c) {
        c.addEventListener('change', function () { $$('input[name="' + c.dataset.checkAll + '"]').forEach(function (x) { x.checked = c.checked; }); });
    });

    /* ---------- Media picker ---------- */
    var mm = $('#mediaModal'), mmGrid = $('#mmGrid'), mmMore = $('#mmMore'), mmCb = null, mmPage = 1;
    function mmItem(m) {
        var d = document.createElement('div');
        d.className = 'm-item';
        d.innerHTML = '<img loading="lazy" alt=""><div class="m-info"><span></span></div>';
        d.querySelector('img').src = m.url;
        d.querySelector('span').textContent = m.w ? fa(m.w + '×' + m.h) : '';
        d.addEventListener('click', function () { if (mmCb) mmCb(m); A.closeMedia(); });
        return d;
    }
    function mmLoad(page) {
        A.post('media_list', { page: page }).then(function (j) {
            if (page === 1) mmGrid.innerHTML = '';
            if (!j.items.length && page === 1) mmGrid.innerHTML = '<div class="empty-state"><span>🖼️</span>هنوز تصویری آپلود نشده است.</div>';
            j.items.forEach(function (m) { mmGrid.appendChild(mmItem(m)); });
            mmMore.hidden = !j.more; mmPage = page;
        });
    }
    A.pickMedia = function (cb) { mmCb = cb; mm.hidden = false; mmLoad(1); };
    A.closeMedia = function () { mm.hidden = true; mmCb = null; };
    if (mm) {
        $$('[data-close]', mm).forEach(function (b) { b.addEventListener('click', A.closeMedia); });
        mmMore.addEventListener('click', function () { mmLoad(mmPage + 1); });
        $('#mmUpload').addEventListener('change', function () {
            var files = Array.prototype.slice.call(this.files), input = this;
            var next = function () {
                var f = files.shift();
                if (!f) { input.value = ''; return; }
                A.toast('در حال آپلود ' + f.name + ' ...', 'info');
                A.upload(f).then(function (j) {
                    if (j.ok) mmGrid.insertBefore(mmItem({ id: j.id, url: j.url, path: j.path }), mmGrid.firstChild);
                    else A.toast(j.error || 'خطا در آپلود', 'error');
                    next();
                });
            };
            if (mmGrid.querySelector('.empty-state')) mmGrid.innerHTML = '';
            next();
        });
    }
    $$('[data-image-field]').forEach(function (f) {
        var input = $('input[type=hidden]', f), box = $('.img-pick', f), img = $('img', box);
        var open = function () { A.pickMedia(function (m) { input.value = m.path; img.src = m.url; box.classList.add('has'); input.dispatchEvent(new Event('change', { bubbles: true })); }); };
        box.addEventListener('click', open);
        $('[data-pick]', f).addEventListener('click', open);
        $('[data-clear]', f).addEventListener('click', function () { input.value = ''; img.removeAttribute('src'); box.classList.remove('has'); input.dispatchEvent(new Event('change', { bubbles: true })); });
    });

    /* ---------- Tag input ---------- */
    $$('[data-tag-input]').forEach(function (wrap) {
        var hidden = $('input[type=hidden]', wrap), inp = $('input[type=text]', wrap);
        var tags = hidden.value ? hidden.value.split(',').map(function (t) { return t.trim(); }).filter(Boolean) : [];
        var render = function () {
            $$('.tg', wrap).forEach(function (t) { t.remove(); });
            tags.forEach(function (t, i) {
                var s = document.createElement('span'); s.className = 'tg'; s.textContent = '#' + t;
                var b = document.createElement('button'); b.type = 'button'; b.textContent = '×';
                b.addEventListener('click', function () { tags.splice(i, 1); render(); });
                s.appendChild(b); wrap.insertBefore(s, inp);
            });
            hidden.value = tags.join(',');
        };
        var add = function () {
            inp.value.split(/[,،]/).forEach(function (v) { v = v.trim().replace(/^#/, ''); if (v && tags.indexOf(v) < 0) tags.push(v); });
            inp.value = ''; render();
        };
        inp.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ',' || e.key === '،') { e.preventDefault(); add(); }
            if (e.key === 'Backspace' && !inp.value && tags.length) { tags.pop(); render(); }
        });
        inp.addEventListener('blur', add);
        wrap.addEventListener('click', function () { inp.focus(); });
        render();
    });

    /* ---------- Slug ---------- */
    A.slugify = function (t) {
        return en(t).trim().toLowerCase().replace(/[‌‏‎]/g, '-').replace(/[^\p{L}\p{N}]+/gu, '-').replace(/-+/g, '-').replace(/^-|-$/g, '').slice(0, 120);
    };
    $$('[data-slug-from]').forEach(function (slug) {
        var src = $(slug.dataset.slugFrom), touched = slug.value !== '';
        slug.addEventListener('input', function () { touched = slug.value !== ''; });
        if (src) src.addEventListener('input', function () { if (!touched) slug.value = A.slugify(src.value); });
    });

    /* ---------- Sortable ---------- */
    A.sortable = function (list, onChange) {
        var drag = null;
        list.addEventListener('dragstart', function (e) { drag = e.target.closest('.sort-item'); if (drag) { drag.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; } });
        list.addEventListener('dragend', function () { if (drag) drag.classList.remove('dragging'); drag = null; onChange && onChange(); });
        list.addEventListener('dragover', function (e) {
            e.preventDefault();
            var over = e.target.closest('.sort-item');
            if (!drag || !over || over === drag) return;
            var r = over.getBoundingClientRect();
            list.insertBefore(drag, (e.clientY - r.top) > r.height / 2 ? over.nextSibling : over);
        });
        list.addEventListener('click', function (e) {
            var b = e.target.closest('[data-mv]'); if (!b) return;
            e.preventDefault();
            var it = b.closest('.sort-item');
            if (b.dataset.mv === 'up' && it.previousElementSibling) list.insertBefore(it, it.previousElementSibling);
            if (b.dataset.mv === 'down' && it.nextElementSibling) list.insertBefore(it.nextElementSibling, it);
            onChange && onChange();
        });
    };

    /* ---------- Chart (SVG) ---------- */
    $$('[data-chart]').forEach(function (wrap) {
        var data = JSON.parse(wrap.dataset.chart), W = wrap.clientWidth || 600, H = wrap.clientHeight || 260, pad = 34;
        var series = data.series, labels = data.labels, max = 1;
        series.forEach(function (s) { s.values.forEach(function (v) { if (v > max) max = v; }); });
        max = Math.ceil(max * 1.15);
        var x = function (i) { return W - pad - i * ((W - pad * 2) / Math.max(1, labels.length - 1)); };
        var y = function (v) { return H - pad - (v / max) * (H - pad * 2); };
        var svg = '<svg viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="none"><defs>';
        series.forEach(function (s, k) { svg += '<linearGradient id="cg' + k + '" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="' + s.color + '" stop-opacity=".45"/><stop offset="1" stop-color="' + s.color + '" stop-opacity="0"/></linearGradient>'; });
        svg += '</defs>';
        for (var g = 0; g <= 4; g++) { var gy = pad + g * (H - pad * 2) / 4; svg += '<line x1="' + pad + '" x2="' + (W - pad) + '" y1="' + gy + '" y2="' + gy + '" stroke="rgba(255,255,255,.06)"/><text x="' + (pad - 6) + '" y="' + (gy + 4) + '" fill="#777" font-size="10" text-anchor="end">' + fa(Math.round(max - g * max / 4)) + '</text>'; }
        labels.forEach(function (l, i) { if (i % Math.ceil(labels.length / 7) === 0) svg += '<text x="' + x(i) + '" y="' + (H - 8) + '" fill="#888" font-size="10" text-anchor="middle">' + l + '</text>'; });
        series.forEach(function (s, k) {
            var pts = s.values.map(function (v, i) { return [x(i), y(v)]; });
            var d = pts.map(function (p, i) {
                if (i === 0) return 'M' + p[0] + ' ' + p[1];
                var pr = pts[i - 1], cx = (pr[0] + p[0]) / 2;
                return 'C' + cx + ' ' + pr[1] + ' ' + cx + ' ' + p[1] + ' ' + p[0] + ' ' + p[1];
            }).join(' ');
            svg += '<path d="' + d + ' L' + pts[pts.length - 1][0] + ' ' + (H - pad) + ' L' + pts[0][0] + ' ' + (H - pad) + 'Z" fill="url(#cg' + k + ')"/>';
            svg += '<path d="' + d + '" fill="none" stroke="' + s.color + '" stroke-width="2.5" stroke-linecap="round" style="filter:drop-shadow(0 0 6px ' + s.color + ');stroke-dasharray:3000;stroke-dashoffset:3000;animation:dash 2s ease forwards ' + (k * .3) + 's"/>';
            pts.forEach(function (p, i) { svg += '<circle cx="' + p[0] + '" cy="' + p[1] + '" r="10" fill="transparent" data-i="' + i + '"/>'; });
        });
        svg += '<style>@keyframes dash{to{stroke-dashoffset:0}}</style></svg><div class="chart-tip"></div>';
        wrap.innerHTML = svg;
        var tip = $('.chart-tip', wrap);
        $$('circle', wrap).forEach(function (c) {
            c.addEventListener('mouseenter', function () {
                var i = +c.dataset.i;
                tip.innerHTML = '<b>' + labels[i] + '</b><br>' + series.map(function (s) { return '<span style="color:' + s.color + '">●</span> ' + s.name + ': ' + fa(s.values[i]); }).join('<br>');
                tip.style.left = (c.getAttribute('cx') / W * 100) + '%'; tip.style.top = (c.getAttribute('cy') / H * 100) + '%'; tip.style.opacity = 1;
            });
            c.addEventListener('mouseleave', function () { tip.style.opacity = 0; });
        });
    });

    /* ---------- Copy ---------- */
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-copy]'); if (!b) return;
        var t = b.dataset.copy;
        if (navigator.clipboard) navigator.clipboard.writeText(t).then(function () { A.toast('کپی شد ✅'); });
        else prompt('کپی کنید:', t);
    });

    /* ==========================================================================
       Jalali date picker
       ========================================================================== */
    function g2j(gy, gm, gd) {
        var g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334], gy2 = gm > 2 ? gy + 1 : gy;
        var days = 355666 + 365 * gy + ~~((gy2 + 3) / 4) - ~~((gy2 + 99) / 100) + ~~((gy2 + 399) / 400) + gd + g_d_m[gm - 1];
        var jy = -1595 + 33 * ~~(days / 12053); days %= 12053; jy += 4 * ~~(days / 1461); days %= 1461;
        if (days > 365) { jy += ~~((days - 1) / 365); days = (days - 1) % 365; }
        var jm, jd;
        if (days < 186) { jm = 1 + ~~(days / 31); jd = 1 + days % 31; } else { jm = 7 + ~~((days - 186) / 30); jd = 1 + (days - 186) % 30; }
        return [jy, jm, jd];
    }
    function j2g(jy, jm, jd) {
        jy += 1595;
        var days = -355668 + 365 * jy + ~~(jy / 33) * 8 + ~~(((jy % 33) + 3) / 4) + jd + (jm < 7 ? (jm - 1) * 31 : (jm - 7) * 30 + 186);
        var gy = 400 * ~~(days / 146097); days %= 146097;
        if (days > 36524) { days--; gy += 100 * ~~(days / 36524); days %= 36524; if (days >= 365) days++; }
        gy += 4 * ~~(days / 1461); days %= 1461;
        if (days > 365) { gy += ~~((days - 1) / 365); days = (days - 1) % 365; }
        var gd = days + 1, sal = [0, 31, ((gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31], gm;
        for (gm = 0; gm < 13 && gd > sal[gm]; gm++) gd -= sal[gm];
        return [gy, gm, gd];
    }
    A.g2j = g2j; A.j2g = j2g;
    var months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    function monthLen(jy, jm) {
        if (jm <= 6) return 31; if (jm <= 11) return 30;
        var a = j2g(jy, 12, 30), b = j2g(jy + 1, 1, 1);
        return (a[0] === b[0] && a[1] === b[1] && a[2] === b[2]) ? 29 : 30;
    }
    function pad(n) { return ('0' + n).slice(-2); }
    var picker = null;
    function closePicker() { if (picker) { picker.remove(); picker = null; } }
    function openPicker(input) {
        closePicker();
        var now = new Date(), t = g2j(now.getFullYear(), now.getMonth() + 1, now.getDate());
        var m = en(input.value).match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})(?:\s+(\d{1,2}):(\d{1,2}))?/);
        var sel = m ? [+m[1], +m[2], +m[3]] : null;
        var hh = m && m[4] ? +m[4] : now.getHours(), mi = m && m[5] ? +m[5] : now.getMinutes();
        var vy = sel ? sel[0] : t[0], vm = sel ? sel[1] : t[1];
        picker = document.createElement('div');
        picker.className = 'jdp';
        var render = function () {
            var g = j2g(vy, vm, 1), first = (new Date(g[0], g[1] - 1, g[2]).getDay() + 1) % 7, len = monthLen(vy, vm);
            var h = '<div class="jdp-head"><button type="button" data-n="-1">›</button><b>' + months[vm - 1] + ' ' + fa(vy) + '</b><button type="button" data-n="1">‹</button></div><div class="jdp-grid">';
            ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'].forEach(function (d) { h += '<span>' + d + '</span>'; });
            for (var i = 0; i < first; i++) h += '<span></span>';
            for (var d = 1; d <= len; d++) {
                var cls = [];
                if (vy === t[0] && vm === t[1] && d === t[2]) cls.push('today');
                if (sel && vy === sel[0] && vm === sel[1] && d === sel[2]) cls.push('sel');
                if ((first + d - 1) % 7 === 6) cls.push('fri');
                h += '<button type="button" class="' + cls.join(' ') + '" data-d="' + d + '">' + fa(d) + '</button>';
            }
            h += '</div><div class="jdp-foot">ساعت <input type="number" min="0" max="23" value="' + hh + '" data-h> : <input type="number" min="0" max="59" value="' + mi + '" data-m>'
                + '<button type="button" class="abtn abtn-ghost abtn-xs" data-now>اکنون</button><button type="button" class="abtn abtn-ghost abtn-xs" data-clear>پاک</button></div>';
            picker.innerHTML = h;
        };
        var write = function () {
            if (!sel) return;
            hh = Math.min(23, Math.max(0, +$('[data-h]', picker).value || 0)); mi = Math.min(59, Math.max(0, +$('[data-m]', picker).value || 0));
            input.value = sel[0] + '/' + pad(sel[1]) + '/' + pad(sel[2]) + ' ' + pad(hh) + ':' + pad(mi);
            input.dispatchEvent(new Event('change', { bubbles: true }));
        };
        render();
        picker.addEventListener('click', function (e) {
            e.stopPropagation();
            var b = e.target.closest('button'); if (!b) return;
            if (b.dataset.n) { vm -= +b.dataset.n * -1; if (vm < 1) { vm = 12; vy--; } if (vm > 12) { vm = 1; vy++; } render(); }
            else if (b.dataset.d) { sel = [vy, vm, +b.dataset.d]; write(); render(); }
            else if (b.hasAttribute('data-now')) { var n = new Date(); sel = t.slice(); vy = t[0]; vm = t[1]; hh = n.getHours(); mi = n.getMinutes(); render(); write(); closePicker(); }
            else if (b.hasAttribute('data-clear')) { input.value = ''; input.dispatchEvent(new Event('change', { bubbles: true })); closePicker(); }
        });
        picker.addEventListener('change', function (e) { if (e.target.matches('[data-h],[data-m]')) write(); });
        document.body.appendChild(picker);
        var r = input.getBoundingClientRect();
        var left = Math.max(8, Math.min(window.innerWidth - 308, r.left + window.scrollX));
        picker.style.top = (r.bottom + window.scrollY + 6) + 'px';
        picker.style.left = left + 'px';
    }
    document.addEventListener('focusin', function (e) { if (e.target.matches('[data-jdp]')) openPicker(e.target); });
    document.addEventListener('click', function (e) { if (picker && !picker.contains(e.target) && !e.target.matches('[data-jdp]')) closePicker(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closePicker(); if (mm && !mm.hidden) A.closeMedia(); } });

    /* ---------- Countdown (ad previews) ---------- */
    setInterval(function () {
        $$('.ad-countdown').forEach(function (c) {
            var diff = Math.max(0, Math.floor((new Date(c.dataset.end).getTime() - Date.now()) / 1000));
            var v = { d: Math.floor(diff / 86400), h: Math.floor(diff % 86400 / 3600), m: Math.floor(diff % 3600 / 60), s: diff % 60 };
            $$('b', c).forEach(function (b) { b.textContent = fa(v[b.dataset.u]); });
        });
    }, 1000);

    window.NXA = A;
})();
