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

    /* ---------- Cinematic page warp (login / account links) ---------- */
    var warp = $('#nxWarp'), warpIn = document.documentElement.classList.contains('warp-in');
    function warpTo(href, x, y) {
        if (!warp || reduce) { location.href = href; return; }
        warp.style.setProperty('--wx', (x == null ? window.innerWidth / 2 : x) + 'px');
        warp.style.setProperty('--wy', (y == null ? window.innerHeight / 2 : y) + 'px');
        warp.classList.add('cover');
        store.sset('nx_warp', '1');
        setTimeout(function () { location.href = href; }, 820);
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
        setTimeout(function () { document.documentElement.classList.remove('warp-in'); }, 160);
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
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
            });
        }, { rootMargin: '0px 0px -8% 0px', threshold: 0.06 });
        $$('[data-reveal]').forEach(function (el) { io.observe(el); });
    }

    /* ---------- Background canvas ---------- */
    var cv = $('#bgCanvas');
    if (cv && !reduce) {
        var ctx = cv.getContext('2d');
        var W, H, dpr = Math.min(window.devicePixelRatio || 1, 2), parts = [], cols = [], running = true;
        var hexToRgb = function (h) { h = h.replace('#', ''); return [parseInt(h.substr(0, 2), 16), parseInt(h.substr(2, 2), 16), parseInt(h.substr(4, 2), 16)]; };
        var pc = hexToRgb(NX.primary || '#ff1f3d'), sc = hexToRgb(NX.secondary || '#ff7a3d');
        var mouse = { x: -999, y: -999 };
        function size() {
            W = cv.clientWidth; H = cv.clientHeight;
            cv.width = W * dpr; cv.height = H * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            if (NX.bg === 'matrix') {
                cols = []; for (var i = 0; i < Math.ceil(W / 18); i++) cols.push(Math.random() * -H);
            } else {
                var n = Math.min(90, Math.floor(W * H / 16000));
                parts = [];
                for (var j = 0; j < n; j++) parts.push({ x: Math.random() * W, y: Math.random() * H, vx: (Math.random() - .5) * .35, vy: (Math.random() - .5) * .35, r: Math.random() * 1.8 + .6, c: Math.random() > .7 ? sc : pc });
            }
        }
        var chars = 'NEXUS01アイウエオカキクケコ<>/{}#$%&';
        function drawMatrix() {
            ctx.fillStyle = 'rgba(' + (NX.light ? '238,240,246' : '0,0,0') + ',0.12)';
            ctx.fillRect(0, 0, W, H);
            ctx.font = '15px monospace';
            for (var i = 0; i < cols.length; i++) {
                var ch = chars[Math.floor(Math.random() * chars.length)];
                ctx.fillStyle = 'rgba(' + pc.join(',') + ',' + (Math.random() * .5 + .3) + ')';
                ctx.fillText(ch, i * 18, cols[i]);
                cols[i] = cols[i] > H + Math.random() * 1000 ? 0 : cols[i] + 18;
            }
        }
        function drawParticles() {
            ctx.clearRect(0, 0, W, H);
            for (var i = 0; i < parts.length; i++) {
                var p = parts[i];
                p.x += p.vx; p.y += p.vy;
                if (p.x < 0 || p.x > W) p.vx *= -1;
                if (p.y < 0 || p.y > H) p.vy *= -1;
                var dxm = p.x - mouse.x, dym = p.y - mouse.y, dm = dxm * dxm + dym * dym;
                if (dm < 14000) { p.x += dxm * .012; p.y += dym * .012; }
                ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                ctx.fillStyle = 'rgba(' + p.c.join(',') + ',.85)'; ctx.fill();
                for (var j = i + 1; j < parts.length; j++) {
                    var q = parts[j], dx = p.x - q.x, dy = p.y - q.y, d = dx * dx + dy * dy;
                    if (d < 16000) {
                        ctx.strokeStyle = 'rgba(' + pc.join(',') + ',' + (0.16 * (1 - d / 16000)) + ')';
                        ctx.lineWidth = 1; ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(q.x, q.y); ctx.stroke();
                    }
                }
            }
        }
        var last = 0;
        function loop(t) {
            if (!running) return;
            requestAnimationFrame(loop);
            if (NX.bg === 'matrix') { if (t - last < 55) return; last = t; drawMatrix(); }
            else drawParticles();
        }
        size();
        window.addEventListener('resize', function () { clearTimeout(cv._t); cv._t = setTimeout(size, 200); });
        window.addEventListener('mousemove', function (e) { mouse.x = e.clientX; mouse.y = e.clientY; }, { passive: true });
        document.addEventListener('visibilitychange', function () { running = !document.hidden; if (running) requestAnimationFrame(loop); });
        requestAnimationFrame(loop);
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
    $$('.ad-theme-spotlight').forEach(function (el) {
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
    window.addEventListener('scroll', function () { requestAnimationFrame(onScroll); }, { passive: true });
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
    var so = $('#searchOverlay');
    function openSearch() { if (!so) return; so.hidden = false; setTimeout(function () { $('input', so).focus(); }, 50); document.body.style.overflow = 'hidden'; }
    function closeSearch() { if (!so) return; so.hidden = true; document.body.style.overflow = ''; }
    if ($('#searchOpen')) $('#searchOpen').addEventListener('click', openSearch);
    if ($('#searchClose')) $('#searchClose').addEventListener('click', closeSearch);
    if (so) so.addEventListener('click', function (e) { if (e.target === so) closeSearch(); });
    var drawer = $('#drawer');
    if ($('#menuOpen') && drawer) {
        $('#menuOpen').addEventListener('click', function () { drawer.hidden = false; document.body.style.overflow = 'hidden'; });
        $$('[data-close]', drawer).forEach(function (b) { b.addEventListener('click', function () { drawer.hidden = true; document.body.style.overflow = ''; }); });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closeSearch(); if (drawer) { drawer.hidden = true; document.body.style.overflow = ''; } }
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
            var done = function () { toast('لینک کپی شد ✅'); };
            if (navigator.clipboard) navigator.clipboard.writeText(txt).then(done, function () { prompt('کپی کنید:', txt); });
            else { prompt('کپی کنید:', txt); }
        });
    });
    $$('[data-share]').forEach(function (b) {
        if (!navigator.share) { b.remove(); return; }
        b.addEventListener('click', function () { navigator.share({ title: b.dataset.title, url: b.dataset.url }).catch(function () {}); });
    });

    /* ---------- Ads ---------- */
    $$('.ad-rotate').forEach(function (slot) {
        var ads = $$(':scope > .ad', slot), i = 0;
        if (ads.length < 2) return;
        ads[0].classList.add('on');
        setInterval(function () { ads[i].classList.remove('on'); i = (i + 1) % ads.length; ads[i].classList.add('on'); }, 6500);
    });
    var popup = $('.ad-popup');
    if (popup) {
        var pk = 'nx_pop_' + popup.dataset.id, lastShown = parseInt(store.get(pk) || '0', 10), freq = parseInt(popup.dataset.freq, 10) * 3600000;
        if (Date.now() - lastShown > freq) {
            setTimeout(function () { popup.hidden = false; store.set(pk, Date.now()); }, Math.max(0, parseInt(popup.dataset.delay, 10)) * 1000);
        }
        $$('[data-close]', popup).forEach(function (b) { b.addEventListener('click', function () { popup.hidden = true; }); });
    }
    var fl = $('.ad-floating');
    if (fl) {
        var fk = 'nx_float_' + fl.dataset.id;
        if (!store.sget(fk)) setTimeout(function () { fl.hidden = false; }, 2500);
        $('[data-close]', fl).addEventListener('click', function () { fl.hidden = true; store.sset(fk, 1); });
    }
    var cds = $$('.ad-countdown');
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
