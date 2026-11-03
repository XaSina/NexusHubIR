<?php
require __DIR__ . '/inc/boot.php';
require_cap('pages');
$id = (int) ($_GET['id'] ?? 0);
$p = $id ? row('SELECT * FROM pages WHERE id = ?', [$id]) : null;
if ($id && !$p) redirect('admin/pages.php');
$p = $p ?: ['id' => 0, 'title' => '', 'slug' => '', 'content' => '', 'status' => 'published', 'show_in_menu' => 0, 'show_in_footer' => 1, 'sort_order' => 0];
if (is_post()) {
    csrf_check();
    $data = [
        'title' => mb_substr(trim((string) $_POST['title']), 0, 190),
        'content' => content_to_store(sanitize_html((string) ($_POST['content'] ?? ''))),
        'status' => ($_POST['status'] ?? '') === 'draft' ? 'draft' : 'published',
        'show_in_footer' => !empty($_POST['show_in_footer']) ? 1 : 0,
        'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        'updated_at' => now(),
    ];
    if ($data['title'] === '') { flash('عنوان را وارد کنید.', 'error'); redirect('admin/page-edit.php' . ($p['id'] ? '?id=' . $p['id'] : '')); }
    $data['slug'] = unique_slug('pages', slugify(trim((string) ($_POST['slug'] ?? '')) ?: $data['title']), (int) $p['id']);
    if ($p['id']) { update('pages', $data, (int) $p['id']); $nid = (int) $p['id']; }
    else { $data['created_at'] = now(); $nid = insert('pages', $data); }
    flash('صفحه ذخیره شد.');
    redirect('admin/page-edit.php?id=' . $nid);
}
adm_head($p['id'] ? 'ویرایش صفحه' : 'صفحه جدید', 'pages');
?>
<form method="post">
    <?= csrf_field() ?>
    <div class="split">
        <div class="card">
            <?= f_text('title', 'عنوان صفحه', $p['title'], ['required' => true, 'id' => 'pTitle', 'attrs' => 'class="title-input"']) ?>
            <?= f_text('slug', 'نامک (آدرس)', $p['slug'], ['ltr' => true, 'attrs' => 'data-slug-from="#pTitle"', 'placeholder' => 'about']) ?>
            <div class="af"><span class="af-label">محتوا</span><textarea name="content" data-editor data-key="page-<?= (int) $p['id'] ?>"><?= e(content_for_display((string) $p['content'])) ?></textarea></div>
        </div>
        <div class="split-side">
            <div class="card">
                <?= f_select('status', 'وضعیت', ['published' => 'منتشر شده', 'draft' => 'پیش‌نویس'], $p['status']) ?>
                <?= f_switch('show_in_footer', 'نمایش لینک در فوتر', (bool) $p['show_in_footer']) ?>
                <?= f_text('sort_order', 'ترتیب', (string) $p['sort_order'], ['type' => 'number']) ?>
                <p class="af-hint">برای افزودن به منوی بالا، از «ظاهر سایت ← منو» استفاده کنید.</p>
                <button class="abtn abtn-primary abtn-block" type="submit">ذخیره صفحه</button>
                <?php if ($p['id']): ?><a class="abtn abtn-ghost abtn-block mt" href="<?= e(url('page/' . rawurlencode($p['slug']))) ?>" target="_blank">مشاهده صفحه</a><?php endif; ?>
            </div>
        </div>
    </div>
</form>
<?php adm_foot(['editor.js']);
