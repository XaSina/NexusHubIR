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
        'card_style' => 'glass',      // glass | solid | outline | neon
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
    ];
}

/** تم‌های آماده */
function theme_presets(): array
{
    return [
        'nexus' => ['name' => 'Nexus Red', 'desc' => 'قرمز نئونی — تم اصلی کانال', 'primary' => '#ff1f3d', 'secondary' => '#ff7a3d', 'bg' => '#050507', 'light' => false],
        'blood' => ['name' => 'Blood Moon', 'desc' => 'قرمز تیره و سینمایی', 'primary' => '#c3002f', 'secondary' => '#ff2e63', 'bg' => '#070304', 'light' => false],
        'cyber' => ['name' => 'Cyber Blue', 'desc' => 'آبی سایبرپانک', 'primary' => '#00e1ff', 'secondary' => '#7c4dff', 'bg' => '#03060d', 'light' => false],
        'toxic' => ['name' => 'Toxic Green', 'desc' => 'سبز ماتریکسی', 'primary' => '#39ff14', 'secondary' => '#00ffa3', 'bg' => '#030805', 'light' => false],
        'royal' => ['name' => 'Royal Purple', 'desc' => 'بنفش و صورتی نئون', 'primary' => '#b14dff', 'secondary' => '#ff3df2', 'bg' => '#06030b', 'light' => false],
        'gold' => ['name' => 'Golden Hour', 'desc' => 'طلایی و نارنجی گرم', 'primary' => '#ffc53d', 'secondary' => '#ff7a1f', 'bg' => '#080602', 'light' => false],
        'ocean' => ['name' => 'Deep Ocean', 'desc' => 'فیروزه‌ای آرام', 'primary' => '#1de9b6', 'secondary' => '#2979ff', 'bg' => '#020a0c', 'light' => false],
        'snow' => ['name' => 'Snow Glass', 'desc' => 'تم روشن شیشه‌ای', 'primary' => '#e0102e', 'secondary' => '#ff6a3d', 'bg' => '#eef0f6', 'light' => true],
        'custom' => ['name' => 'سفارشی', 'desc' => 'رنگ‌های دلخواه خودت', 'primary' => null, 'secondary' => null, 'bg' => null, 'light' => false],
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
    ];
}
