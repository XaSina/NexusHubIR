/* ==========================================================================
   NEXUS | HUB — Front-end interactions
   ========================================================================== */
(function () {
    'use strict';
    var NX = window.NX || {};
    var $ = function (s, c) { return (c || document).querySelector(s); };
    var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var finePointer = window.matchMedia && window.matchMedia('(pointer: fine)').matches;
    var faDigits = function (s) { return String(s).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; }); };
    var store = {
        get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
        set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} },
        sget: function (k) { try { return sessionStorage.getItem(k); } catch (e) { return null; } },
        sset: function (k, v) { try { sessionStorage.setItem(k, v); } catch (e) {} }
    };

    if (NX.reveal && !reduce && 'IntersectionObserver' in window) document.documentElement.classList.add('js-reveal');

    /* ---------- Toast ---------- */
    function toast(msg) {
        var wrap = $('#toasts');
        if (!wrap) return;
        var t = document.createElement('div');
        t.className = 'toast';
        t.textContent = msg;
        wrap.appendChild(t);
        setTimeout(function () { t.classList.add('out'); setTimeout(function () { t.remove(); }, 400); }, 2600);
    }
    window.nxToast = toast;

    function api(path, data) {
        var fd = new FormData();
        Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
        fd.append('_csrf', NX.csrf);
        return fetch((NX.base || '') + '/' + path, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': NX.csrf } })
            .then(function (r) { return r.json(); });
    }
    window.nxApi = api;

    /* ---------- Cinematic page warp (login / account links) ---------- */
    /* Warp hand-off helpers.
       Leaving: restart the loader loops at 0, remember when the warp started, start loading the next page while the
       iris is still closing (the old page stays on screen until the new one paints).
       Arriving: keep the cover until fonts + logo are ready (and at least ~0.9s since the warp began), then exit. */
    function warpRestart(w) { w.classList.add('rs'); void w.offsetWidth; w.classList.remove('rs'); document.documentElement.style.removeProperty('--wt'); }
    function warpExit() {
        var root = document.documentElement, t = parseFloat(root.style.getPropertyValue('--wt')) || 0;
        var hold = Math.max(0, 900 + t);                 // --wt is negative: time already spent covered
        var ready = [];
        if (document.fonts && document.fonts.ready) ready.push(document.fonts.ready);
        var logo = document.querySelector('.nx-warp-core img');
        if (logo && logo.decode) ready.push(logo.decode().catch(function () {}));
        ready.push(new Promise(function (r) { setTimeout(r, hold); }));
        return Promise.race([Promise.all(ready), new Promise(function (r) { setTimeout(r, 1600); })]).then(function () {
            return new Promise(function (res) {
                requestAnimationFrame(function () { requestAnimationFrame(function () {
                    root.classList.add('warp-out'); root.classList.remove('warp-in');
                    setTimeout(function () { root.classList.remove('warp-out'); root.style.removeProperty('--wt'); }, 1300);
                    res();
                }); });
            });
        });
    }
    var warpPrefetched = {};
    function warpPrefetch(a) {
        if (!a || !a.href || warpPrefetched[a.href] || a.origin !== location.origin) return;
        warpPrefetched[a.href] = 1;
        var l = document.createElement('link'); l.rel = 'prefetch'; l.href = a.href; document.head.appendChild(l);
    }
    ['pointerenter', 'touchstart', 'focusin'].forEach(function (ev) {
        document.addEventListener(ev, function (e) { var a = e.target.closest && e.target.closest('a[data-warp]'); if (a) warpPrefetch(a); }, { passive: true, capture: true });
    });
    var warp = $('#nxWarp'), warpIn = document.documentElement.classList.contains('warp-in');
    function warpTo(href, x, y) {
        if (!warp || reduce) { location.href = href; return; }
        warp.style.setProperty('--wx', (x == null ? window.innerWidth / 2 : x) + 'px');
        warp.style.setProperty('--wy', (y == null ? window.innerHeight / 2 : y) + 'px');
        warpRestart(warp);
        warp.classList.add('cover');
        store.sset('nx_warp', String(Date.now()));
        setTimeout(function () { location.href = href; }, 600);
    }
    window.nxWarpTo = warpTo;
    document.addEventListener('click', function (e) {
        var a = e.target.closest('a[data-warp]');
        if (!a || e.ctrlKey || e.metaKey || e.shiftKey || e.button) return;
        e.preventDefault();
        warpTo(a.href, e.clientX || null, e.clientY || null);
    });
    window.addEventListener('pageshow', function (e) { if (e.persisted && warp) warp.classList.remove('cover'); });
    if (warpIn) {
        try { sessionStorage.removeItem('nx_warp'); } catch (e) {}
        if ($('#preloader')) $('#preloader').remove();
        warpExit();
    }

    /* ---------- Text decode effect ---------- */
    $$('[data-scramble]').forEach(function (el) {
        if (reduce) return;
        var final = el.dataset.scramble, chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ0123456789#$%&@', start = null, dur = 1400;
        (function step(t) {
            if (!start) start = t;
            var p = Math.min(1, (t - start) / dur), n = Math.floor(final.length * p), out = '';
            for (var i = 0; i < final.length; i++) out += i < n || final[i] === '-' || final[i] === ' ' ? final[i] : chars[Math.floor(Math.random() * chars.length)];
            el.textContent = out;
            if (p < 1) requestAnimationFrame(step);
        })(performance.now());
    });

    /* ---------- Preloader ---------- */
    var pl = $('#preloader');
    function hidePreloader() { if (pl && !pl.classList.contains('done')) { pl.classList.add('done'); setTimeout(function () { pl.remove(); }, 700); } }
    if (pl) {
        window.addEventListener('load', function () { setTimeout(hidePreloader, 350); });
        setTimeout(hidePreloader, 2800);
    }

    /* ---------- Reveal ---------- */
    if (document.documentElement.classList.contains('js-reveal')) {
        /* Items that enter together are staggered in reading order (top to bottom, right to left);
           once played, the reveal styles are dropped so hover effects are instant again. */
        var io = new IntersectionObserver(function (entries) {
            var batch = entries.filter(function (en) { return en.isIntersecting; });
            batch.sort(function (a, b) { return (a.boundingClientRect.top - b.boundingClientRect.top) || (b.boundingClientRect.left - a.boundingClientRect.left); });
            batch.forEach(function (en, i) {
                var el = en.target, d = Math.min(i, 6) * 70;
                io.unobserve(el);
                el.style.setProperty('--rd', d + 'ms');
                el.classList.add('in');
                setTimeout(function () { el.removeAttribute('data-reveal'); el.classList.remove('in'); el.style.removeProperty('--rd'); }, 1500 + d);
            });
        }, { rootMargin: '0px 0px -40px 0px', threshold: 0 });
        $$('[data-reveal]').forEach(function (el) { io.observe(el); });
    }

    /* ---------- Background canvas: particles · matrix · stars · waves · bokeh · fireflies · embers · rain ----------
       Glows are pre-rendered once into small sprites (drawImage is cheap); phones run at 30fps, low-res, paused while scrolling. */
    var cv = $('#bgCanvas');
    if (cv && !reduce) {
        var ctx = cv.getContext('2d');
        var lite = window.matchMedia('(hover: none), (max-width: 900px)').matches;
        var W, H, dpr = lite ? 1 : Math.min(window.devicePixelRatio || 1, 2), parts = [], cols = [], running = true, scrolling = false, scrollT, tick = 0;
        var hexToRgb = function (h) { h = h.replace('#', ''); return [parseInt(h.substr(0, 2), 16), parseInt(h.substr(2, 2), 16), parseInt(h.substr(4, 2), 16)]; };
        var pc = hexToRgb(NX.primary || '#ff1f3d'), sc = hexToRgb(NX.secondary || '#ff7a3d');
        var rgba = function (c, a) { return 'rgba(' + c[0] + ',' + c[1] + ',' + c[2] + ',' + a + ')'; };
        var mouse = { x: -999, y: -999 }, mode = NX.bg, R = Math.random;
        var sprite = function (c, r, soft) {   // radial glow drawn once
            var o = document.createElement('canvas'), s2 = r * 2; o.width = o.height = s2;
            var g = o.getContext('2d'), gr = g.createRadialGradient(r, r, 0, r, r, r);
            gr.addColorStop(0, rgba(c, soft ? .55 : 1)); gr.addColorStop(soft ? .5 : .25, rgba(c, soft ? .22 : .5)); gr.addColorStop(1, rgba(c, 0));
            g.fillStyle = gr; g.fillRect(0, 0, s2, s2); return o;
        };
        var spP, spS, spW;
        function size() {
            W = cv.clientWidth; H = cv.clientHeight;
            cv.width = W * dpr; cv.height = H * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            var area = W * H, k = lite ? .55 : 1, i;
            parts = []; cols = [];
            if (mode === 'matrix') { for (i = 0; i < Math.ceil(W / 18); i++) cols.push(R() * -H); }
            else if (mode === 'stars') { for (i = 0; i < Math.min(lite ? 160 : 380, area / 3500); i++) parts.push({ x: (R() - .5) * W, y: (R() - .5) * H, z: R() * W, c: R() > .75 ? pc : (R() > .5 ? sc : [255, 255, 255]) }); }
            else if (mode === 'bokeh') { spP = sprite(pc, 120, true); spS = sprite(sc, 120, true); for (i = 0; i < Math.round(18 * k); i++) parts.push({ x: R() * W, y: R() * H, r: 40 + R() * 110, vx: (R() - .5) * .25, vy: (R() - .5) * .25, a: .25 + R() * .45, s: R() > .5 }); }
            else if (mode === 'fireflies' || mode === 'embers') {
                spP = sprite(pc, 24); spS = sprite(sc, 24); spW = sprite([255, 236, 180], 12);
                for (i = 0; i < Math.round((mode === 'embers' ? 70 : 55) * k); i++) parts.push(newMote(true));
            }
            else if (mode === 'rain') { for (i = 0; i < Math.round(Math.min(220, area / 6000) * k); i++) parts.push({ x: R() * W * 1.2, y: R() * H, l: 10 + R() * 26, v: 6 + R() * 9, c: R() > .4 ? pc : sc, a: .15 + R() * .5 }); }
            else if (mode === 'waves') { /* computed per frame */ }
            else { var n = Math.min(lite ? 34 : 90, Math.floor(area / (lite ? 20000 : 16000))); for (i = 0; i < n; i++) parts.push({ x: R() * W, y: R() * H, vx: (R() - .5) * .35, vy: (R() - .5) * .35, r: R() * 1.8 + .6, c: R() > .7 ? sc : pc }); }
        }
        function newMote(anyY) {
            var e = mode === 'embers';
            return { x: R() * W, y: anyY ? R() * H : (e ? H + 20 : R() * H), vx: (R() - .5) * (e ? .6 : .3), vy: e ? -(.6 + R() * 1.6) : (R() - .5) * .3, ph: R() * 6.28, sp: .01 + R() * .03, r: e ? 6 + R() * 9 : 7 + R() * 10, s: R() > .55, life: R() };
        }
        var chars = 'NEXUS01アイウエオカキクケコ<>/{}#$%&';
        function drawMatrix() {
            ctx.fillStyle = 'rgba(' + (NX.light ? '238,240,246' : '0,0,0') + ',0.12)'; ctx.fillRect(0, 0, W, H);
            ctx.font = '15px monospace';
            for (var i = 0; i < cols.length; i++) {
                ctx.fillStyle = rgba(pc, R() * .5 + .3); ctx.fillText(chars[Math.floor(R() * chars.length)], i * 18, cols[i]);
                cols[i] = cols[i] > H + R() * 1000 ? 0 : cols[i] + 18;
            }
        }
        function drawParticles() {
            ctx.clearRect(0, 0, W, H);
            for (var i = 0; i < parts.length; i++) {
                var p = parts[i]; p.x += p.vx; p.y += p.vy;
                if (p.x < 0 || p.x > W) p.vx *= -1; if (p.y < 0 || p.y > H) p.vy *= -1;
                var dxm = p.x - mouse.x, dym = p.y - mouse.y; if (dxm * dxm + dym * dym < 14000) { p.x += dxm * .012; p.y += dym * .012; }
                ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, 6.283); ctx.fillStyle = rgba(p.c, .85); ctx.fill();
                for (var j = i + 1; j < parts.length; j++) {
                    var q = parts[j], dx = p.x - q.x, dy = p.y - q.y, d = dx * dx + dy * dy;
                    if (d < 16000) { ctx.strokeStyle = rgba(pc, 0.16 * (1 - d / 16000)); ctx.lineWidth = 1; ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(q.x, q.y); ctx.stroke(); }
                }
            }
        }
        function drawStars() {
            ctx.clearRect(0, 0, W, H);   // streaks already show the motion; keeps the theme glow visible behind
            var cx = W / 2 + (mouse.x > 0 ? (mouse.x - W / 2) * .05 : 0), cy = H / 2 + (mouse.y > 0 ? (mouse.y - H / 2) * .05 : 0), sp = lite ? 4 : 3;
            for (var i = 0; i < parts.length; i++) {
                var p = parts[i], pz = p.z; p.z -= sp;
                if (p.z < 1) { p.x = (R() - .5) * W; p.y = (R() - .5) * H; p.z = W; continue; }
                var x1 = cx + p.x / pz * W * .5, y1 = cy + p.y / pz * H * .5, x2 = cx + p.x / p.z * W * .5, y2 = cy + p.y / p.z * H * .5;
                if (x2 < 0 || x2 > W || y2 < 0 || y2 > H) { p.z = W; continue; }
                var a = Math.min(1, (1 - p.z / W) * 1.4);
                ctx.strokeStyle = rgba(p.c, a); ctx.lineWidth = Math.max(.5, (1 - p.z / W) * 2.4);
                ctx.beginPath(); ctx.moveTo(x1, y1); ctx.lineTo(x2, y2); ctx.stroke();
            }
        }
        function drawWaves(t) {
            ctx.clearRect(0, 0, W, H);
            var lines = lite ? 4 : 6, step = lite ? 22 : 14;
            for (var l = 0; l < lines; l++) {
                var c = l % 2 ? sc : pc, amp = 26 + l * 12, base = H * (.55 + l * .06), f = .0035 + l * .0007, ph = t * (.00045 + l * .00012) + l;
                ctx.beginPath();
                for (var x = -step; x <= W + step; x += step) {
                    var y = base + Math.sin(x * f + ph) * amp + Math.sin(x * f * .5 - ph * 1.3) * amp * .6;
                    if (x === -step) ctx.moveTo(x, y); else ctx.lineTo(x, y);
                }
                ctx.strokeStyle = rgba(c, .32 - l * .035); ctx.lineWidth = 1.6; ctx.stroke();
            }
        }
        function drawBokeh() {
            ctx.clearRect(0, 0, W, H);
            for (var i = 0; i < parts.length; i++) {
                var p = parts[i]; p.x += p.vx; p.y += p.vy;
                if (p.x < -p.r) p.x = W + p.r; if (p.x > W + p.r) p.x = -p.r; if (p.y < -p.r) p.y = H + p.r; if (p.y > H + p.r) p.y = -p.r;
                ctx.globalAlpha = p.a; ctx.drawImage(p.s ? spS : spP, p.x - p.r, p.y - p.r, p.r * 2, p.r * 2);
            }
            ctx.globalAlpha = 1;
        }
        function drawMotes() {
            ctx.clearRect(0, 0, W, H);
            var e = mode === 'embers';
            for (var i = 0; i < parts.length; i++) {
                var p = parts[i]; p.ph += p.sp;
                p.x += p.vx + Math.sin(p.ph) * (e ? .5 : .35); p.y += p.vy + (e ? 0 : Math.cos(p.ph * .8) * .3);
                if (e) { p.life += .004; if (p.y < -20 || p.life > 1.4) { parts[i] = newMote(false); parts[i].life = 0; continue; } }
                else { if (p.x < -20) p.x = W + 20; if (p.x > W + 20) p.x = -20; if (p.y < -20) p.y = H + 20; if (p.y > H + 20) p.y = -20; }
                var a = e ? Math.max(0, 1 - p.life * .75) : (.35 + Math.sin(p.ph * 2.2) * .35 + .3);
                ctx.globalAlpha = Math.max(0, Math.min(1, a));
                ctx.drawImage(p.s ? spS : spP, p.x - p.r, p.y - p.r, p.r * 2, p.r * 2);
                ctx.drawImage(spW, p.x - p.r * .3, p.y - p.r * .3, p.r * .6, p.r * .6);
            }
            ctx.globalAlpha = 1;
        }
        function drawRain() {
            ctx.clearRect(0, 0, W, H);
            ctx.lineWidth = 1.2;
            for (var i = 0; i < parts.length; i++) {
                var p = parts[i]; p.y += p.v; p.x -= p.v * .18;
                if (p.y > H + 30) { p.y = -30; p.x = R() * W * 1.2; }
                ctx.strokeStyle = rgba(p.c, p.a); ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(p.x + p.l * .18, p.y - p.l); ctx.stroke();
            }
        }
        var draw = { matrix: drawMatrix, stars: drawStars, waves: drawWaves, bokeh: drawBokeh, fireflies: drawMotes, embers: drawMotes, rain: drawRain }[mode] || drawParticles;
        var last = 0, frame = mode === 'matrix' ? 55 : (lite ? 33 : 0);
        var looping = false;                             // guard: never run two loops at once (tab switches used to start a second one)
        function kick() { if (!looping && running && !scrolling) { looping = true; requestAnimationFrame(loop); } }
        function loop(t) {
            if (!running || scrolling) { looping = false; return; }   // fully idle while hidden or while a phone scrolls
            requestAnimationFrame(loop);
            if (frame && t - last < frame) return;
            last = t; draw(t);
        }
        size();
        window.addEventListener('resize', function () { clearTimeout(cv._t); cv._t = setTimeout(size, 200); });
        window.addEventListener('mousemove', function (e) { mouse.x = e.clientX; mouse.y = e.clientY; }, { passive: true });
        document.addEventListener('visibilitychange', function () { running = !document.hidden; kick(); });
        if (lite) window.addEventListener('scroll', function () { scrolling = true; clearTimeout(scrollT); scrollT = setTimeout(function () { scrolling = false; kick(); }, 160); }, { passive: true });
        kick();
    }

    /* ---------- Cursor glow ---------- */
    var glow = $('#cursorGlow');
    if (glow && finePointer && !reduce) {
        var gx = 0, gy = 0, tx = 0, ty = 0;
        window.addEventListener('mousemove', function (e) { tx = e.clientX; ty = e.clientY; glow.classList.add('on'); }, { passive: true });
        document.addEventListener('mouseleave', function () { glow.classList.remove('on'); });
        (function follow() { gx += (tx - gx) * .12; gy += (ty - gy) * .12; glow.style.transform = 'translate(' + (gx - 210) + 'px,' + (gy - 210) + 'px)'; requestAnimationFrame(follow); })();
    }

    /* ---------- Tilt + shine ---------- */
    if (finePointer && !reduce) {
        $$('.tilt').forEach(function (el) {
            el.addEventListener('mousemove', function (e) {
                var r = el.getBoundingClientRect(), x = (e.clientX - r.left) / r.width, y = (e.clientY - r.top) / r.height;
                el.style.setProperty('--mx', (x * 100) + '%'); el.style.setProperty('--my', (y * 100) + '%');
                if (NX.tilt) el.style.transform = 'perspective(900px) rotateX(' + ((.5 - y) * 7) + 'deg) rotateY(' + ((x - .5) * 7) + 'deg) translateY(-6px)';
            });
            el.addEventListener('mouseleave', function () { el.style.transform = ''; });
        });
    }
    $$('.nxp-theme-spotlight').forEach(function (el) {
        el.addEventListener('mousemove', function (e) {
            var r = el.getBoundingClientRect();
            el.style.setProperty('--mx', (e.clientX - r.left) + 'px'); el.style.setProperty('--my', (e.clientY - r.top) + 'px');
        });
    });

    /* ---------- Scroll: header, progress, to-top, parallax ---------- */
    var header = $('#siteHeader'), prog = $('#readProgress'), toTop = $('#toTop'), postBody = $('.post-body'), par = $('.parallax .cover');
    var ring = toTop ? $('circle', toTop) : null;
    function onScroll() {
        var y = window.scrollY, h = document.documentElement.scrollHeight - window.innerHeight;
        if (header) header.classList.toggle('scrolled', y > 30);
        var ratio = h > 0 ? y / h : 0;
        if (postBody && prog) {
            var r = postBody.getBoundingClientRect();
            var total = r.height - window.innerHeight * .5;
            ratio = Math.max(0, Math.min(1, (-r.top + window.innerHeight * .3) / (total > 0 ? total : 1)));
        }
        if (prog) prog.style.width = (ratio * 100) + '%';
        if (toTop) { toTop.classList.toggle('show', y > 500); if (ring) ring.style.setProperty('--off', 132 - 132 * (h > 0 ? y / h : 0)); }
        if (par && !reduce && y < 900) par.style.transform = 'translateY(' + (y * .18) + 'px) scale(1.05)';
    }
    // at most one scroll update per frame (was queuing a callback on every scroll event)
    var scrollTicking = false;
    window.addEventListener('scroll', function () {
        if (scrollTicking) return;
        scrollTicking = true;
        requestAnimationFrame(function () { scrollTicking = false; onScroll(); });
    }, { passive: true });
    onScroll();
    if (toTop) toTop.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });

    /* ---------- Live clock ---------- */
    var clock = $('#liveClock');
    if (clock) setInterval(function () {
        var d = new Date();
        // ساعت تهران
        var utc = d.getTime() + d.getTimezoneOffset() * 60000, t = new Date(utc + 3.5 * 3600000);
        clock.textContent = faDigits(('0' + t.getHours()).slice(-2) + ':' + ('0' + t.getMinutes()).slice(-2));
    }, 15000);

    /* ---------- Announcement ---------- */
    var ann = $('#announce');
    if (ann) {
        var key = 'nx_ann_' + ann.dataset.hash;
        if (store.get(key)) ann.remove();
        else $('.announce-close', ann).addEventListener('click', function () { ann.style.transition = '.4s'; ann.style.opacity = 0; ann.style.maxHeight = '0px'; store.set(key, 1); setTimeout(function () { ann.remove(); }, 400); });
    }

    /* ---------- Search overlay & drawer ---------- */
    var so = $('#searchOverlay'), drawer = $('#drawer'), root = document.documentElement;
    var lock = function () { root.classList.toggle('nx-lock', !!((so && !so.hidden) || (drawer && drawer.classList.contains('open')))); };
    function openSearch() { if (!so) return; so.hidden = false; lock(); setTimeout(function () { $('input', so).focus(); }, 50); }
    function closeSearch() { if (!so) return; so.hidden = true; lock(); }
    if ($('#searchOpen')) $('#searchOpen').addEventListener('click', openSearch);
    if ($('#searchClose')) $('#searchClose').addEventListener('click', closeSearch);
    if (so) so.addEventListener('click', function (e) { if (e.target === so) closeSearch(); });
    function openDrawer() {
        if (!drawer) return;
        drawer.removeAttribute('inert'); drawer.setAttribute('aria-hidden', 'false');
        requestAnimationFrame(function () { drawer.classList.add('open'); lock(); });
    }
    function closeDrawer() {
        if (!drawer || !drawer.classList.contains('open')) return;
        drawer.classList.remove('open'); drawer.setAttribute('aria-hidden', 'true'); drawer.setAttribute('inert', '');
        if (drawer.contains(document.activeElement)) document.activeElement.blur();
        lock();
    }
    if ($('#menuOpen') && drawer) {
        $('#menuOpen').addEventListener('click', openDrawer);
        $$('[data-close]', drawer).forEach(function (b) { b.addEventListener('click', closeDrawer); });
        // back/forward cache: never come back to a page with the menu stuck open
        window.addEventListener('pageshow', function (e) {
            if (!e.persisted) return;
            drawer.classList.add('no-anim'); closeDrawer();
            setTimeout(function () { drawer.classList.remove('no-anim'); }, 60);
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeSearch(); closeDrawer(); }
        if (e.key === '/' && !/input|textarea/i.test(document.activeElement.tagName) && !document.activeElement.isContentEditable) { e.preventDefault(); openSearch(); }
    });

    /* ---------- Hero slider ---------- */
    var slider = $('#heroSlider');
    if (slider) {
        var slides = $$('.slide', slider), dots = $$('.slider-dots button', slider), cur = 0, timer;
        var go = function (i) {
            cur = (i + slides.length) % slides.length;
            slides.forEach(function (s, k) { s.classList.toggle('active', k === cur); });
            dots.forEach(function (d, k) { d.classList.remove('active'); if (k === cur) { void d.offsetWidth; d.classList.add('active'); } });
        };
        var play = function () { clearInterval(timer); timer = setInterval(function () { go(cur + 1); }, 7000); };
        $$('[data-dir]', slider).forEach(function (b) { b.addEventListener('click', function () { go(cur + parseInt(b.dataset.dir, 10)); play(); }); });
        dots.forEach(function (d) { d.addEventListener('click', function () { go(parseInt(d.dataset.go, 10)); play(); }); });
        var sx = null;
        slider.addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; }, { passive: true });
        slider.addEventListener('touchend', function (e) {
            if (sx === null) return; var dx = e.changedTouches[0].clientX - sx;
            if (Math.abs(dx) > 50) { go(cur + (dx > 0 ? 1 : -1)); play(); } sx = null;
        });
        play();
    }

    /* ---------- Horizontal scroll buttons ---------- */
    $$('[data-scroll]').forEach(function (b) {
        b.addEventListener('click', function () {
            var block = b.closest('.cat-block'), sc = block && $('.h-scroll', block);
            if (!sc) return;
            var amount = sc.clientWidth * .8 * (parseInt(b.dataset.scroll, 10) > 0 ? -1 : 1);
            sc.scrollBy({ left: amount, behavior: 'smooth' });
        });
    });

    /* ---------- Reactions ---------- */
    var reactBox = $('#reactions');
    if (reactBox) {
        reactBox.addEventListener('click', function (e) {
            var btn = e.target.closest('.react');
            if (!btn || reactBox._busy) return;
            reactBox._busy = true;
            btn.classList.remove('pop'); void btn.offsetWidth; btn.classList.add('pop');
            for (var i = 0; i < 6; i++) {
                var s = document.createElement('span');
                s.className = 're-burst'; s.textContent = btn.dataset.emoji;
                s.style.setProperty('--tx', (Math.random() * 120 - 60) + 'px'); s.style.setProperty('--ty', (-40 - Math.random() * 70) + 'px');
                btn.appendChild(s); setTimeout(function (el) { el.remove(); }, 1000, s);
            }
            api('api/react', { post_id: reactBox.dataset.post, emoji: btn.dataset.emoji }).then(function (j) {
                reactBox._busy = false;
                if (!j.ok) { toast(j.error || 'خطا'); return; }
                $$('.react', reactBox).forEach(function (b) {
                    var c = j.counts[b.dataset.emoji] || 0;
                    $('.re-count', b).textContent = c ? faDigits(c) : '';
                    b.classList.toggle('mine', j.mine === b.dataset.emoji);
                });
            }).catch(function () { reactBox._busy = false; toast('خطا در ارتباط'); });
        });
    }

    /* ---------- Comments ---------- */
    var cf = $('#commentForm');
    if (cf) {
        var replyBox = $('#replyTo');
        $$('[data-reply]').forEach(function (b) {
            b.addEventListener('click', function () {
                cf.parent_id.value = b.dataset.reply;
                $('b', replyBox).textContent = b.dataset.name;
                replyBox.hidden = false;
                cf.scrollIntoView({ behavior: 'smooth', block: 'center' });
                setTimeout(function () { cf.body.focus(); }, 400);
            });
        });
        $('button', replyBox).addEventListener('click', function () { cf.parent_id.value = 0; replyBox.hidden = true; });
        cf.addEventListener('submit', function (e) {
            e.preventDefault();
            var msg = $('.form-msg', cf), btn = $('button[type=submit]', cf), data = {};
            $$('input,textarea', cf).forEach(function (i) { if (i.name) data[i.name] = i.value; });
            btn.disabled = true; msg.className = 'form-msg'; msg.textContent = 'در حال ارسال...';
            api('api/comment', data).then(function (j) {
                btn.disabled = false;
                msg.className = 'form-msg ' + (j.ok ? 'ok' : 'err');
                msg.textContent = j.ok ? j.message : (j.error || 'خطا');
                if (j.ok) { cf.body.value = ''; cf.parent_id.value = 0; replyBox.hidden = true; if (j.approved) setTimeout(function () { location.reload(); }, 1200); }
            }).catch(function () { btn.disabled = false; msg.className = 'form-msg err'; msg.textContent = 'خطا در ارتباط با سرور'; });
        });
    }

    /* ---------- Copy / Share ---------- */
    $$('[data-copy]').forEach(function (b) {
        b.addEventListener('click', function () {
            var txt = b.dataset.copy;
            var done = function () {
                toast(b.dataset.copyMsg || (/^https?:/.test(txt) ? 'لینک کپی شد ✅' : 'کپی شد ✅'));
                b.classList.remove('copied'); void b.offsetWidth; b.classList.add('copied');
                setTimeout(function () { b.classList.remove('copied'); }, 1600);
            };
            var legacy = function () {
                var ta = document.createElement('textarea');
                ta.value = txt; ta.setAttribute('readonly', ''); ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0';
                document.body.appendChild(ta); ta.select();
                var ok = false; try { ok = document.execCommand('copy'); } catch (e) {}
                ta.remove();
                if (ok) done(); else prompt('کپی کنید:', txt);
            };
            if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(txt).then(done, legacy);
            else legacy();
        });
    });
    $$('[data-share]').forEach(function (b) {
        if (!navigator.share) { b.remove(); return; }
        b.addEventListener('click', function () { navigator.share({ title: b.dataset.title, url: b.dataset.url }).catch(function () {}); });
    });

    /* ---------- Breaking-news ticker: measure so each pass starts and ends fully off-screen ---------- */
    var tk = $('.ticker-move');
    if (tk) {
        var setTk = function () {
            var W = tk.parentNode.clientWidth, C = tk.scrollWidth;       // RTL: the text sits at the right edge
            tk.style.setProperty('--tk-from', (-W) + 'px');              // its right edge just past the left side
            tk.style.setProperty('--tk-to', C + 'px');                   // its left edge just past the right side
        };
        setTk();
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(setTk);
        window.addEventListener('resize', function () { clearTimeout(tk._t); tk._t = setTimeout(setTk, 200); });
    }

    /* ---------- News video player (custom controls over a native <video>) ---------- */
    var players = [];
    $$('.nx-player').forEach(function (fig) {
        var v = $('video', fig), stage = $('.np-stage', fig);
        if (!v) return;
        v.removeAttribute('controls');
        var fmt = function (t) { t = Math.max(0, Math.floor(t || 0)); var h = Math.floor(t / 3600), m = Math.floor(t % 3600 / 60), s = t % 60; return (h ? h + ':' + ('0' + m).slice(-2) : m) + ':' + ('0' + s).slice(-2); };
        var I = {
            play: '<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>',
            pause: '<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M7 5h4v14H7zM13 5h4v14h-4z"/></svg>',
            vol: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 9v6h4l5 4V5L8 9H4Z" fill="currentColor"/><path d="M16 8.5a5 5 0 0 1 0 7M18.5 6a8.5 8.5 0 0 1 0 12"/></svg>',
            mute: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 9v6h4l5 4V5L8 9H4Z" fill="currentColor"/><path d="m17 9 5 6M22 9l-5 6"/></svg>',
            fs: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>',
            pip: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="3"/><rect x="12" y="11" width="8" height="6" rx="1.5" fill="currentColor"/></svg>'
        };
        var ui = document.createElement('div');
        ui.className = 'np-ui';
        ui.innerHTML = '<button type="button" class="np-big" aria-label="پخش">' + I.play + '</button><span class="np-spin"></span>'
            + '<div class="np-bar" dir="ltr">'
            + '<div class="np-progress" role="slider" aria-label="زمان" tabindex="0"><i class="np-buf"></i><i class="np-played"></i><b class="np-thumb"></b><span class="np-tip"></span></div>'
            + '<div class="np-row"><button type="button" class="np-btn np-pp" aria-label="پخش/توقف">' + I.play + '</button>'
            + '<span class="np-time"><b class="np-cur">0:00</b> / <span class="np-dur">0:00</span></span>'
            + '<span class="np-grow"></span>'
            + '<button type="button" class="np-btn np-mute" aria-label="صدا">' + I.vol + '</button><input type="range" class="np-vol" min="0" max="1" step="0.05" value="1" aria-label="بلندی صدا">'
            + '<button type="button" class="np-btn np-speed" aria-label="سرعت">1×</button>'
            + (document.pictureInPictureEnabled ? '<button type="button" class="np-btn np-pip" aria-label="تصویر در تصویر">' + I.pip + '</button>' : '')
            + '<button type="button" class="np-btn np-fs" aria-label="تمام‌صفحه">' + I.fs + '</button></div></div>';
        stage.appendChild(ui);
        var big = $('.np-big', ui), pp = $('.np-pp', ui), prog = $('.np-progress', ui), played = $('.np-played', ui), buf = $('.np-buf', ui), thumb = $('.np-thumb', ui),
            cur = $('.np-cur', ui), dur = $('.np-dur', ui), tip = $('.np-tip', ui), muteB = $('.np-mute', ui), vol = $('.np-vol', ui), speed = $('.np-speed', ui), hideT;
        players.push(v);
        var toggle = function () { if (v.paused) { players.forEach(function (o) { if (o !== v) o.pause(); }); v.play().catch(function () {}); } else v.pause(); };
        var showUi = function () { fig.classList.add('np-active'); clearTimeout(hideT); if (!v.paused) hideT = setTimeout(function () { fig.classList.remove('np-active'); }, 2600); };
        var upd = function () {
            var d = v.duration || 0, p = d ? v.currentTime / d : 0;
            played.style.width = (p * 100) + '%'; thumb.style.left = (p * 100) + '%'; cur.textContent = fmt(v.currentTime);
            if (v.buffered.length && d) buf.style.width = (v.buffered.end(v.buffered.length - 1) / d * 100) + '%';
        };
        v.addEventListener('loadedmetadata', function () { dur.textContent = fmt(v.duration); upd(); });
        v.addEventListener('timeupdate', upd); v.addEventListener('progress', upd);
        v.addEventListener('play', function () { fig.classList.add('np-playing'); pp.innerHTML = I.pause; showUi(); });
        v.addEventListener('pause', function () { fig.classList.remove('np-playing'); pp.innerHTML = I.play; fig.classList.add('np-active'); clearTimeout(hideT); });
        v.addEventListener('ended', function () { fig.classList.remove('np-playing'); pp.innerHTML = I.play; fig.classList.add('np-active'); });
        v.addEventListener('waiting', function () { fig.classList.add('np-wait'); });
        ['playing', 'canplay', 'seeked'].forEach(function (ev) { v.addEventListener(ev, function () { fig.classList.remove('np-wait'); }); });
        big.addEventListener('click', toggle); pp.addEventListener('click', toggle);
        v.addEventListener('click', toggle);
        v.addEventListener('dblclick', function () { fsToggle(); });
        stage.addEventListener('mousemove', showUi); stage.addEventListener('touchstart', showUi, { passive: true });
        // seek (click / drag, LTR bar)
        var seekAt = function (x) { var r = prog.getBoundingClientRect(), p = Math.max(0, Math.min(1, (x - r.left) / r.width)); if (v.duration) v.currentTime = p * v.duration; upd(); };
        var dragging = false;
        prog.addEventListener('pointerdown', function (e) { dragging = true; prog.setPointerCapture(e.pointerId); seekAt(e.clientX); });
        prog.addEventListener('pointermove', function (e) {
            var r = prog.getBoundingClientRect(), p = Math.max(0, Math.min(1, (e.clientX - r.left) / r.width));
            tip.textContent = fmt(p * (v.duration || 0)); tip.style.left = (p * 100) + '%';
            if (dragging) seekAt(e.clientX);
        });
        prog.addEventListener('pointerup', function () { dragging = false; });
        prog.addEventListener('keydown', function (e) { if (e.key === 'ArrowRight') v.currentTime += 5; if (e.key === 'ArrowLeft') v.currentTime -= 5; });
        muteB.addEventListener('click', function () { v.muted = !v.muted; });
        vol.addEventListener('input', function () { v.volume = +vol.value; v.muted = +vol.value === 0; });
        v.addEventListener('volumechange', function () { muteB.innerHTML = v.muted || v.volume === 0 ? I.mute : I.vol; vol.value = v.muted ? 0 : v.volume; });
        var rates = [1, 1.25, 1.5, 2, 0.5];
        speed.addEventListener('click', function () { var i = (rates.indexOf(v.playbackRate) + 1) % rates.length; v.playbackRate = rates[i]; speed.textContent = rates[i] + '×'; });
        var pip = $('.np-pip', ui);
        if (pip) pip.addEventListener('click', function () { (document.pictureInPictureElement ? document.exitPictureInPicture() : v.requestPictureInPicture()).catch(function () {}); });
        var fsToggle = function () {
            var el = stage, d = document;
            if (d.fullscreenElement || d.webkitFullscreenElement) (d.exitFullscreen || d.webkitExitFullscreen).call(d);
            else if (el.requestFullscreen) el.requestFullscreen().catch(function () {});
            else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
            else if (v.webkitEnterFullscreen) v.webkitEnterFullscreen();   // iPhone
        };
        $('.np-fs', ui).addEventListener('click', fsToggle);
        stage.tabIndex = 0;
        stage.addEventListener('keydown', function (e) {
            if (e.target !== stage) return;
            if (e.key === ' ' || e.key === 'k') { e.preventDefault(); toggle(); }
            else if (e.key === 'm') v.muted = !v.muted;
            else if (e.key === 'f') fsToggle();
            else if (e.key === 'ArrowRight') v.currentTime += 5;
            else if (e.key === 'ArrowLeft') v.currentTime -= 5;
        });
        fig.classList.add('np-ready', 'np-active');
    });

    /* ---------- Online heartbeat: keeps this visitor "online", refreshes the live counter ---------- */
    (function () {
        var out = $('#nxOnline'), lastPing = Date.now();
        function ping() {
            if (document.hidden) return;
            lastPing = Date.now();
            api('api/ping', {}).then(function (j) {
                if (j && j.ok && out) out.textContent = j.online_fa;
                if (j && j.ok) document.dispatchEvent(new CustomEvent('nx:ping', { detail: j }));
            }).catch(function () {});
        }
        setInterval(ping, NX.member ? 30000 : 60000);
        document.addEventListener('visibilitychange', function () { if (!document.hidden && Date.now() - lastPing > 55000) ping(); });
    })();

    /* ==========================================================================
       Stories viewer (Instagram-style). RTL: bars fill right→left, so the
       left side = next, right side = previous.
       ========================================================================== */
    var SV = window.NX_STORIES, sv = $('#storyViewer');
    if (SV && SV.length && sv) (function () {
        var KEY = 'nx_story_seen', seen = {};
        try { seen = JSON.parse(store.get(KEY) || '{}') || {}; } catch (e) { seen = {}; }
        var track = $('.stories-track'), bubbles = $$('.story[data-story]');
        // seen stories turn grey and move to the end (edited stories count as new again)
        bubbles.forEach(function (b) { if (seen[b.dataset.sid] >= +b.dataset.v) b.classList.add('seen'); });
        bubbles.filter(function (b) { return b.classList.contains('seen'); }).forEach(function (b) { track.appendChild(b); });

        var bars = $('.sv-bars', sv), media = $('.sv-media', sv), cap = $('.sv-cap', sv), link = $('.sv-link', sv),
            mute = $('.sv-mute', sv), loading = $('.sv-loading', sv);
        var gi = 0, si = 0, el = null, elapsed = 0, dur = 6, ready = false, paused = false, raf = 0, lastT = 0, muted = true, viewed = {};
        var open = false;

        function markSeen(g) { seen[SV[g].id] = SV[g].v; store.set(KEY, JSON.stringify(seen)); var b = $('.story[data-sid="' + SV[g].id + '"]'); if (b) b.classList.add('seen'); }
        function fill(i, p) { var b = bars.children[i]; if (b) b.firstChild.style.transform = 'scaleX(' + Math.max(0, Math.min(1, p)) + ')'; }

        function showGroup(g) {
            gi = g; var s = SV[g];
            $('.sv-av img', sv).src = s.cover; $('.sv-title', sv).textContent = s.title; $('.sv-ago', sv).textContent = s.ago;
            bars.innerHTML = s.items.map(function () { return '<i><b></b></i>'; }).join('');
            if (!viewed[s.id]) { viewed[s.id] = 1; api('api/story-view', { story_id: s.id }).catch(function () {}); }
        }
        function showSlide(i) {
            var it = SV[gi].items[i]; si = i; ready = false; elapsed = 0; dur = it.duration;
            for (var k = 0; k < bars.children.length; k++) fill(k, k < i ? 1 : 0);
            if (el && el.pause) el.pause();
            media.innerHTML = ''; loading.classList.add('on');
            cap.textContent = it.caption || ''; cap.hidden = !it.caption;
            if (it.link) { link.hidden = false; link.href = it.link; $('span', link).textContent = it.linkText; } else link.hidden = true;
            mute.hidden = it.type !== 'video';
            if (it.type === 'video') {
                el = document.createElement('video');
                el.playsInline = true; el.setAttribute('playsinline', ''); el.preload = 'auto'; el.muted = muted; el.src = it.src;
                el.addEventListener('loadeddata', function () { ready = true; loading.classList.remove('on'); });
                el.addEventListener('ended', next);
                el.addEventListener('error', function () { ready = true; loading.classList.remove('on'); });
                var pl = el.play(); if (pl && pl.catch) pl.catch(function () { muted = true; el.muted = true; mute.textContent = '🔇'; el.play().catch(function () {}); });
            } else {
                el = new Image(); el.decoding = 'async'; el.alt = '';
                el.onload = el.onerror = function () { ready = true; loading.classList.remove('on'); };
                el.src = it.src;
                // preload the next image
                var nx = SV[gi].items[i + 1] || (SV[gi + 1] && SV[gi + 1].items[0]);
                if (nx && nx.type === 'image') { var pre = new Image(); pre.src = nx.src; }
            }
            media.appendChild(el);
        }
        function tick(t) {
            if (!open) return;
            raf = requestAnimationFrame(tick);
            var dt = lastT ? t - lastT : 0; lastT = t;
            if (!ready || paused) return;
            var it = SV[gi].items[si];
            if (it.type === 'video') { if (el.duration) fill(si, el.currentTime / el.duration); return; }
            elapsed += dt / 1000; fill(si, elapsed / dur);
            if (elapsed >= dur) next();
        }
        function next() {
            if (si < SV[gi].items.length - 1) return showSlide(si + 1);
            markSeen(gi);
            if (gi < SV.length - 1) { showGroup(gi + 1); return showSlide(0); }
            close();
        }
        function prev() {
            if (si > 0) return showSlide(si - 1);
            if (gi > 0) { showGroup(gi - 1); return showSlide(0); }
            showSlide(0);
        }
        function nextGroup() { markSeen(gi); if (gi < SV.length - 1) { showGroup(gi + 1); showSlide(0); } else close(); }
        function prevGroup() { if (gi > 0) { showGroup(gi - 1); showSlide(0); } else showSlide(0); }
        function setPaused(p) { paused = p; sv.classList.toggle('paused', p); if (el && el.tagName === 'VIDEO') { if (p) el.pause(); else el.play().catch(function () {}); } }

        function openAt(g) {
            open = true; paused = false; lastT = 0; sv.hidden = false; document.body.style.overflow = 'hidden';
            requestAnimationFrame(function () { sv.classList.add('in'); });
            showGroup(g); showSlide(0);
            cancelAnimationFrame(raf); raf = requestAnimationFrame(tick);
            try { history.pushState({ nxStory: 1 }, ''); } catch (e) {}
        }
        function close(fromPop) {
            if (!open) return;
            open = false; cancelAnimationFrame(raf);
            if (el && el.pause) el.pause();
            sv.classList.remove('in'); document.body.style.overflow = '';
            setTimeout(function () { sv.hidden = true; media.innerHTML = ''; }, 280);
            if (!fromPop && history.state && history.state.nxStory) { try { history.back(); } catch (e) {} }
        }
        window.addEventListener('popstate', function () { if (open) close(true); });   // phone Back button closes the viewer

        bubbles.forEach(function (b) { b.addEventListener('click', function () { openAt(+b.dataset.story); }); });
        $('.sv-close', sv).addEventListener('click', function () { close(); });
        $('.sv-side-next', sv).addEventListener('click', nextGroup);
        $('.sv-side-prev', sv).addEventListener('click', prevGroup);
        mute.addEventListener('click', function (e) { e.stopPropagation(); muted = !muted; if (el) el.muted = muted; mute.textContent = muted ? '🔇' : '🔊'; });
        link.addEventListener('click', function () { setPaused(true); });

        // tap = navigate, hold = pause, swipe down = close, swipe sideways = other story
        var downT = 0, sx = 0, sy = 0, holdT = 0, held = false, zone = null;
        function down(e) {
            var p = e.touches ? e.touches[0] : e; sx = p.clientX; sy = p.clientY; downT = Date.now(); held = false;
            zone = e.target.closest('.sv-tap');
            clearTimeout(holdT); holdT = setTimeout(function () { held = true; setPaused(true); }, 220);
        }
        function up(e) {
            clearTimeout(holdT);
            var p = e.changedTouches ? e.changedTouches[0] : e, dx = p.clientX - sx, dy = p.clientY - sy;
            if (held) { setPaused(false); return; }
            if (dy > 90 && Math.abs(dy) > Math.abs(dx)) return close();
            if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy)) return dx < 0 ? nextGroup() : prevGroup();
            if (zone) zone.classList.contains('sv-next') ? next() : prev();
        }
        var frame = $('.sv-frame', sv);
        frame.addEventListener('touchstart', down, { passive: true });
        frame.addEventListener('touchend', up);
        frame.addEventListener('mousedown', function (e) { if (!e.target.closest('a,button')) down(e); });
        frame.addEventListener('mouseup', function (e) { if (!e.target.closest('a,button')) up(e); });
        sv.addEventListener('click', function (e) { if (e.target === sv) close(); });   // click on backdrop (desktop)
        document.addEventListener('keydown', function (e) {
            if (!open) return;
            if (e.key === 'Escape') close();
            else if (e.key === 'ArrowLeft') next();
            else if (e.key === 'ArrowRight') prev();
            else if (e.key === ' ') { e.preventDefault(); setPaused(!paused); }
        });
        document.addEventListener('visibilitychange', function () { if (open && document.hidden) setPaused(true); });
    })();

    /* ---------- Ads ---------- */
    $$('.nxp-rotate').forEach(function (slot) {
        var ads = $$(':scope > .nxp', slot), i = 0;
        if (ads.length < 2) return;
        ads[0].classList.add('on');
        setInterval(function () { ads[i].classList.remove('on'); i = (i + 1) % ads.length; ads[i].classList.add('on'); }, 6500);
    });
    var popup = $('.nxp-popup');
    if (popup) {
        var pk = 'nx_pop_' + popup.dataset.id, lastShown = parseInt(store.get(pk) || '0', 10), freq = parseInt(popup.dataset.freq, 10) * 3600000;
        if (Date.now() - lastShown > freq) {
            setTimeout(function () { popup.hidden = false; store.set(pk, Date.now()); }, Math.max(0, parseInt(popup.dataset.delay, 10)) * 1000);
        }
        $$('[data-close]', popup).forEach(function (b) { b.addEventListener('click', function () { popup.hidden = true; }); });
    }
    var fl = $('.nxp-floating');
    if (fl) {
        var fk = 'nx_float_' + fl.dataset.id;
        if (!store.sget(fk)) setTimeout(function () { fl.hidden = false; }, 2500);
        $('[data-close]', fl).addEventListener('click', function () { fl.hidden = true; store.sset(fk, 1); });
    }
    var cds = $$('.nxp-countdown');
    if (cds.length) {
        var tick = function () {
            cds.forEach(function (c) {
                var diff = Math.max(0, Math.floor((new Date(c.dataset.end).getTime() - Date.now()) / 1000));
                var v = { d: Math.floor(diff / 86400), h: Math.floor(diff % 86400 / 3600), m: Math.floor(diff % 3600 / 60), s: diff % 60 };
                $$('b', c).forEach(function (b) { b.textContent = faDigits(v[b.dataset.u]); });
            });
        };
        tick(); setInterval(tick, 1000);
    }
})();
