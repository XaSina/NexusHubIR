<?php
require __DIR__ . '/inc/boot.php';
require_cap('comments');

if (is_post()) {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $act = (string) ($_POST['act'] ?? '');
    $ids = $id ? [$id] : array_map('intval', (array) ($_POST['ids'] ?? []));
    if ($act === 'reply' && $id) {
        $parent = row('SELECT * FROM comments WHERE id = ?', [$id]);
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($parent && $body !== '') {
            $me = current_user();
            insert('comments', [
                'post_id' => $parent['post_id'], 'parent_id' => $parent['parent_id'] ?: $parent['id'], 'user_id' => $me['id'],
                'name' => $me['display_name'] ?: $me['username'], 'body' => mb_substr($body, 0, 2000), 'status' => 'approved', 'ip' => client_ip(), 'created_at' => now(),
            ]);
            q("UPDATE comments SET status = 'approved' WHERE id = ?", [$id]);
            flash('پاسخ شما ثبت و منتشر شد.');
        }
    } elseif ($ids && in_array($act, ['approved', 'pending', 'spam'], true)) {
        q('UPDATE comments SET status = ? WHERE id IN (' . implode(',', $ids) . ')', [$act]);
        flash('وضعیت نظرات بروزرسانی شد.');
    } elseif ($ids && $act === 'delete') {
        $in = implode(',', $ids);
        q("DELETE FROM comments WHERE id IN ($in) OR parent_id IN ($in)");
        flash('نظر حذف شد.');
    } elseif ($act === 'purge_spam') {
        q("DELETE FROM comments WHERE status = 'spam'");
        flash('همه نظرات اسپم حذف شدند.');
    }
    redirect('admin/comments.php?status=' . urlencode((string) ($_GET['status'] ?? '')));
}

$status = (string) ($_GET['status'] ?? 'pending');
$valid = ['pending' => 'در انتظار', 'approved' => 'تایید شده', 'spam' => 'اسپم', 'all' => 'همه'];
if (!isset($valid[$status])) $status = 'pending';
$where = $status === 'all' ? '1=1' : 'c.status = ' . db()->quote($status);
$total = (int) val("SELECT COUNT(*) FROM comments c WHERE $where");
$pg = paginate($total, 25, (int) ($_GET['page'] ?? 1));
$list = rows("SELECT c.*, p.title AS post_title, p.slug AS post_slug FROM comments c LEFT JOIN posts p ON p.id = c.post_id WHERE $where ORDER BY c.created_at DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}");
$counts = array_column(rows('SELECT status, COUNT(*) c FROM comments GROUP BY status'), 'c', 'status');

adm_head('مدیریت نظرات', 'comments');
?>
<div class="card">
    <div class="tabs">
        <?php foreach ($valid as $k => $l): ?>
            <a href="?status=<?= $k ?>" class="<?= $status === $k ? 'active' : '' ?>"><?= $l ?><em>(<?= fa_num((string) ($k === 'all' ? array_sum($counts) : ($counts[$k] ?? 0))) ?>)</em></a>
        <?php endforeach; ?>
        <?php if ($status === 'spam' && !empty($counts['spam'])): ?>
            <form method="post" style="margin-right:auto"><?= csrf_field() ?><button class="abtn abtn-danger abtn-sm" name="act" value="purge_spam" data-confirm="همه اسپم‌ها حذف شوند؟">حذف همه اسپم‌ها</button></form>
        <?php endif; ?>
    </div>
    <?php if (!$list): ?><div class="empty-state"><span>💬</span>نظری در این بخش وجود ندارد.</div><?php endif; ?>
    <?php foreach ($list as $c): ?>
        <div class="cmt-box <?= e($c['status']) ?>">
            <div class="cmt-meta">
                <span class="avatar avatar-letter" style="width:32px;height:32px"><?= e(mb_substr($c['name'], 0, 1)) ?></span>
                <b><?= e($c['name']) ?></b>
                <?php if ($c['email']): ?><span class="ltr"><?= e($c['email']) ?></span><?php endif; ?>
                <span><?= jdate('j F Y H:i', $c['created_at']) ?></span>
                <?= status_badge($c['status']) ?>
                <?php if ($c['parent_id']): ?><span class="badge b-info">پاسخ</span><?php endif; ?>
                <span class="ltr muted"><?= e($c['ip']) ?></span>
            </div>
            <p><?= e($c['body']) ?></p>
            <div class="row-flex small">
                <span class="muted">در خبر: <a href="<?= e(url('post/' . rawurlencode((string) $c['post_slug']))) ?>#c<?= (int) $c['id'] ?>" target="_blank" style="color:var(--primary)"><?= e(mb_strimwidth((string) $c['post_title'], 0, 60, '…')) ?></a></span>
                <form method="post" class="row-flex" style="margin-right:auto;gap:6px">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <?php if ($c['status'] !== 'approved'): ?><button class="abtn abtn-ok abtn-xs" name="act" value="approved">✔ تایید</button><?php endif; ?>
                    <?php if ($c['status'] !== 'pending'): ?><button class="abtn abtn-ghost abtn-xs" name="act" value="pending">در انتظار</button><?php endif; ?>
                    <?php if ($c['status'] !== 'spam'): ?><button class="abtn abtn-ghost abtn-xs" name="act" value="spam">اسپم</button><?php endif; ?>
                    <button type="button" class="abtn abtn-ghost abtn-xs" onclick="var f=this.closest('.cmt-box').querySelector('.reply-f');f.hidden=!f.hidden">↩ پاسخ</button>
                    <button class="abtn abtn-danger abtn-xs" name="act" value="delete" data-confirm="این نظر حذف شود؟">حذف</button>
                </form>
            </div>
            <form method="post" class="reply-f mt" hidden>
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><input type="hidden" name="act" value="reply">
                <textarea class="input" name="body" rows="3" placeholder="پاسخ شما (با نشان «تیم NEXUS» منتشر می‌شود)..." required></textarea>
                <button class="abtn abtn-primary abtn-sm mt" type="submit">ارسال پاسخ</button>
            </form>
        </div>
    <?php endforeach; ?>
    <?= pagination_html($pg, url('admin/comments.php?status=' . $status)) ?>
</div>
<?php adm_foot();
