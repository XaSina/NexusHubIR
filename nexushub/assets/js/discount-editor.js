/* NEXUS | HUB panel — discount settings for a prize (lucky wheel items, raffle prizes).
   Each row keeps its settings as JSON in a hidden input (.dsc-data); this draws a small panel under the row to edit them. */
(function () {
    'use strict';
    var SHOP = window.NX_SHOP || { cats: [], products: [] };
    var fa = function (s) { return String(s).replace(/\d/g, function (x) { return '۰۱۲۳۴۵۶۷۸۹'[x]; }); };
    var num = function (v) { return parseFloat(String(v || '').replace(/[۰-۹]/g, function (c) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(c); }).replace(/[٬,]/g, '')) || 0; };
    var esc = function (s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
    function read(inp) { try { var d = JSON.parse(inp.value || '{}'); return d && typeof d === 'object' ? d : {}; } catch (e) { return {}; } }
    function summary(d) {
        var s = fa(d.pct || 0) + '٪ تخفیف ' + (d.scope === 'cats' ? 'روی ' + fa((d.ids || []).length) + ' دسته' : d.scope === 'products' ? 'روی ' + fa((d.ids || []).length) + ' محصول' : 'روی همه‌ی محصولات');
        if (d.min) s += ' · حداقل خرید ' + fa(Math.round(d.min).toLocaleString('en-US')) + ' تومان';
        if (d.max) s += ' · سقف ' + fa(Math.round(d.max).toLocaleString('en-US')) + ' تومان';
        s += ' · ' + (d.days ? 'اعتبار ' + fa(d.days) + ' روز' : 'بدون تاریخ انقضا');
        return s;
    }
    function chips(list, key, label, sel) {
        if (!list.length) return '<small class="muted">' + (key === 'cats' ? 'هنوز دسته‌ای توی فروشگاه نساختی.' : 'هنوز محصولی نیست.') + '</small>';
        return '<div class="dsc-chips" data-k="' + key + '">' + list.map(function (x) { return '<button type="button" data-id="' + x.id + '"' + (sel.indexOf(x.id) >= 0 ? ' class="on"' : '') + '>' + esc(x[label]) + '</button>'; }).join('') + '</div>';
    }
    /** row: the editable row element; inp: hidden JSON input; host: where the panel goes */
    window.NXDiscount = function (row, inp, host) {
        var d = read(inp);
        d = { pct: d.pct || 10, max: d.max || 0, min: d.min || 0, scope: d.scope || 'all', ids: (d.ids || []).map(Number), days: d.days == null ? 30 : d.days };
        var p = document.createElement('div'); p.className = 'dsc-panel';
        p.innerHTML = '<div class="dsc-head"><b>🏷️ تنظیمات تخفیف</b><small class="dsc-sum"></small></div>'
            + '<div class="dsc-grid">'
            + '<label><span>درصد تخفیف</span><span class="dsc-in"><input data-f="pct" inputmode="decimal" dir="ltr"><i>٪</i></span></label>'
            + '<label><span>حداقل مبلغ خرید (تومان)</span><input data-f="min" inputmode="numeric" dir="ltr" placeholder="۰ = بدون حداقل"></label>'
            + '<label><span>سقف تخفیف (تومان)</span><input data-f="max" inputmode="numeric" dir="ltr" placeholder="۰ = بدون سقف"></label>'
            + '<label><span>اعتبار (روز)</span><input data-f="days" inputmode="numeric" dir="ltr" placeholder="۰ = همیشه"></label>'
            + '</div>'
            + '<div class="dsc-scope"><span>روی چه محصولاتی؟</span><div class="dsc-seg">'
            + '<button type="button" data-s="all">همه‌ی محصولات</button><button type="button" data-s="cats">دسته‌های خاص</button><button type="button" data-s="products">محصولات خاص</button></div>'
            + '<div class="dsc-pick" data-for="cats">' + chips(SHOP.cats, 'cats', 'name', d.scope === 'cats' ? d.ids : []) + '</div>'
            + '<div class="dsc-pick" data-for="products">' + chips(SHOP.products, 'products', 'title', d.scope === 'products' ? d.ids : []) + '</div></div>'
            + '<small class="dsc-hint">هر بار برنده بشه یه کد جدا براش ساخته می‌شه که فقط یک بار، موقع خرید از فروشگاه، خودکار اعمال می‌شه.</small>';
        host.appendChild(p);
        ['pct', 'min', 'max', 'days'].forEach(function (f) { p.querySelector('[data-f="' + f + '"]').value = d[f] || (f === 'pct' ? 10 : ''); });
        function save() {
            d.pct = Math.max(0, Math.min(100, num(p.querySelector('[data-f=pct]').value)));
            d.min = Math.max(0, Math.round(num(p.querySelector('[data-f=min]').value)));
            d.max = Math.max(0, Math.round(num(p.querySelector('[data-f=max]').value)));
            d.days = Math.max(0, Math.round(num(p.querySelector('[data-f=days]').value)));
            var pick = p.querySelector('.dsc-pick[data-for="' + d.scope + '"]');
            d.ids = pick ? [].map.call(pick.querySelectorAll('button.on'), function (b) { return +b.dataset.id; }) : [];
            inp.value = JSON.stringify(d);
            p.querySelector('.dsc-sum').textContent = summary(d);
            [].forEach.call(p.querySelectorAll('[data-s]'), function (b) { b.classList.toggle('on', b.dataset.s === d.scope); });
            [].forEach.call(p.querySelectorAll('.dsc-pick'), function (x) { x.hidden = x.dataset.for !== d.scope; });
            row.dispatchEvent(new Event('input', { bubbles: true }));
        }
        p.addEventListener('input', save);
        p.addEventListener('click', function (e) {
            var s = e.target.closest('[data-s]'); if (s) { d.scope = s.dataset.s; save(); return; }
            var c = e.target.closest('.dsc-chips button'); if (c) { c.classList.toggle('on'); save(); }
        });
        save();
        return { el: p, remove: function () { p.remove(); } };
    };
})();
