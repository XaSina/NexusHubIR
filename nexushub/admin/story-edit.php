<?php
/**
 * ساخت / ویرایش استوری (چند اسلاید عکس یا ویدیو)
 */
require __DIR__ . '/inc/boot.php';
require_cap('*');

$id = (int) ($_GET['id'] ?? 0);
$st = $id ? row('SELECT * FROM stories WHERE id = ?', [$id]) : null;
if ($id && !$st) { flash('استوری یافت نشد.', 'error'); redirect('admin/stories.php'); }
$st = $st ?: ['id' => 0, 'title' => '', 'cover' => '', 'is_active' => 1, 'sort_order' => 0, 'expires_at' => null, 'views' => 0];
$items = $st['id'] ? rows('SELECT * FROM story_items WHERE story_id = ? ORDER BY sort_order, id', [$st['id']]) : [];
$expiryOptions = ['24' => '۲۴ ساعت (مثل استوری اینستاگرام)', '48' => '۴۸ ساعت', '168' => '۷ روز', '720' => '۳۰ روز', 'forever' => 'همیشگی (هایلایت)', 'custom' => 'تاریخ دلخواه (شمسی)'];

if (is_post()) {
    csrf_check();
    $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 80);
    $exp = (string) ($_POST['expiry'] ?? 'keep');
    $expiresAt = $st['expires_at'];
    if ($exp === 'forever') $expiresAt = null;
    elseif ($exp === 'custom') $expiresAt = jalali_to_datetime((string) ($_POST['expires_custom'] ?? '')) ?: $st['expires_at'];
    elseif (ctype_digit($exp)) $expiresAt = date('Y-m-d H:i:s', time() + (int) $exp * 3600);

    // slides arrive as parallel arrays in display order
    $slides = [];
    foreach ((array) ($_POST['item_media'] ?? []) as $i => $media) {
        $media = trim((string) $media);
        if ($media === '' || !preg_match('~^(uploads|assets)/[\w./-]+$~', $media) || str_contains($media, '..')) continue;
        $type = (($_POST['item_type'][$i] ?? '') === 'video') ? 'video' : 'image';
        $slides[] = [
            'media' => $media, 'media_type' => $type,
            'caption' => mb_substr(trim((string) ($_POST['item_caption'][$i] ?? '')), 0, 300) ?: null,
            'link' => safe_link((string) ($_POST['item_link'][$i] ?? '')) ?: null,
            'link_text' => mb_substr(trim((string) ($_POST['item_link_text'][$i] ?? '')), 0, 60) ?: null,
            'duration' => max(2, min(30, (int) ($_POST['item_duration'][$i] ?? 6))),
        ];
        if (count($slides) >= 20) break;
    }
    if ($title === '') {
        flash('عنوان استوری را وارد کنید.', 'error');
    } elseif (!$slides) {
        flash('حداقل یک اسلاید (عکس یا ویدیو) اضافه کنید.', 'error');
    } else {
        $data = [
            'title' => $title, 'cover' => trim((string) ($_POST['cover'] ?? '')) ?: null, 'is_active' => !empty($_POST['is_active']) ? 1 : 0,
            'sort_order' => (int) ($_POST['sort_order'] ?? 0), 'expires_at' => $expiresAt, 'updated_at' => now(),
        ];
        if ($st['id']) { update('stories', $data, (int) $st['id']); $sid = (int) $st['id']; }
        else { $data['created_at'] = now(); $sid = insert('stories', $data); }
        q('DELETE FROM story_items WHERE story_id = ?', [$sid]);
        foreach ($slides as $k => $sl) insert('story_items', $sl + ['story_id' => $sid, 'sort_order' => $k]);
        flash('استوری ذخیره شد ✅' . ($data['is_active'] ? ' و در صفحه اصلی نمایش داده می‌شود.' : ''));
        redirect('admin/story-edit.php?id=' . $sid);
    }
    redirect('admin/story-edit.php' . ($st['id'] ? '?id=' . $st['id'] : ''));
}

$limitMb = server_upload_limit() ? round(server_upload_limit() / 1048576) : 0;
adm_head($st['id'] ? 'ویرایش استوری' : 'استوری جدید', 'stories');
?>
<form method="post" id="storyForm">
<?= csrf_field() ?>
<div class="split">
    <div>
        <div class="card">
            <div class="card-head"><h3><?= adm_icon('image', 20) ?> اسلایدها <small class="muted" id="slideCount"></small></h3>
                <div class="row-flex">
                    <label class="abtn abtn-primary abtn-sm"><?= adm_icon('upload', 16) ?> آپلود عکس / ویدیو<input type="file" id="slideUpload" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm" multiple hidden></label>
                    <button type="button" class="abtn abtn-ghost abtn-sm" id="slidePick"><?= adm_icon('image', 16) ?> از کتابخانه</button>
                </div>
            </div>
            <p class="af-hint">بهترین اندازه: عمودی ۱۰۸۰×۱۹۲۰ (۹:۱۶). ویدیو فقط MP4 یا WebM، حداکثر ۱۰۰ مگابایت (به‌صورت تکه‌تکه آپلود می‌شود، پس محدودیت آپلود هاست مهم نیست). اسلایدها را بکشید تا ترتیبشان عوض شود.</p>
            <div class="progress" id="slideProg" hidden><i></i></div>
            <div class="sortable slides" id="slides">
                <?php foreach ($items as $it): ?>
                    <div class="sort-item slide-row" draggable="true">
                        <span class="sort-handle">⋮⋮</span>
                        <span class="slide-thumb"><?php if ($it['media_type'] === 'video'): ?><video src="<?= e(media_url($it['media'])) ?>#t=0.5" muted playsinline preload="metadata"></video><em>🎬</em><?php else: ?><img src="<?= e(media_url($it['media'])) ?>" alt=""><?php endif; ?></span>
                        <input type="hidden" name="item_media[]" value="<?= e($it['media']) ?>"><input type="hidden" name="item_type[]" value="<?= e($it['media_type']) ?>">
                        <div class="slide-fields">
                            <input name="item_caption[]" value="<?= e((string) $it['caption']) ?>" placeholder="متن روی استوری (اختیاری)" maxlength="300">
                            <div class="slide-2">
                                <input name="item_link[]" value="<?= e((string) $it['link']) ?>" placeholder="لینک (اختیاری) https://" dir="ltr">
                                <input name="item_link_text[]" value="<?= e((string) $it['link_text']) ?>" placeholder="متن دکمه: مشاهده" maxlength="60">
                                <label class="slide-dur" <?= $it['media_type'] === 'video' ? 'title="ویدیو تا انتها پخش می‌شود"' : '' ?>><input type="number" name="item_duration[]" value="<?= (int) $it['duration'] ?>" min="2" max="30" <?= $it['media_type'] === 'video' ? 'disabled' : '' ?>> ثانیه</label>
                            </div>
                        </div>
                        <span class="mv"><button type="button" class="adm-ib" data-mv="up">▲</button><button type="button" class="adm-ib" data-mv="down">▼</button></span>
                        <button type="button" class="adm-ib" data-del title="حذف اسلاید"><?= adm_icon('trash', 15) ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="empty-state" id="slidesEmpty" <?= $items ? 'hidden' : '' ?>><span>🖼️</span>هنوز اسلایدی اضافه نشده. عکس یا ویدیو آپلود کنید.</div>
        </div>
    </div>

    <div class="split-side">
        <div class="card">
            <div class="card-head"><h3><?= adm_icon('story', 20) ?> تنظیمات استوری</h3><?php if ($st['id']): ?><span class="badge"><?= icon('eye', 12) ?> <?= fa_num((string) $st['views']) ?> بازدید</span><?php endif; ?></div>
            <?= f_text('title', 'عنوان (زیر دایره نمایش داده می‌شود)', (string) $st['title'], ['required' => true, 'maxlength' => 80, 'placeholder' => 'مثلاً: قرعه‌کشی، GTA VI، معرفی گوشی']) ?>
            <label class="af"><span class="af-label">مدت نمایش</span>
                <select name="expiry" id="expirySel">
                    <?php if ($st['id']): ?><option value="keep" selected>بدون تغییر — <?= $st['expires_at'] ? 'تا ' . jdate('j F H:i', $st['expires_at']) : 'همیشگی' ?></option><?php endif; ?>
                    <?php foreach ($expiryOptions as $k => $l): ?><option value="<?= e($k) ?>" <?= !$st['id'] && $k === '24' ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
                </select>
                <small class="af-hint">بعد از این زمان، استوری خودکار از سایت حذف می‌شود (در پنل می‌ماند و قابل تمدید است).</small>
            </label>
            <div id="expiryCustom" hidden><?= f_jdate('expires_custom', 'پایان نمایش (شمسی)', $st['expires_at']) ?></div>
            <div class="logo-pick"><?= f_image('cover', 'تصویر دایره (اختیاری — پیش‌فرض: اولین اسلاید)', $st['cover']) ?></div>
            <?= f_text('sort_order', 'ترتیب (عدد کمتر = جلوتر)', (string) $st['sort_order'], ['type' => 'number']) ?>
            <?= f_switch('is_active', 'استوری فعال باشد', (bool) $st['is_active']) ?>
            <div class="form-actions mt">
                <button class="abtn abtn-primary" style="flex:1" type="submit"><?= icon('check', 18) ?> ذخیره استوری</button>
                <a class="abtn abtn-ghost" href="stories.php">بازگشت</a>
            </div>
        </div>
        <div class="card">
            <div class="card-head"><h3><?= icon('eye', 20) ?> پیش‌نمایش</h3><small class="muted">روی اسلاید کلیک کنید</small></div>
            <div class="phone-prev" id="phonePrev">
                <div class="pp-bars" id="ppBars"></div>
                <div class="pp-head"><span class="pp-av"><img src="<?= e(media_url((string) setting('logo'))) ?>" alt=""></span><b id="ppTitle"><?= e($st['title'] ?: 'عنوان استوری') ?></b><small>همین الان</small></div>
                <div class="pp-media" id="ppMedia"><span class="muted">اسلایدی انتخاب نشده</span></div>
                <div class="pp-cap" id="ppCap"></div>
                <span class="pp-link" id="ppLink" hidden></span>
            </div>
        </div>
    </div>
</div>
</form>
<template id="slideTpl">
    <div class="sort-item slide-row" draggable="true">
        <span class="sort-handle">⋮⋮</span>
        <span class="slide-thumb"></span>
        <input type="hidden" name="item_media[]"><input type="hidden" name="item_type[]">
        <div class="slide-fields">
            <input name="item_caption[]" placeholder="متن روی استوری (اختیاری)" maxlength="300">
            <div class="slide-2">
                <input name="item_link[]" placeholder="لینک (اختیاری) https://" dir="ltr">
                <input name="item_link_text[]" placeholder="متن دکمه: مشاهده" maxlength="60">
                <label class="slide-dur"><input type="number" name="item_duration[]" value="6" min="2" max="30"> ثانیه</label>
            </div>
        </div>
        <span class="mv"><button type="button" class="adm-ib" data-mv="up">▲</button><button type="button" class="adm-ib" data-mv="down">▼</button></span>
        <button type="button" class="adm-ib" data-del title="حذف اسلاید">✕</button>
    </div>
</template>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var list = document.getElementById('slides'), tpl = document.getElementById('slideTpl'), empty = document.getElementById('slidesEmpty'), count = document.getElementById('slideCount');
    var prog = document.getElementById('slideProg'), bar = prog.querySelector('i'), sel = null;
    function refresh() {
        var rows = list.querySelectorAll('.slide-row');
        empty.hidden = rows.length > 0; count.textContent = rows.length ? '(' + NXA.fa(rows.length) + ' اسلاید)' : '';
        var bars = document.getElementById('ppBars'); bars.innerHTML = '';
        rows.forEach(function (r) { var i = document.createElement('i'); if (r === sel) i.className = 'on'; bars.appendChild(i); });
        if (!sel || !list.contains(sel)) preview(rows[0] || null);
    }
    function preview(row) {
        sel = row;
        list.querySelectorAll('.slide-row').forEach(function (r) { r.classList.toggle('sel', r === row); });
        var m = document.getElementById('ppMedia'), cap = document.getElementById('ppCap'), ln = document.getElementById('ppLink');
        if (!row) { m.innerHTML = '<span class="muted">اسلایدی انتخاب نشده</span>'; cap.textContent = ''; ln.hidden = true; return; }
        var src = row.querySelector('[name="item_media[]"]').dataset.url || row.querySelector('img,video').getAttribute('src');
        var isVid = row.querySelector('[name="item_type[]"]').value === 'video';
        m.innerHTML = isVid ? '<video autoplay muted loop playsinline></video>' : '<img alt="">';
        m.firstChild.src = src.replace('#t=0.5', '');
        cap.textContent = row.querySelector('[name="item_caption[]"]').value;
        var lt = row.querySelector('[name="item_link[]"]').value.trim();
        ln.hidden = !lt; ln.textContent = (row.querySelector('[name="item_link_text[]"]').value.trim() || 'مشاهده') + ' ↑';
        var bars = document.getElementById('ppBars').children, rows = [].slice.call(list.querySelectorAll('.slide-row'));
        [].forEach.call(bars, function (b, i) { b.className = i < rows.indexOf(row) ? 'done' : (i === rows.indexOf(row) ? 'on' : ''); });
    }
    function addSlide(media) {
        var row = tpl.content.firstElementChild.cloneNode(true), th = row.querySelector('.slide-thumb');
        row.querySelector('[name="item_media[]"]').value = media.path;
        row.querySelector('[name="item_media[]"]').dataset.url = media.url;
        row.querySelector('[name="item_type[]"]').value = media.type || 'image';
        if (media.type === 'video') {
            th.innerHTML = '<video muted playsinline preload="metadata"></video><em>🎬</em>'; th.firstChild.src = media.url + '#t=0.5';
            var d = row.querySelector('[name="item_duration[]"]'); d.disabled = true; d.closest('label').title = 'ویدیو تا انتها پخش می‌شود';
        } else { th.innerHTML = '<img alt="">'; th.firstChild.src = media.url; }
        list.appendChild(row); refresh(); preview(row);
    }
    NXA.sortable(list, refresh);
    list.addEventListener('click', function (e) {
        var del = e.target.closest('[data-del]');
        if (del) { del.closest('.slide-row').remove(); refresh(); return; }
        var row = e.target.closest('.slide-row');
        if (row && !e.target.closest('input,button')) preview(row);
    });
    list.addEventListener('input', function (e) { var row = e.target.closest('.slide-row'); if (row && row === sel) preview(row); });
    list.addEventListener('focusin', function (e) { var row = e.target.closest('.slide-row'); if (row && row !== sel) preview(row); });
    document.querySelector('[name=title]').addEventListener('input', function () { document.getElementById('ppTitle').textContent = this.value || 'عنوان استوری'; });
    document.getElementById('slidePick').addEventListener('click', function () { NXA.pickMedia(function (m) { addSlide({ path: m.path, url: m.url, type: 'image' }); }); });
    document.getElementById('slideUpload').addEventListener('change', function () {
        var files = [].slice.call(this.files), input = this, total = files.length, done = 0;
        if (!total) return; prog.hidden = false;
        (function next() {
            var f = files.shift();
            if (!f) { input.value = ''; setTimeout(function () { prog.hidden = true; bar.style.width = 0; }, 500); return; }
            if (/^video\//.test(f.type) || /\.(mp4|webm|m4v)$/i.test(f.name)) {   // videos: chunked upload (not limited by the host's upload size)
                NXA.uploadVideo(f, 'story').then(function (j) { done++; if (j.ok) addSlide(j); next(); });
                return;
            }
            var fd = new FormData(); fd.append('file', f);
            NXA.post('upload_story', fd, function (p) { bar.style.width = ((done + p) / total * 100) + '%'; }).then(function (j) {
                done++;
                if (j.ok) addSlide(j); else NXA.toast(f.name + ': ' + (j.error || 'خطا'), 'error');
                next();
            }, function () { done++; NXA.toast(f.name + ': آپلود ناموفق (شاید حجم فایل زیاد است)', 'error'); next(); });
        })();
    });
    var es = document.getElementById('expirySel'), ec = document.getElementById('expiryCustom');
    function exp() { ec.hidden = es.value !== 'custom'; } es.addEventListener('change', exp); exp();
    document.getElementById('storyForm').addEventListener('submit', function (e) {
        if (!list.querySelector('.slide-row')) { e.preventDefault(); NXA.toast('حداقل یک اسلاید اضافه کنید.', 'error'); }
        list.querySelectorAll('[name="item_duration[]"]:disabled').forEach(function (d) { d.disabled = false; });   // keep arrays aligned
    });
    refresh();
});
</script>
<?php adm_foot();
