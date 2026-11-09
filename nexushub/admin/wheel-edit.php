<?php
require __DIR__ . '/inc/boot.php';
require_cap('wheels');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$w = $id ? row('SELECT * FROM wheels WHERE id = ?', [$id]) : null;
if ($id && !$w) { flash('گردونه پیدا نشد.', 'error'); redirect('admin/wheels.php'); }
$w = $w ?: ['id' => 0, 'slug' => '', 'title' => '', 'description' => '', 'image' => 'assets/img/presets/raffle-wheel.svg', 'audience' => ($_GET['audience'] ?? '') === 'private' ? 'private' : 'public',
    'status' => 'draft', 'items' => json_encode(wheel_sample_items(), JSON_UNESCAPED_UNICODE), 'rules' => json_encode(['signup' => 1, 'buy_every' => 10, 'buy_spins' => 1, 'invite_every' => 3, 'invite_spins' => 1, 'daily' => 0, 'max_user' => 0, 'old_buys' => 0]),
    'show_chances' => 1, 'box_on' => 1, 'box_places' => 'home_top,sidebar', 'box_theme' => 'neon', 'box_accent' => '', 'end_at' => null, 'created_at' => now()];
$errors = [];

if (is_post()) {
    csrf_check();
    $d = $w;
    $d['title'] = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 190);
    $d['description'] = mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 3000);
    $d['image'] = trim((string) ($_POST['image'] ?? ''));
    $d['audience'] = ($_POST['audience'] ?? '') === 'private' ? 'private' : 'public';
    $items = [];
    foreach ((array) ($_POST['it_label'] ?? []) as $i => $lab) {
        $lab = mb_substr(trim((string) $lab), 0, 60);
        if ($lab === '') continue;
        $stock = trim((string) en_num((string) ($_POST['it_stock'][$i] ?? '')));
        $items[] = ['label' => $lab, 'emoji' => mb_substr(trim((string) ($_POST['it_emoji'][$i] ?? '')), 0, 4),
            'chance' => max(0, min(100, round((float) en_num(str_replace(['٫', ','], '.', (string) ($_POST['it_chance'][$i] ?? '0'))), 3))),
            'kind' => in_array($_POST['it_kind'][$i] ?? '', ['empty', 'again', 'discount'], true) ? $_POST['it_kind'][$i] : 'prize',
            'disc' => ($_POST['it_kind'][$i] ?? '') === 'discount' ? discount_norm(json_decode((string) ($_POST['it_disc'][$i] ?? ''), true)) : null,
            'code' => mb_substr(trim((string) ($_POST['it_code'][$i] ?? '')), 0, 120), 'stock' => $stock === '' ? -1 : max(0, (int) $stock)];
    }
    $d['items'] = json_encode(array_slice($items, 0, 24), JSON_UNESCAPED_UNICODE);
    $num = fn($k, $max = 1000) => max(0, min($max, (int) en_num((string) ($_POST[$k] ?? 0))));
    $d['rules'] = json_encode(['signup' => $num('r_signup', 50), 'buy_every' => $num('r_buy_every'), 'buy_spins' => max(1, $num('r_buy_spins', 50)), 'invite_every' => $num('r_invite_every'),
        'invite_spins' => max(1, $num('r_invite_spins', 50)), 'daily' => $num('r_daily', 20), 'max_user' => $num('r_max_user', 100000), 'old_buys' => !empty($_POST['r_old_buys']) ? 1 : 0]);
    $d['show_chances'] = !empty($_POST['show_chances']) ? 1 : 0;
    $d['status'] = in_array($_POST['status'] ?? '', ['draft', 'active', 'paused'], true) ? $_POST['status'] : 'draft';
    $d['end_at'] = trim((string) ($_POST['end_at'] ?? '')) !== '' ? (jalali_to_datetime((string) $_POST['end_at']) ?: null) : null;
    $d['box_on'] = !empty($_POST['box_on']) ? 1 : 0;
    $d['box_places'] = implode(',', array_intersect((array) ($_POST['box_places'] ?? []), array_keys(shop_banner_places())));
    $d['box_theme'] = array_key_exists($_POST['box_theme'] ?? '', ad_themes()) ? $_POST['box_theme'] : 'neon';
    $d['box_accent'] = valid_hex((string) ($_POST['box_accent'] ?? ''), '') ?: null;

    if ($d['title'] === '') $errors[] = 'یه اسم برای گردونه بنویس.';
    if (count($items) < 2) $errors[] = 'حداقل ۲ گزینه لازمه.';
    if (!array_filter($items, fn($it) => $it['chance'] > 0)) $errors[] = 'حداقل یکی از گزینه‌ها باید شانس بیشتر از صفر داشته باشه.';

    if (!$errors) {
        $row = array_intersect_key($d, array_flip(['title', 'description', 'image', 'audience', 'items', 'rules', 'show_chances', 'status', 'end_at', 'box_on', 'box_places', 'box_theme', 'box_accent']));
        $row['updated_at'] = now();
        if ($w['id']) {
            q('UPDATE wheels SET ' . implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($row))) . ' WHERE id = ?', array_merge(array_values($row), [$w['id']]));
            $nid = (int) $w['id'];
        } else {
            $row['slug'] = unique_slug('wheels', slugify((string) ($_POST['slug'] ?? '') ?: $d['title']) ?: 'wheel-' . time());
            $row['created_at'] = now();
            $nid = (int) insert('wheels', $row);
        }
        if (isset($_POST['footer_wheel_seen'])) save_settings(['footer_wheel_link' => !empty($_POST['footer_wheel_link']) ? '1' : '0']);
        flash('گردونه ذخیره شد 🎡');
        redirect('admin/wheel-view.php?id=' . $nid);
    }
    $w = $d;
}

$items = wheel_items($w) ?: wheel_sample_items();
$R = wheel_rules($w);
$places = array_filter(explode(',', (string) $w['box_places']));
$chips = function (string $for, array $opts, string $cur): string {
    $h = '<div class="rf-quick" data-for="' . e($for) . '">';
    foreach ($opts as $v => $t) $h .= '<button type="button" data-v="' . e((string) $v) . '"' . ((string) $v === $cur ? ' class="on"' : '') . '>' . e($t) . '</button>';
    return $h . '</div>';
};
$kinds = ['prize' => ['🎁', 'جایزه'], 'empty' => ['😅', 'پوچ'], 'again' => ['🔁', 'دوباره'], 'discount' => ['🏷️', 'تخفیف']];
$shopData = ['cats' => rows('SELECT id, name FROM shop_categories ORDER BY sort_order, id'), 'products' => rows("SELECT id, title FROM products WHERE status = 'active' ORDER BY id DESC LIMIT 300")];
adm_head($w['id'] ? 'ویرایش گردونه شانس' : 'گردونه شانس جدید', 'wheels');
?>
<link rel="stylesheet" href="<?= e(asset('css/wheel.css')) ?>">
<form method="post" id="lwForm" class="rf-form lw-form">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
<?php foreach ($errors as $er): ?><div class="alert alert-warn"><?= e($er) ?></div><?php endforeach; ?>
<div class="split">
    <div>
        <div class="card">
            <div class="card-head"><h3>🎡 گردونه برای کیه؟</h3></div>
            <div class="rf-modes lw-aud">
                <label class="rf-mode"><input type="radio" name="audience" value="private" <?= $w['audience'] === 'private' ? 'checked' : '' ?>><span><b>🔒 فقط برای خودم</b><small>توی پنل خودت می‌چرخونی — برای قرعه‌ی سریع بین هر چی خواستی، با شانس‌های دلخواه. روی سایت نمایش داده نمی‌شه.</small></span></label>
                <label class="rf-mode"><input type="radio" name="audience" value="public" <?= $w['audience'] === 'public' ? 'checked' : '' ?>><span><b>🌍 برای کاربرهای سایت</b><small>کاربرها روی سایت می‌چرخونن؛ چرخش رو با ثبت‌نام، خرید، دعوت یا روزانه می‌گیرن.</small></span></label>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h3>📝 اطلاعات</h3></div>
            <div class="rf-info">
                <div class="rf-info-main">
                    <?= f_text('title', 'اسم گردونه', $w['title'], ['required' => true, 'placeholder' => 'مثلاً گردونه شانس پاییزه']) ?>
                    <?php if (!$w['id']): ?><?= f_text('slug', 'نامک آدرس (اختیاری، انگلیسی)', '', ['ltr' => true, 'placeholder' => 'lucky-wheel']) ?><?php endif; ?>
                    <?= f_textarea('description', 'توضیحات (اختیاری)', (string) $w['description'], ['rows' => 3]) ?>
                </div>
                <div class="rf-img"><?= f_image('image', 'تصویر', (string) $w['image'], 'برای کادر تبلیغ و بالای صفحه', preset_images('raffle')) ?></div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h3>🎯 گزینه‌ها و شانس‌ها</h3><span class="lw-sum" id="lwSum"></span></div>
            <p class="small muted" style="margin:-4px 0 12px">شانس هر گزینه به درصد. انتخاب روی سرور با الگوریتم امن انجام می‌شه و دقیقاً همین درصدها رعایت می‌شه. موجودی خالی = نامحدود؛ وقتی موجودی یه جایزه تموم بشه، دیگه نمیاد و شانسش بین بقیه پخش می‌شه.</p>
            <div class="lw-rows-head"><span></span><span>ایموجی</span><span>عنوان</span><span>شانس ٪</span><span>نوع</span><span>کد / توضیح برنده</span><span>موجودی</span><span></span></div>
            <div class="lw-rows" id="lwRows">
                <?php foreach ($items as $i => $it): ?>
                <div class="lw-wrap"><div class="lw-row">
                    <b class="lw-n"><?= fa_num((string) ($i + 1)) ?></b>
                    <input class="lw-emoji" name="it_emoji[]" value="<?= e($it['emoji']) ?>" maxlength="4" aria-label="ایموجی">
                    <input name="it_label[]" value="<?= e($it['label']) ?>" placeholder="مثلاً ۵۰ استارز" aria-label="عنوان">
                    <span class="lw-pct"><input name="it_chance[]" value="<?= e((string) (float) $it['chance']) ?>" inputmode="decimal" dir="ltr" aria-label="شانس"><i>٪</i></span>
                    <input type="hidden" name="it_kind[]" value="<?= e($it['kind']) ?>"><button type="button" class="lw-kind k-<?= e($it['kind']) ?>" data-kind><?= $kinds[$it['kind']][0] ?> <?= $kinds[$it['kind']][1] ?></button>
                    <input name="it_code[]" value="<?= e($it['code']) ?>" placeholder="اختیاری — به برنده نشون داده می‌شه" aria-label="کد">
                    <input name="it_stock[]" value="<?= $it['stock'] < 0 ? '' : (int) $it['stock'] ?>" placeholder="∞" inputmode="numeric" dir="ltr" aria-label="موجودی">
                    <button type="button" class="rfl-ib rfl-del lw-del" aria-label="حذف"><?= adm_icon('trash', 15) ?></button>
                    <input type="hidden" class="dsc-data" name="it_disc[]" value="<?= e(json_encode($it['disc'] ?: new stdClass(), JSON_UNESCAPED_UNICODE)) ?>">
                </div><div class="lw-dsc-host"></div></div>
                <?php endforeach; ?>
            </div>
            <div class="lw-tools">
                <button type="button" class="abtn tb-add" id="lwAdd"><?= adm_icon('plus', 16) ?> افزودن گزینه</button>
                <button type="button" class="lw-tool" id="lwEqual">⚖️ شانس مساوی</button>
                <button type="button" class="lw-tool" id="lwNorm">💯 رسوندن جمع به ۱۰۰</button>
                <button type="button" class="lw-tool" id="lwSample">✨ نمونه‌ی آماده</button>
            </div>
        </div>

        <div class="card" data-aud="public">
            <div class="card-head"><h3>🎟️ کاربرها چطوری چرخش بگیرن؟</h3></div>
            <div class="lw-rulegrid">
                <div class="lw-rulebox"><b>📝 ثبت‌نام در سایت</b><small>چند چرخش رایگان به هر عضو</small>
                    <?= f_text('r_signup', '', (string) $R['signup'], ['type' => 'number', 'min' => 0]) ?><?= $chips('r_signup', ['0' => 'هیچ', '1' => '۱', '2' => '۲', '3' => '۳'], (string) $R['signup']) ?></div>
                <div class="lw-rulebox"><b>🛍️ خرید از فروشگاه</b><small>هر چند خرید (تأییدشده) = چند چرخش</small>
                    <div class="lw-pair"><?= f_text('r_buy_every', 'هر چند خرید', (string) $R['buy_every'], ['type' => 'number', 'min' => 0, 'hint' => '۰ = خاموش']) ?><?= f_text('r_buy_spins', 'چند چرخش', (string) $R['buy_spins'], ['type' => 'number', 'min' => 1]) ?></div>
                    <?= $chips('r_buy_every', ['0' => 'خاموش', '1' => '۱', '5' => '۵', '10' => '۱۰', '20' => '۲۰'], (string) $R['buy_every']) ?>
                    <?= f_switch('r_old_buys', 'خریدهای قبل از ساخت گردونه هم حساب بشه', (bool) $R['old_buys']) ?></div>
                <div class="lw-rulebox"><b>🤝 دعوت دوستان</b><small>هر چند دعوت (کسی که با لینک ثبت‌نام کنه) = چند چرخش</small>
                    <div class="lw-pair"><?= f_text('r_invite_every', 'هر چند دعوت', (string) $R['invite_every'], ['type' => 'number', 'min' => 0, 'hint' => '۰ = خاموش']) ?><?= f_text('r_invite_spins', 'چند چرخش', (string) $R['invite_spins'], ['type' => 'number', 'min' => 1]) ?></div>
                    <?= $chips('r_invite_every', ['0' => 'خاموش', '1' => '۱', '3' => '۳', '5' => '۵', '10' => '۱۰'], (string) $R['invite_every']) ?></div>
                <div class="lw-rulebox"><b>☀️ چرخش روزانه</b><small>هر روز چند چرخش رایگان (تمدید ساعت ۰۰:۰۰)</small>
                    <?= f_text('r_daily', '', (string) $R['daily'], ['type' => 'number', 'min' => 0]) ?><?= $chips('r_daily', ['0' => 'خاموش', '1' => '۱', '2' => '۲', '3' => '۳'], (string) $R['daily']) ?></div>
                <div class="lw-rulebox"><b>🧱 سقف هر نفر</b><small>کل چرخش‌هایی که یه نفر می‌تونه بزنه</small>
                    <?= f_text('r_max_user', '', (string) $R['max_user'], ['type' => 'number', 'min' => 0, 'hint' => '۰ = بدون سقف']) ?><?= $chips('r_max_user', ['0' => 'بدون سقف', '3' => '۳', '5' => '۵', '10' => '۱۰', '50' => '۵۰'], (string) $R['max_user']) ?></div>
                <div class="lw-rulebox"><b>🎁 هدیه‌ی دستی</b><small>از صفحه‌ی مدیریت گردونه می‌تونی به هر عضوی چرخش هدیه بدی.</small><?= f_switch('show_chances', 'درصد شانس‌ها به کاربرها نشون داده بشه', (bool) $w['show_chances']) ?></div>
            </div>
        </div>

        <div class="card" data-aud="public">
            <div class="card-head"><h3>📣 کادر تبلیغ روی سایت</h3><small class="muted">یه تم انتخاب کن</small></div>
            <div class="rf-boxrow">
                <?= f_switch('box_on', 'نمایش کادر روی سایت', (bool) $w['box_on']) ?>
                <label class="af"><span class="af-label">کجای سایت؟ (چند مورد)</span>
                    <select name="box_places[]" multiple data-multi data-placeholder="هیچ جایی انتخاب نشده">
                        <?php foreach (shop_banner_places() as $k => $nm): ?><option value="<?= $k ?>" <?= in_array($k, $places, true) ? 'selected' : '' ?>><?= e($nm) ?></option><?php endforeach; ?>
                    </select></label>
                <?= f_color('box_accent', 'رنگ کادر', (string) ($w['box_accent'] ?: setting('color_primary', '#ff1f3d'))) ?>
            </div>
            <div class="theme-gallery rf-gallery" id="rfGallery">
                <?php foreach (ad_themes() as $k => $t): ?>
                    <label class="theme-opt">
                        <input type="radio" name="box_theme" value="<?= e($k) ?>" <?= $w['box_theme'] === $k ? 'checked' : '' ?>>
                        <?= wheel_box_html(['box_theme' => $k] + $w, true) ?>
                        <div class="t-name"><b><?= e($t['name']) ?></b><small><?= e($t['desc']) ?></small></div>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div>
        <div class="card lw-prev-card">
            <div class="card-head"><h3>👀 پیش‌نمایش زنده</h3></div>
            <div class="nxw" id="lwPrev" data-logo="<?= e(logo_url()) ?>"></div>
            <small class="af-hint" style="text-align:center;display:block">هر گزینه رو عوض کنی، گردونه همین‌جا عوض می‌شه.</small>
        </div>
        <div class="card" data-aud="public">
            <div class="card-head"><h3>⏰ وضعیت</h3></div>
            <?= f_select('status', 'وضعیت', ['draft' => 'پیش‌نویس (فقط خودت می‌بینی)', 'active' => 'فعال — کاربرها می‌تونن بچرخونن', 'paused' => 'متوقف'], $w['status']) ?>
            <?= f_jdate('end_at', 'پایان (اختیاری، شمسی)', $w['end_at'], 'خالی = همیشه فعال') ?>
            <input type="hidden" name="footer_wheel_seen" value="1"><?= f_switch('footer_wheel_link', 'بخش «گردونه شانس» روی سایت باز باشه (لینک فوتر)', setting_on('footer_wheel_link'), 'اگه خاموش کنی لینک فوتر و کادرها برداشته می‌شن و هر کی لینک رو باز کنه می‌بینه این بخش بسته‌ست') ?>
        </div>
        <div class="card rf-savecard">
            <div class="rf-save-head"><span class="rf-save-ic">🎡</span><div><b><?= $w['id'] ? 'ذخیره‌ی تغییرات' : 'ساخت گردونه' ?></b><small>بعد از ذخیره، توی صفحه‌ی مدیریت می‌تونی بچرخونیش.</small></div></div>
            <button class="rf-save" type="submit"><span class="rf-save-check"><?= icon('check', 18) ?></span><span><?= $w['id'] ? 'ذخیره و اعمال' : 'ساخت و ذخیره' ?></span></button>
            <a class="rf-back" href="wheels.php">→ بازگشت به لیست گردونه‌ها</a>
        </div>
    </div>
</div>
<input type="hidden" name="box_places[]" value="">
</form>
<script>window.LW_SAMPLE = <?= json_encode(wheel_sample_items(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>; window.NX_SHOP = <?= json_encode($shopData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var $$ = function (s, c) { return [].slice.call((c || document).querySelectorAll(s)); };
    var rows = document.getElementById('lwRows'), KIND = { prize: ['🎁', 'جایزه'], empty: ['😅', 'پوچ'], again: ['🔁', 'دوباره'], discount: ['🏷️', 'تخفیف'] }, ORDER = ['prize', 'discount', 'empty', 'again'];
    var num = function (v) { v = String(v || '').replace(/[۰-۹]/g, function (c) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(c); }).replace(/[٫,]/g, '.'); return parseFloat(v) || 0; };
    var W = window.NXWheel ? NXWheel(document.getElementById('lwPrev'), { empty: 'گزینه اضافه کن' }) : null;
    function rowHtml(it) {
        var k = it.kind || 'prize';
        return '<b class="lw-n"></b><input class="lw-emoji" name="it_emoji[]" maxlength="4" aria-label="ایموجی"><input name="it_label[]" placeholder="عنوان گزینه" aria-label="عنوان">'
            + '<span class="lw-pct"><input name="it_chance[]" inputmode="decimal" dir="ltr" aria-label="شانس"><i>٪</i></span>'
            + '<input type="hidden" name="it_kind[]"><button type="button" class="lw-kind" data-kind></button>'
            + '<input name="it_code[]" placeholder="اختیاری — به برنده نشون داده می‌شه" aria-label="کد"><input name="it_stock[]" placeholder="∞" inputmode="numeric" dir="ltr" aria-label="موجودی">'
            + '<button type="button" class="rfl-ib rfl-del lw-del" aria-label="حذف"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button>'
            + '<input type="hidden" class="dsc-data" name="it_disc[]" value="{}">';
    }
    var panels = new WeakMap();
    function setKind(row, k) {
        row.querySelector('[name="it_kind[]"]').value = k; var b = row.querySelector('[data-kind]'); b.className = 'lw-kind k-' + k; b.textContent = KIND[k][0] + ' ' + KIND[k][1];
        var host = row.parentNode.querySelector('.lw-dsc-host'), cur = panels.get(row);
        if (k === 'discount' && !cur && window.NXDiscount && host) {
            panels.set(row, NXDiscount(row, row.querySelector('.dsc-data'), host));
            var lab = row.querySelector('[name="it_label[]"]'); if (!lab.value.trim()) lab.value = JSON.parse(row.querySelector('.dsc-data').value || '{}').pct ? NXA.fa(JSON.parse(row.querySelector('.dsc-data').value).pct) + '٪ تخفیف' : '۱۰٪ تخفیف';
        }
        if (k !== 'discount' && cur) { cur.remove(); panels.delete(row); }
    }
    function addRow(it) {
        var w = document.createElement('div'); w.className = 'lw-wrap'; w.innerHTML = '<div class="lw-row"></div><div class="lw-dsc-host"></div>';
        var r = w.firstChild; r.innerHTML = rowHtml(it || {});
        if (it && it.disc) r.querySelector('.dsc-data').value = JSON.stringify(it.disc);
        r.querySelector('[name="it_emoji[]"]').value = (it && it.emoji) || '🎁'; r.querySelector('[name="it_label[]"]').value = (it && it.label) || '';
        r.querySelector('[name="it_chance[]"]').value = it && it.chance != null ? it.chance : 10; r.querySelector('[name="it_code[]"]').value = (it && it.code) || '';
        r.querySelector('[name="it_stock[]"]').value = it && it.stock >= 0 ? it.stock : '';
        rows.appendChild(w); setKind(r, (it && it.kind) || 'prize'); return r;
    }
    function list() { return $$('.lw-row', rows).map(function (r) { return { row: r, label: r.querySelector('[name="it_label[]"]').value.trim(), emoji: r.querySelector('[name="it_emoji[]"]').value.trim(), chance: num(r.querySelector('[name="it_chance[]"]').value) }; }); }
    function sync() {
        $$('.lw-n', rows).forEach(function (b, i) { b.textContent = NXA.fa(i + 1); });
        var L = list(), sum = L.reduce(function (a, x) { return a + (x.label ? x.chance : 0); }, 0), el = document.getElementById('lwSum');
        el.textContent = 'جمع شانس‌ها: ' + NXA.fa(Math.round(sum * 100) / 100) + '٪'; el.className = 'lw-sum ' + (Math.abs(sum - 100) < .01 ? 'ok' : 'warn');
        el.title = Math.abs(sum - 100) < .01 ? '' : 'اگه جمع ۱۰۰ نباشه، شانس‌ها به نسبت حساب می‌شن';
        if (W) W.build(L.filter(function (x) { return x.label; }).map(function (x) { return { label: x.label, emoji: x.emoji, w: 1 }; }));
    }
    document.getElementById('lwAdd').addEventListener('click', function () { var r = addRow({}); sync(); r.querySelector('[name="it_label[]"]').focus(); });
    rows.addEventListener('click', function (e) {
        var k = e.target.closest('[data-kind]'); if (k) { var row = k.closest('.lw-row'), cur = row.querySelector('[name="it_kind[]"]').value; setKind(row, ORDER[(ORDER.indexOf(cur) + 1) % ORDER.length]); return; }
        var x = e.target.closest('.lw-del'); if (x && rows.children.length > 2) { x.closest('.lw-wrap').remove(); sync(); }
    });
    rows.addEventListener('input', sync);
    document.getElementById('lwEqual').addEventListener('click', function () { var L = list().filter(function (x) { return x.label; }), v = Math.floor(10000 / L.length) / 100; L.forEach(function (x, i) { x.row.querySelector('[name="it_chance[]"]').value = i === L.length - 1 ? Math.round((100 - v * (L.length - 1)) * 100) / 100 : v; }); sync(); });
    document.getElementById('lwNorm').addEventListener('click', function () { var L = list().filter(function (x) { return x.label; }), s = L.reduce(function (a, x) { return a + x.chance; }, 0); if (!s) return; var acc = 0; L.forEach(function (x, i) { var v = i === L.length - 1 ? Math.round((100 - acc) * 100) / 100 : Math.round(x.chance / s * 10000) / 100; acc += v; x.row.querySelector('[name="it_chance[]"]').value = v; }); sync(); });
    document.getElementById('lwSample').addEventListener('click', function () { rows.innerHTML = ''; (window.LW_SAMPLE || []).forEach(addRow); sync(); });
    // audience: private wheels hide the site-only settings
    function aud() { var v = (document.querySelector('[name=audience]:checked') || {}).value; $$('[data-aud]').forEach(function (el) { el.hidden = el.getAttribute('data-aud') !== v; }); }
    $$('[name=audience]').forEach(function (r) { r.addEventListener('change', aud); }); aud();
    // quick chips
    $$('.rf-quick').forEach(function (q) {
        var inp = document.querySelector('[name="' + q.dataset.for + '"]'); if (!inp) return;
        var s = function () { $$('button', q).forEach(function (b) { b.classList.toggle('on', String(parseFloat(b.dataset.v)) === String(parseFloat(inp.value))); }); };
        q.addEventListener('click', function (e) { var b = e.target.closest('[data-v]'); if (b) { inp.value = b.dataset.v; s(); } });
        inp.addEventListener('input', s); s();
    });
    $$('.lw-row', rows).forEach(function (r) { var k = r.querySelector('[name="it_kind[]"]').value; if (k === 'discount') setKind(r, k); });
    sync();
});
</script>
<?php adm_foot(['wheel.js', 'discount-editor.js']);
