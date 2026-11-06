/* NEXUS | HUB — lucky wheel page (site) and owner's wheel (panel).
   The server picks the item (exact chances, CSPRNG); the wheel animates to it and celebrates. */
(function () {
    'use strict';
    var S = window.NX_WHEEL; if (!S || !window.NXWheel) return;
    var $ = function (s, c) { return (c || document).querySelector(s); };
    var d = document.documentElement, OFF = d.classList.contains('perf-off');
    var fa = function (s) { return String(s).replace(/\d/g, function (x) { return '۰۱۲۳۴۵۶۷۸۹'[x]; }); };
    var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
    var toast = function (m, t) { if (window.nxToast) window.nxToast(m, t); else if (window.NXA && NXA.toast) NXA.toast(m, t); else alert(m); };
    var NX = window.NX || {};
    var W = window.NXWheel($('#lwWheel'), { empty: 'گزینه‌ای نیست' });
    var btn = $('#lwSpin'), note = $('#lwNote'), busy = false;

    function post(url, data) {
        var f = new FormData(); Object.keys(data).forEach(function (k) { f.append(k, data[k]); });
        return fetch(url, { method: 'POST', body: f, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'خطای شبکه' }; }); }, function () { return { ok: false, error: 'اتصال برقرار نشد' }; });
    }
    function call(act) {
        if (S.admin) return post(S.spinUrl, { _csrf: S.csrf, ajax: act });
        return post((NX.base || '') + '/api/' + act, { id: S.id, _csrf: NX.csrf || '' });
    }

    function build() { W.build(S.items.map(function (it) { return { label: it.label, emoji: it.emoji, w: 1, out: it.out }; })); }

    function renderMine() {
        var box = $('#lwMine'), chip = $('#lwLeftChip'), me = S.me;
        var left = S.admin ? null : (me ? me.left : 0);
        if (chip) chip.innerHTML = S.admin ? '🔓 چرخش نامحدود' : (me ? '🎟️ ' + fa(left) + ' چرخش' : '');
        if (btn) {
            btn.disabled = busy || (!S.admin && S.logged && (!S.open || left <= 0));
            $('#lwSpinT').textContent = busy ? 'داره می‌چرخه…' : S.admin ? 'بچرخون!' : !S.logged ? 'ورود و چرخوندن' : !S.open ? 'گردونه فعال نیست' : left > 0 ? 'بچرخون! (' + fa(left) + ' چرخش داری)' : 'فعلاً چرخش نداری';
            btn.classList.toggle('ready', !btn.disabled);
        }
        if (!box) return;
        if (!S.logged) { box.innerHTML = '<h3>🎟️ چرخش‌های تو</h3><p class="muted small">برای چرخوندن باید عضو سایت باشی' + (S.rules.signup ? ' — با ثبت‌نام <b>' + fa(S.rules.signup) + ' چرخش رایگان</b> می‌گیری!' : '.') + '</p><button type="button" class="btn btn-primary btn-block lw-login">ورود / ثبت‌نام</button>'; return; }
        var h = '<h3>🎟️ چرخش‌های تو</h3><div class="lw-big"><b>' + fa(me.left) + '</b><span>چرخش آماده</span></div>';
        h += '<div class="lw-stat"><span>چرخش‌های استفاده‌شده</span><b>' + fa(me.used) + '</b></div>';
        if (S.mine && S.mine.length) h += '<div class="lw-wins">' + S.mine.map(function (x) {
            var st = { pending: x.left > 0 ? '<em class="wait">⏳ ' + leftText(x.left) + ' مهلت</em>' : '', delivered: '<em class="ok">✅ تحویل شد</em>', expired: '<em class="over">⌛ باطل شد</em>',
                active: '<em class="ok">🏷️ آماده‌ی استفاده</em>', used: '<em class="ok">🛍️ استفاده شد</em>' }[x.status] || '';
            return '<span class="lw-win k-' + x.kind + '"><b>' + esc(x.label) + '</b><small>' + esc(x.when) + '</small>' + st + '</span>';
        }).join('') + '</div>';
        var open = (S.mine || []).filter(function (x) { return x.status === 'pending' && x.left > 0; });
        if (S.claim && open.length) h += '<div class="pc-box"><div class="pc-head"><span class="pc-ic">📩</span><div><b>جایزه‌ت رو بگیر: ' + esc(open[0].label) + '</b><small>' + leftText(open[0].left) + ' وقت داری از تلگرام بهمون پیام بدی؛ بعدش جایزه باطل می‌شه.</small></div><div class="pc-ids">' + S.claim.buttons + '</div></div></div>';
        if ((S.mine || []).some(function (x) { return x.status || x.kind === 'discount'; })) h += '<a class="lw-allprizes" href="' + esc(S.claim ? S.claim.prizes : (NX.base || '') + '/prizes') + '">🎁 همه‌ی جایزه‌های من ←</a>';
        box.innerHTML = h;
    }
    function leftText(sec) { var h = Math.floor(sec / 3600), m = Math.floor(sec % 3600 / 60); return h ? fa(h) + ' ساعت' + (m ? ' و ' + fa(m) + ' دقیقه' : '') : fa(Math.max(1, m)) + ' دقیقه'; }
    function bar(have, need) { var p = need ? Math.min(100, have / need * 100) : 0; return '<span class="lw-bar"><i style="width:' + p.toFixed(1) + '%"></i></span>'; }
    function renderRules() {
        var el = $('#lwRules'); if (!el) return;
        var R = S.rules, me = S.me, h = '';
        if (R.signup) h += '<div class="lw-rule' + (me ? ' done' : '') + '"><span class="ic">📝</span><div><b>ثبت‌نام در سایت</b><small>' + fa(R.signup) + ' چرخش رایگان' + (me ? ' — گرفتی ✅' : '') + '</small></div></div>';
        if (R.daily) h += '<div class="lw-rule"><span class="ic">☀️</span><div><b>هر روز</b><small>' + fa(R.daily) + ' چرخش رایگان روزانه</small></div></div>';
        if (R.buy_every) h += '<div class="lw-rule"><span class="ic">🛍️</span><div><b>هر ' + fa(R.buy_every) + ' خرید = ' + fa(R.buy_spins) + ' چرخش</b><small>' + (me ? fa(me.buys) + ' خرید ثبت شده · ' + fa(me.buy_next) + ' خرید تا چرخش بعدی' : 'خریدهای فروشگاه حساب می‌شن') + '</small>' + (me ? bar(R.buy_every - me.buy_next, R.buy_every) : '') + '</div></div>';
        if (R.invite_every) h += '<div class="lw-rule"><span class="ic">🤝</span><div><b>هر ' + fa(R.invite_every) + ' دعوت = ' + fa(R.invite_spins) + ' چرخش</b><small>' + (me ? fa(me.invites) + ' نفر با لینکت ثبت‌نام کردن · ' + fa(me.invite_next) + ' نفر تا چرخش بعدی' : 'دوستات با لینک تو ثبت‌نام کنن') + '</small>' + (me ? bar(R.invite_every - me.invite_next, R.invite_every) + '<div class="rf-invite"><input readonly value="' + esc(me.invite) + '"><button type="button" class="btn btn-primary btn-sm" data-copy="' + esc(me.invite) + '">کپی لینک</button></div>' : '') + '</div></div>';
        if (R.max_user) h += '<p class="muted small" style="margin:8px 0 0">هر نفر روی هم حداکثر ' + fa(R.max_user) + ' بار می‌تونه بچرخونه.</p>';
        if (S.claim) h += '<div class="lw-claimnote">🏆 اگه جایزه بردی، تا <b>' + fa(S.claim.hours) + ' ساعت</b> از تلگرام بهمون پیام بده؛ بعدش جایزه باطل می‌شه. جایزه‌های تخفیفی خودکار موقع خرید اعمال می‌شن.</div>';
        el.innerHTML = h || '<p class="muted small">چرخش‌ها رو مدیر سایت هدیه می‌ده.</p>';
    }
    function renderItems() {
        var el = $('#lwItems'); if (!el) return;
        var show = S.items.some(function (it) { return it.chance != null; });
        el.innerHTML = S.items.map(function (it) {
            return '<div class="lw-item k-' + it.kind + (it.out ? ' out' : '') + '"><span class="e">' + esc(it.emoji || (it.kind === 'empty' ? '😅' : '🎁')) + '</span><b>' + esc(it.label) + '</b>'
                + (it.out ? '<small>تموم شد</small>' : show && it.chance != null ? '<small>' + fa(it.chance) + '٪</small>' : '') + '</div>';
        }).join('');
    }
    function renderAll() { renderMine(); renderRules(); renderItems(); }

    function celebrate(it) {
        var html;
        if (it.kind === 'prize') {
            window.NXWheel.party();
            html = '<span class="w-medal">🎉</span><small>تبریک! برنده شدی</small><b>' + esc((it.emoji ? it.emoji + ' ' : '') + it.label) + '</b>'
                + (it.code ? '<div class="w-code"><code>' + esc(it.code) + '</code></div>' : '')
                + '<small style="margin-top:8px">' + (S.admin ? 'نتیجه‌ی چرخش تو' : S.claim ? '📩 تا ' + fa(S.claim.hours) + ' ساعت از تلگرام بهمون پیام بده' : 'جایزه‌ت توی اعلان‌ها هم ثبت شد') + '</small>'
                + (!S.admin && S.claim ? '<div class="pc-ids" style="justify-content:center">' + S.claim.buttons + '</div>' : '');
        } else if (it.kind === 'discount') {
            window.NXWheel.party();
            html = '<span class="w-medal">🏷️</span><small>تبریک! کد تخفیف بردی</small><b>' + esc((it.emoji ? it.emoji + ' ' : '') + it.label) + '</b>'
                + (it.coupon && it.coupon.code ? '<div class="w-code"><code>' + esc(it.coupon.code) + '</code></div>' : '')
                + '<small style="margin-top:8px">' + esc(it.disc || '') + '</small><small>' + (S.admin ? 'نتیجه‌ی چرخش تو' : '🛍️ موقع خرید از فروشگاه خودکار اعمال می‌شه') + '</small>';
        } else if (it.kind === 'again') {
            html = '<span class="w-medal">🔁</span><small>شانس دوباره!</small><b>' + esc(it.label) + '</b><small>یه چرخش دیگه بهت اضافه شد</small>';
        } else {
            html = '<span class="w-medal">😅</span><small>این دفعه نشد…</small><b>' + esc(it.label) + '</b><small>دفعه‌ی بعد حتماً!</small>';
        }
        return W.reveal(html, it.kind === 'prize' || it.kind === 'discount' ? 4600 : 2600);
    }
    function lastLine(it) {
        var el = $('#lwLast'); if (!el) return;
        var row = document.createElement('div'); row.className = 'lw-last-i k-' + it.kind;
        var code = it.coupon && it.coupon.code ? it.coupon.code : it.code;
        row.innerHTML = '<span>' + esc(it.emoji || (it.kind === 'prize' ? '🎁' : it.kind === 'discount' ? '🏷️' : it.kind === 'again' ? '🔁' : '😅')) + '</span><b>' + esc(it.label) + '</b>' + (code ? '<code>' + esc(code) + '</code>' : '') + '<small>همین الان</small>';
        el.insertBefore(row, el.firstChild);
        while (el.children.length > 5) el.removeChild(el.lastChild);
    }

    function spin() {
        if (busy) return;
        if (!S.admin && !S.logged) { call('wheel-spin').then(function (r) { location.href = r.url || ((NX.base || '') + '/auth'); }); return; }
        busy = true; renderMine(); W.mark(-1);
        note.textContent = '🎡 داره می‌چرخه…';
        call(S.admin ? 'spin' : 'wheel-spin').then(function (r) {
            if (!r.ok) { busy = false; renderMine(); note.textContent = ''; if (r.login) { location.href = r.url; return; } toast(r.error || 'خطا', 'error'); return; }
            W.spinTo(r.index, OFF ? 0 : 7).then(function () {
                W.mark(r.index);
                note.textContent = r.item.kind === 'prize' || r.item.kind === 'discount' ? '🎉 ' + r.item.label + '!' : r.item.kind === 'again' ? '🔁 یه چرخش دیگه گرفتی' : '😅 این دفعه پوچ بود';
                lastLine(r.item);
                return celebrate(r.item);
            }).then(function () {
                if (r.state) { Object.keys(r.state).forEach(function (k) { S[k] = r.state[k]; }); }
                busy = false; renderAll();
            });
        });
    }
    if (btn) btn.addEventListener('click', spin);

    /* ---------- owner's private wheel: 1st, 2nd, 3rd … in one go ---------- */
    var drawBtn = $('#lwDraw');
    if (drawBtn && S.admin) {
        var places = $('#lwPlaces'), nPick = 3, MED = ['🥇', '🥈', '🥉'];
        places.addEventListener('click', function (e) { var b = e.target.closest('[data-n]'); if (!b) return; nPick = +b.dataset.n; [].forEach.call(places.querySelectorAll('[data-n]'), function (x) { x.classList.toggle('on', x === b); }); });
        drawBtn.addEventListener('click', function () {
            if (busy) return; busy = true; renderMine(); drawBtn.disabled = true;
            var pod = $('#lwPodium'); pod.innerHTML = '';
            post(S.spinUrl, { _csrf: S.csrf, ajax: 'draw', n: nPick }).then(function (r) {
                if (!r.ok) { busy = false; drawBtn.disabled = false; renderMine(); toast(r.error || 'خطا', 'error'); return; }
                var seq = Promise.resolve();
                r.places.forEach(function (pl, k) {
                    seq = seq.then(function () { note.textContent = '🎡 در حال چرخش برای نفر ' + pl.place + '…'; W.mark(-1); return W.spinTo(pl.index, OFF ? 0 : (k === 0 ? 6 : 4.5)); })
                        .then(function () {
                            W.mark(pl.index);
                            var row = document.createElement('div'); row.className = 'lw-pod-i'; row.style.setProperty('--i', k);
                            row.innerHTML = '<span>' + (MED[k] || '🏅') + '</span><small>نفر ' + esc(pl.place) + '</small><b>' + esc((pl.emoji ? pl.emoji + ' ' : '') + pl.label) + '</b>';
                            pod.appendChild(row);
                            if (k === r.places.length - 1) window.NXWheel.party();
                            return W.reveal('<span class="w-medal">' + (MED[k] || '🏅') + '</span><small>نفر ' + esc(pl.place) + '</small><b>' + esc(pl.label) + '</b>', k === r.places.length - 1 ? 3200 : 1800);
                        });
                });
                seq.then(function () { busy = false; drawBtn.disabled = false; renderMine(); note.textContent = '🏅 قرعه تموم شد — نتیجه‌ها پایین ثبت شد.'; });
            });
        });
    }
    document.addEventListener('click', function (e) {
        if (e.target.closest('.lw-login')) { call('wheel-spin').then(function (r) { location.href = r.url || ((NX.base || '') + '/auth'); }); return; }
    });

    build(); renderAll();
    note.textContent = S.admin ? (S.audience === 'private' ? '🔒 این گردونه فقط برای خودته؛ هر چند بار خواستی بچرخون.' : '🧪 چرخش‌های اینجا آزمایشی‌ان و از موجودی جایزه‌ها کم نمی‌کنن.')
        : !S.open ? 'ℹ️ این گردونه الان فعال نیست.' : S.logged ? (S.me.left ? '🍀 آماده‌ای؟ دکمه رو بزن!' : 'برای چرخش بیشتر، راه‌های کنار صفحه رو ببین.') : '🔐 برای چرخوندن وارد حسابت شو.';
})();
