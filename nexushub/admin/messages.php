<?php
require __DIR__ . '/inc/boot.php';
require_cap('messages');
if (is_post()) {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    switch ($_POST['act'] ?? '') {
        case 'read': q('UPDATE messages SET is_read = 1 WHERE id = ?', [$id]); break;
        case 'unread': q('UPDATE messages SET is_read = 0 WHERE id = ?', [$id]); break;
        case 'delete': q('DELETE FROM messages WHERE id = ?', [$id]); flash('پیام حذف شد.'); break;
        case 'read_all': q('UPDATE messages SET is_read = 1'); flash('همه پیام‌ها خوانده شدند.'); break;
    }
    redirect('admin/messages.php');
}
$total = (int) val('SELECT COUNT(*) FROM messages');
$pg = paginate($total, 20, (int) ($_GET['page'] ?? 1));
$list = rows("SELECT * FROM messages ORDER BY is_read ASC, created_at DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}");
adm_head('پیام‌های تماس', 'messages');
?>
<div class="card">
    <div class="card-head"><h3><?= adm_icon('inbox', 20) ?> صندوق پیام‌ها <small class="muted">(<?= fa_num((string) $total) ?>)</small></h3>
        <form method="post"><?= csrf_field() ?><button class="abtn abtn-ghost abtn-sm" name="act" value="read_all">علامت‌گذاری همه به عنوان خوانده‌شده</button></form></div>
    <?php if (!$list): ?><div class="empty-state"><span>📭</span>پیامی دریافت نشده است.</div><?php endif; ?>
    <?php foreach ($list as $m): ?>
        <div class="msg-box <?= $m['is_read'] ? '' : 'unread' ?>">
            <div class="cmt-meta">
                <span class="avatar avatar-letter" style="width:32px;height:32px"><?= e(mb_substr($m['name'], 0, 1)) ?></span>
                <b><?= e($m['name']) ?></b>
                <?php if ($m['email']): ?><a class="ltr" href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode('پاسخ: ' . ($m['subject'] ?: setting('site_title'))) ?>" style="color:var(--primary)"><?= e($m['email']) ?></a><?php endif; ?>
                <span><?= jdate('l j F Y H:i', $m['created_at']) ?></span>
                <?= $m['is_read'] ? '' : '<span class="badge b-hot">جدید</span>' ?>
            </div>
            <?php if ($m['subject']): ?><h4 style="margin:6px 0"><?= e($m['subject']) ?></h4><?php endif; ?>
            <p style="white-space:pre-wrap;margin:0 0 10px"><?= e($m['body']) ?></p>
            <form method="post" class="row-flex" style="gap:6px">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                <?php if ($m['email']): ?><a class="abtn abtn-primary abtn-xs" href="mailto:<?= e($m['email']) ?>">↩ پاسخ با ایمیل</a><?php endif; ?>
                <button class="abtn abtn-ghost abtn-xs" name="act" value="<?= $m['is_read'] ? 'unread' : 'read' ?>"><?= $m['is_read'] ? 'خوانده نشده' : '✔ خوانده شد' ?></button>
                <button class="abtn abtn-danger abtn-xs" name="act" value="delete" data-confirm="پیام حذف شود؟">حذف</button>
            </form>
        </div>
    <?php endforeach; ?>
    <?= pagination_html($pg, url('admin/messages.php')) ?>
</div>
<?php adm_foot();
