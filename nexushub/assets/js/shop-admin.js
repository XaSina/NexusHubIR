/* NEXUS | HUB — shop admin: product editor (packages, custom amount, customer fields), live toman preview */
(function () {
    'use strict';
    var A = window.NXA || {};
    var $ = function (s, c) { return (c || document).querySelector(s); };
    var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
    var fa = function (s) { return String(s).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; }); };
    var en = function (s) { return String(s || '').replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); }).replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); }).replace(/[٬,]/g, '').replace('٫', '.'); };
    var money = function (n) { return fa(Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '٬')); };

    var form = $('#prodForm');
    if (!form) return;
    var R = JSON.parse(form.dataset.rates || '{}');
    var marginIn = $('#pMargin');

    function toToman(type, amount) {
        amount = parseFloat(en(amount));
        if (!(amount > 0)) return null;
        if (type === 'toman') return Math.round(amount);
        var m = marginIn && en(marginIn.value) !== '' ? parseFloat(en(marginIn.value)) : R.margin;
        var t;
        if (type === 'usd') { if (!R.usd) return null; t = amount * R.usd; }
        else { if (!R.usd || !R.ton) return null; t = amount * R.ton * R.usd; }
        t *= 1 + Math.max(-90, Math.min(500, m || 0)) / 100;
        return Math.ceil(t / R.round - 1e-9) * R.round;
    }
    var label = function (t) { return t === null ? '<em>قیمت دلار/تون تنظیم نشده</em>' : money(t) + ' <small>تومان</small>'; };

    /* ---------- packages ---------- */
    var varList = $('#varList'), varEmpty = $('.var-empty');
    function previewVar(row) {
        var t = toToman($('[data-n=price_type]', row).value, $('[data-n=amount]', row).value);
        $('[data-preview]', row).innerHTML = label(t);
    }
    function refreshVars() { $$('.var-row', varList).forEach(previewVar); if (varEmpty) varEmpty.hidden = !!$('.var-row', varList); }
    varList.addEventListener('input', function (e) { var r = e.target.closest('.var-row'); if (r) previewVar(r); });
    varList.addEventListener('change', function (e) { var r = e.target.closest('.var-row'); if (r) previewVar(r); });
    $('#addVar').addEventListener('click', function () {
        var n = $('#varTpl').content.firstElementChild.cloneNode(true);
        varList.appendChild(n); refreshVars();
        $('[data-n=title]', n).focus();
    });

    /* ---------- customer fields ---------- */
    var fldList = $('#fldList');
    function syncFld(row) { var o = $('.fld-opts', row); if (o) o.hidden = $('[data-n=type]', row).value !== 'select'; }
    $$('.fld-row', fldList).forEach(syncFld);
    fldList.addEventListener('change', function (e) { var r = e.target.closest('.fld-row'); if (r) syncFld(r); });
    $('#addFld').addEventListener('click', function () {
        var n = $('#fldTpl').content.firstElementChild.cloneNode(true);
        fldList.appendChild(n); syncFld(n);
        $('[data-n=label]', n).focus();
    });

    [varList, fldList].forEach(function (l) {
        if (A.sortable) A.sortable(l);
        l.addEventListener('click', function (e) {
            var b = e.target.closest('[data-del]'); if (!b) return;
            var row = b.closest('.sort-item');
            row.style.transition = 'opacity .25s, transform .25s'; row.style.opacity = '0'; row.style.transform = 'translateX(20px)';
            setTimeout(function () { row.remove(); refreshVars(); }, 250);
        });
    });

    /* ---------- custom amount preview ---------- */
    var cOn = $('#cOn'), cBox = $('#cBox'), cPrev = $('#cPreview');
    function previewCustom() {
        var on = cOn.checked;
        cBox.style.opacity = on ? '' : '.45'; cPrev.hidden = !on;
        if (!on) return;
        var type = $('#cType').value, unit = $('[name="custom[unit]"]').value || 'واحد', price = $('#cPrice').value, min = parseFloat(en($('#cMin').value)) || 1;
        var one = toToman(type, price), mn = toToman(type, (parseFloat(en(price)) || 0) * min);
        cPrev.innerHTML = one === null && mn === null ? '⚠️ قیمت هر واحد را وارد کنید (یا قیمت دلار/تون در تنظیمات فروشگاه تنظیم نشده است).'
            : '🧮 هر ۱ ' + unit + ' ≈ <b>' + (one === null ? '—' : money(one)) + '</b> تومان · حداقل خرید (' + fa(min) + ' ' + unit + ') = <b>' + (mn === null ? '—' : money(mn)) + '</b> تومان';
    }
    [cOn, $('#cType'), $('#cPrice'), $('#cMin'), $('[name="custom[unit]"]')].forEach(function (el) { if (el) { el.addEventListener('input', previewCustom); el.addEventListener('change', previewCustom); } });
    if (marginIn) marginIn.addEventListener('input', function () { refreshVars(); previewCustom(); });
    refreshVars(); previewCustom();

    /* ---------- serialize rows into v[i][..] / fld[i][..] on submit ---------- */
    form.addEventListener('submit', function () {
        [['v', varList, '.var-row'], ['fld', fldList, '.fld-row']].forEach(function (g) {
            $$(g[2], g[1]).forEach(function (row, i) {
                $$('[data-n]', row).forEach(function (el) {
                    if (el.type === 'checkbox') { el.name = el.checked ? g[0] + '[' + i + '][' + el.dataset.n + ']' : ''; }
                    else el.name = g[0] + '[' + i + '][' + el.dataset.n + ']';
                });
            });
        });
    });
})();
