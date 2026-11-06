/* NEXUS | HUB — Admin panel scripts */
(function () {
    'use strict';
    var A = window.NXA || {};
    var $ = function (s, c) { return (c || document).querySelector(s); };
    var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };
    var fa = function (s) { return String(s).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; }); };
    var en = function (s) { return String(s).replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); }).replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); }); };
    A.$ = $; A.$$ = $$; A.fa = fa;

    /* ---------- Toast ---------- */
    var lastT = '', lastTAt = 0;
    A.toast = function (msg, type) {
        var now = Date.now(); if (msg === lastT && now - lastTAt < 1200) return;   // the same message twice in a row shows once
        lastT = msg; lastTAt = now;
        var t = document.createElement('div');
        t.className = 'toast ' + (type || 'success');
        t.textContent = msg;
        $('#toasts').appendChild(t);
        setTimeout(function () { t.classList.add('out'); setTimeout(function () { t.remove(); }, 400); }, 3800);
    };
    (A.flashes || []).forEach(function (f, i) { setTimeout(function () { A.toast(f.msg, f.type); }, 200 + i * 250); });

    /* ---------- Ajax ---------- */
    A.post = function (action, data, onProgress) {
        return new Promise(function (resolve, reject) {
            var fd = data instanceof FormData ? data : new FormData();
            if (!(data instanceof FormData)) Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
            fd.append('_csrf', A.csrf);
            fd.append('action', action);
            var x = new XMLHttpRequest();
            x.open('POST', A.ajax);
            x.setRequestHeader('X-CSRF-Token', A.csrf);
            if (onProgress && x.upload) x.upload.onprogress = function (e) { if (e.lengthComputable) onProgress(e.loaded / e.total); };
            x.onload = function () { try { resolve(JSON.parse(x.responseText)); } catch (e) { reject(x.responseText); } };
            x.onerror = function () { reject('network'); };
            x.send(fd);
        });
    };
    A.upload = function (file, onProgress) {
        var fd = new FormData(); fd.append('file', file);
        return A.post('upload', fd, onProgress);
    };

    /* ---------- Chunked upload (large videos) ---------- */
    // piece size fits under the host's upload limit, so 500 MB works even on a 2 MB limit
    A.chunkSize = function () {
        var lim = +A.uploadLimit || 0, size = 8 * 1048576;
        if (lim) size = Math.min(size, Math.floor(lim * 0.8));
        return Math.max(256 * 1024, size);
    };
    A.uploadChunked = function (file, kind, onProgress, isCancelled) {
        var size = A.chunkSize(), total = Math.max(1, Math.ceil(file.size / size)), uid = '';
        var bytes = new Uint8Array(16); (window.crypto || window.msCrypto).getRandomValues(bytes);
        bytes.forEach(function (b) { uid += ('0' + b.toString(16)).slice(-2); });
        return new Promise(function (resolve) {
            (function send(i, tries) {
                if (isCancelled && isCancelled()) return resolve({ ok: false, error: 'آپلود لغو شد.', cancelled: true });
                var fd = new FormData();
                fd.append('chunk', file.slice(i * size, Math.min(file.size, (i + 1) * size)), 'chunk');
                fd.append('uid', uid); fd.append('index', i); fd.append('total', total); fd.append('name', file.name); fd.append('kind', kind || 'post');
                A.post('upload_chunk', fd, function (p) { onProgress && onProgress((i + p) / total); }).then(function (j) {
                    if (!j.ok) return resolve(j);
                    if (j.done) { onProgress && onProgress(1); return resolve(j); }
                    send(i + 1, 0);
                }, function () {
                    if (tries < 3) return setTimeout(function () { send(i, tries + 1); }, 1500 * (tries + 1));   // flaky connection: retry the same piece
                    resolve({ ok: false, error: 'ارتباط با سرور قطع شد؛ دوباره تلاش کنید.' });
                });
            })(0, 0);
        });
    };
    // floating progress panel
    A.progressBox = function (title) {
        var box = document.createElement('div'); box.className = 'up-box';
        box.innerHTML = '<div class="up-head"><b></b><button type="button" class="adm-ib" aria-label="لغو">✕</button></div><div class="progress"><i></i></div><small class="muted"></small>';
        box.querySelector('b').textContent = title;
        document.body.appendChild(box);
        var cancelled = false;
        box.querySelector('button').addEventListener('click', function () { cancelled = true; box.remove(); });
        return {
            set: function (p, mbDone, mbTotal) { box.querySelector('i').style.width = (p * 100) + '%'; box.querySelector('small').textContent = fa(Math.round(p * 100)) + '٪ · ' + fa(mbDone.toFixed(1)) + ' از ' + fa(mbTotal.toFixed(1)) + ' مگابایت'; },
            done: function () { box.remove(); },
            cancelled: function () { return cancelled; }
        };
    };
    A.uploadVideo = function (file, kind) {
        var mb = file.size / 1048576, max = kind === 'story' ? 100 : 500;
        if (!/^video\/(mp4|webm|x-m4v)$/.test(file.type) && !/\.(mp4|webm|m4v)$/i.test(file.name)) { A.toast('فقط ویدیوهای MP4 یا WebM مجاز هستند.', 'error'); return Promise.resolve({ ok: false }); }
        if (mb > max) { A.toast('حداکثر حجم ویدیو ' + fa(max) + ' مگابایت است (این فایل ' + fa(Math.round(mb)) + ' مگابایت است).', 'error'); return Promise.resolve({ ok: false }); }
        var pb = A.progressBox('در حال آپلود: ' + file.name);
        return A.uploadChunked(file, kind, function (p) { pb.set(p, mb * p, mb); }, pb.cancelled).then(function (j) {
            pb.done();
            if (!j.ok && !j.cancelled) A.toast(j.error || 'آپلود ناموفق بود.', 'error');
            if (j.ok) A.toast('ویدیو آپلود شد ✅');
            return j;
        });
    };

    /* ---------- Sidebar ---------- */
    var side = $('#admSide'), ov = $('#admOverlay');
    if ($('#admBurger')) $('#admBurger').addEventListener('click', function () { side.classList.add('open'); ov.classList.add('open'); });
    if (ov) ov.addEventListener('click', function () { side.classList.remove('open'); ov.classList.remove('open'); });

    /* ---------- Confirm ---------- */
    /* handled by confirm.js (themed dialog) */

    /* ---------- Tabs ---------- */
    $$('[data-tabs]').forEach(function (wrap) {
        var btns = $$('[data-tab]', wrap), key = 'nxa_tab_' + location.pathname;
        var show = function (name) {
            btns.forEach(function (b) { b.classList.toggle('active', b.dataset.tab === name); });
            $$('[data-pane]').forEach(function (p) { p.hidden = p.dataset.pane !== name; });
            try { sessionStorage.setItem(key, name); } catch (e) {}
        };
        btns.forEach(function (b) { b.addEventListener('click', function (e) { e.preventDefault(); show(b.dataset.tab); }); });
        var saved = null; try { saved = sessionStorage.getItem(key); } catch (e) {}
        show(saved && btns.some(function (b) { return b.dataset.tab === saved; }) ? saved : btns[0].dataset.tab);
    });

    /* ---------- A field that fails browser validation inside a hidden tab would block "Save" silently:
       open its tab, focus it and say what is wrong ---------- */
    var lastInvalid = 0;
    document.addEventListener('invalid', function (e) {
        var el = e.target, pane = el.closest && el.closest('[data-pane]');
        if (pane && pane.hidden) {
            var btn = document.querySelector('[data-tab="' + pane.dataset.pane + '"]');
            if (btn) btn.click();
        }
        if (Date.now() - lastInvalid > 1500) {
            lastInvalid = Date.now();
            var lab = el.closest('.af') && el.closest('.af').querySelector('.af-label');
            A.toast('«' + (lab ? lab.textContent.trim() : (el.name || 'فیلد')) + '»: ' + (el.validationMessage || 'مقدار نامعتبر است.'), 'error');
            setTimeout(function () { try { el.focus(); el.scrollIntoView({ block: 'center', behavior: 'smooth' }); } catch (x) {} }, 30);
        }
    }, true);

    /* ---------- Colors / ranges ---------- */
    $$('input[type=color][data-sync]').forEach(function (c) {
        var t = c.parentNode.querySelector('input[name="' + c.dataset.sync + '"]');
        c.addEventListener('input', function () { t.value = c.value; t.dispatchEvent(new Event('input', { bubbles: true })); });
        t.addEventListener('input', function () { if (/^#[0-9a-f]{6}$/i.test(t.value)) c.value = t.value; });
    });
    $$('.af-range input').forEach(function (r) {
        var o = r.closest('.af-range').querySelector('output');
        r.addEventListener('input', function () { o.textContent = r.value; });
    });

    /* ---------- Select all ---------- */
    $$('[data-check-all]').forEach(function (c) {
        c.addEventListener('change', function () { $$('input[name="' + c.dataset.checkAll + '"]').forEach(function (x) { x.checked = c.checked; }); });
    });

    /* ---------- Media picker ---------- */
    var mm = $('#mediaModal'), mmGrid = $('#mmGrid'), mmMore = $('#mmMore'), mmCb = null, mmPage = 1;
    function mmItem(m) {
        var d = document.createElement('div');
        d.className = 'm-item';
        d.innerHTML = '<img loading="lazy" alt=""><div class="m-info"><span></span></div>';
        d.querySelector('img').src = m.url;
        d.querySelector('span').textContent = m.w ? fa(m.w + '×' + m.h) : '';
        d.addEventListener('click', function () { if (mmCb) mmCb(m); A.closeMedia(); });
        return d;
    }
    function mmLoad(page) {
        A.post('media_list', { page: page }).then(function (j) {
            if (page === 1) mmGrid.innerHTML = '';
            if (!j.items.length && page === 1) mmGrid.innerHTML = '<div class="empty-state"><span>🖼️</span>هنوز تصویری آپلود نشده است.</div>';
            j.items.forEach(function (m) { mmGrid.appendChild(mmItem(m)); });
            mmMore.hidden = !j.more; mmPage = page;
        });
    }
    A.pickMedia = function (cb) { mmCb = cb; mm.hidden = false; mmLoad(1); };
    A.closeMedia = function () { mm.hidden = true; mmCb = null; };
    if (mm) {
        $$('[data-close]', mm).forEach(function (b) { b.addEventListener('click', A.closeMedia); });
        mmMore.addEventListener('click', function () { mmLoad(mmPage + 1); });
        $('#mmUpload').addEventListener('change', function () {
            var files = Array.prototype.slice.call(this.files), input = this;
            var next = function () {
                var f = files.shift();
                if (!f) { input.value = ''; return; }
                A.toast('در حال آپلود ' + f.name + ' ...', 'info');
                A.upload(f).then(function (j) {
                    if (j.ok) mmGrid.insertBefore(mmItem({ id: j.id, url: j.url, path: j.path }), mmGrid.firstChild);
                    else A.toast(j.error || 'خطا در آپلود', 'error');
                    next();
                });
            };
            if (mmGrid.querySelector('.empty-state')) mmGrid.innerHTML = '';
            next();
        });
    }
    $$('[data-image-field]').forEach(function (f) {
        var input = $('input[type=hidden]', f), box = $('.img-pick', f), img = $('img', box);
        var open = function () { A.pickMedia(function (m) { input.value = m.path; img.src = m.url; box.classList.add('has'); input.dispatchEvent(new Event('change', { bubbles: true })); }); };
        box.addEventListener('click', open);
        $('[data-pick]', f).addEventListener('click', open);
        $('[data-clear]', f).addEventListener('click', function () { input.value = ''; img.removeAttribute('src'); box.classList.remove('has'); input.dispatchEvent(new Event('change', { bubbles: true })); });
    });

    // ready-made images under an image field
    $$('[data-image-field]').forEach(function (f) {
        var input = $('input[type=hidden]', f), box = $('.img-pick', f), img = $('img', box);
        var mark = function () { $$('.ip', f).forEach(function (b) { b.classList.toggle('on', b.dataset.preset === input.value); }); };
        $$('.ip', f).forEach(function (b) {
            b.addEventListener('click', function () { input.value = b.dataset.preset; img.src = b.dataset.url; box.classList.add('has'); mark(); input.dispatchEvent(new Event('change', { bubbles: true })); });
        });
        input.addEventListener('change', mark); mark();
    });

    /* ---------- Emoji / icon picker for icon fields (input[data-emoji]) ---------- */
    var EMO = {
        'فروشگاه': '🛍️ 🛒 🎁 💎 ⭐ 🌟 ✨ 💳 💰 💵 🏷️ 🔥 ⚡ 🚀 👑 🎯 🔑 🎟️ 📦 🧾 ✅ 💯 🆕 🎊',
        'تلگرام و شبکه': '✈️ 📢 💬 📱 📲 🤖 👥 🔔 📣 🌐 🔗 📡 📧 🛰️ 🧭',
        'گیم': '🎮 🕹️ 👾 🏆 🎲 🧩 ⚔️ 🛡️ 🏁 🥇 🎰 🃏',
        'تکنولوژی': '💻 🖥️ ⌨️ 🖱️ 📷 🎧 🔋 💾 🧠 ⚙️ 🛠️ 🔐 📈 🤑',
        'سرگرمی و هنر': '🎬 🎵 🎤 🎨 📸 🎉 🎭 🍿 📺 📰 📚 ✍️ 🎸 🎹',
        'دیگر': '❤️ 💜 💙 💚 🧡 🖤 🌈 ☕ 🍀 🌙 ☀️ ⏰ 📍 🧸 🎈 🪙'
    };
    var emoPop = null;
    var closeEmo = function () { if (emoPop) { emoPop.parentNode.removeChild(emoPop); emoPop = null; } };
    $$('input[data-emoji]').forEach(function (inp) {
        var wrap = document.createElement('span'); wrap.className = 'emo-field';
        inp.parentNode.insertBefore(wrap, inp); wrap.appendChild(inp);
        var btn = document.createElement('button'); btn.type = 'button'; btn.className = 'emo-btn'; btn.title = 'انتخاب آیکن';
        btn.innerHTML = '<span>' + (inp.value || '😀') + '</span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="m6 9 6 6 6-6"/></svg>';
        wrap.appendChild(btn);
        inp.addEventListener('input', function () { btn.firstChild.textContent = inp.value || '😀'; });
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (emoPop && emoPop._for === inp) { closeEmo(); return; }
            closeEmo();
            var pop = document.createElement('div'); pop.className = 'emo-pop'; pop._for = inp;
            var tabs = '<div class="emo-tabs">', panes = '';
            Object.keys(EMO).forEach(function (k, i) {
                tabs += '<button type="button" data-et="' + i + '" class="' + (i ? '' : 'on') + '">' + k + '</button>';
                panes += '<div class="emo-grid" data-ep="' + i + '"' + (i ? ' hidden' : '') + '>' + EMO[k].split(' ').map(function (x) { return '<button type="button" class="emo" data-e="' + x + '">' + x + '</button>'; }).join('') + '</div>';
            });
            pop.innerHTML = tabs + '</div>' + panes + '<small class="emo-hint">یا هر ایموجی دیگری را مستقیم در فیلد تایپ یا پیست کنید</small>';
            document.body.appendChild(pop);
            var r = btn.getBoundingClientRect(), vw = document.documentElement.clientWidth, w = Math.min(340, vw - 16);
            pop.style.width = w + 'px';
            pop.style.left = Math.max(8, Math.min(r.right - w, vw - w - 8)) + 'px';
            var below = window.innerHeight - r.bottom;
            if (below < 300 && r.top > below) pop.style.bottom = (window.innerHeight - r.top + 6) + 'px'; else pop.style.top = (r.bottom + 6) + 'px';
            pop.addEventListener('click', function (ev) {
                ev.stopPropagation();
                var t = ev.target.closest('[data-et]');
                if (t) { $$('[data-et]', pop).forEach(function (x) { x.classList.toggle('on', x === t); }); $$('[data-ep]', pop).forEach(function (g) { g.hidden = g.dataset.ep !== t.dataset.et; }); return; }
                var em = ev.target.closest('[data-e]');
                if (em) { inp.value = em.dataset.e; inp.dispatchEvent(new Event('input', { bubbles: true })); inp.dispatchEvent(new Event('change', { bubbles: true })); closeEmo(); }
            });
            emoPop = pop;
        });
    });
    document.addEventListener('click', closeEmo);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeEmo(); });
    window.addEventListener('scroll', closeEmo, { passive: true });

    /* ---------- Multi-select dropdown (select[multiple][data-multi]) ---------- */
    $$('select[multiple][data-multi]').forEach(function (sel) {
        var wrap = document.createElement('div'); wrap.className = 'nsel nsel-multi';
        sel.parentNode.insertBefore(wrap, sel); wrap.appendChild(sel);
        sel.classList.add('nsel-native'); sel.tabIndex = -1;
        var btn = document.createElement('button'); btn.type = 'button'; btn.className = 'nsel-btn';
        btn.innerHTML = '<span class="nsel-val"></span><svg class="nsel-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>';
        wrap.appendChild(btn);
        var val = $('.nsel-val', btn), menu = document.createElement('div'); menu.className = 'nsel-menu nsel-mmenu'; menu.hidden = true;
        document.body.appendChild(menu);   // on <body>: a card's stacking context must not cover the list
        var place = function () {
            var r = btn.getBoundingClientRect(), vw = document.documentElement.clientWidth, vh = document.documentElement.clientHeight;
            menu.style.width = Math.min(r.width, vw - 16) + 'px';
            menu.style.left = Math.max(8, r.left) + 'px';
            menu.style.maxHeight = 'none';
            var full = menu.offsetHeight, below = vh - r.bottom - 16, above = r.top - 16, up = full > below && above > below;
            menu.style.maxHeight = Math.min(full, up ? above : below) + 'px';
            menu.style.top = up ? '' : (r.bottom + 6) + 'px';
            menu.style.bottom = up ? (vh - r.top + 6) + 'px' : '';
        };
        window.addEventListener('scroll', function () { if (!menu.hidden) place(); }, { passive: true });
        window.addEventListener('resize', function () { if (!menu.hidden) place(); });
        var render = function () {
            var on = Array.prototype.filter.call(sel.options, function (o) { return o.selected; });
            val.innerHTML = on.length ? on.map(function (o) { return '<em class="nsel-chip">' + o.textContent + '</em>'; }).join('') : '<span class="muted">' + (sel.dataset.placeholder || 'انتخاب کنید') + '</span>';
            menu.innerHTML = Array.prototype.map.call(sel.options, function (o, i) {
                return '<div class="nsel-opt nsel-chk' + (o.selected ? ' sel' : '') + '" data-i="' + i + '"><i class="nsel-box"></i>' + o.textContent + '</div>';
            }).join('') + '<div class="nsel-mfoot"><button type="button" data-all>انتخاب همه</button><button type="button" data-none>هیچ‌کدام</button><button type="button" data-done class="ok">تایید</button></div>';
        };
        render();
        var setOpen = function (o) { menu.hidden = !o; wrap.classList.toggle('open', o); document.documentElement.classList.toggle('nsel-open', o); if (o) place(); };
        btn.addEventListener('click', function (e) { e.stopPropagation(); setOpen(menu.hidden); });
        menu.addEventListener('click', function (e) {
            e.stopPropagation();
            var o = e.target.closest('[data-i]');
            if (o) { var opt = sel.options[+o.dataset.i]; opt.selected = !opt.selected; }
            else if (e.target.closest('[data-all]')) Array.prototype.forEach.call(sel.options, function (x) { x.selected = true; });
            else if (e.target.closest('[data-none]')) Array.prototype.forEach.call(sel.options, function (x) { x.selected = false; });
            else if (e.target.closest('[data-done]')) setOpen(false);
            render();
            sel.dispatchEvent(new Event('change', { bubbles: true }));
        });
        document.addEventListener('click', function () { if (!menu.hidden) setOpen(false); });
    });

    /* ---------- Tag input ---------- */
    $$('[data-tag-input]').forEach(function (wrap) {
        var hidden = $('input[type=hidden]', wrap), inp = $('input[type=text]', wrap);
        var tags = hidden.value ? hidden.value.split(',').map(function (t) { return t.trim(); }).filter(Boolean) : [];
        var render = function () {
            $$('.tg', wrap).forEach(function (t) { t.remove(); });
            tags.forEach(function (t, i) {
                var s = document.createElement('span'); s.className = 'tg'; s.textContent = '#' + t;
                var b = document.createElement('button'); b.type = 'button'; b.textContent = '×';
                b.addEventListener('click', function () { tags.splice(i, 1); render(); });
                s.appendChild(b); wrap.insertBefore(s, inp);
            });
            hidden.value = tags.join(',');
        };
        var add = function () {
            inp.value.split(/[,،]/).forEach(function (v) { v = v.trim().replace(/^#/, ''); if (v && tags.indexOf(v) < 0) tags.push(v); });
            inp.value = ''; render();
        };
        inp.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ',' || e.key === '،') { e.preventDefault(); add(); }
            if (e.key === 'Backspace' && !inp.value && tags.length) { tags.pop(); render(); }
        });
        inp.addEventListener('blur', add);
        wrap.addEventListener('click', function () { inp.focus(); });
        render();
    });

    /* ---------- Slug ---------- */
    A.slugify = function (t) {
        return en(t).trim().toLowerCase().replace(/[‌‏‎]/g, '-').replace(/[^\p{L}\p{N}]+/gu, '-').replace(/-+/g, '-').replace(/^-|-$/g, '').slice(0, 120);
    };
    $$('[data-slug-from]').forEach(function (slug) {
        var src = $(slug.dataset.slugFrom), touched = slug.value !== '';
        slug.addEventListener('input', function () { touched = slug.value !== ''; });
        if (src) src.addEventListener('input', function () { if (!touched) slug.value = A.slugify(src.value); });
    });

    /* ---------- Sortable ---------- */
    A.sortable = function (list, onChange) {
        var drag = null;
        list.addEventListener('dragstart', function (e) { drag = e.target.closest('.sort-item'); if (drag) { drag.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; } });
        list.addEventListener('dragend', function () { if (drag) drag.classList.remove('dragging'); drag = null; onChange && onChange(); });
        list.addEventListener('dragover', function (e) {
            e.preventDefault();
            var over = e.target.closest('.sort-item');
            if (!drag || !over || over === drag) return;
            var r = over.getBoundingClientRect();
            list.insertBefore(drag, (e.clientY - r.top) > r.height / 2 ? over.nextSibling : over);
        });
        list.addEventListener('click', function (e) {
            var b = e.target.closest('[data-mv]'); if (!b) return;
            e.preventDefault();
            var it = b.closest('.sort-item');
            if (b.dataset.mv === 'up' && it.previousElementSibling) list.insertBefore(it, it.previousElementSibling);
            if (b.dataset.mv === 'down' && it.nextElementSibling) list.insertBefore(it.nextElementSibling, it);
            onChange && onChange();
        });
    };

    /* ---------- Chart (SVG) ---------- */
    $$('[data-chart]').forEach(function (wrap) {
        var data = JSON.parse(wrap.dataset.chart), W = wrap.clientWidth || 600, H = wrap.clientHeight || 260, pad = 34;
        var series = data.series, labels = data.labels, max = 1;
        series.forEach(function (s) { s.values.forEach(function (v) { if (v > max) max = v; }); });
        max = Math.ceil(max * 1.15);
        var x = function (i) { return W - pad - i * ((W - pad * 2) / Math.max(1, labels.length - 1)); };
        var y = function (v) { return H - pad - (v / max) * (H - pad * 2); };
        var svg = '<svg viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="none"><defs>';
        series.forEach(function (s, k) { svg += '<linearGradient id="cg' + k + '" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="' + s.color + '" stop-opacity=".45"/><stop offset="1" stop-color="' + s.color + '" stop-opacity="0"/></linearGradient>'; });
        svg += '</defs>';
        for (var g = 0; g <= 4; g++) { var gy = pad + g * (H - pad * 2) / 4; svg += '<line x1="' + pad + '" x2="' + (W - pad) + '" y1="' + gy + '" y2="' + gy + '" stroke="rgba(255,255,255,.06)"/><text x="' + (pad - 6) + '" y="' + (gy + 4) + '" fill="#777" font-size="10" text-anchor="end">' + fa(Math.round(max - g * max / 4)) + '</text>'; }
        labels.forEach(function (l, i) { if (i % Math.ceil(labels.length / 7) === 0) svg += '<text x="' + x(i) + '" y="' + (H - 8) + '" fill="#888" font-size="10" text-anchor="middle">' + l + '</text>'; });
        series.forEach(function (s, k) {
            var pts = s.values.map(function (v, i) { return [x(i), y(v)]; });
            var d = pts.map(function (p, i) {
                if (i === 0) return 'M' + p[0] + ' ' + p[1];
                var pr = pts[i - 1], cx = (pr[0] + p[0]) / 2;
                return 'C' + cx + ' ' + pr[1] + ' ' + cx + ' ' + p[1] + ' ' + p[0] + ' ' + p[1];
            }).join(' ');
            svg += '<path d="' + d + ' L' + pts[pts.length - 1][0] + ' ' + (H - pad) + ' L' + pts[0][0] + ' ' + (H - pad) + 'Z" fill="url(#cg' + k + ')"/>';
            svg += '<path d="' + d + '" fill="none" stroke="' + s.color + '" stroke-width="2.5" stroke-linecap="round" style="filter:drop-shadow(0 0 6px ' + s.color + ');stroke-dasharray:3000;stroke-dashoffset:3000;animation:dash 2s ease forwards ' + (k * .3) + 's"/>';
            pts.forEach(function (p, i) { svg += '<circle cx="' + p[0] + '" cy="' + p[1] + '" r="10" fill="transparent" data-i="' + i + '"/>'; });
        });
        svg += '<style>@keyframes dash{to{stroke-dashoffset:0}}</style></svg><div class="chart-tip"></div>';
        wrap.innerHTML = svg;
        var tip = $('.chart-tip', wrap);
        $$('circle', wrap).forEach(function (c) {
            c.addEventListener('mouseenter', function () {
                var i = +c.dataset.i;
                tip.innerHTML = '<b>' + labels[i] + '</b><br>' + series.map(function (s) { return '<span style="color:' + s.color + '">●</span> ' + s.name + ': ' + fa(s.values[i]); }).join('<br>');
                tip.style.left = (c.getAttribute('cx') / W * 100) + '%'; tip.style.top = (c.getAttribute('cy') / H * 100) + '%'; tip.style.opacity = 1;
            });
            c.addEventListener('mouseleave', function () { tip.style.opacity = 0; });
        });
    });

    /* ---------- Shop notifications bell (new receipts / support messages) ---------- */
    (function () {
        var btn = $('#anbBtn'), panel = $('#anbPanel'), list = $('#anbList'), cnt = $('#anbCount');
        if (!btn) return;
        var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; };
        var setN = function (n) { cnt.hidden = !n; cnt.textContent = fa(n); };
        var render = function (items) {
            list.innerHTML = items.length ? items.map(function (n) {
                return '<a class="anb-item' + (n.read ? '' : ' unread') + '" href="' + esc(n.link) + '"><span>' + esc(n.icon) + '</span><span><b>' + esc(n.title) + '</b><small>' + esc(n.body) + '</small><small>' + esc(n.ago) + '</small></span></a>';
            }).join('') : '<div class="anb-empty">🎉 کار انجام‌نشده‌ای نیست</div>';
        };
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            panel.hidden = !panel.hidden;
            if (!panel.hidden) A.post('admin_notifs', { read: 1 }).then(function (j) { if (j.ok) { render(j.items); setN(0); } });
        });
        document.addEventListener('click', function (e) { if (!panel.hidden && !panel.contains(e.target) && e.target !== btn) panel.hidden = true; });
        var seen = null;
        var poll = function () {
            if (document.hidden) return;
            A.post('admin_notifs', {}).then(function (j) {
                if (!j.ok) return;
                setN(j.unread);
                var k = j.latest ? j.latest.key : '';
                if (seen !== null && k && k !== seen) {
                    btn.classList.remove('ring'); void btn.offsetWidth; btn.classList.add('ring');
                    var t = document.createElement('a');
                    t.className = 'anb-toast'; t.href = j.latest.link;
                    t.innerHTML = '<span style="font-size:1.4rem">' + esc(j.latest.icon) + '</span><span><b>' + esc(j.latest.title) + '</b><br><small class="muted">' + esc(j.latest.body) + '</small></span>';
                    document.body.appendChild(t);
                    requestAnimationFrame(function () { t.classList.add('in'); });
                    setTimeout(function () { t.classList.remove('in'); setTimeout(function () { t.remove(); }, 500); }, 7000);
                }
                seen = k;
                // keep the sidebar badges current
                $$('.adm-nav a').forEach(function (a) {
                    var n = /orders\.php$/.test(a.getAttribute('href')) ? j.review : /tickets\.php$/.test(a.getAttribute('href')) ? j.tickets : null;
                    if (n === null) return;
                    var b = $('.nav-badge', a);
                    if (!n) { if (b) b.remove(); return; }
                    if (!b) { b = document.createElement('em'); b.className = 'nav-badge'; a.appendChild(b); }
                    b.textContent = fa(n);
                });
            }).catch(function () {});
        };
        poll();
        setInterval(poll, 30000);
    })();

    /* ---------- Copy ---------- */
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-copy]'); if (!b) return;
        var t = b.dataset.copy;
        if (navigator.clipboard) navigator.clipboard.writeText(t).then(function () { A.toast('کپی شد ✅'); });
        else prompt('کپی کنید:', t);
    });

    /* ==========================================================================
       Jalali date picker
       ========================================================================== */
    function g2j(gy, gm, gd) {
        var g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334], gy2 = gm > 2 ? gy + 1 : gy;
        var days = 355666 + 365 * gy + ~~((gy2 + 3) / 4) - ~~((gy2 + 99) / 100) + ~~((gy2 + 399) / 400) + gd + g_d_m[gm - 1];
        var jy = -1595 + 33 * ~~(days / 12053); days %= 12053; jy += 4 * ~~(days / 1461); days %= 1461;
        if (days > 365) { jy += ~~((days - 1) / 365); days = (days - 1) % 365; }
        var jm, jd;
        if (days < 186) { jm = 1 + ~~(days / 31); jd = 1 + days % 31; } else { jm = 7 + ~~((days - 186) / 30); jd = 1 + (days - 186) % 30; }
        return [jy, jm, jd];
    }
    function j2g(jy, jm, jd) {
        jy += 1595;
        var days = -355668 + 365 * jy + ~~(jy / 33) * 8 + ~~(((jy % 33) + 3) / 4) + jd + (jm < 7 ? (jm - 1) * 31 : (jm - 7) * 30 + 186);
        var gy = 400 * ~~(days / 146097); days %= 146097;
        if (days > 36524) { days--; gy += 100 * ~~(days / 36524); days %= 36524; if (days >= 365) days++; }
        gy += 4 * ~~(days / 1461); days %= 1461;
        if (days > 365) { gy += ~~((days - 1) / 365); days = (days - 1) % 365; }
        var gd = days + 1, sal = [0, 31, ((gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31], gm;
        for (gm = 0; gm < 13 && gd > sal[gm]; gm++) gd -= sal[gm];
        return [gy, gm, gd];
    }
    A.g2j = g2j; A.j2g = j2g;
    var months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    function monthLen(jy, jm) {
        if (jm <= 6) return 31; if (jm <= 11) return 30;
        var a = j2g(jy, 12, 30), b = j2g(jy + 1, 1, 1);
        return (a[0] === b[0] && a[1] === b[1] && a[2] === b[2]) ? 29 : 30;
    }
    function pad(n) { return ('0' + n).slice(-2); }
    var picker = null;
    function closePicker() { if (picker) { picker.remove(); picker = null; } }
    function openPicker(input) {
        closePicker();
        var now = new Date(), t = g2j(now.getFullYear(), now.getMonth() + 1, now.getDate());
        var m = en(input.value).match(/^(\d{4})\/(\d{1,2})\/(\d{1,2})(?:\s+(\d{1,2}):(\d{1,2}))?/);
        var sel = m ? [+m[1], +m[2], +m[3]] : null;
        var hh = m && m[4] ? +m[4] : now.getHours(), mi = m && m[5] ? +m[5] : now.getMinutes();
        var vy = sel ? sel[0] : t[0], vm = sel ? sel[1] : t[1];
        picker = document.createElement('div');
        picker.className = 'jdp';
        var render = function () {
            var g = j2g(vy, vm, 1), first = (new Date(g[0], g[1] - 1, g[2]).getDay() + 1) % 7, len = monthLen(vy, vm);
            var h = '<div class="jdp-head"><button type="button" data-n="-1">›</button><b>' + months[vm - 1] + ' ' + fa(vy) + '</b><button type="button" data-n="1">‹</button></div><div class="jdp-grid">';
            ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'].forEach(function (d) { h += '<span>' + d + '</span>'; });
            for (var i = 0; i < first; i++) h += '<span></span>';
            for (var d = 1; d <= len; d++) {
                var cls = [];
                if (vy === t[0] && vm === t[1] && d === t[2]) cls.push('today');
                if (sel && vy === sel[0] && vm === sel[1] && d === sel[2]) cls.push('sel');
                if ((first + d - 1) % 7 === 6) cls.push('fri');
                h += '<button type="button" class="' + cls.join(' ') + '" data-d="' + d + '">' + fa(d) + '</button>';
            }
            h += '</div><div class="jdp-foot">ساعت <input type="number" min="0" max="23" value="' + hh + '" data-h> : <input type="number" min="0" max="59" value="' + mi + '" data-m>'
                + '<button type="button" class="abtn abtn-ghost abtn-xs" data-now>اکنون</button><button type="button" class="abtn abtn-ghost abtn-xs" data-clear>پاک</button></div>';
            picker.innerHTML = h;
        };
        var write = function () {
            if (!sel) return;
            hh = Math.min(23, Math.max(0, +$('[data-h]', picker).value || 0)); mi = Math.min(59, Math.max(0, +$('[data-m]', picker).value || 0));
            input.value = sel[0] + '/' + pad(sel[1]) + '/' + pad(sel[2]) + ' ' + pad(hh) + ':' + pad(mi);
            input.dispatchEvent(new Event('change', { bubbles: true }));
        };
        render();
        picker.addEventListener('click', function (e) {
            e.stopPropagation();
            var b = e.target.closest('button'); if (!b) return;
            if (b.dataset.n) { vm -= +b.dataset.n * -1; if (vm < 1) { vm = 12; vy--; } if (vm > 12) { vm = 1; vy++; } render(); }
            else if (b.dataset.d) { sel = [vy, vm, +b.dataset.d]; write(); render(); }
            else if (b.hasAttribute('data-now')) { var n = new Date(); sel = t.slice(); vy = t[0]; vm = t[1]; hh = n.getHours(); mi = n.getMinutes(); render(); write(); closePicker(); }
            else if (b.hasAttribute('data-clear')) { input.value = ''; input.dispatchEvent(new Event('change', { bubbles: true })); closePicker(); }
        });
        picker.addEventListener('change', function (e) { if (e.target.matches('[data-h],[data-m]')) write(); });
        document.body.appendChild(picker);
        var r = input.getBoundingClientRect();
        var left = Math.max(8, Math.min(window.innerWidth - 308, r.left + window.scrollX));
        picker.style.top = (r.bottom + window.scrollY + 6) + 'px';
        picker.style.left = left + 'px';
    }
    document.addEventListener('focusin', function (e) { if (e.target.matches('[data-jdp]')) openPicker(e.target); });
    document.addEventListener('click', function (e) { if (picker && !picker.contains(e.target) && !e.target.matches('[data-jdp]')) closePicker(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closePicker(); if (mm && !mm.hidden) A.closeMedia(); } });

    /* ==========================================================================
       Custom select (glass dropdown) — replaces the native popup, keeps the
       native <select> in the form for submission, validation and change events
       ========================================================================== */
    var openSel = null;
    function closeSelect() {
        if (!openSel) return;
        openSel.wrap.classList.remove('open');
        openSel.menu.remove();
        openSel = null;
    }
    A.enhanceSelect = function (sel) {
        if (sel._nx || sel.multiple || sel.size > 1 || sel.hasAttribute('data-native')) return;
        sel._nx = true;
        var wrap = document.createElement('div');
        wrap.className = 'nsel' + (sel.closest('.ed-toolbar') ? ' nsel-ed' : '') + (sel.closest('.toolbar') ? ' nsel-tb' : '');
        sel.parentNode.insertBefore(wrap, sel);
        wrap.appendChild(sel);
        sel.classList.add('nsel-native');
        sel.tabIndex = -1;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'nsel-btn';
        btn.setAttribute('aria-haspopup', 'listbox');
        btn.innerHTML = '<span class="nsel-val"></span><svg class="nsel-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>';
        wrap.appendChild(btn);
        var val = btn.querySelector('.nsel-val'), menu = document.createElement('div'), hi = -1;
        menu.className = 'nsel-menu';
        menu.setAttribute('role', 'listbox');

        var refresh = function () {
            var o = sel.options[sel.selectedIndex];
            val.textContent = o ? o.textContent : '';
            btn.disabled = sel.disabled;
        };
        sel._nxRefresh = refresh;
        var highlight = function (i) {
            var items = $$('.nsel-opt', menu);
            if (!items.length) return;
            hi = Math.max(0, Math.min(items.length - 1, i));
            items.forEach(function (it, k) { it.classList.toggle('hi', k === hi); });
            items[hi].scrollIntoView({ block: 'nearest' });
        };
        var choose = function (i) {
            if (i < 0 || i >= sel.options.length || sel.options[i].disabled) return;
            var changed = sel.selectedIndex !== i;
            sel.selectedIndex = i;
            refresh();
            closeSelect();
            if (changed) {
                sel.dispatchEvent(new Event('input', { bubbles: true }));
                sel.dispatchEvent(new Event('change', { bubbles: true }));
            }
        };
        var place = function () {
            // clientWidth/Height = viewport without the page scrollbar (same box position:fixed uses)
            var r = btn.getBoundingClientRect(), vw = document.documentElement.clientWidth, vh = document.documentElement.clientHeight, gap = 6;
            // same width as the button, flush with its right edge (RTL); narrow buttons get a minimum width
            var w = Math.min(Math.max(r.width, 140), vw - 16);
            var left = Math.max(8, Math.min(r.right - w, vw - w - 8));
            menu.style.width = w + 'px';
            menu.style.left = left + 'px';
            menu.style.maxHeight = 'none';
            var full = menu.offsetHeight, below = vh - r.bottom - gap - 10, above = r.top - gap - 10;
            var up = full > below && above > below;
            menu.style.maxHeight = Math.min(full, up ? above : below) + 'px';
            menu.style.top = up ? '' : (r.bottom + gap) + 'px';
            menu.style.bottom = up ? (vh - r.top + gap) + 'px' : '';
            menu.classList.toggle('up', up);
        };
        var open = function () {
            if (sel.disabled) return;
            closeSelect();
            menu.innerHTML = '';
            Array.prototype.forEach.call(sel.options, function (o, i) {
                var it = document.createElement('div');
                it.className = 'nsel-opt' + (i === sel.selectedIndex ? ' sel' : '') + (o.disabled ? ' dis' : '');
                it.setAttribute('role', 'option');
                it.textContent = o.textContent;
                it.dataset.i = i;
                menu.appendChild(it);
            });
            document.body.appendChild(menu);
            wrap.classList.add('open');
            openSel = { wrap: wrap, menu: menu };
            place();
            highlight(sel.selectedIndex);
        };
        // جلوگیری از گرفتن فوکوس (برای حفظ انتخاب متن در ویرایشگر)
        btn.addEventListener('mousedown', function (e) { e.preventDefault(); });
        menu.addEventListener('mousedown', function (e) { e.preventDefault(); });
        btn.addEventListener('click', function (e) { e.stopPropagation(); if (openSel && openSel.wrap === wrap) closeSelect(); else open(); });
        menu.addEventListener('click', function (e) { e.stopPropagation(); var it = e.target.closest('.nsel-opt'); if (it) choose(+it.dataset.i); });
        menu.addEventListener('mousemove', function (e) { var it = e.target.closest('.nsel-opt'); if (it && +it.dataset.i !== hi) highlight(+it.dataset.i); });
        btn.addEventListener('keydown', function (e) {
            var isOpen = openSel && openSel.wrap === wrap;
            if (['ArrowDown', 'ArrowUp', 'Enter', ' '].indexOf(e.key) >= 0) e.preventDefault();
            if (!isOpen) { if (['ArrowDown', 'ArrowUp', 'Enter', ' '].indexOf(e.key) >= 0) open(); return; }
            if (e.key === 'ArrowDown') highlight(hi + 1);
            else if (e.key === 'ArrowUp') highlight(hi - 1);
            else if (e.key === 'Enter' || e.key === ' ') choose(hi);
            else if (e.key === 'Escape' || e.key === 'Tab') closeSelect();
        });
        sel.addEventListener('change', refresh);
        if (sel.form) sel.form.addEventListener('reset', function () { setTimeout(refresh, 0); });
        refresh();
    };
    A.enhanceSelects = function (root) { $$('select', root).forEach(A.enhanceSelect); };
    document.addEventListener('click', closeSelect);
    window.addEventListener('resize', closeSelect);
    window.addEventListener('scroll', function (e) { if (openSel && !openSel.menu.contains(e.target)) closeSelect(); }, true);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeSelect(); });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { A.enhanceSelects(); });
    else A.enhanceSelects();

    /* ---------- Countdown (ad previews) ---------- */
    setInterval(function () {
        $$('.nxp-countdown').forEach(function (c) {
            var diff = Math.max(0, Math.floor((new Date(c.dataset.end).getTime() - Date.now()) / 1000));
            var v = { d: Math.floor(diff / 86400), h: Math.floor(diff % 86400 / 3600), m: Math.floor(diff % 3600 / 60), s: diff % 60 };
            $$('b', c).forEach(function (b) { b.textContent = fa(v[b.dataset.u]); });
        });
    }, 1000);

    /* animated previews (themes / card styles / effects): only the ones on screen run, the rest pause */
    function gatePreviews() {
        var opts = [].slice.call(document.querySelectorAll('.opt')).filter(function (o) { return o.querySelector('.swatch, .cs-thumb, .fx-thumb'); });
        if (!opts.length) return;
        opts.forEach(function (o) { o.classList.add('anim-gate'); });
        if (!('IntersectionObserver' in window)) { opts.forEach(function (o) { o.classList.add('live'); }); return; }
        var io = new IntersectionObserver(function (es) { es.forEach(function (e) { e.target.classList.toggle('live', e.isIntersecting); }); }, { rootMargin: '40px 0px' });
        opts.forEach(function (o) { io.observe(o); });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', gatePreviews); else gatePreviews();

    /* ---------- Motion: content glides in, numbers count up, buttons answer the press, pages leave softly.
       Phones get shorter, lighter versions; nothing runs with "reduce motion". ---------- */
    (function () {
        var mq = function (q) { return window.matchMedia && window.matchMedia(q).matches; };
        var reduce = mq('(prefers-reduced-motion: reduce)') || document.documentElement.classList.contains('perf-off'), lite = mq('(hover: none), (max-width: 900px)') || document.documentElement.classList.contains('perf-calm');
        var root = document.documentElement;
        var faD = function (t) { return String(t).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; }); };
        var enD = function (t) { return String(t).replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); }).replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); }); };
        if (!reduce) {
            root.classList.add('adm-motion');
            var max = lite ? 8 : 16, n = 0;
            $$('.adm-content .card, .adm-content .tabs, .adm-content > .alert, .adm-content .tbl-wrap, .adm-content .page-head').forEach(function (el) {
                if (n >= max || el.parentElement.closest('.card') || el.closest('[hidden]')) return;
                var r = el.getBoundingClientRect(); if (r.top > window.innerHeight + 40) return;   // below the fold: no waiting
                el.style.setProperty('--ai', n++); el.classList.add('ai');
            });
            $$('.adm-nav a').forEach(function (a, i) { a.style.setProperty('--ni', Math.min(i, 18)); });
            $$('.adm-content .tbl tbody tr').forEach(function (tr, i) { if (i < 14) tr.style.setProperty('--ri', i); });
        }
        // numbers on the dashboard count up
        $$('.stat b').forEach(function (b) {
            var raw = (b.getAttribute('data-count') || enD(b.textContent)).replace(/[,\s٬]/g, '');
            if (!/^\d{1,12}$/.test(raw)) return;
            var target = +raw; if (!target || reduce) return;
            var t0 = 0, dur = lite ? 700 : 1100;
            var paint = function (v) { b.textContent = faD(Math.round(v).toLocaleString('en-US')); };
            paint(0);
            (function step(t) { if (!t0) t0 = t; var k = Math.min(1, (t - t0) / dur); paint(target * (1 - Math.pow(1 - k, 3))); if (k < 1) requestAnimationFrame(step); })(performance.now());
        });
        // top bar gets depth once the page scrolls
        var top = $('.adm-top');
        if (top) { var onS = function () { top.classList.toggle('scrolled', window.scrollY > 8); }; window.addEventListener('scroll', onS, { passive: true }); onS(); }
        // press ripple from the touch / click point
        document.addEventListener('pointerdown', function (e) {
            var b = e.target.closest && e.target.closest('.abtn, .tabs button, .tabs a'); if (!b || reduce) return;
            var r = b.getBoundingClientRect(), sp = document.createElement('i'); sp.className = 'a-ripple';
            sp.style.left = (e.clientX - r.left) + 'px'; sp.style.top = (e.clientY - r.top) + 'px'; b.appendChild(sp);
            setTimeout(function () { if (sp.parentNode) sp.parentNode.removeChild(sp); }, 650);
        }, { passive: true });
        // leaving through the side menu: the content fades out first (feels instant, never abrupt)
        document.addEventListener('click', function (e) {
            var a = e.target.closest && e.target.closest('.adm-nav a, .adm-brand'); if (!a || reduce) return;
            if (e.ctrlKey || e.metaKey || e.shiftKey || e.button || a.target || a.href === location.href) return;
            e.preventDefault(); root.classList.add('adm-leave');
            setTimeout(function () { location.href = a.href; }, 170);
        });
        window.addEventListener('pageshow', function (e) { if (e.persisted) root.classList.remove('adm-leave'); });
    })();

    window.NXA = A;
})();
