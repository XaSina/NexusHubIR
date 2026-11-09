/* NEXUS | HUB — every button tells what it is doing (site, panel, login).
   A button that saves says «در حال ذخیره…» then «ذخیره شد ✓», a back button only says «در حال بازگشت…», and so on.
   Works by itself: form submits, button links and fetch() calls started by a click. Nothing in the button is replaced —
   a small layer is drawn on top and removed again, so the page's own code keeps working.
   Per button:  data-busy="متن هنگام کار"  data-done="متن بعد از موفقیت"  data-nofb (no feedback)  data-download (stays on page) */
(function () {
    'use strict';
    if (window.NXBtn) return;

    var BUSY = [
        [/بازگشت|برگشت|^قبلی/, 'در حال بازگشت'], [/ذخیره/, 'در حال ذخیره'], [/پاسخ/, 'در حال ارسال پاسخ'], [/ارسال|بفرست/, 'در حال ارسال'],
        [/حذف|پاک کن|پاکسازی/, 'در حال حذف'], [/خروج/, 'در حال خروج'], [/ثبت‌?نام/, 'در حال ثبت‌نام'], [/ورود|وارد شو/, 'در حال ورود'],
        [/دانلود|خروجی|پشتیبان/, 'در حال آماده‌سازی فایل'], [/جستجو|پیدا کن/, 'در حال جستجو'], [/خرید سریع|تسویه|پرداخت|تکمیل خرید/, 'در حال رفتن به پرداخت'],
        [/تایید|تأیید/, 'در حال تأیید'], [/^رد |رد کن|رد شود/, 'در حال رد کردن'], [/آپلود|بارگذاری/, 'در حال بارگذاری'],
        [/قرعه‌?کشی کن|انجام قرعه|شروع قرعه/, 'در حال قرعه‌کشی'], [/شرکت/, 'در حال ثبت شرکت'], [/بساز|ساخت|ایجاد/, 'در حال ساخت'],
        [/تست|بررسی|امتحان/, 'در حال بررسی'], [/بازسازی|بروز|به‌روز|آپدیت|همگام/, 'در حال بروزرسانی'], [/اعمال/, 'در حال اعمال'],
        [/افزودن|اضافه|سبد|خرید/, 'در حال افزودن'], [/تمدید/, 'در حال تمدید'], [/لغو/, 'در حال لغو'], [/تحویل/, 'در حال ثبت تحویل'],
        [/غیرفعال|مسدود/, 'در حال غیرفعال کردن'], [/فعال/, 'در حال فعال کردن'], [/ثبت|درخواست/, 'در حال ثبت'], [/کپی/, null],
        [/مشاهده|نمایش|باز کن|جزئیات/, 'در حال باز کردن'], [/ادامه|بعدی|بریم|برو/, 'در حال رفتن'], [/صفر کردن|شروع آمار/, 'در حال صفر کردن']
    ];
    var DONE = [
        [/ذخیره/, 'ذخیره شد'], [/پاسخ|ارسال|بفرست/, 'ارسال شد'], [/حذف|پاک/, 'حذف شد'], [/دانلود|خروجی|پشتیبان/, 'دانلود شروع شد'],
        [/افزودن|اضافه|سبد|خرید/, 'اضافه شد'], [/تایید|تأیید/, 'تأیید شد'], [/تست|بررسی|امتحان/, 'بررسی شد'], [/اعمال/, 'اعمال شد'],
        [/بازسازی|بروز|به‌روز|آپدیت/, 'بروز شد'], [/تمدید/, 'تمدید شد'], [/لغو/, 'لغو شد'], [/غیرفعال|مسدود/, 'غیرفعال شد'], [/فعال/, 'فعال شد'],
        [/شرکت|ثبت|درخواست/, 'ثبت شد'], [/ساخت|بساز|ایجاد/, 'ساخته شد']
    ];
    // controls that already show their own state, or only open / move things on the page
    var SKIP = '[data-nofb], .react, .bm-btn, .share-btn, [data-copy], .nsel, [data-tab], .tabs, [data-mv], .sort-handle, .icon-btn, .adm-ib, .story, ' +
        '.sv-btn, .sv-side, .slider-nav, .nb-btn, .anb-btn, .cmdk, .nxc, .nxp-close, .announce-close, .nxs-x, .nxs-btn, .pf-tab, .pf-box, .theme-opt, .opt, ' +
        '.nx-more, .burger, .drawer, [data-strip-close], .mm-grid, [data-close], .pg-num, .pg-btn, .srp-bar, .to-top, summary, label, .lw-spin, .rf-replay, ' +
        '.amt-btn, [data-q], [data-preset], [data-amt], .au-btn, .au-back, [data-warp], .sec-tools, .h-scroll-nav, [data-scroll], [data-dir], [data-go], .cmdk-btn, ' +
        '.adm-cmd-btn, .co-agree, .nxc-pw, [aria-haspopup], [aria-expanded]';

    function txt(el) {
        var t = el.getAttribute('aria-label') && !el.textContent.trim() ? el.getAttribute('aria-label') : (el.value && el.tagName === 'INPUT' ? el.value : el.textContent);
        return String(t || '').replace(/[\u{1F000}-\u{1FAFF}\u{2600}-\u{27BF}\u{FE0F}←→↩✓✔✕✖⬇️⬆️•·]/gu, '').replace(/\s+/g, ' ').trim();
    }
    function pick(list, t, dflt) { for (var i = 0; i < list.length; i++) if (list[i][0].test(t)) return list[i][1]; return dflt; }
    function busyText(el) {
        if (el.dataset.busy) return el.dataset.busy;
        if (el.hasAttribute('data-download')) return 'در حال آماده‌سازی فایل…';
        var t = pick(BUSY, txt(el), 'لطفاً صبر کن'); return t === null ? null : t + '…';
    }
    function doneText(el) { return el.dataset.done || (el.hasAttribute('data-download') ? 'دانلود شروع شد' : pick(DONE, txt(el), 'انجام شد')); }

    function eligible(el) {
        if (!el || el.disabled || el.closest(SKIP)) return false;
        var tag = el.tagName;
        if (tag === 'A') {
            var h = el.getAttribute('href') || '';
            if (!/\b(btn|abtn)\b/.test(el.className) || !h || h.charAt(0) === '#' || /^(javascript|mailto|tel):/i.test(h) || el.target === '_blank' || el.hasAttribute('download')) return false;
        } else if (!(tag === 'BUTTON' || (tag === 'INPUT' && /^(submit|button)$/.test(el.type)) || el.getAttribute('role') === 'button')) return false;
        if (el.dataset.busy) return true;
        return txt(el).length >= 2;
    }

    var css = '' +
        '.fb-on{color:transparent!important;pointer-events:none}' +
        '.fb-on>*:not(.fb-ov){visibility:hidden}' +
        '.fb-ov{position:absolute;inset:0;z-index:5;display:flex;align-items:center;justify-content:center;gap:8px;padding:0 10px;white-space:nowrap;overflow:hidden;' +
        'font:inherit;font-weight:800;color:var(--fb-c,currentColor);visibility:visible!important;border-radius:inherit;animation:fbIn .2s ease both}' +
        '.fb-ov b{font-weight:inherit;overflow:hidden;text-overflow:ellipsis}' +
        '.fb-sp{flex-shrink:0;width:1.05em;height:1.05em;border-radius:50%;border:2px solid currentColor;border-top-color:transparent;opacity:.85;animation:fbSpin .7s linear infinite}' +
        '.fb-ok{flex-shrink:0;width:1.25em;height:1.25em;border-radius:50%;display:grid;place-items:center;font-style:normal;font-size:.8em;background:currentColor;animation:fbPop .4s cubic-bezier(.34,1.56,.64,1) both}' +
        '.fb-ok::after{content:"✓";color:var(--fb-bg,#111);font-weight:900}' +
        '.fb-done{box-shadow:0 0 0 3px rgba(61,220,132,.28)!important}' +
        '.fb-bad{animation:fbShake .4s}' +
        '.fb-tiny .fb-ov b{display:none}' +
        '@keyframes fbSpin{to{transform:rotate(360deg)}}@keyframes fbIn{from{opacity:0;transform:translateY(4px)}}' +
        '@keyframes fbPop{from{transform:scale(.3);opacity:0}}@keyframes fbShake{20%,60%{transform:translateX(-5px)}40%,80%{transform:translateX(5px)}}' +
        '@media (prefers-reduced-motion:reduce){.fb-sp{animation-duration:1.6s}.fb-ov,.fb-ok,.fb-bad{animation:none}}';
    var st = document.createElement('style'); st.textContent = css; (document.head || document.documentElement).appendChild(st);

    function bgOf(el) {
        for (var n = el; n && n !== document.documentElement; n = n.parentElement) {
            var c = getComputedStyle(n).backgroundColor; if (c && !/rgba\(.*,\s*0\)$|transparent/.test(c)) return c;
        }
        return '#111';
    }
    function layer(el, html) {
        var ov = el.querySelector(':scope > .fb-ov');
        if (!ov) { ov = document.createElement('span'); ov.className = 'fb-ov'; ov.setAttribute('aria-hidden', 'true'); el.appendChild(ov); }
        ov.innerHTML = html;
        return ov;
    }
    // a short button grows a little so its message fits; if it still can't, only the icon shows
    function fit(el, ov, w0) {
        el.classList.remove('fb-tiny');
        var t = ov.querySelector('b'); if (!t) return;
        var extra = t.scrollWidth - t.clientWidth;
        if (extra <= 1) return;
        var need = w0 + extra + 6;
        var max = Math.min(Math.max(w0 * 1.7, w0 + 70), 260);
        if (need <= max) el.style.minWidth = Math.ceil(need) + 'px'; else el.classList.add('fb-tiny');
    }
    function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

    function busy(el, text) {
        if (!el || el.classList.contains('fb-on')) return false;
        text = text || busyText(el); if (text === null) return false;
        var cs = getComputedStyle(el), r = el.getBoundingClientRect();
        el.style.setProperty('--fb-c', cs.color);
        el.style.setProperty('--fb-bg', bgOf(el));
        el.dataset.fbMinw = el.style.minWidth || '';
        if (cs.position === 'static') { el.style.position = 'relative'; el.dataset.fbPos = '1'; }
        el.style.minWidth = Math.ceil(r.width) + 'px';
        el.classList.add('fb-on', 'fb-busy');
        el.setAttribute('aria-busy', 'true');
        var ov = layer(el, '<i class="fb-sp"></i><b>' + esc(text) + '</b>');
        fit(el, ov, r.width);
        clearTimeout(el._fbT); el._fbT = setTimeout(function () { reset(el); }, 20000);   // never stuck
        return true;
    }
    function done(el, text) {
        if (!el || !el.classList.contains('fb-on')) return;
        el.classList.remove('fb-busy'); el.classList.add('fb-done');
        var ov = layer(el, '<i class="fb-ok"></i><b>' + esc(text || doneText(el)) + '</b>');
        fit(el, ov, el.getBoundingClientRect().width);
        clearTimeout(el._fbT); el._fbT = setTimeout(function () { reset(el); }, 1500);
    }
    function fail(el) {
        if (!el) return;
        reset(el);
        el.classList.remove('fb-bad'); void el.offsetWidth; el.classList.add('fb-bad');
        setTimeout(function () { el.classList.remove('fb-bad'); }, 450);
    }
    function reset(el) {
        if (!el) return;
        clearTimeout(el._fbT);
        var ov = el.querySelector(':scope > .fb-ov'); if (ov) ov.remove();
        el.classList.remove('fb-on', 'fb-busy', 'fb-done', 'fb-tiny');
        el.removeAttribute('aria-busy');
        if (el.dataset.fbMinw !== undefined) { el.style.minWidth = el.dataset.fbMinw; delete el.dataset.fbMinw; }
        if (el.dataset.fbPos) { el.style.position = ''; delete el.dataset.fbPos; }
    }
    window.NXBtn = { busy: busy, done: done, fail: fail, reset: reset };

    // 1) a form really leaving the page (not stopped by the page's own code)
    document.addEventListener('submit', function (e) {
        var f = e.target;
        setTimeout(function () {
            if (e.defaultPrevented) return;
            var b = e.submitter && e.submitter.form === f ? e.submitter : f.querySelector('[type=submit]');
            if (!eligible(b)) return;
            if (!busy(b)) return;
            if (b.hasAttribute('data-download') || f.target === '_blank') setTimeout(function () { done(b); }, 2200);   // a file comes back, the page stays
        }, 0);
    });

    // 2) button-looking links that open another page in the same tab
    document.addEventListener('click', function (e) {
        var a = e.target.closest && e.target.closest('a');
        if (!a || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
        setTimeout(function () { if (!e.defaultPrevented && eligible(a)) busy(a); }, 0);
    });

    // 3) a click that starts a request (save, send, add to cart …): busy while it runs, then «… شد ✓»
    var last = null;
    document.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('button, input[type=submit], input[type=button], [role=button]');
        last = b && eligible(b) ? { el: b, t: Date.now() } : null;
    }, true);
    document.addEventListener('submit', function (e) { var s = e.submitter; if (s && eligible(s)) last = { el: s, t: Date.now() }; }, true);
    if (window.fetch) {
        var of = window.fetch;
        window.fetch = function (input, init) {
            var m = String((init && init.method) || (input && input.method) || 'GET').toUpperCase();
            var b = (m !== 'GET' && last && Date.now() - last.t < 900 && document.contains(last.el)) ? last.el : null;
            if (b) { last = null; if (!busy(b)) b = null; }
            var p = of.apply(this, arguments);
            if (!b) return p;
            p.then(function (res) {
                res.clone().json().then(function (j) {
                    if (!res.ok || (j && (j.ok === false || j.error))) fail(b); else done(b, j && typeof j.done === 'string' ? j.done : '');
                }).catch(function () { res.ok ? done(b) : fail(b); });
            }, function () { fail(b); });
            return p;
        };
    }

    // the panel sends its requests with XMLHttpRequest
    if (window.XMLHttpRequest) {
        var XP = XMLHttpRequest.prototype, oOpen = XP.open, oSend = XP.send;
        XP.open = function (m) { this._fbM = String(m || 'GET').toUpperCase(); return oOpen.apply(this, arguments); };
        XP.send = function () {
            var x = this, b = (x._fbM !== 'GET' && last && Date.now() - last.t < 900 && document.contains(last.el)) ? last.el : null;
            if (b) { last = null; if (!busy(b)) b = null; }
            if (b) x.addEventListener('loadend', function () {
                var j = null; try { j = JSON.parse(x.responseText); } catch (e) { }
                if (x.status < 200 || x.status >= 400 || (j && (j.ok === false || j.error))) fail(b); else done(b, j && typeof j.done === 'string' ? j.done : '');
            });
            return oSend.apply(this, arguments);
        };
    }

    // coming back with the browser's back button: no button stays busy
    window.addEventListener('pageshow', function (e) { if (e.persisted) [].forEach.call(document.querySelectorAll('.fb-on'), reset); });
})();
