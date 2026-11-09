<?php
require __DIR__ . '/inc/boot.php';
require_cap('users');
$me = current_user();
$boss = can('*');                       // only a full admin may create / change admins or grant everything
$myCaps = user_caps($me);
$edit = (int) ($_GET['edit'] ?? 0);
$u = $edit ? row('SELECT * FROM users WHERE id = ?', [$edit]) : null;
// someone who is not a full admin manages only «custom» users whose access is within their own
$mayManage = fn(array $x): bool => $boss || ($x['role'] === 'custom' && !array_diff(user_caps($x), $myCaps));
if ($u && !$mayManage($u) && (int) $u['id'] !== (int) $me['id']) { flash('این حساب دسترسی بیشتری از شما داره؛ فقط مدیر کل می‌تونه تغییرش بده.', 'error'); redirect('admin/users.php'); }
$u = $u ?: ['id' => 0, 'username' => '', 'display_name' => '', 'email' => '', 'role' => 'author', 'avatar' => '', 'bio' => '', 'is_active' => 1, 'perms' => '[]'];
$roles = role_names();
if (!$boss) $roles = array_intersect_key($roles, ['custom' => 1] + ((int) ($u['id'] ?? 0) === (int) $me['id'] ? [$me['role'] => 1] : []));

if (is_post()) {
    csrf_check();
    if (!empty($_POST['delete_id'])) {
        $did = (int) $_POST['delete_id'];
        if ($did === (int) $me['id']) flash('نمی‌توانید حساب خودتان را حذف کنید.', 'error');
        elseif (!$boss && !$mayManage(row("SELECT * FROM users WHERE id = ?", [$did]) ?: ['role' => 'admin'])) flash('فقط مدیر کل می‌تونه این حساب رو حذف کنه.', 'error');
        elseif ((row("SELECT role FROM users WHERE id = ?", [$did])['role'] ?? '') === 'admin' && (int) val("SELECT COUNT(*) FROM users WHERE role='admin'") <= 1) flash('حداقل یک مدیر کل باید وجود داشته باشد.', 'error');
        else { q('UPDATE posts SET author_id = ? WHERE author_id = ?', [$me['id'], $did]); q('DELETE FROM users WHERE id = ?', [$did]); flash('کاربر حذف شد و اخبارش به شما منتقل شد.'); }
        redirect('admin/users.php');
    }
    $data = [
        'username' => trim((string) $_POST['username']),
        'display_name' => mb_substr(trim((string) ($_POST['display_name'] ?? '')), 0, 120),
        'email' => mb_substr(trim((string) ($_POST['email'] ?? '')), 0, 190),
        'role' => array_key_exists($_POST['role'] ?? '', $roles) ? $_POST['role'] : ($boss ? 'author' : 'custom'),
        'avatar' => trim((string) ($_POST['avatar'] ?? '')) ?: null,
        'bio' => mb_substr(trim((string) ($_POST['bio'] ?? '')), 0, 500),
        'is_active' => !empty($_POST['is_active']) ? 1 : 0,
    ];
    // hand-picked permissions (custom role); someone who is not a full admin can only pass on what they have themselves
    $picked = array_values(array_intersect((array) ($_POST['perms'] ?? []), array_keys(perm_list())));
    if (!$boss) $picked = array_values(array_intersect($picked, $myCaps));
    $data['perms'] = $data['role'] === 'custom' ? json_encode($picked) : null;
    $pass = (string) ($_POST['password'] ?? '');
    $err = '';
    if ($data['role'] === 'custom' && !$picked) $err = 'برای «دسترسی دستی» حداقل یک بخش رو تیک بزن.';
    if (!preg_match('~^[A-Za-z0-9_.\-]{3,40}$~', $data['username'])) $err = 'نام کاربری باید ۳ تا ۴۰ کاراکتر انگلیسی باشد.';
    elseif ((int) val('SELECT COUNT(*) FROM users WHERE username = ? AND id <> ?', [$data['username'], $u['id']])) $err = 'این نام کاربری قبلاً ثبت شده است.';
    elseif (!$u['id'] && strlen($pass) < 8) $err = 'رمز عبور باید حداقل ۸ کاراکتر باشد.';
    elseif ($pass !== '' && strlen($pass) < 8) $err = 'رمز عبور باید حداقل ۸ کاراکتر باشد.';
    elseif ((int) $u['id'] === (int) $me['id'] && ($data['role'] !== $me['role'] || !$data['is_active'] || ($me['role'] === 'custom' && $data['perms'] !== $me['perms']))) $err = 'نمی‌توانید نقش، دسترسی یا وضعیت خودتان را تغییر دهید.';
    if ($err) { flash($err, 'error'); redirect('admin/users.php' . ($u['id'] ? '?edit=' . $u['id'] : '')); }
    if ($pass !== '') $data['password'] = password_hash($pass, PASSWORD_DEFAULT);
    if ($u['id']) { update('users', $data, (int) $u['id']); flash('کاربر بروزرسانی شد.'); }
    else { $data['created_at'] = now(); insert('users', $data); flash('کاربر جدید ساخته شد.'); }
    redirect('admin/users.php');
}
$list = rows('SELECT u.*, (SELECT COUNT(*) FROM posts p WHERE p.author_id = u.id) AS pc FROM users u ORDER BY u.id');
adm_head('کاربران', 'users');
?>
<div class="split">
    <div class="card">
        <div class="card-head"><h3><?= adm_icon('users', 20) ?> کاربران پنل</h3></div>
        <div class="tbl-wrap"><table class="tbl">
            <thead><tr><th></th><th>نام</th><th>نقش</th><th class="hide-m">اخبار</th><th class="hide-m">آخرین ورود</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($list as $x): ?>
                <tr>
                    <td style="width:50px"><?= user_avatar($x, 38) ?></td>
                    <td><b><?= e($x['display_name'] ?: $x['username']) ?></b><br><small class="muted ltr">@<?= e($x['username']) ?></small> <?= $x['is_active'] ? '' : '<span class="badge b-err">غیرفعال</span>' ?></td>
                    <td><span class="badge <?= $x['role'] === 'admin' ? 'b-hot' : ($x['role'] === 'editor' ? 'b-info' : ($x['role'] === 'custom' ? 'b-ok' : '')) ?>"><?= e(role_names()[$x['role']] ?? $x['role']) ?></span><?php if ($x['role'] === 'custom'): $pc = count(user_caps($x)) - (in_array('shop', user_caps($x), true) ? 1 : 0); ?><small class="muted d-block"><?= fa_num((string) $pc) ?> بخش</small><?php endif; ?></td>
                    <td class="hide-m"><?= fa_num((string) $x['pc']) ?></td>
                    <td class="hide-m small muted"><?= $x['last_login'] ? jago($x['last_login']) : '—' ?></td>
                    <td><div class="actions">
                        <?php if ($mayManage($x) || (int) $x['id'] === (int) $me['id']): ?><a class="adm-ib" href="?edit=<?= (int) $x['id'] ?>"><?= adm_icon('edit', 16) ?></a><?php endif; ?>
                        <?php if ((int) $x['id'] !== (int) $me['id'] && $mayManage($x)): ?><form method="post"><?= csrf_field() ?><button class="adm-ib" name="delete_id" value="<?= (int) $x['id'] ?>" data-confirm="کاربر حذف شود؟"><?= adm_icon('trash', 16) ?></button></form><?php endif; ?>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <div class="alert alert-info small mt">
            <b>مدیر کل:</b> دسترسی کامل · <b>سردبیر:</b> همه اخبار، دسته‌ها، نظرات، رسانه، صفحات، پیام‌ها و سفارش‌ها/محصولات/تیکت‌ها · <b>نویسنده:</b> فقط اخبار خودش و آپلود تصویر · <b>دسترسی دستی:</b> هر بخشی که خودت تیک بزنی
        </div>
    </div>
    <div class="split-side">
        <form class="card" method="post">
            <?= csrf_field() ?>
            <div class="card-head"><h3><?= $u['id'] ? 'ویرایش کاربر' : 'کاربر جدید' ?></h3><?php if ($u['id']): ?><a class="abtn abtn-ghost abtn-xs" href="users.php">لغو</a><?php endif; ?></div>
            <?= f_text('username', 'نام کاربری (انگلیسی)', $u['username'], ['required' => true, 'ltr' => true]) ?>
            <?= f_text('display_name', 'نام نمایشی', $u['display_name']) ?>
            <?= f_text('email', 'ایمیل', $u['email'], ['type' => 'email', 'ltr' => true]) ?>
            <?= f_select('role', 'نقش', $roles, $u['role']) ?>
            <?php $have = $u['role'] === 'custom' ? user_caps($u) : []; ?>
            <div class="perm-box" id="permBox"<?= $u['role'] === 'custom' ? '' : ' hidden' ?>>
                <div class="perm-top"><b>🔐 این کاربر به چه بخش‌هایی دسترسی داشته باشه؟</b><span><button type="button" class="abtn abtn-ghost abtn-xs" data-perm-all data-nofb>همه</button><button type="button" class="abtn abtn-ghost abtn-xs" data-perm-none data-nofb>هیچ‌کدوم</button></span></div>
                <?php foreach (perm_groups() as $gname => $items): ?>
                <details class="perm-g" open>
                    <summary><span><?= e($gname) ?></span><em data-perm-count></em></summary>
                    <div class="perm-list">
                        <?php foreach ($items as $k => $lbl): $mine = $boss || in_array($k, $myCaps, true); ?>
                        <label class="perm-it<?= $mine ? '' : ' is-off' ?>"><input type="checkbox" name="perms[]" value="<?= e($k) ?>"<?= in_array($k, $have, true) ? ' checked' : '' ?><?= $mine ? '' : ' disabled' ?>><span><?= e($lbl) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </details>
                <?php endforeach; ?>
                <small class="af-hint">داشبورد و پروفایل خودش همیشه باز است. هر بخشی که تیک نخوره توی منوی پنلش اصلاً دیده نمی‌شه و آدرسش هم باز نمی‌شه.</small>
            </div>
            <?= f_text('password', $u['id'] ? 'رمز جدید (خالی = بدون تغییر)' : 'رمز عبور', '', ['type' => 'password', 'ltr' => true, 'attrs' => 'autocomplete="new-password"']) ?>
            <div class="logo-pick"><?= f_image('avatar', 'تصویر پروفایل', $u['avatar']) ?></div>
            <?= f_textarea('bio', 'بیوگرافی', (string) $u['bio'], ['rows' => 2]) ?>
            <?= f_switch('is_active', 'حساب فعال', (bool) $u['is_active']) ?>
            <button class="abtn abtn-primary abtn-block mt" type="submit">ذخیره</button>
        </form>
    </div>
</div>
<script>
(function () {
    var box = document.getElementById('permBox'), role = document.querySelector('[name=role]'); if (!box || !role) return;
    function count() { [].forEach.call(box.querySelectorAll('.perm-g'), function (g) { var all = g.querySelectorAll('input:not([disabled])'), on = g.querySelectorAll('input:checked'); g.querySelector('[data-perm-count]').textContent = on.length + ' / ' + all.length; }); }
    function sync() { box.hidden = role.value !== 'custom'; }
    role.addEventListener('change', sync); box.addEventListener('change', count);
    box.querySelector('[data-perm-all]').addEventListener('click', function () { [].forEach.call(box.querySelectorAll('input:not([disabled])'), function (c) { c.checked = true; }); count(); });
    box.querySelector('[data-perm-none]').addEventListener('click', function () { [].forEach.call(box.querySelectorAll('input'), function (c) { c.checked = false; }); count(); });
    sync(); count();
})();
</script>
<?php adm_foot();
