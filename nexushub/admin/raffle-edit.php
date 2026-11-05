<?php
require __DIR__ . '/inc/boot.php';
require_cap('*');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$r = $id ? row('SELECT * FROM raffles WHERE id = ?', [$id]) : null;
if ($id && !$r) { flash('قرعه‌کشی پیدا نشد.', 'error'); redirect('admin/raffles.php'); }
$r = $r ?: ['id' => 0, 'slug' => '', 'title' => '', 'description' => '', 'image' => '', 'mode' => 'free', 'prizes' => json_encode([['title' => '', 'emoji' => '🎁']]),
    'draw_at' => date('Y-m-d H:i:s', strtotime('+1 day')), 'min_participants' => 20, 'min_tickets' => 0, 'ticket_price' => 50000, 'max_tickets_user' => 100,
    'ticket_bonus' => 100, 'max_referrals' => 0, 'status' => 'draft', 'box_on' => 1, 'box_places' => 'home_top,sidebar,post_bottom', 'box_theme' => 'neon', 'box_accent' => '', 'result_hours' => 24];
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
        if ($t !== '') $pz[] = ['title' => $t, 'emoji' => mb_substr(trim((string) ($_POST['prize_emoji'][$i] ?? '🎁')), 0, 4) ?: '🎁'];
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
        flash($d['status'] === 'active' ? 'قرعه‌کشی فعال شد 🎰' : 'ذخیره شد ✅');
        redirect('admin/raffle-view.php?id=' . $nid);
    }
    $r = $d;
}

$prizes = json_decode((string) $r['prizes'], true) ?: [['title' => '', 'emoji' => '🎁']];
$places = array_filter(explode(',', (string) $r['box_places']));
adm_head($r['id'] ? 'ویرایش قرعه‌کشی' : 'قرعه‌کشی جدید', 'raffles');
?>
<form method="post" id="rfForm">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
<?php foreach ($errors as $er): ?><div class="alert alert-warn"><?= e($er) ?></div><?php endforeach; ?>
<?php if ($locked): ?><div class="alert alert-info">این قرعه‌کشی <?= e(raffle_status_names()[$r['status']]) ?>؛ فقط متن‌ها و نمایش قابل تغییرن.</div><?php endif; ?>
<div class="split">
    <div>
        <div class="card">
            <div class="card-head"><h3>🎰 اطلاعات قرعه‌کشی</h3></div>
            <?= f_text('title', 'عنوان', $r['title'], ['required' => true, 'placeholder' => 'مثلاً قرعه‌کشی بزرگ پریمیوم تلگرام']) ?>
            <?php if (!$r['id']): ?><?= f_text('slug', 'نامک آدرس (اختیاری، انگلیسی)', '', ['ltr' => true, 'placeholder' => 'premium-giveaway']) ?><?php endif; ?>
            <?= f_textarea('description', 'توضیحات و قوانین', (string) $r['description'], ['rows' => 4]) ?>
            <?= f_image('image', 'عکس (اختیاری)', (string) $r['image']) ?>
        </div>
        <div class="card">
            <div class="card-head"><h3>🎁 جایزه‌ها</h3><small class="muted">به ترتیب: نفر اول، دوم، …</small></div>
            <div id="pzList" class="pz-list">
                <?php foreach ($prizes as $i => $p): ?>
                <div class="pz-row"><b class="pz-n"><?= fa_num((string) ($i + 1)) ?></b><input class="pz-emoji" name="prize_emoji[]" value="<?= e($p['emoji'] ?? '🎁') ?>" maxlength="4"><input name="prize_title[]" value="<?= e($p['title'] ?? '') ?>" placeholder="مثلاً ۳ ماه پریمیوم تلگرام"><button type="button" class="abtn abtn-sm abtn-ghost pz-del">✕</button></div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="abtn tb-add" id="pzAdd"><?= adm_icon('plus', 16) ?> افزودن جایزه</button>
        </div>
        <div class="card">
            <div class="card-head"><h3>⚙️ روش شرکت</h3></div>
            <div class="rf-modes">
                <?php foreach (raffle_modes() as $k => [$nm, $ds, $ic]): ?>
                <label class="rf-mode"><input type="radio" name="mode" value="<?= $k ?>" <?= $r['mode'] === $k ? 'checked' : '' ?> <?= $locked ? 'disabled' : '' ?>><span><b><?= $ic ?> <?= e($nm) ?></b><small><?= e($ds) ?></small></span></label>
                <?php endforeach; ?>
            </div>
            <div class="grid g2">
                <?= f_text('min_participants', 'حداقل شرکت‌کننده (اگه نرسه برگزار نمی‌شه)', (string) $r['min_participants'], ['type' => 'number', 'min' => 1]) ?>
                <div data-mode="referral"><?= f_text('ticket_bonus', 'هر دعوت چند درصد به شانس پایه اضافه کنه؟', (string) (float) $r['ticket_bonus'], ['type' => 'number', 'min' => 1, 'hint' => '۱۰۰ یعنی هر دعوت = یک شانس کامل دیگه؛ ۵۰ یعنی نصف یک شانس']) ?></div>
                <div data-mode="referral"><?= f_text('max_referrals', 'حداکثر دعوتِ امتیازدار برای هر نفر', (string) $r['max_referrals'], ['type' => 'number', 'min' => 0, 'hint' => '۰ = نامحدود']) ?></div>
                <div data-mode="paid"><?= f_text('ticket_price', 'قیمت هر تیکت (تومان)', (string) $r['ticket_price'], ['type' => 'number', 'min' => 0]) ?></div>
                <div data-mode="paid"><?= f_text('max_tickets_user', 'سقف خرید هر نفر (تیکت)', (string) $r['max_tickets_user'], ['type' => 'number', 'min' => 0, 'hint' => '۰ = بدون سقف']) ?></div>
                <div data-mode="paid"><?= f_text('min_tickets', 'حداقل تیکت فروخته‌شده (اگه نرسه برگزار نمی‌شه)', (string) $r['min_tickets'], ['type' => 'number', 'min' => 0]) ?></div>
            </div>
            <p class="small muted" data-mode="paid">پرداخت کارت‌به‌کارت با همون کارتی انجام می‌شه که توی تنظیمات فروشگاه گذاشتی؛ خریدار فیش رو آپلود می‌کنه و تو توی صفحه‌ی مدیریت قرعه‌کشی تأییدش می‌کنی. شماره‌ی تیکت‌ها به ترتیب تأیید داده می‌شه.</p>
        </div>
    </div>
    <div>
        <div class="card">
            <div class="card-head"><h3>⏰ زمان و وضعیت</h3></div>
            <?= f_jdate('draw_at', 'زمان برگزاری (شمسی)', $r['draw_at'], 'سر همین ساعت گردونه روی سایت زنده می‌چرخه') ?>
            <?php if (!$locked): ?><?= f_select('status', 'وضعیت', ['draft' => 'پیش‌نویس (فقط خودت می‌بینی)', 'active' => 'فعال — مردم می‌تونن شرکت کنن', 'cancelled' => 'لغو شده'], $r['status']) ?><?php endif; ?>
        </div>
    </div>
</div>
<?php $sample = raffle_box_ad($r + ['id' => 0, 'image' => ''], ['participants' => 12, 'need' => (int) $r['min_participants'], 'sold' => 12, 'need_tickets' => (int) $r['min_tickets']]); ?>
<div class="card">
    <div class="card-head"><h3>📣 کادر تبلیغ قرعه‌کشی روی سایت</h3></div>
    <div class="grid g3">
        <?= f_switch('box_on', 'نمایش کادر روی سایت', (bool) $r['box_on'], 'مثل تبلیغ‌ها، با شمارش معکوس و تعداد شرکت‌کننده‌ها') ?>
        <label class="af"><span class="af-label">کجای سایت نمایش داده بشه؟ (چند مورد)</span>
            <select name="box_places[]" multiple data-multi data-placeholder="هیچ جایی انتخاب نشده">
                <?php foreach (shop_banner_places() as $k => $nm): ?><option value="<?= $k ?>" <?= in_array($k, $places, true) ? 'selected' : '' ?>><?= e($nm) ?></option><?php endforeach; ?>
            </select></label>
        <?= f_text('result_hours', 'بعد از قرعه‌کشی، نتیجه تا چند ساعت روی سایت بمونه؟', (string) (int) ($r['result_hours'] ?? 24), ['type' => 'number', 'min' => 0, 'max' => 720, 'hint' => 'تو این مدت کادر «قرعه‌کشی انجام شد — مشاهده‌ی برنده» نشون داده می‌شه و هر کی وارد صفحه بشه گردونه خودکار براش می‌چرخه. ۰ = بلافاصله برداشته بشه']) ?>
    </div>
    <?= f_color('box_accent', 'رنگ کادر', (string) ($r['box_accent'] ?: setting('color_primary', '#ff1f3d'))) ?>
    <div class="af"><span class="af-label">تم کادر</span></div>
    <div class="theme-gallery">
        <?php foreach (ad_themes() as $k => $t): ?>
            <label class="theme-opt">
                <input type="radio" name="box_theme" value="<?= e($k) ?>" <?= $r['box_theme'] === $k ? 'checked' : '' ?>>
                <?= render_ad(['theme' => $k, 'countdown' => 0, 'heading' => $r['title'] ?: 'قرعه‌کشی بزرگ', 'body' => '🎁 ' . (($prizes[0]['title'] ?? '') ?: 'جایزه‌ی ویژه'), 'button_text' => ''] + $sample, true) ?>
                <div class="t-name"><b><?= e($t['name']) ?></b><small><?= e($t['desc']) ?></small></div>
            </label>
        <?php endforeach; ?>
    </div>
</div>
<input type="hidden" name="box_places[]" value="">
<div class="sticky-save"><button class="abtn abtn-primary" type="submit"><?= icon('check', 18) ?> ذخیره</button><a class="abtn" href="raffles.php">بازگشت</a></div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var list = document.getElementById('pzList');
    function renum() { [].forEach.call(list.querySelectorAll('.pz-n'), function (b, i) { b.textContent = NXA.fa(i + 1); }); }
    document.getElementById('pzAdd').addEventListener('click', function () {
        var d = document.createElement('div'); d.className = 'pz-row';
        d.innerHTML = '<b class="pz-n"></b><input class="pz-emoji" name="prize_emoji[]" value="🎁" maxlength="4"><input name="prize_title[]" placeholder="عنوان جایزه"><button type="button" class="abtn abtn-sm abtn-ghost pz-del">✕</button>';
        list.appendChild(d); renum(); d.querySelector('[name="prize_title[]"]').focus();
    });
    list.addEventListener('click', function (e) { var x = e.target.closest('.pz-del'); if (x && list.children.length > 1) { x.closest('.pz-row').remove(); renum(); } });
    function mode() { var m = (document.querySelector('[name=mode]:checked') || {}).value; [].forEach.call(document.querySelectorAll('[data-mode]'), function (el) { el.hidden = el.getAttribute('data-mode') !== m; }); }
    [].forEach.call(document.querySelectorAll('[name=mode]'), function (r) { r.addEventListener('change', mode); }); mode();
});
</script>
<?php adm_foot();
