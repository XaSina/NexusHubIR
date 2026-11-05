/* NEXUS | HUB — performance modes: a small side tab opens a panel that tests the device, shows how well each mode
   suits it and lets the visitor pick one. The mode is a class on <html> (set in <head> before anything is drawn):
     perf-ultra    everything
     perf-balanced animations stay, heavy GPU effects go          (+ perf-safe)
     perf-lite     only light motion, nothing moves while scrolling (+ perf-safe perf-calm)
     perf-off      no animation at all                            (+ perf-safe perf-calm)
   Stored per browser in localStorage (shared by the site, the panel and the login page). */
(function () {
    'use strict';
    var d = document.documentElement, LS = window.localStorage;
    var get = function (k) { try { return LS.getItem(k); } catch (e) { return null; } };
    var set = function (k, v) { try { LS.setItem(k, v); } catch (e) {} };
    var touch = window.matchMedia && window.matchMedia('(hover: none), (pointer: coarse), (max-width: 900px)').matches;
    var MODES = [
        ['auto', 'خودکار', 'بر اساس تست دستگاه شما انتخاب می‌شه', '✨'],
        ['ultra', 'کامل', 'همه‌ی انیمیشن‌ها و افکت‌ها', '🚀'],
        ['balanced', 'متعادل', 'انیمیشن‌ها هست، افکت‌های سنگین گرافیکی نه', '⚖️'],
        ['lite', 'سبک', 'فقط حرکت‌های خیلی سبک؛ موقع اسکرول هیچ چیز تکون نمی‌خوره', '🍃'],
        ['off', 'بدون انیمیشن', 'سریع‌ترین حالت، بدون هیچ حرکتی', '⏸️']
    ];
    var NAMES = { ultra: 'کامل', balanced: 'متعادل', lite: 'سبک', off: 'بدون انیمیشن' };
    var fa = function (s) { return String(s).replace(/\d/g, function (x) { return '۰۱۲۳۴۵۶۷۸۹'[x]; }); };

    /* ---------- device test ---------- */
    function gpuName() {
        try {
            var c = document.createElement('canvas'), g = c.getContext('webgl') || c.getContext('experimental-webgl');
            if (!g) return '';
            var ext = g.getExtension('WEBGL_debug_renderer_info');
            var n = ext ? g.getParameter(ext.UNMASKED_RENDERER_WEBGL) : g.getParameter(g.RENDERER);
            var lose = g.getExtension('WEBGL_lose_context'); if (lose) lose.loseContext();
            return String(n || '').replace(/\s*\(.*?\)\s*/g, ' ').replace(/ANGLE|Direct3D11|vs_5_0|ps_5_0|OpenGL ES|,/g, '').replace(/[()]/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 40);
        } catch (e) { return ''; }
    }
    function measureFps(ms) {
        return new Promise(function (res) {
            var ts = [], t0 = performance.now();
            (function f(t) { ts.push(t); if (t - t0 < ms) requestAnimationFrame(f); else {
                var dts = []; for (var i = 1; i < ts.length; i++) dts.push(ts[i] - ts[i - 1]);
                dts.sort(function (a, b) { return a - b; });
                var med = dts[Math.floor(dts.length / 2)] || 16.7, slow = dts.filter(function (x) { return x > 34; }).length / (dts.length || 1);
                res({ fps: Math.min(144, Math.round(1000 / med)), jank: slow });
            } })(t0);
        });
    }
    function scoreOf(info) {
        var s = 0;
        s += Math.min(info.cores, 8) / 8 * 22;
        s += Math.min(info.mem, 8) / 8 * 22;
        s += Math.min(info.fps, 60) / 60 * 26 - info.jank * 20;
        var g = info.gpu;
        if (/Apple|NVIDIA|GeForce|Radeon|AMD|Intel|Adreno.*\b(7[3-9]\d|8\d\d)\b|Immortalis|Mali-G7[1-9]\d|Xclipse 9/i.test(g)) s += 24;
        else if (/Adreno.*\b6[4-9]\d\b|Mali-G7[78]|Mali-G6[18]|Xclipse/i.test(g)) s += 16;
        else if (/Adreno|Mali|PowerVR|IMG|Vivante|SwiftShader|llvmpipe/i.test(g)) s += 6;
        else s += 12;
        if (info.touch) s -= 6;
        if (info.touch && info.dpr >= 3) s -= 4;
        if (info.saveData) s -= 10;
        return Math.max(5, Math.min(100, Math.round(s + 6)));
    }
    function levelFor(score, info) {
        if (info.reduce) return 'off';
        if (info.touch) return score >= 88 ? 'balanced' : score >= 30 ? 'lite' : 'off';     // phones never get the heaviest mode by themselves
        return score >= 62 ? 'ultra' : score >= 42 ? 'balanced' : score >= 25 ? 'lite' : 'off';
    }
    function fitOf(mode, score) {                     // how well a mode suits this device, in %
        var need = (touch ? { ultra: 95, balanced: 80, lite: 30, off: 0 } : { ultra: 62, balanced: 42, lite: 25, off: 0 })[mode];   // phone GPUs need more headroom (browsers report at most 8 GB of memory, whatever the phone has)
        return Math.max(5, Math.min(100, Math.round(100 - Math.max(0, need - score) * 1.6)));
    }
    function runTest() {
        var info = {
            cores: navigator.hardwareConcurrency || 4, mem: navigator.deviceMemory || (touch ? 3 : 8), dpr: window.devicePixelRatio || 1,
            touch: touch, gpu: gpuName(), saveData: !!(navigator.connection && navigator.connection.saveData),
            reduce: !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)
        };
        return measureFps(1200).then(function (r) {
            info.fps = r.fps; info.jank = r.jank;
            var score = scoreOf(info), level = levelFor(score, info);
            var saved = { score: score, level: level, info: info, at: Date.now() };
            set('nx_perf_test', JSON.stringify(saved)); set('nx_perf_auto', level);
            return saved;
        });
    }
    function lastTest() { try { return JSON.parse(get('nx_perf_test') || 'null'); } catch (e) { return null; } }
    function current() { return get('nx_perf') || 'auto'; }
    function active() { return d.getAttribute('data-perf') || 'ultra'; }

    // first visit: test quietly once the page has settled (the result is used from the next page on)
    if (!lastTest()) {
        var later = function () { runTest().then(function (t) { if (current() === 'auto' && t.level !== active()) apply('auto', true); }); };
        if (window.requestIdleCallback) requestIdleCallback(function () { setTimeout(later, 1500); }, { timeout: 6000 }); else setTimeout(later, 3500);
    }

    function apply(mode, quiet) {
        set('nx_perf', mode);
        var lv = mode === 'auto' ? (get('nx_perf_auto') || (touch ? 'lite' : 'ultra')) : mode;
        // CSS-only parts switch instantly; the rest (background canvas, tilt…) follows on the next page
        d.classList.remove('perf-ultra', 'perf-balanced', 'perf-lite', 'perf-off', 'perf-safe', 'perf-calm');
        d.classList.add('perf-' + lv);
        if (lv !== 'ultra') d.classList.add('perf-safe');
        if (lv === 'lite' || lv === 'off') d.classList.add('perf-calm');
        d.setAttribute('data-perf', lv);
        if (!quiet) location.reload();
    }

    /* ---------- UI ---------- */
    if (document.body.hasAttribute('data-no-perf')) return;
    var tab = document.createElement('button');
    tab.type = 'button'; tab.className = 'pf-tab'; tab.setAttribute('aria-label', 'عملکرد سایت'); tab.title = 'عملکرد سایت';
    tab.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 14l4-4"/><path d="M3.3 17a9 9 0 1 1 17.4 0"/><circle cx="12" cy="14" r="1.4" fill="currentColor"/></svg>';
    document.body.appendChild(tab);

    var box = null;
    function render(t) {
        var lv = active(), cur = current(), sc = t ? t.score : null;
        var ring = sc == null ? '' : '<svg viewBox="0 0 64 64"><circle class="pf-tr" cx="32" cy="32" r="27"/><circle class="pf-bar" cx="32" cy="32" r="27" style="stroke-dashoffset:' + (169.6 * (1 - sc / 100)).toFixed(1) + '"/></svg>';
        var i = t ? t.info : null;
        var rec = t ? t.level : (touch ? 'lite' : 'ultra');
        box.innerHTML = '<div class="pf-head"><b>⚡ عملکرد سایت</b><button type="button" class="pf-x" aria-label="بستن">✕</button></div>'
            + '<div class="pf-score" data-l="' + (sc == null ? 'mid' : sc >= 75 ? 'good' : sc >= 45 ? 'mid' : 'low') + '">' + '<div class="pf-ring">' + ring + '<span>' + (sc == null ? '…' : fa(sc) + '٪') + '</span></div>'
            + '<div><b>' + (sc == null ? 'در حال تست دستگاه…' : 'توان دستگاه شما') + '</b><small>' + (t ? 'حالت پیشنهادی برای دستگاه شما: «' + NAMES[rec] + '»' : 'چند ثانیه صبر کنید') + '</small>'
            + (i ? '<small class="pf-info" dir="rtl">' + fa(i.cores) + ' هسته · ' + (i.mem >= 8 ? '۸GB+ حافظه' : fa(i.mem) + 'GB حافظه') + ' · ' + fa(i.fps) + ' فریم' + (i.gpu ? ' · <span dir="ltr">' + i.gpu.replace(/</g, '') + '</span>' : '') + '</small>' : '') + '</div></div>'
            + '<div class="pf-list">' + MODES.map(function (m) {
                var k = m[0], fit = sc == null || k === 'auto' ? null : fitOf(k, sc);
                return '<button type="button" class="pf-opt' + (cur === k ? ' on' : '') + '" data-m="' + k + '"><span class="pf-ic">' + m[3] + '</span><span class="pf-t"><b>' + m[1]
                    + (k === 'auto' ? ' <em>' + NAMES[rec] + '</em>' : (k === rec ? ' <em>پیشنهادی</em>' : '')) + '</b><small>' + m[2] + '</small>'
                    + (fit == null ? '' : '<i class="pf-fit" style="--f:' + fit + '%" data-l="' + (fit >= 80 ? 'good' : fit >= 50 ? 'mid' : 'low') + '"><u></u></i>') + '</span>'
                    + (fit == null ? '' : '<span class="pf-pc">' + fa(fit) + '٪</span>') + '</button>';
            }).join('') + '</div>'
            + '<div class="pf-foot"><button type="button" class="pf-re">🔄 تست دوباره</button><small>حالت فعلی: «' + NAMES[lv] + '»</small></div>';
    }
    function open() {
        if (box) { close(); return; }
        box = document.createElement('div'); box.className = 'pf-box'; box.setAttribute('role', 'dialog');
        document.body.appendChild(box); render(lastTest());
        requestAnimationFrame(function () { box && box.classList.add('in'); });
        if (!lastTest()) runTest().then(function (t) { if (box) render(t); });
        box.addEventListener('click', function (e) {
            if (e.target.closest('.pf-x')) return close();
            var o = e.target.closest('.pf-opt'); if (o) return apply(o.getAttribute('data-m'));
            if (e.target.closest('.pf-re')) { render(null); runTest().then(function (t) { if (box) render(t); }); }
        });
    }
    function close() { if (!box) return; var b = box; box = null; b.classList.remove('in'); setTimeout(function () { b.remove(); }, 250); }
    tab.addEventListener('click', open);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    document.addEventListener('click', function (e) { if (box && !box.contains(e.target) && !tab.contains(e.target)) close(); });
})();
