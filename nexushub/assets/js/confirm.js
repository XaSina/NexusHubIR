/* NEXUS | HUB — themed confirm dialog (site + panel).
   Any element with data-confirm="question" (button, link, or form) asks first.
   Optional: data-confirm-ok="حذف" data-confirm-tone="danger|primary".
   Also available from code: nxConfirm('question', { ok: 'باشه', tone: 'danger' }).then(function (yes) { ... }) */
(function () {
    'use strict';
    if (window.nxConfirm) return;
    var css = '' +
        '.nxc{position:fixed;inset:0;z-index:100000;display:grid;place-items:center;padding:18px;font-family:inherit}' +
        '.nxc-bd{position:absolute;inset:0;background:rgba(4,3,8,.62);opacity:0;transition:opacity .25s ease}' +
        '.nxc-box{position:relative;width:min(400px,100%);padding:24px 22px 18px;border-radius:22px;text-align:center;color:var(--a-text,var(--text,#f4f4f7));' +
        'background:var(--a-pop,rgb(var(--surface-rgb,20,18,26)));border:1px solid rgba(var(--primary-rgb,255,31,61),.28);' +
        'box-shadow:0 30px 80px -20px rgba(0,0,0,.75),0 0 0 1px rgba(255,255,255,.04) inset,0 0 60px -20px rgba(var(--primary-rgb,255,31,61),.45);' +
        'opacity:0;transform:translate3d(0,14px,0) scale(.94);transition:opacity .25s ease,transform .45s cubic-bezier(.16,1,.3,1)}' +
        '.nxc.on .nxc-bd{opacity:1}.nxc.on .nxc-box{opacity:1;transform:none}' +
        '.nxc.out .nxc-bd{opacity:0}.nxc.out .nxc-box{opacity:0;transform:scale(.96);transition-duration:.18s}' +
        '.nxc-ic{width:58px;height:58px;margin:0 auto 12px;border-radius:50%;display:grid;place-items:center;font-size:1.6rem;' +
        'background:rgba(var(--primary-rgb,255,31,61),.14);border:1px solid rgba(var(--primary-rgb,255,31,61),.35);color:var(--primary,#ff1f3d);animation:nxcPop .5s cubic-bezier(.34,1.56,.64,1)}' +
        '.nxc.primary .nxc-ic{background:rgba(var(--primary-rgb,255,31,61),.12)}' +
        '@keyframes nxcPop{from{transform:scale(.5);opacity:0}}' +
        '.nxc-t{margin:0 0 6px;font-size:1.05rem;font-weight:800;line-height:1.8}' +
        '.nxc-s{margin:0 0 18px;font-size:.84rem;opacity:.6}' +
        '.nxc-row{display:flex;gap:10px}' +
        '.nxc-row button{flex:1;height:46px;border-radius:14px;font:inherit;font-weight:800;font-size:.92rem;cursor:pointer;transition:transform .15s ease,box-shadow .25s,background .25s}' +
        '.nxc-row button:active{transform:scale(.97)}' +
        '.nxc-ok{border:0;color:#fff;background:linear-gradient(135deg,var(--primary,#ff1f3d),var(--secondary,#ff7a3d));box-shadow:0 12px 28px -12px rgba(var(--primary-rgb,255,31,61),.95)}' +
        '.nxc.danger .nxc-ok{background:linear-gradient(135deg,#ff3b5c,#d1123a);box-shadow:0 12px 28px -12px rgba(255,59,92,.9)}' +
        '.nxc-no{color:inherit;background:rgba(127,127,140,.12);border:1px solid rgba(127,127,140,.25)}' +
        '.nxc-no:hover{background:rgba(127,127,140,.2)}' +
        '.nxc-row button:focus-visible{outline:2px solid var(--primary,#ff1f3d);outline-offset:2px}' +
        '.nxc-pw{position:relative;margin:0 0 16px}' +
        '.nxc-pw input{width:100%;height:50px;padding:0 14px;border-radius:14px;font:inherit;font-size:16px;letter-spacing:.5px;direction:ltr;text-align:left;color:inherit;' +
        'background:rgba(127,127,140,.1);border:1px solid rgba(127,127,140,.3);outline:0;transition:border-color .2s,box-shadow .2s}' +
        '.nxc-pw input:focus{border-color:rgba(var(--primary-rgb,255,31,61),.7);box-shadow:0 0 0 4px rgba(var(--primary-rgb,255,31,61),.15)}' +
        '.nxc-err{margin:-8px 0 12px;font-size:.8rem;color:#ff5a6e;min-height:1.4em}' +
        '.nxc.shake .nxc-box{animation:nxcShake .4s}' +
        '@keyframes nxcShake{20%,60%{transform:translateX(-7px)}40%,80%{transform:translateX(7px)}}' +
        '@media (prefers-reduced-motion:reduce){.nxc-box,.nxc-bd{transition:none}.nxc-ic{animation:none}}';
    var st = document.createElement('style'); st.textContent = css; (document.head || document.documentElement).appendChild(st);

    var DANGER = /حذف|پاک|لغو|بسته|ریست|بازنشانی|خروج|delete|remove/i;
    function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
    function nxConfirm(msg, o) {
        o = o || {};
        var tone = o.tone || (DANGER.test(msg) ? 'danger' : 'primary');
        var okText = o.ok || (tone === 'danger' ? (/لغو/.test(msg) ? 'بله، لغو شود' : /بسته/.test(msg) ? 'بله، بسته شود' : /حذف|پاک/.test(msg) ? 'بله، حذف شود' : 'تأیید') : 'تأیید');
        return new Promise(function (resolve) {
            var w = document.createElement('div');
            w.className = 'nxc ' + tone; w.setAttribute('role', 'alertdialog'); w.setAttribute('aria-modal', 'true'); w.dir = 'rtl';
            w.innerHTML = '<div class="nxc-bd"></div><div class="nxc-box"><div class="nxc-ic">' + (tone === 'danger' ? '🗑️' : '❔') + '</div>' +
                '<p class="nxc-t">' + esc(msg) + '</p>' + (tone === 'danger' ? '<p class="nxc-s">این کار قابل بازگشت نیست.</p>' : '<p class="nxc-s"></p>') +
                '<div class="nxc-row"><button type="button" class="nxc-ok">' + esc(okText) + '</button><button type="button" class="nxc-no">' + esc(o.cancel || 'انصراف') + '</button></div></div>';
            if (!/حذف|پاک/.test(msg) && tone === 'danger') w.querySelector('.nxc-ic').textContent = '⚠️';
            document.body.appendChild(w);
            var prev = document.activeElement, done = false;
            requestAnimationFrame(function () { w.classList.add('on'); });
            var ok = w.querySelector('.nxc-ok'), no = w.querySelector('.nxc-no');
            setTimeout(function () { (tone === 'danger' ? no : ok).focus(); }, 30);
            function close(v) {
                if (done) return; done = true;
                w.classList.add('out'); document.removeEventListener('keydown', key, true);
                setTimeout(function () { w.remove(); if (prev && prev.focus) try { prev.focus({ preventScroll: true }); } catch (e) {} }, 200);
                resolve(v);
            }
            function key(e) {
                if (e.key === 'Escape') { e.preventDefault(); close(false); }
                else if (e.key === 'Tab') { e.preventDefault(); (document.activeElement === ok ? no : ok).focus(); }
            }
            document.addEventListener('keydown', key, true);
            ok.addEventListener('click', function () { close(true); });
            no.addEventListener('click', function () { close(false); });
            w.querySelector('.nxc-bd').addEventListener('click', function () { close(false); });
        });
    }
    window.nxConfirm = nxConfirm;

    /* password box: nxPassword({ title }).then(function (pw) { pw === null → cancelled }) */
    function nxPassword(o) {
        o = o || {};
        return new Promise(function (resolve) {
            var w = document.createElement('div');
            w.className = 'nxc primary'; w.setAttribute('role', 'dialog'); w.setAttribute('aria-modal', 'true'); w.dir = 'rtl';
            w.innerHTML = '<div class="nxc-bd"></div><form class="nxc-box" autocomplete="off"><div class="nxc-ic">🔒</div>' +
                '<p class="nxc-t">' + esc(o.title || 'رمز عبور را وارد کنید') + '</p>' + (o.sub ? '<p class="nxc-s">' + esc(o.sub) + '</p>' : '<p class="nxc-s"></p>') +
                '<label class="nxc-pw pw-box"><input type="password" autocomplete="current-password" aria-label="رمز عبور" required></label>' +
                '<p class="nxc-err" aria-live="polite"></p>' +
                '<div class="nxc-row"><button type="submit" class="nxc-ok">' + esc(o.ok || 'تأیید') + '</button><button type="button" class="nxc-no">انصراف</button></div></form>';
            document.body.appendChild(w);
            var prev = document.activeElement, done = false, inp = w.querySelector('input'), err = w.querySelector('.nxc-err');
            requestAnimationFrame(function () { w.classList.add('on'); });
            setTimeout(function () { inp.focus(); }, 40);
            function close(v) {
                if (done) return; done = true;
                w.classList.add('out'); document.removeEventListener('keydown', key, true);
                setTimeout(function () { w.remove(); if (prev && prev.focus) try { prev.focus({ preventScroll: true }); } catch (e) {} }, 200);
                resolve(v);
            }
            function key(e) { if (e.key === 'Escape') { e.preventDefault(); close(null); } }
            document.addEventListener('keydown', key, true);
            w.querySelector('form').addEventListener('submit', function (e) {
                e.preventDefault();
                if (!inp.value) { err.textContent = 'رمز رو بنویس'; w.classList.remove('shake'); void w.offsetWidth; w.classList.add('shake'); inp.focus(); return; }
                close(inp.value);
            });
            w.querySelector('.nxc-no').addEventListener('click', function () { close(null); });
            w.querySelector('.nxc-bd').addEventListener('click', function () { close(null); });
        });
    }
    window.nxPassword = nxPassword;

    // forms with data-pw ask for the password first; it travels as a hidden field «confirm_pw»
    var pwPassed = new WeakSet();
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f.hasAttribute || !f.hasAttribute('data-pw')) return;
        if (pwPassed.has(f)) { pwPassed.delete(f); return; }
        e.preventDefault(); e.stopImmediatePropagation();
        var sub = e.submitter;
        nxPassword({ title: f.getAttribute('data-pw') || 'رمز عبور را وارد کنید' }).then(function (pw) {
            if (pw === null) return;
            var h = f.querySelector('input[name=confirm_pw]');
            if (!h) { h = document.createElement('input'); h.type = 'hidden'; h.name = 'confirm_pw'; f.appendChild(h); }
            h.value = pw;
            pwPassed.add(f);
            if (f.requestSubmit) f.requestSubmit(sub && sub.form === f ? sub : undefined); else f.submit();
            setTimeout(function () { h.value = ''; }, 1500);   // the password never stays in the page
        });
    }, true);

    // buttons / links with data-confirm (capture: runs before other handlers)
    var passed = new WeakSet();
    document.addEventListener('click', function (e) {
        var el = e.target.closest && e.target.closest('[data-confirm]');
        if (!el || el.tagName === 'FORM' || passed.has(el)) return;
        e.preventDefault(); e.stopImmediatePropagation();
        nxConfirm(el.dataset.confirm, { ok: el.dataset.confirmOk, tone: el.dataset.confirmTone }).then(function (yes) {
            if (!yes) return;
            var form = el.form || el.closest('form');
            if ((el.tagName === 'BUTTON' || el.tagName === 'INPUT') && form && (el.type || 'submit') === 'submit') {
                passed.add(form);
                if (form.requestSubmit) form.requestSubmit(el);
                else { if (el.name) { var h = document.createElement('input'); h.type = 'hidden'; h.name = el.name; h.value = el.value; form.appendChild(h); } form.submit(); }
            } else if (el.tagName === 'A' && el.href) {
                location.href = el.href;
            } else { passed.add(el); el.click(); passed.delete(el); }
        });
    }, true);
    // forms with data-confirm (or legacy data-confirm-submit)
    document.addEventListener('submit', function (e) {
        var f = e.target, q = f.dataset.confirm || f.dataset.confirmSubmit;
        if (passed.has(f)) { passed.delete(f); return; }
        if (!q) return;
        e.preventDefault(); e.stopImmediatePropagation();
        var sub = e.submitter;
        nxConfirm(q, { ok: f.dataset.confirmOk, tone: f.dataset.confirmTone }).then(function (yes) {
            if (!yes) return;
            passed.add(f);
            if (f.requestSubmit) f.requestSubmit(sub && sub.form === f ? sub : undefined); else f.submit();
        });
    }, true);
})();
