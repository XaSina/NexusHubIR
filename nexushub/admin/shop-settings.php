<?php
/**
 * فروشگاه — تنظیمات (پرداخت، قیمت دلار/تون، منو، بنر، اعلان‌ها)
 */
require __DIR__ . '/inc/boot.php';
require_cap('shop_settings');

$keys = ['shop_enabled', 'shop_title', 'shop_tagline', 'shop_notice', 'shop_terms', 'shop_support_on',
    'shop_card_number', 'shop_card_name', 'shop_card_bank', 'shop_pay_minutes',
    'shop_usd_mode', 'shop_usd_rate', 'shop_ton_mode', 'shop_ton_usd', 'shop_margin', 'shop_round',
    'shop_in_header', 'shop_in_mobile', 'shop_in_footer', 'shop_menu_label',
    'shop_banner_on', 'shop_banner_title', 'shop_banner_text', 'shop_banner_btn', 'shop_banner_emoji', 'shop_banner_badge', 'shop_banner_style', 'shop_banner_accent', 'shop_banner_image',
    'shop_tg_admin_chat', 'shop_tickers_on', 'ship_free_over', 'ship_track_url', 'ship_note', 'ship_postcode_req',
    'shop_seo_title', 'shop_seo_desc', 'shop_seo_keywords', 'shop_seo_image'];

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
    if (isset($_POST['tickers_sent'])) $save['shop_tickers'] = implode(',', array_values(array_intersect((array) ($_POST['tickers'] ?? []), array_keys(market_assets()))));
    // shipping methods (name, cost, time) + free shipping above an amount
    if (isset($_POST['ship_methods'])) {
        $ml = json_decode((string) $_POST['ship_methods'], true); $okm = [];
        if (is_array($ml)) foreach (array_slice($ml, 0, 12) as $m) {
            $nm = mb_substr(trim((string) ($m['name'] ?? '')), 0, 60); if ($nm === '') continue;
            $okm[] = ['name' => $nm, 'cost' => max(0, (int) str_replace(['٬', ','], '', en_num((string) ($m['cost'] ?? '0')))), 'days' => mb_substr(trim((string) ($m['days'] ?? '')), 0, 60)];
        }
        if ($okm) $save['ship_methods'] = json_encode($okm, JSON_UNESCAPED_UNICODE);
    }
    if (isset($save['ship_free_over'])) $save['ship_free_over'] = (string) max(0, (int) str_replace(['٬', ','], '', en_num($save['ship_free_over'])));
    if (isset($save['ship_track_url'])) $save['ship_track_url'] = safe_link($save['ship_track_url']) ?: '';
    if (isset($save['ship_note'])) $save['ship_note'] = mb_substr($save['ship_note'], 0, 300);
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
    <button type="button" data-tab="ship">🚚 ارسال</button>
    <button type="button" data-tab="seo">🔎 سئو و گوگل</button>
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
        <div class="card-head"><h3>📈 بازار لحظه‌ای در صفحه فروشگاه</h3><span class="muted small">قیمت‌ها خودکار و رایگان از نوبیتکس، CoinGecko و KuCoin گرفته می‌شوند</span></div>
        <?= f_switch('shop_tickers_on', 'نمایش بازار لحظه‌ای', setting_on('shop_tickers_on'), 'بالای قیمت TON در صفحه فروشگاه') ?>
        <input type="hidden" name="tickers_sent" value="1">
        <?php $tsel = market_selected(); $groups = ['fiat' => '💱 ارزها', 'gold' => '🪙 طلا', 'coin' => '₿ ارزهای دیجیتال']; ?>
        <div class="af mt"><span class="af-label">کدام قیمت‌ها نمایش داده شوند؟</span>
            <select name="tickers[]" multiple data-multi data-placeholder="هیچ قیمتی انتخاب نشده">
                <?php foreach ($groups as $g => $gl): ?><optgroup label="<?= e($gl) ?>"><?php foreach (market_assets() as $k => $a): if (($a[3] === 'fiat' ? 'fiat' : $a[3]) !== $g) continue; ?><option value="<?= e($k) ?>" <?= in_array($k, $tsel, true) ? 'selected' : '' ?>><?= e($a[2] . ' ' . $a[0] . ' (' . $a[1] . ')') ?></option><?php endforeach; ?></optgroup><?php endforeach; ?>
            </select>
        </div>
        <button type="button" class="abtn abtn-ghost abtn-sm mt" id="mktTest"><?= icon('bolt', 15) ?> تست منابع و نمایش قیمت‌های فعلی</button>
        <div class="rate-test" id="mktOut" hidden></div>
        <p class="muted small mt">قیمت تومانی با نرخ دلار (تتر) همین صفحه حساب می‌شود؛ برای قیمت‌های تومانی، حالت دلار را روی «خودکار» بگذارید. طلای ۱۸ عیار بر اساس انس جهانی (PAXG) محاسبه می‌شود و ممکن است کمی با بازار داخلی فرق داشته باشد.</p>
    </div>
    <div class="card mt">
        <div class="card-head"><h3>🧮 ماشین‌حساب قیمت</h3><span class="muted small">قیمت نهایی را قبل از تعریف بسته ببینید</span></div>
        <div class="calc">
            <div class="calc-in">
                <span class="calc-lbl">مقدار</span>
                <label class="calc-amt"><input id="cAmt" dir="ltr" inputmode="decimal" placeholder="0" value="12" autocomplete="off"><span id="cUnit">$</span></label>
                <div class="calc-seg" id="cSeg"><button type="button" data-v="usd" class="on">💵 دلار</button><button type="button" data-v="ton">💎 TON</button><i></i></div>
                <div class="calc-chips" id="cChips"><?php foreach ([1, 5, 10, 25, 50, 100] as $q): ?><button type="button" data-a="<?= $q ?>"><?= fa_num((string) $q) ?></button><?php endforeach; ?></div>
                <input type="hidden" id="cType" value="usd">
            </div>
            <div class="calc-out">
                <small>قیمت نهایی برای مشتری</small>
                <div class="calc-total"><b id="cOut">—</b><em>تومان</em></div>
                <ul class="calc-br">
                    <li><span>قیمت پایه</span><b id="cBase" dir="ltr">—</b></li>
                    <li><span>سود <em id="cMarP"></em></span><b id="cMar" dir="ltr">—</b></li>
                    <li><span>گرد کردن</span><b id="cRnd" dir="ltr">—</b></li>
                </ul>
            </div>
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
                <?= f_text('shop_banner_emoji', 'ایموجی', $s['shop_banner_emoji'], ['attrs' => 'data-emoji']) ?>
                <?= f_text('shop_banner_badge', 'روبان گوشه (اختیاری)', $s['shop_banner_badge']) ?>
            </div>
            <?= f_text('shop_banner_title', 'عنوان', $s['shop_banner_title'], ['attrs' => 'data-bp="title"']) ?>
            <?= f_textarea('shop_banner_text', 'متن', $s['shop_banner_text'], ['rows' => 2]) ?>
            <div class="grid g2">
                <?= f_text('shop_banner_btn', 'متن دکمه', $s['shop_banner_btn']) ?>
                <?= f_color('shop_banner_accent', 'رنگ (خالی = رنگ تم)', $s['shop_banner_accent'] ?: (string) setting('color_primary', '#ff1f3d')) ?>
            </div>
            <div class="logo-pick"><?= f_image('shop_banner_image', 'تصویر کنار متن (اختیاری)', $s['shop_banner_image'], '', preset_images('product')) ?></div>
        </div>
    </div>
    <div class="card mt">
        <div class="card-head"><h3>📍 کجا نمایش داده شود؟</h3></div>
        <label class="af"><span class="af-label">جایگاه‌های بنر (چند مورد قابل انتخاب)</span>
            <select name="places[]" multiple data-multi data-placeholder="هیچ جایگاهی انتخاب نشده">
                <?php foreach (shop_banner_places() as $k => $n): ?><option value="<?= $k ?>" <?= in_array($k, $places, true) ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?>
            </select></label>
        <div class="alert alert-info small place-note">🏠 بخش «فروشگاه» صفحه اصلی (عنوان + محصولات ویژه) <?= $homeOn ? '<b>روشن</b> است' : '<b>خاموش</b> است' ?>؛ بنر فقط وقتی بالای آن می‌آید که «صفحه اصلی — بخش فروشگاه» را در لیست بالا انتخاب کنید. روشن/خاموش و ترتیب بخش از <a href="appearance.php">ظاهر سایت ← چیدمان صفحه اصلی</a>.</div>
        <input type="hidden" name="places[]" value="">
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

<div data-pane="ship">
    <div class="grid g2">
        <div class="card">
            <div class="card-head"><h3>🚚 روش‌های ارسال</h3><button type="button" class="abtn abtn-ghost abtn-sm" id="addShipM" data-nofb><?= adm_icon('plus', 16) ?> روش جدید</button></div>
            <p class="muted small">برای محصولاتی که «نحوه‌ی تحویل»شون «ارسال پستی»ه. مشتری موقع خرید یکی رو انتخاب می‌کنه و هزینه‌اش به مبلغ اضافه می‌شه.</p>
            <input type="hidden" name="ship_methods" id="shipMIn">
            <div class="shipm-list" id="shipMList">
                <?php foreach (ship_methods() as $sm): ?>
                <div class="shipm-r"><input class="input" data-k="name" value="<?= e($sm['name']) ?>" placeholder="نام روش (مثلاً پست پیشتاز)"><input class="input" data-k="cost" value="<?= (int) $sm['cost'] ?>" dir="ltr" inputmode="numeric" placeholder="هزینه (تومان)"><input class="input" data-k="days" value="<?= e($sm['days']) ?>" placeholder="زمان (مثلاً ۲ تا ۴ روز)"><button type="button" class="adm-ib danger" data-shipdel title="حذف"><?= adm_icon('trash', 15) ?></button></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-head"><h3>⚙️ قوانین ارسال</h3></div>
            <?= f_text('ship_free_over', 'ارسال رایگان برای خرید بالای (تومان)', (int) $s['ship_free_over'] ? (string) (int) $s['ship_free_over'] : '', ['ltr' => true, 'attrs' => 'inputmode="numeric"', 'placeholder' => '۰ = خاموش', 'hint' => 'هزینه‌ی اضافه‌ی کالاهای سنگین همچنان حساب می‌شه']) ?>
            <?= f_switch('ship_postcode_req', 'کد پستی اجباری باشد', setting_on('ship_postcode_req')) ?>
            <?= f_text('ship_track_url', 'آدرس پیگیری مرسوله', (string) $s['ship_track_url'], ['ltr' => true, 'hint' => '{code} جای کد رهگیری قرار می‌گیره؛ خالی = فقط خود کد نشون داده می‌شه']) ?>
            <?= f_textarea('ship_note', 'توضیح زیر روش‌های ارسال (اختیاری)', (string) $s['ship_note'], ['rows' => 2, 'placeholder' => 'مثلاً: سفارش‌ها هر روز ساعت ۱۴ تحویل پست می‌شن']) ?>
        </div>
    </div>
</div>

<div data-pane="seo">
    <div class="grid g2">
        <div class="card">
            <div class="card-head"><h3>🔎 سئوی صفحه‌ی فروشگاه</h3></div>
            <?= f_text('shop_seo_title', 'عنوان فروشگاه در گوگل', (string) $s['shop_seo_title'], ['placeholder' => 'مثلاً خرید پریمیوم تلگرام، استارز و گیفت کارت | NEXUS', 'hint' => '۵۰ تا ۶۰ حرف · خالی = «نام فروشگاه | نام سایت»']) ?>
            <?= f_textarea('shop_seo_desc', 'توضیح فروشگاه در گوگل', (string) $s['shop_seo_desc'], ['rows' => 3, 'placeholder' => 'مثلاً خرید فوری پریمیوم تلگرام، استارز، تون و اکانت‌های بازی با تحویل خودکار و پشتیبانی ۲۴ ساعته.', 'hint' => '۱۲۰ تا ۱۶۰ حرف · خالی = شعار فروشگاه']) ?>
            <?= f_text('shop_seo_keywords', 'کلمات کلیدی فروشگاه', (string) $s['shop_seo_keywords'], ['placeholder' => 'خرید پریمیوم تلگرام، خرید استارز، گیفت کارت', 'hint' => 'با «،» جدا کن · محصولی که کلمه‌ی خودش رو نداره از این‌ها استفاده می‌کنه']) ?>
            <?= f_image('shop_seo_image', 'تصویر اشتراک‌گذاری فروشگاه (اختیاری)', (string) $s['shop_seo_image'] ?: null, 'وقتی لینک فروشگاه رو توی تلگرام / واتساپ می‌فرستی همین عکس نشون داده می‌شه (۱۲۰۰×۶۳۰)') ?>
        </div>
        <div class="card">
            <div class="card-head"><h3>🚀 فروشگاه چطور توی گوگل میاد؟</h3></div>
            <div class="seo-auto">
                <b>این‌ها خودکار انجام می‌شه ✅</b>
                <ul>
                    <li>همه‌ی محصولات توی <a href="<?= e(site_url('sitemap.xml')) ?>" target="_blank" rel="noopener" dir="ltr">sitemap.xml</a> هستن (با عکس و تاریخ آخرین تغییر).</li>
                    <li>هر محصول «اسکیمای محصول» داره: قیمت، موجودی، عکس‌ها و مشخصات — گوگل می‌تونه قیمت رو کنار نتیجه نشون بده.</li>
                    <li>با هر بار ذخیره‌ی محصول، به موتورهای جستجو خبر داده می‌شه (IndexNow).</li>
                    <li>آدرس هر محصول کوتاه و فارسیه؛ مثلاً <b>shop/پریمیوم-تلگرام</b> — گوگل کلمه‌های داخل آدرس رو هم می‌خونه.</li>
                </ul>
            </div>
            <ol class="seo-steps">
                <li><b>Google Search Console</b> رو باز کن و سایتت رو اضافه کن (یه بار برای کل سایت کافیه).<br><a class="abtn abtn-ghost abtn-sm" href="https://search.google.com/search-console" target="_blank" rel="noopener">باز کردن سرچ کنسول ↗</a></li>
                <li>روش تأیید «HTML tag» رو بزن، کدِ داخل <code>content="…"</code> رو کپی کن و توی <a href="settings.php">تنظیمات ← عمومی و سئو</a> بخش «کد تأیید گوگل» بذار.</li>
                <li>از منوی <b>Sitemaps</b> این آدرس رو ثبت کن:<br><code class="seo-copy" dir="ltr" data-copy="<?= e(site_url('sitemap.xml')) ?>" data-copy-msg="کپی شد ✅"><?= e(site_url('sitemap.xml')) ?></code></li>
                <li>آدرس فروشگاه رو بالای سرچ کنسول (URL Inspection) بزن و «Request Indexing» رو بزن:<br><code class="seo-copy" dir="ltr" data-copy="<?= e(site_url('shop')) ?>" data-copy-msg="کپی شد ✅"><?= e(site_url('shop')) ?></code></li>
                <li>برای هر محصول، کارت «🔎 سئو و نمایش در گوگل» رو پر کن: عنوانی که مردم سرچ می‌کنن (مثلاً «خرید پریمیوم تلگرام ۳ ماهه») + یه توضیح جذاب + عکس واقعی.</li>
                <li>صبور باش: معمولاً ۳ روز تا ۲ هفته طول می‌کشه تا صفحه‌ها بیان؛ بالا اومدن توی کلمه‌های پررقابت با محتوای خوب، لینک از کانال/سایت‌های دیگه و نظرات خریدارها کم‌کم اتفاق می‌افته.</li>
            </ol>
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
    var sml = document.getElementById('shipMList');
    if (sml) {
        document.getElementById('addShipM').addEventListener('click', function () {
            var r = document.createElement('div'); r.className = 'shipm-r';
            r.innerHTML = '<input class="input" data-k="name" placeholder="نام روش (مثلاً پست پیشتاز)"><input class="input" data-k="cost" dir="ltr" inputmode="numeric" placeholder="هزینه (تومان)"><input class="input" data-k="days" placeholder="زمان (مثلاً ۲ تا ۴ روز)"><button type="button" class="adm-ib danger" data-shipdel title="حذف">✕</button>';
            sml.appendChild(r); r.querySelector('input').focus();
        });
        sml.addEventListener('click', function (e) { var b = e.target.closest('[data-shipdel]'); if (b) b.closest('.shipm-r').remove(); });
        document.getElementById('shopSet').addEventListener('submit', function () {
            document.getElementById('shipMIn').value = JSON.stringify([].map.call(sml.querySelectorAll('.shipm-r'), function (r) { var o = {}; [].forEach.call(r.querySelectorAll('[data-k]'), function (i) { o[i.dataset.k] = i.value; }); return o; }));
        });
    }
    if (tab) { var b = document.querySelector('[data-tab="' + tab + '"]'); if (b) b.click(); }
    document.getElementById('shopSet').addEventListener('submit', function () { var a = document.querySelector('[data-tab].active'); document.getElementById('tabKeep').value = a ? a.dataset.tab : ''; });
    var R = { ton: <?= json_encode($r['ton']) ?>, usd: <?= json_encode($r['usd']) ?> };
    var fmtT = function (n) { return A.fa(String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, '٬')); };
    function calc() {
        var usd = parseFloat(en(document.getElementById('usdIn').value)) || R.usd, m = parseFloat(en(document.getElementById('marginIn').value)) || 0, rd = +document.getElementById('roundIn').value || 1;
        var a = parseFloat(en(document.getElementById('cAmt').value)) || 0, t = document.getElementById('cType').value;
        var base = t === 'usd' ? a * usd : a * (R.ton || 0) * usd, withM = base * (1 + m / 100), fin = withM > 0 ? Math.ceil(withM / rd) * rd : 0;
        var out = document.getElementById('cOut'), txt = fin > 0 ? fmtT(fin) : '—';
        if (out.textContent !== txt) { out.textContent = txt; out.classList.remove('flip'); void out.offsetWidth; out.classList.add('flip'); }
        document.getElementById('cBase').textContent = base > 0 ? fmtT(base) : '—';
        document.getElementById('cMar').textContent = base > 0 ? '+' + fmtT(withM - base) : '—';
        document.getElementById('cMarP').textContent = m ? '(' + A.fa(String(m)) + '٪)' : '';
        document.getElementById('cRnd').textContent = fin > 0 ? '+' + fmtT(fin - withM) : '—';
        document.getElementById('cUnit').textContent = t === 'usd' ? '$' : 'TON';
    }
    var seg = document.getElementById('cSeg');
    seg.addEventListener('click', function (e) {
        var b = e.target.closest('button'); if (!b) return;
        [].forEach.call(seg.querySelectorAll('button'), function (x) { x.classList.toggle('on', x === b); });
        seg.classList.toggle('alt', b.dataset.v === 'ton');
        var s = document.getElementById('cType'); s.value = b.dataset.v; s.dispatchEvent(new Event('change'));
    });
    document.getElementById('cChips').addEventListener('click', function (e) {
        var b = e.target.closest('button'); if (!b) return;
        var i = document.getElementById('cAmt'); i.value = b.dataset.a; i.dispatchEvent(new Event('input'));
    });
    ['usdIn', 'marginIn', 'roundIn', 'cAmt', 'cType'].forEach(function (id) { var el = document.getElementById(id); el.addEventListener('input', calc); el.addEventListener('change', calc); });
    calc();
    document.getElementById('mktTest').addEventListener('click', function () {
        var b = this, out = document.getElementById('mktOut');
        b.disabled = true; out.hidden = false; out.textContent = '⏳ در حال بررسی منابع قیمت (ممکن است چند ثانیه طول بکشد)...';
        A.post('market_test', {}).then(function (j) {
            b.disabled = false;
            if (!j.ok) { out.textContent = 'خطا'; return; }
            var esc = function (s) { return String(s).replace(/[&<>]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]; }); };
            out.innerHTML = (j.lines || []).map(function (l) { return '<div>' + esc(l) + '</div>'; }).join('') +
                (j.rows && j.rows.length ? '<table class="mkt-tbl"><tr><th>قیمت</th><th>تومان</th><th>دلار</th><th>۲۴ ساعت</th></tr>' + j.rows.map(function (r) {
                    var c = r.chg === null ? '' : '<span style="color:' + (r.chg >= 0 ? '#3ddc84' : '#ff6b7d') + '">' + (r.chg >= 0 ? '▲ ' : '▼ ') + A.fa(Math.abs(r.chg).toFixed(2)) + '٪</span>';
                    return '<tr><td>' + esc(r.icon + ' ' + r.name) + '</td><td>' + esc(r.fa) + '</td><td dir="ltr">' + esc(r.usd) + '</td><td dir="ltr">' + c + '</td></tr>';
                }).join('') + '</table><div class="muted small">✅ قیمت‌های صفحه فروشگاه هم همین الان به‌روز شدند.</div>' : '<div>ℹ️ هیچ قیمتی انتخاب نشده است.</div>');
        }).catch(function () { b.disabled = false; out.textContent = 'خطا در ارتباط'; });
    });
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
