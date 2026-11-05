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
        if (S.mine && S.mine.length) h += '<div class="lw-wins">' + S.mine.map(function (x) { return '<span class="lw-win k-' + x.kind + '"><b>' + esc(x.label) + '</b><small>' + esc(x.when) + '</small></span>'; }).join('') + '</div>';
        box.innerHTML = h;
    }
    function bar(have, need) { var p = need ? Math.min(100, have / need * 100) : 0; return '<span class="lw-bar"><i style="width:' + p.toFixed(1) + '%"></i></span>'; }
    function renderRules() {
        var el = $('#lwRules'); if (!el) return;
        var R = S.rules, me = S.me, h = '';
        if (R.signup) h += '<div class="lw-rule' + (me ? ' done' : '') + '"><span class="ic">📝</span><div><b>ثبت‌نام در سایت</b><small>' + fa(R.signup) + ' چرخش رایگان' + (me ? ' — گرفتی ✅' : '') + '</small></div></div>';
        if (R.daily) h += '<div class="lw-rule"><span class="ic">☀️</span><div><b>هر روز</b><small>' + fa(R.daily) + ' چرخش رایگان روزانه</small></div></div>';
        if (R.buy_every) h += '<div class="lw-rule"><span class="ic">🛍️</span><div><b>هر ' + fa(R.buy_every) + ' خرید = ' + fa(R.buy_spins) + ' چرخش</b><small>' + (me ? fa(me.buys) + ' خرید ثبت شده · ' + fa(me.buy_next) + ' خرید تا چرخش بعدی' : 'خریدهای فروشگاه حساب می‌شن') + '</small>' + (me ? bar(R.buy_every - me.buy_next, R.buy_every) : '') + '</div></div>';
        if (R.invite_every) h += '<div class="lw-rule"><span class="ic">🤝</span><div><b>هر ' + fa(R.invite_every) + ' دعوت = ' + fa(R.invite_spins) + ' چرخش</b><small>' + (me ? fa(me.invites) + ' نفر با لینکت ثبت‌نام کردن · ' + fa(me.invite_next) + ' نفر تا چرخش بعدی' : 'دوستات با لینک تو ثبت‌نام کنن') + '</small>' + (me ? bar(R.invite_every - me.invite_next, R.invite_every) + '<div class="rf-invite"><input readonly value="' + esc(me.invite) + '"><button type="button" class="btn btn-primary btn-sm" data-copy="' + esc(me.invite) + '">کپی لینک</button></div>' : '') + '</div></div>';
        if (R.max_user) h += '<p class="muted small" style="margin:8px 0 0">هر نفر روی هم حداکثر ' + fa(R.max_user) + ' بار می‌تونه بچرخونه.</p>';
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
    function renderRecent() {
        var el = $('#lwRecent'), box = $('#lwRecentBox'); if (!el) return;
        if (box) box.hidden = !S.recent.length;
        el.innerHTML = S.recent.map(function (x) { return '<div class="lw-feed-i"><span>🏆</span><b>' + esc(x.name) + '</b><em>' + esc(x.label) + '</em><small>' + esc(x.when) + '</small></div>'; }).join('');
    }
    function renderAll() { renderMine(); renderRules(); renderItems(); renderRecent(); }

    function celebrate(it) {
        var html;
        if (it.kind === 'prize') {
            window.NXWheel.party();
            html = '<span class="w-medal">🎉</span><small>تبریک! برنده شدی</small><b>' + esc((it.emoji ? it.emoji + ' ' : '') + it.label) + '</b>'
                + (it.code ? '<div class="w-code"><code>' + esc(it.code) + '</code></div>' : '')
                + '<small style="margin-top:8px">' + (S.admin ? 'نتیجه‌ی چرخش تو' : 'جایزه‌ت توی اعلان‌ها هم ثبت شد') + '</small>';
        } else if (it.kind === 'again') {
            html = '<span class="w-medal">🔁</span><small>شانس دوباره!</small><b>' + esc(it.label) + '</b><small>یه چرخش دیگه بهت اضافه شد</small>';
        } else {
            html = '<span class="w-medal">😅</span><small>این دفعه نشد…</small><b>' + esc(it.label) + '</b><small>دفعه‌ی بعد حتماً!</small>';
        }
        return W.reveal(html, it.kind === 'prize' ? 4200 : 2600);
    }
    function lastLine(it) {
        var el = $('#lwLast'); if (!el) return;
        var row = document.createElement('div'); row.className = 'lw-last-i k-' + it.kind;
        row.innerHTML = '<span>' + esc(it.emoji || (it.kind === 'prize' ? '🎁' : it.kind === 'again' ? '🔁' : '😅')) + '</span><b>' + esc(it.label) + '</b>' + (it.code ? '<code>' + esc(it.code) + '</code>' : '') + '<small>همین الان</small>';
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
                note.textContent = r.item.kind === 'prize' ? '🎉 ' + r.item.label + '!' : r.item.kind === 'again' ? '🔁 یه چرخش دیگه گرفتی' : '😅 این دفعه پوچ بود';
                lastLine(r.item);
                return celebrate(r.item);
            }).then(function () {
                if (r.state) { Object.keys(r.state).forEach(function (k) { S[k] = r.state[k]; }); }
                busy = false; renderAll();
            });
        });
    }
    if (btn) btn.addEventListener('click', spin);
    document.addEventListener('click', function (e) {
        if (e.target.closest('.lw-login')) { call('wheel-spin').then(function (r) { location.href = r.url || ((NX.base || '') + '/auth'); }); return; }
        var c = e.target.closest('[data-copy]');
        if (c && c.closest('#lwRules')) { var v = c.getAttribute('data-copy'); (navigator.clipboard ? navigator.clipboard.writeText(v) : Promise.reject()).then(function () { toast('لینک کپی شد ✅', 'success'); }, function () { prompt('کپی کن:', v); }); }
    });

    build(); renderAll();
    note.textContent = S.admin ? (S.audience === 'private' ? '🔒 این گردونه فقط برای خودته؛ هر چند بار خواستی بچرخون.' : '🧪 چرخش‌های اینجا آزمایشی‌ان و از موجودی جایزه‌ها کم نمی‌کنن.')
        : !S.open ? 'ℹ️ این گردونه الان فعال نیست.' : S.logged ? (S.me.left ? '🍀 آماده‌ای؟ دکمه رو بزن!' : 'برای چرخش بیشتر، راه‌های کنار صفحه رو ببین.') : '🔐 برای چرخوندن وارد حسابت شو.';
})();
