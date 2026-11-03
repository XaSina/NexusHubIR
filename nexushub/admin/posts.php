<?php
require __DIR__ . '/inc/boot.php';
require_cap('posts');
$me = current_user();
$own = !can('edit_others');

// عملیات گروهی / حذف
if (is_post()) {
    csrf_check();
    $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
    if (!empty($_POST['delete_id'])) $ids = [(int) $_POST['delete_id']];
    $act = $_POST['bulk'] ?? (!empty($_POST['delete_id']) ? 'delete' : '');
    if ($ids && $act) {
        $where = 'id IN (' . implode(',', $ids) . ')' . ($own ? ' AND author_id = ' . (int) $me['id'] : '');
        switch ($act) {
            case 'delete':
                $real = array_map('intval', array_column(rows("SELECT id FROM posts WHERE $where"), 'id'));
                if ($real) {
                    $in = implode(',', $real);
                    q("DELETE FROM post_tags WHERE post_id IN ($in)");
                    q("DELETE FROM comments WHERE post_id IN ($in)");
                    q("DELETE FROM reactions WHERE post_id IN ($in)");
                    q("DELETE FROM reaction_log WHERE post_id IN ($in)");
                    q("DELETE FROM posts WHERE id IN ($in)");
                }
                flash(fa_num((string) count($real)) . ' خبر حذف شد.');
                break;
            case 'publish': q("UPDATE posts SET status = 'published', published_at = COALESCE(published_at, ?) WHERE $where", [now()]); flash('اخبار انتخابی منتشر شدند.'); break;
            case 'draft': q("UPDATE posts SET status = 'draft' WHERE $where"); flash('اخبار انتخابی به پیش‌نویس منتقل شدند.'); break;
            case 'feature': q("UPDATE posts SET is_featured = 1 WHERE $where"); flash('به اخبار ویژه اضافه شدند.'); break;
            case 'unfeature': q("UPDATE posts SET is_featured = 0 WHERE $where"); flash('از ویژه خارج شدند.'); break;
            case 'breaking': q("UPDATE posts SET is_breaking = 1 WHERE $where"); flash('به اخبار فوری اضافه شدند.'); break;
            case 'unbreaking': q("UPDATE posts SET is_breaking = 0 WHERE $where"); flash('از اخبار فوری خارج شدند.'); break;
        }
    }
    redirect('admin/posts.php?' . http_build_query(array_intersect_key($_GET, array_flip(['status', 'cat', 'q', 'page']))));
}

$status = (string) ($_GET['status'] ?? '');
$cat = (int) ($_GET['cat'] ?? 0);
$qs = trim((string) ($_GET['q'] ?? ''));
$where = ['1=1'];
$params = [];
if ($own) { $where[] = 'p.author_id = ?'; $params[] = $me['id']; }
if ($status === 'published') { $where[] = "p.status = 'published' AND p.published_at <= ?"; $params[] = now(); }
elseif ($status === 'scheduled') { $where[] = "p.status = 'published' AND p.published_at > ?"; $params[] = now(); }
elseif ($status === 'draft') $where[] = "p.status = 'draft'";
elseif ($status === 'featured') $where[] = 'p.is_featured = 1';
elseif ($status === 'breaking') $where[] = 'p.is_breaking = 1';
if ($cat) { $where[] = 'p.category_id = ?'; $params[] = $cat; }
if ($qs !== '') { $where[] = 'p.title LIKE ?'; $params[] = '%' . $qs . '%'; }
$w = implode(' AND ', $where);
$total = (int) val("SELECT COUNT(*) FROM posts p WHERE $w", $params);
$pg = paginate($total, 20, (int) ($_GET['page'] ?? 1));
$list = rows(POST_SELECT . " WHERE $w ORDER BY p.created_at DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
$cc = array_column(rows("SELECT post_id, COUNT(*) c FROM comments GROUP BY post_id"), 'c', 'post_id');

$ownSql = $own ? ' AND author_id = ' . (int) $me['id'] : '';
$counts = [
    '' => (int) val("SELECT COUNT(*) FROM posts WHERE 1=1 $ownSql"),
    'published' => (int) val("SELECT COUNT(*) FROM posts WHERE status='published' AND published_at <= ? $ownSql", [now()]),
    'scheduled' => (int) val("SELECT COUNT(*) FROM posts WHERE status='published' AND published_at > ? $ownSql", [now()]),
    'draft' => (int) val("SELECT COUNT(*) FROM posts WHERE status='draft' $ownSql"),
    'featured' => (int) val("SELECT COUNT(*) FROM posts WHERE is_featured=1 $ownSql"),
    'breaking' => (int) val("SELECT COUNT(*) FROM posts WHERE is_breaking=1 $ownSql"),
];
$labels = ['' => 'همه', 'published' => 'منتشر شده', 'scheduled' => 'زمان‌بندی', 'draft' => 'پیش‌نویس', 'featured' => '⭐ ویژه', 'breaking' => '⚡ فوری'];

adm_head('مدیریت اخبار', 'posts');
?>
<div class="card">
    <div class="tabs">
        <?php foreach ($labels as $k => $l): ?>
            <a href="?status=<?= e($k) ?>" class="<?= $status === $k ? 'active' : '' ?>"><?= e($l) ?><em>(<?= fa_num((string) $counts[$k]) ?>)</em></a>
        <?php endforeach; ?>
    </div>
    <form class="toolbar" method="get">
        <input type="hidden" name="status" value="<?= e($status) ?>">
        <input type="search" name="q" value="<?= e($qs) ?>" placeholder="جستجوی عنوان...">
        <select name="cat"><option value="0">همه دسته‌ها</option><?php foreach (get_categories() as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $cat === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['icon'] . ' ' . $c['name']) ?></option><?php endforeach; ?></select>
        <button class="abtn abtn-ghost abtn-sm" type="submit"><?= icon('search', 16) ?> فیلتر</button>
        <a class="abtn abtn-primary abtn-sm" href="post-edit.php" style="margin-right:auto"><?= adm_icon('plus', 16) ?> خبر جدید</a>
    </form>

    <form method="post" id="bulkForm">
        <?= csrf_field() ?>
        <?php if ($list): ?>
        <div class="toolbar">
            <select name="bulk">
                <option value="">عملیات گروهی...</option>
                <option value="publish">انتشار</option><option value="draft">پیش‌نویس</option>
                <option value="feature">ویژه کردن</option><option value="unfeature">حذف از ویژه</option>
                <option value="breaking">فوری کردن</option><option value="unbreaking">حذف از فوری</option>
                <option value="delete">حذف</option>
            </select>
            <button class="abtn abtn-ghost abtn-sm" type="submit" data-confirm="عملیات روی موارد انتخاب‌شده انجام شود؟">اعمال</button>
        </div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead><tr><th><input type="checkbox" data-check-all="ids[]"></th><th></th><th>عنوان</th><th class="hide-m">دسته</th><th class="hide-m">نویسنده</th><th>وضعیت</th><th class="hide-m">بازدید</th><th class="hide-m">تاریخ</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($list as $p): ?>
                    <tr>
                        <td><input type="checkbox" name="ids[]" value="<?= (int) $p['id'] ?>"></td>
                        <td><?= $p['cover'] ? '<img class="thumb" src="' . e(media_url($p['cover'])) . '" alt="" loading="lazy">' : '<span class="thumb" style="display:grid;place-items:center;font-size:1.3rem">' . e($p['cat_icon'] ?? '📰') . '</span>' ?></td>
                        <td>
                            <a class="t-title" href="post-edit.php?id=<?= (int) $p['id'] ?>"><?= e($p['title']) ?></a>
                            <span class="row-flex" style="gap:4px;margin-top:4px">
                                <?php if ($p['is_featured']): ?><span class="badge b-warn">⭐ ویژه</span><?php endif; ?>
                                <?php if ($p['is_breaking']): ?><span class="badge b-hot">⚡ فوری</span><?php endif; ?>
                                <?php if (!empty($cc[$p['id']])): ?><span class="badge"><?= icon('comment', 12) ?> <?= fa_num((string) $cc[$p['id']]) ?></span><?php endif; ?>
                                <?php if ($p['telegram_sent']): ?><span class="badge b-info"><?= icon('telegram', 12) ?></span><?php endif; ?>
                            </span>
                        </td>
                        <td class="hide-m"><?= $p['cat_name'] ? e($p['cat_icon'] . ' ' . $p['cat_name']) : '<span class="muted">—</span>' ?></td>
                        <td class="hide-m small"><?= e($p['author_name'] ?: $p['author_username']) ?></td>
                        <td><?= status_badge($p['status'], $p['published_at']) ?></td>
                        <td class="hide-m"><?= fa_short_num((int) $p['views']) ?></td>
                        <td class="hide-m small muted"><?= $p['published_at'] ? jdate('Y/m/d H:i', $p['published_at']) : jdate('Y/m/d', $p['created_at']) ?></td>
                        <td><div class="actions">
                            <a class="adm-ib" href="post-edit.php?id=<?= (int) $p['id'] ?>" title="ویرایش"><?= adm_icon('edit', 16) ?></a>
                            <a class="adm-ib" href="<?= e(post_url($p)) ?><?= $p['status'] !== 'published' ? '?preview=1' : '' ?>" target="_blank" title="مشاهده"><?= icon('eye', 16) ?></a>
                            <button class="adm-ib" name="delete_id" value="<?= (int) $p['id'] ?>" data-confirm="این خبر حذف شود؟" title="حذف"><?= adm_icon('trash', 16) ?></button>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty-state"><span>📭</span>خبری یافت نشد.<br><a class="abtn abtn-primary abtn-sm mt" href="post-edit.php">انتشار اولین خبر</a></div>
        <?php endif; ?>
    </form>
    <?= str_replace('class="pagination"', 'class="tabs" style="justify-content:center;margin-top:16px"', pagination_html($pg, url('admin/posts.php?' . http_build_query(array_filter(['status' => $status, 'cat' => $cat ?: null, 'q' => $qs]))))) ?>
</div>
<?php adm_foot();
