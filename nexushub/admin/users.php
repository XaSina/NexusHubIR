<?php
require __DIR__ . '/inc/boot.php';
require_cap('*');
$me = current_user();
$edit = (int) ($_GET['edit'] ?? 0);
$u = $edit ? row('SELECT * FROM users WHERE id = ?', [$edit]) : null;
$u = $u ?: ['id' => 0, 'username' => '', 'display_name' => '', 'email' => '', 'role' => 'author', 'avatar' => '', 'bio' => '', 'is_active' => 1];

if (is_post()) {
    csrf_check();
    if (!empty($_POST['delete_id'])) {
        $did = (int) $_POST['delete_id'];
        if ($did === (int) $me['id']) flash('نمی‌توانید حساب خودتان را حذف کنید.', 'error');
        elseif (row("SELECT role FROM users WHERE id = ?", [$did])['role'] === 'admin' && (int) val("SELECT COUNT(*) FROM users WHERE role='admin'") <= 1) flash('حداقل یک مدیر کل باید وجود داشته باشد.', 'error');
        else { q('UPDATE posts SET author_id = ? WHERE author_id = ?', [$me['id'], $did]); q('DELETE FROM users WHERE id = ?', [$did]); flash('کاربر حذف شد و اخبارش به شما منتقل شد.'); }
        redirect('admin/users.php');
    }
    $data = [
        'username' => trim((string) $_POST['username']),
        'display_name' => mb_substr(trim((string) ($_POST['display_name'] ?? '')), 0, 120),
        'email' => mb_substr(trim((string) ($_POST['email'] ?? '')), 0, 190),
        'role' => array_key_exists($_POST['role'] ?? '', role_names()) ? $_POST['role'] : 'author',
        'avatar' => trim((string) ($_POST['avatar'] ?? '')) ?: null,
        'bio' => mb_substr(trim((string) ($_POST['bio'] ?? '')), 0, 500),
        'is_active' => !empty($_POST['is_active']) ? 1 : 0,
    ];
    $pass = (string) ($_POST['password'] ?? '');
    $err = '';
    if (!preg_match('~^[A-Za-z0-9_.\-]{3,40}$~', $data['username'])) $err = 'نام کاربری باید ۳ تا ۴۰ کاراکتر انگلیسی باشد.';
    elseif ((int) val('SELECT COUNT(*) FROM users WHERE username = ? AND id <> ?', [$data['username'], $u['id']])) $err = 'این نام کاربری قبلاً ثبت شده است.';
    elseif (!$u['id'] && strlen($pass) < 8) $err = 'رمز عبور باید حداقل ۸ کاراکتر باشد.';
    elseif ($pass !== '' && strlen($pass) < 8) $err = 'رمز عبور باید حداقل ۸ کاراکتر باشد.';
    elseif ((int) $u['id'] === (int) $me['id'] && ($data['role'] !== 'admin' || !$data['is_active'])) $err = 'نمی‌توانید نقش یا وضعیت خودتان را تغییر دهید.';
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
                    <td><span class="badge <?= $x['role'] === 'admin' ? 'b-hot' : ($x['role'] === 'editor' ? 'b-info' : '') ?>"><?= e(role_names()[$x['role']] ?? $x['role']) ?></span></td>
                    <td class="hide-m"><?= fa_num((string) $x['pc']) ?></td>
                    <td class="hide-m small muted"><?= $x['last_login'] ? jago($x['last_login']) : '—' ?></td>
                    <td><div class="actions">
                        <a class="adm-ib" href="?edit=<?= (int) $x['id'] ?>"><?= adm_icon('edit', 16) ?></a>
                        <?php if ((int) $x['id'] !== (int) $me['id']): ?><form method="post"><?= csrf_field() ?><button class="adm-ib" name="delete_id" value="<?= (int) $x['id'] ?>" data-confirm="کاربر حذف شود؟"><?= adm_icon('trash', 16) ?></button></form><?php endif; ?>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <div class="alert alert-info small mt">
            <b>مدیر کل:</b> دسترسی کامل · <b>سردبیر:</b> همه اخبار، دسته‌ها، نظرات، رسانه، صفحات و پیام‌ها · <b>نویسنده:</b> فقط اخبار خودش و آپلود تصویر
        </div>
    </div>
    <div class="split-side">
        <form class="card" method="post">
            <?= csrf_field() ?>
            <div class="card-head"><h3><?= $u['id'] ? 'ویرایش کاربر' : 'کاربر جدید' ?></h3><?php if ($u['id']): ?><a class="abtn abtn-ghost abtn-xs" href="users.php">لغو</a><?php endif; ?></div>
            <?= f_text('username', 'نام کاربری (انگلیسی)', $u['username'], ['required' => true, 'ltr' => true]) ?>
            <?= f_text('display_name', 'نام نمایشی', $u['display_name']) ?>
            <?= f_text('email', 'ایمیل', $u['email'], ['type' => 'email', 'ltr' => true]) ?>
            <?= f_select('role', 'نقش', role_names(), $u['role']) ?>
            <?= f_text('password', $u['id'] ? 'رمز جدید (خالی = بدون تغییر)' : 'رمز عبور', '', ['type' => 'password', 'ltr' => true, 'attrs' => 'autocomplete="new-password"']) ?>
            <div class="logo-pick"><?= f_image('avatar', 'تصویر پروفایل', $u['avatar']) ?></div>
            <?= f_textarea('bio', 'بیوگرافی', (string) $u['bio'], ['rows' => 2]) ?>
            <?= f_switch('is_active', 'حساب فعال', (bool) $u['is_active']) ?>
            <button class="abtn abtn-primary abtn-block mt" type="submit">ذخیره</button>
        </form>
    </div>
</div>
<?php adm_foot();
