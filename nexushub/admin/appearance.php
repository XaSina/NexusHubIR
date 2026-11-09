<?php
require __DIR__ . '/inc/boot.php';
require_cap('*');

$keys = ['theme_preset', 'color_primary', 'color_secondary', 'color_bg', 'glass_blur', 'glass_opacity', 'radius', 'font_scale', 'card_style', 'hero_style', 'bg_effect',
    'auth_logo_style', 'auth_skin', 'anim_preloader', 'anim_cursor', 'anim_tilt', 'anim_reveal', 'anim_glitch', 'anim_noise', 'show_stories', 'show_ticker', 'ticker_label', 'ticker_speed',
    'announce_on', 'announce_text', 'announce_link', 'announce_style', 'logo', 'favicon', 'footer_text', 'footer_brand_on', 'footer_brand_social', 'copyright', 'trust_badges', 'trust_title', 'show_online',
    'social_telegram', 'social_group', 'social_admin', 'social_instagram', 'social_x', 'social_youtube', 'social_aparat', 'custom_css', 'home_sections', 'menu',
    'latest_mode', 'latest_count', 'latest_hours', 'latest_since',
    'home_latest_title', 'home_latest_more', 'home_latest_btn', 'home_cat_count', 'home_trend_title', 'home_trend_count', 'home_shop_count', 'wide_single',
    'sidebar_widgets', 'sb_trend_title', 'sb_trend_count', 'sb_tg_title', 'sb_tg_text', 'sb_tg_btn', 'sb_tags_title', 'sb_tags_count', 'theme_decor', 'decor_density', 'decor_admin',
    'brand_sub', 'topbar_on', 'topbar_date_on', 'topbar_social_on', 'hdr_search_on', 'hdr_login_on', 'hdr_tg_on', 'hdr_tg_label', 'hdr_login_label', 'footer_cats_title', 'footer_links_title', 'footer_cta_on', 'footer_cta_title', 'footer_cta_text', 'footer_cta_btn', 'vmsg_check', 'vmsg_field', 'vmsg_email', 'vmsg_short', 'footer_made', 'tgblock_eyebrow', 'tgblock_title', 'tgblock_text', 'tgblock_btn', 'tgblock_group_btn', 'contact_style', 'contact_icons_title', 'to_top_on', 'perf_widget_on', 'warp_on', 'auth_tagline', 'auth_btn_label', 'auth_back_label', 'auth_welcome_title', 'auth_welcome_text'];

if (is_post()) {
    csrf_check();
    if (!empty($_POST['reset'])) {
        $d = default_settings();
        save_settings(array_intersect_key($d, array_flip(array_diff($keys, ['logo', 'favicon', 'social_telegram', 'social_group', 'social_admin', 'social_instagram', 'social_x', 'social_youtube', 'social_aparat', 'menu', 'announce_text', 'announce_link', 'footer_text', 'copyright']))));
        flash('ظاهر سایت به حالت پیش‌فرض برگشت.');
        redirect('admin/appearance.php');
    }
    $save = [];
    foreach ($keys as $k) if (isset($_POST[$k])) $save[$k] = is_string($_POST[$k]) ? trim($_POST[$k]) : '';
    // اعتبارسنجی
    foreach (['color_primary', 'color_secondary', 'color_bg'] as $ck) if (isset($save[$ck])) $save[$ck] = valid_hex($save[$ck], default_settings()[$ck]);
    if (!array_key_exists($save['theme_preset'] ?? '', theme_presets())) $save['theme_preset'] = 'nexus';
    if (isset($save['card_style']) && !isset(card_styles()[$save['card_style']])) $save['card_style'] = 'glass';
    if (isset($save['bg_effect']) && !isset(bg_effects()[$save['bg_effect']])) $save['bg_effect'] = 'particles';
    if (isset($save['theme_decor']) && !in_array($save['theme_decor'], array_merge(['auto', 'off'], array_keys(decor_types())), true)) $save['theme_decor'] = 'auto';
    if (isset($save['auth_skin']) && !isset(auth_skins()[$save['auth_skin']])) $save['auth_skin'] = 'nebula';
    if (isset($save['auth_logo_style']) && !in_array($save['auth_logo_style'], ['particles', 'laser', 'glass'], true)) $save['auth_logo_style'] = 'particles';
    if (isset($save['decor_density'])) $save['decor_density'] = (string) max(6, min(40, (int) $save['decor_density']));
    foreach (['social_telegram', 'social_group', 'social_admin', 'social_instagram', 'social_x', 'social_youtube', 'social_aparat', 'announce_link'] as $lk) if (isset($save[$lk])) $save[$lk] = safe_link($save[$lk]);
    // «آخرین اخبار» صفحه اصلی: تعداد ثابت، چند ساعت اخیر، یا از یک تاریخ
    if (isset($save['latest_mode'])) {
        $save['latest_mode'] = in_array($save['latest_mode'], ['count', 'hours', 'since'], true) ? $save['latest_mode'] : 'count';
        $save['latest_count'] = (string) max(3, min(60, (int) en_num($save['latest_count'] ?? '12')));
        $save['latest_hours'] = (string) max(1, min(24 * 90, (int) en_num($save['latest_hours'] ?? '72')));
        $save['latest_since'] = ($save['latest_since'] ?? '') !== '' ? (string) (jalali_to_datetime((string) $save['latest_since']) ?: '') : '';
    }
    // trust badges: up to 12, each {title, code, link}; the code is cleaned again whenever it is shown
    if (isset($save['trust_badges'])) {
        $tb = json_decode($save['trust_badges'], true);
        $clean = [];
        if (is_array($tb)) foreach (array_slice($tb, 0, 12) as $b) {
            if (!is_array($b)) continue;
            $code = trim((string) ($b['code'] ?? ''));
            if ($code === '') continue;
            $clean[] = ['title' => mb_substr(trim((string) ($b['title'] ?? '')), 0, 60), 'code' => mb_substr($code, 0, 3000), 'link' => safe_link((string) ($b['link'] ?? ''))];
        }
        $save['trust_badges'] = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    if (isset($save['trust_title'])) $save['trust_title'] = mb_substr($save['trust_title'], 0, 60);
    foreach (['brand_sub', 'hdr_tg_label', 'hdr_login_label', 'footer_cats_title', 'footer_links_title', 'footer_cta_title', 'footer_cta_btn', 'vmsg_check', 'vmsg_field', 'vmsg_email', 'vmsg_short', 'tgblock_eyebrow', 'tgblock_title', 'tgblock_btn', 'tgblock_group_btn', 'contact_icons_title', 'footer_made', 'auth_tagline', 'auth_btn_label', 'auth_back_label', 'auth_welcome_title'] as $tk)
        if (isset($save[$tk])) $save[$tk] = mb_substr(str_replace(["\r", "\n"], ' ', $save[$tk]), 0, 120);
    if (isset($save['contact_style'])) $save['contact_style'] = $save['contact_style'] === 'cards' ? 'cards' : 'icons';
    foreach (['footer_cta_text', 'auth_welcome_text', 'tgblock_text'] as $tk) if (isset($save[$tk])) $save[$tk] = mb_substr($save[$tk], 0, 400);
    // a label left empty falls back to its default
    foreach (['hdr_tg_label', 'hdr_login_label', 'auth_btn_label', 'auth_back_label', 'auth_welcome_title', 'vmsg_check', 'vmsg_field', 'vmsg_email', 'vmsg_short'] as $tk)
        if (isset($save[$tk]) && trim($save[$tk]) === '') $save[$tk] = default_settings()[$tk];
    // home texts & counts, sidebar widgets
    foreach (['home_cat_count' => [3, 20], 'home_trend_count' => [2, 12], 'home_shop_count' => [2, 12], 'sb_trend_count' => [3, 10], 'sb_tags_count' => [4, 40]] as $nk => [$mn, $mx])
        if (isset($save[$nk])) $save[$nk] = (string) max($mn, min($mx, (int) en_num($save[$nk])));
    foreach (['home_latest_title', 'home_latest_more', 'home_latest_btn', 'home_trend_title', 'sb_trend_title', 'sb_tg_title', 'sb_tg_btn', 'sb_tags_title'] as $tk)
        if (isset($save[$tk])) $save[$tk] = mb_substr(str_replace(["\r", "\n"], ' ', $save[$tk]), 0, 80);
    if (isset($save['wide_single'])) $save['wide_single'] = $save['wide_single'] === 'full' ? 'full' : 'half';
    if (isset($save['sb_tg_text'])) $save['sb_tg_text'] = mb_substr($save['sb_tg_text'], 0, 300);
    foreach (['home_latest_title', 'home_trend_title', 'sb_trend_title', 'sb_tg_btn', 'sb_tags_title'] as $tk)
        if (isset($save[$tk]) && trim($save[$tk]) === '') $save[$tk] = default_settings()[$tk];
    if (isset($save['sidebar_widgets'])) {
        $sw = json_decode($save['sidebar_widgets'], true); $okw = [];
        if (is_array($sw)) foreach ($sw as $w) if (is_array($w) && isset(sidebar_widget_names()[$w['key'] ?? ''])) $okw[] = ['key' => $w['key'], 'on' => !empty($w['on']) ? 1 : 0];
        if ($okw) $save['sidebar_widgets'] = json_encode($okw); else unset($save['sidebar_widgets']);
    }
    foreach (['home_sections', 'menu'] as $jk) {
        if (isset($save[$jk])) {
            $j = json_decode($save[$jk], true);
            if (!is_array($j)) unset($save[$jk]);
            else $save[$jk] = json_encode(array_values($j), JSON_UNESCAPED_UNICODE);
        }
    }
    save_settings($save);
    flash('ظاهر سایت ذخیره شد 🎨');
    redirect('admin/appearance.php');
}

$s = settings_all();
$presets = theme_presets();
$sections = setting_json('home_sections') ?: json_decode(default_settings()['home_sections'], true);
$known = array_column($sections, 'key');
foreach (home_section_names() as $k => $n) if (!in_array($k, $known, true)) $sections[] = ['key' => $k, 'on' => 0];
$menu = setting_json('menu');

adm_head('ظاهر سایت', ($_GET['tab'] ?? '') === 'layout' ? 'home' : 'appearance');
?>
<form method="post" id="apForm">
<?= csrf_field() ?>
<div class="tabs" data-tabs>
    <button type="button" data-tab="theme">🎨 تم و رنگ</button>
    <button type="button" data-tab="layout">🧩 چیدمان صفحه اصلی</button>
    <button type="button" data-tab="anim">✨ انیمیشن‌ها</button>
    <button type="button" data-tab="header">🏷️ لوگو و هدر</button>
    <button type="button" data-tab="menu">☰ منو</button>
    <button type="button" data-tab="footer">🔗 فوتر و شبکه‌ها</button>
    <button type="button" data-tab="texts">✍️ متن‌ها و بخش‌ها</button>
    <button type="button" data-tab="css">💻 CSS سفارشی</button>
</div>

<div data-pane="theme">
    <div class="card">
        <div class="card-head"><h3><?= adm_icon('palette', 20) ?> تم‌های آماده</h3><span class="muted small">با یک کلیک کل سایت، صفحه ورود، صفحه انتقال و پنل تغییر می‌کند</span></div>
        <?php foreach (theme_groups() as $gk => $gname): ?>
        <h4 class="theme-group-title"><?= e($gname) ?></h4>
        <div class="opt-grid theme-grid">
            <?php foreach ($presets as $k => $p): if (($p['group'] ?? 'base') !== $gk) continue; $pp = $p['primary'] ?? $s['color_primary']; $ps = $p['secondary'] ?? $s['color_secondary']; $pb = $p['bg'] ?? $s['color_bg']; ?>
                <label class="opt">
                    <input type="radio" name="theme_preset" value="<?= e($k) ?>" data-fx="<?= e((string) ($p['fx'] ?? '')) ?>" <?= $s['theme_preset'] === $k ? 'checked' : '' ?>>
                    <span class="swatch" style="background:<?= e($pb) ?>;--sp:<?= e($pp) ?>;--ss:<?= e($ps) ?>"><em></em><i></i><?php if (!empty($p['decor'])): ?><u class="sw-decor"><?= e((string) array_slice(explode(' ', decor_types()[$p['decor']] ?? ''), -1)[0]) ?></u><?php endif; ?></span>
                    <b><?= e($p['name']) ?></b><small><?= e($p['desc']) ?></small>
                </label>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <label class="chk-row mt"><input type="checkbox" id="fxFollow" checked> با انتخاب تم، افکت پس‌زمینه‌ی پیشنهادی همان تم هم انتخاب شود (در تب «چیدمان صفحه اصلی»)</label>
    </div>
    <div class="card mt">
        <div class="card-head"><h3>🎉 افکت مناسبتی (برف، شکوفه، قلب، خفاش ...)</h3><span class="muted small">ذرات سبک و روان روی صفحه — روی سایت، صفحه ورود و پنل</span></div>
        <div class="grid g3">
            <?= f_select('theme_decor', 'افکت', ['auto' => '🪄 خودکار (همان افکت تم انتخابی)', 'off' => '⛔ خاموش'] + decor_types(), (string) ($s['theme_decor'] ?? 'auto')) ?>
            <?= f_range('decor_density', 'تعداد ذرات', $s['decor_density'] ?? '18', 6, 40) ?>
            <div><?= f_switch('decor_admin', 'نمایش در پنل مدیریت هم باشد', setting_on('decor_admin'), 'در پنل کم‌رنگ‌تر و با ذرات کمتر') ?></div>
        </div>
    </div>
    <div class="grid g2 mt">
        <div class="card">
            <div class="card-head"><h3>🎯 رنگ‌های سفارشی</h3><span class="badge b-info">برای تم «سفارشی»</span></div>
            <?= f_color('color_primary', 'رنگ اصلی (نئون)', $s['color_primary']) ?>
            <?= f_color('color_secondary', 'رنگ دوم (گرادیان)', $s['color_secondary']) ?>
            <?= f_color('color_bg', 'رنگ پس‌زمینه', $s['color_bg']) ?>
            <p class="af-hint">اگر پس‌زمینه روشن انتخاب کنید، سایت خودکار به حالت روشن می‌رود.</p>
        </div>
        <div class="card">
            <div class="card-head"><h3>🪟 افکت شیشه‌ای</h3></div>
            <?= f_range('glass_blur', 'میزان تاری شیشه', $s['glass_blur'], 0, 40, 'px') ?>
            <?= f_range('glass_opacity', 'کدری شیشه', $s['glass_opacity'], 10, 95, '٪') ?>
            <?= f_range('radius', 'گردی گوشه‌ها', $s['radius'], 0, 36, 'px') ?>
            <?= f_range('font_scale', 'اندازه فونت', $s['font_scale'], 85, 120, '٪') ?>
        </div>
    </div>
    <div class="card mt">
        <div class="card-head"><h3>🃏 سبک کارت‌ها</h3></div>
        <div class="opt-grid">
            <?php foreach (card_styles() as $k => [$n, $d]): ?>
                <label class="opt"><input type="radio" name="card_style" value="<?= $k ?>" <?= $s['card_style'] === $k ? 'checked' : '' ?>><span class="cs-thumb cs-<?= $k ?>"><i></i><i></i></span><b><?= $n ?></b><small><?= $d ?></small></label>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div data-pane="layout">
    <div class="card home-hub">
        <div class="card-head"><h3>🏠 همه‌ی تنظیمات صفحه اصلی</h3><span class="muted small">چیدمان، عنوان‌ها و سایدبار همین‌جاست؛ بقیه‌ی بخش‌ها از این میان‌برها</span></div>
        <div class="home-hub-links">
            <a href="<?= e(url('admin/ads.php')) ?>"><span>📢</span><b>تبلیغات</b><small>بالای هدر، زیر اسلایدر، وسط، سایدبار…</small></a>
            <a href="<?= e(url('admin/categories.php')) ?>"><span>🗂️</span><b>بلوک دسته‌ها</b><small>کدوم دسته روی صفحه اصلی بیاد (🏠)</small></a>
            <a href="<?= e(url('admin/stories.php')) ?>"><span>⭕</span><b>استوری‌ها</b><small>دایره‌های بالای صفحه</small></a>
            <a href="<?= e(url('admin/shop-settings.php')) ?>"><span>🛍️</span><b>بنر فروشگاه</b><small>بنر و جاهای نمایشش</small></a>
            <a href="<?= e(url('admin/raffles.php')) ?>"><span>🎁</span><b>باکس قرعه‌کشی</b><small>داخل هر قرعه‌کشی ← جای نمایش</small></a>
            <a href="<?= e(url('admin/wheels.php')) ?>"><span>🎡</span><b>باکس گردونه</b><small>داخل هر گردونه ← جای نمایش</small></a>
            <a href="<?= e(url('admin/promos.php')) ?>"><span>🏷️</span><b>باکس کد تخفیف</b><small>داخل هر کد ← جای نمایش</small></a>
            <a href="<?= e(url('admin/posts.php')) ?>"><span>⭐</span><b>خبرهای ویژه و فوری</b><small>تیک «ویژه» = بخش بالا، «فوری» = نوار فوری</small></a>
        </div>
    </div>
    <div class="card mt">
        <div class="card-head"><h3>🖼️ سبک بخش ویژه (بالای صفحه اصلی)</h3></div>
        <div class="opt-grid">
            <?php foreach (['magazine' => ['مجله‌ای', '۱ خبر بزرگ + ۴ خبر کوچک'], 'slider' => ['اسلایدر', 'اسلایدر تمام عرض با افکت Ken Burns'], 'grid' => ['سه‌تایی', '۳ کارت بزرگ کنار هم'], 'spotlight' => ['اسپات‌لایت', '۱ خبر تمام‌عرض + ۳ خبر زیرش'], 'mosaic' => ['موزاییکی', '۵ خبر در چیدمان نامتقارن مجله‌ای'], 'list' => ['لیست خبری', '۶ خبر فشرده در دو ستون، مثل خبرگزاری']] as $k => [$n, $d]): ?>
                <label class="opt"><input type="radio" name="hero_style" value="<?= $k ?>" <?= $s['hero_style'] === $k ? 'checked' : '' ?>>
                    <span class="layout-thumb lt-<?= $k ?>"><?= str_repeat('<span></span>', ['magazine' => 5, 'grid' => 3, 'spotlight' => 4, 'mosaic' => 5, 'list' => 6][$k] ?? 1) ?></span><b><?= $n ?></b><small><?= $d ?></small></label>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card mt">
        <div class="card-head"><h3>🌌 افکت پس‌زمینه</h3></div>
        <div class="opt-grid">
            <?php foreach (bg_effects() as $k => [$n, $d]): ?>
                <label class="opt"><input type="radio" name="bg_effect" value="<?= $k ?>" <?= $s['bg_effect'] === $k ? 'checked' : '' ?>><span class="fx-thumb fx-<?= $k ?>"><b></b><b></b><b></b><b></b><b></b><b></b></span><b><?= $n ?></b><small><?= $d ?></small></label>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card mt">
        <div class="card-head"><h3>📰 «آخرین اخبار» صفحه اصلی</h3><span class="muted small">چه خبرهایی در بخش آخرین اخبار بیایند</span></div>
        <?php $lm = $s['latest_mode'] ?? 'count'; ?>
        <div class="opt-grid">
            <label class="opt"><input type="radio" name="latest_mode" value="count" <?= $lm === 'count' ? 'checked' : '' ?>><b>تعداد ثابت</b><small>همیشه چند خبر آخر</small></label>
            <label class="opt"><input type="radio" name="latest_mode" value="hours" <?= $lm === 'hours' ? 'checked' : '' ?>><b>چند ساعت اخیر</b><small>مثلاً خبرهای ۷۲ ساعت گذشته</small></label>
            <label class="opt"><input type="radio" name="latest_mode" value="since" <?= $lm === 'since' ? 'checked' : '' ?>><b>از یک تاریخ</b><small>خبرهای منتشرشده بعد از تاریخ دلخواه</small></label>
        </div>
        <div class="grid g3">
            <?= f_text('latest_count', 'حداکثر تعداد خبر', (string) ($s['latest_count'] ?? '12'), ['type' => 'number', 'ltr' => true, 'min' => 3, 'max' => 60, 'hint' => 'در حالت «تعداد ثابت» دقیقاً همین تعداد؛ در دو حالت دیگر سقف تعداد']) ?>
            <div class="af"><?= f_text('latest_hours', 'چند ساعت اخیر', (string) ($s['latest_hours'] ?? '72'), ['type' => 'number', 'ltr' => true, 'min' => 1, 'attrs' => 'id="latestHours"']) ?>
                <div class="row-flex" style="gap:6px;margin-top:-6px"><?php foreach ([24 => '۲۴ ساعت', 48 => '۴۸ ساعت', 72 => '۷۲ ساعت', 168 => '۱ هفته'] as $h => $hl): ?><button type="button" class="abtn abtn-ghost abtn-xs" onclick="document.getElementById('latestHours').value=<?= $h ?>"><?= $hl ?></button><?php endforeach; ?></div></div>
            <?= f_jdate('latest_since', 'از تاریخ (شمسی)', ($s['latest_since'] ?? '') ?: null, 'در حالت «از یک تاریخ»') ?>
        </div>
        <p class="muted small">اگر در بازه انتخابی هیچ خبری نباشد، برای خالی نماندن صفحه، آخرین خبرها نمایش داده می‌شوند.</p>
    </div>
    <div class="grid g2 mt">
        <div class="card">
            <div class="card-head"><h3>↕️ ترتیب بخش‌های صفحه اصلی</h3><span class="muted small">بکشید یا با فلش جابجا کنید</span></div>
            <input type="hidden" name="home_sections" id="secInput">
            <div class="sortable" id="secList">
                <?php foreach ($sections as $sec): if (!isset(home_section_names()[$sec['key']])) continue; ?>
                    <div class="sort-item" draggable="true" data-key="<?= e($sec['key']) ?>">
                        <span class="sort-handle">⋮⋮</span><b><?= e(home_section_names()[$sec['key']]) ?></b>
                        <span class="mv"><button type="button" class="adm-ib" data-mv="up" style="width:30px;height:30px">▲</button><button type="button" class="adm-ib" data-mv="down" style="width:30px;height:30px">▼</button></span>
                        <input type="checkbox" class="sw" <?= !empty($sec['on']) ? 'checked' : '' ?>>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-head"><h3>📰 نوار فوری و استوری‌ها</h3></div>
            <?= f_switch('show_stories', 'استوری‌ها (بالای صفحه اصلی)', setting_on('show_stories'), 'استوری‌ها از بخش «استوری‌ها» در منوی پنل ساخته می‌شوند') ?>
            <?= f_switch('show_ticker', 'نوار اخبار فوری متحرک', setting_on('show_ticker'), 'اخبار با تیک «فوری» نمایش داده می‌شوند') ?>
            <?= f_text('ticker_label', 'برچسب نوار', $s['ticker_label']) ?>
            <?= f_range('ticker_speed', '⏱️ سرعت نوار فوری: هر دور عبور چند ثانیه طول بکشد (عدد بیشتر = آرام‌تر)', $s['ticker_speed'], 5, 180, ' ثانیه') ?>
        </div>
    </div>
    <div class="grid g2 mt">
        <div class="card">
            <div class="card-head"><h3>🧱 عنوان و تعداد بخش‌های صفحه اصلی</h3><span class="muted small">خالی گذاشتن دکمه‌ها = دکمه نمایش داده نمی‌شه</span></div>
            <div class="grid g2">
                <?= f_text('home_latest_title', 'عنوان «آخرین اخبار»', (string) $s['home_latest_title']) ?>
                <?= f_text('home_latest_more', 'لینک کنار عنوان', (string) $s['home_latest_more'], ['placeholder' => 'همه اخبار']) ?>
            </div>
            <?= f_text('home_latest_btn', 'دکمه‌ی زیر آخرین اخبار', (string) $s['home_latest_btn'], ['placeholder' => 'مشاهده اخبار بیشتر']) ?>
            <div class="grid g2">
                <?= f_text('home_trend_title', 'عنوان «پربازدیدترین‌ها»', (string) $s['home_trend_title']) ?>
                <?= f_text('home_trend_count', 'تعداد پربازدیدترین‌ها', (string) $s['home_trend_count'], ['type' => 'number', 'ltr' => true, 'min' => 2, 'max' => 12]) ?>
            </div>
            <div class="grid g2">
                <?= f_text('home_cat_count', 'تعداد خبر هر بلوک دسته', (string) $s['home_cat_count'], ['type' => 'number', 'ltr' => true, 'min' => 3, 'max' => 20, 'hint' => 'کدوم دسته‌ها بیان: دسته‌بندی‌ها ← 🏠']) ?>
                <?= f_text('home_shop_count', 'تعداد محصول در بخش فروشگاه', (string) $s['home_shop_count'], ['type' => 'number', 'ltr' => true, 'min' => 2, 'max' => 12]) ?>
            </div>
            <div class="af"><span class="af-label">📐 باکس تنها در جاهای عریض (تبلیغ، گردونه، قرعه‌کشی…)</span>
                <div class="opt-grid">
                    <?php $wsg = (string) ($s['wide_single'] ?? 'half'); ?>
                    <label class="opt"><input type="radio" name="wide_single" value="half" <?= $wsg !== 'full' ? 'checked' : '' ?>><b>نصف عرض، وسط صفحه</b><small>جمع‌وجور؛ دوتا که باشن کنار هم پر می‌شن</small></label>
                    <label class="opt"><input type="radio" name="wide_single" value="full" <?= $wsg === 'full' ? 'checked' : '' ?>><b>تمام عرض</b><small>یه نوار کامل از این سر تا اون سر</small></label>
                </div>
                <small class="af-hint">دو یا چند باکس در یه جا همیشه دوتا کنار هم میان. روی گوشی همیشه تمام‌عرضه.</small>
            </div>
            <p class="muted small">متن‌های بخش «دعوت به کانال تلگرام» در زبانه‌ی «✍️ متن‌ها و بخش‌ها» هستن.</p>
        </div>
        <div class="card">
            <div class="card-head"><h3>📚 سایدبار</h3><span class="muted small">کنار آخرین اخبار، صفحه‌ی خبر، آرشیو و صفحه‌ها — بکش یا با فلش جابجا کن</span></div>
            <input type="hidden" name="sidebar_widgets" id="sbInput">
            <div class="sortable" id="sbList">
                <?php foreach (sidebar_widgets() as $wd): ?>
                    <div class="sort-item" draggable="true" data-key="<?= e($wd['key']) ?>">
                        <span class="sort-handle">⋮⋮</span><b><?= e(sidebar_widget_names()[$wd['key']]) ?></b>
                        <span class="mv"><button type="button" class="adm-ib" data-mv="up" style="width:30px;height:30px">▲</button><button type="button" class="adm-ib" data-mv="down" style="width:30px;height:30px">▼</button></span>
                        <input type="checkbox" class="sw" <?= !empty($wd['on']) ? 'checked' : '' ?> aria-label="نمایش <?= e(sidebar_widget_names()[$wd['key']]) ?>">
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="muted small">همه رو خاموش کنی، ستون کناری کلاً حذف می‌شه و اخبار تمام‌عرض می‌شن.</p>
            <details class="sb-more mt"><summary>🔥 داغ‌ترین‌ها</summary>
                <div class="grid g2">
                    <?= f_text('sb_trend_title', 'عنوان', (string) $s['sb_trend_title']) ?>
                    <?= f_text('sb_trend_count', 'تعداد خبر', (string) $s['sb_trend_count'], ['type' => 'number', 'ltr' => true, 'min' => 3, 'max' => 10, 'hint' => 'بازه‌ی «داغ» از تنظیمات ← روزهای پربازدید']) ?>
                </div>
            </details>
            <details class="sb-more"><summary>✈️ کارت عضویت در کانال</summary>
                <?= f_text('sb_tg_title', 'عنوان کارت', (string) $s['sb_tg_title'], ['placeholder' => (string) setting('site_title'), 'hint' => 'خالی = نام سایت. لینک کانال از زبانه‌ی «فوتر و شبکه‌ها» خونده می‌شه']) ?>
                <?= f_textarea('sb_tg_text', 'متن کارت', (string) $s['sb_tg_text'], ['rows' => 2, 'placeholder' => (string) setting('site_tagline'), 'hint' => 'خالی = شعار سایت']) ?>
                <?= f_text('sb_tg_btn', 'متن دکمه', (string) $s['sb_tg_btn']) ?>
            </details>
            <details class="sb-more"><summary>🏷️ برچسب‌های داغ</summary>
                <div class="grid g2">
                    <?= f_text('sb_tags_title', 'عنوان', (string) $s['sb_tags_title']) ?>
                    <?= f_text('sb_tags_count', 'تعداد برچسب', (string) $s['sb_tags_count'], ['type' => 'number', 'ltr' => true, 'min' => 4, 'max' => 40]) ?>
                </div>
            </details>
            <p class="muted small mt">تبلیغ‌های سایدبار از «تبلیغات» ساخته می‌شن (جای «سایدبار»).</p>
        </div>
    </div>
</div>

<div data-pane="anim">
        <div class="card mb">
        <div class="card-head"><h3>🎨 تم کادر ورود و ثبت‌نام</h3><span class="muted small">شکل کادر، فیلدها، دکمه‌ها و کلید «ورود | ثبت‌نام» صفحه‌ی ورود سایت</span></div>
        <div class="opt-grid as-grid">
            <?php $ask = (string) ($s['auth_skin'] ?? 'nebula'); foreach (auth_skins() as $k => [$n, $d, $bg, $btn]): ?>
                <label class="opt as-opt"><input type="radio" name="auth_skin" value="<?= e($k) ?>" <?= $ask === $k ? 'checked' : '' ?>>
                    <span class="as-prev as-<?= e($k) ?>" style="--as-bg:<?= e($bg) ?>;--as-btn:<?= e($btn) ?>"><i class="as-f"></i><i class="as-f"></i><i class="as-b"></i></span>
                    <b><?= e($n) ?></b><small><?= e($d) ?></small></label>
            <?php endforeach; ?>
        </div>
        <p class="muted small mt">بعد از ذخیره، صفحه‌ی <a href="<?= e(url('auth')) ?>" target="_blank">ورود سایت ↗</a> رو باز کن و ببین.</p>
    </div>
<div class="card">
        <div class="card-head"><h3>🔐 لوگوی صفحه ورود</h3><span class="muted small">انیمیشن لوگوی NX در صفحه ورود و ثبت‌نام</span></div>
        <div class="opt-grid">
            <?php $als = (string) ($s['auth_logo_style'] ?? 'particles'); foreach (['particles' => ['✨ ذرات نورانی', 'ذرات قرمز از فضا جمع می‌شوند و NX را می‌سازند'], 'laser' => ['🔦 لیزر نورانی', 'دور NX با نور قرمز کشیده می‌شود، بعد پر و نورانی می‌شود'], 'glass' => ['💎 شیشه‌ای سه‌بعدی', 'NX شیشه‌ای قرمز که با موس یا کج کردن گوشی می‌چرخد']] as $k => [$n, $d]): ?>
                <label class="opt"><input type="radio" name="auth_logo_style" value="<?= $k ?>" <?= $als === $k ? 'checked' : '' ?>><b><?= $n ?></b><small><?= $d ?></small></label>
            <?php endforeach; ?>
        </div>
        <p class="muted small mt">با هر سه سبک، با زدن «ورود و ثبت‌نام» لوگو به ذرات قرمز تبدیل می‌شود و آرام روی صفحه پخش می‌شود. (برای لوگوی آپلودی سفارشی، سبک ذرات استفاده می‌شود.)</p>
    </div>
<div class="card">
        <div class="card-head"><h3>✨ انیمیشن‌ها و افکت‌ها</h3><span class="muted small">برای سرعت بیشتر روی گوشی‌های ضعیف، برخی را خاموش کنید</span></div>
        <div class="grid g2">
            <div>
                <?= f_switch('anim_preloader', 'لودینگ ورودی (Preloader)', setting_on('anim_preloader'), 'انیمیشن لوگو هنگام باز شدن سایت') ?>
                <?= f_switch('anim_reveal', 'ظاهر شدن تدریجی با اسکرول', setting_on('anim_reveal'), 'کارت‌ها با حرکت نرم ظاهر می‌شوند') ?>
                <?= f_switch('anim_tilt', 'افکت سه‌بعدی کارت‌ها (Tilt)', setting_on('anim_tilt'), 'چرخش کارت با حرکت موس') ?>
            </div>
            <div>
                <?= f_switch('anim_cursor', 'هاله نورانی دنبال موس', setting_on('anim_cursor')) ?>
                <?= f_switch('anim_glitch', 'افکت گلیچ روی لوگو و تیترها', setting_on('anim_glitch')) ?>
                <?= f_switch('anim_noise', 'بافت نویز سینمایی', setting_on('anim_noise'), 'دانه‌های ریز فیلم روی کل صفحه') ?>
            </div>
        </div>
    </div>
</div>

<div data-pane="header">
    <div class="grid g2">
        <div class="card">
            <div class="card-head"><h3>🏷️ لوگو</h3></div>
            <div class="grid g2">
                <div class="logo-pick"><?= f_image('logo', 'لوگو (مربع، دایره‌ای نمایش داده می‌شود)', $s['logo']) ?></div>
                <div class="logo-pick"><?= f_image('favicon', 'آیکون تب مرورگر (Favicon)', $s['favicon']) ?></div>
            </div>
        </div>
        <div class="card">
            <div class="card-head"><h3>📌 پیام سنجاق‌شده (نوار اعلان بالای سایت)</h3></div>
            <?= f_switch('announce_on', 'نمایش نوار اعلان', setting_on('announce_on')) ?>
            <?= f_text('announce_text', 'متن اعلان', $s['announce_text']) ?>
            <?= f_text('announce_link', 'لینک اعلان', $s['announce_link'], ['ltr' => true]) ?>
            <?= f_switch('show_online', '🟢 نمایش تعداد کاربران آنلاین در بالای سایت', setting_on('show_online'), 'به همه بازدیدکننده‌ها نشان داده می‌شود؛ در داشبورد پنل همیشه نمایش داده می‌شود') ?>
            <?= f_select('announce_style', 'سبک نوار', ['pinned' => 'سنجاق (شبیه تلگرام)', 'gradient' => 'گرادیان متحرک', 'glitch' => 'سایبری چشمک‌زن'], $s['announce_style']) ?>
        </div>
    </div>
</div>

<div data-pane="menu">
    <div class="card">
        <div class="card-head"><h3>☰ منوی اصلی سایت</h3><button type="button" class="abtn abtn-ghost abtn-sm" id="addMenu"><?= adm_icon('plus', 16) ?> افزودن آیتم</button></div>
        <p class="af-hint">برای لینک داخلی با / شروع کنید (مثلاً <span class="ltr">/category/game</span>) یا آدرس کامل بنویسید.</p>
        <input type="hidden" name="menu" id="menuInput">
        <div class="sortable" id="menuList">
            <?php foreach ($menu as $m): ?>
                <div class="sort-item menu-row" draggable="true"><span class="sort-handle">⋮⋮</span><input value="<?= e($m['title'] ?? '') ?>" placeholder="عنوان"><input value="<?= e($m['url'] ?? '') ?>" placeholder="/category/game" dir="ltr"><button type="button" class="adm-ib" data-del><?= adm_icon('trash', 15) ?></button></div>
            <?php endforeach; ?>
        </div>
        <div class="mt small muted quick-links"><span>لینک‌های سریع:</span>
            <?php foreach (get_categories() as $c): ?><button type="button" class="abtn abtn-ghost abtn-xs" data-quick="<?= e($c['name']) ?>|/category/<?= e($c['slug']) ?>"><?= e($c['icon'] . ' ' . $c['name']) ?></button> <?php endforeach; ?>
            <button type="button" class="abtn abtn-ghost abtn-xs" data-quick="همه اخبار|/archive">همه اخبار</button>
            <button type="button" class="abtn abtn-ghost abtn-xs" data-quick="تماس|/contact">تماس</button>
            <?php foreach (rows("SELECT title, slug FROM pages WHERE status='published'") as $pp): ?><button type="button" class="abtn abtn-ghost abtn-xs" data-quick="<?= e($pp['title']) ?>|/page/<?= e($pp['slug']) ?>">📄 <?= e($pp['title']) ?></button> <?php endforeach; ?>
        </div>
    </div>
</div>

<div data-pane="footer">
    <div class="grid g2">
        <div class="card">
            <div class="card-head"><h3>🔗 شبکه‌های اجتماعی</h3></div>
            <?= f_text('social_telegram', 'کانال تلگرام', $s['social_telegram'], ['ltr' => true, 'placeholder' => 'https://t.me/NexusHubIR']) ?>
            <?= f_text('social_group', 'گروه تلگرام', $s['social_group'], ['ltr' => true]) ?>
            <?= f_text('social_admin', 'آیدی مدیر', $s['social_admin'], ['ltr' => true]) ?>
            <?= f_text('social_instagram', 'اینستاگرام', $s['social_instagram'], ['ltr' => true]) ?>
            <?= f_text('social_x', 'ایکس (توییتر)', $s['social_x'], ['ltr' => true]) ?>
            <?= f_text('social_youtube', 'یوتیوب', $s['social_youtube'], ['ltr' => true]) ?>
            <?= f_text('social_aparat', 'آپارات', $s['social_aparat'], ['ltr' => true]) ?>
        </div>
        <div class="card">
            <div class="card-head"><h3>📝 فوتر</h3></div>
            <?= f_switch('footer_brand_on', 'کادر لوگو و معرفی سایت در فوتر', setting_on('footer_brand_on'), 'خاموش باشه، ستون‌های فوتر جای خالیش رو پر می‌کنن') ?>
            <?= f_switch('footer_brand_social', 'آیکون شبکه‌های اجتماعی داخل این کادر', setting_on('footer_brand_social')) ?>
            <?= f_textarea('footer_text', 'متن درباره در فوتر', $s['footer_text'], ['rows' => 3, 'hint' => 'خالی بذاری، کادر فقط لوگو و اسم سایت رو جمع‌وجور نشون می‌ده']) ?>
            <?= f_text('copyright', 'متن کپی‌رایت', $s['copyright']) ?>
        </div>
        <div class="card tb-card">
            <div class="card-head"><h3>🛡️ نمادهای اعتماد (اینماد، ساماندهی و …)</h3></div>
            <p class="muted small">کد رسمی نماد رو همون‌طور که سایت صادرکننده داده اینجا بچسبون، یا فقط آدرس عکس نماد رو بزن. هر چندتا که بخوای اضافه کن؛ توی فوتر کنار هم نشون داده می‌شن.</p>
            <?= f_text('trust_title', 'عنوان بخش', (string) ($s['trust_title'] ?? 'نمادهای اعتماد')) ?>
            <input type="hidden" name="trust_badges" id="tbData" value="<?= e((string) ($s['trust_badges'] ?? '[]')) ?>">
            <div class="tb-list" id="tbList"></div>
            <button type="button" class="abtn tb-add" id="tbAdd"><?= icon('plus', 16) ?> افزودن نماد</button>
        </div>
    </div>
</div>

<div data-pane="texts">
    <div class="grid g2">
        <div class="card">
            <div class="card-head"><h3>🏷️ هدر سایت</h3></div>
            <?= f_text('brand_sub', 'زیرنویس کنار لوگو', $s['brand_sub'], ['ltr' => true, 'placeholder' => 'Gaming · Tech · News']) ?>
            <?= f_switch('hdr_tg_on', 'دکمه‌ی تلگرام در هدر', setting_on('hdr_tg_on'), 'دکمه‌ی رنگی «عضویت در کانال» بالای سایت') ?>
            <?= f_switch('hdr_login_on', 'دکمه‌ی «ورود | ثبت‌نام» در هدر', setting_on('hdr_login_on'), 'خاموش باشه، صفحه‌ی ورود هنوز از منوی گوشی و لینک‌ها در دسترسه') ?>
            <?= f_switch('hdr_search_on', 'دکمه‌ی جستجو در هدر', setting_on('hdr_search_on')) ?>
            <?= f_switch('topbar_on', 'نوار باریک بالای هدر', setting_on('topbar_on'), 'تاریخ، ساعت، تعداد آنلاین و آیکون شبکه‌ها') ?>
            <div class="grid g2">
                <?= f_switch('topbar_date_on', 'تاریخ و ساعت', setting_on('topbar_date_on')) ?>
                <?= f_switch('topbar_social_on', 'آیکون شبکه‌های اجتماعی', setting_on('topbar_social_on')) ?>
            </div>
            <p class="muted small">نمایش تعداد آنلاین‌ها از زبانه‌ی «🏷️ لوگو و هدر» روشن و خاموش می‌شه.</p>
            <?= f_text('hdr_tg_label', 'متن دکمه‌ی تلگرام', $s['hdr_tg_label']) ?>
            <?= f_text('hdr_login_label', 'متن دکمه‌ی ورود (برای مهمان‌ها)', $s['hdr_login_label']) ?>
        </div>
        <div class="card">
            <div class="card-head"><h3>🔗 فوتر</h3></div>
            <div class="grid g2">
                <?= f_text('footer_cats_title', 'عنوان ستون دسته‌ها', $s['footer_cats_title']) ?>
                <?= f_text('footer_links_title', 'عنوان ستون لینک‌ها', $s['footer_links_title']) ?>
            </div>
            <?= f_switch('footer_cta_on', 'کارت تلگرام در فوتر', setting_on('footer_cta_on')) ?>
            <?= f_text('footer_cta_title', 'عنوان کارت تلگرام', $s['footer_cta_title']) ?>
            <?= f_textarea('footer_cta_text', 'متن کارت تلگرام', $s['footer_cta_text'], ['rows' => 2]) ?>
            <?= f_text('footer_cta_btn', 'متن دکمه‌ی کارت تلگرام', (string) setting('footer_cta_btn'), ['placeholder' => 'کانال تلگرام']) ?>
            <div class="ap-sub"><b>💬 بلوک «کامیونیتی تلگرام» صفحه‌ی اصلی</b></div>
            <div class="grid g2">
                <?= f_text('tgblock_eyebrow', 'برچسب بالا (خالی = نمایش داده نشه)', (string) setting('tgblock_eyebrow')) ?>
                <?= f_text('tgblock_title', 'عنوان', (string) setting('tgblock_title')) ?>
                <?= f_text('tgblock_btn', 'متن دکمه‌ی کانال', (string) setting('tgblock_btn')) ?>
                <?= f_text('tgblock_group_btn', 'متن دکمه‌ی گروه', (string) setting('tgblock_group_btn')) ?>
            </div>
            <?= f_textarea('tgblock_text', 'متن بلوک', (string) setting('tgblock_text'), ['rows' => 2]) ?>
            <div class="ap-sub"><b>📮 صفحه‌ی تماس با ما</b></div>
            <div class="grid g2">
                <?= f_select('contact_style', 'نمایش راه‌های ارتباطی', ['icons' => 'فقط لوگوهای متحرک کنار هم', 'cards' => 'کارت با اسم و آدرس'], (string) setting('contact_style', 'icons')) ?>
                <?= f_text('contact_icons_title', 'متن بالای لوگوها', (string) setting('contact_icons_title', 'از این راه‌ها هم در دسترسیم')) ?>
            </div>
            <?= f_text('footer_made', 'متن پایین‌ترین خط (خالی = نمایش داده نشه)', $s['footer_made'], ['ltr' => true, 'hint' => 'علامت ❤ خودکار رنگی و تپنده می‌شه']) ?>
        </div>
        <div class="card">
            <div class="card-head"><h3>✅ پیام فیلدها و تیک‌های اجباری</h3><span class="muted" style="font-size:.78rem">به‌جای پیام انگلیسی مرورگر، با طراحی سایت</span></div>
            <div class="grid g2">
                <?= f_text('vmsg_check', 'وقتی تیک اجباری زده نشده', (string) setting('vmsg_check')) ?>
                <?= f_text('vmsg_field', 'وقتی یه فیلد اجباری خالیه', (string) setting('vmsg_field')) ?>
                <?= f_text('vmsg_email', 'وقتی ایمیل درست نیست', (string) setting('vmsg_email')) ?>
                <?= f_text('vmsg_short', 'وقتی متن کوتاهه ({n} = حداقل)', (string) setting('vmsg_short')) ?>
            </div>
        </div>
        <div class="card">
            <div class="card-head"><h3>🔐 صفحه‌ی ورود و ثبت‌نام</h3></div>
            <?= f_text('auth_tagline', 'جمله‌ی تایپی زیر لوگو', $s['auth_tagline']) ?>
            <?= f_text('auth_btn_label', 'متن دکمه‌ی اصلی', $s['auth_btn_label']) ?>
            <?= f_text('auth_back_label', 'متن دکمه‌ی بازگشت', $s['auth_back_label']) ?>
            <?= f_text('auth_welcome_title', 'عنوان کادر ورود', $s['auth_welcome_title']) ?>
            <?= f_textarea('auth_welcome_text', 'توضیح کادر ورود', $s['auth_welcome_text'], ['rows' => 3]) ?>
        </div>
        <div class="card">
            <div class="card-head"><h3>🧭 بخش‌های کلی سایت</h3></div>
            <?= f_switch('warp_on', 'انیمیشن جابه‌جایی بین صفحه‌ها', setting_on('warp_on'), 'پرده و لوگوی وسط صفحه موقع رفتن به صفحه‌ی دیگه') ?>
            <?= f_switch('to_top_on', 'دکمه‌ی «بازگشت به بالا»', setting_on('to_top_on'), 'دایره‌ی پایین صفحه با نوار پیشرفت اسکرول') ?>
            <?= f_switch('perf_widget_on', 'دکمه‌ی «عملکرد سایت» کنار صفحه', setting_on('perf_widget_on'), 'هر بازدیدکننده خودش حالت کامل، متعادل، سبک یا بدون انیمیشن رو انتخاب می‌کنه. (توی پنل همیشه هست)') ?>
            <div class="alert alert-info small">متن «درباره»، کپی‌رایت، نمادهای اعتماد و شبکه‌های اجتماعی در تب «🔗 فوتر و شبکه‌ها» هستن.</div>
        </div>
    </div>
</div>

<div data-pane="css">
    <div class="card">
        <div class="card-head"><h3>💻 CSS سفارشی</h3></div>
        <?= f_textarea('custom_css', 'کدهای CSS اضافه', $s['custom_css'], ['rows' => 14, 'code' => true, 'placeholder' => ".post-title { letter-spacing: -.5px; }", 'hint' => 'این کدها بعد از استایل اصلی سایت بارگذاری می‌شوند. متغیرهای قابل استفاده: var(--primary)، var(--secondary)، var(--radius)']) ?>
    </div>
</div>

<div class="sticky-save">
    <button class="abtn abtn-ghost" name="reset" value="1" data-confirm="تنظیمات ظاهری به حالت پیش‌فرض برگردد؟">بازگشت به پیش‌فرض</button>
    <a class="abtn abtn-ghost" href="<?= e(url('')) ?>" target="_blank"><?= icon('eye', 16) ?> مشاهده سایت</a>
    <button class="abtn abtn-primary" type="submit"><?= icon('check', 18) ?> ذخیره تغییرات</button>
</div>
</form>
<script>
/* trust badges: add / remove rows, live preview of what was saved, JSON on submit */
document.addEventListener('DOMContentLoaded', function () {
    var list = document.getElementById('tbList'), data = document.getElementById('tbData'), prev = <?= json_encode(array_map('trust_badge_html', trust_badges()), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    if (!list) return;
    var n = 0;
    function row(b, html) {
        b = b || {}; n++;
        var d = document.createElement('div'); d.className = 'tb-row';
        d.innerHTML = '<div class="tb-row-head"><b>نماد ' + n + '</b><button type="button" class="abtn abtn-sm tb-del" title="حذف">✕ حذف</button></div>'
            + '<div class="tb-row-body"><div class="tb-fields">'
            + '<label class="tb-f"><span>نام (مثلاً اینماد)</span><input class="tb-title"></label>'
            + '<label class="tb-f"><span>کد نماد یا آدرس عکس</span><textarea class="tb-code" rows="3" dir="ltr" placeholder="<a href=&quot;https://trustseal.enamad.ir/...&quot;><img src=&quot;...&quot;></a>"></textarea></label>'
            + '<label class="tb-f"><span>لینک (اختیاری، فقط وقتی آدرس عکس گذاشتی)</span><input class="tb-link" dir="ltr" placeholder="https://"></label>'
            + '</div><div class="tb-preview">' + (html || '<small>پیش‌نمایش بعد از ذخیره</small>') + '</div></div>';
        d.querySelector('.tb-title').value = b.title || ''; d.querySelector('.tb-code').value = b.code || ''; d.querySelector('.tb-link').value = b.link || '';
        list.appendChild(d); return d;
    }
    var saved = []; try { saved = JSON.parse(data.value || '[]') || []; } catch (e) {}
    saved.forEach(function (b, i) { row(b, prev[i]); });
    if (!saved.length) row();
    document.getElementById('tbAdd').addEventListener('click', function () { row().querySelector('.tb-title').focus(); });
    list.addEventListener('click', function (e) { var x = e.target.closest('.tb-del'); if (x) x.closest('.tb-row').remove(); });
    document.getElementById('apForm').addEventListener('submit', function () {
        data.value = JSON.stringify([].map.call(list.querySelectorAll('.tb-row'), function (r) {
            return { title: r.querySelector('.tb-title').value.trim(), code: r.querySelector('.tb-code').value.trim(), link: r.querySelector('.tb-link').value.trim() };
        }).filter(function (b) { return b.code; }));
    });
});
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('apForm');
    var secList = document.getElementById('secList'), menuList = document.getElementById('menuList');
    NXA.sortable(secList); NXA.sortable(menuList);
    var sbList = document.getElementById('sbList'); if (sbList) NXA.sortable(sbList);
    function row(t, u) {
        var d = document.createElement('div'); d.className = 'sort-item menu-row'; d.draggable = true;
        d.innerHTML = '<span class="sort-handle">⋮⋮</span><input placeholder="عنوان"><input placeholder="/category/game" dir="ltr"><button type="button" class="adm-ib" data-del>✕</button>';
        d.querySelectorAll('input')[0].value = t || ''; d.querySelectorAll('input')[1].value = u || '';
        menuList.appendChild(d); return d;
    }
    document.getElementById('addMenu').addEventListener('click', function () { row().querySelector('input').focus(); });
    document.querySelectorAll('[data-quick]').forEach(function (b) { b.addEventListener('click', function () { var p = b.dataset.quick.split('|'); row(p[0], p[1]); }); });
    menuList.addEventListener('click', function (e) { if (e.target.closest('[data-del]')) e.target.closest('.sort-item').remove(); });
    form.addEventListener('submit', function () {
        if (sbList) document.getElementById('sbInput').value = JSON.stringify([].map.call(sbList.querySelectorAll('.sort-item'), function (i) { return { key: i.dataset.key, on: i.querySelector('.sw').checked ? 1 : 0 }; }));
        document.getElementById('secInput').value = JSON.stringify([].map.call(secList.querySelectorAll('.sort-item'), function (i) { return { key: i.dataset.key, on: i.querySelector('.sw').checked ? 1 : 0 }; }));
        document.getElementById('menuInput').value = JSON.stringify([].map.call(menuList.querySelectorAll('.sort-item'), function (i) { var x = i.querySelectorAll('input'); return { title: x[0].value.trim(), url: x[1].value.trim() }; }).filter(function (m) { return m.title && m.url; }));
    });
    // picking a theme also picks the background effect that suits it
    document.querySelectorAll('input[name=theme_preset]').forEach(function (r) {
        r.addEventListener('change', function () {
            var fx = r.dataset.fx, follow = document.getElementById('fxFollow');
            if (!fx || !follow || !follow.checked) return;
            var t = document.querySelector('input[name=bg_effect][value="' + fx + '"]');
            if (t) t.checked = true;   // silently follows the theme (no toast covering the save bar)
        });
    });
});
</script>
<?php adm_foot();
