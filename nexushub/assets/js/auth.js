/* ==========================================================================
   NEXUS | HUB — Cinematic auth flow
   intro → identify → (login | register → success) → access granted → account
   ========================================================================== */
(function () {
    'use strict';
    var C = window.NXAU || {};
    var $ = function (s, c) { return (c || document).querySelector(s); };
    var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
    var body = document.body, root = document.documentElement;
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var fa = function (s) { return String(s).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; }); };
    var en = function (s) { return String(s).replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); }).replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); }); };
    var wait = function (ms) { return new Promise(function (r) { setTimeout(r, reduce ? Math.min(ms, 60) : ms); }); };
    var cssVar = function (n, d) { return (getComputedStyle(root).getPropertyValue(n) || d).trim(); };

    function api(path, data) {
        var fd = new FormData();
        Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
        fd.append('_csrf', C.csrf);
        return fetch((C.base || '') + '/api/' + path, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': C.csrf } })
            .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'خطای سرور' }; }); })
            .catch(function () { return { ok: false, error: 'ارتباط با سرور برقرار نشد.' }; });
    }

    /* ---------- page warp (shared with site) ---------- */
    var warp = $('#nxWarp');
    function warpTo(href) {
        if (!warp || reduce) { location.href = href; return; }
        warp.style.setProperty('--wx', '50%'); warp.style.setProperty('--wy', '50%');
        warp.classList.add('cover');
        try { sessionStorage.setItem('nx_warp', '1'); } catch (e) {}
        setTimeout(function () { location.href = href; }, 820);
    }
    document.addEventListener('click', function (e) {
        var a = e.target.closest('a[data-warp]');
        if (!a || e.ctrlKey || e.metaKey) return;
        e.preventDefault(); warpTo(a.href);
    });
    window.addEventListener('pageshow', function (e) { if (e.persisted && warp) warp.classList.remove('cover'); });
    var arrivedByWarp = root.classList.contains('warp-in');
    if (arrivedByWarp) {
        try { sessionStorage.removeItem('nx_warp'); } catch (e) {}
        setTimeout(function () { root.classList.remove('warp-in'); }, 140);
    }

    /* ---------- timecode HUD ---------- */
    var tc = $('#auTc'), t0 = performance.now(), hud = $('#auHudState');
    var pad = function (n) { return ('0' + n).slice(-2); };
    setInterval(function () {
        var t = (performance.now() - t0) / 1000;
        tc.textContent = pad(Math.floor(t / 3600)) + ':' + pad(Math.floor(t / 60) % 60) + ':' + pad(Math.floor(t) % 60) + ':' + pad(Math.floor(t * 24) % 24);
    }, 42);
    var setHud = function (s) { if (hud) hud.textContent = s; };

    /* ==========================================================================
       Canvas FX: embers · hyperspace warp · confetti
       ========================================================================== */
    var cv = $('#auFx'), ctx = cv.getContext('2d'), W = 0, H = 0, dpr = Math.min(window.devicePixelRatio || 1, 2);
    var hex = function (h) { h = h.replace('#', ''); if (h.length === 3) h = h.replace(/./g, '$&$&'); return [parseInt(h.substr(0, 2), 16), parseInt(h.substr(2, 2), 16), parseInt(h.substr(4, 2), 16)]; };
    var PC = hex(cssVar('--primary', '#ff1f3d')), SC = hex(cssVar('--secondary', '#ff7a3d'));
    var embers = [], streaks = [], confetti = [], warpUntil = 0, warpStart = 0;
    function size() { W = cv.clientWidth; H = cv.clientHeight; cv.width = W * dpr; cv.height = H * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0); }
    function ember(fresh) {
        return { x: Math.random() * W, y: fresh ? H + Math.random() * 60 : Math.random() * H, vx: (Math.random() - .5) * .35, vy: -(.25 + Math.random() * 1.1),
            r: .5 + Math.random() * 2.2, a: .3 + Math.random() * .7, c: Math.random() < .72 ? PC : SC, w: Math.random() * 6.28 };
    }
    size();
    var EMBERS = Math.min(110, Math.floor(W * H / 14000));
    for (var i = 0; i < EMBERS; i++) embers.push(ember(false));
    window.addEventListener('resize', function () { size(); });

    function frame(now) {
        requestAnimationFrame(frame);
        if (document.hidden) return;
        ctx.clearRect(0, 0, W, H);
        ctx.globalCompositeOperation = 'lighter';
        var warping = now < warpUntil, k = warping ? Math.min(1, (now - warpStart) / 600) : 0;
        // embers
        for (var i = 0; i < embers.length; i++) {
            var e = embers[i];
            e.w += .02; e.x += e.vx + Math.sin(e.w) * .25; e.y += e.vy * (1 + k * 6);
            if (e.y < -20) embers[i] = e = ember(true);
            ctx.fillStyle = 'rgba(' + e.c.join(',') + ',' + (e.a * .18) + ')';
            ctx.beginPath(); ctx.arc(e.x, e.y, e.r * 4, 0, 6.28); ctx.fill();
            ctx.fillStyle = 'rgba(255,' + (190 + (e.c === SC ? 40 : 0)) + ',170,' + e.a + ')';
            ctx.beginPath(); ctx.arc(e.x, e.y, e.r * .8, 0, 6.28); ctx.fill();
        }
        // hyperspace streaks
        if (warping && !reduce) for (var s = 0; s < 14; s++) {
            var ang = Math.random() * 6.283;
            streaks.push({ x: W / 2 + Math.cos(ang) * 20, y: H / 2 + Math.sin(ang) * 20, a: ang, v: 4 + Math.random() * 6, l: 0, c: Math.random() < .5 ? PC : [255, 255, 255] });
        }
        for (var j = streaks.length - 1; j >= 0; j--) {
            var st = streaks[j];
            st.v *= 1.075; st.l = Math.min(260, st.l + st.v * .9);
            st.x += Math.cos(st.a) * st.v; st.y += Math.sin(st.a) * st.v;
            ctx.strokeStyle = 'rgba(' + st.c.join(',') + ',.75)'; ctx.lineWidth = 1.6;
            ctx.beginPath(); ctx.moveTo(st.x, st.y); ctx.lineTo(st.x - Math.cos(st.a) * st.l, st.y - Math.sin(st.a) * st.l); ctx.stroke();
            if (st.x < -300 || st.x > W + 300 || st.y < -300 || st.y > H + 300) streaks.splice(j, 1);
        }
        // confetti
        ctx.globalCompositeOperation = 'source-over';
        for (var q = confetti.length - 1; q >= 0; q--) {
            var p = confetti[q];
            p.vy += .18; p.vx *= .99; p.x += p.vx; p.y += p.vy; p.r += p.vr; p.life--;
            ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.r); ctx.globalAlpha = Math.max(0, Math.min(1, p.life / 40));
            ctx.fillStyle = p.c; ctx.fillRect(-p.s / 2, -p.s / 4, p.s, p.s / 2); ctx.restore();
            if (p.life <= 0 || p.y > H + 40) confetti.splice(q, 1);
        }
    }
    requestAnimationFrame(frame);
    function doWarp(ms) { warpStart = performance.now(); warpUntil = warpStart + ms; }
    function burst(x, y, n) {
        if (reduce) return;
        var cols = [cssVar('--primary', '#ff1f3d'), cssVar('--secondary', '#ff7a3d'), '#ffffff', '#ffd23d', '#3ddc84', '#00e1ff'];
        for (var i = 0; i < n; i++) {
            var a = Math.random() * 6.283, v = 4 + Math.random() * 9;
            confetti.push({ x: x, y: y, vx: Math.cos(a) * v, vy: Math.sin(a) * v - 5, r: Math.random() * 6, vr: (Math.random() - .5) * .4, s: 6 + Math.random() * 8, c: cols[i % cols.length], life: 110 + Math.random() * 60 });
        }
    }
    function flash() { var f = $('.au-flash'); f.classList.remove('go'); void f.offsetWidth; f.classList.add('go'); }

    /* ---------- text effects ---------- */
    function scramble(el, text, dur) {
        var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ0123456789#$%&@/<>', start = performance.now();
        return new Promise(function (res) {
            (function step(t) {
                var p = Math.min(1, (t - start) / (reduce ? 1 : dur)), n = Math.floor(text.length * p), out = '';
                for (var i = 0; i < text.length; i++) out += (i < n || /[\s|\-]/.test(text[i])) ? text[i] : chars[Math.floor(Math.random() * chars.length)];
                el.textContent = out;
                if (p < 1) requestAnimationFrame(step); else res();
            })(start);
        });
    }
    function typeText(el, text, speed) {
        var chars = Array.from(text), i = 0;
        return new Promise(function (res) {
            (function tick() { el.textContent = chars.slice(0, ++i).join(''); if (i < chars.length) setTimeout(tick, reduce ? 0 : speed); else res(); })();
        });
    }

    /* ==========================================================================
       Intro sequence
       ========================================================================== */
    var title = $('#auTitle'), titleText = title.dataset.text;
    title.textContent = '';
    (async function intro() {
        await wait(arrivedByWarp ? 380 : 150);
        body.classList.add('s1'); setHud('BOOTING');
        await wait(650);
        body.classList.add('s2'); flash(); setHud('SIGNAL LOCKED');
        await wait(600);
        body.classList.add('s3');
        scramble(title, titleText, 1100);
        await typeText($('#auType'), 'دروازه‌ی ورود به کامیونیتی NEXUS 🔥', 45);
        body.classList.add('s4'); setHud('READY');
    })();

    /* ==========================================================================
       Auth card / steps
       ========================================================================== */
    var stage = $('#auStage'), card = $('#auCard'), dots = $$('#auDots i'), backBtn = $('#auBackStep');
    var steps = {}; $$('.au-step').forEach(function (s) { steps[s.dataset.step] = s; });
    var current = 'identify', identity = { value: '', type: '' };

    $('#auStart').addEventListener('click', async function () {
        body.classList.add('warping'); setHud('WARP DRIVE');
        doWarp(1300); flash();
        await wait(950);
        $('#auIntro').hidden = true;
        stage.hidden = false;
        body.classList.remove('warping');
        setHud('IDENTIFY');
        steps.identify.classList.add('in');
        setTimeout(function () { $('#auIdentifier').focus(); }, 500);
    });

    function setDots(n) { dots.forEach(function (d, i) { d.classList.toggle('on', i < n); }); }
    function show(name) {
        if (name === current) return;
        var from = steps[current], to = steps[name];
        var h0 = card.offsetHeight;
        from.classList.remove('in'); from.classList.add('out');
        to.hidden = false; to.classList.remove('out'); to.classList.add('in');
        card.classList.toggle('wide', name === 'register');
        // animate card height
        var h1 = card.offsetHeight; card.style.height = h0 + 'px'; void card.offsetHeight; card.style.height = h1 + 'px';
        setTimeout(function () { from.hidden = true; from.classList.remove('out'); }, 380);
        setTimeout(function () { card.style.height = ''; }, 600);
        current = name;
        backBtn.hidden = !(name === 'login' || name === 'register');
        setDots({ identify: 1, login: 2, register: 2, success: 3 }[name] || 1);
        setHud({ identify: 'IDENTIFY', login: 'VERIFY', register: 'NEW MEMBER', success: 'REGISTERED' }[name] || 'READY');
        var first = to.querySelector('input:not([type=hidden]):not(.au-hp)');
        if (first && name !== 'success') setTimeout(function () { first.focus(); }, 420);
        clearErrors(to);
    }
    backBtn.addEventListener('click', function () { show('identify'); });
    $$('[data-goto]').forEach(function (b) { b.addEventListener('click', function () { show(b.dataset.goto); }); });

    function errorOn(form, msg) {
        var box = $('.au-error', form);
        box.textContent = msg; box.classList.add('on');
        card.classList.remove('shake'); void card.offsetWidth; card.classList.add('shake');
        setTimeout(function () { card.classList.remove('shake'); }, 520);
    }
    function clearErrors(form) {
        var box = $('.au-error', form); if (box) { box.classList.remove('on'); box.textContent = ''; }
        $$('.au-field.err', form).forEach(function (f) { f.classList.remove('err'); });
        $$('.au-ferr', form).forEach(function (f) { f.textContent = ''; });
    }
    function loading(form, on) { var b = $('.au-btn', form) || form; b.classList.toggle('loading', on); b.disabled = on; }

    /* identifier live type detection */
    var ICONS = {
        phone: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="2" width="12" height="20" rx="3"/><path d="M11 18h2"/></svg>',
        email: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m3 7 9 6 9-6"/></svg>',
        username: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>',
        none: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l2 6 6 2-6 2-2 6-2-6-6-2 6-2 2-6Z"/></svg>'
    };
    var LABELS = { phone: 'موبایل', email: 'ایمیل', username: 'نام کاربری' };
    function detect(v) {
        v = en(v).trim();
        if (!v) return null;
        if (v.indexOf('@') >= 0) return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v) ? 'email' : null;
        var p = v.replace(/[\s\-().]/g, '').replace(/^\+98/, '0').replace(/^0098/, '0').replace(/^9(\d{9})$/, '09$1');
        if (/^09\d{9}$/.test(p)) return 'phone';
        if (/^[0-9+]/.test(v)) return null;
        if (/^[A-Za-z][A-Za-z0-9_.]{2,29}$/.test(v)) return 'username';
        return null;
    }
    var idInput = $('#auIdentifier'), idIcon = $('#auIdIcon'), idType = $('#auIdType'), lastType = 'x';
    function updateIdType() {
        var t = detect(idInput.value) || 'none';
        if (t !== lastType) {
            idIcon.innerHTML = ICONS[t]; idIcon.classList.remove('pop'); void idIcon.offsetWidth; idIcon.classList.add('pop');
            idType.textContent = LABELS[t] || ''; idType.classList.toggle('on', t !== 'none');
            lastType = t;
        }
    }
    idInput.addEventListener('input', updateIdType); updateIdType();

    /* step 1: identify */
    steps.identify.addEventListener('submit', async function (e) {
        e.preventDefault(); clearErrors(this);
        var v = en(idInput.value).trim();
        if (!detect(v)) return errorOn(this, 'یک شماره موبایل (مثل 09121234567)، ایمیل یا نام کاربری انگلیسی معتبر وارد کن.');
        loading(this, true); setHud('SCANNING');
        var j = await api('auth-check', { identifier: v });
        await wait(350);
        loading(this, false);
        if (!j.ok) { setHud('IDENTIFY'); return errorOn(this, j.error || 'خطا'); }
        identity = { value: j.value, type: j.type, raw: v };
        if (j.exists) {
            $('#auHelloName').textContent = j.first_name;
            $('#auAvatar').textContent = (j.first_name || 'N').charAt(0);
            $('#auLoginId').textContent = j.value;
            $('#auPassword').value = '';
            show('login');
        } else {
            var f = steps.register;
            $('#auRegId').textContent = j.value;
            ['phone', 'email', 'username'].forEach(function (k) { var inp = f.elements[k]; if (k === j.type) inp.value = j.value; else if (inp.dataset.auto) { inp.value = ''; delete inp.dataset.auto; } });
            show('register');
        }
    });

    /* step 2: login */
    steps.login.addEventListener('submit', async function (e) {
        e.preventDefault(); clearErrors(this);
        var pw = $('#auPassword').value;
        if (!pw) { $('#auPassword').closest('.au-field').classList.add('err'); return errorOn(this, 'رمز عبور را وارد کن.'); }
        loading(this, true); setHud('AUTHENTICATING');
        var j = await api('auth-login', { identifier: identity.value, password: pw });
        loading(this, false);
        if (!j.ok) { setHud('VERIFY'); $('#auPassword').closest('.au-field').classList.add('err'); $('#auPassword').select(); return errorOn(this, j.error || 'خطا'); }
        enterSequence(j);
    });

    /* step 3: register */
    var reg = steps.register, pwField = reg.elements.password, strength = $('#auStrength');
    pwField.addEventListener('input', function () {
        var v = en(pwField.value), s = 0;
        if (v.length >= 8) s++;
        if (/[A-Za-z]/.test(v) && /\d/.test(v)) s++;
        if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
        if (/[^A-Za-z0-9]/.test(v) || v.length >= 12) s++;
        strength.dataset.s = v ? Math.max(1, s) : 0;
    });
    reg.elements.email.addEventListener('input', function () {
        var u = reg.elements.username;
        if (u.value && !u.dataset.auto) return;
        var local = (this.value.split('@')[0] || '').replace(/[^A-Za-z0-9_.]/g, '').replace(/^[^A-Za-z]+/, '').slice(0, 30);
        u.value = local.length >= 3 ? local : ''; u.dataset.auto = '1';
    });
    reg.elements.username.addEventListener('input', function () { delete this.dataset.auto; });
    reg.addEventListener('submit', async function (e) {
        e.preventDefault(); clearErrors(this);
        var data = {}, f = this;
        ['first_name', 'last_name', 'phone', 'email', 'username', 'password', 'website'].forEach(function (k) { data[k] = en(f.elements[k].value).trim(); });
        data.password = f.elements.password.value;
        var missing = ['first_name', 'last_name', 'phone', 'email', 'username', 'password'].filter(function (k) { return !data[k]; });
        if (missing.length) { missing.forEach(function (k) { f.elements[k].closest('.au-field').classList.add('err'); }); return errorOn(f, 'لطفاً همه‌ی فیلدها را کامل کن.'); }
        loading(f, true); setHud('CREATING ID');
        var j = await api('auth-register', data);
        loading(f, false);
        if (!j.ok) {
            setHud('NEW MEMBER');
            if (j.errors) Object.keys(j.errors).forEach(function (k) {
                var inp = f.elements[k]; if (!inp) return;
                var fld = inp.closest('.au-field'); fld.classList.add('err');
                $('.au-ferr', fld).textContent = j.errors[k];
            });
            return errorOn(f, j.error || 'خطا در ثبت‌نام');
        }
        // success!
        $('#auSucName').textContent = j.name;
        $('#auSucFull').textContent = j.full_name;
        $('#auSucDate').textContent = j.date;
        show('success');
        flash();
        var r = card.getBoundingClientRect();
        setTimeout(function () { burst(r.left + r.width / 2, r.top + 90, 140); }, 900);
        setTimeout(function () { burst(window.innerWidth * .15, window.innerHeight * .4, 60); burst(window.innerWidth * .85, window.innerHeight * .4, 60); }, 1250);
        setTimeout(function () { scramble($('#auSucCode'), j.code, 1300); }, 1400);
    });

    /* step 4: finish → auto login */
    $('#auFinish').addEventListener('click', async function () {
        var b = this; b.classList.add('loading'); b.disabled = true;
        var j = await api('auth-finish', {});
        if (!j.ok) {
            b.classList.remove('loading'); b.disabled = false;
            identity.value = reg.elements.username.value;
            $('#auHelloName').textContent = $('#auSucName').textContent;
            $('#auLoginId').textContent = identity.value;
            show('login');
            return errorOn(steps.login, j.error || 'لطفاً وارد شو.');
        }
        enterSequence(j);
    });

    /* eye toggles */
    $$('.au-eye').forEach(function (b) {
        b.addEventListener('click', function () {
            var inp = b.parentNode.querySelector('input');
            inp.type = inp.type === 'password' ? 'text' : 'password';
            b.classList.toggle('on', inp.type === 'text');
        });
    });

    /* ==========================================================================
       Access granted sequence
       ========================================================================== */
    async function enterSequence(j) {
        if (j.csrf) C.csrf = j.csrf;
        setHud('ACCESS GRANTED');
        card.classList.add('leaving');
        await wait(650);
        stage.hidden = true;
        var ov = $('#auEnter'), pct = $('#auPct'), term = $('#auTerm');
        ov.hidden = false; body.classList.add('entering');
        doWarp(400);
        var lines = ['> handshake with nexus core ... <b>OK</b>', '> verifying identity [' + (j.code || 'NX') + '] ... <b>OK</b>', '> decrypting member profile ... <b>OK</b>', '> syncing preferences ... <b>OK</b>', '> access level: MEMBER'];
        var start = performance.now(), dur = reduce ? 1 : 1700, shown = 0;
        await new Promise(function (res) {
            (function tick(t) {
                var p = Math.min(1, (t - start) / dur), eased = 1 - Math.pow(1 - p, 3);
                pct.textContent = fa(Math.round(eased * 100)) + '٪';
                while (shown < Math.floor(p * lines.length)) { var d = document.createElement('div'); d.innerHTML = lines[shown++]; term.appendChild(d); }
                if (p < 1) requestAnimationFrame(tick); else res();
            })(start);
        });
        $('#auWelcomeName').textContent = j.name || '';
        body.classList.add('granted');
        $('#auGranted').classList.add('on');
        flash();
        burst(window.innerWidth / 2, window.innerHeight / 2, 120);
        await wait(1500);
        warpTo(j.redirect || C.home);
    }
})();
