<?php
require __DIR__ . '/inc/boot.php';
require_cap('posts');
$me = current_user();

$id = (int) ($_GET['id'] ?? 0);
$post = $id ? row('SELECT * FROM posts WHERE id = ?', [$id]) : null;
if ($id && !$post) { flash('خبر یافت نشد.', 'error'); redirect('admin/posts.php'); }
if ($post && !can('edit_others') && (int) $post['author_id'] !== (int) $me['id']) { flash('شما فقط می‌توانید اخبار خودتان را ویرایش کنید.', 'error'); redirect('admin/posts.php'); }

$p = $post ?: [
    'id' => 0, 'title' => '', 'slug' => '', 'excerpt' => '', 'content' => '', 'cover' => '', 'category_id' => null, 'status' => 'draft',
    'is_featured' => 0, 'is_breaking' => 0, 'allow_comments' => 1, 'source_name' => '', 'source_url' => '', 'meta_title' => '', 'meta_desc' => '',
    'published_at' => null, 'telegram_sent' => 0, 'author_id' => $me['id'],
];
$errors = [];

if (is_post()) {
    csrf_check();
    $do = $_POST['do'] ?? 'draft';
    $p['title'] = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 250);
    $p['slug'] = slugify(trim((string) ($_POST['slug'] ?? '')) ?: $p['title']);
    $p['excerpt'] = mb_substr(trim((string) ($_POST['excerpt'] ?? '')), 0, 600);
    $p['content'] = content_to_store(sanitize_html((string) ($_POST['content'] ?? '')));
    $p['cover'] = trim((string) ($_POST['cover'] ?? '')) ?: null;
    $p['category_id'] = (int) ($_POST['category_id'] ?? 0) ?: null;
    $p['is_featured'] = !empty($_POST['is_featured']) ? 1 : 0;
    $p['is_breaking'] = !empty($_POST['is_breaking']) ? 1 : 0;
    $p['allow_comments'] = !empty($_POST['allow_comments']) ? 1 : 0;
    $p['source_name'] = mb_substr(trim((string) ($_POST['source_name'] ?? '')), 0, 120) ?: null;
    $p['source_url'] = safe_link((string) ($_POST['source_url'] ?? '')) ?: null;
    $p['meta_title'] = mb_substr(trim((string) ($_POST['meta_title'] ?? '')), 0, 250) ?: null;
    $p['meta_desc'] = mb_substr(trim((string) ($_POST['meta_desc'] ?? '')), 0, 320) ?: null;
    $pubInput = trim((string) ($_POST['published_at'] ?? ''));
    $pubAt = $pubInput !== '' ? jalali_to_datetime($pubInput) : null;
    if ($pubInput !== '' && !$pubAt) $errors[] = 'تاریخ انتشار معتبر نیست (نمونه: 1405/08/12 14:30).';
    if ($p['title'] === '') $errors[] = 'عنوان خبر را وارد کنید.';

    $p['status'] = $do === 'publish' ? 'published' : 'draft';
    $p['published_at'] = $pubAt ?: ($p['status'] === 'published' ? ($post['published_at'] ?? null) ?: now() : ($post['published_at'] ?? null));
    if ($p['status'] === 'published' && !$pubAt && $post && $post['status'] === 'draft') $p['published_at'] = now();

    if (!$errors) {
        $p['slug'] = unique_slug('posts', $p['slug'], (int) $p['id']);
        $data = [
            'title' => $p['title'], 'slug' => $p['slug'], 'excerpt' => $p['excerpt'], 'content' => $p['content'], 'cover' => $p['cover'],
            'category_id' => $p['category_id'], 'status' => $p['status'], 'is_featured' => $p['is_featured'], 'is_breaking' => $p['is_breaking'],
            'allow_comments' => $p['allow_comments'], 'source_name' => $p['source_name'], 'source_url' => $p['source_url'],
            'meta_title' => $p['meta_title'], 'meta_desc' => $p['meta_desc'], 'published_at' => $p['published_at'], 'updated_at' => now(),
        ];
        if ($p['id']) {
            update('posts', $data, (int) $p['id']);
        } else {
            $data += ['author_id' => $me['id'], 'created_at' => now(), 'views' => 0];
            $p['id'] = insert('posts', $data);
        }
        sync_tags((int) $p['id'], (string) ($_POST['tags'] ?? ''));

        $msg = $p['status'] === 'published' ? (strtotime($p['published_at']) > time() ? 'خبر زمان‌بندی شد ⏰' : 'خبر منتشر شد 🚀') : 'پیش‌نویس ذخیره شد 💾';
        flash($msg);

        // ارسال به تلگرام
        if (!empty($_POST['send_telegram']) && $p['status'] === 'published') {
            if (strtotime($p['published_at']) > time()) {
                flash('ارسال به تلگرام فقط برای اخبار منتشرشده (نه زمان‌بندی‌شده) انجام می‌شود.', 'warn');
            } else {
                $full = row(POST_SELECT . ' WHERE p.id = ?', [$p['id']]);
                $r = telegram_send_post($full);
                if (!empty($r['ok'])) { q('UPDATE posts SET telegram_sent = 1 WHERE id = ?', [$p['id']]); flash('خبر در کانال تلگرام ارسال شد ✈️'); }
                else flash('ارسال به تلگرام ناموفق: ' . ($r['description'] ?? 'خطا'), 'error');
            }
        }
        redirect('admin/post-edit.php?id=' . $p['id']);
    }
}

$tagsCsv = $p['id'] ? implode(',', array_column(post_tags((int) $p['id']), 'name')) : '';
if (is_post() && isset($_POST['tags'])) $tagsCsv = (string) $_POST['tags'];
$isPub = $p['status'] === 'published';
$tgReady = trim((string) setting('tg_bot_token')) !== '';

adm_head($p['id'] ? 'ویرایش خبر' : 'افزودن خبر جدید', $p['id'] ? 'posts' : 'post-new');
?>
<form method="post" id="postForm">
    <?= csrf_field() ?>
    <?php foreach ($errors as $er): ?><div class="alert alert-warn"><?= e($er) ?></div><?php endforeach; ?>
    <div class="split">
        <div>
            <div class="card">
                <?= f_text('title', 'عنوان خبر', $p['title'], ['required' => true, 'maxlength' => 250, 'placeholder' => 'یک تیتر جذاب بنویسید...', 'id' => 'fTitle', 'attrs' => 'class="title-input"']) ?>
                <label class="af"><span class="af-label">نامک (آدرس) <small class="muted ltr" style="margin-right:auto"><?= e(site_url('post/')) ?>…</small></span>
                    <input type="text" name="slug" value="<?= e($p['slug']) ?>" data-slug-from="#fTitle" placeholder="به صورت خودکار از عنوان ساخته می‌شود"></label>
                <div class="af"><span class="af-label">متن خبر</span>
                    <textarea name="content" data-editor data-key="post-<?= (int) $p['id'] ?>" placeholder="متن خبر را اینجا بنویسید... (می‌توانید تصویر را بکشید و رها کنید یا Paste کنید)"><?= e(content_for_display((string) $p['content'])) ?></textarea>
                </div>
                <?= f_textarea('excerpt', 'خلاصه خبر (لید)', (string) $p['excerpt'], ['rows' => 3, 'maxlength' => 600, 'hint' => 'در کارت‌ها و زیر تیتر نمایش داده می‌شود. خالی بگذارید تا خودکار ساخته شود.']) ?>
            </div>
            <div class="card">
                <div class="card-head"><h3><?= icon('search', 18) ?> سئو و پیش‌نمایش گوگل</h3></div>
                <div class="seo-preview mb">
                    <div class="sp-url"><?= e(site_url('post/')) ?><span id="spSlug"><?= e($p['slug']) ?></span></div>
                    <div class="sp-title" id="spTitle"><?= e($p['meta_title'] ?: $p['title'] ?: 'عنوان خبر') ?></div>
                    <div class="sp-desc" id="spDesc"><?= e($p['meta_desc'] ?: $p['excerpt'] ?: 'توضیحات خبر در نتایج جستجو اینجا نمایش داده می‌شود.') ?></div>
                </div>
                <div class="grid g2">
                    <?= f_text('meta_title', 'عنوان سئو (اختیاری)', (string) $p['meta_title'], ['maxlength' => 250, 'id' => 'fMetaTitle']) ?>
                    <?= f_text('source_name', 'نام منبع', (string) $p['source_name'], ['placeholder' => 'مثلاً GamesRadar+']) ?>
                </div>
                <?= f_textarea('meta_desc', 'توضیحات سئو (اختیاری)', (string) $p['meta_desc'], ['rows' => 2, 'maxlength' => 320, 'attrs' => 'id="fMetaDesc"']) ?>
                <?= f_text('source_url', 'لینک منبع', (string) $p['source_url'], ['ltr' => true, 'placeholder' => 'https://']) ?>
            </div>
        </div>

        <div class="split-side">
            <div class="card">
                <div class="card-head"><h3><?= icon('send', 18) ?> انتشار</h3><?= $p['id'] ? status_badge($p['status'], $p['published_at']) : '<span class="badge">جدید</span>' ?></div>
                <?= f_jdate('published_at', 'تاریخ و ساعت انتشار (شمسی)', $p['published_at'], 'خالی = همین الان. تاریخ آینده = زمان‌بندی خودکار') ?>
                <?= f_switch('is_featured', 'خبر ویژه ⭐', (bool) $p['is_featured'], 'نمایش در اسلایدر / بخش ویژه صفحه اصلی') ?>
                <?= f_switch('is_breaking', 'خبر فوری ⚡', (bool) $p['is_breaking'], 'نمایش در نوار اخبار فوری') ?>
                <?= f_switch('allow_comments', 'امکان ثبت نظر', (bool) $p['allow_comments']) ?>
                <label class="sw-row"><span class="sw-text"><b>ارسال به کانال تلگرام ✈️</b><small><?= $tgReady ? ($p['telegram_sent'] ? 'قبلاً ارسال شده؛ با فعال‌سازی دوباره ارسال می‌شود' : 'با ربات به کانال ارسال می‌شود') : 'ابتدا توکن ربات را در تنظیمات وارد کنید' ?></small></span>
                    <input type="checkbox" class="sw" name="send_telegram" value="1" <?= $tgReady ? (setting_on('tg_autopost') && !$p['telegram_sent'] && !$isPub ? 'checked' : '') : 'disabled' ?>></label>
                <div class="form-actions mt">
                    <button class="abtn abtn-primary" style="flex:1" name="do" value="publish" type="submit"><?= icon('send', 17) ?> <?= $isPub ? 'بروزرسانی' : 'انتشار' ?></button>
                    <button class="abtn abtn-ghost" name="do" value="draft" type="submit"><?= $isPub ? 'به پیش‌نویس' : 'ذخیره پیش‌نویس' ?></button>
                </div>
                <?php if ($p['id']): ?>
                    <div class="row-flex mt" style="justify-content:space-between">
                        <a class="abtn abtn-ghost abtn-sm" href="<?= e(post_url($p)) ?><?= $isPub ? '' : '?preview=1' ?>" target="_blank"><?= icon('eye', 15) ?> پیش‌نمایش</a>
                        <span class="muted small">بازدید: <?= fa_num((string) ($post['views'] ?? 0)) ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card">
                <div class="card-head"><h3><?= adm_icon('image', 18) ?> تصویر شاخص</h3></div>
                <?= f_image('cover', '', $p['cover'], 'پیشنهاد: ۱۲۰۰×۷۵۰ پیکسل') ?>
            </div>
            <div class="card">
                <div class="card-head"><h3><?= adm_icon('folder', 18) ?> دسته‌بندی و برچسب</h3></div>
                <label class="af"><span class="af-label">دسته‌بندی</span>
                    <select name="category_id"><option value="0">— بدون دسته —</option>
                        <?php foreach (get_categories() as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $p['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['icon'] . ' ' . $c['name']) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <div class="af"><span class="af-label">برچسب‌ها <small class="muted">(Enter یا ویرگول)</small></span>
                    <div class="tag-input" data-tag-input><input type="hidden" name="tags" value="<?= e($tagsCsv) ?>"><input type="text" placeholder="مثلاً GTA VI"></div>
                </div>
            </div>
            <div class="card">
                <div class="card-head"><h3><?= icon('sparkle', 18) ?> شورت‌کدها</h3></div>
                <p class="small muted" style="margin:0">در متن خبر می‌توانید از این کدها استفاده کنید:</p>
                <p class="small ltr" style="margin:6px 0 0;line-height:2.2">[video src="uploads/…" title="…"]<br><small class="muted" style="font-family:Vazirmatn">↑ با دکمه‌ی «آپلود ویدیو» در نوار ابزار ساخته می‌شود (تا ۵۰۰ مگابایت)</small><br>[telegram post="NexusHubIR/123"]<br>[aparat id="abc12"]<br>[youtube id="dQw4w9WgXcQ"]</p>
            </div>
        </div>
    </div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var t = document.getElementById('fTitle'), mt = document.getElementById('fMetaTitle'), md = document.getElementById('fMetaDesc'),
        ex = document.querySelector('[name=excerpt]'), sl = document.querySelector('[name=slug]');
    function upd() {
        document.getElementById('spTitle').textContent = mt.value || t.value || 'عنوان خبر';
        document.getElementById('spDesc').textContent = md.value || ex.value || 'توضیحات خبر در نتایج جستجو اینجا نمایش داده می‌شود.';
        document.getElementById('spSlug').textContent = sl.value;
    }
    [t, mt, md, ex, sl].forEach(function (el) { el.addEventListener('input', upd); });
    document.addEventListener('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); document.querySelector('button[name=do][value=<?= $isPub ? 'publish' : 'draft' ?>]').click(); } });
});
</script>
<?php adm_foot(['editor.js']);
