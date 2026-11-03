<?php
require __DIR__ . '/inc/boot.php';
require_cap('media');

if (is_post()) {
    csrf_check();
    if (!empty($_POST['delete_id'])) { delete_media((int) $_POST['delete_id']); flash('فایل حذف شد.'); }
    redirect('admin/media.php');
}
$total = (int) val('SELECT COUNT(*) FROM media');
$pg = paginate($total, 48, (int) ($_GET['page'] ?? 1));
$list = rows("SELECT m.*, u.display_name FROM media m LEFT JOIN users u ON u.id = m.uploaded_by ORDER BY m.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}");
$size = (int) val('SELECT COALESCE(SUM(size),0) FROM media');
adm_head('کتابخانه رسانه', 'media');
?>
<div class="card">
    <label class="dropzone" id="dz">
        <?= adm_icon('upload', 40) ?>
        <b>تصاویر را اینجا بکشید و رها کنید یا کلیک کنید</b><br><small>JPG، PNG، GIF، WEBP — حداکثر ۱۰ مگابایت · تصاویر بزرگ خودکار بهینه می‌شوند</small>
        <input type="file" accept="image/*" multiple hidden id="dzInput">
        <div class="progress" hidden id="dzProg"><i></i></div>
    </label>
</div>
<div class="card">
    <div class="card-head"><h3><?= adm_icon('image', 20) ?> فایل‌ها <small class="muted">(<?= fa_num((string) $total) ?> فایل · <?= fa_num(number_format($size / 1048576, 1)) ?> مگابایت)</small></h3></div>
    <?php if (!$list): ?><div class="empty-state"><span>🖼️</span>هنوز فایلی آپلود نشده است.</div><?php endif; ?>
    <div class="media-grid">
        <?php foreach ($list as $m): $u = site_url($m['path']); ?>
            <div class="m-item" style="cursor:default">
                <?php if (str_starts_with((string) $m['mime'], 'video/')): ?>
                    <video src="<?= e(media_url($m['path'])) ?>#t=0.5" muted playsinline preload="metadata" style="width:100%;height:100%;object-fit:cover"></video><span class="badge b-info" style="position:absolute;top:6px;right:6px">🎬 ویدیو</span>
                <?php else: ?><img src="<?= e(media_url($m['path'])) ?>" alt="" loading="lazy"><?php endif; ?>
                <div class="m-info" style="opacity:1">
                    <span><?= $m['width'] ? fa_num((string) $m['width']) . '×' . fa_num((string) $m['height']) . ' · ' : '' ?><?= fa_num(number_format($m['size'] / 1024)) ?>KB</span>
                    <span class="row-flex" style="gap:4px">
                        <button class="adm-ib" style="width:28px;height:28px" data-copy="<?= e($u) ?>" title="کپی لینک"><?= icon('copy', 14) ?></button>
                        <form method="post" style="display:inline"><?= csrf_field() ?><button class="adm-ib" style="width:28px;height:28px" name="delete_id" value="<?= (int) $m['id'] ?>" data-confirm="این فایل حذف شود؟ (اگر در خبری استفاده شده باشد، تصویر آن خبر از بین می‌رود)" title="حذف"><?= adm_icon('trash', 14) ?></button></form>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?= pagination_html($pg, url('admin/media.php')) ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var dz = document.getElementById('dz'), inp = document.getElementById('dzInput'), prog = document.getElementById('dzProg'), bar = prog.querySelector('i');
    function send(files) {
        files = [].slice.call(files); var total = files.length, done = 0, fails = 0;
        if (!total) return; prog.hidden = false;
        (function next() {
            var f = files.shift();
            if (!f) { NXA.toast(NXA.fa(done) + ' فایل آپلود شد' + (fails ? ' (' + NXA.fa(fails) + ' ناموفق)' : '')); setTimeout(function () { location.reload(); }, 700); return; }
            NXA.upload(f, function (p) { bar.style.width = ((done + p) / total * 100) + '%'; }).then(function (j) {
                if (j.ok) done++; else { fails++; NXA.toast(f.name + ': ' + (j.error || 'خطا'), 'error'); }
                next();
            }, function () { fails++; next(); });
        })();
    }
    inp.addEventListener('change', function () { send(inp.files); });
    ['dragenter', 'dragover'].forEach(function (ev) { dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.add('over'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { dz.addEventListener(ev, function (e) { e.preventDefault(); dz.classList.remove('over'); }); });
    dz.addEventListener('drop', function (e) { send(e.dataTransfer.files); });
});
</script>
<?php adm_foot();
