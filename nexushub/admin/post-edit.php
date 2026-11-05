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
    $p['excerpt'] = mb_substr(trim(preg_replace('~\s+~u', ' ', strip_shortcodes((string) ($_POST['excerpt'] ?? '')))), 0, 600);
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
        if ($p['status'] === 'published' && strtotime((string) $p['published_at']) <= time()) indexnow_ping([site_url('post/' . rawurlencode($p['slug']))]);
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
                <div class="seo-check" id="seoCheck">
                    <div class="sc-score"><svg viewBox="0 0 44 44"><circle class="sc-track" cx="22" cy="22" r="19"/><circle class="sc-bar" id="scBar" cx="22" cy="22" r="19"/></svg><b id="scNum">0</b></div>
                    <div class="sc-body"><b class="sc-head" id="scHead">امتیاز سئوی این خبر</b><ul class="sc-list" id="scList"></ul></div>
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
                <?= f_image('cover', '', $p['cover'], 'پیشنهاد: ۱۲۰۰×۷۵۰ پیکسل', preset_images('cover')) ?>
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

    /* live SEO checklist: what Google cares about, checked while the post is written */
    var host = <?= json_encode((string) parse_url(site_url(''), PHP_URL_HOST)) ?>, form = document.getElementById('postForm');
    var faN = function (n) { return String(n).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; }); };
    function bodyHtml() { var ed = form.querySelector('[contenteditable="true"]'); return ed ? ed.innerHTML : (form.querySelector('[name=content]').value || ''); }
    function check() {
        var title = (mt.value || t.value).trim(), desc = (md.value || ex.value).trim(), slug = sl.value.trim();
        var html = bodyHtml(), box = document.createElement('div'); box.innerHTML = html;
        var text = (box.textContent || '').replace(/\s+/g, ' ').trim(), words = text ? text.split(' ').length : 0;
        var links = [].slice.call(box.querySelectorAll('a[href]')).map(function (a) { return a.getAttribute('href'); });
        var internal = links.filter(function (h) { return h.charAt(0) === '/' || (host && h.indexOf(host) > -1); }).length;
        var imgs = box.querySelectorAll('img').length, cover = (form.querySelector('[name=cover]') || {}).value;
        var cat = +((form.querySelector('[name=category_id]') || {}).value || 0);
        var tags = ((form.querySelector('[name=tags]') || {}).value || '').split(',').filter(function (x) { return x.trim(); }).length;
        var firstWord = (t.value.trim().split(/\s+/).filter(function (w) { return w.length > 2; })[0] || '');
        var lead = text.slice(0, 300);
        var L = function (s) { return [...s].length; };
        var items = [
            [L(title) >= 30 && L(title) <= 65, L(title) < 30 ? 'عنوان کوتاهه (' + faN(L(title)) + ' حرف) — ۳۰ تا ۶۵ حرف بهترینه' : L(title) > 65 ? 'عنوان بلنده (' + faN(L(title)) + ' حرف)؛ گوگل آخرش رو می‌بره — ۶۵ حرف یا کمتر' : 'طول عنوان عالیه (' + faN(L(title)) + ' حرف)'],
            [L(desc) >= 100 && L(desc) <= 165, !desc ? 'توضیحات سئو یا خلاصه خالیه — ۱ تا ۲ جمله‌ی جذاب بنویس' : L(desc) < 100 ? 'توضیحات کوتاهه (' + faN(L(desc)) + ' حرف) — ۱۰۰ تا ۱۶۵ حرف' : L(desc) > 165 ? 'توضیحات بلنده (' + faN(L(desc)) + ' حرف) — تا ۱۶۵ حرف' : 'طول توضیحات عالیه'],
            [words >= 300, words >= 300 ? 'متن کامله (' + faN(words) + ' کلمه)' : 'متن کوتاهه (' + faN(words) + ' کلمه) — ۳۰۰ کلمه به بالا شانس بیشتری داره'],
            [!!cover, cover ? 'عکس کاور داره' : 'عکس کاور نداره — برای گوگل دیسکاور و اشتراک‌گذاری لازمه'],
            [cat > 0, cat > 0 ? 'دسته‌بندی انتخاب شده' : 'دسته‌بندی انتخاب نشده'],
            [tags >= 2, tags >= 2 ? 'برچسب‌ها کافیه (' + faN(tags) + ')' : 'حداقل ۲ برچسب بذار (اسم بازی، شرکت، موضوع)'],
            [internal >= 1, internal >= 1 ? 'به خبرهای دیگه‌ی سایت لینک داده (' + faN(internal) + ')' : 'توی متن به یه خبر دیگه‌ی سایتت لینک بده'],
            [!slug || (/^[a-z0-9-]+$/.test(slug) && slug.length <= 60), !slug ? 'نامک خودکار از عنوان ساخته می‌شه' : /^[a-z0-9-]+$/.test(slug) ? (slug.length <= 60 ? 'نامک کوتاه و انگلیسیه' : 'نامک بلنده — کوتاه‌ترش کن') : 'نامک فارسی هم کار می‌کنه، ولی انگلیسی کوتاه (مثل gta-6-trailer) بهتره'],
            [!firstWord || lead.indexOf(firstWord) > -1, !firstWord || lead.indexOf(firstWord) > -1 ? 'موضوع عنوان توی پاراگراف اول هست' : 'کلمه‌ی اصلی عنوان («' + firstWord + '») رو توی پاراگراف اول هم بیار'],
            [imgs > 0, imgs > 0 ? 'داخل متن عکس داره' : 'یه عکس داخل متن خبر هم بذار']
        ];
        var ok = items.filter(function (i) { return i[0]; }).length, score = Math.round(ok / items.length * 100);
        document.getElementById('scList').innerHTML = items.map(function (i) { return '<li class="' + (i[0] ? 'ok' : 'no') + '">' + i[1].replace(/</g, '&lt;') + '</li>'; }).join('');
        document.getElementById('scNum').textContent = faN(score);
        var bar = document.getElementById('scBar'); bar.style.strokeDashoffset = String(119.4 * (1 - score / 100));
        var box2 = document.getElementById('seoCheck'); box2.dataset.level = score >= 80 ? 'good' : score >= 50 ? 'mid' : 'low';
        document.getElementById('scHead').textContent = score >= 80 ? 'سئوی این خبر عالیه ✅' : score >= 50 ? 'سئوی خوبیه؛ چند مورد مونده' : 'برای سئوی بهتر این‌ها رو درست کن';
    }
    var qT = 0, q = function () { clearTimeout(qT); qT = setTimeout(check, 250); };
    form.addEventListener('input', q); form.addEventListener('change', q);
    setInterval(check, 2500);   // the editor, cover picker and tag box change values without input events
    setTimeout(check, 400);
    document.addEventListener('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); document.querySelector('button[name=do][value=<?= $isPub ? 'publish' : 'draft' ?>]').click(); } });
});
</script>
<?php adm_foot(['editor.js']);
