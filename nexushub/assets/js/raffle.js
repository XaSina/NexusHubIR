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

    /* ---------- the wheel (shared engine: assets/js/wheel.js) ---------- */
    var wrap = $('#rfWheelWrap'), busy = false;
    var W = window.NXWheel(wrap, { empty: S.mode === 'paid' ? 'تیکت' : 'شرکت کن!' });
    function buildWheel() { W.build(S.wheel || []); }   // one slice per participant (or ticket), never repeated
    function wait(ms) { return new Promise(function (r) { setTimeout(r, OFF ? 0 : ms); }); }
    var party = function () { if (window.NXWheel) window.NXWheel.party(); };

    /* ---------- results: they slide in and the card grows smoothly (nothing jumps) ---------- */
    var MED = ['🥇', '🥈', '🥉'];
    function smooth(el, html) {
        if (!el) return;
        var h0 = el.offsetHeight; el.innerHTML = html;
        if (OFF) return;
        var h1 = el.scrollHeight; if (h0 === h1) return;
        el.style.height = h0 + 'px'; el.style.overflow = 'hidden'; void el.offsetHeight;
        el.style.transition = 'height .7s cubic-bezier(.16,1,.3,1)'; el.style.height = h1 + 'px';
        setTimeout(function () { el.style.height = ''; el.style.overflow = ''; el.style.transition = ''; }, 720);
    }
    function replay(on) { var b = $('#rfReplay'); if (!b) return; b.hidden = false; b.classList.toggle('show', !!on); b.disabled = !on; }
    function renderResults() {
        smooth($('#rfResults'), (S.results || []).map(function (r, i) {
            return '<div class="rf-res' + (r.me ? ' me' : '') + '" style="--i:' + i + '"><span class="m">' + (MED[i] || '🏅') + '</span><span class="t"><b>' + esc(r.name) + (r.me ? ' <em>(تو)</em>' : '') + '</b><small>🎁 ' + esc(r.prize) + (r.ticket ? ' · تیکت #' + fa(r.ticket) : '') + '</small></span><span class="c">' + fa(r.chance) + '٪<small>شانس</small></span></div>';
        }).join(''));
    }
    function showWinner(r, i) {
        var which = ['جایزه‌ی اول', 'جایزه‌ی دوم', 'جایزه‌ی سوم'][i] || ('جایزه‌ی ' + fa(i + 1));
        party();
        return W.reveal('<span class="w-medal">' + (MED[i] || '🏆') + '</span><small>برنده‌ی ' + which + '</small><b>' + esc(r.name) + '</b>' + (r.ticket ? '<small>تیکت شماره‌ی #' + fa(r.ticket) + '</small>' : '') + '<span class="w-prize">🎁 ' + esc(r.prize) + '</span>', 3600);
    }
    function play() {
        if (busy || !S.results || !S.results.length) return;
        busy = true; replay(false);
        try { wrap.scrollIntoView({ behavior: OFF || CALM ? 'auto' : 'smooth', block: 'center' }); } catch (e) {}
        var note = $('#rfWheelNote'), seq = Promise.resolve();
        S.results.forEach(function (r, i) {
            var hit = 0;
            seq = seq.then(function () {
                note.textContent = '🎡 در حال چرخش برای ' + (['جایزه‌ی اول', 'جایزه‌ی دوم', 'جایزه‌ی سوم'][i] || 'جایزه‌ی بعدی') + ': ' + r.prize + ' …';
                hit = Math.max(0, W.pick(function (s) { return s.win === i; }));
                return W.spinTo(hit, i === 0 ? 8.5 : 6);
            }).then(function () { W.mark(hit); return showWinner(r, i); })
              .then(function () { return wait(400); });
        });
        seq.then(function () { busy = false; note.textContent = '🏆 قرعه‌کشی تموم شد — به برنده‌ها تبریک!'; renderResults(); setTimeout(function () { replay(true); }, OFF ? 0 : 500); });
    }
    function testSpin() {   // admin preview before the draw: a random slice, nothing is saved
        if (busy || !W.segs.length) return; busy = true; replay(false);
        var note = $('#rfWheelNote'), k = Math.floor(Math.random() * W.segs.length);
        note.textContent = '🎡 چرخش آزمایشی… (نتیجه‌ی واقعی نیست و ذخیره نمی‌شه)';
        W.spinTo(k, 6).then(function () { W.mark(k); note.textContent = '🎯 گردونه روی «' + W.segs[k].label + '» ایستاد — فقط آزمایشی بود.'; busy = false; replay(true); });
    }
    $('#rfReplay').addEventListener('click', function () { if (S.results && S.results.length) { W.mark(-1); play(); } else if (S.admin) testSpin(); });

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
                update(st); refreshAll(); party(); toast('✅ شرکت کردی! موفق باشی 🍀', 'success');
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
        replay(true);
        note.textContent = S.status === 'drawn' ? '🏆 قرعه‌کشی برگزار شده — دکمه رو بزن تا گردونه بچرخه و برنده‌ها رو ببینی.'
            : '👥 ' + fa(S.participants) + ' شرکت‌کننده روی گردونه‌ان. می‌تونی آزمایشی بچرخونیش؛ برنده‌ی واقعی سر ساعت (یا با «برگزاری همین الان») مشخص می‌شه.';
        if (S.status !== 'drawn') $('#rfReplay').textContent = '🎡 چرخش آزمایشی';
    } else if (S.status === 'drawn') {
        // during the result window everyone who has not watched it yet sees the wheel spin by itself (once per browser)
        var seenKey = 'nx_rf_seen_' + S.id, seen = false;
        try { seen = !!localStorage.getItem(seenKey); } catch (e) {}
        if (S.show || ((S.resultLive || S.now - S.drawnAt < 600) && !seen)) {
            try { localStorage.setItem(seenKey, '1'); } catch (e) {}
            note.textContent = '🎡 قرعه‌کشی انجام شده — الان گردونه رو برات می‌چرخونیم…';
            replay(false); setTimeout(play, 1100);
        } else { renderResults(); replay(true); note.textContent = '🏆 این قرعه‌کشی برگزار شده؛ می‌تونی دوباره پخشش کنی.'; }
    } else if (S.status === 'active') {
        note.textContent = S.mode === 'paid' ? 'روی گردونه شماره‌ی تیکت‌ها هست؛ سر ساعت یکی‌شون برنده می‌شه.' : 'اسم شرکت‌کننده‌ها روی گردونه‌ست؛ هر کی شانس بیشتری داره خونه‌ی بزرگ‌تری داره.';
        if (S.left <= 0) goLive();
    } else if (S.status === 'failed') note.textContent = 'ℹ️ این قرعه‌کشی به حد نصاب نرسید و برگزار نشد.';
})();
