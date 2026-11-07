<?php
require __DIR__ . '/inc/boot.php';
require_cap('*');

$keys = ['site_title', 'site_tagline', 'site_description', 'meta_keywords', 'contact_email', 'posts_per_page', 'archive_per_page', 'trending_days',
    'members_register', 'comments_enabled', 'comments_moderation', 'reactions_enabled', 'reactions_list',
    'tg_bot_token', 'tg_chat_id', 'tg_autopost', 'tg_template',
    'ad_label', 'post_middle_paragraph', 'maintenance', 'maintenance_text', 'head_code', 'footer_code', 'verify_files', 'og_image', 'google_verify', 'robots_extra', 'seo_home_title', 'seo_site_name', 'seo_index_authors', 'seo_https', 'indexnow_on', 'news_sitemap_on', 'seo_alt_names'];

if (is_post()) {
    csrf_check();
    $save = [];
    foreach ($keys as $k) if (isset($_POST[$k])) $save[$k] = trim((string) $_POST[$k]);
    $save['posts_per_page'] = (string) max(3, min(60, (int) ($save['posts_per_page'] ?? 12)));
    if (isset($save['archive_per_page'])) $save['archive_per_page'] = (string) max(3, min(60, (int) $save['archive_per_page']));
    $save['trending_days'] = (string) max(1, min(365, (int) ($save['trending_days'] ?? 30)));
    $save['post_middle_paragraph'] = (string) max(1, min(20, (int) ($save['post_middle_paragraph'] ?? 3)));
    if (isset($save['reactions_list'])) {
        $list = array_slice(array_unique(array_filter(array_map('trim', preg_split('~[,،\s]+~u', $save['reactions_list'])))), 0, 10);
        $save['reactions_list'] = implode(',', array_map(fn($x) => mb_substr($x, 0, 4), $list));
    }
    // a browser's saved-login autofill can drop the admin username/password into these fields: never store that
    if (isset($save['contact_email']) && $save['contact_email'] !== '' && !filter_var($save['contact_email'], FILTER_VALIDATE_EMAIL)) {
        unset($save['contact_email']);
        flash('ایمیل تماس معتبر نبود و تغییر نکرد؛ بقیه تنظیمات ذخیره شد.', 'warn');
    }
    if (isset($save['tg_bot_token']) && $save['tg_bot_token'] !== '' && !preg_match('~^\d{5,}:[\w-]{20,}$~', $save['tg_bot_token'])) {
        unset($save['tg_bot_token']);
        flash('توکن ربات تلگرام معتبر نبود و تغییر نکرد (شکل درست: 123456789:ABC...).', 'warn');
    }
    // extra code boxes: page-structure tags typed in by mistake (<html>, <head>, </body> ...) would break every page
    foreach (['head_code', 'footer_code'] as $ck) if (isset($save[$ck])) $save[$ck] = trim(preg_replace('~</?(html|head|body)\b[^>]*>|<!doctype[^>]*>~i', '', $save[$ck]));
    if (isset($save['google_verify'])) $save['google_verify'] = preg_replace('~[^A-Za-z0-9_-]~', '', preg_match('~content=["\']([^"\']+)~', $save['google_verify'], $gm) ? $gm[1] : $save['google_verify']);
    if (isset($save['robots_extra'])) $save['robots_extra'] = mb_substr(preg_replace('~[^\x20-\x7E\n]~', '', str_replace("\r", '', $save['robots_extra'])), 0, 2000);
    if (isset($save['verify_files'])) {   // file names only: letters, digits, - and _, ending in .txt / .html
        $vf = array_filter(array_map('trim', preg_split('~[\r\n,]+~', $save['verify_files'])), fn($f) => (bool) preg_match('~^[A-Za-z0-9_-]{3,80}\.(txt|html?)$~', $f));
        $save['verify_files'] = implode("\n", array_slice(array_unique($vf), 0, 20));
    }
    save_settings($save);
    flash('تنظیمات ذخیره شد ✅');
    redirect('admin/settings.php');
}
$s = settings_all();
adm_head('تنظیمات', 'settings');
?>
<form method="post">
<?= csrf_field() ?>
<div class="tabs" data-tabs>
    <button type="button" data-tab="general">⚙️ عمومی و سئو</button>
    <button type="button" data-tab="comments">💬 نظرات و واکنش‌ها</button>
    <button type="button" data-tab="telegram">✈️ ربات تلگرام</button>
    <button type="button" data-tab="ads">📢 تبلیغات</button>
    <button type="button" data-tab="advanced">🛠️ پیشرفته</button>
</div>

<div data-pane="general">
    <div class="grid g2">
        <div class="card">
            <div class="card-head"><h3>🌐 اطلاعات سایت</h3></div>
            <?= f_text('site_title', 'عنوان سایت', $s['site_title'], ['required' => true]) ?>
            <?= f_text('site_tagline', 'شعار سایت', $s['site_tagline']) ?>
            <?= f_text('contact_email', 'ایمیل تماس', $s['contact_email'], ['ltr' => true, 'placeholder' => 'info@example.com', 'attrs' => 'inputmode="email" autocomplete="off" data-lpignore="true"']) ?>
            <div class="grid g2">
                <?= f_text('posts_per_page', 'تعداد خبر در هر صفحه', $s['posts_per_page'], ['type' => 'number', 'min' => 3, 'max' => 60]) ?>
                <?= f_text('archive_per_page', 'تعداد خبر در هر صفحه (همه اخبار، دسته‌ها، برچسب‌ها و جستجو)', (string) ($s['archive_per_page'] ?? 9), ['type' => 'number', 'min' => 3, 'max' => 60]) ?>
                <?= f_text('trending_days', 'بازه «داغ‌ترین‌ها» (روز)', $s['trending_days'], ['type' => 'number', 'min' => 1, 'max' => 365]) ?>
            </div>
        </div>
        <div class="card">
            <div class="card-head"><h3>🔎 سئو</h3></div>
            <?= f_textarea('site_description', 'توضیحات متا (Meta Description)', $s['site_description'], ['rows' => 4, 'maxlength' => 320]) ?>
            <?= f_text('meta_keywords', 'کلمات کلیدی (با ویرگول)', $s['meta_keywords']) ?>
            <?= f_image('og_image', 'عکس پیش‌فرض اشتراک‌گذاری (وقتی لینک سایت رو جایی می‌فرستن)', $s['og_image'] ?? '', 'پیشنهاد: ۱۲۰۰×۶۳۰ پیکسل. خبرها عکس کاور خودشون رو دارن.') ?>
            <?= f_text('seo_home_title', 'عنوان صفحه‌ی اصلی در گوگل', (string) ($s['seo_home_title'] ?? ''), ['placeholder' => 'NexusHub | نکسوس هاب — اخبار گیم، تکنولوژی و فروشگاه', 'hint' => 'همون خط آبی بزرگی که توی نتیجه‌ی گوگل دیده می‌شه. خالی = «اسم سایت — شعار». اسم برند رو اول بنویس.']) ?>
            <?= f_text('seo_site_name', 'اسم سایت در گوگل', (string) ($s['seo_site_name'] ?? ''), ['placeholder' => 'NexusHub', 'hint' => 'اسم کوتاهی که گوگل بالای نتیجه و کنار عنوان‌ها نشون می‌ده؛ بدون | و —. خالی = اولین اسم برند پایین.']) ?>
            <?= f_switch('seo_index_authors', 'صفحه‌ی نویسنده‌ها هم توی گوگل بیاد', setting_on('seo_index_authors'), 'خاموش (پیشنهادی): صفحه‌ی نویسنده توی جستجوی اسم سایت جای صفحه‌ی اصلی رو نمی‌گیره') ?>
            <?= f_text('seo_alt_names', 'اسم‌های دیگه‌ی برند (با ویرگول)', (string) ($s['seo_alt_names'] ?? ''), ['placeholder' => 'NexusHub, Nexus Hub, نکسوس هاب', 'hint' => 'هر اسمی که ممکنه مردم برای پیدا کردن سایتت سرچ کنن. آدرس دامنه خودکار اضافه می‌شه.']) ?>
            <?= f_switch('seo_https', 'آدرس‌ها همیشه با https به گوگل داده بشن', setting_on('seo_https'), 'نقشه‌ی سایت، آدرس اصلی خبرها و لینک اشتراک با https ساخته می‌شن. فقط وقتی خاموشش کن که سایتت SSL نداره.') ?>
            <?= f_text('google_verify', 'کد تأیید گوگل (Search Console)', $s['google_verify'] ?? '', ['ltr' => true, 'placeholder' => 'متاتگ یا فقط کد content', 'hint' => 'کل متاتگی که گوگل داده رو بچسبون؛ خودش کد رو برمی‌داره.']) ?>
            <?= f_textarea('robots_extra', 'خطوط اضافه برای robots.txt', $s['robots_extra'] ?? '', ['rows' => 3, 'code' => true, 'placeholder' => 'Disallow: /private']) ?>
            <?= f_switch('news_sitemap_on', 'نقشه‌ی گوگل نیوز', setting_on('news_sitemap_on'), 'خبرهای ۲ روز اخیر برای بخش «اخبار» گوگل — در Search Console هم news-sitemap.xml رو ثبت کن') ?>
            <?= f_switch('indexnow_on', 'اطلاع فوری به Bing و Yandex (IndexNow)', setting_on('indexnow_on'), 'هر خبری که منتشر کنی همون لحظه به این موتورها اعلام می‌شه' . (setting('indexnow_last') ? ' · آخرین ارسال: ' . setting('indexnow_last') : '')) ?>
            <div class="row-flex">
                <a class="abtn abtn-sm" href="<?= e(url('robots.txt')) ?>" target="_blank" rel="noopener" dir="ltr">robots.txt ↗</a>
                <a class="abtn abtn-sm" href="<?= e(url('sitemap.xml')) ?>" target="_blank" rel="noopener" dir="ltr">sitemap.xml ↗</a>
                <a class="abtn abtn-sm" href="<?= e(url('news-sitemap.xml')) ?>" target="_blank" rel="noopener" dir="ltr">news-sitemap.xml ↗</a>
                <a class="abtn abtn-sm" href="https://search.google.com/test/rich-results?url=<?= e(rawurlencode(site_url(''))) ?>" target="_blank" rel="noopener">تست داده‌های ساختاریافته‌ی گوگل ↗</a>
            </div>
            <div class="alert alert-info small">نقشه سایت: <a class="ltr" href="<?= e(url('sitemap.xml')) ?>" target="_blank"><?= e(site_url('sitemap.xml')) ?></a></div>
        </div>
    </div>
</div>

<div data-pane="comments">
    <div class="grid g2">
        <div class="card">
            <div class="card-head"><h3>👥 اعضای سایت</h3></div>
            <?= f_switch('members_register', 'ثبت‌نام کاربران جدید در سایت فعال باشد', setting_on('members_register'), 'اگر خاموش باشد، فقط اعضای قبلی می‌توانند وارد شوند') ?>
            <hr style="border:0;border-top:1px solid var(--a-line);margin:14px 0">
            <div class="card-head"><h3>💬 نظرات</h3></div>
            <?= f_switch('comments_enabled', 'امکان ثبت نظر در سایت', setting_on('comments_enabled')) ?>
            <?= f_switch('comments_moderation', 'نظرات پس از تایید مدیر نمایش داده شوند', setting_on('comments_moderation'), 'پیشنهاد می‌شود روشن باشد تا جلوی اسپم گرفته شود') ?>
        </div>
        <div class="card">
            <div class="card-head"><h3>🔥 واکنش‌ها (ری‌اکشن شبیه تلگرام)</h3></div>
            <?= f_switch('reactions_enabled', 'نمایش واکنش‌ها زیر خبر', setting_on('reactions_enabled')) ?>
            <?= f_text('reactions_list', 'ایموجی‌ها (با ویرگول جدا کنید)', $s['reactions_list'], ['hint' => 'حداکثر ۱۰ ایموجی. مثال: 🔥,❤️,😂,😮,😭,👍']) ?>
        </div>
    </div>
</div>

<div data-pane="telegram">
    <div class="card">
        <div class="card-head"><h3>✈️ ارسال خودکار خبر به کانال تلگرام</h3><button type="button" class="abtn abtn-ghost abtn-sm" id="tgTest">تست اتصال</button></div>
        <div class="alert alert-info small">
            ۱. در تلگرام به <b class="ltr">@BotFather</b> پیام دهید و یک ربات بسازید و توکن را کپی کنید.<br>
            ۲. ربات را در کانال <b class="ltr"><?= e(telegram_handle() ?: '@YourChannel') ?></b> ادمین کنید (با دسترسی ارسال پیام).<br>
            ۳. توکن و آیدی کانال را اینجا وارد و ذخیره کنید، سپس «تست اتصال» را بزنید.<br>
            ⚠️ اگر هاست شما در ایران است، ممکن است به API تلگرام دسترسی نداشته باشد.
        </div>
        <div class="grid g2">
            <?= f_text('tg_bot_token', 'توکن ربات', $s['tg_bot_token'], ['ltr' => true, 'placeholder' => '123456:ABC-DEF...', 'attrs' => 'class="secret-input" autocomplete="off" spellcheck="false" data-lpignore="true"']) ?>
            <?= f_text('tg_chat_id', 'آیدی کانال', $s['tg_chat_id'], ['ltr' => true, 'placeholder' => '@NexusHubIR']) ?>
        </div>
        <?= f_switch('tg_autopost', 'به صورت پیش‌فرض تیک «ارسال به تلگرام» در خبر جدید فعال باشد', setting_on('tg_autopost')) ?>
        <?= f_textarea('tg_template', 'قالب پیام', $s['tg_template'], ['rows' => 6, 'hint' => 'متغیرها: {title} عنوان، {excerpt} خلاصه، {link} لینک خبر، {category} دسته، {channel} آیدی کانال. تگ‌های &lt;b&gt; و &lt;i&gt; مجاز است.']) ?>
    </div>
</div>

<div data-pane="ads">
    <div class="card">
        <div class="card-head"><h3>📢 تنظیمات عمومی تبلیغات</h3></div>
        <div class="grid g2">
            <?= f_text('ad_label', 'برچسب گوشه تبلیغ', $s['ad_label'], ['hint' => 'خالی بگذارید تا برچسب نمایش داده نشود']) ?>
            <?= f_text('post_middle_paragraph', 'تبلیغ وسط خبر بعد از پاراگراف شماره', $s['post_middle_paragraph'], ['type' => 'number', 'min' => 1, 'max' => 20]) ?>
        </div>
        <a class="abtn abtn-primary abtn-sm" href="ads.php">مدیریت تبلیغات ←</a>
    </div>
</div>

<div data-pane="advanced">
    <div class="grid g2">
        <div class="card">
            <div class="card-head"><h3>🔧 حالت تعمیر</h3></div>
            <?= f_switch('maintenance', 'فعال‌سازی حالت تعمیر', setting_on('maintenance'), 'سایت برای بازدیدکنندگان بسته می‌شود؛ شما (مدیر) همچنان سایت را می‌بینید') ?>
            <?= f_textarea('maintenance_text', 'پیام حالت تعمیر', $s['maintenance_text'], ['rows' => 3]) ?>
        </div>
        <div class="card">
            <div class="card-head"><h3>🧩 کدهای اضافه</h3></div>
            <?= f_textarea('head_code', 'کد داخل <head> (مثلاً گوگل آنالیتیکس)', $s['head_code'], ['rows' => 5, 'code' => true]) ?>
            <?= f_textarea('footer_code', 'کد قبل از </body>', $s['footer_code'], ['rows' => 5, 'code' => true]) ?>
        </div>
        <div class="card">
            <div class="card-head"><h3>🧯 آخرین خطاهای سایت</h3></div>
            <?php $errs = nx_recent_errors(15); if (!$errs): ?><p class="muted small">هیچ خطایی ثبت نشده ✅</p>
            <?php else: ?><p class="muted small">اگه صفحه‌ای «خطایی رخ داد» نشون داد، دلیلش اینجا نوشته می‌شه — یه اسکرین‌شات از همین کادر کافیه.</p>
            <pre class="ltr small" style="white-space:pre-wrap;max-height:260px;overflow:auto;margin:0;padding:12px;border-radius:12px;background:rgba(0,0,0,.35)"><?= e(implode("\n", $errs)) ?></pre><?php endif; ?>
        </div>
        <div class="card">
            <div class="card-head"><h3>✅ فایل‌های تأیید دامنه (اینماد و …)</h3></div>
            <?= f_textarea('verify_files', 'نام فایل‌ها (هر خط یکی)', (string) ($s['verify_files'] ?? ''), ['rows' => 4, 'code' => true, 'placeholder' => '35243901.txt', 'hint' => 'اسم فایلی که اینماد (یا هر سایت دیگه) گفته رو اینجا بنویس، مثلاً 35243901.txt — دیگه لازم نیست فایلی آپلود کنی؛ سایت خودش اون آدرس رو خالی تحویل می‌ده. حتماً دامنه‌ی ثبت‌شده در اینماد درست باشه: nexushubstore.ir']) ?>
            <div class="row-flex vf-links">
            <?php foreach (array_filter(array_map('trim', preg_split('~[\r\n,]+~', (string) ($s['verify_files'] ?? '')))) as $vfn): ?>
                <a class="abtn abtn-sm" href="<?= e(url($vfn)) ?>" target="_blank" rel="noopener" dir="ltr"><?= e($vfn) ?> ↗</a>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="sticky-save"><button class="abtn abtn-primary" type="submit"><?= icon('check', 18) ?> ذخیره تنظیمات</button></div>
</form>
<script>
document.getElementById('tgTest').addEventListener('click', function () {
    var b = this; b.disabled = true; b.textContent = 'در حال تست...';
    NXA.post('telegram_test', {}).then(function (j) { b.disabled = false; b.textContent = 'تست اتصال'; NXA.toast(j.ok ? j.message : ('خطا: ' + j.error), j.ok ? 'success' : 'error'); });
});
</script>
<?php adm_foot();
