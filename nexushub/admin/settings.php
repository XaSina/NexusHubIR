<?php
require __DIR__ . '/inc/boot.php';
require_cap('*');

$keys = ['site_title', 'site_tagline', 'site_description', 'meta_keywords', 'contact_email', 'posts_per_page', 'archive_per_page', 'trending_days',
    'members_register', 'comments_enabled', 'comments_moderation', 'reactions_enabled', 'reactions_list',
    'tg_bot_token', 'tg_chat_id', 'tg_autopost', 'tg_template',
    'ad_label', 'post_middle_paragraph', 'maintenance', 'maintenance_text', 'head_code', 'footer_code'];

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
            <?= f_textarea('head_code', 'کد داخل &lt;head&gt; (مثلاً گوگل آنالیتیکس)', $s['head_code'], ['rows' => 5, 'code' => true]) ?>
            <?= f_textarea('footer_code', 'کد قبل از &lt;/body&gt;', $s['footer_code'], ['rows' => 5, 'code' => true]) ?>
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
