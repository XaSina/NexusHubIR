<?php
require __DIR__ . '/inc/boot.php';
require_cap('promos');

$edit = (int) ($_GET['edit'] ?? 0);
$P = $edit ? row('SELECT * FROM promo_codes WHERE id = ?', [$edit]) : null;
$errors = [];

if (is_post()) {
    csrf_check();
    $act = $_POST['act'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    if ($act === 'save') {
        $code = strtoupper(preg_replace('~[^A-Za-z0-9_-]~', '', (string) en_num((string) ($_POST['code'] ?? ''))));
        $d = discount_norm(json_decode((string) ($_POST['disc'] ?? ''), true));
        if (strlen($code) < 3) $errors[] = 'کد باید حداقل ۳ حرف یا عدد انگلیسی باشه.';
        if (!$d) $errors[] = 'درصد تخفیف رو مشخص کن.';
        if ($code && val('SELECT id FROM promo_codes WHERE code = ? AND id <> ?', [$code, $id])) $errors[] = 'این کد قبلاً ساخته شده.';
        if (!$errors) {
            $row = ['code' => $code, 'title' => mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 120) ?: null, 'pct' => $d['pct'], 'max_off' => $d['max'], 'min_total' => $d['min'],
                'scope' => $d['scope'], 'scope_ids' => implode(',', $d['ids']), 'max_uses' => max(0, (int) en_num((string) ($_POST['max_uses'] ?? 0))),
                'starts_at' => trim((string) ($_POST['starts_at'] ?? '')) !== '' ? (jalali_to_datetime((string) $_POST['starts_at']) ?: null) : null,
                'expires_at' => trim((string) ($_POST['expires_at'] ?? '')) !== '' ? (jalali_to_datetime((string) $_POST['expires_at']) ?: null) : null,
                'active' => !empty($_POST['active']) ? 1 : 0, 'box_on' => !empty($_POST['box_on']) ? 1 : 0,
                'box_places' => implode(',', array_intersect((array) ($_POST['box_places'] ?? []), array_keys(shop_banner_places()))),
                'box_theme' => array_key_exists($_POST['box_theme'] ?? '', ad_themes()) ? $_POST['box_theme'] : 'neon', 'box_accent' => valid_hex((string) ($_POST['box_accent'] ?? ''), '') ?: null];
            if ($id) { q('UPDATE promo_codes SET ' . implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($row))) . ' WHERE id = ?', array_merge(array_values($row), [$id])); flash('کد ذخیره شد ✅'); }
            else { insert('promo_codes', $row + ['created_at' => now()]); flash('کد تخفیف ساخته شد 🏷️'); }
            redirect('admin/promos.php');
        }
        $P = ($P ?: []) + $_POST;
    } elseif ($act === 'toggle') {
        q('UPDATE promo_codes SET active = 1 - active WHERE id = ?', [$id]); redirect('admin/promos.php');
    } elseif ($act === 'delete') {
        q('DELETE FROM promo_uses WHERE promo_id = ?', [$id]); q('DELETE FROM promo_codes WHERE id = ?', [$id]); flash('کد حذف شد.'); redirect('admin/promos.php');
    } elseif ($act === 'gift') {   // personal codes for chosen members → their notifications + "my prizes"
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['members'] ?? [])))));
        $d = discount_norm(json_decode((string) ($_POST['gdisc'] ?? ''), true));
        $title = mb_substr(trim((string) ($_POST['gtitle'] ?? '')), 0, 120) ?: ($d ? fa_num((string) (float) $d['pct']) . '٪ تخفیف هدیه' : '');
        $msg = trim((string) ($_POST['gmsg'] ?? ''));
        if (!$ids) $errors[] = 'حداقل یک نفر رو انتخاب کن.';
        if (!$d) $errors[] = 'درصد تخفیف رو مشخص کن.';
        if (!$errors) {
            foreach ($ids as $mid) if (row('SELECT id FROM members WHERE id = ?', [$mid])) { $pid = prize_create($mid, 'gift', 0, $title, '🏷️', $d); if ($msg !== '') prize_message($pid, $msg); }
            flash('🎁 کد تخفیف برای ' . fa_num((string) count($ids)) . ' نفر فرستاده شد؛ توی اعلان‌ها و «جایزه‌های من» می‌بینن.');
            redirect('admin/promos.php#buyers');
        }
    }
}

$list = rows('SELECT * FROM promo_codes ORDER BY active DESC, id DESC');
$days = in_array((int) ($_GET['d'] ?? 0), [0, 30, 90, 365], true) ? (int) ($_GET['d'] ?? 0) : 0;
$buyers = top_buyers(30, $days);
$P = $P ?: ['id' => 0, 'code' => '', 'title' => '', 'pct' => 10, 'max_off' => 0, 'min_total' => 0, 'scope' => 'all', 'scope_ids' => '', 'max_uses' => 100, 'starts_at' => null, 'expires_at' => null,
    'active' => 1, 'box_on' => 0, 'box_places' => 'home_top', 'box_theme' => 'neon', 'box_accent' => ''];
$pd = ['pct' => (float) $P['pct'], 'max' => (int) $P['max_off'], 'min' => (int) $P['min_total'], 'scope' => (string) $P['scope'], 'ids' => array_values(array_filter(array_map('intval', explode(',', (string) $P['scope_ids'])))), 'days' => 0];
$places = array_filter(explode(',', (string) $P['box_places']));
$shopData = ['cats' => rows('SELECT id, name FROM shop_categories ORDER BY sort_order, id'), 'products' => rows("SELECT id, title FROM products WHERE status = 'active' ORDER BY id DESC LIMIT 300")];
adm_head('کدهای تخفیف', 'promos');
?>
<div class="rfl-head">
    <div class="rfl-head-t"><span class="rfl-head-ic">🏷️</span><div><h2>کدهای تخفیف</h2><small><?= fa_num((string) count(array_filter($list, fn($x) => $x['active']))) ?> کد فعال · کاربرها توی سبد خرید واردش می‌کنن · هر نفر یک بار</small></div></div>
    <a class="rfl-new" href="#pmForm"><span><?= adm_icon('plus', 18) ?></span> کد جدید</a>
</div>
<?php foreach ($errors as $er): ?><div class="alert alert-warn"><?= e($er) ?></div><?php endforeach; ?>

<?php if ($list): ?>
<div class="pmx-grid">
    <?php foreach ($list as $i => $p): $prob = promo_problem($p, null); $full = (int) $p['max_uses'] ? min(100, round($p['uses'] / max(1, $p['max_uses']) * 100)) : 0; ?>
    <article class="pmx <?= $prob ? 'is-off' : '' ?>" style="--i:<?= $i ?>">
        <div class="pmx-top">
            <button type="button" class="pmx-chip" data-copy="<?= e($p['code']) ?>" title="کپی"><code dir="ltr"><?= e($p['code']) ?></code> 📋</button>
            <span class="badge <?= $prob ? 'b-muted' : 'b-ok' ?>"><?= $prob ? e(rtrim($prob, '.')) : 'فعال' ?></span>
        </div>
        <b class="pmx-title"><?= e($p['title'] ?: discount_text(promo_disc($p))) ?></b>
        <?php if ($p['title']): ?><small class="pmx-sub"><?= e(discount_text(promo_disc($p))) ?></small><?php endif; ?>
        <div class="pmx-use"><span class="rfl-bar"><i style="width:<?= (int) $p['max_uses'] ? $full : 0 ?>%"></i></span><small>👥 <?= fa_num((string) $p['uses']) ?><?= (int) $p['max_uses'] ? ' از ' . fa_num((string) $p['max_uses']) : ' نفر (بدون سقف)' ?></small></div>
        <div class="rfl-meta">
            <?php if ($p['expires_at']): ?><span>⏳ تا <?= e(jdate('j F — H:i', strtotime($p['expires_at']))) ?></span><?php endif; ?>
            <?php if ($p['box_on']): ?><span>📣 کادر روی سایت</span><?php endif; ?>
        </div>
        <div class="rfl-act">
            <a class="rfl-go" href="?edit=<?= (int) $p['id'] ?>#pmForm"><?= adm_icon('edit', 15) ?> ویرایش</a>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="toggle"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="rfl-ib rfl-onoff<?= $p['active'] ? ' is-on' : '' ?>" title="<?= $p['active'] ? 'غیرفعال کن' : 'فعال کن' ?>"><span class="rfl-sw"><i></i></span></button></form>
            <form method="post" data-confirm="این کد حذف بشه؟"><?= csrf_field() ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="rfl-ib rfl-del" title="حذف"><?= adm_icon('trash', 16) ?></button></form>
        </div>
    </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="post" class="card pmx-form" id="pmForm"><?= csrf_field() ?><input type="hidden" name="act" value="save"><input type="hidden" name="id" value="<?= (int) $P['id'] ?>">
    <div class="card-head"><h3><?= $P['id'] ? '✏️ ویرایش کد ' . e($P['code']) : '➕ ساخت کد تخفیف همگانی' ?></h3><?php if ($P['id']): ?><a class="abtn abtn-sm" href="promos.php">کد جدید</a><?php endif; ?></div>
    <div class="pmx-fgrid">
        <label class="af"><span class="af-label">کد (انگلیسی و عدد)</span><span class="pmx-codein"><input name="code" id="pmCode" value="<?= e((string) $P['code']) ?>" dir="ltr" maxlength="30" placeholder="NEXUS20" required><button type="button" class="abtn abtn-sm" id="pmGen">🎲 بساز</button></span></label>
        <?= f_text('title', 'عنوان (اختیاری — روی کادر سایت)', (string) $P['title'], ['placeholder' => 'مثلاً تخفیف یلدایی']) ?>
        <div>
            <?= f_text('max_uses', 'سقف تعداد استفاده', (string) (int) $P['max_uses'], ['type' => 'number', 'min' => 0, 'hint' => '۰ = بدون سقف · هر نفر یک بار · به خریدار نشون داده نمی‌شه']) ?>
            <div class="rf-quick" data-for="max_uses"><?php foreach ([0 => 'بدون سقف', 10 => '۱۰', 50 => '۵۰', 100 => '۱۰۰', 500 => '۵۰۰'] as $v => $t): ?><button type="button" data-v="<?= $v ?>"><?= $t ?></button><?php endforeach; ?></div>
        </div>
        <?= f_jdate('starts_at', 'شروع (اختیاری)', $P['starts_at'] ?: null, 'خالی = از همین الان') ?>
        <?= f_jdate('expires_at', 'پایان (اختیاری)', $P['expires_at'] ?: null, 'خالی = بدون تاریخ انقضا') ?>
        <?= f_switch('active', 'فعال باشه', (bool) $P['active']) ?>
    </div>
    <div class="pmx-dsc"><input type="hidden" name="disc" id="pmDisc" value="<?= e(json_encode($pd, JSON_UNESCAPED_UNICODE)) ?>"></div>
    <div class="pmx-box">
        <?= f_switch('box_on', '📣 به شکل کادر تبلیغ روی سایت هم نشون داده بشه (با دکمه‌ی کپی)', (bool) $P['box_on']) ?>
        <div class="rf-boxrow">
            <label class="af"><span class="af-label">کجای سایت؟</span><select name="box_places[]" multiple data-multi data-placeholder="هیچ جایی انتخاب نشده"><?php foreach (shop_banner_places() as $k => $nm): ?><option value="<?= $k ?>" <?= in_array($k, $places, true) ? 'selected' : '' ?>><?= e($nm) ?></option><?php endforeach; ?></select></label>
            <?= f_color('box_accent', 'رنگ کادر', (string) ($P['box_accent'] ?: setting('color_primary', '#ff1f3d'))) ?>
        </div>
        <div class="af-label pmx-glabel">🎨 تم کادر — روی هر کدوم بزنی همون انتخاب می‌شه</div>
        <div class="theme-gallery rf-gallery" id="pmGallery">
            <?php $sample = ['code' => $P['code'] ?: 'NEXUS20', 'title' => $P['title'] ?: 'کد تخفیف ویژه'] + $P;
            foreach (ad_themes() as $k => $t): ?>
                <label class="theme-opt">
                    <input type="radio" name="box_theme" value="<?= e($k) ?>" <?= (string) $P['box_theme'] === $k ? 'checked' : '' ?>>
                    <?= promo_box_html(['box_theme' => $k] + $sample, true) ?>
                    <div class="t-name"><b><?= e($t['name']) ?></b><small><?= e($t['desc']) ?></small></div>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
    <input type="hidden" name="box_places[]" value="">
    <button class="rf-save pc-save" type="submit"><span class="rf-save-check"><?= icon('check', 18) ?></span><span><?= $P['id'] ? 'ذخیره‌ی کد' : 'ساخت کد' ?></span></button>
</form>

<form method="post" class="card pmx-buyers" id="buyers"><?= csrf_field() ?><input type="hidden" name="act" value="gift">
    <div class="card-head"><h3>🏆 بیشترین خریدها</h3>
        <div class="pzx-tabs" style="margin:0"><?php foreach ([0 => 'همیشه', 30 => '۳۰ روز', 90 => '۹۰ روز', 365 => '۱ سال'] as $v => $t): ?><a class="<?= $days === $v ? 'on' : '' ?>" href="?d=<?= $v ?>#buyers"><?= $t ?></a><?php endforeach; ?></div></div>
    <?php if (!$buyers): ?><div class="empty-state"><span>🛍️</span>هنوز خرید تأییدشده‌ای نیست.</div><?php else: ?>
    <div class="pmx-blist">
        <label class="pmx-ball"><input type="checkbox" id="pmAll"> انتخاب همه</label>
        <?php foreach ($buyers as $i => $bu): ?>
        <label class="pmx-b">
            <input type="checkbox" name="members[]" value="<?= (int) $bu['id'] ?>">
            <span class="pmx-rank"><?= ['🥇', '🥈', '🥉'][$i] ?? fa_num((string) ($i + 1)) ?></span>
            <span class="pmx-bwho"><b><?= e($bu['first_name'] . ' ' . $bu['last_name']) ?></b><span class="pzx-meta"><span dir="ltr"><?= e($bu['phone']) ?></span><span dir="ltr"><?= e($bu['member_code']) ?></span></span></span>
            <span class="pmx-bnum"><b><?= money((int) $bu['spent']) ?></b><small><?= fa_num((string) $bu['orders']) ?> خرید · <?= e(jago($bu['last_at'])) ?></small></span>
        </label>
        <?php endforeach; ?>
    </div>
    <div class="pmx-send">
        <b>🎁 فرستادن کد تخفیف شخصی به انتخاب‌شده‌ها</b>
        <small>برای هر نفر یه کد یک‌بار مصرف ساخته می‌شه و توی اعلان‌ها و «جایزه‌های من» کپی می‌کنه؛ موقع خرید هم خودکار پیشنهاد می‌شه.</small>
        <div class="grid g2"><?= f_text('gtitle', 'عنوان', '', ['placeholder' => 'مثلاً هدیه‌ی مشتری ویژه']) ?><?= f_text('gmsg', 'پیام همراه (اختیاری)', '', ['placeholder' => 'ممنون که همراه ما هستی 💛']) ?></div>
        <div class="pmx-gdsc"><input type="hidden" name="gdisc" id="pmGdisc" value="<?= e(json_encode(['pct' => 15, 'min' => 0, 'max' => 0, 'scope' => 'all', 'ids' => [], 'days' => 7])) ?>"></div>
        <button class="rf-save pc-save" type="submit"><span class="rf-save-check">🎁</span><span>فرستادن کد</span></button>
    </div>
    <?php endif; ?>
</form>
<script>window.NX_SHOP = <?= json_encode($shopData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var q = function (s) { return document.querySelector(s); };
    if (window.NXDiscount) {
        var a = q('.pmx-dsc'); NXDiscount(a, q('#pmDisc'), a);
        var g = q('.pmx-gdsc'); if (g) NXDiscount(g, q('#pmGdisc'), g);
        [].forEach.call(document.querySelectorAll('.pmx-dsc [data-f=days]'), function (i) { i.closest('label').style.display = 'none'; });   // the code's own dates are used instead
    }
    q('#pmGen').addEventListener('click', function () { var s = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789', c = 'NX'; for (var i = 0; i < 6; i++) c += s[Math.floor(Math.random() * s.length)]; q('#pmCode').value = c; });
    [].forEach.call(document.querySelectorAll('.rf-quick[data-for]'), function (qq) {
        var inp = document.querySelector('[name="' + qq.dataset.for + '"]'); if (!inp) return;
        var sy = function () { [].forEach.call(qq.querySelectorAll('button'), function (b) { b.classList.toggle('on', b.dataset.v === String(parseInt(inp.value, 10))); }); };
        qq.addEventListener('click', function (e) { var b = e.target.closest('[data-v]'); if (b) { inp.value = b.dataset.v; sy(); } }); inp.addEventListener('input', sy); sy();
    });
    // live previews in the theme gallery (title, code, percent and colour follow the form)
    var gal = q('#pmGallery'), form = q('#pmForm');
    function live() {
        if (!gal) return;
        var t = (q('[name=title]').value || 'کد تخفیف ویژه').trim(), c = (q('#pmCode').value || 'NEXUS20').trim().toUpperCase();
        [].forEach.call(gal.querySelectorAll('.rfx-title'), function (x) { x.textContent = t; if (x.hasAttribute('data-text')) x.setAttribute('data-text', t); });
        [].forEach.call(gal.querySelectorAll('.pmx-code code'), function (x) { x.textContent = c; });
        var pct = q('.pmx-dsc [data-f=pct]'); if (pct && pct.value) [].forEach.call(gal.querySelectorAll('.rfx-prize'), function (x) { x.textContent = x.textContent.replace(/[\d۰-۹.]+\s*٪|[\d۰-۹.]+\s*%/, (window.NXA && NXA.fa ? NXA.fa(pct.value) : pct.value) + '٪'); });
        var ac = q('[name=box_accent]');
        if (ac && /^#[0-9a-f]{6}$/i.test(ac.value)) { var v = ac.value, rgb = [1, 3, 5].map(function (i) { return parseInt(v.substr(i, 2), 16); }).join(','); [].forEach.call(gal.querySelectorAll('.rfx'), function (x) { x.style.setProperty('--nxp', v); x.style.setProperty('--nxp-rgb', rgb); x.style.setProperty('--nxp-on', (function (c) { var l = c.split(',').map(function (n) { n /= 255; return n <= .03928 ? n / 12.92 : Math.pow((n + .055) / 1.055, 2.4); }); return .2126 * l[0] + .7152 * l[1] + .0722 * l[2] > .4 ? '#0b0d12' : '#fff'; })(rgb)); }); }
    }
    if (form) { form.addEventListener('input', live); form.addEventListener('change', live); }
    var all = q('#pmAll'); if (all) all.addEventListener('change', function () { [].forEach.call(document.querySelectorAll('[name="members[]"]'), function (c) { c.checked = all.checked; }); });
});
</script>
<?php adm_foot(['discount-editor.js']);
