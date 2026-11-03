<?php
/**
 * داده‌های نمونه برای نصب اولیه
 */

function seed_demo(int $authorId): void
{
    $cats = [
        ['گیم', 'game', '🎮', '#ff1f3d', 'اخبار، نقد و بررسی و لیست‌های برتر دنیای بازی‌های ویدیویی'],
        ['تکنولوژی', 'tech', '💻', '#00e1ff', 'آخرین اخبار موبایل، سخت‌افزار، هوش مصنوعی و گجت‌ها'],
        ['ادیت', 'edit', '🎬', '#b14dff', 'آموزش و ترفندهای ادیت ویدیو و عکس'],
        ['موزیک', 'music', '🎵', '#1de9b6', 'تازه‌ترین آهنگ‌ها و اخبار دنیای موسیقی'],
        ['سرگرمی', 'fun', '🎉', '#ffc53d', 'فیلم، سریال، میم و هر چیزی که حالتو خوب کنه'],
        ['اخبار روز', 'news', '📰', '#ff7a3d', 'مهم‌ترین اتفاقات روز'],
    ];
    $catIds = [];
    foreach ($cats as $i => [$name, $slug, $icon, $color, $desc]) {
        $catIds[$slug] = insert('categories', [
            'name' => $name, 'slug' => $slug, 'icon' => $icon, 'color' => $color, 'description' => $desc,
            'sort_order' => $i, 'show_on_home' => 1, 'created_at' => now(),
        ]);
    }

    $posts = [
        [
            'title' => '۱۰ بازی برتر Resident Evil از دید رسانه GamesRadar+',
            'cat' => 'game', 'cover' => 're.svg', 'featured' => 1, 'breaking' => 1, 'views' => 1840, 'hours' => 2,
            'excerpt' => 'رسانه GamesRadar+ فهرست ۱۰ بازی برتر سری Resident Evil را منتشر کرد؛ رزیدنت ایول ۴ همچنان در صدر است.',
            'tags' => 'Resident Evil, کپکام, ترسناک, لیست برتر',
            'source' => ['GamesRadar+', 'https://www.gamesradar.com'],
            'content' => '<p>سری <strong>Resident Evil</strong> یکی از محبوب‌ترین و تاثیرگذارترین فرنچایزهای تاریخ بازی‌های ویدیویی است. رسانه GamesRadar+ به تازگی لیستی از ۱۰ بازی برتر این مجموعه منتشر کرده که مثل همیشه بحث‌های زیادی بین طرفداران به راه انداخته است.</p><h2>فهرست کامل</h2><ol><li>Resident Evil 4</li><li>Resident Evil Requiem</li><li>Resident Evil Remake</li><li>Resident Evil 2</li><li>Resident Evil 7: Biohazard</li><li>Resident Evil 2 Remake</li><li>Resident Evil 4 Remake</li><li>Resident Evil – Code: Veronica X</li><li>Resident Evil Village</li><li>Resident Evil 3: Nemesis</li></ol><p>نسخه اصلی <em>Resident Evil 4</em> با تغییر زاویه دوربین و گیم‌پلی اکشن‌محور، مسیر کل صنعت بازی‌های سوم شخص را تغییر داد و جای تعجب ندارد که در صدر این فهرست قرار گرفته است.</p><blockquote>به نظر شما کدوم نسخه باید رتبه اول باشه؟ نظرتون رو زیر همین خبر بنویسید 👇</blockquote><p>حضور Requiem در رتبه دوم نشان می‌دهد که کپکام هنوز هم می‌تواند با هر نسخه جدید، طرفداران قدیمی و جدید را شگفت‌زده کند.</p>',
        ],
        [
            'title' => 'نگاهی به تابلوی Vice City در میامی؛ جزئیات تازه از دنیای GTA VI',
            'cat' => 'game', 'cover' => 'vice.svg', 'featured' => 1, 'breaking' => 1, 'views' => 2650, 'hours' => 5,
            'excerpt' => 'تصاویر جدیدی از محیط‌های شهر Vice City منتشر شده که نشان می‌دهد راکستار چقدر روی جزئیات دنیای GTA VI وسواس داشته است.',
            'tags' => 'GTA VI, راک‌استار, Vice City, جهان باز',
            'content' => '<p>طرفداران سری <strong>GTA</strong> این روزها حسابی سرگرم بررسی تصاویر و جزئیات منتشر شده از شهر <strong>Vice City</strong> هستند. تابلوی معروف شهر که الهام گرفته از میامی واقعی است، حالا با جزئیات خیره‌کننده‌ای بازسازی شده است.</p><h2>چه چیزهایی دیدیم؟</h2><ul><li>جاده‌های ساحلی با نورپردازی واقع‌گرایانه هنگام غروب</li><li>پوشش گیاهی متراکم و متنوع در حومه شهر</li><li>تابلوها و بیلبوردهای طنزآمیز به سبک همیشگی راکستار</li></ul><p>به نظر می‌رسد GTA VI بزرگ‌ترین و زنده‌ترین جهانی باشد که راکستار تا امروز ساخته است.</p><p>[telegram post="NexusHubIR/1"]</p>',
        ],
        [
            'title' => 'هوش مصنوعی روی گوشی؛ پردازنده‌های جدید چه چیزی را تغییر می‌دهند؟',
            'cat' => 'tech', 'cover' => 'tech.svg', 'featured' => 1, 'breaking' => 0, 'views' => 980, 'hours' => 9,
            'excerpt' => 'نسل جدید تراشه‌های موبایل با واحدهای پردازش عصبی قدرتمند، اجرای مدل‌های هوش مصنوعی را بدون اینترنت ممکن کرده‌اند.',
            'tags' => 'هوش مصنوعی, پردازنده, موبایل',
            'content' => '<p>سازندگان تراشه در سال جاری تمرکز ویژه‌ای روی <strong>NPU</strong> یا واحد پردازش عصبی داشته‌اند. نتیجه این تمرکز، اجرای سریع مدل‌های زبانی و تصویری به صورت کاملاً آفلاین روی گوشی است.</p><h3>مزایا برای کاربر</h3><ul><li>حریم خصوصی بیشتر چون داده‌ها از گوشی خارج نمی‌شوند</li><li>سرعت پاسخ‌دهی بالاتر</li><li>مصرف باتری بهینه‌تر نسبت به نسل قبل</li></ul><p>به احتمال زیاد در یکی دو سال آینده، بسیاری از قابلیت‌های هوش مصنوعی بدون نیاز به اتصال اینترنت در دسترس خواهند بود.</p>',
        ],
        [
            'title' => 'مقایسه پرچمداران ۲۰۲۶؛ کدام گوشی ارزش خرید بیشتری دارد؟',
            'cat' => 'tech', 'cover' => 'phone.svg', 'featured' => 1, 'breaking' => 0, 'views' => 1320, 'hours' => 20,
            'excerpt' => 'در این مقاله پرچمداران امسال را از نظر دوربین، باتری، نمایشگر و قیمت با هم مقایسه کرده‌ایم.',
            'tags' => 'موبایل, مقایسه, خرید گوشی',
            'content' => '<p>بازار گوشی‌های پرچمدار امسال رقابتی‌تر از همیشه است. در این مطلب سعی کردیم بدون تعارف، نقاط قوت و ضعف هر گوشی را بررسی کنیم.</p><h2>دوربین</h2><p>سنسورهای یک اینچی حالا به یک استاندارد تبدیل شده‌اند و عکاسی در شب تقریباً به کیفیت عکاسی در روز رسیده است.</p><h2>باتری</h2><p>باتری‌های سیلیکون-کربن ظرفیت را بدون افزایش ضخامت بالا برده‌اند.</p><table><thead><tr><th>ویژگی</th><th>امتیاز</th></tr></thead><tbody><tr><td>دوربین</td><td>۹.۵</td></tr><tr><td>باتری</td><td>۹</td></tr><tr><td>نمایشگر</td><td>۹.۸</td></tr></tbody></table>',
        ],
        [
            'title' => '۵ ترفند حرفه‌ای در افترافکت که ادیت‌هایتان را متحول می‌کند',
            'cat' => 'edit', 'cover' => 'edit.svg', 'featured' => 1, 'breaking' => 0, 'views' => 760, 'hours' => 30,
            'excerpt' => 'با این پنج ترفند ساده اما کاربردی در After Effects، ادیت‌های خود را یک سطح بالاتر ببرید.',
            'tags' => 'افترافکت, آموزش ادیت, موشن',
            'content' => '<p>اگر تازه وارد دنیای ادیت شده‌اید یا مدتی است با <strong>After Effects</strong> کار می‌کنید، این ترفندها حتماً به کارتان می‌آیند.</p><ol><li><strong>Graph Editor</strong> را جدی بگیرید؛ نرمی حرکت‌ها از اینجا می‌آید.</li><li>از <strong>Motion Blur</strong> برای طبیعی‌تر شدن انیمیشن‌ها استفاده کنید.</li><li>با <strong>Null Object</strong> کنترل چند لایه را یکجا در دست بگیرید.</li><li>Expressionهای ساده مثل <code>wiggle(5,20)</code> را یاد بگیرید.</li><li>همیشه پروژه را با <strong>Pre-compose</strong> مرتب نگه دارید.</li></ol><p>ادیت‌های خود را برای ما در گروه تلگرام بفرستید تا در کانال منتشر کنیم 🔥</p>',
        ],
        [
            'title' => 'پرشنوندترین آهنگ‌های هفته؛ از پاپ تا رپ',
            'cat' => 'music', 'cover' => 'music.svg', 'featured' => 0, 'breaking' => 0, 'views' => 540, 'hours' => 40,
            'excerpt' => 'لیست پرشنوندترین آهنگ‌های این هفته در پلتفرم‌های پخش آنلاین را ببینید.',
            'tags' => 'موزیک, پلی‌لیست, رپ',
            'content' => '<p>هر هفته لیستی از پرشنوندترین آهنگ‌ها را برایتان آماده می‌کنیم. این هفته ژانر رپ حضور پررنگی در لیست داشته است.</p><ul><li>ترک اول — رکورد تازه در اولین روز انتشار</li><li>ترک دوم — همکاری غافلگیرکننده دو خواننده محبوب</li><li>ترک سوم — بازگشت قدرتمند یک گروه قدیمی</li></ul><p>پلی‌لیست کامل را در کانال تلگرام NEXUS دنبال کنید 🎧</p>',
        ],
        [
            'title' => 'جنگ کارت‌های گرافیک؛ نسل جدید RTX و Radeon رو در رو',
            'cat' => 'tech', 'cover' => 'gpu.svg', 'featured' => 0, 'breaking' => 1, 'views' => 1580, 'hours' => 52,
            'excerpt' => 'بنچمارک‌های اولیه نسل جدید کارت‌های گرافیک منتشر شد؛ رقابت این بار نزدیک‌تر از همیشه است.',
            'tags' => 'کارت گرافیک, RTX, Radeon, سخت‌افزار',
            'content' => '<p>بنچمارک‌های اولیه نسل تازه کارت‌های گرافیک منتشر شد و نتایج نشان می‌دهد که رقابت در بازه قیمتی میان‌رده بسیار نزدیک است.</p><h2>رهگیری پرتو و فریم‌سازی</h2><p>هر دو سازنده روی تکنولوژی‌های <strong>Frame Generation</strong> و ارتقای هوشمند تصویر سرمایه‌گذاری سنگینی کرده‌اند.</p><p>اگر قصد ارتقا دارید، پیشنهاد می‌کنیم تا انتشار بررسی‌های کامل صبر کنید.</p>',
        ],
        [
            'title' => 'سریال‌هایی که این پاییز نباید از دست بدهید',
            'cat' => 'fun', 'cover' => 'fun.svg', 'featured' => 0, 'breaking' => 0, 'views' => 690, 'hours' => 70,
            'excerpt' => 'از درام‌های جنایی تا علمی‌تخیلی؛ لیست سریال‌های پاییزی NEXUS را ببینید.',
            'tags' => 'سریال, فیلم, سرگرمی',
            'content' => '<p>پاییز فصل سریال دیدن است! لیستی از بهترین سریال‌های این فصل را برایتان جمع کرده‌ایم.</p><ul><li>یک درام جنایی نفس‌گیر با بازیگران سرشناس</li><li>یک سریال علمی‌تخیلی با جلوه‌های ویژه خیره‌کننده</li><li>یک کمدی سبک برای شب‌های خسته</li></ul><p>شما کدوم رو می‌بینید؟ 🍿</p>',
        ],
        [
            'title' => 'فینال مسابقات ای‌اسپورت ۲۰۲۶؛ رکورد بیننده شکسته شد',
            'cat' => 'news', 'cover' => 'esport.svg', 'featured' => 0, 'breaking' => 1, 'views' => 1100, 'hours' => 90,
            'excerpt' => 'فینال امسال با بیش از ۸ میلیون بیننده همزمان، پربیننده‌ترین رویداد تاریخ ای‌اسپورت شد.',
            'tags' => 'ای‌اسپورت, مسابقات, گیم',
            'content' => '<p>فینال مسابقات جهانی ای‌اسپورت امسال با استقبال بی‌سابقه‌ای روبرو شد و رکورد تعداد بینندگان همزمان را جابجا کرد.</p><p>تیم قهرمان پس از یک سری نفس‌گیر پنج بازیه موفق به کسب جام شد.</p><blockquote>ای‌اسپورت دیگر یک سرگرمی نیست؛ یک صنعت میلیارد دلاری است.</blockquote>',
        ],
    ];

    $i = 0;
    foreach ($posts as $p) {
        $pub = date('Y-m-d H:i:s', time() - $p['hours'] * 3600);
        $slug = unique_slug('posts', slugify($p['title']));
        $id = insert('posts', [
            'title' => $p['title'], 'slug' => $slug, 'excerpt' => $p['excerpt'], 'content' => $p['content'],
            'cover' => 'assets/img/demo/' . $p['cover'], 'category_id' => $catIds[$p['cat']], 'author_id' => $authorId,
            'status' => 'published', 'is_featured' => $p['featured'], 'is_breaking' => $p['breaking'], 'allow_comments' => 1,
            'views' => $p['views'], 'source_name' => $p['source'][0] ?? null, 'source_url' => $p['source'][1] ?? null,
            'published_at' => $pub, 'created_at' => $pub, 'updated_at' => $pub,
        ]);
        sync_tags($id, $p['tags']);
        foreach (['🔥' => rand(5, 60), '❤️' => rand(2, 30), '😮' => rand(0, 10)] as $em => $cnt) {
            insert('reactions', ['post_id' => $id, 'emoji' => $em, 'cnt' => $cnt]);
        }
        if ($i === 0) {
            $cid = insert('comments', ['post_id' => $id, 'name' => 'علی', 'body' => 'به نظرم RE2 Remake باید بالاتر باشه 😍', 'status' => 'approved', 'created_at' => now()]);
            insert('comments', ['post_id' => $id, 'parent_id' => $cid, 'user_id' => $authorId, 'name' => 'NEXUS', 'body' => 'موافقیم! واقعا شاهکار بود 🔥', 'status' => 'approved', 'created_at' => now()]);
            insert('comments', ['post_id' => $id, 'name' => 'مهمان', 'email' => 'guest@example.com', 'body' => 'این نظر منتظر تایید مدیر است.', 'status' => 'pending', 'created_at' => now()]);
        }
        $i++;
    }

    // صفحات
    insert('pages', [
        'title' => 'درباره ما', 'slug' => 'about', 'status' => 'published', 'show_in_menu' => 0, 'show_in_footer' => 1, 'sort_order' => 1,
        'content' => '<h2>🌐 NEXUS | HUB</h2><p>گیم 🎮 | تکنولوژی 💻 | ادیت 🎬 | موزیک 🎵 | اخبار روز 📰 | سرگرمی 🎉</p><p>محتوای روزانه، کاربردی و متفاوت 🔥 — ما اینجاییم تا هر روز جدیدترین و جذاب‌ترین اخبار دنیای گیم و تکنولوژی را به ساده‌ترین شکل به دست شما برسانیم.</p><blockquote>عضویت در کامیونیتی NEXUS 💬</blockquote><ul><li>👑 مدیر: <a href="https://t.me/XaSina">@XaSina</a></li><li>📢 کانال: <a href="https://t.me/NexusHubIR">@NexusHubIR</a></li><li>💬 گروه: <a href="https://t.me/NexusHubIR_Group">@NexusHubIR_Group</a></li></ul>',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    insert('pages', [
        'title' => 'تعرفه تبلیغات', 'slug' => 'advertise', 'status' => 'published', 'show_in_menu' => 0, 'show_in_footer' => 1, 'sort_order' => 2,
        'content' => '<h2>تبلیغات در NEXUS | HUB</h2><p>برای ثبت سفارش تبلیغ در سایت و کانال تلگرام با مدیر در ارتباط باشید.</p><ul><li>بنر صفحه اصلی</li><li>بنر سایدبار</li><li>تبلیغ داخل خبر</li><li>پاپ‌آپ ویژه</li><li>پست ریپورتاژ</li></ul>',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // تبلیغات نمونه
    $end = date('Y-m-d H:i:s', strtotime('+7 days'));
    $ads = [
        ['title' => 'قرعه‌کشی ویژه', 'position' => 'home_top', 'theme' => 'flow', 'accent' => '#ff1f3d', 'emoji' => '🎰', 'heading' => 'قرعه‌کشی ویژه NEXUS شروع شد!', 'body' => 'با عضویت در کانال و گروه تلگرام در قرعه‌کشی شرکت کن 🧸', 'button_text' => 'شرکت در قرعه‌کشی', 'link' => 'https://t.me/NexusHubIR', 'countdown' => 1, 'end_at' => $end, 'badge' => 'ویژه'],
        ['title' => 'تبلیغ سایدبار', 'position' => 'sidebar', 'theme' => 'neon', 'accent' => '#ff1f3d', 'emoji' => '📢', 'heading' => 'اینجا جای تبلیغ شماست', 'body' => 'برند خود را به هزاران گیمر و علاقه‌مند تکنولوژی معرفی کنید.', 'button_text' => 'رزرو تبلیغ', 'link' => 'https://t.me/XaSina'],
        ['title' => 'تبلیغ وسط صفحه', 'position' => 'home_middle', 'theme' => 'aurora', 'accent' => '#b14dff', 'emoji' => '🎮', 'heading' => 'گروه گفتگوی گیمرها', 'body' => 'بحث، تبادل نظر و پیدا کردن هم‌تیمی در گروه NEXUS', 'button_text' => 'ورود به گروه', 'link' => 'https://t.me/NexusHubIR_Group'],
        ['title' => 'وسط خبر', 'position' => 'post_middle', 'theme' => 'glass', 'accent' => '#00e1ff', 'emoji' => '⚡', 'heading' => 'زودتر از همه باخبر شو', 'body' => 'اخبار فوری گیم و تکنولوژی رو در تلگرام دنبال کن.', 'button_text' => 'عضویت', 'link' => 'https://t.me/NexusHubIR'],
        ['title' => 'پایان خبر', 'position' => 'post_bottom', 'theme' => 'glitch', 'accent' => '#ff1f3d', 'emoji' => '👾', 'heading' => 'NEXUS GAMING CLUB', 'body' => 'کامیونیتی گیمرهای حرفه‌ای ایران', 'button_text' => 'بزن بریم', 'link' => 'https://t.me/NexusHubIR'],
        ['title' => 'بالای فوتر', 'position' => 'footer_top', 'theme' => 'holo', 'accent' => '#ff3df2', 'emoji' => '💎', 'heading' => 'پکیج تبلیغاتی VIP', 'body' => 'نمایش در تمام صفحات سایت + پست ویژه در کانال', 'button_text' => 'اطلاعات بیشتر', 'link' => 'https://t.me/XaSina'],
        ['title' => 'پاپ‌آپ نمونه (غیرفعال)', 'position' => 'popup', 'theme' => 'fire', 'accent' => '#ff6a00', 'emoji' => '🔥', 'heading' => 'تخفیف آتشین!', 'body' => 'این یک تبلیغ پاپ‌آپ نمونه است. از پنل فعالش کن.', 'button_text' => 'مشاهده', 'link' => 'https://t.me/NexusHubIR', 'is_active' => 0, 'countdown' => 1, 'end_at' => $end],
    ];
    foreach ($ads as $k => $a) {
        insert('ads', $a + [
            'format' => 'card', 'device' => 'all', 'new_tab' => 1, 'priority' => 10 - $k, 'is_active' => $a['is_active'] ?? 1,
            'impressions' => rand(200, 3000), 'clicks' => rand(10, 150), 'created_at' => now(),
        ]);
    }

    // آمار نمونه ۱۴ روز گذشته
    for ($d = 13; $d >= 0; $d--) {
        $v = rand(300, 1200) + (13 - $d) * 40;
        q('INSERT INTO stats_daily (day, views, visitors, post_views, ad_clicks) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE views = VALUES(views)',
            [date('Y-m-d', strtotime("-$d days")), $v, (int) ($v * .45), (int) ($v * .7), rand(5, 40)]);
    }

    insert('messages', ['name' => 'سارا', 'email' => 'sara@example.com', 'subject' => 'همکاری', 'body' => 'سلام، برای تبلیغ در سایت و کانال چطور باید اقدام کنم؟', 'is_read' => 0, 'created_at' => now()]);
}
