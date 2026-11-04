/* ==========================================================================
   NEXUS | HUB — Cinematic auth flow
   intro → identify → (login | register → success) → access granted → account
   ========================================================================== */
(function () {
    'use strict';
    var C = window.NXAU || {};
    var $ = function (s, c) { return (c || document).querySelector(s); };
    var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
    var body = document.body, root = document.documentElement;
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var touch = window.matchMedia && window.matchMedia('(hover: none)').matches;   // phones: no auto-focus (the keyboard would push the page)
    $$('.nx-warp-core img').forEach(function (i) { if (i.complete && i.naturalWidth) i.classList.add('ok'); });
    var fa = function (s) { return String(s).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; }); };
    var en = function (s) { return String(s).replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); }).replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); }); };
    var wait = function (ms) { return new Promise(function (r) { setTimeout(r, reduce ? Math.min(ms, 60) : ms); }); };
    var cssVar = function (n, d) { return (getComputedStyle(root).getPropertyValue(n) || d).trim(); };

    function api(path, data) {
        var fd = new FormData();
        Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
        fd.append('_csrf', C.csrf);
        return fetch((C.base || '') + '/api/' + path, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': C.csrf } })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'خطای سرور' }; }); })
            .catch(function () { return { ok: false, error: 'ارتباط با سرور برقرار نشد.' }; });
    }

    /* ---------- page warp (shared with site) ---------- */
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
    var warp = $('#nxWarp');
    function warpTo(href) {
        if (!warp || reduce) { location.href = href; return; }
        warp.style.setProperty('--wx', '50%'); warp.style.setProperty('--wy', '50%');
        warpRestart(warp);
        warp.classList.add('cover');
        try { sessionStorage.setItem('nx_warp', String(Date.now())); } catch (e) {}
        setTimeout(function () { location.href = href; }, 600);
    }
    document.addEventListener('click', function (e) {
        var a = e.target.closest('a[data-warp]');
        if (!a || e.ctrlKey || e.metaKey) return;
        e.preventDefault(); warpTo(a.href);
    });
    window.addEventListener('pageshow', function (e) { if (e.persisted && warp) warp.classList.remove('cover'); });
    var arrivedByWarp = root.classList.contains('warp-in'), warpGone = Promise.resolve();
    if (arrivedByWarp) {
        try { sessionStorage.removeItem('nx_warp'); } catch (e) {}
        warpGone = warpExit();
    }

    /* ---------- timecode HUD ---------- */
    var tc = $('#auTc'), t0 = performance.now(), hud = $('#auHudState');
    var pad = function (n) { return ('0' + n).slice(-2); };
    if (tc) setInterval(function () {
        var t = (performance.now() - t0) / 1000;
        tc.textContent = pad(Math.floor(t / 3600)) + ':' + pad(Math.floor(t / 60) % 60) + ':' + pad(Math.floor(t) % 60) + ':' + pad(Math.floor(t * 24) % 24);
    }, 42);
    var setHud = function (s) { if (hud) hud.textContent = s; };

    /* ==========================================================================
       Canvas FX (one layer): stars · particle logo · hyperspace streaks · confetti
       Everything is batched into a few paths per frame so phones stay at 60fps.
       ========================================================================== */
    var small = window.innerWidth < 760;
    var cv = $('#auFx'), ctx = cv.getContext('2d'), W = 0, H = 0, dpr = Math.min(window.devicePixelRatio || 1, 2);
    var hex = function (h) { h = h.replace('#', ''); if (h.length === 3) h = h.replace(/./g, '$&$&'); return [parseInt(h.substr(0, 2), 16), parseInt(h.substr(2, 2), 16), parseInt(h.substr(4, 2), 16)]; };
    var PC = hex(cssVar('--primary', '#ff1f3d')), SC = hex(cssVar('--secondary', '#ff7a3d'));
    var mix = function (a, b, t) { return [0, 1, 2].map(function (i) { return Math.round(a[i] + (b[i] - a[i]) * t); }); };
    var rgba = function (c, a) { return 'rgba(' + c[0] + ',' + c[1] + ',' + c[2] + ',' + a + ')'; };
    var stars = [], streaks = [], confetti = [], warpUntil = 0, warpStart = 0;
    function glowSprite(c, R) {
        var o = document.createElement('canvas'); o.width = o.height = R * 2;
        var g = o.getContext('2d'), gr = g.createRadialGradient(R, R, 0, R, R, R);
        gr.addColorStop(0, 'rgba(255,255,255,1)'); gr.addColorStop(.18, rgba(c, .9)); gr.addColorStop(.45, rgba(c, .25)); gr.addColorStop(1, rgba(c, 0));
        g.fillStyle = gr; g.fillRect(0, 0, R * 2, R * 2); return o;
    }
    var spP = glowSprite(PC, 24), spW = glowSprite([255, 240, 230], 16);
    function size() {
        W = cv.clientWidth; H = cv.clientHeight; cv.width = W * dpr; cv.height = H * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        placeLogo();
    }
    function star() { return { x: Math.random() * W, y: Math.random() * H, r: Math.random() < .1 ? 1.5 : (Math.random() < .45 ? 1 : .6), ph: Math.random() * 6.28, sp: .6 + Math.random() * 1.8, v: .02 + Math.random() * .06 }; }

    /* ---------- particle logo ---------- */
    var LOGO = { parts: [], state: 'none', t0: 0, cx: 0, cy: 0, sz: 0, formAt: 0, orbitA: 0 };
    var pointer = { x: -9999, y: -9999 };
    if (!window.matchMedia('(hover: none)').matches) {
        window.addEventListener('pointermove', function (e) { pointer.x = e.clientX; pointer.y = e.clientY; }, { passive: true });
        document.addEventListener('pointerleave', function () { pointer.x = pointer.y = -9999; });
    }
    function placeLogo() {
        var em = $('#auEmblem'); if (!em) return;
        var r = em.getBoundingClientRect();
        if (LOGO.sz && Math.abs(LOGO.sz - r.width) > 1) glowCv = dotsCv = brightCv = comboCv = null;
        LOGO.cx = r.left + r.width / 2; LOGO.cy = r.top + r.height / 2; LOGO.sz = r.width;
    }
    // sample the real logo: keep pixels that stand out from the background colour (works for any uploaded logo)
    function sampleLogo(img) {
        var S = small ? 190 : 230, oc = document.createElement('canvas'); oc.width = oc.height = S;
        var g = oc.getContext('2d'); g.drawImage(img, 0, 0, S, S);
        var d = g.getImageData(0, 0, S, S).data, bg = [d[0], d[1], d[2]], pts = [];
        for (var y = 0; y < S; y++) for (var x = 0; x < S; x++) {
            var i = (y * S + x) * 4; if (d[i + 3] < 120) continue;
            var dx = x - S / 2, dy = y - S / 2; if (dx * dx + dy * dy > S * S * .25) continue;          // logo is shown as a circle
            var dr = d[i] - bg[0], dg = d[i + 1] - bg[1], db = d[i + 2] - bg[2];
            if (dr * dr + dg * dg + db * db < 3600) continue;
            pts.push([x / S - .5, y / S - .5, Math.max(d[i], d[i + 1], d[i + 2]) / 255]);
        }
        for (var k = pts.length - 1; k > 0; k--) { var j = Math.floor(Math.random() * (k + 1)), t = pts[k]; pts[k] = pts[j]; pts[j] = t; }
        return pts.slice(0, small ? 1900 : 3300);
    }
    // returns ms until the logo is formed (0 = particles unavailable → show the image instead)
    function startLogo() {
        var img = $('#auLogo'), em = $('#auEmblem');
        var pts = null;
        try { if (img && img.naturalWidth) pts = sampleLogo(img); } catch (e) { pts = null; }  // cross-origin logos can't be sampled
        if (!pts || pts.length < 200) { if (em) em.classList.add('img-mode'); return 0; }
        em.classList.add('px');
        placeLogo();
        var R0 = Math.max(W, H) * .75, now = performance.now();
        LOGO.parts = pts.map(function (p) {
            var a = Math.random() * 6.283, rr = R0 * (.35 + Math.random() * .75), lum = p[2];
            return { nx: p[0], ny: p[1], sx: Math.cos(a) * rr, sy: Math.sin(a) * rr * .7, d: Math.random() * 650, du: 1250 + Math.random() * 650,
                b: lum > .72 ? 3 : lum > .48 ? 2 : lum > .26 ? 1 : 0, ph: Math.random() * 6.28, ox: 0, oy: 0, spark: Math.random() < .045, x: 0, y: 0, vx: 0, vy: 0 };
        });
        LOGO.state = reduce ? 'formed' : 'forming'; LOGO.t0 = now; LOGO.formAt = now + (reduce ? 0 : 1950); LOGO.doneAt = now + (reduce ? 0 : 2650);
        return reduce ? 1 : 1950;
    }
    function explodeLogo() {
        if (LOGO.state === 'none' || LOGO.state === 'gone') return;
        LOGO.parts.forEach(function (p) {
            var dx = p.x - LOGO.cx, dy = p.y - LOGO.cy, l = Math.sqrt(dx * dx + dy * dy) || 1, v = 2 + Math.random() * 6;
            p.vx = dx / l * v + (Math.random() - .5) * 2; p.vy = dy / l * v + (Math.random() - .5) * 2;
        });
        LOGO.state = 'explode'; LOGO.t0 = performance.now();
    }
    var BUCKET = [rgba(mix(PC, [255, 255, 255], .08), .8), rgba(mix(PC, [255, 255, 255], .32), 1), rgba(mix(PC, [255, 255, 255], .62), 1), 'rgba(255,252,248,1)'];
    var ease = function (t) { return 1 - Math.pow(1 - t, 4); };
    var glowCv = null, dotsCv = null, brightCv = null, comboCv = null;
    function buildDots() {
        var L = LOGO, n = Math.ceil(L.sz * 1.1 * dpr), o = L.sz * .55, s2 = small ? 1.05 : 1.15;
        var mk = function (bright) {
            var g = document.createElement('canvas'); g.width = g.height = n;
            var c = g.getContext('2d'); c.scale(dpr, dpr);
            var paths = [new Path2D(), new Path2D(), new Path2D(), new Path2D()];
            for (var i = 0; i < L.parts.length; i++) { var p = L.parts[i]; paths[bright ? Math.min(3, p.b + 1) : p.b].rect(p.nx * L.sz + o - s2 / 2, p.ny * L.sz + o - s2 / 2, s2, s2); }
            for (var k = 0; k < 4; k++) { c.fillStyle = BUCKET[k]; c.fill(paths[k]); }
            return g;
        };
        dotsCv = mk(false); brightCv = mk(true);
        if (!glowCv) buildGlow();
        var gsz = L.sz * 1.3, m = Math.ceil(gsz * dpr), cb = document.createElement('canvas'); cb.width = cb.height = m;
        var cc = cb.getContext('2d'); cc.scale(dpr, dpr);
        cc.globalCompositeOperation = 'lighter'; cc.globalAlpha = .55; cc.drawImage(glowCv, 0, 0, gsz, gsz);
        cc.globalCompositeOperation = 'source-over'; cc.globalAlpha = 1; cc.drawImage(dotsCv, (gsz - L.sz * 1.1) / 2, (gsz - L.sz * 1.1) / 2, L.sz * 1.1, L.sz * 1.1);
        comboCv = cb;
    }
    function buildGlow() {
        var L = LOGO, hasF = 'filter' in CanvasRenderingContext2D.prototype, k = hasF ? 2 : 5, n = Math.ceil(L.sz * 1.3 / k), g = document.createElement('canvas'); g.width = g.height = n;
        var c = g.getContext('2d'), o = L.sz * .65;
        if (hasF) c.filter = 'blur(' + (7 / k) + 'px)';
        c.globalCompositeOperation = 'lighter';
        for (var i = 0; i < L.parts.length; i++) {
            var p = L.parts[i]; c.fillStyle = p.b >= 2 ? rgba(mix(PC, [255, 255, 255], .5), .35) : rgba(PC, .3);
            c.fillRect((p.nx * L.sz + o) / k - .6, (p.ny * L.sz + o) / k - .6, 1.2, 1.2);
        }
        glowCv = g;
    }
    // orbit ring with depth: back half is drawn behind the logo (dim), front half in front (brighter)
    function drawOrbit(now, front, oa) {
        var L = LOGO, sz = L.sz, rx = sz * .84, ry = sz * .24, rot = -.28, ocy = L.cy + sz * .05;
        ctx.globalAlpha = oa * (front ? .55 : .2); ctx.strokeStyle = rgba(mix(PC, [255, 255, 255], .45), 1); ctx.lineWidth = front ? 1 : .8;
        ctx.beginPath(); ctx.ellipse(L.cx, ocy, rx, ry, rot, front ? 0 : Math.PI, front ? Math.PI : Math.PI * 2); ctx.stroke();
        var a0 = now * .0008;
        for (var c = 0, cn = small ? 10 : 18; c < cn; c++) {
            var aa = a0 - c * (small ? .07 : .04), inFront = Math.sin(aa) > 0;
            if (inFront !== front) continue;
            var ex = Math.cos(aa) * rx, ey = Math.sin(aa) * ry;
            var x = L.cx + ex * Math.cos(rot) - ey * Math.sin(rot), y = ocy + ex * Math.sin(rot) + ey * Math.cos(rot);
            var gs = (c ? 8 : 20) * (1 - c / 20) * (front ? 1 : .7);
            ctx.globalAlpha = oa * (1 - c / 18) * (c ? .5 : 1) * (front ? 1 : .45); ctx.drawImage(c ? spP : spW, x - gs / 2, y - gs / 2, gs, gs);
        }
    }
    function drawLogo(now) {
        var L = LOGO; if (L.state === 'none' || L.state === 'gone') return;
        var paths = [new Path2D(), new Path2D(), new Path2D(), new Path2D()], sparks = [];
        var ps = small ? 1.05 : 1.15, alpha = 1, sz = L.sz;
        var sweep = L.cx - sz * .8 + (((now - L.formAt) % 4200) / 4200) * sz * 2.2;     // shine band passing across
        if (L.state === 'explode') { alpha = Math.max(0, 1 - (now - L.t0) / 900); if (!alpha) { L.state = 'gone'; return; } }
        var near = pointer.x > L.cx - sz && pointer.x < L.cx + sz && pointer.y > L.cy - sz && pointer.y < L.cy + sz;
        var settledAll = L.state !== 'explode' && now > L.doneAt;
        if (settledAll && !near && !L.parts.some(function (p) { return Math.abs(p.ox) > .05 || Math.abs(p.oy) > .05; })) {
            if (!comboCv) buildDots();
            var gsz = sz * 1.3, dsz = sz * 1.1;
            drawOrbit(now, false, 1);
            ctx.globalAlpha = 1; ctx.drawImage(comboCv, Math.round((L.cx - gsz / 2) * dpr) / dpr, Math.round((L.cy - gsz / 2) * dpr) / dpr, gsz, gsz);
            // shine band: the bright sprite seen through a moving clip
            ctx.save(); ctx.beginPath(); ctx.rect(sweep - 12, L.cy - dsz / 2, 24, dsz); ctx.clip(); ctx.drawImage(brightCv, L.cx - dsz / 2, L.cy - dsz / 2, dsz, dsz); ctx.restore();
            for (var q2 = 0, lim = small ? 70 : 200, cnt = 0; q2 < L.parts.length && cnt < lim; q2++) {
                var sp2 = L.parts[q2]; if (!sp2.spark) continue; cnt++;
                var tw2 = Math.sin(now * .003 + sp2.ph * 3);
                if (tw2 > .55) { var g2 = 5 + (tw2 - .55) * 18; ctx.globalAlpha = (tw2 - .55) * 2; ctx.drawImage(spW, L.cx + sp2.nx * sz - g2 / 2, L.cy + sp2.ny * sz - g2 / 2, g2, g2); }
            }
            drawOrbit(now, true, 1);
            ctx.globalAlpha = 1;
            return;
        }
        for (var i = 0; i < L.parts.length; i++) {
            var p = L.parts[i], tx = L.cx + p.nx * sz, ty = L.cy + p.ny * sz, x, y, b = p.b, s = ps;
            if (L.state === 'explode') {
                p.vx *= 1.04; p.vy *= 1.04; p.x += p.vx; p.y += p.vy; x = p.x; y = p.y; s = ps * (1 + (now - L.t0) / 450);
            } else {
                var t = Math.min(1, Math.max(0, (now - L.t0 - p.d) / p.du));
                if (t < 1) {
                    var e = ease(t), sw = (1 - e) * 1.25, cs = Math.cos(sw), sn = Math.sin(sw);
                    var rx = p.sx * (1 - e) + (tx - L.cx) * e, ry = p.sy * (1 - e) + (ty - L.cy) * e;
                    x = L.cx + rx * cs - ry * sn; y = L.cy + rx * sn + ry * cs; s = ps * (1 + (1 - e) * 1.6);
                    if (t <= 0) continue;
                } else {
                    // formed: tiny shimmer + spring-back repel from the pointer
                    var wx = 0, wy = 0;
                    if (near) { var ddx = tx - pointer.x, ddy = ty - pointer.y, d2 = ddx * ddx + ddy * ddy; if (d2 < 3600) { var dd = Math.sqrt(d2) || 1, f = (1 - dd / 60) * 16; wx = ddx / dd * f; wy = ddy / dd * f; } }
                    p.ox += (wx - p.ox) * .14; p.oy += (wy - p.oy) * .14;
                    x = tx + p.ox + Math.sin(now * .0016 + p.ph) * .35; y = ty + p.oy + Math.cos(now * .0013 + p.ph) * .35;
                    if (Math.abs(x - sweep) < 12 && b < 3) b++;
                }
                p.x = x; p.y = y;
            }
            paths[b].rect(x - s / 2, y - s / 2, s, s);
            if (p.spark && L.state !== 'explode') sparks.push(p);
        }
        var oa = L.state !== 'explode' && now > L.formAt - 400 ? Math.min(1, (now - L.formAt + 400) / 900) : 0;
        if (L.state !== 'explode' && now > L.formAt && !glowCv) buildGlow();
        if (glowCv) {
            var ga = L.state === 'explode' ? alpha : Math.min(1, (now - L.formAt) / 700), gsz = sz * 1.3;
            ctx.globalCompositeOperation = 'lighter'; ctx.globalAlpha = ga * .55;
            ctx.drawImage(glowCv, L.cx - gsz / 2, L.cy - gsz / 2, gsz, gsz);
            ctx.globalCompositeOperation = 'source-over';
        }
        if (oa) drawOrbit(now, false, oa);
        ctx.globalAlpha = alpha;
        for (var k = 0; k < 4; k++) { ctx.fillStyle = BUCKET[k]; ctx.fill(paths[k]); }
        // twinkling sparks
        for (var q = 0; q < sparks.length; q++) {
            var sp = sparks[q], tw = Math.sin(now * .003 + sp.ph * 3);
            if (tw > .55) { var g = 5 + (tw - .55) * 18; ctx.globalAlpha = alpha * (tw - .55) * 2; ctx.drawImage(spW, sp.x - g / 2, sp.y - g / 2, g, g); }
        }
        if (oa) drawOrbit(now, true, oa);
        ctx.globalAlpha = 1;
    }

    size();
    var STARS = Math.min(small ? 130 : 280, Math.floor(W * H / 6000));
    for (var i = 0; i < STARS; i++) stars.push(star());
    window.addEventListener('resize', function () { size(); });

    function frame(now) {
        requestAnimationFrame(frame);
        if (document.hidden) return;
        ctx.clearRect(0, 0, W, H);
        var warping = now < warpUntil, wp = warping ? (now - warpStart) / (warpUntil - warpStart) : 1;
        // stars: three brightness batches, slow drift, twinkle
        var sp3 = [new Path2D(), new Path2D(), new Path2D()];
        for (var i = 0; i < stars.length; i++) {
            var s = stars[i];
            s.y -= s.v; s.x -= s.v * .4; if (s.y < -2) { s.y = H + 2; s.x = Math.random() * W; } if (s.x < -2) s.x = W + 2;
            var tw = Math.sin(now * .001 * s.sp + s.ph), lv = tw > .45 ? 2 : tw > -.3 ? 1 : 0;
            sp3[lv].rect(s.x, s.y, s.r, s.r);
        }
        ctx.fillStyle = 'rgba(255,255,255,.28)'; ctx.fill(sp3[0]);
        ctx.fillStyle = 'rgba(255,255,255,.6)'; ctx.fill(sp3[1]);
        ctx.fillStyle = 'rgba(255,250,245,.95)'; ctx.fill(sp3[2]);
        drawLogo(now);
        // hyperspace streaks (two batched paths)
        if (warping && !reduce && wp < .72) for (var n = 0; n < (small ? 3 : 8); n++) {
            var ang = Math.random() * 6.283, d0 = 30 + Math.random() * 60;
            streaks.push({ x: W / 2 + Math.cos(ang) * d0, y: H * .45 + Math.sin(ang) * d0, a: ang, v: 2.5 + Math.random() * 3.5, l: 0, c: Math.random() < .55 });
        }
        if (streaks.length) {
            var pathC = new Path2D(), pathW = new Path2D();
            for (var j = streaks.length - 1; j >= 0; j--) {
                var st = streaks[j];
                st.v *= 1.06; st.l = Math.min(200, st.l + st.v * .8);
                st.x += Math.cos(st.a) * st.v; st.y += Math.sin(st.a) * st.v;
                if (st.x < -260 || st.x > W + 260 || st.y < -260 || st.y > H + 260) { streaks.splice(j, 1); continue; }
                var pth = st.c ? pathC : pathW;
                pth.moveTo(st.x, st.y); pth.lineTo(st.x - Math.cos(st.a) * st.l, st.y - Math.sin(st.a) * st.l);
            }
            ctx.lineWidth = small ? 1.2 : 1.5;
            ctx.strokeStyle = rgba(PC, .55); ctx.stroke(pathC);
            ctx.strokeStyle = 'rgba(255,245,238,.45)'; ctx.stroke(pathW);
        }
        // confetti
        for (var q = confetti.length - 1; q >= 0; q--) {
            var p = confetti[q];
            p.vy += .18; p.vx *= .99; p.x += p.vx; p.y += p.vy; p.r += p.vr; p.life--;
            ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.r); ctx.globalAlpha = Math.max(0, Math.min(1, p.life / 40));
            ctx.fillStyle = p.c; ctx.fillRect(-p.s / 2, -p.s / 4, p.s, p.s / 2); ctx.restore();
            if (p.life <= 0 || p.y > H + 40) confetti.splice(q, 1);
        }
        ctx.globalAlpha = 1;
    }
    requestAnimationFrame(frame);
    function doWarp(ms) { warpStart = performance.now(); warpUntil = warpStart + ms; }
    function burst(x, y, n) {
        if (reduce) return;
        var cols = [cssVar('--primary', '#ff1f3d'), cssVar('--secondary', '#ff7a3d'), '#ffffff', '#ffd23d', '#3ddc84', '#00e1ff'];
        for (var i = 0; i < n; i++) {
            var a = Math.random() * 6.283, v = 4 + Math.random() * 9;
            confetti.push({ x: x, y: y, vx: Math.cos(a) * v, vy: Math.sin(a) * v - 5, r: Math.random() * 6, vr: (Math.random() - .5) * .4, s: 6 + Math.random() * 8, c: cols[i % cols.length], life: 110 + Math.random() * 60 });
        }
    }
    function flash(soft) { var f = $('.au-flash'); f.classList.remove('go'); f.classList.toggle('soft', !!soft); void f.offsetWidth; f.classList.add('go'); }

    /* ---------- text effects ---------- */
    function scramble(el, text, dur) {
        var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ0123456789#$%&@/<>', start = performance.now();
        return new Promise(function (res) {
            (function step(t) {
                var p = Math.min(1, (t - start) / (reduce ? 1 : dur)), n = Math.floor(text.length * p), out = '';
                for (var i = 0; i < text.length; i++) out += (i < n || /[\s|\-]/.test(text[i])) ? text[i] : chars[Math.floor(Math.random() * chars.length)];
                el.textContent = out;
                if (p < 1) requestAnimationFrame(step); else res();
            })(start);
        });
    }
    function typeText(el, text, speed) {
        var chars = Array.from(text), i = 0;
        return new Promise(function (res) {
            (function tick() { el.textContent = chars.slice(0, ++i).join(''); if (i < chars.length) setTimeout(tick, reduce ? 0 : speed); else res(); })();
        });
    }

    /* ==========================================================================
       Intro sequence
       ========================================================================== */
    var title = $('#auTitle'), titleText = title.dataset.text;
    // keep the real text (invisible until stage 3) so the column height never changes and the logo doesn't jump
    // wait (max 2.5s) until the logo is decoded so it glides in instead of popping in late
    var logoReady = (function () {
        var img = $('#auLogo');
        if (!img) return Promise.resolve();
        var done = img.complete && img.naturalWidth ? (img.decode ? img.decode() : Promise.resolve()) : new Promise(function (r) { img.addEventListener('load', r, { once: true }); img.addEventListener('error', r, { once: true }); });
        return Promise.race([done.catch(function () {}), new Promise(function (r) { setTimeout(r, 2500); })]);
    })();
    (async function intro() {
        await warpGone; await wait(arrivedByWarp ? 260 : 150);
        body.classList.add('s1'); setHud('BOOTING');
        await logoReady;
        var formIn = startLogo();                       // particles swirl in and assemble the logo
        await wait(formIn ? Math.max(0, formIn - 250) : 650);
        body.classList.add('s2'); setHud('SIGNAL LOCKED');
        if (!formIn) setTimeout(function () { flash(true); }, reduce ? 0 : 1050);
        await wait(formIn ? 550 : 1300);
        body.classList.add('s3');
        scramble(title, titleText, 900);
        setTimeout(function () { body.classList.add('s4'); setHud('READY'); }, reduce ? 0 : 750);   // the button doesn't wait for the typing
        await typeText($('#auType'), 'دروازه‌ی ورود به کامیونیتی NEXUS 🔥', 34);
    })();

    /* ==========================================================================
       Auth card / steps
       ========================================================================== */
    var stage = $('#auStage'), card = $('#auCard'), dots = $$('#auDots i'), backBtn = $('#auBackStep');
    var steps = {}; $$('.au-step').forEach(function (s) { steps[s.dataset.step] = s; });
    var current = 'identify', identity = { value: '', type: '' };

    $('#auStart').addEventListener('click', async function () {
        var btn = this; if (btn.disabled) return; btn.disabled = true;
        btn.classList.add('fired');
        if (btn.animate && !reduce) { var ring = document.createElement('span'); ring.className = 'au-start-ring'; btn.appendChild(ring);
            ring.animate([{ opacity: .9, transform: 'scale(1)' }, { opacity: 0, transform: 'scale(1.5, 2)' }], { duration: 700, easing: 'cubic-bezier(.16,1,.3,1)' }).onfinish = function () { ring.remove(); }; }
        await wait(140);
        body.classList.add('warping'); setHud('WARP DRIVE');
        explodeLogo();
        doWarp(1150);
        setTimeout(function () { flash(true); }, 520);
        await wait(900);
        $('#auIntro').hidden = true;
        stage.hidden = false;
        body.classList.remove('warping');            // the scene eases back while the card rises
        setHud('IDENTIFY');
        steps.identify.classList.add('in');
        if (!touch) setTimeout(function () { $('#auIdentifier').focus({ preventScroll: true }); }, 650);
    });

    function setDots(n) { dots.forEach(function (d, i) { d.classList.toggle('on', i < n); }); }
    function show(name) {
        if (name === current) return;
        var from = steps[current], to = steps[name];
        var h0 = card.offsetHeight;
        from.classList.remove('in'); from.classList.add('out');
        to.hidden = false; to.classList.remove('out'); to.classList.add('in');
        card.classList.toggle('wide', name === 'register');
        // animate card height
        var h1 = card.offsetHeight; card.style.height = h0 + 'px'; void card.offsetHeight; card.style.height = h1 + 'px';
        setTimeout(function () { from.hidden = true; from.classList.remove('out'); }, 380);
        setTimeout(function () { card.style.height = ''; }, 600);
        current = name;
        backBtn.hidden = !(name === 'login' || name === 'register');
        setDots({ identify: 1, login: 2, register: 2, success: 3 }[name] || 1);
        setHud({ identify: 'IDENTIFY', login: 'VERIFY', register: 'NEW MEMBER', success: 'REGISTERED' }[name] || 'READY');
        var first = to.querySelector('input:not([type=hidden]):not(.au-hp)');
        if (first && name !== 'success' && !touch) setTimeout(function () { first.focus({ preventScroll: true }); }, 420);
        clearErrors(to);
    }
    backBtn.addEventListener('click', function () { show('identify'); });
    $$('[data-goto]').forEach(function (b) { b.addEventListener('click', function () { show(b.dataset.goto); }); });

    var canTranslate = window.CSS && CSS.supports && CSS.supports('translate', '1px');
    /* one tight, damped shake; dy glides the card from where it was (the error line makes it taller and it is centred) */
    function shake(el, dy) {
        if (reduce || !el || !el.animate) return;
        dy = dy || 0;
        var xs = [0, -10, 9, -6, 4, -2, 0], n = xs.length - 1;
        var k = xs.map(function (x, i) { var y = (dy * Math.pow(1 - i / n, 2)).toFixed(1); return canTranslate ? { translate: x + 'px ' + y + 'px' } : { transform: 'translate(' + x + 'px,' + y + 'px)' }; });
        el.animate(k, { duration: dy ? 560 : 480, easing: 'cubic-bezier(.36,.07,.19,.97)' });
    }
    function errorOn(form, msg) {
        var box = $('.au-error', form);
        box.textContent = msg; box.classList.add('on');
        shake($('#auSteps'), 0);
        if (navigator.vibrate) try { navigator.vibrate(35); } catch (e) {}
    }
    function clearErrors(form) {
        var box = $('.au-error', form); if (box) { box.classList.remove('on'); box.textContent = ''; }
        $$('.au-field.err', form).forEach(function (f) { f.classList.remove('err'); });
        $$('.au-ferr', form).forEach(function (f) { f.textContent = ''; });
    }
    function loading(form, on) { var b = $('.au-btn', form) || form; b.classList.toggle('loading', on); b.disabled = on; }

    /* identifier live type detection */
    var ICONS = {
        phone: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="2" width="12" height="20" rx="3"/><path d="M11 18h2"/></svg>',
        email: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m3 7 9 6 9-6"/></svg>',
        username: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>',
        none: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l2 6 6 2-6 2-2 6-2-6-6-2 6-2 2-6Z"/></svg>'
    };
    var LABELS = { phone: 'موبایل', email: 'ایمیل', username: 'نام کاربری' };
    function detect(v) {
        v = en(v).trim();
        if (!v) return null;
        if (v.indexOf('@') >= 0) return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v) ? 'email' : null;
        var p = v.replace(/[\s\-().]/g, '').replace(/^\+98/, '0').replace(/^0098/, '0').replace(/^9(\d{9})$/, '09$1');
        if (/^09\d{9}$/.test(p)) return 'phone';
        if (/^[0-9+]/.test(v)) return null;
        if (/^[A-Za-z][A-Za-z0-9_.]{2,29}$/.test(v)) return 'username';
        return null;
    }
    var idInput = $('#auIdentifier'), idIcon = $('#auIdIcon'), idType = $('#auIdType'), lastType = 'x';
    var idField = idInput.closest('.au-id-field'), idLine = $('.au-id-line', idField), idRing = $('.au-id-ring', idField), idSparks = $('.au-id-sparks', idField);
    var idBtn = $('.au-btn', steps.identify), mctx = document.createElement('canvas').getContext('2d');
    function updateIdType() {
        var t = detect(idInput.value) || 'none';
        if (t !== lastType) {
            idIcon.innerHTML = ICONS[t]; idIcon.classList.remove('pop'); void idIcon.offsetWidth; idIcon.classList.add('pop');
            idType.textContent = LABELS[t] || ''; idType.classList.toggle('on', t !== 'none');
            var valid = t !== 'none';
            if (valid && lastType !== 'x') { idField.classList.remove('sweep'); void idField.offsetWidth; idField.classList.add('sweep'); }
            idField.classList.toggle('valid', valid); idBtn.classList.toggle('armed', valid);
            lastType = t;
        }
    }
    function textW(v) {
        var cs = getComputedStyle(idInput);
        mctx.font = cs.fontWeight + ' ' + cs.fontSize + ' ' + cs.fontFamily;
        return mctx.measureText(v).width + (parseFloat(cs.letterSpacing) || 0) * v.length;
    }
    function typingFx(e) {
        var v = idInput.value, cs = getComputedStyle(idInput), pr = parseFloat(cs.paddingRight), pl = parseFloat(cs.paddingLeft);
        var inner = Math.max(1, idInput.clientWidth - pr - pl), w = Math.min(textW(v), inner);
        idLine.style.transform = 'scaleX(' + (v ? Math.max(.05, w / inner).toFixed(3) : 0) + ')';
        if (reduce || !e || !e.data || !idInput.animate) return;     // only real keystrokes / pastes
        // text is right-aligned, so the caret (end of an LTR value) sits at its left edge
        var x = idInput.clientWidth - pr - w;
        for (var i = 0; i < 3; i++) {
            var s = document.createElement('i'); s.className = 'au-spark'; s.style.left = x + 'px'; idSparks.appendChild(s);
            var dx = -8 - Math.random() * 22, dy = (Math.random() - .5) * 34;
            s.animate([{ transform: 'translate(0,0) scale(1)', opacity: 1 }, { transform: 'translate(' + dx + 'px,' + dy + 'px) scale(.2)', opacity: 0 }], { duration: 420 + Math.random() * 220, easing: 'cubic-bezier(.16,1,.3,1)' }).onfinish = (function (n) { return function () { n.remove(); }; })(s);
        }
        idRing.animate([{ opacity: .55, transform: 'scale(1)' }, { opacity: 0, transform: 'scale(1.035, 1.12)' }], { duration: 480, easing: 'cubic-bezier(.16,1,.3,1)' });
    }
    var idMirror = $('#auIdMirror'), mirrorVal = '';
    function renderMirror(next, animate) {
        var prev = mirrorVal, pre = 0, suf = 0;
        while (pre < prev.length && pre < next.length && prev[pre] === next[pre]) pre++;
        while (suf < prev.length - pre && suf < next.length - pre && prev[prev.length - 1 - suf] === next[next.length - 1 - suf]) suf++;
        // keep the untouched characters (their running animation continues), drop the replaced ones, insert the new ones
        var kids = Array.prototype.slice.call(idMirror.children);
        if (kids.length !== prev.length) { kids.forEach(function (k) { k.remove(); }); kids = []; pre = 0; suf = 0; prev = ''; }
        for (var r = pre; r < prev.length - suf; r++) kids[r].remove();
        var ref = kids[prev.length - suf] || null, added = 0;
        for (var i = pre; i < next.length - suf; i++) {
            var sp = document.createElement('span');
            sp.className = animate ? 'ch new' : 'ch';
            if (animate) sp.style.setProperty('--cd', Math.min(added++, 12) * 28 + 'ms');
            sp.textContent = next[i];
            idMirror.insertBefore(sp, ref);
        }
        Array.prototype.forEach.call(idMirror.children, function (k, n) { k.style.setProperty('--i', n); });
        mirrorVal = next;
        // only draw the mirror while the text fits; long values fall back to the native (scrolling) text
        idField.classList.toggle('mirror', !reduce && next.length > 0 && idMirror.scrollWidth <= idMirror.clientWidth + 1);
    }
    function wave() { if (reduce) return; idMirror.classList.remove('wave'); void idMirror.offsetWidth; idMirror.classList.add('wave'); setTimeout(function () { idMirror.classList.remove('wave'); }, 1300); }
    idInput.addEventListener('input', function (e) {
        var was = idField.classList.contains('valid');
        updateIdType(); typingFx(e); renderMirror(idInput.value, true);
        if (!was && idField.classList.contains('valid')) setTimeout(wave, 420);
    });
    idInput.addEventListener('scroll', function () { if (idInput.scrollLeft) idField.classList.remove('mirror'); });
    updateIdType(); typingFx(null); renderMirror(idInput.value, false);

    /* step 1: identify */
    steps.identify.addEventListener('submit', async function (e) {
        e.preventDefault(); clearErrors(this);
        var v = en(idInput.value).trim();
        if (!detect(v)) return errorOn(this, 'یک شماره موبایل (مثل 09121234567)، ایمیل یا نام کاربری انگلیسی معتبر وارد کن.');
        loading(this, true); setHud('SCANNING');
        var j = await api('auth-check', { identifier: v });
        await wait(350);
        loading(this, false);
        if (!j.ok) { setHud('IDENTIFY'); return errorOn(this, j.error || 'خطا'); }
        identity = { value: j.value, type: j.type, raw: v };
        if (j.exists) {
            $('#auHelloName').textContent = j.first_name;
            $('#auAvatar').textContent = (j.first_name || 'N').charAt(0);
            $('#auLoginId').textContent = j.value;
            $('#auPassword').value = '';
            show('login');
        } else {
            var f = steps.register;
            $('#auRegId').textContent = j.value;
            ['phone', 'email', 'username'].forEach(function (k) { var inp = f.elements[k]; if (k === j.type) inp.value = j.value; else if (inp.dataset.auto) { inp.value = ''; delete inp.dataset.auto; } });
            show('register');
        }
    });

    /* step 2: login */
    steps.login.addEventListener('submit', async function (e) {
        e.preventDefault(); clearErrors(this);
        var pw = $('#auPassword').value;
        if (!pw) { $('#auPassword').closest('.au-field').classList.add('err'); return errorOn(this, 'رمز عبور را وارد کن.'); }
        loading(this, true); setHud('AUTHENTICATING');
        var j = await api('auth-login', { identifier: identity.value, password: pw });
        loading(this, false);
        if (!j.ok) { setHud('VERIFY'); $('#auPassword').closest('.au-field').classList.add('err'); $('#auPassword').select(); return errorOn(this, j.error || 'خطا'); }
        var lb = $('.au-btn', this); lb.disabled = true; lb.classList.add('done');
        await wait(420);
        enterSequence(j);
    });

    /* step 3: register */
    var reg = steps.register, pwField = reg.elements.password, strength = $('#auStrength');
    pwField.addEventListener('input', function () {
        var v = en(pwField.value), s = 0;
        if (v.length >= 8) s++;
        if (/[A-Za-z]/.test(v) && /\d/.test(v)) s++;
        if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
        if (/[^A-Za-z0-9]/.test(v) || v.length >= 12) s++;
        strength.dataset.s = v ? Math.max(1, s) : 0;
    });
    reg.elements.email.addEventListener('input', function () {
        var u = reg.elements.username;
        if (u.value && !u.dataset.auto) return;
        var local = (this.value.split('@')[0] || '').replace(/[^A-Za-z0-9_.]/g, '').replace(/^[^A-Za-z]+/, '').slice(0, 30);
        u.value = local.length >= 3 ? local : ''; u.dataset.auto = '1';
    });
    reg.elements.username.addEventListener('input', function () { delete this.dataset.auto; });
    reg.addEventListener('submit', async function (e) {
        e.preventDefault(); clearErrors(this);
        var data = {}, f = this;
        ['first_name', 'last_name', 'phone', 'email', 'username', 'password', 'website'].forEach(function (k) { data[k] = en(f.elements[k].value).trim(); });
        data.password = f.elements.password.value;
        var missing = ['first_name', 'last_name', 'phone', 'email', 'username', 'password'].filter(function (k) { return !data[k]; });
        if (missing.length) { missing.forEach(function (k) { f.elements[k].closest('.au-field').classList.add('err'); }); return errorOn(f, 'لطفاً همه‌ی فیلدها را کامل کن.'); }
        loading(f, true); setHud('CREATING ID');
        var j = await api('auth-register', data);
        loading(f, false);
        if (!j.ok) {
            setHud('NEW MEMBER');
            if (j.errors) Object.keys(j.errors).forEach(function (k) {
                var inp = f.elements[k]; if (!inp) return;
                var fld = inp.closest('.au-field'); fld.classList.add('err');
                $('.au-ferr', fld).textContent = j.errors[k];
            });
            return errorOn(f, j.error || 'خطا در ثبت‌نام');
        }
        // success!
        $('#auSucName').textContent = j.name;
        $('#auSucFull').textContent = j.full_name;
        $('#auSucDate').textContent = j.date;
        show('success');
        flash();
        var r = card.getBoundingClientRect();
        setTimeout(function () { burst(r.left + r.width / 2, r.top + 90, 140); }, 900);
        setTimeout(function () { burst(window.innerWidth * .15, window.innerHeight * .4, 60); burst(window.innerWidth * .85, window.innerHeight * .4, 60); }, 1250);
        setTimeout(function () { scramble($('#auSucCode'), j.code, 1300); }, 1400);
    });

    /* step 4: finish → auto login */
    $('#auFinish').addEventListener('click', async function () {
        var b = this; b.classList.add('loading'); b.disabled = true;
        var j = await api('auth-finish', {});
        if (!j.ok) {
            b.classList.remove('loading'); b.disabled = false;
            identity.value = reg.elements.username.value;
            $('#auHelloName').textContent = $('#auSucName').textContent;
            $('#auLoginId').textContent = identity.value;
            show('login');
            return errorOn(steps.login, j.error || 'لطفاً وارد شو.');
        }
        enterSequence(j);
    });

    /* ==========================================================================
       Access granted sequence
       ========================================================================== */
    async function enterSequence(j) {
        if (j.csrf) C.csrf = j.csrf;
        setHud('ACCESS GRANTED');
        var ov = $('#auEnter'), pct = $('#auPct'), term = $('#auTerm'), prog = $('#auProg'), core = $('#auCore'), av = $('#auEnterAvatar');
        var letter = (j.name || $('#auHelloName').textContent || 'N').trim().charAt(0) || 'N';
        av.textContent = letter;
        // shared-element flight: the avatar (or the button) travels into the centre of the ring
        var src = $('#auAvatar'); if (!src || !src.offsetParent) src = $('.au-btn.done') || $('#auFinish');
        var from = src ? src.getBoundingClientRect() : null, fly = null;
        if (from && !reduce && document.body.animate) {
            fly = document.createElement('div'); fly.className = 'au-fly'; fly.textContent = letter;
            var sz = Math.min(from.width, from.height);
            fly.style.cssText = 'left:' + (from.left + from.width / 2 - sz / 2) + 'px;top:' + (from.top + from.height / 2 - sz / 2) + 'px;width:' + sz + 'px;height:' + sz + 'px;font-size:' + (sz * .42) + 'px';
            document.body.appendChild(fly);
        }
        card.classList.add('leaving');
        await wait(380);
        stage.hidden = true;
        ov.hidden = false; body.classList.add('entering');
        doWarp(420);
        if (fly) {
            var to = av.getBoundingClientRect(), fr = fly.getBoundingClientRect();
            var dx = (to.left + to.width / 2) - (fr.left + fr.width / 2), dy = (to.top + to.height / 2) - (fr.top + fr.height / 2), sc = to.width / fr.width;
            await new Promise(function (res) {
                var an = fly.animate([{ transform: 'none' }, { transform: 'translate(' + dx + 'px,' + dy + 'px) scale(' + sc + ')' }], { duration: 720, easing: 'cubic-bezier(.65,0,.35,1)', fill: 'forwards' });
                an.onfinish = res; setTimeout(res, 900);
            });
            av.classList.add('on'); fly.remove();
        } else { av.classList.add('on'); }
        var lines = ['> handshake with nexus core ... <b>OK</b>', '> verifying identity [' + (j.code || 'NX') + '] ... <b>OK</b>', '> decrypting member profile ... <b>OK</b>', '> syncing preferences ... <b>OK</b>', '> access level: <b>MEMBER</b>'];
        var start = performance.now(), dur = reduce ? 1 : 1800, shown = 0;
        await new Promise(function (res) {
            (function tick(t) {
                var p = Math.min(1, (t - start) / dur), eased = p < .5 ? 4 * p * p * p : 1 - Math.pow(-2 * p + 2, 3) / 2;
                pct.textContent = Math.round(eased * 100) + '%';
                prog.style.strokeDashoffset = (100 - eased * 100).toFixed(2);
                while (shown < Math.min(lines.length, Math.floor(p * lines.length + .35))) { var d = document.createElement('div'); d.innerHTML = lines[shown++]; term.appendChild(d); }
                if (p < 1) requestAnimationFrame(tick); else res();
            })(start);
        });
        $('#auWelcomeName').textContent = j.name || '';
        body.classList.add('granted');
        $('#auGranted').classList.add('on');
        flash(true);
        var r = core.getBoundingClientRect();
        burst(r.left + r.width / 2, r.top + r.height / 2, small ? 60 : 90);
        await wait(1600);
        warpTo(j.redirect || C.home);
    }
})();
