<?php
require __DIR__ . '/inc/boot.php';
require_cap('raffles');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$r = $id ? row('SELECT * FROM raffles WHERE id = ?', [$id]) : null;
if ($id && !$r) { flash('قرعه‌کشی پیدا نشد.', 'error'); redirect('admin/raffles.php'); }
$r = $r ?: ['id' => 0, 'slug' => '', 'title' => '', 'description' => '', 'image' => '', 'mode' => 'free', 'prizes' => json_encode([['title' => '', 'emoji' => '🎁']]),
    'draw_at' => date('Y-m-d H:i:s', strtotime('+1 day')), 'min_participants' => 20, 'min_tickets' => 0, 'ticket_price' => 50000, 'max_tickets_user' => 100,
    'ticket_bonus' => 100, 'max_referrals' => 5, 'status' => 'draft', 'box_on' => 1, 'box_places' => 'home_top,sidebar,post_bottom', 'box_theme' => 'neon', 'box_accent' => '', 'result_hours' => 24];
$locked = in_array($r['status'], ['drawn', 'failed'], true);
$errors = [];

if (is_post()) {
    csrf_check();
    $d = $r;
    $d['title'] = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 190);
    $d['description'] = mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 4000);
    $d['image'] = trim((string) ($_POST['image'] ?? ''));
    $d['mode'] = array_key_exists($_POST['mode'] ?? '', raffle_modes()) ? $_POST['mode'] : 'free';
    $pz = [];
    foreach ((array) ($_POST['prize_title'] ?? []) as $i => $t) {
        $t = mb_substr(trim((string) $t), 0, 120);
        if ($t === '') continue;
        $one = ['title' => $t, 'emoji' => mb_substr(trim((string) ($_POST['prize_emoji'][$i] ?? '🎁')), 0, 4) ?: '🎁'];
        if (($_POST['prize_kind'][$i] ?? '') === 'discount' && ($dc = discount_norm(json_decode((string) ($_POST['prize_disc'][$i] ?? ''), true)))) $one['discount'] = $dc;
        $pz[] = $one;
    }
    $d['prizes'] = json_encode(array_slice($pz, 0, 20), JSON_UNESCAPED_UNICODE);
    $d['draw_at'] = jalali_to_datetime((string) ($_POST['draw_at'] ?? '')) ?: $r['draw_at'];
    $d['min_participants'] = max(1, min(100000, (int) en_num((string) ($_POST['min_participants'] ?? 1))));
    $d['min_tickets'] = max(0, min(1000000, (int) en_num((string) ($_POST['min_tickets'] ?? 0))));
    $d['ticket_price'] = max(0, min(1000000000, (int) en_num(str_replace([',', '٬'], '', (string) ($_POST['ticket_price'] ?? 0)))));
    $d['max_tickets_user'] = max(0, min(100000, (int) en_num((string) ($_POST['max_tickets_user'] ?? 0))));
    $d['ticket_bonus'] = max(1, min(1000, (float) en_num((string) ($_POST['ticket_bonus'] ?? 100))));
    $d['max_referrals'] = max(0, min(10000, (int) en_num((string) ($_POST['max_referrals'] ?? 0))));
    $d['box_on'] = !empty($_POST['box_on']) ? 1 : 0;
    $d['box_places'] = implode(',', array_intersect((array) ($_POST['box_places'] ?? []), array_keys(shop_banner_places())));
    $d['box_theme'] = array_key_exists($_POST['box_theme'] ?? '', ad_themes()) ? $_POST['box_theme'] : 'neon';
    $d['box_accent'] = valid_hex((string) ($_POST['box_accent'] ?? ''), '') ?: null;
    $d['result_hours'] = max(0, min(720, (int) en_num((string) ($_POST['result_hours'] ?? 24))));
    $want = (string) ($_POST['status'] ?? 'draft');
    if (!$locked) $d['status'] = in_array($want, ['draft', 'active', 'cancelled'], true) ? $want : 'draft';

    if ($d['title'] === '') $errors[] = 'عنوان قرعه‌کشی رو بنویس.';
    if (!$pz) $errors[] = 'حداقل یک جایزه تعریف کن.';
    if ($d['status'] === 'active' && strtotime($d['draw_at']) <= time() + 60) $errors[] = 'زمان برگزاری باید در آینده باشه.';
    if ($d['mode'] === 'paid' && $d['ticket_price'] <= 0) $errors[] = 'قیمت هر تیکت رو مشخص کن.';

    if (!$errors) {
        $row = array_intersect_key($d, array_flip(['title', 'description', 'image', 'mode', 'prizes', 'draw_at', 'min_participants', 'min_tickets', 'ticket_price', 'max_tickets_user', 'ticket_bonus', 'max_referrals', 'status', 'box_on', 'box_places', 'box_theme', 'box_accent', 'result_hours']));
        $row['updated_at'] = now();
        if ($r['id']) {
            $sets = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($row)));
            q("UPDATE raffles SET $sets WHERE id = ?", array_merge(array_values($row), [$r['id']]));
            $nid = (int) $r['id'];
        } else {
            $row['slug'] = unique_slug('raffles', slugify((string) ($_POST['slug'] ?? '') ?: $d['title']) ?: 'raffle-' . time());
            $row['created_at'] = now();
            $nid = (int) insert('raffles', $row);
        }
        save_settings(['footer_raffle_link' => !empty($_POST['footer_raffle_link']) ? '1' : '0']);
        flash($d['status'] === 'active' ? 'قرعه‌کشی فعال شد 🎰' : 'ذخیره شد ✅');
        redirect('admin/raffle-view.php?id=' . $nid);
    }
    $r = $d;
}

$prizes = json_decode((string) $r['prizes'], true) ?: [['title' => '', 'emoji' => '🎁']];
$places = array_filter(explode(',', (string) $r['box_places']));
adm_head($r['id'] ? 'ویرایش قرعه‌کشی' : 'قرعه‌کشی جدید', 'raffles');
?>
<form method="post" id="rfForm" class="rf-form">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
<?php foreach ($errors as $er): ?><div class="alert alert-warn"><?= e($er) ?></div><?php endforeach; ?>
<?php if ($locked): ?><div class="alert alert-info">این قرعه‌کشی <?= e(raffle_status_names()[$r['status']]) ?>؛ فقط متن‌ها و نمایش قابل تغییرن.</div><?php endif; ?>
<?php
$chips = function (string $for, array $opts, string $cur): string {
    $h = '<div class="rf-quick" data-for="' . e($for) . '">';
    foreach ($opts as $v => $t) $h .= '<button type="button" data-v="' . e((string) $v) . '"' . ((string) $v === $cur ? ' class="on"' : '') . '>' . e($t) . '</button>';
    return $h . '</div>';
};
$bonus = (string) (float) $r['ticket_bonus'];
?>
<div class="split">
    <div>
        <div class="card">
            <div class="card-head"><h3>🎰 اطلاعات قرعه‌کشی</h3></div>
            <div class="rf-info">
                <div class="rf-info-main">
                    <?= f_text('title', 'عنوان', $r['title'], ['required' => true, 'placeholder' => 'مثلاً قرعه‌کشی بزرگ پریمیوم تلگرام']) ?>
                    <?php if (!$r['id']): ?><?= f_text('slug', 'نامک آدرس (اختیاری، انگلیسی)', '', ['ltr' => true, 'placeholder' => 'premium-giveaway']) ?><?php endif; ?>
                    <?= f_textarea('description', 'توضیحات و قوانین', (string) $r['description'], ['rows' => 4]) ?>
                </div>
                <div class="rf-img"><?= f_image('image', 'تصویر کادر و صفحه', (string) $r['image'], 'یکی از تصاویر متحرک آماده رو بزن یا عکس خودت رو آپلود کن', preset_images('raffle')) ?></div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h3>🎁 جایزه‌ها</h3><small class="muted">به ترتیب: نفر اول، دوم، …</small></div>
            <div id="pzList" class="pz-list">
                <?php foreach ($prizes as $i => $p): ?>
                <?php $isD = !empty($p['discount']); ?>
                <div class="pz-wrap"><div class="pz-row"><b class="pz-n"><?= fa_num((string) ($i + 1)) ?></b><input class="pz-emoji" name="prize_emoji[]" value="<?= e($p['emoji'] ?? '🎁') ?>" maxlength="4" aria-label="ایموجی"><input name="prize_title[]" value="<?= e($p['title'] ?? '') ?>" placeholder="مثلاً ۳ ماه پریمیوم تلگرام" aria-label="عنوان جایزه"><input type="hidden" name="prize_kind[]" value="<?= $isD ? 'discount' : 'item' ?>"><input type="hidden" class="dsc-data" name="prize_disc[]" value="<?= e(json_encode($p['discount'] ?? new stdClass(), JSON_UNESCAPED_UNICODE)) ?>"><button type="button" class="pz-kind <?= $isD ? 'is-d' : '' ?>" data-pzkind><?= $isD ? '🏷️ تخفیف' : '🎁 هدیه' ?></button><button type="button" class="abtn abtn-sm abtn-ghost pz-del" aria-label="حذف">✕</button></div><div class="pz-dsc-host"></div></div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="abtn abtn-sm tb-add" id="pzAdd"><?= adm_icon('plus', 16) ?> افزودن جایزه</button>
        </div>

        <div class="card">
            <div class="card-head"><h3>⚙️ روش شرکت</h3></div>
            <div class="rf-modes">
                <?php foreach (raffle_modes() as $k => [$nm, $ds, $ic]): ?>
                <label class="rf-mode"><input type="radio" name="mode" value="<?= $k ?>" <?= $r['mode'] === $k ? 'checked' : '' ?> <?= $locked ? 'disabled' : '' ?>><span><b><?= $ic ?> <?= e($nm) ?></b><small><?= e($ds) ?></small></span></label>
                <?php endforeach; ?>
            </div>
            <div class="rf-modeset" data-mode="free"><b>🎟️ رایگان</b><p class="small muted" style="margin:0">هر عضو با یک کلیک شرکت می‌کنه و شانس همه برابره؛ تنظیم دیگه‌ای لازم نیست.</p></div>
            <div class="rf-modeset" data-mode="referral">
                <b>🤝 امتیاز دعوت</b>
                <div class="grid g2">
                    <div>
                        <?= f_text('ticket_bonus', 'هر دعوت چقدر شانس اضافه کنه؟ (درصد)', $bonus, ['type' => 'number', 'min' => 1, 'max' => 1000]) ?>
                        <?= $chips('ticket_bonus', ['25' => '۲۵٪', '50' => '۵۰٪', '100' => '۱۰۰٪', '200' => '۲۰۰٪'], $bonus) ?>
                        <small class="af-hint">۱۰۰٪ یعنی هر دعوت = یک شانس کامل دیگه</small>
                    </div>
                    <div>
                        <?= f_text('max_referrals', 'سقف دعوتِ امتیازدار برای هر نفر', (string) (int) $r['max_referrals'], ['type' => 'number', 'min' => 0, 'max' => 10000]) ?>
                        <?= $chips('max_referrals', ['1' => '۱', '2' => '۲', '3' => '۳', '5' => '۵', '10' => '۱۰', '20' => '۲۰', '0' => 'نامحدود'], (string) (int) $r['max_referrals']) ?>
                        <small class="af-hint" id="refHint"></small>
                    </div>
                </div>
            </div>
            <div class="rf-modeset" data-mode="paid">
                <b>💳 تیکت پولی</b>
                <div class="grid g3">
                    <?= f_text('ticket_price', 'قیمت هر تیکت (تومان)', (string) $r['ticket_price'], ['type' => 'number', 'min' => 0]) ?>
                    <?= f_text('max_tickets_user', 'سقف خرید هر نفر', (string) $r['max_tickets_user'], ['type' => 'number', 'min' => 0, 'hint' => '۰ = بدون سقف']) ?>
                    <?= f_text('min_tickets', 'حداقل تیکت فروخته‌شده', (string) $r['min_tickets'], ['type' => 'number', 'min' => 0, 'hint' => 'اگه نرسه برگزار نمی‌شه']) ?>
                </div>
                <p class="small muted" style="margin:6px 0 0">پرداخت کارت‌به‌کارت با کارت تنظیمات فروشگاه؛ خریدار فیش رو آپلود می‌کنه و تو تأییدش می‌کنی. شماره‌ی تیکت‌ها به ترتیب تأیید داده می‌شه.</p>
            </div>
        </div>
        <div class="card">
            <div class="card-head"><h3>📣 کادر تبلیغ روی سایت</h3><small class="muted">یه تم انتخاب کن — پیش‌نمایش‌ها زنده‌ان</small></div>
            <div class="rf-boxrow">
                <?= f_switch('box_on', 'نمایش کادر روی سایت', (bool) $r['box_on'], 'با شمارش معکوس و تعداد شرکت‌کننده‌ها') ?>
                <label class="af"><span class="af-label">کجای سایت؟ (چند مورد)</span>
                    <select name="box_places[]" multiple data-multi data-placeholder="هیچ جایی انتخاب نشده">
                        <?php foreach (shop_banner_places() as $k => $nm): ?><option value="<?= $k ?>" <?= in_array($k, $places, true) ? 'selected' : '' ?>><?= e($nm) ?></option><?php endforeach; ?>
                    </select></label>
                <?= f_color('box_accent', 'رنگ کادر', (string) ($r['box_accent'] ?: setting('color_primary', '#ff1f3d'))) ?>
            </div>
            <div class="theme-gallery rf-gallery" id="rfGallery">
                <?php $sampleSt = ['participants' => max(1, (int) round((int) $r['min_participants'] * .6)), 'need' => (int) $r['min_participants'], 'sold' => max(1, (int) round((int) $r['min_tickets'] * .6)), 'need_tickets' => (int) $r['min_tickets']];
                foreach (ad_themes() as $k => $t): ?>
                    <label class="theme-opt">
                        <input type="radio" name="box_theme" value="<?= e($k) ?>" <?= $r['box_theme'] === $k ? 'checked' : '' ?>>
                        <?= raffle_box_html(['box_theme' => $k, 'status' => 'active', 'prizes' => json_encode($prizes, JSON_UNESCAPED_UNICODE)] + $r, $sampleSt, true) ?>
                        <div class="t-name"><b><?= e($t['name']) ?></b><small><?= e($t['desc']) ?></small></div>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div>
        <div class="rf-sticky">
        <div class="card">
            <div class="card-head"><h3>⏰ زمان و وضعیت</h3></div>
            <?= f_jdate('draw_at', 'زمان برگزاری (شمسی)', $r['draw_at'], 'سر همین ساعت گردونه روی سایت زنده می‌چرخه') ?>
            <?php if (!$locked): ?><?= f_select('status', 'وضعیت', ['draft' => 'پیش‌نویس (فقط خودت می‌بینی)', 'active' => 'فعال — مردم می‌تونن شرکت کنن', 'cancelled' => 'لغو شده'], $r['status']) ?><?php endif; ?>
            <?= f_text('min_participants', 'حداقل شرکت‌کننده', (string) $r['min_participants'], ['type' => 'number', 'min' => 1, 'hint' => 'اگه تا زمان برگزاری نرسه، قرعه‌کشی برگزار نمی‌شه']) ?>
            <?= f_text('result_hours', 'نمایش نتیجه روی سایت (ساعت)', (string) (int) ($r['result_hours'] ?? 24), ['type' => 'number', 'min' => 0, 'max' => 720]) ?>
            <?= $chips('result_hours', ['0' => 'نه', '6' => '۶ ساعت', '24' => '۱ روز', '72' => '۳ روز', '168' => '۱ هفته'], (string) (int) ($r['result_hours'] ?? 24)) ?>
            <small class="af-hint">تو این مدت کادر «برنده مشخص شد» روی سایت می‌مونه و گردونه برای هر کی وارد صفحه بشه خودکار می‌چرخه.</small>
            <?= f_switch('footer_raffle_link', 'بخش «قرعه‌کشی» روی سایت باز باشه (لینک فوتر)', setting_on('footer_raffle_link'), 'اگه خاموش کنی لینک فوتر و کادرها برداشته می‌شن و هر کی لینک رو باز کنه می‌بینه این بخش بسته‌ست') ?>
        </div>
        <div class="card rf-savecard">
            <div class="rf-save-head"><span class="rf-save-ic">🎰</span><div><b><?= $r['id'] ? 'ذخیره‌ی تغییرات' : 'ساخت قرعه‌کشی' ?></b><small>وضعیت فعلی: <?= e(['draft' => '📝 پیش‌نویس', 'active' => '🟢 فعال', 'cancelled' => '⛔ لغو شده', 'drawn' => '🏆 برگزار شده', 'failed' => 'ℹ️ برگزار نشد'][$r['status']] ?? $r['status']) ?></small></div></div>
            <button class="rf-save" type="submit"><span class="rf-save-check"><?= icon('check', 18) ?></span><span><?= $r['id'] ? 'ذخیره و اعمال روی سایت' : 'ساخت و ذخیره' ?></span></button>
            <a class="rf-back" href="raffles.php">→ بازگشت به لیست قرعه‌کشی‌ها</a>
        </div>
        </div>
    </div>
</div>

<input type="hidden" name="box_places[]" value="">
</form>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var $$ = function (s, c) { return [].slice.call((c || document).querySelectorAll(s)); };
    var list = document.getElementById('pzList');
    function renum() { $$('.pz-n', list).forEach(function (b, i) { b.textContent = NXA.fa(i + 1); }); }
    document.getElementById('pzAdd').addEventListener('click', function () {
        var w = document.createElement('div'); w.className = 'pz-wrap';
        w.innerHTML = '<div class="pz-row"><b class="pz-n"></b><input class="pz-emoji" name="prize_emoji[]" value="🎁" maxlength="4" aria-label="ایموجی"><input name="prize_title[]" placeholder="عنوان جایزه" aria-label="عنوان جایزه"><input type="hidden" name="prize_kind[]" value="item"><input type="hidden" class="dsc-data" name="prize_disc[]" value="{}"><button type="button" class="pz-kind" data-pzkind>🎁 هدیه</button><button type="button" class="abtn abtn-sm abtn-ghost pz-del" aria-label="حذف">✕</button></div><div class="pz-dsc-host"></div>';
        list.appendChild(w); renum(); w.querySelector('[name="prize_title[]"]').focus(); live();
    });
    list.addEventListener('click', function (e) {
        var x = e.target.closest('.pz-del'); if (x && list.children.length > 1) { x.closest('.pz-wrap').remove(); renum(); live(); return; }
        var k = e.target.closest('[data-pzkind]'); if (k) { var row = k.closest('.pz-row'), kind = row.querySelector('[name="prize_kind[]"]'); setPz(row, kind.value === 'discount' ? 'item' : 'discount'); }
    });
    var pzPanels = new WeakMap();
    function setPz(row, v) {   // a prize is a gift, or a discount the shop applies by itself
        row.querySelector('[name="prize_kind[]"]').value = v;
        var b = row.querySelector('[data-pzkind]'); b.textContent = v === 'discount' ? '🏷️ تخفیف' : '🎁 هدیه'; b.classList.toggle('is-d', v === 'discount');
        var cur = pzPanels.get(row), host = row.parentNode.querySelector('.pz-dsc-host');
        if (v === 'discount' && !cur && window.NXDiscount) { pzPanels.set(row, NXDiscount(row, row.querySelector('.dsc-data'), host)); var t = row.querySelector('[name="prize_title[]"]'); if (!t.value.trim()) t.value = '۱۰٪ تخفیف فروشگاه'; }
        if (v !== 'discount' && cur) { cur.remove(); pzPanels.delete(row); }
    }
    $$('.pz-row', list).forEach(function (r) { if (r.querySelector('[name="prize_kind[]"]').value === 'discount') setPz(r, 'discount'); });
    // mode cards -> show only that mode's settings
    function mode() { var m = (document.querySelector('[name=mode]:checked') || {}).value; $$('[data-mode]').forEach(function (el) { el.hidden = el.getAttribute('data-mode') !== m; }); }
    $$('[name=mode]').forEach(function (r) { r.addEventListener('change', mode); }); mode();
    // quick-pick chips
    $$('.rf-quick').forEach(function (q) {
        var inp = document.querySelector('[name="' + q.dataset.for + '"]'); if (!inp) return;
        var sync = function () { $$('button', q).forEach(function (b) { b.classList.toggle('on', String(parseFloat(b.dataset.v)) === String(parseFloat(NXA.en ? NXA.en(inp.value) : inp.value))); }); hint(); };
        q.addEventListener('click', function (e) { var b = e.target.closest('[data-v]'); if (!b) return; inp.value = b.dataset.v; inp.dispatchEvent(new Event('input', { bubbles: true })); sync(); });
        inp.addEventListener('input', sync); sync();
    });
    function hint() {
        var h = document.getElementById('refHint'), mx = parseInt(document.querySelector('[name=max_referrals]').value, 10) || 0, b = parseFloat(document.querySelector('[name=ticket_bonus]').value) || 0;
        if (h) h.textContent = mx ? 'هر نفر حداکثر با ' + NXA.fa(mx) + ' دعوت می‌تونه شانسش رو تا ' + NXA.fa(Math.round(100 + mx * b) / 100) + ' برابر کنه؛ دعوت‌های بیشتر امتیاز نداره.' : 'نامحدود: هر چی دعوت کنه شانسش بیشتر می‌شه.';
    }
    // live previews in the theme gallery
    var g = document.getElementById('rfGallery');
    function live() {
        var t = (document.querySelector('[name=title]').value || 'قرعه‌کشی بزرگ').trim(), p = ((document.querySelector('[name="prize_title[]"]') || {}).value || 'جایزه‌ی ویژه').trim();
        var n = $$('[name="prize_title[]"]').filter(function (i) { return i.value.trim(); }).length, em = (document.querySelector('[name="prize_emoji[]"]') || {}).value || '🎁';
        $$('.rfx-title', g).forEach(function (x) { x.textContent = t; if (x.hasAttribute('data-text')) x.setAttribute('data-text', t); });
        $$('.rfx-prize', g).forEach(function (x) { x.innerHTML = ''; x.appendChild(document.createTextNode('🎁 ' + p)); if (n > 1) { var e = document.createElement('em'); e.textContent = ' + ' + NXA.fa(n - 1) + ' جایزه'; x.appendChild(e); } });
        $$('.rfx-emoji', g).forEach(function (x) { x.textContent = em; });
        var ac = document.querySelector('[name=box_accent]');
        if (ac && /^#[0-9a-f]{6}$/i.test(ac.value)) { var v = ac.value, rgb = [1, 3, 5].map(function (i) { return parseInt(v.substr(i, 2), 16); }).join(','); $$('.rfx', g).forEach(function (x) { x.style.setProperty('--nxp', v); x.style.setProperty('--nxp-rgb', rgb); x.style.setProperty('--nxp-on', (function (c) { var l = c.split(',').map(function (n) { n /= 255; return n <= .03928 ? n / 12.92 : Math.pow((n + .055) / 1.055, 2.4); }); return .2126 * l[0] + .7152 * l[1] + .0722 * l[2] > .4 ? '#0b0d12' : '#fff'; })(rgb)); }); }
        var img = document.querySelector('[name=image]'), url = img && img.closest('[data-image-field]') ? (img.closest('[data-image-field]').querySelector('.img-pick img') || {}).src : '';
        $$('.rfx-art', g).forEach(function (a) {
            var has = img && img.value && url, cur = a.querySelector('img');
            if (has) { if (!cur) { a.querySelectorAll('.rfx-emoji').forEach(function (x) { x.remove(); }); cur = document.createElement('img'); cur.alt = ''; a.appendChild(cur); } cur.src = url; }
            else if (cur) { cur.remove(); var s = document.createElement('span'); s.className = 'rfx-emoji'; s.textContent = em; a.appendChild(s); }
        });
    }
    document.getElementById('rfForm').addEventListener('input', live);
    document.getElementById('rfForm').addEventListener('change', live);
    document.getElementById('rfForm').addEventListener('click', function (e) { if (e.target.closest('.ip, [data-clear]')) setTimeout(live, 60); });
    window.addEventListener('focus', function () { setTimeout(live, 200); });
    live();
});
</script>
<script>window.NX_SHOP = <?= json_encode(['cats' => rows('SELECT id, name FROM shop_categories ORDER BY sort_order, id'), 'products' => rows("SELECT id, title FROM products WHERE status = 'active' ORDER BY id DESC LIMIT 300")], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<?php adm_foot(['discount-editor.js']);
