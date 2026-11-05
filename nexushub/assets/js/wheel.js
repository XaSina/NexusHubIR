/* NEXUS | HUB — the shared prize wheel (raffles + lucky wheel).
   NXWheel(wrap, opts) draws the wheel inside `wrap` and returns { build(list), spinTo(i, sec), winners(k), mark(i), reveal(html), segs }.
   The result always comes from the server; the wheel only animates to it.
   NXWheel.party() rains confetti over the whole screen, like a birthday party. */
(function () {
    'use strict';
    var d = document.documentElement;
    var OFF = d.classList.contains('perf-off'), CALM = d.classList.contains('perf-calm'), SAFE = d.classList.contains('perf-safe');
    var esc = function (s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
    var uid = 0;

    /* --acc1 / --acc2: the theme colours, but never so light that white text on them disappears */
    (function safeColours() {
        try {
            var cs = getComputedStyle(d), cv = document.createElement('canvas').getContext('2d');
            var rgb = function (c) { cv.fillStyle = '#000'; cv.fillStyle = c; var h = cv.fillStyle; if (h[0] === '#') return [1, 3, 5].map(function (i) { return parseInt(h.substr(i, 2), 16); }); var m = h.match(/\d+(\.\d+)?/g); return m ? m.slice(0, 3).map(Number) : [255, 31, 61]; };
            var hsl = function (c) { var r = c[0] / 255, g = c[1] / 255, b = c[2] / 255, mx = Math.max(r, g, b), mn = Math.min(r, g, b), l = (mx + mn) / 2, h = 0, s2 = 0;
                if (mx !== mn) { var dd = mx - mn; s2 = l > .5 ? dd / (2 - mx - mn) : dd / (mx + mn); h = mx === r ? (g - b) / dd + (g < b ? 6 : 0) : mx === g ? (b - r) / dd + 2 : (r - g) / dd + 4; h *= 60; } return [h, s2, l]; };
            var css = function (h, s3, l) { return 'hsl(' + Math.round((h + 360) % 360) + ' ' + Math.round(s3 * 100) + '% ' + Math.round(l * 100) + '%)'; };
            var p1 = hsl(rgb(cs.getPropertyValue('--primary').trim() || '#ff1f3d')), p2 = hsl(rgb(cs.getPropertyValue('--secondary').trim() || '#7c4dff'));
            var a1 = css(p1[0], Math.max(.62, p1[1]), Math.min(.54, Math.max(.4, p1[2])));
            var close = Math.abs(((p2[0] - p1[0] + 540) % 360) - 180) < 25;
            var a2 = p2[2] > .66 || p2[1] < .3 || close ? css(p1[0] + (p1[0] > 180 ? -48 : 48), Math.max(.6, p1[1]), .46) : css(p2[0], Math.max(.55, p2[1]), Math.min(.52, Math.max(.38, p2[2])));
            d.style.setProperty('--acc1', a1); d.style.setProperty('--acc2', a2);
        } catch (e) {}
    })();

    // slice paints: [centre, middle, rim] — brand colours alternate with deep glass so names stay readable
    var PAL = [
        ['color-mix(in srgb, var(--acc1) 38%, #04040a)', 'color-mix(in srgb, var(--acc1) 80%, #000)', 'var(--acc1)'],
        ['#05060d', '#0f1226', '#1b1f42'],
        ['color-mix(in srgb, var(--acc2) 38%, #04040a)', 'color-mix(in srgb, var(--acc2) 80%, #000)', 'var(--acc2)'],
        ['#07050f', '#160f2c', '#28194d']
    ];

    function NXWheel(wrap, opts) {
        opts = opts || {};
        var id = 'nxw' + (++uid);
        wrap.classList.add('nxw');
        wrap.innerHTML = '<div class="nxw-aura" aria-hidden="true"></div><div class="nxw-rim" aria-hidden="true"><div class="nxw-bulbs"></div></div>'
            + '<div class="nxw-disc"></div><div class="nxw-glass" aria-hidden="true"></div>'
            + '<div class="nxw-pointer" aria-hidden="true"><svg viewBox="0 0 44 60"><defs>'
            + '<linearGradient id="' + id + 'p" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffffff"/><stop offset=".45" style="stop-color:color-mix(in srgb, var(--acc1, var(--primary)) 60%, #fff)"/><stop offset="1" style="stop-color:var(--acc1, var(--primary))"/></linearGradient>'
            + '<radialGradient id="' + id + 'g" cx=".35" cy=".3" r=".85"><stop offset="0" stop-color="#fff"/><stop offset=".35" style="stop-color:var(--acc2, var(--secondary))"/><stop offset="1" style="stop-color:color-mix(in srgb, var(--acc2, var(--secondary)) 40%, #000)"/></radialGradient></defs>'
            + '<path d="M22 58 C16 45 4 34 4 21 A18 18 0 0 1 40 21 C40 34 28 45 22 58Z" fill="url(#' + id + 'p)" stroke="rgba(0,0,0,.55)" stroke-width="1.2"/>'
            + '<circle cx="22" cy="21" r="11" fill="#06060c"/><circle cx="22" cy="21" r="9" fill="url(#' + id + 'g)"/><ellipse cx="18.5" cy="17" rx="3.2" ry="2.2" fill="#fff" opacity=".9"/></svg></div>'
            + '<div class="nxw-hub" aria-hidden="true"><span class="nxw-hub-ring"></span><span class="nxw-orb"><i></i><i></i><b></b></span></div>'
            + '<div class="nxw-winner" hidden></div>';
        var disc = wrap.querySelector('.nxw-disc'), pointer = wrap.querySelector('.nxw-pointer'), bulbs = wrap.querySelector('.nxw-bulbs'), winBox = wrap.querySelector('.nxw-winner');
        var N = 32, h = ''; for (var i = 0; i < N; i++) h += '<i style="--a:' + (i * 360 / N).toFixed(2) + 'deg;--k:' + i + '"></i>'; bulbs.innerHTML = h;
        var segs = [], api = { segs: segs };

        api.build = function (list) {
            list = list && list.length ? list : [{ label: opts.empty || '—', w: 1 }];
            var total = list.reduce(function (a, s) { return a + (s.w || 1); }, 0), a0 = 0, n = list.length;
            var pt = function (a, r) { var t = (a - 90) * Math.PI / 180; return (Math.cos(t) * r).toFixed(3) + ' ' + (Math.sin(t) * r).toFixed(3); };
            var defs = '<defs>' + PAL.map(function (c, k) {
                return '<radialGradient id="' + id + 's' + k + '" cx="0" cy="0" r="96" gradientUnits="userSpaceOnUse"><stop offset=".2" style="stop-color:' + c[0] + '"/><stop offset=".7" style="stop-color:' + c[1] + '"/><stop offset="1" style="stop-color:' + c[2] + '"/></radialGradient>';
            }).join('') + '<radialGradient id="' + id + 'sh" cx="0" cy="0" r="96" gradientUnits="userSpaceOnUse"><stop offset=".55" stop-color="#000" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity=".45"/></radialGradient></defs>';
            var body = '', lines = '', ticks = '', labels = '';
            segs.length = 0;
            list.forEach(function (s, i) {
                var w = s.w || 1, a1 = a0 + w / total * 360, seg = { a0: a0, a1: a1, mid: (a0 + a1) / 2, win: s.win, label: s.label, data: s };
                var k = s.color != null ? -1 : i % 4; if (k >= 0 && i === n - 1 && n > 1 && k === 0) k = 2;
                var fill = k < 0 ? esc(s.color) : 'url(#' + id + 's' + k + ')';
                var path = n === 1 ? '<circle r="96"' : '<path d="M0 0 L' + pt(a0, 96) + ' A96 96 0 ' + (a1 - a0 > 180 ? 1 : 0) + ' 1 ' + pt(a1, 96) + ' Z"';
                body += '<g class="nxw-seg" data-i="' + i + '">' + path + ' fill="' + fill + '"/></g>';
                if (n > 1) {
                    lines += '<line x1="0" y1="0" x2="' + pt(a0, 96).replace(' ', '" y2="') + '"/>';
                    if (n <= 90) ticks += '<circle cx="' + pt(a0, 92.4).replace(' ', '" cy="') + '" r="' + (n <= 24 ? 1.5 : 1) + '"/>';
                }
                // label (HTML, not SVG text: Chrome sometimes breaks Persian letter joins inside SVG)
                var left = seg.mid > 180 && n > 1;
                labels += '<span class="nxw-t nxw-l' + (left ? ' is-left' : '') + '" data-i="' + i + '" style="--rot:' + (left ? seg.mid + 90 : seg.mid - 90).toFixed(3) + 'deg"><span>' + esc((s.emoji ? s.emoji + ' ' : '') + (s.label || '')) + '</span></span>';
                segs.push(seg); a0 = a1;
            });
            disc.innerHTML = '<svg viewBox="-100 -100 200 200" aria-hidden="true" shape-rendering="geometricPrecision" text-rendering="geometricPrecision">' + defs + body
                + '<circle r="96" fill="url(#' + id + 'sh)"/><g class="nxw-lines">' + lines + '</g>'
                + '<circle r="96.2" fill="none" class="nxw-edge"/><g class="nxw-ticks">' + ticks + '</g><circle r="30" class="nxw-core"/></svg>'
                + '<div class="nxw-labels">' + labels + '</div>';
            wrap.classList.toggle('nxw-dense', n > 60);
            fit();
            return api;
        };
        /* every label fits the room it has: from just outside the hub (r 33) to the rim (r 84), and is never taller
           than its slice is wide. Long names go to two lines first, then get smaller; only tiny slices get an ellipsis. */
        function fit() {
            var R0 = 84, R1 = 33, room = R0 - R1, W = disc.offsetWidth; if (!W) return;
            var u = W / 200;   // px per wheel unit
            [].forEach.call(disc.querySelectorAll('.nxw-l'), function (el) {
                var seg = segs[+el.getAttribute('data-i')]; if (!seg) return;
                var n = segs.length, span = (seg.a1 - seg.a0) * Math.PI / 180, inner = el.firstChild;
                var tall = n === 1 ? 30 : Math.max(3, span * (R1 + room * .35) * .9);   // slice width near the inner end of the text
                el.style.setProperty('--w', (room * u) + 'px'); el.style.setProperty('--h', (tall * u) + 'px');
                el.style.setProperty('--x', ((el.classList.contains('is-left') ? -R0 : R1) * u) + 'px');
                inner.textContent = (seg.data && seg.data.emoji ? seg.data.emoji + ' ' : '') + (seg.label || '');
                var fits = function (fs, wrapIt) {
                    inner.style.fontSize = (fs * u) + 'px'; inner.classList.toggle('two', !!wrapIt);
                    return inner.scrollWidth <= el.clientWidth + .5 && inner.scrollHeight <= el.clientHeight + .5;
                };
                var fs = Math.min(10.5, tall * .8);
                if (fits(fs, false)) return;
                var one = fs; while (one > 3.8 && !fits(one, false)) one *= .93;
                if (/\s/.test(seg.label || '') && tall >= 7) {   // two lines can keep letters bigger
                    var two = Math.min(10.5, tall * .42); while (two > 3.8 && !fits(two, true)) two *= .93;
                    if (two > one + .4 && fits(two, true)) return;
                }
                if (fits(one, false)) return;
                inner.classList.add('cut'); fits(3.8, false);   // tiny slice: ellipsis
            });
        }
        api.fit = fit;
        if (window.ResizeObserver) { var lastW = 0; new ResizeObserver(function () { var w = wrap.offsetWidth; if (w && w !== lastW) { lastW = w; fit(); } }).observe(wrap); }
        // label sizes are measured, so measure again once the Persian font is really there
        var redraw = function () { if (segs.length) fit(); };
        if (document.fonts && document.fonts.load) {
            document.fonts.load('800 12px Vazirmatn', 'گردونه شانس سینا ۱۲۳').then(redraw, function () {});
            var tm; document.fonts.addEventListener && document.fonts.addEventListener('loadingdone', function () { clearTimeout(tm); tm = setTimeout(redraw, 60); });
        }
        function curRot() {
            var m = getComputedStyle(disc).transform; if (!m || m === 'none') return 0;
            var v = m.match(/matrix\(([^)]+)\)/); if (!v) return 0; v = v[1].split(',').map(parseFloat);
            return Math.atan2(v[1], v[0]) * 180 / Math.PI;
        }
        function segAt(r) { var a = ((-r % 360) + 360) % 360; for (var i = 0; i < segs.length; i++) if (a >= segs[i].a0 && a < segs[i].a1) return i; return 0; }
        api.busy = false;
        api.spinTo = function (idx, dur) {
            return new Promise(function (done) {
                var s = segs[idx]; if (!s) return done();
                api.busy = true; api.mark(-1);
                var span = s.a1 - s.a0, aim = s.a0 + span * (.22 + Math.random() * .56);   // somewhere inside the slice, not always the middle
                var base = curRot(); disc.style.transition = 'none'; disc.style.transform = 'rotate(' + base + 'deg)'; void disc.offsetWidth;
                var target = base + (OFF ? 0 : 360 * (dur > 6 ? 9 : 6)) + (((-aim - base) % 360) + 360) % 360;
                var finish = function () { wrap.classList.remove('nxw-spinning'); disc.style.willChange = 'auto'; api.busy = false; done(); };
                if (OFF) { disc.style.transform = 'rotate(' + target + 'deg)'; return finish(); }
                wrap.classList.add('nxw-spinning'); disc.style.willChange = 'transform';
                disc.style.transition = 'transform ' + dur + 's cubic-bezier(.08,.72,.05,1)';
                disc.style.transform = 'rotate(' + target + 'deg)';
                var last = -1, t0 = performance.now(), lastTick = 0;
                (function watch(now) {
                    var i = segAt(curRot());
                    if (i !== last && now - lastTick > 42) { last = i; lastTick = now; pointer.classList.remove('tick'); void pointer.offsetWidth; pointer.classList.add('tick'); }
                    if (performance.now() - t0 < dur * 1000 + 60) requestAnimationFrame(watch); else finish();
                })(t0);
            });
        };
        api.winners = function (test) { var all = []; segs.forEach(function (s, k) { if (test(s, k)) all.push(k); }); return all; };
        api.pick = function (test) { var all = api.winners(test); return all.length ? all[Math.floor(Math.random() * all.length)] : -1; };
        api.mark = function (i) { [].forEach.call(disc.querySelectorAll('.nxw-seg, .nxw-t'), function (g) { g.classList.toggle('win', +g.getAttribute('data-i') === i); }); wrap.classList.toggle('nxw-has-win', i >= 0); };
        api.reveal = function (html, ms) {
            winBox.innerHTML = html; winBox.hidden = false; void winBox.offsetWidth; winBox.classList.add('in');
            return new Promise(function (r) { setTimeout(function () { winBox.classList.remove('in'); setTimeout(function () { winBox.hidden = true; r(); }, OFF ? 0 : 380); }, OFF ? 600 : (ms || 3400)); });
        };
        api.idle = function (on) { wrap.classList.toggle('nxw-idle', !!on && !OFF && !CALM); };
        return api;
    }

    /* ---------- full-screen party confetti: two cannons from the bottom corners + a shower from the top ---------- */
    var partyOn = false;
    NXWheel.party = function () {
        if (OFF || partyOn) return; partyOn = true;
        var cv = document.createElement('canvas'); cv.className = 'nxw-party'; cv.setAttribute('aria-hidden', 'true'); document.body.appendChild(cv);
        var dpr = Math.min(2, window.devicePixelRatio || 1), W = window.innerWidth, H = window.innerHeight;
        cv.width = W * dpr; cv.height = H * dpr; var ctx = cv.getContext('2d'); ctx.scale(dpr, dpr);
        var cs = getComputedStyle(d), col = [cs.getPropertyValue('--primary').trim() || '#ff1f3d', cs.getPropertyValue('--secondary').trim() || '#7c4dff', '#ffd23f', '#ffffff', '#ff4fa3', '#3ddc84', '#4fc3ff'];
        var NUM = CALM ? 110 : SAFE ? 170 : 300, ps = [];
        function add(x, y, ang, spd, delay) {
            var shape = Math.random(); ps.push({ x: x, y: y, vx: Math.cos(ang) * spd, vy: Math.sin(ang) * spd, g: .16 + Math.random() * .1, drag: .985 - Math.random() * .01,
                w: 6 + Math.random() * 7, h: 3 + Math.random() * 5, rot: Math.random() * 6.3, vr: (Math.random() - .5) * .35, flip: Math.random() * 6.3, vf: .08 + Math.random() * .12,
                sway: Math.random() * 6.3, c: col[Math.floor(Math.random() * col.length)], kind: shape < .62 ? 0 : shape < .86 ? 1 : 2, delay: delay || 0 });
        }
        var side = Math.round(NUM * .36);
        for (var i = 0; i < side; i++) {
            add(-10, H + 10, -Math.PI / 2 + .35 + Math.random() * .55, 13 + Math.random() * 11, Math.random() * 260);
            add(W + 10, H + 10, -Math.PI / 2 - .35 - Math.random() * .55, 13 + Math.random() * 11, Math.random() * 260);
        }
        for (var j = 0; j < NUM - side * 2; j++) add(Math.random() * W, -20 - Math.random() * H * .4, Math.PI / 2, 1 + Math.random() * 2, 350 + Math.random() * 900);
        var t0 = performance.now(), LIFE = 5200;
        (function frame(now) {
            var t = now - t0; ctx.clearRect(0, 0, W, H);
            var fade = t > LIFE - 900 ? Math.max(0, (LIFE - t) / 900) : 1, alive = 0;
            for (var k = 0; k < ps.length; k++) {
                var p = ps[k]; if (t < p.delay) { alive++; continue; }
                p.vy += p.g; p.vx *= p.drag; p.vy *= p.drag; p.sway += .05; p.x += p.vx + Math.sin(p.sway) * .6; p.y += p.vy; p.rot += p.vr; p.flip += p.vf;
                if (p.y > H + 40) continue; alive++;
                ctx.save(); ctx.globalAlpha = fade; ctx.translate(p.x, p.y); ctx.rotate(p.rot); ctx.scale(1, Math.cos(p.flip)); ctx.fillStyle = p.c;
                if (p.kind === 0) ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
                else if (p.kind === 1) { ctx.beginPath(); ctx.arc(0, 0, p.h * .7, 0, 6.283); ctx.fill(); }
                else { ctx.fillRect(-1.2, -p.w, 2.4, p.w * 2); }   // thin streamer
                ctx.restore();
            }
            if (t < LIFE && alive) requestAnimationFrame(frame); else { cv.remove(); partyOn = false; }
        })(t0);
    };
    window.NXWheel = NXWheel;
})();
