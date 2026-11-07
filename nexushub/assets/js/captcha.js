/* NEXUS | HUB — security code: refresh (button / tap on the picture / after a failed try), Persian digits typed are fine */
(function () {
    'use strict';
    function refresh(box) {
        if (!box) return;
        var img = box.querySelector('.nxc-img'), fr = box.querySelector('.nxc-frame'), re = box.querySelector('.nxc-re'), inp = box.querySelector('input[name=captcha]');
        if (!img) return;
        fr.classList.add('loading');
        if (re) { re.classList.remove('spin'); void re.offsetWidth; re.classList.add('spin'); }
        img.onload = img.onerror = function () { fr.classList.remove('loading'); };
        img.src = box.getAttribute('data-src') + '&v=' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
        if (inp) { inp.value = ''; }
        box.classList.remove('ok');
        clearTimeout(box._exp); box._exp = setTimeout(function () { refresh(box); }, 9 * 60 * 1000);   // codes expire after 10 minutes
    }
    function init(box) {
        if (box._nxc) return; box._nxc = true;
        var inp = box.querySelector('input[name=captcha]');
        [].forEach.call(box.querySelectorAll('.nxc-frame, .nxc-re'), function (el) { el.addEventListener('click', function (e) { e.preventDefault(); refresh(box); if (inp) inp.focus(); }); });
        if (inp) inp.addEventListener('input', function () {
            var v = inp.value.replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); }).replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); }).replace(/\D/g, '');
            if (v !== inp.value) inp.value = v;
            box.classList.remove('err');
        });
        box._exp = setTimeout(function () { refresh(box); }, 9 * 60 * 1000);
    }
    function scan(root) { [].forEach.call((root || document).querySelectorAll('.nx-cap'), init); }
    window.NXCap = {
        refresh: function (el) { var box = el && el.closest ? (el.classList && el.classList.contains('nx-cap') ? el : el.querySelector('.nx-cap') || el.closest('.nx-cap')) : null; if (box) refresh(box); else [].forEach.call(document.querySelectorAll('.nx-cap'), refresh); },
        error: function (el) { var box = el && el.querySelector ? (el.classList.contains('nx-cap') ? el : el.querySelector('.nx-cap')) : null; if (!box) return; box.classList.remove('err'); void box.offsetWidth; box.classList.add('err'); refresh(box); var i = box.querySelector('input'); if (i) i.focus(); },
        value: function (el) { var i = el && el.querySelector ? el.querySelector('.nx-cap input[name=captcha]') : null; return i ? i.value : ''; },
        scan: scan
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { scan(); }); else scan();
})();
