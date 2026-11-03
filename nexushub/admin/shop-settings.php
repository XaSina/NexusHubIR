<?php
/**
 * فروشگاه — تنظیمات (پرداخت، قیمت دلار/تون، منو، بنر، اعلان‌ها)
 */
require __DIR__ . '/inc/boot.php';
require_cap('*');

$keys = ['shop_enabled', 'shop_title', 'shop_tagline', 'shop_notice', 'shop_terms', 'shop_support_on',
    'shop_card_number', 'shop_card_name', 'shop_card_bank', 'shop_pay_minutes',
    'shop_usd_mode', 'shop_usd_rate', 'shop_ton_mode', 'shop_ton_usd', 'shop_margin', 'shop_round',
    'shop_in_header', 'shop_in_mobile', 'shop_in_footer', 'shop_menu_label',
    'shop_banner_on', 'shop_banner_title', 'shop_banner_text', 'shop_banner_btn', 'shop_banner_emoji', 'shop_banner_badge', 'shop_banner_style', 'shop_banner_accent', 'shop_banner_image',
    'shop_tg_admin_chat'];

if (is_post()) {
    csrf_check();
    $save = [];
    foreach ($keys as $k) if (isset($_POST[$k])) $save[$k] = trim((string) $_POST[$k]);
    $card = preg_replace('~\D~', '', en_num($save['shop_card_number'] ?? ''));
    if ($card !== '' && strlen($card) !== 16) { flash('شماره کارت باید ۱۶ رقم باشد؛ تغییر نکرد.', 'warn'); unset($save['shop_card_number']); }
    else $save['shop_card_number'] = $card;
    foreach (['shop_usd_rate', 'shop_ton_usd', 'shop_margin'] as $k) if (isset($save[$k])) $save[$k] = (string) (float) str_replace(['٬', ','], '', en_num($save[$k]));
    $save['shop_round'] = (string) max(1, (int) en_num($save['shop_round'] ?? '1000'));
    $save['shop_pay_minutes'] = (string) max(10, min(1440, (int) en_num($save['shop_pay_minutes'] ?? '60')));
    $save['shop_usd_mode'] = ($save['shop_usd_mode'] ?? '') === 'auto' ? 'auto' : 'manual';
    $save['shop_ton_mode'] = ($save['shop_ton_mode'] ?? '') === 'manual' ? 'manual' : 'auto';
    if (!array_key_exists($save['shop_banner_style'] ?? '', ad_themes())) $save['shop_banner_style'] = 'flow';
    $save['shop_banner_accent'] = ($save['shop_banner_accent'] ?? '') !== '' ? valid_hex($save['shop_banner_accent'], '') : '';
    $save['shop_banner_places'] = implode(',', array_values(array_intersect((array) ($_POST['places'] ?? []), array_keys(shop_banner_places()))));
    save_settings($save);
    // the home banner section follows its switch
    flash('تنظیمات فروشگاه ذخیره شد ✅');
    redirect('admin/shop-settings.php' . (!empty($_POST['_tab']) ? '?tab=' . urlencode((string) $_POST['_tab']) : ''));
}

$s = settings_all();
$r = shop_rates();
$places = array_filter(array_map('trim', explode(',', (string) $s['shop_banner_places'])));
$homeOn = in_array('shop', array_column(array_filter(setting_json('home_sections') ?: [], fn($x) => !empty($x['on'])), 'key'), true);
adm_head('تنظیمات فروشگاه', 'shop-settings');
?>
<form method="post" id="shopSet">
<?= csrf_field() ?>
<input type="hidden" name="_tab" id="tabKeep" value="">
<div class="tabs" data-tabs>
    <button type="button" data-tab="general">🛍️ عمومی</button>
    <button type="button" data-tab="pay">💳 پرداخت</button>
    <button type="button" data-tab="price">💎 قیمت دلار و تون</button>
    <button type="button" data-tab="menu">📢 منو و بنر</button>
    <button type="button" data-tab="notify">🔔 اعلان‌ها</button>
</div>

<div data-pane="general">
    <div class="grid g2">
        <div class="card">
            <div class="card-head"><h3>🛍️ فروشگاه</h3><a class="abtn abtn-ghost abtn-sm" href="<?= e(url('shop')) ?>" target="_blank"><?= icon('external', 15) ?> مشاهده</a></div>
            <?= f_switch('shop_enabled', 'فروشگاه فعال باشد', setting_on('shop_enabled'), 'خاموش = صفحه فروشگاه، لینک‌ها و بنرها از سایت حذف می‌شوند') ?>
            <?= f_text('shop_title', 'نام فروشگاه', $s['shop_title']) ?>
            <?= f_textarea('shop_tagline', 'شعار / توضیح بالای فروشگاه', $s['shop_tagline'], ['rows' => 2]) ?>
            <?= f_switch('shop_support_on', '💬 پشتیبانی (تیکت) فعال باشد', setting_on('shop_support_on')) ?>
        </div>
        <div class="card">
            <div class="card-head"><h3>📋 متن‌ها</h3></div>
            <?= f_textarea('shop_notice', 'پیام صفحه پرداخت (مثل ساعات کاری / زمان تحویل)', $s['shop_notice'], ['rows' => 3]) ?>
            <?= f_textarea('shop_terms', 'قوانین فروشگاه (در صفحه تکمیل خرید)', $s['shop_terms'], ['rows' => 6]) ?>
        </div>
    </div>
</div>

<div data-pane="pay">
    <div class="grid g2">
        <div class="card">
            <div class="card-head"><h3>💳 کارت مقصد (کارت‌به‌کارت)</h3></div>
            <?= f_text('shop_card_number', 'شماره کارت (۱۶ رقم)', $s['shop_card_number'], ['ltr' => true, 'attrs' => 'inputmode="numeric" autocomplete="off"']) ?>
            <?= f_text('shop_card_name', 'به نام', $s['shop_card_name'], ['attrs' => 'autocomplete="off"']) ?>
            <?= f_text('shop_card_bank', 'نام بانک', $s['shop_card_bank']) ?>
            <?= f_text('shop_pay_minutes', 'مهلت پرداخت بعد از ثبت سفارش (دقیقه)', $s['shop_pay_minutes'], ['type' => 'number', 'ltr' => true, 'hint' => 'بعد از این مدت، سفارش بدون فیش خودکار «منقضی» می‌شود']) ?>
        </div>
        <div class="card">
            <div class="card-head"><h3>👁️ پیش‌نمایش کارت در سایت</h3></div>
            <?php $cd = preg_replace('~\D~', '', (string) $s['shop_card_number']); ?>
            <div class="bank-prev">
                <div class="bp-top"><b><?= e($s['shop_card_bank']) ?></b><span></span></div>
                <div class="bp-num ltr"><?= e(trim(chunk_split($cd, 4, ' '))) ?></div>
                <div class="bp-name"><small>به نام</small><b><?= e($s['shop_card_name']) ?></b></div>
            </div>
            <p class="muted small mt">مشتری کنار کارت، دکمه «کپی شماره کارت» و «کپی مبلغ» دارد و بعد از واریز، تصویر فیش را آپلود می‌کند. فیش‌ها در پوشه خصوصی ذخیره می‌شوند و فقط خود مشتری و مدیران می‌توانند ببینند.</p>
        </div>
    </div>
</div>

<div data-pane="price">
    <div class="grid g2">
        <div class="card">
            <div class="card-head"><h3>💵 قیمت دلار (به تومان)</h3></div>
            <div class="opt-grid">
                <label class="opt"><input type="radio" name="shop_usd_mode" value="manual" <?= $s['shop_usd_mode'] !== 'auto' ? 'checked' : '' ?>><b>دستی</b><small>خودتان قیمت دلار را وارد می‌کنید (پیشنهادی)</small></label>
                <label class="opt"><input type="radio" name="shop_usd_mode" value="auto" <?= $s['shop_usd_mode'] === 'auto' ? 'checked' : '' ?>><b>خودکار (تتر نوبیتکس)</b><small>رایگان؛ اگر در دسترس نبود از قیمت دستی استفاده می‌شود</small></label>
            </div>
            <?= f_text('shop_usd_rate', 'قیمت هر دلار (تومان)', $s['shop_usd_rate'] === '0' ? '' : $s['shop_usd_rate'], ['ltr' => true, 'attrs' => 'inputmode="numeric" id="usdIn"', 'placeholder' => 'مثلاً 98000']) ?>
            <hr class="sep">
            <?= f_text('shop_margin', 'درصد سود پیش‌فرض روی قیمت‌های دلاری و تونی', $s['shop_margin'], ['ltr' => true, 'attrs' => 'inputmode="decimal" id="marginIn"', 'hint' => 'مثلاً 10 یعنی ۱۰٪ بالاتر از قیمت محاسبه‌شده. هر محصول می‌تواند درصد جداگانه داشته باشد.']) ?>
            <?= f_select('shop_round', 'گرد کردن قیمت نهایی (رو به بالا)', ['1' => 'بدون گرد کردن', '100' => '۱۰۰ تومان', '500' => '۵۰۰ تومان', '1000' => '۱٬۰۰۰ تومان', '5000' => '۵٬۰۰۰ تومان', '10000' => '۱۰٬۰۰۰ تومان'], $s['shop_round'], ['attrs' => 'id="roundIn"']) ?>
        </div>
        <div class="card">
            <div class="card-head"><h3>💎 قیمت TON (به دلار)</h3></div>
            <div class="opt-grid">
                <label class="opt"><input type="radio" name="shop_ton_mode" value="auto" <?= $s['shop_ton_mode'] !== 'manual' ? 'checked' : '' ?>><b>لحظه‌ای (API رایگان)</b><small>نوبیتکس ← CoinGecko ← OKX ← TonAPI؛ هر ۵ دقیقه به‌روز می‌شود</small></label>
                <label class="opt"><input type="radio" name="shop_ton_mode" value="manual" <?= $s['shop_ton_mode'] === 'manual' ? 'checked' : '' ?>><b>دستی</b><small>قیمت ثابتی که خودتان وارد می‌کنید</small></label>
            </div>
            <?= f_text('shop_ton_usd', 'قیمت دستی / پشتیبان هر TON (دلار)', $s['shop_ton_usd'] === '0' ? '' : $s['shop_ton_usd'], ['ltr' => true, 'attrs' => 'inputmode="decimal" id="tonIn"', 'placeholder' => 'مثلاً 3.2', 'hint' => 'در حالت لحظه‌ای، فقط وقتی همه منابع قطع باشند استفاده می‌شود']) ?>
            <div class="rate-live" id="rateLive">
                <div><span>💎 TON فعلی</span><b class="ltr" id="rlTon"><?= $r['ton'] ? '$' . number_format($r['ton'], 3) : '—' ?></b><small id="rlTonSrc"><?= e($r['ton_src']) ?><?= $r['ton_at'] ? ' · ' . jago(date('Y-m-d H:i:s', $r['ton_at'])) : '' ?></small></div>
                <div><span>💵 دلار فعلی</span><b id="rlUsd"><?= $r['usd'] ? money((int) $r['usd']) . ' ت' : '—' ?></b><small id="rlUsdSrc"><?= e($r['usd_src']) ?></small></div>
                <div><span>🧮 هر ۱ TON</span><b id="rlTonT"><?= $r['ton'] && $r['usd'] ? money((int) round($r['ton'] * $r['usd'])) . ' ت' : '—' ?></b><small>بدون سود</small></div>
            </div>
            <button type="button" class="abtn abtn-ghost abtn-sm" id="rateTest"><?= icon('bolt', 15) ?> تست اتصال به منابع قیمت</button>
            <div class="rate-test" id="rateOut" hidden></div>
        </div>
    </div>
    <div class="card mt">
        <div class="card-head"><h3>🧮 ماشین‌حساب قیمت</h3><span class="muted small">قیمت نهایی را قبل از تعریف بسته ببینید</span></div>
        <div class="calc-row">
            <input id="cAmt" dir="ltr" inputmode="decimal" placeholder="مقدار" value="12">
            <select id="cType"><option value="usd">دلار ($)</option><option value="ton">TON</option></select>
            <span>=</span><b id="cOut">—</b><small class="muted">تومان (با سود و گرد کردن)</small>
        </div>
    </div>
</div>

<div data-pane="menu">
    <div class="grid g2">
        <div class="card">
            <div class="card-head"><h3>🧭 دکمه فروشگاه در منوها</h3></div>
            <?= f_text('shop_menu_label', 'متن دکمه', $s['shop_menu_label']) ?>
            <?= f_switch('shop_in_header', '🖥️ منوی بالای سایت (کامپیوتر)', setting_on('shop_in_header')) ?>
            <?= f_switch('shop_in_mobile', '📱 منوی موبایل (دکمه ☰)', setting_on('shop_in_mobile')) ?>
            <?= f_switch('shop_in_footer', '⬇️ فوتر', setting_on('shop_in_footer')) ?>
            <p class="muted small">دکمه «سبد خرید» و «اعلان‌ها» خودکار در هدر ظاهر می‌شوند (وقتی سبد پر است / کاربر وارد شده).</p>
        </div>
        <div class="card">
            <div class="card-head"><h3>📢 بنر تبلیغ فروشگاه</h3></div>
            <?= f_switch('shop_banner_on', 'نمایش بنر فروشگاه', setting_on('shop_banner_on')) ?>
            <div class="grid g2">
                <?= f_text('shop_banner_emoji', 'ایموجی', $s['shop_banner_emoji']) ?>
                <?= f_text('shop_banner_badge', 'روبان گوشه (اختیاری)', $s['shop_banner_badge']) ?>
            </div>
            <?= f_text('shop_banner_title', 'عنوان', $s['shop_banner_title'], ['attrs' => 'data-bp="title"']) ?>
            <?= f_textarea('shop_banner_text', 'متن', $s['shop_banner_text'], ['rows' => 2]) ?>
            <div class="grid g2">
                <?= f_text('shop_banner_btn', 'متن دکمه', $s['shop_banner_btn']) ?>
                <?= f_color('shop_banner_accent', 'رنگ (خالی = رنگ تم)', $s['shop_banner_accent'] ?: (string) setting('color_primary', '#ff1f3d')) ?>
            </div>
            <div class="logo-pick"><?= f_image('shop_banner_image', 'تصویر کنار متن (اختیاری)', $s['shop_banner_image']) ?></div>
        </div>
    </div>
    <div class="card mt">
        <div class="card-head"><h3>📍 کجا نمایش داده شود؟</h3></div>
        <div class="chk-grid">
            <label class="chk-row"><input type="checkbox" disabled <?= $homeOn ? 'checked' : '' ?>> 🏠 صفحه اصلی — بخش فروشگاه (بنر + محصولات ویژه) <a href="appearance.php" class="small">← ترتیب در «ظاهر سایت»</a></label>
            <?php foreach (shop_banner_places() as $k => $n): ?>
                <label class="chk-row"><input type="checkbox" name="places[]" value="<?= $k ?>" <?= in_array($k, $places, true) ? 'checked' : '' ?>> <?= e($n) ?></label>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card mt">
        <div class="card-head"><h3>🎨 تم بنر</h3><span class="muted small">همان تم‌های بخش تبلیغات</span></div>
        <div class="theme-gallery">
            <?php foreach (ad_themes() as $k => $t): ?>
                <label class="theme-opt">
                    <input type="radio" name="shop_banner_style" value="<?= $k ?>" <?= $s['shop_banner_style'] === $k ? 'checked' : '' ?>>
                    <?= render_ad(['theme' => $k] + shop_banner_ad(), true) ?>
                    <span class="t-name"><b><?= e($t['name']) ?></b><small class="muted"><?= e($t['desc']) ?></small></span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div data-pane="notify">
    <div class="grid g2">
        <div class="card">
            <div class="card-head"><h3>🔔 اعلان‌های سایت</h3></div>
            <ul class="muted small" style="line-height:2.2;margin:0;padding-right:18px">
                <li>برای هر سفارش فقط <b>یک اعلان</b> ساخته می‌شود که با تغییر وضعیت به‌روز می‌شود (اعلان‌ها زیاد نمی‌شوند).</li>
                <li>اعلان سفارش‌های انجام‌شده / لغوشده <b>۲۴ ساعت بعد خودکار پاک</b> می‌شوند.</li>
                <li>کاربر در هدر سایت آیکن 🔔 دارد و با تغییر وضعیت، اعلان زنده (بدون رفرش) می‌بیند.</li>
                <li>مدیران در بالای پنل آیکن 🔔 دارند: فیش‌های جدید و پیام‌های پشتیبانی.</li>
            </ul>
        </div>
        <div class="card">
            <div class="card-head"><h3>✈️ اعلان تلگرام برای مدیر (اختیاری)</h3></div>
            <?= f_text('shop_tg_admin_chat', 'آیدی عددی چت مدیر (نه کانال!)', $s['shop_tg_admin_chat'], ['ltr' => true, 'placeholder' => 'مثلاً 123456789', 'hint' => 'با ربات تنظیم‌شده در «تنظیمات ← ربات تلگرام» برای فیش و تیکت جدید پیام می‌فرستد. آیدی عددی را از ربات @userinfobot بگیرید و یک بار به ربات خودتان /start بدهید.']) ?>
            <p class="muted small">اگر هاست شما ایرانی است و به تلگرام دسترسی ندارد، خالی بگذارید.</p>
        </div>
    </div>
</div>

<div class="sticky-save"><button class="abtn abtn-primary" type="submit"><?= icon('check', 18) ?> ذخیره تنظیمات فروشگاه</button></div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var A = window.NXA, en = function (s) { return String(s || '').replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); }).replace(/[٬,]/g, ''); };
    var tab = new URLSearchParams(location.search).get('tab');
    if (tab) { var b = document.querySelector('[data-tab="' + tab + '"]'); if (b) b.click(); }
    document.getElementById('shopSet').addEventListener('submit', function () { var a = document.querySelector('[data-tab].active'); document.getElementById('tabKeep').value = a ? a.dataset.tab : ''; });
    var R = { ton: <?= json_encode($r['ton']) ?>, usd: <?= json_encode($r['usd']) ?> };
    function calc() {
        var usd = parseFloat(en(document.getElementById('usdIn').value)) || R.usd, m = parseFloat(en(document.getElementById('marginIn').value)) || 0, rd = +document.getElementById('roundIn').value || 1;
        var a = parseFloat(en(document.getElementById('cAmt').value)) || 0, t = document.getElementById('cType').value;
        var v = t === 'usd' ? a * usd : a * (R.ton || 0) * usd;
        v = v * (1 + m / 100);
        document.getElementById('cOut').textContent = v > 0 ? A.fa(String(Math.ceil(v / rd) * rd).replace(/\B(?=(\d{3})+(?!\d))/g, '٬')) : '—';
    }
    ['usdIn', 'marginIn', 'roundIn', 'cAmt', 'cType'].forEach(function (id) { var el = document.getElementById(id); el.addEventListener('input', calc); el.addEventListener('change', calc); });
    calc();
    document.getElementById('rateTest').addEventListener('click', function () {
        var b = this, out = document.getElementById('rateOut');
        b.disabled = true; out.hidden = false; out.textContent = '⏳ در حال اتصال به منابع قیمت...';
        A.post('rates_test', {}).then(function (j) {
            b.disabled = false;
            out.innerHTML = (j.lines || []).map(function (l) { return '<div>' + l + '</div>'; }).join('');
            if (j.ton) { R.ton = j.ton; document.getElementById('rlTon').textContent = '$' + (+j.ton).toFixed(3); document.getElementById('rlTonSrc').textContent = j.ton_src; }
            if (j.usd) { R.usd = j.usd; }
            calc();
        }).catch(function () { b.disabled = false; out.textContent = 'خطا در ارتباط'; });
    });
});
</script>
<?php adm_foot(); ?>
