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
        'shop' => 'بنر فروشگاه + محصولات ویژه',
    ];
}
