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
    (function dress() {
        // chasing bulbs on the metal rim, a fixed glass reflection and a jewelled pointer
        var L = $('.rf-lights', wrap); if (!L) return; var h = '', N = 28;
        for (var i = 0; i < N; i++) h += '<i style="--a:' + (i * 360 / N).toFixed(2) + 'deg;--k:' + i + '"></i>';
        L.innerHTML = h;
        var place = function () { L.style.setProperty('--r', Math.max(0, L.offsetWidth / 2 - L.offsetWidth * .028) + 'px'); }; place(); window.addEventListener('resize', place);
        if (!$('.rf-gloss', wrap)) { var g = document.createElement('div'); g.className = 'rf-gloss'; g.setAttribute('aria-hidden', 'true'); disc.parentNode.insertBefore(g, disc.nextSibling); }
        if (pointer && !pointer.firstChild) pointer.innerHTML = '<svg viewBox="0 0 40 58" aria-hidden="true"><defs>'
            + '<linearGradient id="rfPg" x1="0" x2="1"><stop offset="0" stop-color="#8a5800"/><stop offset=".35" stop-color="#ffe9a6"/><stop offset=".6" stop-color="#ffc23d"/><stop offset="1" stop-color="#9a6400"/></linearGradient>'
            + '<radialGradient id="rfGem" cx=".35" cy=".3" r=".8"><stop offset="0" stop-color="#fff"/><stop offset=".3" style="stop-color:var(--primary)"/><stop offset="1" style="stop-color:color-mix(in srgb, var(--primary) 45%, #000)"/></radialGradient></defs>'
            + '<path d="M20 57 C15 44 3 33 3 20 A17 17 0 0 1 37 20 C37 33 25 44 20 57Z" fill="url(#rfPg)" stroke="#6b4300" stroke-width="1.2"/>'
            + '<circle cx="20" cy="20" r="10.5" fill="#2a1a00" opacity=".55"/><circle cx="20" cy="20" r="9" fill="url(#rfGem)"/><ellipse cx="16.5" cy="16" rx="3" ry="2.2" fill="#fff" opacity=".85"/></svg>';
    })();
    // rich alternating palette: brand colours + deep night + gold
    var PAL = [
        ['color-mix(in srgb, var(--primary) 55%, #000)', 'var(--primary)', 'color-mix(in srgb, var(--primary) 70%, #fff)'],
        ['#07060d', '#1a1530', '#2f2752'],
        ['color-mix(in srgb, var(--secondary) 55%, #000)', 'var(--secondary)', 'color-mix(in srgb, var(--secondary) 70%, #fff)'],
        ['#120a1c', '#2b1d44', '#47306e'],
        ['#7a4a00', '#e3a21a', '#ffd772']
    ];
    function buildWheel() {
        var base = S.wheel && S.wheel.length ? S.wheel : [{ label: S.mode === 'paid' ? 'تیکت' : 'شرکت کن!', w: 1, win: null }];
        var list = base.slice();
        if (base.length > 1 || (S.wheel && S.wheel.length)) while (list.length < 8) list = list.concat(base.slice(0, Math.min(base.length, 8 - list.length)));  // few names: repeat them so the wheel looks full
        var total = list.reduce(function (a, s) { return a + s.w; }, 0), a0 = 0, n = list.length, np = PAL.length;
        var pt = function (a, r) { var t = (a - 90) * Math.PI / 180; return (Math.cos(t) * r).toFixed(2) + ' ' + (Math.sin(t) * r).toFixed(2); };
        var defs = '<defs>' + PAL.map(function (c, k) {
            return '<radialGradient id="rfG' + k + '" cx="0" cy="0" r="96" gradientUnits="userSpaceOnUse"><stop offset=".18" style="stop-color:' + c[0] + '"/><stop offset=".72" style="stop-color:' + c[1] + '"/><stop offset="1" style="stop-color:' + c[2] + '"/></radialGradient>';
        }).join('') + '</defs>';
        var body = '', lines = '', pegs = '', labels = '';
        segs = list.map(function (s, i) {
            var a1 = a0 + s.w / total * 360, seg = { a0: a0, a1: a1, mid: (a0 + a1) / 2, win: s.win, label: s.label };
            var k = i % np; if (i === n - 1 && n > 1 && k === 0) k = 2;
            var path = n === 1 ? '<circle r="96"' : '<path d="M0 0 L' + pt(a0, 96) + ' A96 96 0 ' + (a1 - a0 > 180 ? 1 : 0) + ' 1 ' + pt(a1, 96) + ' Z"';
            body += '<g class="rf-seg" data-i="' + i + '">' + path + ' fill="url(#rfG' + k + ')"/></g>';
            if (n > 1) { lines += '<line x1="0" y1="0" x2="' + pt(a0, 96).replace(' ', '" y2="') + '"/>'; if (n <= 80) pegs += '<circle cx="' + pt(a0, 91).replace(' ', '" cy="') + '" r="' + (n <= 24 ? 1.9 : 1.3) + '"/>'; }
            var span = (a1 - a0) * Math.PI / 180, fs = Math.min(9.5, span * 58 * .78, Math.max(5.4, 60 / (s.label.length * .58)));
            if (fs >= 2.4 || n === 1) {
                if (n === 1) fs = 10;
                var maxc = Math.max(3, Math.floor(60 / (fs * .58))), lab = s.label.length > maxc ? s.label.slice(0, maxc - 1) + '…' : s.label;
                var left = seg.mid > 180 && n > 1;   // left half: turn the label over so it never reads upside down
                labels += '<text class="rf-seg-t' + (k === 4 ? ' dk' : '') + '" data-i="' + i + '" style="font-size:' + fs.toFixed(2) + 'px" transform="rotate(' + (left ? seg.mid + 90 : seg.mid - 90).toFixed(2) + ') translate(' + (left ? -85 : 85) + ' 0)" text-anchor="' + (left ? 'end' : 'start') + '" direction="rtl" dominant-baseline="middle">' + esc(lab) + '</text>';
            }
            a0 = a1; return seg;
        });
        disc.innerHTML = '<svg viewBox="-100 -100 200 200" aria-hidden="true">' + defs + body
            + '<g class="rf-lines">' + lines + '</g>' + labels
            + '<circle r="95.4" fill="none" stroke="rgba(0,0,0,.45)" stroke-width="1.6"/><circle r="93.6" fill="none" stroke="rgba(255,255,255,.12)" stroke-width=".6"/>'
            + '<g class="rf-pegs">' + pegs + '</g><circle r="27" fill="rgba(0,0,0,.35)"/></svg>';
        disc.classList.toggle('idle', !OFF && !CALM && S.status === 'active');
        disc.classList.toggle('dense', n > 60);
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
            var span = s.a1 - s.a0, aim = s.a0 + span * (.25 + Math.random() * .5);   // land somewhere inside the slice, not always the middle
            var base = curRot(); disc.classList.remove('idle'); disc.style.transition = 'none'; disc.style.transform = 'rotate(' + base + 'deg)'; void disc.offsetWidth;
            var target = base + (OFF ? 0 : 360 * (dur > 6 ? 9 : 6)) + (((-aim - base) % 360) + 360) % 360;
            rot = target;
            if (OFF) { disc.style.transform = 'rotate(' + target + 'deg)'; return done(); }
            wrap.classList.add('spinning');
            disc.style.transition = 'transform ' + dur + 's cubic-bezier(.1,.7,.05,1)';
            disc.style.transform = 'rotate(' + target + 'deg)';
            var last = -1, t0 = performance.now(), lastTick = 0;
            (function watch(now) {
                var i = segAt(curRot());
                if (i !== last && pointer && now - lastTick > 45) { last = i; lastTick = now; pointer.classList.remove('tick'); void pointer.offsetWidth; pointer.classList.add('tick'); }
                if (performance.now() - t0 < dur * 1000 + 80) requestAnimationFrame(watch); else { wrap.classList.remove('spinning'); done(); }
            })(t0);
        });
    }
    function winIdx(i) {   // the winner may own several slices (repeats / tickets): pick one of them
        var all = []; segs.forEach(function (s, k) { if (s.win === i) all.push(k); });
        return all.length ? all[Math.floor(Math.random() * all.length)] : -1;
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
            return '<div class="rf-res' + (r.me ? ' me' : '') + '"><span class="m">' + (MED[i] || '🏅') + '</span><span><b>' + esc(r.name) + (r.ticket ? ' — تیکت #' + fa(r.ticket) : '') + '</b><small>' + esc(r.prize) + ' · شانسش ' + fa(r.chance) + '٪ بود</small></span></div>';
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
        var note = $('#rfWheelNote'), seq = Promise.resolve(), hit = 0;
        S.results.forEach(function (r, i) {
            seq = seq.then(function () {
                note.textContent = '🎡 در حال چرخش برای ' + (['جایزه‌ی اول', 'جایزه‌ی دوم', 'جایزه‌ی سوم'][i] || 'جایزه‌ی بعدی') + ': ' + r.prize + ' …';
                var idx = winIdx(i); hit = idx < 0 ? 0 : idx;
                $$seg().forEach(function (g) { g.classList.remove('win'); });
                return spinTo(hit, i === 0 ? 8.5 : 6);
            }).then(function () {
                $$seg().forEach(function (g) { g.classList.toggle('win', +g.getAttribute('data-i') === hit); });
                return showWinner(r, i);
            }).then(function () { renderResults(); return wait(500); });
        });
        seq.then(function () { busy = false; note.textContent = '🏆 قرعه‌کشی تموم شد — به برنده‌ها تبریک!'; $('#rfReplay').hidden = false; renderResults(); });
    }
    function $$seg() { return [].slice.call(disc.querySelectorAll('.rf-seg, .rf-seg-t')); }
    function testSpin() {   // admin preview before the draw: a random slice, nothing is saved
        if (busy || segs.length < 1) return; busy = true;
        var note = $('#rfWheelNote'), k = Math.floor(Math.random() * segs.length);
        $$seg().forEach(function (g) { g.classList.remove('win'); });
        note.textContent = '🎡 چرخش آزمایشی… (نتیجه‌ی واقعی نیست و ذخیره نمی‌شه)';
        spinTo(k, 6).then(function () {
            $$seg().forEach(function (g) { g.classList.toggle('win', +g.getAttribute('data-i') === k); });
            note.textContent = '🎯 گردونه روی «' + segs[k].label + '» ایستاد — فقط آزمایشی بود.'; busy = false;
        });
    }
    $('#rfReplay').addEventListener('click', function () { if (S.results && S.results.length) { buildWheel(); play(); } else if (S.admin) testSpin(); });

    /* ---------- live: at the draw time poll the server until the result is in ---------- */
    var live = false;
    function goLive() {
        if (live || S.admin) return; live = true;
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
    setInterval(function () { if (!S.admin && S.status === 'active' && !live && document.visibilityState === 'visible') api('raffle-state', {}).then(function (st) { if (st.ok) { update(st); endAt = Date.now() + st.left * 1000; refreshAll(); } }); }, 25000);

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
    if (S.admin) {   // panel: the owner spins it whenever they like, with full names
        renderResults();
        $('#rfReplay').hidden = false;
        note.textContent = S.status === 'drawn' ? '🏆 قرعه‌کشی برگزار شده — دکمه رو بزن تا گردونه بچرخه و برنده‌ها رو ببینی.'
            : '👥 ' + fa(S.participants) + ' شرکت‌کننده روی گردونه‌ان. می‌تونی آزمایشی بچرخونیش؛ برنده‌ی واقعی سر ساعت (یا با «برگزاری همین الان») مشخص می‌شه.';
        if (S.status !== 'drawn') $('#rfReplay').textContent = '🎡 چرخش آزمایشی';
    } else if (S.status === 'drawn') {
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
