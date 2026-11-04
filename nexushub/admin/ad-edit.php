<?php
require __DIR__ . '/inc/boot.php';
require_cap('*');

$id = (int) ($_GET['id'] ?? 0);
$a = $id ? row('SELECT * FROM ads WHERE id = ?', [$id]) : null;
if ($id && !$a) { flash('تبلیغ یافت نشد.', 'error'); redirect('admin/ads.php'); }
$a = $a ?: [
    'id' => 0, 'title' => '', 'position' => (string) ($_GET['pos'] ?? 'home_top'), 'format' => 'card', 'theme' => 'neon', 'accent' => theme_colors()['primary'],
    'image' => '', 'link' => '', 'html' => '', 'heading' => '', 'body' => '', 'button_text' => 'مشاهده', 'emoji' => '🔥', 'badge' => '',
    'device' => 'all', 'countdown' => 0, 'new_tab' => 1, 'popup_delay' => 4, 'popup_freq' => 12, 'priority' => 0, 'is_active' => 1, 'start_at' => null, 'end_at' => null,
];
$positions = ad_positions();
$themes = ad_themes();

if (is_post()) {
    csrf_check();
    $start = trim((string) ($_POST['start_at'] ?? ''));
    $end = trim((string) ($_POST['end_at'] ?? ''));
    $data = [
        'title' => mb_substr(trim((string) $_POST['title']), 0, 190) ?: 'تبلیغ بدون نام',
        'position' => isset($positions[$_POST['position'] ?? '']) ? $_POST['position'] : 'home_top',
        'format' => in_array($_POST['format'] ?? '', ['card', 'image', 'html'], true) ? $_POST['format'] : 'card',
        'theme' => isset($themes[$_POST['theme'] ?? '']) ? $_POST['theme'] : 'neon',
        'accent' => valid_hex((string) ($_POST['accent'] ?? ''), '#ff1f3d'),
        'image' => trim((string) ($_POST['image'] ?? '')) ?: null,
        'link' => safe_link((string) ($_POST['link'] ?? '')) ?: null,
        'html' => (string) ($_POST['html'] ?? ''),
        'heading' => mb_substr(trim((string) ($_POST['heading'] ?? '')), 0, 190),
        'body' => mb_substr(trim((string) ($_POST['body'] ?? '')), 0, 1000),
        'button_text' => mb_substr(trim((string) ($_POST['button_text'] ?? '')), 0, 80),
        'emoji' => mb_substr(trim((string) ($_POST['emoji'] ?? '')), 0, 8),
        'badge' => mb_substr(trim((string) ($_POST['badge'] ?? '')), 0, 40),
        'device' => in_array($_POST['device'] ?? '', ['all', 'mobile', 'desktop'], true) ? $_POST['device'] : 'all',
        'countdown' => !empty($_POST['countdown']) ? 1 : 0,
        'new_tab' => !empty($_POST['new_tab']) ? 1 : 0,
        'popup_delay' => max(0, min(120, (int) ($_POST['popup_delay'] ?? 4))),
        'popup_freq' => max(0, min(720, (int) ($_POST['popup_freq'] ?? 12))),
        'priority' => (int) ($_POST['priority'] ?? 0),
        'is_active' => !empty($_POST['is_active']) ? 1 : 0,
        'start_at' => $start !== '' ? jalali_to_datetime($start) : null,
        'end_at' => $end !== '' ? jalali_to_datetime($end) : null,
    ];
    if ($data['countdown'] && !$data['end_at']) flash('برای نمایش شمارش معکوس، تاریخ پایان را تعیین کنید.', 'warn');
    if ($a['id']) { update('ads', $data, (int) $a['id']); flash('تبلیغ ذخیره شد ✅'); $nid = (int) $a['id']; }
    else { $data['created_at'] = now(); $nid = insert('ads', $data); flash('تبلیغ جدید ساخته شد 🎉'); }
    redirect(!empty($_POST['back']) ? 'admin/ads.php' : 'admin/ad-edit.php?id=' . $nid);
}

$sample = ['id' => 0, 'format' => 'card', 'accent' => $a['accent'], 'emoji' => '🔥', 'heading' => 'نمونه تبلیغ', 'body' => '', 'button_text' => '', 'device' => 'all', 'link' => '#'];
adm_head($a['id'] ? 'ویرایش تبلیغ' : 'تبلیغ جدید', 'ads');
?>
<form method="post" id="adForm">
    <?= csrf_field() ?>
    <div class="split">
        <div>
            <div class="card">
                <div class="card-head"><h3><?= icon('eye', 20) ?> پیش‌نمایش زنده</h3><span class="muted small">با هر تغییر بروز می‌شود</span></div>
                <div class="preview-stage" id="adPreview"><?= render_ad($a + ['id' => 0], true) ?></div>
            </div>
            <div class="card">
                <div class="card-head"><h3><?= icon('sparkle', 20) ?> انتخاب تم تبلیغ</h3><span class="muted small"><?= fa_num((string) count($themes)) ?> تم انیمیشنی</span></div>
                <div class="theme-gallery">
                    <?php foreach ($themes as $k => $t): ?>
                        <label class="theme-opt">
                            <input type="radio" name="theme" value="<?= e($k) ?>" <?= $a['theme'] === $k ? 'checked' : '' ?>>
                            <?= render_ad($sample + ['theme' => $k, 'title' => $t['name'], 'heading' => $t['name']], true) ?>
                            <div class="t-name"><b><?= e($t['name']) ?></b><small><?= e($t['desc']) ?></small></div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card">
                <div class="card-head"><h3><?= adm_icon('edit', 20) ?> محتوای تبلیغ</h3></div>
                <div class="tabs" id="fmtTabs">
                    <?php foreach (ad_formats() as $k => $l): ?>
                        <label class="opt opt-pill" style="padding:8px 14px;flex-direction:row;align-items:center"><input type="radio" name="format" value="<?= e($k) ?>" <?= $a['format'] === $k ? 'checked' : '' ?>><b style="font-size:.84rem"><?= e(explode(' (', $l)[0]) ?></b></label>
                    <?php endforeach; ?>
                </div>
                <div data-fmt="card image">
                    <?= f_image('image', 'تصویر (برای بنر تصویری الزامی؛ برای کارت اختیاری)', $a['image']) ?>
                </div>
                <div data-fmt="card">
                    <div class="grid g2">
                        <?= f_text('heading', 'تیتر تبلیغ', (string) $a['heading'], ['placeholder' => 'مثلاً: قرعه‌کشی ویژه شروع شد!']) ?>
                        <?= f_text('emoji', 'ایموجی', (string) $a['emoji'], ['maxlength' => 8, 'attrs' => 'data-emoji']) ?>
                    </div>
                    <?= f_textarea('body', 'متن تبلیغ', (string) $a['body'], ['rows' => 3]) ?>
                    <div class="grid g2">
                        <?= f_text('button_text', 'متن دکمه', (string) $a['button_text']) ?>
                        <?= f_text('badge', 'برچسب روبان (اختیاری)', (string) $a['badge'], ['placeholder' => 'ویژه / جدید / ۵۰٪ تخفیف']) ?>
                    </div>
                    <?= f_switch('countdown', 'شمارش معکوس تا تاریخ پایان ⏳', (bool) $a['countdown'], 'برای قرعه‌کشی و تخفیف‌های زمان‌دار عالیه') ?>
                </div>
                <div data-fmt="html">
                    <?= f_textarea('html', 'کد HTML / اسکریپت تبلیغ', (string) $a['html'], ['rows' => 8, 'code' => true, 'hint' => 'کد تبلیغاتی یکتانت، گوگل ادسنس، تپسل و ... را اینجا قرار دهید. این کد بدون تغییر در سایت قرار می‌گیرد.']) ?>
                </div>
                <div data-fmt="card image">
                    <?= f_text('link', 'لینک مقصد', (string) $a['link'], ['ltr' => true, 'placeholder' => 'https://t.me/...']) ?>
                    <?= f_switch('new_tab', 'باز شدن در تب جدید', (bool) $a['new_tab']) ?>
                </div>
            </div>
        </div>

        <div class="split-side">
            <div class="card">
                <div class="card-head"><h3><?= adm_icon('ads', 20) ?> تنظیمات</h3></div>
                <?= f_text('title', 'نام تبلیغ (داخلی)', (string) $a['title'], ['required' => true, 'placeholder' => 'مثلاً: بنر قرعه‌کشی آبان']) ?>
                <label class="af"><span class="af-label">جایگاه نمایش</span>
                    <select name="position" id="adPos">
                        <?php foreach ($positions as $k => $p): ?><option value="<?= e($k) ?>" <?= $a['position'] === $k ? 'selected' : '' ?>><?= e($p['name']) ?> — <?= "\u{2066}" . e($p['size']) . "\u{2069}" ?></option><?php endforeach; ?>
                    </select>
                    <small class="af-hint" id="posHint"></small>
                </label>
                <?= f_color('accent', 'رنگ اصلی تبلیغ', (string) $a['accent']) ?>
                <?= f_select('device', 'نمایش روی', ['all' => 'همه دستگاه‌ها', 'mobile' => 'فقط موبایل', 'desktop' => 'فقط دسکتاپ'], $a['device']) ?>
                <div id="popupOpts" class="grid g2">
                    <?= f_text('popup_delay', 'تاخیر نمایش (ثانیه)', (string) $a['popup_delay'], ['type' => 'number', 'min' => 0, 'max' => 120]) ?>
                    <?= f_text('popup_freq', 'تکرار هر چند ساعت', (string) $a['popup_freq'], ['type' => 'number', 'min' => 0, 'max' => 720]) ?>
                </div>
                <?= f_text('priority', 'اولویت (عدد بزرگ‌تر = اول)', (string) $a['priority'], ['type' => 'number']) ?>
            </div>
            <div class="card">
                <div class="card-head"><h3><?= icon('calendar', 20) ?> زمان‌بندی (شمسی)</h3></div>
                <?= f_jdate('start_at', 'شروع نمایش', $a['start_at'], 'خالی = از همین الان') ?>
                <?= f_jdate('end_at', 'پایان نمایش', $a['end_at'], 'خالی = نامحدود') ?>
                <?= f_switch('is_active', 'تبلیغ فعال باشد', (bool) $a['is_active']) ?>
            </div>
            <?php if ($a['id']): ?>
            <div class="card">
                <div class="card-head"><h3><?= adm_icon('chart', 20) ?> آمار</h3></div>
                <div class="list-item"><div class="li-body"><b>نمایش</b></div><span class="badge"><?= fa_num(number_format((int) $a['impressions'])) ?></span></div>
                <div class="list-item"><div class="li-body"><b>کلیک</b></div><span class="badge b-ok"><?= fa_num(number_format((int) $a['clicks'])) ?></span></div>
                <div class="list-item"><div class="li-body"><b>نرخ کلیک (CTR)</b></div><span class="ctr"><?= fa_num($a['impressions'] ? number_format($a['clicks'] / $a['impressions'] * 100, 2) : '0') ?>٪</span></div>
            </div>
            <?php endif; ?>
            <div class="form-actions">
                <button class="abtn abtn-primary" style="flex:1" type="submit"><?= icon('check', 18) ?> ذخیره تبلیغ</button>
                <button class="abtn abtn-ghost" type="submit" name="back" value="1">ذخیره و بازگشت</button>
            </div>
        </div>
    </div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('adForm'), prev = document.getElementById('adPreview'), pos = document.getElementById('adPos');
    var hints = <?= json_encode(array_map(fn($p) => $p['hint'] . ' · سایز پیشنهادی ' . "\u{2066}" . $p['size'] . "\u{2069}", $positions), JSON_UNESCAPED_UNICODE) ?>;
    var t;
    function fmt() {
        var f = form.querySelector('[name=format]:checked').value;
        document.querySelectorAll('[data-fmt]').forEach(function (el) { el.hidden = el.dataset.fmt.split(' ').indexOf(f) < 0; });
        document.getElementById('popupOpts').hidden = pos.value !== 'popup';
        document.getElementById('posHint').textContent = hints[pos.value] || '';
    }
    function refresh() {
        clearTimeout(t);
        t = setTimeout(function () {
            var fd = new FormData(form); fd.delete('_csrf');
            NXA.post('ad_preview', fd).then(function (j) {
                if (!j.ok) return;
                prev.innerHTML = j.html;
                prev.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); }); });
            });
        }, 350);
    }
    form.addEventListener('input', function () { fmt(); refresh(); });
    form.addEventListener('change', function () { fmt(); refresh(); });
    fmt();
});
</script>
<?php adm_foot();
