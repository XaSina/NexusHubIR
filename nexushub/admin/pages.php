<?php
require __DIR__ . '/inc/boot.php';
require_cap('pages');
if (is_post()) {
    csrf_check();
    if (!empty($_POST['delete_id'])) { q('DELETE FROM pages WHERE id = ?', [(int) $_POST['delete_id']]); flash('صفحه حذف شد.'); }
    redirect('admin/pages.php');
}
$list = rows('SELECT * FROM pages ORDER BY sort_order, id');
adm_head('صفحات', 'pages');
?>
<div class="card">
    <div class="card-head"><h3><?= adm_icon('pages', 20) ?> صفحات ثابت</h3><a class="abtn abtn-primary abtn-sm" href="page-edit.php"><?= adm_icon('plus', 16) ?> صفحه جدید</a></div>
    <?php if (!$list): ?><div class="empty-state"><span>📄</span>صفحه‌ای ساخته نشده.</div><?php else: ?>
    <div class="tbl-wrap"><table class="tbl">
        <thead><tr><th>عنوان</th><th class="hide-m">آدرس</th><th>وضعیت</th><th class="hide-m">فوتر</th><th class="hide-m">بروزرسانی</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($list as $p): ?>
            <tr>
                <td><a class="t-title" href="page-edit.php?id=<?= (int) $p['id'] ?>"><?= e($p['title']) ?></a></td>
                <td class="hide-m ltr small muted">/page/<?= e($p['slug']) ?></td>
                <td><?= status_badge($p['status']) ?></td>
                <td class="hide-m"><?= $p['show_in_footer'] ? '✔' : '—' ?></td>
                <td class="hide-m small muted"><?= jdate('j F Y', $p['updated_at']) ?></td>
                <td><div class="actions">
                    <a class="adm-ib" href="page-edit.php?id=<?= (int) $p['id'] ?>"><?= adm_icon('edit', 16) ?></a>
                    <a class="adm-ib" href="<?= e(url('page/' . rawurlencode($p['slug']))) ?>" target="_blank"><?= icon('eye', 16) ?></a>
                    <form method="post"><?= csrf_field() ?><button class="adm-ib" name="delete_id" value="<?= (int) $p['id'] ?>" data-confirm="این صفحه حذف شود؟"><?= adm_icon('trash', 16) ?></button></form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
<?php adm_foot();
