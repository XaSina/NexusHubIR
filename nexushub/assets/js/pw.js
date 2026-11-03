/* NEXUS | HUB — show/hide password button, used on every password field (site, sign-in page, admin, installer).
   The button sits in its own reserved space inside the field, so typed text never runs under it. */
(function () {
    'use strict';
    if (window.NXPW) return;
    window.NXPW = true;

    var css = ''
        + '.pw-box{position:relative;display:block}'
        + '.pw-box>input.pw-in{padding-left:52px!important}'
        + '.au-field.pw-box .pw-eye{top:28px}'
        + 'input.pw-in::-ms-reveal,input.pw-in::-ms-clear{display:none}'
        + '.pw-eye{position:absolute;left:7px;top:50%;width:38px;height:38px;margin-top:-19px;display:grid;place-items:center;padding:0;border:0;border-radius:12px;cursor:pointer;'
        + 'background:transparent;color:rgba(160,160,175,.9);-webkit-tap-highlight-color:transparent;-webkit-user-select:none;user-select:none;'
        + 'transition:color .25s,background .25s,transform .2s,box-shadow .25s;z-index:2}'
        + '.pw-eye:hover{color:var(--primary,#ff1f3d);background:rgba(var(--primary-rgb,255,31,61),.1)}'
        + '.pw-eye:active{transform:scale(.88)}'
        + '.pw-eye:focus{outline:0}.pw-eye:focus-visible{box-shadow:0 0 0 2px rgba(var(--primary-rgb,255,31,61),.6)}'
        + '.pw-eye.on{color:var(--primary,#ff1f3d);background:rgba(var(--primary-rgb,255,31,61),.14);box-shadow:inset 0 0 0 1px rgba(var(--primary-rgb,255,31,61),.35),0 0 18px -6px rgba(var(--primary-rgb,255,31,61),.9)}'
        + '.pw-eye svg{width:20px;height:20px;overflow:visible}'
        + '.pw-eye .pw-lid{transform-origin:12px 12px;transition:transform .32s cubic-bezier(.3,1.6,.5,1)}'
        + '.pw-eye .pw-pupil{transform-origin:12px 12px;transition:transform .35s cubic-bezier(.3,1.6,.5,1)}'
        + '.pw-eye .pw-slash{stroke-dasharray:24;stroke-dashoffset:0;transition:stroke-dashoffset .35s cubic-bezier(.65,0,.35,1)}'
        + '.pw-eye.on .pw-slash{stroke-dashoffset:24}'
        + '.pw-eye:not(.on) .pw-pupil{transform:scale(.7)}'
        + '.pw-eye.blink .pw-lid,.pw-eye.blink .pw-pupil{animation:pwBlink .34s ease}'
        + '@keyframes pwBlink{45%{transform:scaleY(.08)}}'
        + '@media (prefers-reduced-motion:reduce){.pw-eye *{transition:none!important;animation:none!important}}';

    var svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        + '<g class="pw-lid"><path d="M2.5 12s3.4-6.5 9.5-6.5S21.5 12 21.5 12s-3.4 6.5-9.5 6.5S2.5 12 2.5 12Z"/><circle class="pw-pupil" cx="12" cy="12" r="3"/></g>'
        + '<path class="pw-slash" d="M3.5 3.5l17 17"/></svg>';

    function addStyle() {
        if (document.getElementById('pwCss')) return;
        var s = document.createElement('style');
        s.id = 'pwCss'; s.textContent = css;
        (document.head || document.documentElement).appendChild(s);
    }

    function enhance(inp) {
        if (inp.dataset.pw) return;
        inp.dataset.pw = '1';
        // reuse a wrapper that already positions the field (sign-in page / admin login); otherwise wrap the input
        var box = inp.parentNode;
        if (!box || !(box.classList.contains('au-field') || box.classList.contains('pass-wrap') || box.classList.contains('pw-box'))) {
            box = document.createElement('span');
            box.className = 'pw-box';
            inp.parentNode.insertBefore(box, inp);
            box.appendChild(inp);
        } else {
            box.classList.add('pw-box');
            // remove an older eye button from the markup
            Array.prototype.forEach.call(box.querySelectorAll('.au-eye, button'), function (b) { if (!b.classList.contains('pw-eye')) b.remove(); });
        }
        inp.classList.add('pw-in');
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pw-eye';
        btn.tabIndex = 0;
        btn.innerHTML = svg;
        var sync = function () {
            var shown = inp.type === 'text';
            btn.classList.toggle('on', shown);
            btn.setAttribute('aria-pressed', shown ? 'true' : 'false');
            btn.setAttribute('aria-label', shown ? 'پنهان کردن رمز' : 'نمایش رمز');
            btn.title = shown ? 'پنهان کردن رمز' : 'نمایش رمز';
        };
        // keep the keyboard open / the caret in the field on phones
        btn.addEventListener('pointerdown', function (e) { if (document.activeElement === inp) e.preventDefault(); });
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var focused = document.activeElement === inp, pos = null;
            try { pos = inp.selectionStart; } catch (x) {}
            inp.type = inp.type === 'password' ? 'text' : 'password';
            sync();
            btn.classList.remove('blink'); void btn.offsetWidth; btn.classList.add('blink');
            if (focused) { inp.focus(); try { if (pos !== null) inp.setSelectionRange(pos, pos); } catch (x) {} }
        });
        // never submit (or let the browser remember) the password as visible text
        if (inp.form) inp.form.addEventListener('submit', function () { if (inp.type === 'text') { inp.type = 'password'; sync(); } }, true);
        sync();
        box.appendChild(btn);
    }

    function scan(root) {
        Array.prototype.forEach.call((root || document).querySelectorAll('input[type=password]:not([data-pw])'), enhance);
    }

    function init() {
        addStyle();
        scan();
        if (window.MutationObserver) new MutationObserver(function (list) {
            for (var i = 0; i < list.length; i++) if (list[i].addedNodes.length) { scan(); break; }
        }).observe(document.body, { childList: true, subtree: true });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
