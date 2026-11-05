/* NEXUS | HUB — raffle page: countdown, live wheel, winner reveal, join / buy / invite.
   The winner is decided on the server; the wheel spins to the stored result (fair, and the same for every viewer). */
(function () {
    'use strict';
    var S = window.NX_RAFFLE; if (!S) return;
    var $ = function (s, c) { return (c || document).querySelector(s); };
    var d = document.documentElement;
    var OFF = d.classList.contains('perf-off'), CALM = d.classList.contains('perf-calm');
    var fa = function (s) { return String(s).replace(/\d/g, function (x) { return '۰۱۲۳۴۵۶۷۸۹'[x]; }); };
    var money = function (n) { return fa(Math.round(n).toLocaleString('en-US')); };
    var esc = function (s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
    var toast = function (m) { if (window.nxToast) window.nxToast(m); else alert(m); };
    var NX = window.NX || {};
    function api(act, fd) {
        if (!(fd instanceof FormData)) { var f = new FormData(); Object.keys(fd || {}).forEach(function (k) { f.append(k, fd[k]); }); fd = f; }
        fd.append('id', S.id); fd.append('_csrf', NX.csrf || '');
        return fetch((NX.base || '') + '/api/' + act, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'خطای شبکه' }; }); });
    }
    function update(st) { Object.keys(st).forEach(function (k) { if (k !== 'ok') S[k] = st[k]; }); }

    /* ---------- countdown ---------- */
    var endAt = Date.now() + (S.left || 0) * 1000, cnt = $('#rfCount'), lastV = {};
    function tick() {
        var s = Math.max(0, Math.round((endAt - Date.now()) / 1000));
        if (cnt && !cnt.hidden) {
            var v = { d: Math.floor(s / 86400), h: Math.floor(s % 86400 / 3600), m: Math.floor(s % 3600 / 60), s: s % 60 };
            Object.keys(v).forEach(function (u) {
                var b = cnt.querySelector('[data-u="' + u + '"]'); if (!b) return;
                var txt = fa(v[u] < 10 ? '0' + v[u] : v[u]);
                if (lastV[u] !== txt) { b.textContent = txt; lastV[u] = txt; if (!OFF && !CALM) { b.classList.remove('tick'); void b.offsetWidth; b.classList.add('tick'); } }
            });
        }
        if (s <= 0 && S.status === 'active') goLive();
    }
    setInterval(tick, 1000); tick();

    /* ---------- progress, my status, people ---------- */
    function renderProg() {
        var el = $('#rfProg'); if (!el) return;
        var paid = S.mode === 'paid', have = paid ? S.sold : S.participants, need = paid ? (S.need_tickets || 0) : (S.need || 0);
        var pct = need ? Math.min(100, have / need * 100) : 100;
        el.innerHTML = '<div class="rf-pbar"><i style="--p:' + pct.toFixed(1) + '%"></i></div><div class="rf-plabel"><span><b>' + fa(have) + '</b>' + (need ? ' از ' + fa(need) : '') + (paid ? ' تیکت فروخته شده' : ' نفر شرکت کردن') + '</span>'
            + (need ? '<span>' + (have >= need ? '✅ به حد نصاب رسید' : fa(Math.max(0, need - have)) + (paid ? ' تیکت' : ' نفر') + ' تا حد نصاب') + '</span>' : '') + '</div>'
            + (paid && S.need ? '<div class="rf-plabel"><span>👥 ' + fa(S.participants) + ' از حداقل ' + fa(S.need) + ' نفر</span></div>' : '');
    }
    function renderMine() {
        var box = $('#rfMine'), jb = $('#rfJoinBox'), me = S.me, open = S.status === 'active' && (endAt - Date.now()) > 0;
        if (jb) {
            jb.innerHTML = me && me.joined
                ? '<div class="rf-ok">✅ تو توی این قرعه‌کشی هستی</div>'
                : (open ? '<h3>شرکت در قرعه‌کشی</h3><p class="muted small">با یک کلیک شرکت کن؛ سر ساعت گردونه همین‌جا زنده می‌چرخه.</p><button type="button" class="btn btn-primary btn-block rf-join rf-join-big">🎟️ شرکت در قرعه‌کشی</button>' : '');
        }
        if (!box) return;
        if (!me || !me.joined) { box.innerHTML = S.mode === 'paid' && me && me.pending ? '<div class="rf-me"><div class="rf-me-row"><span>⏳ در انتظار تأیید فیش</span><b>' + fa(me.pending) + ' تیکت</b></div></div>' : ''; return; }
        var h = '<div class="rf-me"><div class="rf-me-row"><span>شانس برنده شدنت</span><b class="rf-chance">' + fa(me.chance) + '٪</b></div>';
        if (S.mode === 'paid') {
            h += '<div class="rf-me-row"><span>تیکت‌های تو</span><b>' + fa(me.tickets) + ' عدد</b></div>';
            if (me.ranges && me.ranges.length) h += '<div class="rf-tickets">' + me.ranges.map(function (g) { return '<span>#' + fa(g[0]) + (g[1] > g[0] ? ' – #' + fa(g[1]) : '') + '</span>'; }).join('') + '</div>';
            if (me.pending) h += '<div class="rf-me-row"><span>⏳ در انتظار تأیید</span><b>' + fa(me.pending) + ' تیکت</b></div>';
        }
        if (S.mode === 'referral') {
            h += '<div class="rf-me-row"><span>دوستانی که دعوت کردی</span><b>' + fa(me.invited) + (S.maxRef ? ' / ' + fa(S.maxRef) : '') + '</b></div>'
                + '<div class="rf-me-row"><span>تیکت‌های جایزه</span><b>+' + fa(me.bonus) + ' (هر کدوم ' + fa(S.bonusPct) + '٪ شانس)</b></div>';
            if (S.invite) h += '<small class="muted">لینک دعوت تو — بفرست برای دوستات:</small><div class="rf-invite"><input readonly value="' + esc(S.invite) + '"><button type="button" class="btn btn-primary btn-sm" data-copy="' + esc(S.invite) + '">کپی</button></div>';
        }
        box.innerHTML = h + '</div>';
    }
    function renderPeople() {
        var el = $('#rfPeople'), n = $('#rfPeopleN'); if (!el) return;
        if (n) n.textContent = '(' + fa(S.participants) + ' نفر)';
        var max = S.people.length ? S.people[0].chance : 1;
        el.innerHTML = S.people.length ? S.people.map(function (p, i) {
            var extra = S.mode === 'paid' ? fa(p.tickets) + ' تیکت · ' : (S.mode === 'referral' && p.bonus ? '+' + fa(p.bonus) + ' دعوت · ' : '');
            return '<div class="rf-pp' + (p.me ? ' me' : '') + '"><span class="n">' + fa(i + 1) + '</span><b>' + esc(p.name) + (p.me ? ' (تو)' : '') + '</b><small>' + extra + fa(p.chance) + '٪</small><span class="bar"><i style="--w:' + Math.max(3, p.chance / (max || 1) * 100).toFixed(1) + '%"></i></span></div>';
        }).join('') : '<p class="muted small">هنوز کسی شرکت نکرده — اولین نفر باش!</p>';
    }

    /* ---------- the wheel ---------- */
    var disc = $('#rfDisc'), wrap = $('#rfWheelWrap'), pointer = $('#rfPointer'), segs = [], rot = 0, busy = false;
    (function lights() {
        var L = $('.rf-lights'); if (!L) return; var h = ''; for (var i = 0; i < 24; i++) h += '<i style="--a:' + (i * 15) + 'deg"></i>'; L.innerHTML = h;
        var place = function () { L.style.setProperty('--r', Math.max(0, L.offsetWidth / 2 - 5) + 'px'); }; place(); window.addEventListener('resize', place);
    })();
    var COLORS = ['var(--primary)', 'var(--secondary)', 'color-mix(in srgb, var(--primary) 55%, #120812)', 'color-mix(in srgb, var(--secondary) 50%, #0c0814)'];
    function buildWheel() {
        var list = S.wheel && S.wheel.length ? S.wheel : [{ label: S.mode === 'paid' ? 'تیکت' : 'شرکت کن!', w: 1, win: null }];
        var total = list.reduce(function (a, s) { return a + s.w; }, 0), a0 = 0, n = list.length;
        var fs = n <= 10 ? 10 : n <= 20 ? 8 : n <= 32 ? 6.2 : 5, maxc = n <= 10 ? 14 : n <= 20 ? 11 : 8;
        var svg = '<svg viewBox="-100 -100 200 200" aria-hidden="true">';
        segs = list.map(function (s, i) {
            var a1 = a0 + s.w / total * 360, seg = { a0: a0, a1: a1, mid: (a0 + a1) / 2, win: s.win, label: s.label };
            var p = function (a, r) { var t = (a - 90) * Math.PI / 180; return (Math.cos(t) * r).toFixed(2) + ' ' + (Math.sin(t) * r).toFixed(2); };
            var large = a1 - a0 > 180 ? 1 : 0;
            var path = n === 1 ? '<circle r="96"' : '<path d="M0 0 L' + p(a0, 96) + ' A96 96 0 ' + large + ' 1 ' + p(a1, 96) + ' Z"';
            var lab = s.label.length > maxc ? s.label.slice(0, maxc - 1) + '…' : s.label;
            svg += '<g class="rf-seg" data-i="' + i + '">' + path + ' style="fill:' + COLORS[(i === n - 1 && n > 1 && i % 4 === 0) ? 2 : i % 4] + '" stroke="rgba(0,0,0,.35)" stroke-width=".6"/>'
                + '<text class="rf-seg-t" style="font-size:' + fs + 'px" transform="rotate(' + (seg.mid - 90).toFixed(2) + ') translate(88 0)" text-anchor="end" dominant-baseline="middle">' + esc(lab) + '</text></g>';
            a0 = a1; return seg;
        });
        disc.innerHTML = svg + '<circle r="97" fill="none" stroke="rgba(255,255,255,.25)" stroke-width="1.5"/></svg>';
        if (!OFF && !CALM && S.status === 'active') disc.classList.add('idle'); else disc.classList.remove('idle');
    }
    function curRot() {
        var m = getComputedStyle(disc).transform; if (!m || m === 'none') return 0;
        var v = m.match(/matrix\(([^)]+)\)/); if (!v) return 0; v = v[1].split(',').map(parseFloat);
        return Math.atan2(v[1], v[0]) * 180 / Math.PI;
    }
    function segAt(r) { var a = ((-r % 360) + 360) % 360; for (var i = 0; i < segs.length; i++) if (a >= segs[i].a0 && a < segs[i].a1) return i; return 0; }
    function spinTo(idx, dur) {
        return new Promise(function (done) {
            var s = segs[idx]; if (!s) return done();
            var span = s.a1 - s.a0, aim = s.a0 + span * (.25 + Math.random() * .5);   // land somewhere inside the segment, not always the middle
            var base = curRot(); disc.classList.remove('idle'); disc.style.transition = 'none'; disc.style.transform = 'rotate(' + base + 'deg)'; void disc.offsetWidth;
            var target = base + (OFF ? 0 : 360 * (dur > 6 ? 8 : 5)) + (((-aim - base) % 360) + 360) % 360;
            rot = target;
            if (OFF) { disc.style.transform = 'rotate(' + target + 'deg)'; return done(); }
            wrap.classList.add('spinning');
            disc.style.transition = 'transform ' + dur + 's cubic-bezier(.12,.68,.06,1)';
            disc.style.transform = 'rotate(' + target + 'deg)';
            var last = -1, t0 = performance.now();
            (function watch() {
                var i = segAt(curRot());
                if (i !== last && pointer) { last = i; pointer.classList.remove('tick'); void pointer.offsetWidth; pointer.classList.add('tick'); }
                if (performance.now() - t0 < dur * 1000 + 80) requestAnimationFrame(watch); else { wrap.classList.remove('spinning'); done(); }
            })();
        });
    }
    function wait(ms) { return new Promise(function (r) { setTimeout(r, OFF ? 0 : ms); }); }

    /* ---------- confetti ---------- */
    function confetti() {
        if (OFF) return;
        var cv = $('#rfConfetti'); if (!cv) return;
        var w = cv.width = cv.offsetWidth, h = cv.height = cv.offsetHeight, ctx = cv.getContext('2d'), N = CALM ? 70 : 160, ps = [];
        var cols = [getComputedStyle(d).getPropertyValue('--primary').trim() || '#ff1f3d', getComputedStyle(d).getPropertyValue('--secondary').trim() || '#ff7a3d', '#ffc53d', '#ffffff', '#3ddc84'];
        for (var i = 0; i < N; i++) { var a = Math.random() * Math.PI * 2, v = 4 + Math.random() * 9; ps.push({ x: w / 2, y: h / 2, vx: Math.cos(a) * v, vy: Math.sin(a) * v - 4, r: 3 + Math.random() * 5, c: cols[i % cols.length], rot: Math.random() * 6, vr: (Math.random() - .5) * .4 }); }
        var t0 = performance.now();
        (function frame(t) {
            var k = (t - t0) / 2800; ctx.clearRect(0, 0, w, h);
            ps.forEach(function (p) { p.vy += .22; p.vx *= .99; p.x += p.vx; p.y += p.vy; p.rot += p.vr; ctx.save(); ctx.globalAlpha = Math.max(0, 1 - k); ctx.translate(p.x, p.y); ctx.rotate(p.rot); ctx.fillStyle = p.c; ctx.fillRect(-p.r, -p.r / 2, p.r * 2, p.r); ctx.restore(); });
            if (k < 1) requestAnimationFrame(frame); else ctx.clearRect(0, 0, w, h);
        })(t0);
    }

    /* ---------- results ---------- */
    var MED = ['🥇', '🥈', '🥉'];
    function renderResults() {
        var el = $('#rfResults'); if (!el) return;
        el.innerHTML = (S.results || []).map(function (r, i) {
            return '<div class="rf-res' + (S.me && S.people.some(function (p) { return p.me && p.name === r.name; }) ? ' me' : '') + '"><span class="m">' + (MED[i] || '🏅') + '</span><span><b>' + esc(r.name) + (r.ticket ? ' — تیکت #' + fa(r.ticket) : '') + '</b><small>' + esc(r.prize) + ' · شانسش ' + fa(r.chance) + '٪ بود</small></span></div>';
        }).join('');
    }
    function showWinner(r, i) {
        var box = $('#rfWinner'); if (!box) return Promise.resolve();
        var which = ['جایزه‌ی اول', 'جایزه‌ی دوم', 'جایزه‌ی سوم'][i] || ('جایزه‌ی ' + fa(i + 1));
        box.innerHTML = '<span class="w-medal">' + (MED[i] || '🏆') + '</span><small>برنده‌ی ' + which + '</small><b>' + esc(r.name) + '</b>' + (r.ticket ? '<small>تیکت شماره‌ی #' + fa(r.ticket) + '</small>' : '') + '<small class="w-prize">🎁 ' + esc(r.prize) + '</small>';
        box.hidden = false; void box.offsetWidth; box.classList.add('in'); confetti();
        return wait(3200).then(function () { box.classList.remove('in'); return wait(350); }).then(function () { box.hidden = true; });
    }
    function play() {
        if (busy || !S.results || !S.results.length) return;
        busy = true; $('#rfReplay').hidden = true; $('#rfResults').innerHTML = '';
        try { wrap.scrollIntoView({ behavior: OFF || CALM ? 'auto' : 'smooth', block: 'center' }); } catch (e) {}
        var note = $('#rfWheelNote'), seq = Promise.resolve();
        S.results.forEach(function (r, i) {
            seq = seq.then(function () {
                note.textContent = '🎡 در حال چرخش برای ' + (['جایزه‌ی اول', 'جایزه‌ی دوم', 'جایزه‌ی سوم'][i] || 'جایزه‌ی بعدی') + ': ' + r.prize + ' …';
                var idx = -1; segs.forEach(function (s, k) { if (s.win === i) idx = k; });
                return spinTo(idx < 0 ? 0 : idx, i === 0 ? 8 : 5.5);
            }).then(function () {
                $$seg().forEach(function (g) { g.classList.toggle('win', +g.getAttribute('data-i') === segs.findIndex(function (s) { return s.win === i; })); });
                return showWinner(r, i);
            }).then(function () { renderResults(); return wait(500); });
        });
        seq.then(function () { busy = false; note.textContent = '🏆 قرعه‌کشی تموم شد — به برنده‌ها تبریک!'; $('#rfReplay').hidden = false; renderResults(); });
    }
    function $$seg() { return [].slice.call(disc.querySelectorAll('.rf-seg')); }
    $('#rfReplay').addEventListener('click', function () { buildWheel(); play(); });

    /* ---------- live: at the draw time poll the server until the result is in ---------- */
    var live = false;
    function goLive() {
        if (live) return; live = true;
        $('#rfLive').hidden = false; if (cnt) cnt.hidden = true;
        $('#rfWheelNote').textContent = '⏳ قرعه‌کشی شروع شد… گردونه تا چند لحظه دیگه می‌چرخه';
        var tries = 0;
        (function poll() {
            api('raffle-state', {}).then(function (st) {
                if (st.ok) update(st);
                if (S.status === 'drawn') { try { localStorage.setItem('nx_rf_seen_' + S.id, '1'); } catch (e) {} refreshAll(); play(); return; }
                if (S.status === 'failed') { refreshAll(); $('#rfWheelNote').textContent = 'ℹ️ قرعه‌کشی به حد نصاب نرسید و برگزار نشد.'; $('#rfLive').hidden = true; return; }
                if (++tries < 60) setTimeout(poll, 2500);
            });
        })();
    }
    function refreshAll() { renderProg(); renderMine(); renderPeople(); if (!busy) buildWheel(); var stc = $('#rfStatus'); if (stc) { stc.className = 'rf-chip rf-st-' + S.status; stc.textContent = { active: 'در حال ثبت‌نام', drawn: 'برگزار شد', failed: 'برگزار نشد', cancelled: 'لغو شد', draft: 'پیش‌نویس' }[S.status] || S.status; } }

    // keep the counts fresh while people are joining
    setInterval(function () { if (S.status === 'active' && !live && document.visibilityState === 'visible') api('raffle-state', {}).then(function (st) { if (st.ok) { update(st); endAt = Date.now() + st.left * 1000; refreshAll(); } }); }, 25000);

    /* ---------- actions ---------- */
    document.addEventListener('click', function (e) {
        var j = e.target.closest('.rf-join');
        if (j) {
            if (j.hasAttribute('data-login') || !S.logged) { api('raffle-join', {}).then(function (st) { location.href = st.url || ((NX.base || '') + '/auth'); }); return; }
            j.disabled = true;
            api('raffle-join', {}).then(function (st) {
                j.disabled = false;
                if (st.login) { location.href = st.url; return; }
                if (!st.ok) { toast(st.error || 'خطا', 'error'); return; }
                update(st); refreshAll(); confetti(); toast('✅ شرکت کردی! موفق باشی 🍀', 'success');
            });
            return;
        }
        var c = e.target.closest('[data-copy]');
        if (c) { var v = c.getAttribute('data-copy'); (navigator.clipboard ? navigator.clipboard.writeText(v) : Promise.reject()).then(function () { toast('کپی شد ✅', 'success'); }, function () { prompt('کپی کن:', v); }); }
    });
    var buy = $('#rfBuy');
    if (buy) {
        var q = $('#rfQty'), tot = $('#rfTotal');
        var cap = function () { var used = S.me ? (S.me.tickets + S.me.pending) : 0; return S.cap ? Math.max(1, S.cap - used) : 1000; };
        var sync = function () { var v = Math.max(1, Math.min(cap(), parseInt(q.value, 10) || 1)); q.value = v; tot.textContent = money(v * S.price); };
        buy.addEventListener('click', function (e) { var b = e.target.closest('[data-q]'); if (b) { q.value = (parseInt(q.value, 10) || 1) + (+b.getAttribute('data-q')); sync(); } });
        q.addEventListener('input', sync); sync();
        var file = buy.querySelector('[type=file]');
        file.addEventListener('change', function () { var l = file.closest('.rf-file'); l.classList.toggle('has', !!file.files.length); l.querySelector('em').textContent = file.files.length ? '✅ ' + file.files[0].name : 'انتخاب عکس فیش واریزی'; });
        buy.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!S.logged) { api('raffle-buy', {}).then(function (st) { location.href = st.url || ((NX.base || '') + '/auth'); }); return; }
            if (!file.files.length) { toast('عکس فیش واریزی رو انتخاب کن.', 'error'); return; }
            var btn = buy.querySelector('[type=submit]'); btn.disabled = true; btn.textContent = 'در حال ارسال…';
            api('raffle-buy', new FormData(buy)).then(function (st) {
                btn.disabled = false; btn.textContent = 'ثبت خرید و ارسال فیش';
                if (st.login) { location.href = st.url; return; }
                if (!st.ok) { toast(st.error || 'خطا', 'error'); return; }
                update(st); refreshAll(); buy.reset(); file.dispatchEvent(new Event('change')); sync();
                toast('✅ خریدت ثبت شد؛ بعد از تأیید فیش، تیکت‌ها اینجا نشون داده می‌شن.', 'success');
            });
        });
    }

    /* ---------- start ---------- */
    refreshAll();
    var note = $('#rfWheelNote');
    if (S.status === 'drawn') {
        renderResults();
        // during the result window everyone who has not watched it yet sees the wheel spin by itself (once per browser)
        var seenKey = 'nx_rf_seen_' + S.id, seen = false;
        try { seen = !!localStorage.getItem(seenKey); } catch (e) {}
        if (S.show || ((S.resultLive || S.now - S.drawnAt < 600) && !seen)) {
            try { localStorage.setItem(seenKey, '1'); } catch (e) {}
            note.textContent = '🎡 قرعه‌کشی انجام شده — الان گردونه رو برات می‌چرخونیم…';
            setTimeout(play, 1100);
        } else { $('#rfReplay').hidden = false; note.textContent = '🏆 این قرعه‌کشی برگزار شده؛ می‌تونی دوباره پخشش کنی.'; }
    } else if (S.status === 'active') {
        note.textContent = S.mode === 'paid' ? 'روی گردونه شماره‌ی تیکت‌ها هست؛ سر ساعت یکی‌شون برنده می‌شه.' : 'اسم شرکت‌کننده‌ها روی گردونه‌ست؛ هر کی شانس بیشتری داره خونه‌ی بزرگ‌تری داره.';
        if (S.left <= 0) goLive();
    } else if (S.status === 'failed') note.textContent = 'ℹ️ این قرعه‌کشی به حد نصاب نرسید و برگزار نشد.';
})();
