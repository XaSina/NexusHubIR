/* NEXUS | HUB — panel quick search (Ctrl + K) */
(function () {
    'use strict';
    var A = window.NXA || {}, items = A.cmd || [], box, inp, list, sel = 0, shown = [], timer = 0, ctrl = null, lastQ = '', remote = [];
    var RKEY = 'nx_cmd_recent';

    function norm(s) {
        return String(s || '').toLowerCase()
            .replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); })
            .replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); })
            .replace(/ي/g, 'ی').replace(/ك/g, 'ک').replace(/[‌ً-ٟ]/g, '').replace(/\s+/g, ' ').trim();
    }
    function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function mark(t, q) {
        if (!q) return esc(t);
        var n = norm(t), i = n.indexOf(q);
        // the normalised text keeps the same length for letters, so the index maps back safely
        if (i < 0 || n.length !== String(t).length) return esc(t);
        return esc(t.slice(0, i)) + '<mark>' + esc(t.slice(i, i + q.length)) + '</mark>' + esc(t.slice(i + q.length));
    }
    function score(it, q) {
        var t = norm(it.t), s = norm(it.s || '');
        if (t.indexOf(q) === 0) return 3;
        if (t.indexOf(q) > -1) return 2;
        if (s.indexOf(q) > -1) return 1;
        // letters in order (e.g. "تنظف" → تنظیمات فروشگاه)
        var j = 0; for (var k = 0; k < t.length && j < q.length; k++) if (t[k] === q[j]) j++;
        return j === q.length && q.length > 2 ? .5 : 0;
    }
    function recent() { try { return JSON.parse(localStorage.getItem(RKEY) || '[]').slice(0, 5); } catch (e) { return []; } }
    function remember(it) {
        try {
            var r = recent().filter(function (x) { return x.h !== it.h; });
            r.unshift({ g: 'اخیر', i: it.i, t: it.t, s: it.s || '', h: it.h, x: it.x || 0 });
            localStorage.setItem(RKEY, JSON.stringify(r.slice(0, 5)));
        } catch (e) { }
    }

    function build() {
        box = document.createElement('div');
        box.className = 'cmdk'; box.hidden = true;
        box.innerHTML = '<div class="cmdk-dim" data-x></div>' +
            '<div class="cmdk-box" role="dialog" aria-modal="true" aria-label="جستجوی سریع">' +
            '<label class="cmdk-in"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>' +
            '<input type="search" id="cmdkInput" autocomplete="off" spellcheck="false" placeholder="صفحه، خبر، کد سفارش، شماره عضو…" aria-controls="cmdkList">' +
            '<i class="cmdk-spin" hidden></i><button type="button" class="cmdk-esc" data-x>Esc</button></label>' +
            '<div class="cmdk-list" id="cmdkList" role="listbox"></div>' +
            '<div class="cmdk-foot"><span><kbd>↑</kbd><kbd>↓</kbd> جابه‌جایی</span><span><kbd>Enter</kbd> باز کردن</span><span class="cmdk-ft-d"><kbd>Ctrl</kbd>+<kbd>Enter</kbd> زبانه جدید</span><span class="cmdk-ft-d"><kbd>/</kbd> یا <kbd>Ctrl K</kbd> هر جای پنل</span></div>' +
            '</div>';
        document.body.appendChild(box);
        inp = box.querySelector('input'); list = box.querySelector('.cmdk-list');
        box.addEventListener('click', function (e) {
            if (e.target.closest('[data-x]')) return close();
            var row = e.target.closest('.cmdk-it'); if (row) go(+row.dataset.n, e.ctrlKey || e.metaKey);
        });
        list.addEventListener('mousemove', function (e) { var row = e.target.closest('.cmdk-it'); if (row && +row.dataset.n !== sel) { sel = +row.dataset.n; paint(); } });
        inp.addEventListener('input', function () { sel = 0; render(); fetchRemote(); });
        inp.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
            else if (e.key === 'Enter') { e.preventDefault(); go(sel, e.ctrlKey || e.metaKey); }
            else if (e.key === 'Escape') { e.preventDefault(); close(); }
            else if (e.key === 'Tab') { e.preventDefault(); move(e.shiftKey ? -1 : 1); }
        });
    }

    function render() {
        var q = norm(inp.value), groups = {}, order = [];
        if (!q) {
            shown = recent().concat(items.filter(function (x) { return x.b > 0; }).map(function (x) { return Object.assign({}, x, { g: 'نیاز به رسیدگی' }); }))
                .concat(items.filter(function (x) { return x.g === 'کار سریع'; }).slice(0, 4))
                .concat(items.filter(function (x) { return x.g === 'صفحه‌های پنل'; }));
            var seen = {}; shown = shown.filter(function (x) { var k = x.g + x.h; if (seen[k]) return false; seen[k] = 1; return true; });
        } else {
            shown = items.map(function (it) { return { it: it, s: score(it, q) }; }).filter(function (x) { return x.s > 0; })
                .sort(function (a, b) { return b.s - a.s; }).map(function (x) { return x.it; }).slice(0, 10)
                .concat(lastQ === q ? remote : []);
        }
        shown.forEach(function (it) { if (!groups[it.g]) { groups[it.g] = []; order.push(it.g); } groups[it.g].push(it); });
        var html = '', n = 0;
        shown = [];
        order.forEach(function (g) {
            html += '<div class="cmdk-g">' + esc(g) + '</div>';
            groups[g].forEach(function (it) {
                shown.push(it);
                html += '<a class="cmdk-it" role="option" data-n="' + n + '" href="' + esc(it.h) + '"' + (it.x ? ' target="_blank" rel="noopener"' : '') + '>' +
                    '<span class="cmdk-ic">' + esc(it.i || '•') + '</span><span class="cmdk-tx"><b>' + mark(it.t, q) + '</b>' + (it.s ? '<small>' + esc(it.s) + '</small>' : '') + '</span>' +
                    (it.b ? '<em class="nav-badge">' + esc(String(it.b).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; })) + '</em>' : '') +
                    '<span class="cmdk-go" aria-hidden="true">↵</span></a>';
                n++;
            });
        });
        if (!n) {
            var busy = q && q.length > 1 && lastQ !== q;
            html = '<div class="cmdk-empty">' + (busy ? '<i class="cmdk-spin"></i> در حال جستجو…' : (q.length < 2 && q ? 'حداقل ۲ حرف بنویس' : 'چیزی پیدا نشد 🙂<small>کد سفارش، شماره موبایل، نام عضو یا عنوان خبر را امتحان کن</small>')) + '</div>';
        }
        list.innerHTML = html;
        if (sel >= n) sel = Math.max(0, n - 1);
        paint();
    }
    function paint() {
        var rows = list.querySelectorAll('.cmdk-it');
        for (var i = 0; i < rows.length; i++) {
            var on = i === sel; rows[i].classList.toggle('on', on); rows[i].setAttribute('aria-selected', on ? 'true' : 'false');
            if (on) { var r = rows[i], top = r.offsetTop, bot = top + r.offsetHeight; if (top < list.scrollTop + 28) list.scrollTop = top - 28; else if (bot > list.scrollTop + list.clientHeight) list.scrollTop = bot - list.clientHeight + 6; }
        }
    }
    function move(d) { if (!shown.length) return; sel = (sel + d + shown.length) % shown.length; paint(); }
    function go(n, tab) {
        var it = shown[n]; if (!it) return;
        remember(it);
        if (tab || it.x) window.open(it.h, '_blank', 'noopener'); else location.href = it.h;
        if (tab) close();
    }
    function fetchRemote() {
        clearTimeout(timer);
        var q = norm(inp.value);
        if (q.length < 2) { remote = []; lastQ = q; spin(false); return; }
        timer = setTimeout(function () {
            if (ctrl) try { ctrl.abort(); } catch (e) { }
            ctrl = window.AbortController ? new AbortController() : null;
            var f = new FormData(); f.append('action', 'cmd_search'); f.append('q', q); f.append('_csrf', A.csrf || '');
            spin(true);
            fetch(A.ajax, { method: 'POST', body: f, credentials: 'same-origin', signal: ctrl ? ctrl.signal : undefined })
                .then(function (r) { return r.json(); })
                .then(function (j) { if (norm(inp.value) !== q) return; remote = (j && j.items) || []; lastQ = q; spin(false); render(); })
                .catch(function (e) { if (e && e.name === 'AbortError') return; lastQ = q; remote = []; spin(false); render(); });
        }, 200);
    }
    function spin(on) { var s = box.querySelector('.cmdk-in .cmdk-spin'); if (s) s.hidden = !on; }

    var lastFocus = null;
    function open() {
        if (!box) build();
        if (!box.hidden) return inp.focus();
        lastFocus = document.activeElement;
        box.hidden = false; document.documentElement.classList.add('cmdk-on');
        inp.value = ''; sel = 0; remote = []; lastQ = ''; render();
        requestAnimationFrame(function () { box.classList.add('in'); inp.focus(); });
    }
    function close() {
        if (!box || box.hidden) return;
        box.classList.remove('in'); document.documentElement.classList.remove('cmdk-on');
        setTimeout(function () { box.hidden = true; }, 160);
        if (lastFocus && lastFocus.focus) try { lastFocus.focus(); } catch (e) { }
    }

    document.addEventListener('keydown', function (e) {
        var k = (e.key || '').toLowerCase();
        if ((e.ctrlKey || e.metaKey) && (k === 'k' || e.code === 'KeyK')) { e.preventDefault(); box && !box.hidden ? close() : open(); return; }
        if (k === '/' && !e.ctrlKey && !e.metaKey && !e.altKey) {
            var t = e.target, tag = t && t.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || (t && t.isContentEditable)) return;
            e.preventDefault(); open();
        }
    });
    var btn = document.getElementById('cmdBtn');
    if (btn) btn.addEventListener('click', open);
    window.NXCmd = { open: open, close: close };
})();
