<?php
/**
 * مقادیر پیش‌فرض تنظیمات، تم‌ها و جایگاه‌های تبلیغ
 */

function default_settings(): array
{
    return [
        // عمومی
        'site_title' => 'NEXUS | HUB',
        'site_tagline' => 'گیم | تکنولوژی | ادیت | موزیک | سرگرمی | اخبار روز',
        'site_description' => 'NEXUS | HUB — محتوای روزانه، کاربردی و متفاوت در دنیای گیم، تکنولوژی، ادیت، موزیک و اخبار روز.',
        'meta_keywords' => 'گیم, تکنولوژی, اخبار بازی, اخبار تکنولوژی, نکسوس',
        'logo' => 'assets/img/logo-256.png',
        'favicon' => 'assets/img/favicon.png',
        'contact_email' => '',
        'posts_per_page' => '12',
        'footer_text' => 'NEXUS | HUB — محتوای روزانه، کاربردی و متفاوت 🔥 به کامیونیتی NEXUS بپیوندید.',
        'copyright' => 'تمامی حقوق برای NEXUS | HUB محفوظ است.',
        'show_online' => '1',

        // شبکه‌های اجتماعی
        'social_telegram' => 'https://t.me/NexusHubIR',
        'social_group' => 'https://t.me/NexusHubIR_Group',
        'social_admin' => 'https://t.me/XaSina',
        'social_instagram' => '',
        'social_x' => '',
        'social_youtube' => '',
        'social_aparat' => '',

        // ظاهر
        'theme_preset' => 'nexus',
        'color_primary' => '#ff1f3d',
        'color_secondary' => '#ff7a3d',
        'color_bg' => '#050507',
        'glass_blur' => '18',
        'glass_opacity' => '55',
        'radius' => '20',
        'card_style' => 'glass',
        'theme_decor' => 'auto',      // auto (از تم) | off | یکی از decor_types
        'decor_admin' => '1',
        'decor_density' => '18',
        'hero_style' => 'magazine',   // magazine | slider | grid
        'bg_effect' => 'particles',   // particles | grid | aurora | matrix | none
        'font_scale' => '100',
        'anim_preloader' => '1',
        'anim_cursor' => '1',
        'anim_tilt' => '1',
        'anim_reveal' => '1',
        'anim_glitch' => '1',
        'anim_noise' => '1',
        'show_stories' => '1',
        'show_ticker' => '1',
        'ticker_label' => 'فوری',
        'ticker_speed' => '40',
        'announce_on' => '1',
        'announce_text' => '🎰 قرعه‌کشی ویژه شروع شد! برای شرکت به کانال تلگرام NEXUS بپیوندید 🧸',
        'announce_link' => 'https://t.me/NexusHubIR',
        'announce_style' => 'pinned', // pinned | gradient | glitch
        'home_sections' => json_encode([
            ['key' => 'hero', 'on' => 1],
            ['key' => 'latest', 'on' => 1],
            ['key' => 'categories', 'on' => 1],
            ['key' => 'trending', 'on' => 1],
            ['key' => 'telegram', 'on' => 1],
            ['key' => 'shop', 'on' => 1],
        ]),
        'menu' => json_encode([
            ['title' => 'خانه', 'url' => '/'],
            ['title' => 'همه اخبار', 'url' => '/archive'],
            ['title' => 'گیم', 'url' => '/category/game'],
            ['title' => 'تکنولوژی', 'url' => '/category/tech'],
            ['title' => 'سرگرمی', 'url' => '/category/fun'],
            ['title' => 'تماس', 'url' => '/contact'],
        ], JSON_UNESCAPED_UNICODE),
        'custom_css' => '',
        'head_code' => '',
        'footer_code' => '',

        // نظرات و واکنش‌ها
        'members_register' => '1',
        'comments_enabled' => '1',
        'comments_moderation' => '1',
        'reactions_enabled' => '1',
        'reactions_list' => '🔥,❤️,😂,😮,😭,👍',
        'trending_days' => '30',
        'latest_mode' => 'count',   // count | hours | since
        'latest_count' => '12',
        'latest_hours' => '72',
        'latest_since' => '',

        // تلگرام
        'tg_bot_token' => '',
        'tg_chat_id' => '@NexusHubIR',
        'tg_autopost' => '0',
        'tg_template' => "🧩 <b>{title}</b>\n\n{excerpt}\n\n🔗 {link}\n\n📢 {channel}",

        // سایر
        'maintenance' => '0',
        'maintenance_text' => 'سایت در حال بروزرسانی است؛ به زودی برمی‌گردیم 🔧',
        'ad_label' => 'تبلیغ',
        'post_middle_paragraph' => '3',

        // فروشگاه
        'shop_enabled' => '1',
        'shop_title' => 'فروشگاه NEXUS',
        'shop_tagline' => 'پریمیوم تلگرام، استارز، تون و هر چیزی که لازم داری — سریع، امن و با پشتیبانی واقعی',
        'shop_card_number' => '6219861939982061',
        'shop_card_name' => 'مهدیه عباس زاده',
        'shop_card_bank' => 'بانک سامان',
        'shop_usd_mode' => 'manual',      // manual | auto (قیمت تتر نوبیتکس)
        'shop_usd_rate' => '0',           // قیمت دلار به تومان (دستی)
        'shop_ton_mode' => 'auto',        // auto | manual
        'shop_ton_usd' => '0',            // قیمت دستی / پشتیبان هر تون به دلار
        'shop_margin' => '0',             // درصد سود روی قیمت‌های دلاری و تونی
        'shop_round' => '1000',           // گرد کردن قیمت نهایی (رو به بالا)
        'shop_pay_minutes' => '60',
        'shop_notice' => 'پس از واریز، تصویر فیش را ارسال کنید. سفارش‌ها معمولاً بین ۵ تا ۳۰ دقیقه انجام می‌شوند.',
        'shop_terms' => "• سفارش پس از تایید فیش واریزی انجام می‌شود.\n• اطلاعات تحویل (مثل آیدی تلگرام) را با دقت وارد کنید؛ مسئولیت اطلاعات اشتباه با خریدار است.\n• در صورت عدم امکان انجام سفارش، مبلغ کامل به کارت شما برگشت داده می‌شود.\n• برای پیگیری از بخش پشتیبانی تیکت ثبت کنید.",
        'shop_in_header' => '1',
        'shop_in_mobile' => '1',
        'shop_in_footer' => '1',
        'shop_menu_label' => 'فروشگاه',
        'shop_banner_on' => '1',
        'shop_banner_title' => 'فروشگاه NEXUS افتتاح شد 🛍️',
        'shop_banner_text' => 'پریمیوم تلگرام، استارز و تون با بهترین قیمت؛ پرداخت کارت‌به‌کارت و تحویل سریع.',
        'shop_banner_btn' => 'ورود به فروشگاه',
        'shop_banner_style' => 'flow',    // یکی از تم‌های تبلیغ
        'shop_banner_places' => 'sidebar,post_bottom',
        'shop_banner_emoji' => '🛍️',
        'shop_banner_accent' => '',
        'shop_banner_image' => '',
        'shop_banner_badge' => 'جدید',
        'shop_support_on' => '1',
        'shop_tg_admin_chat' => '',
        'shop_rate_cache' => '',
    ];
}

/** انواع محصول فروشگاه + فیلدهای پیش‌فرض هر نوع */
function product_types(): array
{
    $tgUser = ['key' => 'tg_username', 'label' => 'آیدی تلگرام گیرنده', 'type' => 'username', 'required' => 1, 'placeholder' => '@username'];
    return [
        'premium' => ['name' => 'پریمیوم تلگرام', 'icon' => '⭐', 'color' => '#a855f7', 'fields' => [$tgUser]],
        'stars' => ['name' => 'استارز تلگرام', 'icon' => '🌟', 'color' => '#ffc53d', 'fields' => [$tgUser]],
        'ton' => ['name' => 'تون (TON)', 'icon' => '💎', 'color' => '#0098ea', 'fields' => [['key' => 'wallet', 'label' => 'آدرس کیف پول TON', 'type' => 'text', 'required' => 1, 'placeholder' => 'UQ... یا EQ...']]],
        'giftcard' => ['name' => 'گیفت کارت', 'icon' => '🎁', 'color' => '#ff3d8b', 'fields' => []],
        'game' => ['name' => 'بازی / آیتم گیم', 'icon' => '🎮', 'color' => '#ff1f3d', 'fields' => [['key' => 'account', 'label' => 'ایمیل یا آیدی اکانت', 'type' => 'text', 'required' => 1, 'placeholder' => '']]],
        'account' => ['name' => 'اکانت / اشتراک', 'icon' => '🔑', 'color' => '#1de9b6', 'fields' => []],
        'service' => ['name' => 'خدمات', 'icon' => '🛠️', 'color' => '#3dbbff', 'fields' => []],
        'other' => ['name' => 'سایر', 'icon' => '🛍️', 'color' => '#ff7a3d', 'fields' => []],
    ];
}

function order_statuses(): array
{
    return [
        'pending_payment' => ['name' => 'در انتظار پرداخت', 'icon' => '💳', 'tone' => 'warn'],
        'reviewing' => ['name' => 'در حال بررسی فیش', 'icon' => '🔎', 'tone' => 'info'],
        'rejected' => ['name' => 'فیش تایید نشد', 'icon' => '⚠️', 'tone' => 'error'],
        'approved' => ['name' => 'پرداخت تایید شد — در حال انجام', 'icon' => '⚙️', 'tone' => 'info'],
        'completed' => ['name' => 'انجام شد', 'icon' => '✅', 'tone' => 'success'],
        'canceled' => ['name' => 'لغو شد', 'icon' => '✖️', 'tone' => 'muted'],
        'expired' => ['name' => 'منقضی شد', 'icon' => '⌛', 'tone' => 'muted'],
    ];
}

/** تم‌های آماده — group: base | pro | occasion · fx: افکت پس‌زمینه پیشنهادی · decor: افکت مناسبتی */
function theme_presets(): array
{
    $t = fn($name, $desc, $p, $s, $bg, $group = 'base', $fx = null, $decor = null, $light = false) => compact('name', 'desc', 'group', 'fx', 'decor', 'light') + ['primary' => $p, 'secondary' => $s, 'bg' => $bg];
    return [
        // ---------- اصلی
        'nexus' => $t('Nexus Red', 'قرمز نئونی — تم اصلی کانال', '#ff1f3d', '#ff7a3d', '#050507', 'base', 'particles'),
        'blood' => $t('Blood Moon', 'قرمز تیره و سینمایی', '#c3002f', '#ff2e63', '#070304', 'base', 'embers'),
        'cyber' => $t('Cyber Blue', 'آبی سایبرپانک', '#00e1ff', '#7c4dff', '#03060d', 'base', 'grid'),
        'toxic' => $t('Toxic Green', 'سبز ماتریکسی', '#39ff14', '#00ffa3', '#030805', 'base', 'matrix'),
        'royal' => $t('Royal Purple', 'بنفش و صورتی نئون', '#b14dff', '#ff3df2', '#06030b', 'base', 'bokeh'),
        'gold' => $t('Golden Hour', 'طلایی و نارنجی گرم', '#ffc53d', '#ff7a1f', '#080602', 'base', 'fireflies'),
        'ocean' => $t('Deep Ocean', 'فیروزه‌ای آرام', '#1de9b6', '#2979ff', '#020a0c', 'base', 'waves'),
        'snow' => $t('Snow Glass', 'تم روشن شیشه‌ای', '#e0102e', '#ff6a3d', '#eef0f6', 'base', 'none', null, true),
        // ---------- حرفه‌ای
        'synthwave' => $t('Synthwave 84', 'صورتی و فیروزه‌ای دهه ۸۰', '#ff2e97', '#00e5ff', '#0b0420', 'pro', 'grid'),
        'vapor' => $t('Vaporwave', 'پاستلی نئونی رویایی', '#ff71ce', '#01cdfe', '#100322', 'pro', 'bokeh'),
        'tokyo' => $t('Neon Tokyo', 'شب‌های بارانی توکیو', '#ff003c', '#00f0ff', '#06060c', 'pro', 'rain'),
        'galaxy' => $t('Galaxy', 'کهکشان بنفش و صورتی', '#8a5cff', '#ff4ecd', '#05030f', 'pro', 'stars'),
        'nebula' => $t('Nebula', 'سحابی آبی و ارغوانی', '#4d7cff', '#c84dff', '#03041a', 'pro', 'nebula'),
        'lava' => $t('Lava Core', 'گدازه‌ی نارنجی و زرد', '#ff4d00', '#ffd000', '#0a0402', 'pro', 'embers'),
        'arctic' => $t('Arctic Ice', 'یخی و کریستالی', '#7ff0ff', '#4d7cff', '#020812', 'pro', 'stars'),
        'emerald' => $t('Emerald Luxe', 'زمرد و طلا — لوکس', '#00d68f', '#d4af37', '#02100a', 'pro', 'fireflies'),
        'crimson' => $t('Royal Crimson', 'زرشکی و طلایی سلطنتی', '#d0103a', '#d4af37', '#0b0406', 'pro', 'bokeh'),
        'sunset' => $t('Sunset Drive', 'غروب نارنجی و صورتی', '#ff5f6d', '#ffc371', '#120608', 'pro', 'waves'),
        'hacker' => $t('Hacker Terminal', 'ترمینال سبز کلاسیک', '#00ff66', '#a3ff12', '#010402', 'pro', 'matrix'),
        'carbon' => $t('Carbon Mono', 'سیاه و نقره‌ای مینیمال', '#e8e8ea', '#8a8f98', '#0a0a0b', 'pro', 'hex'),
        'aurora' => $t('Aurora Borealis', 'شفق قطبی سبز و بنفش', '#00ffa3', '#7c4dff', '#020a10', 'pro', 'aurora'),
        'pearl' => $t('Pearl Light', 'روشن و مرواریدی', '#6b4dff', '#00b3ff', '#f4f5fb', 'pro', 'none', null, true),
        'sakura' => $t('Sakura Light', 'روشن صورتی شکوفه‌ای', '#e8467c', '#ff9e7a', '#fff4f7', 'pro', 'bokeh', null, true),
        // ---------- مناسبتی
        'nowruz' => $t('نوروز', 'بهار، سبزه و شکوفه 🌸', '#2ecc71', '#ff6fa5', '#04120a', 'occasion', 'bokeh', 'petals'),
        'sizdah' => $t('سیزده‌بدر', 'طبیعت و سبزه 🌿', '#7bd34a', '#ffd23d', '#061205', 'occasion', 'fireflies', 'leaves_green'),
        'yalda' => $t('شب یلدا', 'بلندترین شب — انار و هندوانه 🍉', '#c3002f', '#ff8a3d', '#0b0306', 'occasion', 'stars', 'yalda'),
        'suri' => $t('چهارشنبه‌سوری', 'آتش و جرقه 🔥', '#ff5500', '#ffcc00', '#0a0300', 'occasion', 'embers', 'embers'),
        'halloween' => $t('هالووین', 'نارنجی و بنفش ترسناک 🎃', '#ff7518', '#9b30ff', '#0a0612', 'occasion', 'fireflies', 'halloween'),
        'christmas' => $t('کریسمس / سال نو', 'برف، قرمز و سبز 🎄', '#e0242f', '#1fa35a', '#050b08', 'occasion', 'stars', 'snow'),
        'winter' => $t('زمستان', 'برف و آبی یخی ❄️', '#9fd8ff', '#ffffff', '#04080f', 'occasion', 'stars', 'snow'),
        'autumn' => $t('پاییز', 'برگ‌های نارنجی ریزان 🍁', '#ff8c1a', '#c0392b', '#0e0703', 'occasion', 'fireflies', 'leaves'),
        'valentine' => $t('ولنتاین / سپندارمذگان', 'عشق و قلب‌های صورتی 💖', '#ff2e63', '#ff8fb1', '#12040a', 'occasion', 'bokeh', 'hearts'),
        'ramadan' => $t('رمضان', 'هلال ماه و فانوس طلایی 🌙', '#d4af37', '#1de9b6', '#03100e', 'occasion', 'stars', 'ramadan'),
        'eid' => $t('عید فطر / قربان', 'سبز و طلایی جشن ✨', '#3ddc84', '#ffd24d', '#03100a', 'occasion', 'fireflies', 'sparkles'),
        'muharram' => $t('محرم', 'مشکی و سبز — آرام و بی‌جلوه', '#2e9e5b', '#9aa0a6', '#040606', 'occasion', 'none', null),
        // ---------- دلخواه
        'custom' => ['name' => 'سفارشی', 'desc' => 'رنگ‌های دلخواه خودت', 'group' => 'base', 'fx' => null, 'decor' => null, 'primary' => null, 'secondary' => null, 'bg' => null, 'light' => false],
    ];
}

function theme_groups(): array
{
    return ['base' => '🎨 تم‌های اصلی', 'pro' => '💎 تم‌های حرفه‌ای', 'occasion' => '🎉 تم‌های مناسبتی'];
}

function card_styles(): array
{
    return [
        'glass' => ['شیشه‌ای', 'مات و شفاف با بلور'], 'solid' => ['توپر', 'سبک و سریع‌تر'], 'outline' => ['خطی', 'فقط حاشیه'],
        'neon' => ['نئونی', 'درخشش رنگی دور کارت'], 'gradient' => ['حاشیه گرادیانی', 'حاشیه‌ی رنگین براق'], 'holo' => ['هولوگرافیک', 'برق رنگین‌کمانی روی کارت'],
        'cyber' => ['سایبری', 'گوشه‌های بریده‌ی HUD'], 'elevated' => ['سه‌بعدی', 'سایه‌ی عمیق و برجسته'], 'frost' => ['یخی مات', 'بلور غلیظ و نرم'],
    ];
}

function bg_effects(): array
{
    return [
        'particles' => ['شبکه ذرات', 'ذرات متصل متحرک (تعاملی با موس)'], 'matrix' => ['ماتریکس', 'باران کاراکترهای سایبری'],
        'grid' => ['گرید سایبری', 'شبکه سه‌بعدی متحرک'], 'aurora' => ['شفق قطبی', 'نور چرخان ملایم'],
        'stars' => ['سفر بین ستاره‌ها', 'ستاره‌هایی که به سمتت می‌آیند'], 'waves' => ['امواج نئونی', 'موج‌های نرم و روان'],
        'bokeh' => ['بوکه', 'دایره‌های نورانی محو'], 'fireflies' => ['کرم شب‌تاب', 'نقطه‌های درخشان شناور'],
        'embers' => ['جرقه و آتش', 'جرقه‌های بالارونده'], 'rain' => ['باران نئونی', 'خطوط باران سایبرپانکی'],
        'nebula' => ['سحابی', 'ابرهای رنگی کهکشانی'], 'hex' => ['شش‌ضلعی', 'شبکه‌ی کندوی تپنده'],
        'none' => ['ساده', 'فقط هاله‌های رنگی'],
    ];
}

/** افکت‌های مناسبتی (ذرات ریزان) */
function decor_types(): array
{
    return [
        'snow' => 'برف ❄️', 'petals' => 'شکوفه 🌸', 'hearts' => 'قلب 💖', 'halloween' => 'خفاش و کدو 🎃', 'leaves' => 'برگ پاییزی 🍁',
        'leaves_green' => 'برگ سبز 🌿', 'yalda' => 'یلدا 🍉', 'embers' => 'جرقه 🔥', 'ramadan' => 'هلال و ستاره 🌙', 'sparkles' => 'درخشش ✨',
    ];
}

/** جایگاه‌های تبلیغ */
function ad_positions(): array
{
    return [
        'header_top' => ['name' => 'بالای هدر (نوار سراسری)', 'hint' => 'بنر باریک بالای همه صفحات', 'size' => '1200×90'],
        'home_top' => ['name' => 'صفحه اصلی — زیر اسلایدر', 'hint' => 'بنر عریض بعد از بخش ویژه', 'size' => '1200×200'],
        'home_middle' => ['name' => 'صفحه اصلی — وسط', 'hint' => 'بین دسته‌بندی‌ها', 'size' => '1200×200'],
        'sidebar' => ['name' => 'سایدبار', 'hint' => 'کنار اخبار و صفحه خبر', 'size' => '400×400'],
        'post_top' => ['name' => 'خبر — بالای متن', 'hint' => 'قبل از شروع متن خبر', 'size' => '800×150'],
        'post_middle' => ['name' => 'خبر — وسط متن', 'hint' => 'بعد از پاراگراف N ام', 'size' => '800×150'],
        'post_bottom' => ['name' => 'خبر — پایان متن', 'hint' => 'بعد از متن و قبل از نظرات', 'size' => '800×200'],
        'footer_top' => ['name' => 'بالای فوتر', 'hint' => 'در همه صفحات', 'size' => '1200×150'],
        'popup' => ['name' => 'پاپ‌آپ (پنجره شناور)', 'hint' => 'با تاخیر و تکرار قابل تنظیم', 'size' => '600×400'],
        'floating' => ['name' => 'نوار شناور پایین صفحه', 'hint' => 'چسبان به پایین، قابل بستن', 'size' => '728×90'],
    ];
}

/** تم‌های تبلیغ */
function ad_themes(): array
{
    return [
        'neon' => ['name' => 'Neon Pulse', 'desc' => 'حاشیه‌ی نئونی تپنده'],
        'glass' => ['name' => 'Glass Shine', 'desc' => 'شیشه‌ای با برق عبوری'],
        'flow' => ['name' => 'Gradient Flow', 'desc' => 'حاشیه‌ی گرادیانی چرخان'],
        'glitch' => ['name' => 'Cyber Glitch', 'desc' => 'گلیچ سایبری با اسکن‌لاین'],
        'holo' => ['name' => 'Holographic', 'desc' => 'هولوگرافیک رنگین‌کمانی'],
        'aurora' => ['name' => 'Aurora', 'desc' => 'شفق قطبی متحرک'],
        'fire' => ['name' => 'Fire Embers', 'desc' => 'ذرات آتش در حال صعود'],
        'spotlight' => ['name' => 'Spotlight', 'desc' => 'نورافکن دنبال‌کننده‌ی موس'],
        'ribbon' => ['name' => 'VIP Ribbon', 'desc' => 'روبان ویژه گوشه'],
        'minimal' => ['name' => 'Minimal', 'desc' => 'ساده و تمیز'],
    ];
}

function ad_formats(): array
{
    return [
        'card' => 'کارت متنی (ایموجی، عنوان، متن، دکمه، تصویر اختیاری)',
        'image' => 'بنر تصویری',
        'html' => 'کد HTML / اسکریپت (یکتانت، گوگل و ...)',
    ];
}

function home_section_names(): array
{
    return [
        'hero' => 'بخش ویژه (اسلایدر / مجله‌ای)',
        'latest' => 'آخرین اخبار + سایدبار',
        'categories' => 'بلوک دسته‌بندی‌ها',
        'trending' => 'پربازدیدترین‌ها',
        'telegram' => 'دعوت به کانال تلگرام',
        'shop' => 'بنر فروشگاه + محصولات ویژه',
    ];
}
