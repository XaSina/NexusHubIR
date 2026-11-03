/* NEXUS | HUB — shop: live price, add to cart, order tracking, receipt upload, notifications bell */
(function () {
    'use strict';
    var $ = function (s, r) { return (r || document).querySelector(s); };
    var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
    var NX = window.NX || {};
    var fa = function (s) { return String(s).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; }); };
    var en = function (s) { return String(s).replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); }).replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); }).replace(/[٫,]/g, '.'); };
    var money = function (n) { return fa(Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '٬')); };
    var toast = function (m) { if (window.nxToast) window.nxToast(m); };
    var api = function (p, d) {
        if (window.nxApi) return window.nxApi(p, d);
        var fd = new FormData(); Object.keys(d).forEach(function (k) { fd.append(k, d[k]); }); fd.append('_csrf', NX.csrf);
        return fetch((NX.base || '') + '/' + p, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
    };
    var trimNum = function (n) { return String(+(+n).toFixed(6)); };

    /* ---------- themed dropdowns: native <select> lists look white/blue and off-design on phones & PCs ---------- */
    var openX = null;
    var closeX = function () { if (!openX) return; openX.wrap.classList.remove('open'); if (openX.menu.parentNode) openX.menu.parentNode.removeChild(openX.menu); openX = null; };
    function xselect(sel) {
        if (sel._x || sel.multiple || sel.size > 1) return;
        sel._x = true;
        var wrap = document.createElement('div'); wrap.className = 'xsel';
        sel.parentNode.insertBefore(wrap, sel); wrap.appendChild(sel);
        sel.classList.add('xsel-native'); sel.tabIndex = -1;
        var btn = document.createElement('button'); btn.type = 'button'; btn.className = 'xsel-btn'; btn.setAttribute('aria-haspopup', 'listbox');
        btn.innerHTML = '<span class="xsel-val"></span><svg class="xsel-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>';
        wrap.appendChild(btn);
        var val = $('.xsel-val', btn), menu = document.createElement('div'); menu.className = 'xsel-menu'; menu.setAttribute('role', 'listbox');
        var refresh = function () { var o = sel.options[sel.selectedIndex]; val.textContent = o ? o.textContent : ''; val.classList.toggle('ph', !o || o.value === '' || o.value === '0'); };
        var place = function () {
            var r = btn.getBoundingClientRect(), vw = document.documentElement.clientWidth, vh = document.documentElement.clientHeight;
            var w = Math.min(Math.max(r.width, 180), vw - 16);
            menu.style.width = w + 'px'; menu.style.left = Math.max(8, Math.min(r.right - w, vw - w - 8)) + 'px';
            menu.style.maxHeight = 'none';
            var full = menu.offsetHeight, below = vh - r.bottom - 16, above = r.top - 16, up = full > below && above > below;
            menu.style.maxHeight = Math.min(full, up ? above : below) + 'px';
            menu.style.top = up ? '' : (r.bottom + 6) + 'px'; menu.style.bottom = up ? (vh - r.top + 6) + 'px' : '';
            menu.classList.toggle('up', up);
        };
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (openX && openX.wrap === wrap) { closeX(); return; }
            closeX();
            menu.innerHTML = Array.prototype.map.call(sel.options, function (o, i) {
                return '<div class="xsel-opt' + (i === sel.selectedIndex ? ' sel' : '') + (o.disabled ? ' dis' : '') + '" role="option" data-i="' + i + '"></div>';
            }).join('');
            $$('.xsel-opt', menu).forEach(function (el, i) { el.textContent = sel.options[i].textContent; });
            document.body.appendChild(menu); wrap.classList.add('open'); openX = { wrap: wrap, menu: menu, place: place }; place();
            var cur = $('.xsel-opt.sel', menu); if (cur) cur.scrollIntoView({ block: 'nearest' });
        });
        menu.addEventListener('click', function (e) {
            e.stopPropagation();
            var o = e.target.closest('[data-i]'); if (!o || o.classList.contains('dis')) return;
            sel.selectedIndex = +o.dataset.i; refresh(); closeX();
            sel.dispatchEvent(new Event('change', { bubbles: true }));
        });
        sel.addEventListener('change', refresh);
        refresh();
    }
    $$('.shop-page select, .pd-wrap select, .field select').forEach(xselect);
    document.addEventListener('click', closeX);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeX(); });
    window.addEventListener('resize', closeX);
    window.addEventListener('scroll', function () { if (openX) openX.place(); }, { passive: true });   // follow the field (phones scroll while tapping)

    /* ---------- qty steppers ---------- */
    $$('.qty').forEach(function (q) {
        var inp = $('input', q);
        $$('button[data-q]', q).forEach(function (b) {
            b.addEventListener('click', function () {
                var v = parseInt(en(inp.value), 10) || 1;
                v = Math.max(1, Math.min(20, v + (b.dataset.q === '+' ? 1 : -1)));
                inp.value = v;
                inp.dispatchEvent(new Event('input', { bubbles: true }));
                if (q.hasAttribute('data-autosubmit')) { clearTimeout(q._t); q._t = setTimeout(function () { q.submit(); }, 450); }
            });
        });
        if (q.hasAttribute('data-autosubmit')) inp.addEventListener('change', function () { q.submit(); });
    });

    /* ---------- product page: price + add to cart ---------- */
    var form = $('#buyForm');
    if (form) {
        var out = $('#totalOut'), amountBox = $('#amountBox'), amountIn = $('#amountIn'), amountErr = $('#amountErr'), qtyBox = $('#qtyBox'), qtyIn = $('#qtyIn');
        var cfg = amountBox ? JSON.parse(amountBox.dataset.cfg || '{}') : null;
        var lastTxt = '';
        var isCustom = function () { var r = $('input[name=vid]:checked', form); return r ? r.value === '0' : !!(form.querySelector('input[name=vid][type=hidden]') && amountBox); };
        var amountVal = function () { return parseFloat(en(amountIn ? amountIn.value : '0')); };
        var calc = function () {
            var total = null;
            if (isCustom() && cfg && cfg.unitRaw !== null) {
                var a = amountVal();
                amountErr && (amountErr.textContent = '');
                if (!(a > 0)) total = null;
                else {
                    if (a < cfg.min || a > cfg.max) { amountErr.textContent = 'مقدار باید بین ' + fa(trimNum(cfg.min)) + ' و ' + fa(trimNum(cfg.max)) + ' ' + cfg.unit + ' باشد.'; }
                    var raw = cfg.unitRaw * a;
                    total = cfg.step ? Math.ceil(raw / cfg.step - 1e-9) * cfg.step : Math.round(raw);
                }
                $$('.amt-presets button').forEach(function (b) { b.classList.toggle('on', +b.dataset.preset === a); });
            } else {
                var r = $('input[name=vid]:checked', form);
                if (r && r.dataset.price) total = (+r.dataset.price) * Math.max(1, parseInt(en(qtyIn ? qtyIn.value : '1'), 10) || 1);
            }
            var txt = total === null ? '—' : money(total);
            if (txt !== lastTxt) { out.textContent = txt; out.classList.remove('flip'); void out.offsetWidth; out.classList.add('flip'); lastTxt = txt; }
        };
        $$('input[name=vid]', form).forEach(function (r) {
            r.addEventListener('change', function () {
                var c = r.value === '0';
                if (amountBox) amountBox.hidden = !c;
                if (qtyBox) qtyBox.hidden = c;
                if (c && amountIn) setTimeout(function () { amountIn.focus(); amountIn.select(); }, 60);
                calc();
            });
        });
        if (amountIn) {
            amountIn.addEventListener('input', calc);
            $$('.amt-btn', form).forEach(function (b) {
                b.addEventListener('click', function () {
                    var a = amountVal() || cfg.min, inc = cfg.inc || 1;
                    a = b.dataset.amt === '+' ? a + inc : a - inc;
                    a = Math.max(cfg.min, Math.min(cfg.max, Math.round(a / inc) * inc));
                    amountIn.value = trimNum(a); calc();
                });
            });
            $$('.amt-presets button').forEach(function (b) { b.addEventListener('click', function () { amountIn.value = b.dataset.preset; calc(); }); });
        }
        if (qtyIn) qtyIn.addEventListener('input', calc);
        calc();

        // add to cart without leaving the page (fly-to-cart); "buy now" submits normally → checkout
        form.addEventListener('submit', function (e) {
            var go = e.submitter ? e.submitter.value : 'cart';
            $$('.field-err', form).forEach(function (x) { x.textContent = ''; x.closest('.field').classList.remove('has-err'); });
            if (go !== 'cart' || !window.fetch) return;
            e.preventDefault();
            var btn = $('#addCart'), fd = new FormData(form), data = {};
            fd.forEach(function (v, k) { data[k] = v; });
            data.pid = form.dataset.pid;
            if (amountIn && !isCustom()) delete data.amount;
            if (data.amount) data.amount = en(data.amount);
            btn.disabled = true;
            api('api/cart-add', data).then(function (j) {
                btn.disabled = false;
                if (!j.ok) {
                    if (j.errors) Object.keys(j.errors).forEach(function (k) {
                        var el = form.querySelector('.field-err[data-for="' + k + '"]');
                        if (el) { el.textContent = j.errors[k]; el.closest('.field').classList.add('has-err'); }
                    });
                    toast('⚠️ ' + (j.error || 'خطا'));
                    return;
                }
                flyToCart(btn, j.count, j.count_fa);
                showAdded();
            }).catch(function () { btn.disabled = false; form.submit(); });
        });
    }

    function flyToCart(from, count, countFa) {
        var target = $('#cartBtn');
        if (!target) {   // first item: create the header cart button
            var actions = $('.header-actions'), search = $('#searchOpen');
            if (actions && search) {
                target = document.createElement('a');
                target.className = 'icon-btn cart-btn'; target.id = 'cartBtn'; target.href = (NX.base || '') + '/cart'; target.setAttribute('aria-label', 'سبد خرید');
                target.innerHTML = '<svg class="ic" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h2.5l2.6 12.2a1.6 1.6 0 0 0 1.6 1.3h9.1a1.6 1.6 0 0 0 1.6-1.2L21 8H5.6"/></svg><em class="hb-count" id="cartCount" hidden></em>';
                search.after(target);
            }
        }
        var badge = $('#cartCount');
        var upd = function () { if (badge) { badge.hidden = false; badge.textContent = countFa || fa(count); badge.classList.remove('pop'); void badge.offsetWidth; badge.classList.add('pop'); } if (target) { target.classList.remove('shake'); void target.offsetWidth; target.classList.add('shake'); } };
        if (!target || window.matchMedia('(prefers-reduced-motion: reduce)').matches) { upd(); return; }
        var a = from.getBoundingClientRect(), b = target.getBoundingClientRect();
        var dot = document.createElement('i');
        dot.className = 'fly-dot';
        dot.style.left = (a.left + a.width / 2) + 'px'; dot.style.top = (a.top + a.height / 2) + 'px';
        document.body.appendChild(dot);
        requestAnimationFrame(function () {
            dot.style.transform = 'translate(' + (b.left + b.width / 2 - a.left - a.width / 2) + 'px,' + (b.top + b.height / 2 - a.top - a.height / 2) + 'px) scale(.5)';
            dot.style.opacity = '.4';
        });
        setTimeout(function () { dot.remove(); upd(); }, 760);
    }
    function showAdded() {
        var bar = document.createElement('a');
        bar.className = 'nx-ntoast tone-success';
        bar.href = (NX.base || '') + '/cart';
        bar.innerHTML = '<span class="nb-ic">🛒</span><span class="nb-tx"><b>به سبد خرید اضافه شد</b><span>برای تکمیل خرید کلیک کنید</span></span>';
        document.body.appendChild(bar);
        requestAnimationFrame(function () { bar.classList.add('in'); });
        setTimeout(function () { bar.classList.remove('in'); setTimeout(function () { bar.remove(); }, 500); }, 3200);
    }

    /* ---------- order page ---------- */
    var op = $('#orderPage');
    if (op) {
        // advance animation after a live status change
        try { if (sessionStorage.getItem('nx_order_adv') === op.dataset.code) { sessionStorage.removeItem('nx_order_adv'); var st = $('.order-steps'); if (st) st.classList.add('advanced'); var f = document.createElement('div'); f.className = 'order-flash'; document.body.appendChild(f); setTimeout(function () { f.remove(); }, 1300); } } catch (e) {}

        // payment countdown
        var tm = $('.pay-timer');
        if (tm) {
            var left = +tm.dataset.left, total = +($('.pt-bar', tm).style.getPropertyValue('--t')) || left, end = Date.now() + left * 1000, bar = $('.pt-bar', tm), lab = $('.pt-left', tm);
            var tick = function () {
                var s = Math.max(0, Math.round((end - Date.now()) / 1000));
                lab.textContent = fa(String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0'));
                if (bar) bar.style.strokeDashoffset = (100.5 * (1 - s / total)).toFixed(2);
                tm.classList.toggle('urgent', s < 300);
                if (s <= 0) { clearInterval(iv); setTimeout(function () { location.reload(); }, 1500); }
            };
            var iv = setInterval(tick, 1000); tick();
        }

        // bank card tilt (desktop)
        var card = $('#bankCard');
        if (card && window.matchMedia('(hover: hover)').matches) {
            card.addEventListener('mousemove', function (e) {
                var r = card.getBoundingClientRect(), x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5;
                card.style.transform = 'perspective(900px) rotateY(' + (x * 10) + 'deg) rotateX(' + (-y * 10) + 'deg)';
            });
            card.addEventListener('mouseleave', function () { card.style.transform = ''; });
        }

        // receipt dropzone preview
        var drop = $('#dropZone'), rin = $('#receiptIn');
        if (drop && rin) {
            var show = function (file) {
                if (!file || !/^image\//.test(file.type)) return;
                var url = URL.createObjectURL(file);
                $('.drop-prev img', drop).src = url;
                $('.drop-prev', drop).hidden = false; $('.drop-empty', drop).hidden = true;
            };
            rin.addEventListener('change', function () { show(rin.files[0]); });
            ['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('over'); }); });
            ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function () { drop.classList.remove('over'); }); });
            drop.addEventListener('drop', function (e) { e.preventDefault(); if (e.dataTransfer.files.length) { rin.files = e.dataTransfer.files; show(rin.files[0]); } });
            $('#receiptForm').addEventListener('submit', function (e) {
                if (!rin.files.length) { e.preventDefault(); toast('⚠️ تصویر فیش را انتخاب کنید.'); return; }
                var b = this.querySelector('button[type=submit]'); b.disabled = true; b.innerHTML = '<span class="spin-dot"></span> در حال ارسال فیش...';
            });
        }

        // live status: when the admin approves / completes, the page updates itself
        if (op.dataset.final !== '1') {
            var poll = function () {
                if (document.hidden) return;
                api('api/order-status', { code: op.dataset.code }).then(function (j) {
                    if (j && j.ok && (j.status !== op.dataset.status || String(j.v) !== op.dataset.v)) {
                        try { sessionStorage.setItem('nx_order_adv', op.dataset.code); } catch (e) {}
                        location.reload();
                    }
                }).catch(function () {});
            };
            setInterval(poll, op.dataset.status === 'pending_payment' ? 20000 : 8000);
            document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
        }
    }

    /* ---------- support ---------- */
    $$('[data-toggle]').forEach(function (b) {
        b.addEventListener('click', function () { var t = $(b.dataset.toggle); if (!t) return; t.hidden = !t.hidden; if (!t.hidden) { t.scrollIntoView({ behavior: 'smooth', block: 'start' }); var i = $('input,textarea', t); if (i) setTimeout(function () { i.focus(); }, 300); } });
    });
    $$('.attach-btn input').forEach(function (i) {
        i.addEventListener('change', function () { var l = i.closest('.attach-btn'); l.classList.toggle('has', !!i.files.length); $('span', l).textContent = i.files.length ? '📎 ' + i.files[0].name : 'پیوست تصویر'; });
    });
    var tp = $('#ticketPage');
    if (tp) {
        if (location.hash === '#end' || tp.querySelectorAll('.bubble').length > 3) { var end = $('#end'); if (end) setTimeout(function () { end.scrollIntoView({ block: 'end' }); }, 300); }
        setInterval(function () {
            if (document.hidden) return;
            var ta = $('.chat-reply textarea');
            if (ta && ta.value.trim()) return;   // don't lose what the user is typing
            api('api/ticket-poll', { code: tp.dataset.code }).then(function (j) { if (j && j.ok && j.last > +tp.dataset.last) location.href = location.pathname + '?t=' + Date.now() + '#end'; }).catch(function () {});
        }, 12000);
    }

    /* ---------- notifications bell (members) ---------- */
    var nbBtn = $('#nbBtn'), nbPanel = $('#nbPanel'), nbList = $('#nbList'), nbCount = $('#nbCount');
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; };
    var tones = { success: '61,220,132', info: '61,187,255', warn: '255,176,61', error: '255,90,110', muted: '150,150,165' };
    function setCount(n) { if (!nbCount) return; nbCount.hidden = !n; nbCount.textContent = fa(n); }
    function renderList(items) {
        if (!items.length) { nbList.innerHTML = '<div class="nb-empty"><span>🔕</span>اعلان جدیدی نداری</div>'; return; }
        nbList.innerHTML = items.map(function (n) {
            return '<a class="nb-item' + (n.read ? '' : ' unread') + '" href="' + esc(n.link || '#') + '" style="--t:' + (tones[n.tone] || tones.info) + '">'
                + '<span class="nb-ic">' + esc(n.icon) + '</span><span class="nb-tx"><b>' + esc(n.title) + '</b>' + (n.body ? '<span>' + esc(n.body) + '</span>' : '') + '<small>' + esc(n.ago) + '</small></span></a>';
        }).join('');
    }
    if (nbBtn && nbPanel) {
        var open = false;
        var close = function () { open = false; nbPanel.hidden = true; nbBtn.setAttribute('aria-expanded', 'false'); };
        nbBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            open = !open; nbPanel.hidden = !open; nbBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) api('api/notifs', { read: 1 }).then(function (j) { if (j.ok) { renderList(j.items); setCount(0); } }).catch(function () {});
        });
        document.addEventListener('click', function (e) { if (open && !nbPanel.contains(e.target)) close(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && open) close(); });
    }
    // live: a status change shows a toast + bell ring (each notice once)
    document.addEventListener('nx:ping', function (e) {
        var j = e.detail;
        if (typeof j.notif !== 'number') return;
        var prev = nbCount && !nbCount.hidden ? 1 : 0;
        setCount(j.notif);
        if (j.latest) {
            var seen = ''; try { seen = localStorage.getItem('nx_nseen') || ''; } catch (x) {}
            if (seen !== j.latest.key) {
                try { localStorage.setItem('nx_nseen', j.latest.key); } catch (x) {}
                if (seen !== '' || prev) liveToast(j.latest);
                if (nbBtn) { nbBtn.classList.remove('ring'); void nbBtn.offsetWidth; nbBtn.classList.add('ring'); }
            }
        }
    });
    function liveToast(n) {
        var t = document.createElement('a');
        t.className = 'nx-ntoast'; t.href = n.link || '#'; t.style.setProperty('--t', tones[n.tone] || tones.info);
        t.innerHTML = '<span class="nb-ic">' + esc(n.icon) + '</span><span class="nb-tx"><b>' + esc(n.title) + '</b>' + (n.body ? '<span>' + esc(n.body) + '</span>' : '') + '</span>';
        document.body.appendChild(t);
        requestAnimationFrame(function () { t.classList.add('in'); });
        setTimeout(function () { t.classList.remove('in'); setTimeout(function () { t.remove(); }, 500); }, 6000);
    }
})();
