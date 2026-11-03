/* NEXUS | HUB — Lightweight rich text editor (no dependencies) */
(function () {
    'use strict';
    var A = window.NXA;
    var $ = A.$, $$ = A.$$;

    var I = {
        bold: '<b>B</b>', italic: '<i>I</i>', underline: '<u>U</u>', strike: '<s>S</s>',
        ul: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/></svg>',
        ol: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 6h10M10 12h10M10 18h10M4 6h1v4M4 10h2M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/></svg>',
        right: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 6H3M21 12H9M21 18H7"/></svg>',
        center: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 6H3M17 12H7M19 18H5"/></svg>',
        left: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 6H3M15 12H3M17 18H3"/></svg>',
        link: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>',
        unlink: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18.8 13.2 20 12a5 5 0 0 0-7-7l-1.2 1.2M5.2 10.8 4 12a5 5 0 0 0 7 7l1.2-1.2M3 3l18 18"/></svg>',
        image: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-9 9"/></svg>',
        video: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="4"/><path d="m10 9 5 3-5 3Z"/></svg>',
        tg: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 4.5 2.8 11.7c-.9.4-.9 1.5.1 1.8l4.7 1.5 1.8 5.6c.3.8 1.2 1 1.8.4l2.6-2.4 4.9 3.6c.7.5 1.6.1 1.8-.7l3.2-15.3c.2-1-.8-1.8-1.7-1.4Z"/></svg>',
        quote: '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M10 7H6a2 2 0 0 0-2 2v4h4v4l2-4V7Zm10 0h-4a2 2 0 0 0-2 2v4h4v4l2-4V7Z"/></svg>',
        hr: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18"/></svg>',
        table: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M3 15h18M9 4v16M15 4v16"/></svg>',
        color: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 18 6-14 6 14M8.5 13h7"/><path d="M4 21h16" stroke="var(--primary)" stroke-width="3"/></svg>',
        mark: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 11-6 6v3h9l3-3"/><path d="m22 12-4.6 4.6a2 2 0 0 1-2.8 0l-5.2-5.2a2 2 0 0 1 0-2.8L14 4"/></svg>',
        clear: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7V4h16v3M9 20h6M12 4v16M3 3l18 18"/></svg>',
        undo: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/></svg>',
        redo: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 14 5-5-5-5"/><path d="M20 9H9.5a5.5 5.5 0 0 0 0 11H13"/></svg>',
        code: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m16 18 6-6-6-6M8 6l-6 6 6 6"/></svg>'
    };

    function Editor(ta) {
        var self = this;
        this.ta = ta;
        var wrap = document.createElement('div'); wrap.className = 'editor';
        var tb = document.createElement('div'); tb.className = 'ed-toolbar';
        var area = document.createElement('div'); area.className = 'ed-area prose'; area.contentEditable = 'true'; area.dir = 'rtl';
        area.dataset.placeholder = ta.getAttribute('placeholder') || 'متن خبر را اینجا بنویسید...';
        area.innerHTML = ta.value;
        var src = document.createElement('textarea'); src.className = 'ed-source'; src.hidden = true; src.spellcheck = false;
        var status = document.createElement('div'); status.className = 'ed-status';
        this.area = area; this.src = src;

        var btns = [
            ['select'],
            ['sep'], ['bold', 'ضخیم (Ctrl+B)'], ['italic', 'مورب'], ['underline', 'زیرخط'], ['strike', 'خط خورده'],
            ['sep'], ['ul', 'لیست نقطه‌ای'], ['ol', 'لیست شماره‌دار'], ['quote', 'نقل قول'],
            ['sep'], ['right', 'راست‌چین'], ['center', 'وسط‌چین'], ['left', 'چپ‌چین'],
            ['sep'], ['link', 'لینک'], ['unlink', 'حذف لینک'], ['image', 'تصویر'], ['video', 'ویدیو (آپارات/یوتیوب)'], ['tg', 'پست تلگرام'],
            ['sep'], ['color', 'رنگ متن'], ['mark', 'هایلایت'], ['hr', 'خط جداکننده'], ['table', 'جدول'],
            ['sep'], ['clear', 'پاک کردن فرمت'], ['undo', 'واگرد'], ['redo', 'ازنو'], ['code', 'ویرایش HTML']
        ];
        btns.forEach(function (b) {
            if (b[0] === 'sep') { var s = document.createElement('span'); s.className = 'sep'; tb.appendChild(s); return; }
            if (b[0] === 'select') {
                var sel = document.createElement('select');
                [['p', 'پاراگراف'], ['h2', 'تیتر ۲'], ['h3', 'تیتر ۳'], ['h4', 'تیتر ۴'], ['pre', 'کد']].forEach(function (o) {
                    var op = document.createElement('option'); op.value = o[0]; op.textContent = o[1]; sel.appendChild(op);
                });
                sel.addEventListener('change', function () { self.exec('formatBlock', '<' + sel.value + '>'); area.focus(); });
                tb.appendChild(sel); self.blockSel = sel; return;
            }
            var el;
            if (b[0] === 'color' || b[0] === 'mark') {
                el = document.createElement('label');
                el.innerHTML = I[b[0]] + '<input type="color" value="' + (b[0] === 'color' ? '#ff1f3d' : '#ffe066') + '">';
                var ci = el.querySelector('input');
                ci.addEventListener('input', function () { self.restore(); self.exec(b[0] === 'color' ? 'foreColor' : 'hiliteColor', ci.value); });
                el.addEventListener('mousedown', function () { self.save(); });
            } else {
                el = document.createElement('button'); el.type = 'button'; el.innerHTML = I[b[0]];
                el.addEventListener('mousedown', function (e) { e.preventDefault(); });
                el.addEventListener('click', function () { self.action(b[0]); });
            }
            el.title = b[1]; el.dataset.cmd = b[0];
            tb.appendChild(el);
        });

        ta.hidden = true;
        ta.parentNode.insertBefore(wrap, ta);
        wrap.appendChild(tb); wrap.appendChild(area); wrap.appendChild(src); wrap.appendChild(status);
        this.tb = tb; this.status = status;

        try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) {}
        area.addEventListener('input', function () { self.sync(); });
        area.addEventListener('keyup', function () { self.state(); });
        area.addEventListener('mouseup', function () { self.state(); });
        area.addEventListener('paste', function (e) { self.paste(e); });
        area.addEventListener('drop', function (e) {
            var files = e.dataTransfer && e.dataTransfer.files;
            if (files && files.length && files[0].type.indexOf('image') === 0) { e.preventDefault(); self.uploadInsert(files[0]); }
        });
        area.addEventListener('click', function (e) {
            $$('img.sel', area).forEach(function (i) { i.classList.remove('sel'); });
            if (e.target.tagName === 'IMG') e.target.classList.add('sel');
        });
        area.addEventListener('keydown', function (e) {
            if ((e.key === 'Delete' || e.key === 'Backspace') && $('img.sel', area)) { e.preventDefault(); $('img.sel', area).remove(); self.sync(); }
        });
        src.addEventListener('input', function () { ta.value = src.value; self.count(); });
        var form = ta.form;
        if (form) form.addEventListener('submit', function () { self.sync(true); try { localStorage.removeItem(self.key); } catch (e) {} });

        // Autosave
        this.key = 'nx_ed_' + (ta.dataset.key || location.pathname + location.search);
        try {
            var saved = localStorage.getItem(this.key);
            if (saved && saved !== ta.value && saved.replace(/<[^>]+>/g, '').trim().length > 10) {
                var bar = document.createElement('div');
                bar.className = 'alert alert-info';
                bar.innerHTML = 'یک نسخه ذخیره‌نشده از این متن پیدا شد. <button type="button" class="abtn abtn-xs abtn-primary">بازیابی</button> <button type="button" class="abtn abtn-xs abtn-ghost">نادیده گرفتن</button>';
                wrap.parentNode.insertBefore(bar, wrap);
                bar.querySelectorAll('button')[0].addEventListener('click', function () { area.innerHTML = saved; self.sync(); bar.remove(); });
                bar.querySelectorAll('button')[1].addEventListener('click', function () { localStorage.removeItem(self.key); bar.remove(); });
            }
        } catch (e) {}
        setInterval(function () { try { if (ta.value) localStorage.setItem(self.key, ta.value); } catch (e) {} }, 8000);
        this.count();
    }

    Editor.prototype.exec = function (cmd, val) { document.execCommand(cmd, false, val || null); this.sync(); this.state(); };
    Editor.prototype.save = function () { var s = window.getSelection(); if (s.rangeCount) this.range = s.getRangeAt(0).cloneRange(); };
    Editor.prototype.restore = function () { this.area.focus(); if (this.range) { var s = window.getSelection(); s.removeAllRanges(); s.addRange(this.range); } };
    Editor.prototype.insertHTML = function (html) { this.restore(); this.exec('insertHTML', html); };
    Editor.prototype.sync = function (force) {
        if (!this.src.hidden && !force) return;
        if (!this.src.hidden) { this.area.innerHTML = this.src.value; }
        var h = this.area.innerHTML.replace(/<img([^>]*?) class="sel"/g, '<img$1').replace(/ class=""/g, '');
        if (h === '<br>' || h === '<p><br></p>') h = '';
        this.ta.value = h;
        this.count();
    };
    Editor.prototype.count = function () {
        var txt = (this.area.innerText || '').trim(), words = txt ? txt.split(/\s+/).length : 0;
        this.status.innerHTML = '<span>' + A.fa(words) + ' کلمه · ' + A.fa(txt.length) + ' کاراکتر</span><span>زمان مطالعه: ' + A.fa(Math.max(1, Math.ceil(words / 200))) + ' دقیقه</span>';
    };
    Editor.prototype.state = function () {
        var map = { bold: 'bold', italic: 'italic', underline: 'underline', strike: 'strikeThrough', ul: 'insertUnorderedList', ol: 'insertOrderedList' };
        $$('button[data-cmd]', this.tb).forEach(function (b) {
            var c = map[b.dataset.cmd];
            if (c) { try { b.classList.toggle('on', document.queryCommandState(c)); } catch (e) {} }
        });
        try {
            var blk = (document.queryCommandValue('formatBlock') || 'p').toLowerCase().replace(/[<>]/g, '');
            if (this.blockSel && ['p', 'h2', 'h3', 'h4', 'pre'].indexOf(blk) >= 0 && this.blockSel.value !== blk) {
                this.blockSel.value = blk;
                if (this.blockSel._nxRefresh) this.blockSel._nxRefresh();
            }
        } catch (e) {}
    };
    Editor.prototype.uploadInsert = function (file) {
        var self = this;
        A.toast('در حال آپلود تصویر...', 'info');
        A.upload(file).then(function (j) {
            if (!j.ok) return A.toast(j.error || 'خطا در آپلود', 'error');
            self.insertHTML('<p><img src="' + j.url + '" alt=""></p>');
        });
    };
    Editor.prototype.paste = function (e) {
        var cd = e.clipboardData;
        if (!cd) return;
        if (cd.files && cd.files.length && cd.files[0].type.indexOf('image') === 0) { e.preventDefault(); this.uploadInsert(cd.files[0]); return; }
        var html = cd.getData('text/html');
        e.preventDefault();
        if (html) {
            var tmp = document.createElement('div'); tmp.innerHTML = html;
            $$('script,style,meta,link,title,xml', tmp).forEach(function (n) { n.remove(); });
            $$('*', tmp).forEach(function (n) {
                Array.prototype.slice.call(n.attributes).forEach(function (a) { if (['href', 'src', 'alt'].indexOf(a.name) < 0) n.removeAttribute(a.name); });
                if (/^(SPAN|FONT|O:P)$/.test(n.tagName)) { n.replaceWith.apply(n, Array.prototype.slice.call(n.childNodes)); }
            });
            this.exec('insertHTML', tmp.innerHTML);
        } else {
            var text = cd.getData('text/plain') || '';
            var paras = text.split(/\n{2,}|\r\n\r\n/).map(function (p) { return '<p>' + p.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/\n/g, '<br>') + '</p>'; });
            this.exec('insertHTML', paras.length > 1 ? paras.join('') : text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/\n/g, '<br>'));
        }
    };
    Editor.prototype.action = function (a) {
        var self = this;
        switch (a) {
            case 'bold': case 'italic': case 'underline': this.exec(a); break;
            case 'strike': this.exec('strikeThrough'); break;
            case 'ul': this.exec('insertUnorderedList'); break;
            case 'ol': this.exec('insertOrderedList'); break;
            case 'quote': this.exec('formatBlock', '<blockquote>'); break;
            case 'right': this.exec('justifyRight'); break;
            case 'center': this.exec('justifyCenter'); break;
            case 'left': this.exec('justifyLeft'); break;
            case 'unlink': this.exec('unlink'); break;
            case 'hr': this.exec('insertHorizontalRule'); break;
            case 'clear': this.exec('removeFormat'); this.exec('formatBlock', '<p>'); break;
            case 'undo': this.exec('undo'); break;
            case 'redo': this.exec('redo'); break;
            case 'link':
                this.save();
                var url = prompt('آدرس لینک را وارد کنید:', 'https://');
                if (url && url !== 'https://') {
                    this.restore();
                    if (window.getSelection().toString()) this.exec('createLink', url);
                    else this.insertHTML('<a href="' + url.replace(/"/g, '&quot;') + '">' + url.replace(/</g, '&lt;') + '</a>');
                }
                break;
            case 'image':
                this.save();
                A.pickMedia(function (m) {
                    var alt = prompt('متن جایگزین تصویر (اختیاری):', '') || '';
                    self.insertHTML('<p><img src="' + m.url + '" alt="' + alt.replace(/"/g, '&quot;') + '"></p>');
                });
                break;
            case 'video':
                this.save();
                var v = prompt('لینک ویدیو آپارات یا یوتیوب:', '');
                if (!v) break;
                var m1 = v.match(/aparat\.com\/v\/([A-Za-z0-9]+)/), m2 = v.match(/(?:youtu\.be\/|v=|embed\/|shorts\/)([A-Za-z0-9_\-]{6,})/);
                if (m1) this.insertHTML('<p>[aparat id="' + m1[1] + '"]</p>');
                else if (m2) this.insertHTML('<p>[youtube id="' + m2[1] + '"]</p>');
                else A.toast('لینک ویدیو معتبر نیست', 'error');
                break;
            case 'tg':
                this.save();
                var t = prompt('لینک پست تلگرام (مثال: https://t.me/NexusHubIR/120):', 'https://t.me/');
                var mt = t && t.match(/t\.me\/([A-Za-z0-9_]{3,64})\/(\d+)/);
                if (mt) this.insertHTML('<p>[telegram post="' + mt[1] + '/' + mt[2] + '"]</p>');
                else if (t && t !== 'https://t.me/') A.toast('لینک پست تلگرام معتبر نیست', 'error');
                break;
            case 'table':
                this.save();
                var r = parseInt(prompt('تعداد سطرها:', '3'), 10), c = parseInt(prompt('تعداد ستون‌ها:', '2'), 10);
                if (r > 0 && c > 0 && r < 30 && c < 10) {
                    var h = '<table><thead><tr>' + new Array(c + 1).join('<th>عنوان</th>') + '</tr></thead><tbody>';
                    for (var i = 0; i < r - 1; i++) h += '<tr>' + new Array(c + 1).join('<td>-</td>') + '</tr>';
                    this.insertHTML(h + '</tbody></table><p><br></p>');
                }
                break;
            case 'code':
                if (this.src.hidden) {
                    this.sync(true);
                    this.src.value = this.ta.value.replace(/<\/(p|h\d|blockquote|ul|ol|li|table|tr|figure)>/g, '</$1>\n');
                    this.src.hidden = false; this.area.hidden = true;
                } else {
                    this.area.innerHTML = this.src.value; this.src.hidden = true; this.area.hidden = false; this.sync(true);
                }
                $('button[data-cmd=code]', this.tb).classList.toggle('on', !this.src.hidden);
                break;
        }
        this.area.focus && !this.area.hidden && this.state();
    };

    $$('textarea[data-editor]').forEach(function (ta) { ta._editor = new Editor(ta); });
})();
