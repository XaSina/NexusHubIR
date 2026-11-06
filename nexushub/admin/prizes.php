<?php
require __DIR__ . '/inc/boot.php';
require_cap('*');
prizes_expire_due();

if (is_post()) {
    csrf_check();
    $pid = (int) ($_POST['prize'] ?? 0);
    switch ($_POST['act'] ?? '') {
        case 'deliver': prize_set_status($pid, 'delivered'); flash('✅ تحویل شد و به برنده خبر داده شد.'); break;
        case 'undo': prize_set_status($pid, 'pending'); flash('به حالت «منتظر دریافت» برگشت.'); break;
        case 'msg':
            $ok = prize_message($pid, (string) ($_POST['body'] ?? ''));
            if ($ok && !empty($_POST['and_deliver'])) prize_set_status($pid, 'delivered');
            flash($ok ? '✉️ پیام برای برنده فرستاده شد (توی اعلان‌ها و «جایزه‌های من» می‌بینه).' : 'متن پیام خالیه.', $ok ? 'success' : 'error');
            break;
        case 'links':
            save_settings(['link_prizes_footer' => !empty($_POST['link_prizes_footer']) ? '1' : '0', 'link_notifs_footer' => !empty($_POST['link_notifs_footer']) ? '1' : '0',
                'link_prizes_drawer' => !empty($_POST['link_prizes_drawer']) ? '1' : '0', 'link_notifs_drawer' => !empty($_POST['link_notifs_drawer']) ? '1' : '0']);
            flash('ذخیره شد ✅');
            break;
    }
    redirect('admin/prizes.php?' . http_build_query(array_intersect_key($_GET, array_flip(['t', 's', 'p', 'q']))));
}

$tab = in_array($_GET['t'] ?? '', ['raffle', 'wheel', 'discount'], true) ? $_GET['t'] : 'all';
$stf = array_key_exists($_GET['s'] ?? '', prize_status_names()) ? $_GET['s'] : '';
$qs = trim((string) ($_GET['q'] ?? ''));
$where = ['1=1']; $args = [];
if ($tab === 'discount') $where[] = "p.prize_type = 'discount'"; elseif ($tab !== 'all') { $where[] = 'p.source = ?'; $args[] = $tab; }
if ($stf) { $where[] = 'p.status = ?'; $args[] = $stf; }
if ($qs !== '') { $where[] = '(m.first_name LIKE ? OR m.last_name LIKE ? OR m.phone LIKE ? OR m.member_code LIKE ? OR p.title LIKE ?)'; array_push($args, ...array_fill(0, 5, '%' . en_num($qs) . '%')); }
$W = implode(' AND ', $where);
$per = 12;
$total = (int) val("SELECT COUNT(*) FROM prizes p JOIN members m ON m.id = p.member_id WHERE $W", $args);
$pages = max(1, (int) ceil($total / $per)); $page = max(1, min($pages, (int) ($_GET['p'] ?? 1)));
$list = rows("SELECT p.*, m.first_name, m.last_name, m.phone, m.member_code, c.code coupon_code, c.pct, c.max_off, c.min_total, c.scope, c.scope_ids
    FROM prizes p JOIN members m ON m.id = p.member_id LEFT JOIN coupons c ON c.id = p.coupon_id WHERE $W ORDER BY (p.status = 'pending') DESC, p.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $args);
$msgs = $list ? rows('SELECT * FROM prize_messages WHERE prize_id IN (' . implode(',', array_map(fn($p) => (int) $p['id'], $list)) . ') ORDER BY id') : [];
$cnt = fn($sql) => (int) val("SELECT COUNT(*) FROM prizes WHERE $sql");
$tabs = ['all' => ['🎁 همه', $cnt('1=1')], 'raffle' => ['🎰 برنده‌های قرعه‌کشی', $cnt("source = 'raffle'")], 'wheel' => ['🎡 برنده‌های گردونه', $cnt("source = 'wheel'")], 'discount' => ['🏷️ کدهای تخفیف', $cnt("prize_type = 'discount'")]];
$link = fn(array $o) => 'prizes.php?' . http_build_query(array_filter($o + ['t' => $tab === 'all' ? '' : $tab, 's' => $stf, 'q' => $qs]));
adm_head('جایزه‌ها و برنده‌ها', 'prizes');
?>
<div class="rfl-head">
    <div class="rfl-head-t"><span class="rfl-head-ic">🎁</span><div><h2>جایزه‌ها و برنده‌ها</h2><small><?= fa_num((string) $cnt("status = 'pending'")) ?> منتظر تحویل · <?= fa_num((string) $cnt("status = 'delivered'")) ?> تحویل‌شده · <?= fa_num((string) $cnt("status = 'active'")) ?> کد تخفیف فعال</small></div></div>
</div>

<div class="pzx-tabs">
    <?php foreach ($tabs as $k => [$t, $n]): ?><a class="<?= $tab === $k ? 'on' : '' ?>" href="<?= e('prizes.php?' . http_build_query(array_filter(['t' => $k === 'all' ? '' : $k]))) ?>"><?= e($t) ?> <em><?= fa_num((string) $n) ?></em></a><?php endforeach; ?>
</div>
<form class="pzx-filter" method="get">
    <?php if ($tab !== 'all'): ?><input type="hidden" name="t" value="<?= e($tab) ?>"><?php endif; ?>
    <input type="search" name="q" value="<?= e($qs) ?>" placeholder="جستجو: اسم، موبایل، کد عضویت یا جایزه">
    <select name="s" onchange="this.form.submit()"><option value="">همه‌ی وضعیت‌ها</option><?php foreach (prize_status_names() as $k => [$ic, $nm]): ?><option value="<?= $k ?>" <?= $stf === $k ? 'selected' : '' ?>><?= $ic ?> <?= e($nm) ?></option><?php endforeach; ?></select>
    <button class="abtn abtn-sm" type="submit"><?= adm_icon('search', 15) ?> جستجو</button>
</form>

<?php if (!$list): ?>
    <div class="card"><div class="empty-state"><span>🎁</span>چیزی پیدا نشد.</div></div>
<?php else: ?>
<div class="pzx-list">
    <?php foreach ($list as $p): $st = prize_status_names()[$p['status']] ?? ['•', $p['status'], 'muted']; $pm = array_values(array_filter($msgs, fn($m) => (int) $m['prize_id'] === (int) $p['id'])); ?>
    <article class="pzx pzx-<?= e($p['status']) ?>">
        <div class="pzx-main">
            <span class="pzx-ic"><?= e($p['emoji'] ?: ($p['prize_type'] === 'discount' ? '🏷️' : '🎁')) ?></span>
            <div class="pzx-who"><b><?= e($p['first_name'] . ' ' . $p['last_name']) ?></b><span class="pzx-meta"><span dir="ltr"><?= e($p['phone']) ?></span><span dir="ltr"><?= e($p['member_code']) ?></span></span></div>
            <div class="pzx-what"><b><?= e($p['title']) ?></b><small><?= e(prize_source_label($p)) ?> · <?= e(jago($p['created_at'])) ?></small>
                <?php if ($p['prize_type'] === 'discount'): ?><small class="pzx-code"><code dir="ltr"><?= e((string) $p['coupon_code']) ?></code> <?= e(discount_text(['pct' => (float) $p['pct'], 'max' => (int) $p['max_off'], 'min' => (int) $p['min_total'], 'scope' => $p['scope'], 'ids' => array_filter(explode(',', (string) $p['scope_ids']))])) ?></small><?php endif; ?></div>
            <div class="pzx-st"><span class="badge b-<?= $st[2] === 'ok' ? 'ok' : ($st[2] === 'warn' ? 'warn' : ($st[2] === 'info' ? 'info' : 'muted')) ?>"><?= $st[0] ?> <?= e($st[1]) ?></span>
                <?php if ($p['status'] === 'pending' && $p['deadline']): ?><small>⏳ <?= e(raffle_left_text(max(60, strtotime($p['deadline']) - time()))) ?> مهلت</small><?php endif; ?></div>
            <div class="pzx-act">
                <?php if ($p['prize_type'] === 'item' && $p['status'] !== 'delivered'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="deliver"><input type="hidden" name="prize" value="<?= (int) $p['id'] ?>"><button class="rfl-hbtn is-go"><span>✓</span> تحویل داده شد</button></form><?php endif; ?>
                <?php if ($p['status'] === 'delivered'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="undo"><input type="hidden" name="prize" value="<?= (int) $p['id'] ?>"><button class="rfl-hbtn is-dim"><span>↺</span> برگردون</button></form><?php endif; ?>
                <button type="button" class="rfl-hbtn" data-msg="<?= (int) $p['id'] ?>"><span>✉️</span> پیام به برنده</button>
            </div>
        </div>
        <?php foreach ($pm as $m): ?><div class="pzx-msg">✉️ <?= nl2br(e($m['body'])) ?> <small><?= e(jago($m['created_at'])) ?></small></div><?php endforeach; ?>
        <form method="post" class="pzx-form" id="pzxForm<?= (int) $p['id'] ?>" hidden><?= csrf_field() ?><input type="hidden" name="act" value="msg"><input type="hidden" name="prize" value="<?= (int) $p['id'] ?>">
            <textarea name="body" rows="3" placeholder="مثلاً: جایزه‌ت فعال شد ✅ — کد / لینک / توضیح تحویل رو اینجا بنویس" required></textarea>
            <div class="pzx-form-act"><?php if ($p['prize_type'] === 'item' && $p['status'] !== 'delivered'): ?><label><input type="checkbox" name="and_deliver" value="1" checked> همزمان «تحویل داده شد» بزن</label><?php endif; ?><button class="rf-save" type="submit"><span class="rf-save-check">✉️</span><span>بفرست</span></button></div>
        </form>
    </article>
    <?php endforeach; ?>
</div>
<?php if ($pages > 1): ?><div class="pzx-pager"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="<?= $i === $page ? 'on' : '' ?>" href="<?= e($link(['p' => $i])) ?>"><?= fa_num((string) $i) ?></a><?php endfor; ?></div><?php endif; ?>
<?php endif; ?>

<form method="post" class="card pzx-links"><?= csrf_field() ?><input type="hidden" name="act" value="links">
    <div class="card-head"><h3>🔗 لینک «جایزه‌های من» و «اعلان‌های من» روی سایت</h3><small class="muted">دکمه‌ی اعلان (زنگوله) همیشه بالای سایت هست؛ اینجا انتخاب کن کجاهای دیگه لینکشون باشه</small></div>
    <div class="pzx-links-grid">
        <?= f_switch('link_prizes_footer', '🎁 جایزه‌های من — توی فوتر', setting_on('link_prizes_footer')) ?>
        <?= f_switch('link_notifs_footer', '🔔 اعلان‌های من — توی فوتر', setting_on('link_notifs_footer')) ?>
        <?= f_switch('link_prizes_drawer', '🎁 جایزه‌های من — منوی گوشی', setting_on('link_prizes_drawer')) ?>
        <?= f_switch('link_notifs_drawer', '🔔 اعلان‌های من — منوی گوشی', setting_on('link_notifs_drawer')) ?>
    </div>
    <button class="rf-save pc-save" type="submit"><span class="rf-save-check"><?= icon('check', 18) ?></span><span>ذخیره</span></button>
</form>
<script>
document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-msg]'); if (!b) return;
    var f = document.getElementById('pzxForm' + b.dataset.msg); f.hidden = !f.hidden; if (!f.hidden) f.querySelector('textarea').focus();
});
</script>
<?php adm_foot();
