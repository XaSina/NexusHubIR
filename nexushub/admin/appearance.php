<?php
require __DIR__ . '/inc/boot.php';
require_cap('*');

$keys = ['theme_preset', 'color_primary', 'color_secondary', 'color_bg', 'glass_blur', 'glass_opacity', 'radius', 'font_scale', 'card_style', 'hero_style', 'bg_effect',
    'anim_preloader', 'anim_cursor', 'anim_tilt', 'anim_reveal', 'anim_glitch', 'anim_noise', 'show_stories', 'show_ticker', 'ticker_label', 'ticker_speed',
    'announce_on', 'announce_text', 'announce_link', 'announce_style', 'logo', 'favicon', 'footer_text', 'copyright', 'show_online',
    'social_telegram', 'social_group', 'social_admin', 'social_instagram', 'social_x', 'social_youtube', 'social_aparat', 'custom_css', 'home_sections', 'menu',
    'latest_mode', 'latest_count', 'latest_hours', 'latest_since', 'theme_decor', 'decor_density', 'decor_admin'];

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
    if (isset($save['decor_density'])) $save['decor_density'] = (string) max(6, min(40, (int) $save['decor_density']));
    foreach (['social_telegram', 'social_group', 'social_admin', 'social_instagram', 'social_x', 'social_youtube', 'social_aparat', 'announce_link'] as $lk) if (isset($save[$lk])) $save[$lk] = safe_link($save[$lk]);
    // «آخرین اخبار» صفحه اصلی: تعداد ثابت، چند ساعت اخیر، یا از یک تاریخ
    if (isset($save['latest_mode'])) {
        $save['latest_mode'] = in_array($save['latest_mode'], ['count', 'hours', 'since'], true) ? $save['latest_mode'] : 'count';
        $save['latest_count'] = (string) max(3, min(60, (int) en_num($save['latest_count'] ?? '12')));
        $save['latest_hours'] = (string) max(1, min(24 * 90, (int) en_num($save['latest_hours'] ?? '72')));
        $save['latest_since'] = ($save['latest_since'] ?? '') !== '' ? (string) (jalali_to_datetime((string) $save['latest_since']) ?: '') : '';
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

adm_head('ظاهر سایت', 'appearance');
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
    <div class="card">
        <div class="card-head"><h3>🖼️ سبک بخش ویژه (بالای صفحه اصلی)</h3></div>
        <div class="opt-grid">
            <?php foreach (['magazine' => ['مجله‌ای', '۱ خبر بزرگ + ۴ خبر کوچک'], 'slider' => ['اسلایدر', 'اسلایدر تمام عرض با افکت Ken Burns'], 'grid' => ['سه‌تایی', '۳ کارت بزرگ کنار هم']] as $k => [$n, $d]): ?>
                <label class="opt"><input type="radio" name="hero_style" value="<?= $k ?>" <?= $s['hero_style'] === $k ? 'checked' : '' ?>>
                    <span class="layout-thumb lt-<?= $k ?>"><?= str_repeat('<span></span>', $k === 'magazine' ? 5 : ($k === 'grid' ? 3 : 1)) ?></span><b><?= $n ?></b><small><?= $d ?></small></label>
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
</div>

<div data-pane="anim">
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
        <div class="mt small muted">لینک‌های سریع:
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
            <?= f_textarea('footer_text', 'متن درباره در فوتر', $s['footer_text'], ['rows' => 4]) ?>
            <?= f_text('copyright', 'متن کپی‌رایت', $s['copyright']) ?>
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
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('apForm');
    var secList = document.getElementById('secList'), menuList = document.getElementById('menuList');
    NXA.sortable(secList); NXA.sortable(menuList);
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
        document.getElementById('secInput').value = JSON.stringify([].map.call(secList.querySelectorAll('.sort-item'), function (i) { return { key: i.dataset.key, on: i.querySelector('.sw').checked ? 1 : 0 }; }));
        document.getElementById('menuInput').value = JSON.stringify([].map.call(menuList.querySelectorAll('.sort-item'), function (i) { var x = i.querySelectorAll('input'); return { title: x[0].value.trim(), url: x[1].value.trim() }; }).filter(function (m) { return m.title && m.url; }));
    });
    // picking a theme also picks the background effect that suits it
    document.querySelectorAll('input[name=theme_preset]').forEach(function (r) {
        r.addEventListener('change', function () {
            var fx = r.dataset.fx, follow = document.getElementById('fxFollow');
            if (!fx || !follow || !follow.checked) return;
            var t = document.querySelector('input[name=bg_effect][value="' + fx + '"]');
            if (t) { t.checked = true; NXA.toast && NXA.toast('افکت پس‌زمینه‌ی پیشنهادی این تم انتخاب شد ✨'); }
        });
    });
});
</script>
<?php adm_foot();
