<?php
/**
 * NEXUS | HUB — کنترلر اصلی سایت (Router)
 */
require __DIR__ . '/app/bootstrap.php';

function route_path(): string
{
    if (isset($_GET['r']) && is_string($_GET['r'])) return trim($_GET['r'], '/');
    $uri = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    $base = nx_base_path();
    if ($base !== '' && stripos($uri, $base) === 0) $uri = substr($uri, strlen($base));
    $uri = preg_replace('~^/index\.php~', '', $uri);
    return trim($uri, '/');
}

function page_out(string $tpl, array $data = [], array $meta = []): void
{
    track_online();
    $content = view($tpl, $data);
    echo view('layout', ['content' => $content, 'meta' => $meta, 'bodyClass' => $data['bodyClass'] ?? 'page-' . $tpl]);
}

function not_found(): never
{
    http_response_code(404);
    page_out('404', [], ['title' => 'صفحه پیدا نشد', 'noindex' => true]);
    exit;
}

$path = route_path();
$seg = $path === '' ? [] : explode('/', $path);
$page = max(1, (int) ($_GET['page'] ?? 1));
$per = max(3, min(60, (int) setting('archive_per_page', 9)));   // all list pages: all news, categories, tags, authors, search

// IndexNow key file (proves to Bing / Yandex that the pings come from this site)
if (count($seg) === 1 && preg_match('~^[a-f0-9]{32}\.txt$~', $seg[0]) && $seg[0] === setting('indexnow_key') . '.txt') {
    header('Content-Type: text/plain; charset=utf-8');
    echo setting('indexnow_key');
    exit;
}

// domain-verification files (Enamad, Samandehi, Google, ...) listed in the panel: an empty text file at the
// site root, even in maintenance mode
if (count($seg) === 1 && preg_match('~^[A-Za-z0-9_-]{3,80}\.(txt|html?)$~', $seg[0])) {
    $vf = array_filter(array_map('trim', preg_split('~[\r\n,]+~', (string) setting('verify_files', ''))));
    if (in_array($seg[0], $vf, true)) {
        header('Content-Type: ' . (str_ends_with($seg[0], '.txt') ? 'text/plain' : 'text/html') . '; charset=utf-8');
        header('Cache-Control: no-cache');
        // Google's verification file must contain this exact line; other issuers (Enamad, ...) want it empty
        if (preg_match('~^google[0-9a-f]+\.html$~i', $seg[0])) echo 'google-site-verification: ' . $seg[0];
        exit;
    }
}

// حالت تعمیر
if (setting_on('maintenance') && (!current_user() || isset($_GET['maintenance_preview'])) && ($seg[0] ?? '') !== 'go') {
    http_response_code(503);
    echo view('maintenance');
    exit;
}

switch ($seg[0] ?? '') {

    case '':
        track_visit();
        page_out('home', ['bodyClass' => 'page-home'], ['title' => trim((string) setting('seo_home_title')) ?: setting('site_title') . ' — ' . setting('site_tagline'), 'url' => site_url('')]);
        break;

    case 'post':
        $post = get_post_by_slug($seg[1] ?? '');
        if (!$post && !empty($_GET['preview']) && can('posts')) {
            $post = row(POST_SELECT . ' WHERE p.slug = ?', [$seg[1] ?? '']);
        }
        if (!$post) not_found();
        track_visit(true);
        count_post_view($post);
        page_out('post', ['post' => $post], [
            'title' => ($post['meta_title'] ?: $post['title']),
            'description' => $post['meta_desc'] ?: (trim(strip_shortcodes((string) $post['excerpt'])) ?: str_excerpt((string) $post['content'])),
            'image' => $post['cover'] && !str_ends_with(strtolower((string) $post['cover']), '.svg') ? site_url($post['cover']) : '',   // SVG covers can't be link-preview images
            'url' => site_url('post/' . rawurlencode($post['slug'])),
            'type' => 'article',
            'published' => $post['published_at'],
            'post' => $post, 'tags' => post_tags((int) $post['id']),
            'crumbs' => array_values(array_filter([['خانه', site_url('')], $post['cat_slug'] ? [$post['cat_name'], site_url('category/' . rawurlencode($post['cat_slug']))] : null, [$post['title'], site_url('post/' . rawurlencode($post['slug']))]])),
        ]);
        break;

    case 'category':
        $cat = row('SELECT * FROM categories WHERE slug = ?', [$seg[1] ?? '']);
        if (!$cat) not_found();
        track_visit();
        $total = (int) val('SELECT COUNT(*) FROM posts p WHERE ' . pub_where() . ' AND p.category_id = ?', [$cat['id']]);
        $pg = paginate($total, $per, $page);
        $posts = get_posts(['category_id' => $cat['id'], 'limit' => $per, 'offset' => $pg['offset']]);
        page_out('list', ['posts' => $posts, 'pg' => $pg, 'heading' => $cat['name'], 'icon' => $cat['icon'], 'color' => $cat['color'], 'sub' => $cat['description'], 'base' => cat_url($cat), 'bodyClass' => 'page-list'], [
            'title' => $cat['name'] . ($pg['page'] > 1 ? ' — صفحه ' . fa_num((string) $pg['page']) : '') . ' | ' . setting('site_title'), 'description' => $cat['description'] ?: setting('site_description'),
            'crumbs' => [['خانه', site_url('')], [$cat['name'], site_url('category/' . rawurlencode($cat['slug']))]],
        ] + seo_list_meta('category/' . rawurlencode($cat['slug']), $pg));
        break;

    case 'tag':
        $tag = row('SELECT * FROM tags WHERE slug = ?', [$seg[1] ?? '']);
        if (!$tag) not_found();
        track_visit();
        $total = (int) val('SELECT COUNT(*) FROM posts p WHERE ' . pub_where() . ' AND p.id IN (SELECT post_id FROM post_tags WHERE tag_id = ?)', [$tag['id']]);
        $pg = paginate($total, $per, $page);
        $posts = get_posts(['tag_id' => $tag['id'], 'limit' => $per, 'offset' => $pg['offset']]);
        page_out('list', ['posts' => $posts, 'pg' => $pg, 'heading' => '#' . $tag['name'], 'icon' => '🏷️', 'sub' => 'اخبار مرتبط با برچسب «' . $tag['name'] . '»', 'base' => tag_url($tag), 'bodyClass' => 'page-list'], [
            'title' => '#' . $tag['name'] . ($pg['page'] > 1 ? ' — صفحه ' . fa_num((string) $pg['page']) : '') . ' | ' . setting('site_title'),
            'description' => 'جدیدترین اخبار و مطالب درباره‌ی ' . $tag['name'] . ' در ' . setting('site_title'),
            'crumbs' => [['خانه', site_url('')], ['#' . $tag['name'], site_url('tag/' . rawurlencode($tag['slug']))]],
        ] + seo_list_meta('tag/' . rawurlencode($tag['slug']), $pg));
        break;

    case 'archive':
        track_visit();
        [$posts, $total] = get_posts(['limit' => $per, 'offset' => ($page - 1) * $per, 'count' => true]);
        $pg = paginate($total, $per, $page);
        page_out('list', ['posts' => $posts, 'pg' => $pg, 'heading' => 'همه اخبار', 'icon' => '📰', 'sub' => 'جدیدترین مطالب ' . setting('site_title'), 'base' => url('archive'), 'bodyClass' => 'page-list'], [
            'title' => 'همه اخبار' . ($pg['page'] > 1 ? ' — صفحه ' . fa_num((string) $pg['page']) : '') . ' | ' . setting('site_title'),
            'crumbs' => [['خانه', site_url('')], ['همه اخبار', site_url('archive')]],
        ] + seo_list_meta('archive', $pg));
        break;

    case 'author':
        $aKey = (string) ($seg[1] ?? '');
        if (!ctype_digit($aKey)) {                                    // old links (/author/<username>) move to the number, so the login name isn't public
            $old = row('SELECT id FROM users WHERE username = ? AND is_active = 1', [$aKey]);
            if (!$old) not_found();
            // writer pages kept out of Google: the old result moves to the home page; shown in Google: it moves to the writer's new address
            header('Location: ' . site_url(setting_on('seo_index_authors') ? 'author/' . (int) $old['id'] : ''), true, 301); exit;
        }
        $author = row('SELECT * FROM users WHERE id = ? AND is_active = 1', [(int) $aKey]);
        if (!$author) not_found();
        [$posts, $total] = get_posts(['author_id' => $author['id'], 'limit' => $per, 'offset' => ($page - 1) * $per, 'count' => true]);
        $pg = paginate($total, $per, $page);
        $aName = trim((string) $author['display_name']) ?: (string) setting('site_title');
        page_out('list', ['posts' => $posts, 'pg' => $pg, 'heading' => $aName, 'icon' => '✍️', 'sub' => $author['bio'] ?: 'مطالب این نویسنده', 'base' => url('author/' . (int) $author['id']), 'bodyClass' => 'page-list'], [
            'title' => 'مطالب ' . $aName . ' | ' . setting('site_title'),
            'description' => $author['bio'] ?: ('مطالب ' . $aName . ' در ' . setting('site_title')),
            'noindex' => !setting_on('seo_index_authors'),            // writer pages stay out of Google, so the home page wins brand searches
        ] + seo_list_meta('author/' . (int) $author['id'], $pg));
        break;

    case 'search':
        track_visit();
        $qstr = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        $posts = [];
        $pg = paginate(0, $per, 1);
        if (mb_strlen($qstr) >= 2) {
            [$posts, $total] = get_posts(['search' => $qstr, 'limit' => $per, 'offset' => ($page - 1) * $per, 'count' => true]);
            $pg = paginate($total, $per, $page);
        }
        page_out('list', ['posts' => $posts, 'pg' => $pg, 'heading' => $qstr !== '' ? 'جستجو: ' . $qstr : 'جستجو', 'icon' => '🔎', 'sub' => $qstr !== '' ? fa_num((string) $pg['total']) . ' نتیجه یافت شد' : 'عبارت مورد نظر را وارد کنید', 'base' => url('search?q=' . rawurlencode($qstr)), 'search' => $qstr, 'bodyClass' => 'page-list'], [
            'title' => 'جستجو | ' . setting('site_title'), 'noindex' => true,
        ]);
        break;

    case 'page':
        $pageRow = row("SELECT * FROM pages WHERE slug = ? AND status = 'published'", [$seg[1] ?? '']);
        if (!$pageRow) not_found();
        track_visit();
        page_out('page', ['pageRow' => $pageRow], ['title' => $pageRow['title'] . ' | ' . setting('site_title'), 'description' => str_excerpt((string) $pageRow['content']),
            'url' => site_url('page/' . rawurlencode($pageRow['slug'])), 'crumbs' => [['خانه', site_url('')], [$pageRow['title'], site_url('page/' . rawurlencode($pageRow['slug']))]]]);
        break;

    case 'contact':
        $sent = false;
        $errors = [];
        $old = ['name' => '', 'email' => '', 'subject' => '', 'body' => ''];
        if (is_post()) {
            foreach ($old as $k => $_) $old[$k] = mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $k === 'body' ? 5000 : 190);
            if (!csrf_valid()) $errors[] = 'نشست منقضی شده، دوباره تلاش کنید.';
            if (!empty($_POST['website'])) $errors[] = 'درخواست نامعتبر.';
            if (mb_strlen($old['name']) < 2) $errors[] = 'نام را وارد کنید.';
            if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'ایمیل معتبر نیست.';
            if (mb_strlen($old['body']) < 5) $errors[] = 'متن پیام خیلی کوتاه است.';
            if (time() - (int) ($_SESSION['last_contact'] ?? 0) < 60) $errors[] = 'لطفاً یک دقیقه بعد دوباره پیام دهید.';
            if (!$errors) {
                insert('messages', $old + ['ip' => client_ip(), 'created_at' => now()]);
                $_SESSION['last_contact'] = time();
                $sent = true;
                $old = array_map(fn() => '', $old);
            }
        }
        page_out('contact', ['sent' => $sent, 'errors' => $errors, 'old' => $old], ['title' => 'تماس با ما | ' . setting('site_title')]);
        break;

    case 'i':      // invite link: remember who invited, then signup (guests) or straight to the page (members)
        header('Cache-Control: no-store');
        $owner = invite_owner((string) ($seg[1] ?? ''));
        $t = (string) ($seg[2] ?? ''); $dest = '';
        if (preg_match('~^w(\d+)$~', $t, $mm) && ($w = row('SELECT id, slug FROM wheels WHERE id = ?', [(int) $mm[1]]))) {
            $dest = 'wheel/' . rawurlencode((string) $w['slug']);
            if ($owner) $_SESSION['wheel_ref'][(int) $w['id']] = member_invite_code($owner);
        } elseif (preg_match('~^r(\d+)$~', $t, $mm) && ($rf = row('SELECT id, slug FROM raffles WHERE id = ?', [(int) $mm[1]]))) {
            $dest = 'raffle/' . rawurlencode((string) $rf['slug']);
            if ($owner) $_SESSION['raffle_ref'][(int) $rf['id']] = member_invite_code($owner);
        }
        $me = current_member();
        if ($owner && (!$me || (int) $me['id'] !== (int) $owner['id'])) {
            $code = member_invite_code($owner);
            $_SESSION['invite'] = ['code' => $code, 'by' => (int) $owner['id'], 'name' => (string) $owner['first_name']];
            setcookie('nx_inv', $code, ['expires' => time() + 30 * 86400, 'path' => (nx_base_path() ?: '') . '/', 'samesite' => 'Lax', 'httponly' => true, 'secure' => !empty($_SERVER['HTTPS'])]);
        }
        if ($me) { header('Location: ' . url($dest)); exit; }
        if ($dest !== '') $_SESSION['after_login'] = $dest;
        header('Location: ' . url('auth')); exit;

    case 'auth':
        if (current_member()) { header('Location: ' . after_login_url()); exit; }
        header('Cache-Control: no-store');
        echo view('auth');
        break;

    case 'account':
        $member = current_member();
        if (!$member) redirect('auth');
        header('Cache-Control: no-store');
        $errors = [];
        $tab = 'info';
        if (is_post()) {
            if (!csrf_valid()) { $errors['_'] = 'نشست منقضی شده، دوباره تلاش کنید.'; }
            elseif (($_POST['act'] ?? '') === 'logout') { member_logout(); redirect(''); }
            elseif (($_POST['act'] ?? '') === 'profile') {
                $tab = 'edit';
                [$d, $errors] = validate_member_input($_POST + ['username' => $member['username']], (int) $member['id'], false);
                if (!$errors) {
                    unset($d['username']);
                    update('members', $d + ['updated_at' => now()], (int) $member['id']);
                    $_SESSION['account_flash'] = 'اطلاعات شما با موفقیت ذخیره شد ✅';
                    redirect('account');
                }
            } elseif (($_POST['act'] ?? '') === 'password') {
                $tab = 'password';
                $new = (string) ($_POST['new_password'] ?? '');
                if (!password_verify((string) ($_POST['current_password'] ?? ''), $member['password'])) $errors['current_password'] = 'رمز فعلی اشتباه است.';
                elseif (strlen($new) < 8 || !preg_match('~[A-Za-z]~', $new) || !preg_match('~\d~', en_num($new))) $errors['new_password'] = 'رمز جدید باید حداقل ۸ کاراکتر و شامل حروف انگلیسی و عدد باشد.';
                elseif ($new !== (string) ($_POST['confirm_password'] ?? '')) $errors['confirm_password'] = 'تکرار رمز مطابقت ندارد.';
                if (!$errors) {
                    $hash = password_hash($new, PASSWORD_DEFAULT);
                    update('members', ['password' => $hash, 'updated_at' => now()], (int) $member['id']);
                    $_SESSION['mpw'] = substr(hash('sha256', $hash), 0, 32);
                    $_SESSION['account_flash'] = 'رمز عبور تغییر کرد 🔐';
                    redirect('account');
                }
            }
        }
        $flash = $_SESSION['account_flash'] ?? '';
        unset($_SESSION['account_flash']);
        page_out('account', ['member' => $member, 'errors' => $errors, 'tab' => $tab, 'flash' => $flash, 'old' => is_post() ? $_POST : [], 'bodyClass' => 'page-account'], [
            'title' => 'حساب کاربری | ' . setting('site_title'), 'noindex' => true,
        ]);
        break;

    /* ======================= Shop ======================= */
    case 'shop':
        if (!shop_on()) not_found();
        track_visit();
        $slug = $seg[1] ?? '';
        if ($slug === '') {
            $scats = rows('SELECT * FROM shop_categories WHERE is_active = 1 ORDER BY sort_order, id');
            $catSlug = (string) ($_GET['cat'] ?? '');
            $curCat = null;
            foreach ($scats as $c) if ($c['slug'] === $catSlug) $curCat = $c;
            $qstr = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
            $products = shop_products(['category_id' => $curCat['id'] ?? 0, 'search' => $qstr]);
            page_out('shop', ['products' => $products, 'scats' => $scats, 'curCat' => $curCat, 'q' => $qstr, 'bodyClass' => 'page-shop'], [
                'title' => setting('shop_title') . ' | ' . setting('site_title'), 'description' => setting('shop_tagline'),
                'url' => site_url('shop') . ($curCat ? '?cat=' . rawurlencode($curCat['slug']) : ''), 'noindex' => $qstr !== '',
                'crumbs' => [['خانه', site_url('')], [setting('shop_title'), site_url('shop')]],
            ]);
            break;
        }
        $product = row("SELECT p.*, c.name AS cat_name, c.slug AS cat_slug FROM products p LEFT JOIN shop_categories c ON c.id = p.category_id WHERE p.slug = ? AND (p.status IN ('active','soldout') OR ? = 1)", [$slug, current_user() ? 1 : 0]);
        if (!$product) not_found();
        $formErr = []; $formMsg = '';
        if (is_post()) {
            if (!csrf_valid()) $formMsg = 'نشست منقضی شده؛ صفحه را رفرش کنید.';
            else {
                [$vals, $formErr] = validate_product_fields($product, (array) ($_POST['f'] ?? []));
                if (!$formErr) {
                    $amount = isset($_POST['amount']) && $_POST['amount'] !== '' ? (float) en_num((string) $_POST['amount']) : null;
                    $formMsg = cart_add($product, (int) ($_POST['vid'] ?? 0), $amount, (int) ($_POST['qty'] ?? 1), $vals);
                    if ($formMsg === '') redirect(($_POST['go'] ?? '') === 'checkout' ? 'checkout' : 'cart');
                }
            }
        }
        page_out('product', ['product' => $product, 'formErr' => $formErr, 'formMsg' => $formMsg, 'old' => $_POST, 'bodyClass' => 'page-shop page-product'], [
            'title' => $product['title'] . ' | ' . setting('shop_title'), 'description' => $product['subtitle'] ?: str_excerpt((string) $product['description']),
            'image' => $product['image'] && !str_ends_with(strtolower((string) $product['image']), '.svg') ? site_url($product['image']) : '',
            'url' => site_url('shop/' . rawurlencode($product['slug'])), 'product' => $product,
            'crumbs' => [['خانه', site_url('')], [setting('shop_title'), site_url('shop')], [$product['title'], site_url('shop/' . rawurlencode($product['slug']))]],
        ]);
        break;

    case 'cart':
        if (!shop_on()) not_found();
        header('Cache-Control: no-store');
        if (is_post() && csrf_valid()) {
            $key = (string) ($_POST['key'] ?? '');
            $cart = cart_raw();
            switch ($_POST['act'] ?? '') {
                case 'qty': if (isset($cart[$key]) && $cart[$key]['vid']) $cart[$key]['qty'] = max(1, min(20, (int) ($_POST['qty'] ?? 1))); break;
                case 'remove': unset($cart[$key]); break;
                case 'clear': $cart = []; break;
            }
            $_SESSION['cart'] = $cart;
            redirect('cart');
        }
        page_out('cart', ['lines' => cart_lines(), 'bodyClass' => 'page-shop page-cart'], ['title' => 'سبد خرید | ' . setting('shop_title'), 'noindex' => true]);
        break;

    case 'checkout':
        if (!shop_on()) not_found();
        header('Cache-Control: no-store');
        $member = current_member();
        if (!$member) { $_SESSION['after_login'] = 'checkout'; redirect('auth'); }
        $lines = cart_lines();
        if (!$lines) redirect('cart');
        $err = '';
        if (is_post()) {
            if (!csrf_valid()) $err = 'نشست منقضی شده؛ دوباره تلاش کنید.';
            elseif (empty($_POST['agree'])) $err = 'برای ثبت سفارش، قوانین فروشگاه را بپذیرید.';
            else {
                [$order, $err] = order_create($member, (string) ($_POST['note'] ?? ''), (string) ($_POST['coupon'] ?? '0'));
                if ($order) redirect('order/' . $order['code'] . '?new=1');
            }
        }
        page_out('checkout', ['lines' => $lines, 'member' => $member, 'err' => $err, 'coupons' => coupons_for_cart($member, $lines), 'bodyClass' => 'page-shop page-checkout'], ['title' => 'تکمیل خرید | ' . setting('shop_title'), 'noindex' => true]);
        break;

    case 'order':
        header('Cache-Control: no-store');
        shop_expire_due();
        $order = row('SELECT * FROM orders WHERE code = ?', [strtoupper((string) ($seg[1] ?? ''))]);
        $member = current_member();
        $isAdmin = (bool) current_user();
        if (!$order || (!$isAdmin && (!$member || (int) $member['id'] !== (int) $order['member_id']))) {
            if (!$member && !$isAdmin) { $_SESSION['after_login'] = 'order/' . ($seg[1] ?? ''); redirect('auth'); }
            not_found();
        }
        if (($seg[2] ?? '') === 'receipt') {
            if (!$order['receipt']) { http_response_code(404); exit; }
            serve_private((string) $order['receipt']);
        }
        $err = '';
        if (is_post() && $member && (int) $member['id'] === (int) $order['member_id']) {
            if (!csrf_valid()) $err = 'نشست منقضی شده؛ دوباره تلاش کنید.';
            elseif (($_POST['act'] ?? '') === 'receipt' && in_array($order['status'], ['pending_payment', 'rejected'], true)) {
                $up = upload_private_image($_FILES['receipt'] ?? [], 'receipts');
                if (!$up['ok']) $err = $up['error'];
                else {
                    order_set_status($order, 'reviewing', [
                        'receipt' => $up['path'], 'reject_reason' => null,
                        'receipt_ref' => mb_substr(en_num(trim((string) ($_POST['ref'] ?? ''))), 0, 60) ?: null,
                    ]);
                    redirect('order/' . $order['code'] . '?sent=1');
                }
            } elseif (($_POST['act'] ?? '') === 'cancel' && $order['status'] === 'pending_payment') {
                order_set_status($order, 'canceled', ['reject_reason' => 'لغو توسط مشتری']);
                redirect('order/' . $order['code']);
            }
        }
        notifs_cleanup();
        if ($member) q('UPDATE notifications SET is_read = 1 WHERE audience = ? AND ref_type = ? AND ref_id = ?', ['m:' . $member['id'], 'order', $order['id']]);
        page_out('order', ['order' => $order, 'items' => order_items((int) $order['id']), 'err' => $err, 'bodyClass' => 'page-shop page-order'], ['title' => 'سفارش ' . $order['code'] . ' | ' . setting('shop_title'), 'noindex' => true]);
        break;

    case 'orders':
        $member = current_member();
        if (!$member) { $_SESSION['after_login'] = 'orders'; redirect('auth'); }
        header('Cache-Control: no-store');
        shop_expire_due();
        $orders = rows('SELECT * FROM orders WHERE member_id = ? ORDER BY id DESC LIMIT 100', [$member['id']]);
        page_out('orders', ['orders' => $orders, 'member' => $member, 'bodyClass' => 'page-shop page-orders'], ['title' => 'سفارش‌های من | ' . setting('shop_title'), 'noindex' => true]);
        break;

    case 'support':
        if (!setting_on('shop_support_on')) not_found();
        $member = current_member();
        $code = strtoupper((string) ($seg[1] ?? ''));
        // the support desk is for members; an admin without a member account only views ticket pages (from the panel)
        if (!$member && ($code === '' || !current_user())) { $_SESSION['after_login'] = 'support' . ($code !== '' ? '/' . $code : ''); redirect('auth'); }
        header('Cache-Control: no-store');
        $err = '';
        if ($code === '') {
            if (is_post()) {
                $subject = mb_substr(trim((string) ($_POST['subject'] ?? '')), 0, 150);
                $body = mb_substr(trim((string) ($_POST['body'] ?? '')), 0, 4000);
                $oid = (int) ($_POST['order_id'] ?? 0);
                if (!csrf_valid()) $err = 'نشست منقضی شده؛ دوباره تلاش کنید.';
                elseif (mb_strlen($subject) < 3) $err = 'موضوع را بنویسید.';
                elseif (mb_strlen($body) < 5) $err = 'متن پیام خیلی کوتاه است.';
                elseif (time() - (int) ($_SESSION['last_ticket'] ?? 0) < 30) $err = 'لطفاً چند ثانیه بعد دوباره تلاش کنید.';
                elseif ((int) val("SELECT COUNT(*) FROM tickets WHERE member_id = ? AND status = 'open'", [$member['id']]) >= 5) $err = 'شما ۵ تیکت باز دارید؛ لطفاً منتظر پاسخ بمانید.';
                else {
                    $att = null;
                    if (!empty($_FILES['file']['name'])) { $up = upload_private_image($_FILES['file'], 'tickets'); if (!$up['ok']) $err = $up['error']; else $att = $up['path']; }
                    if (!$err) {
                        if ($oid && !val('SELECT id FROM orders WHERE id = ? AND member_id = ?', [$oid, $member['id']])) $oid = 0;
                        $tcode = new_code('tickets', 'T');
                        $tid = insert('tickets', ['code' => $tcode, 'member_id' => $member['id'], 'order_id' => $oid ?: null, 'subject' => $subject, 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
                        ticket_add_message(row('SELECT * FROM tickets WHERE id = ?', [$tid]), 'member', $body, $att);
                        $_SESSION['last_ticket'] = time();
                        redirect('support/' . $tcode);
                    }
                }
            }
            $tickets = rows('SELECT t.*, (SELECT COUNT(*) FROM ticket_messages m WHERE m.ticket_id = t.id) AS msgs FROM tickets t WHERE member_id = ? ORDER BY updated_at DESC LIMIT 100', [$member['id']]);
            $myOrders = rows('SELECT id, code, total, created_at FROM orders WHERE member_id = ? ORDER BY id DESC LIMIT 30', [$member['id']]);
            page_out('support', ['tickets' => $tickets, 'myOrders' => $myOrders, 'err' => $err, 'old' => $_POST, 'pre' => (string) ($_GET['order'] ?? ''), 'bodyClass' => 'page-shop page-support'], ['title' => 'پشتیبانی فروشگاه | ' . setting('site_title'), 'noindex' => true]);
            break;
        }
        $ticket = row('SELECT * FROM tickets WHERE code = ?', [$code]);
        if (!$ticket || (!current_user() && (int) $ticket['member_id'] !== (int) $member['id'])) not_found();
        if (($seg[2] ?? '') === 'file') {
            $msg = row('SELECT attachment FROM ticket_messages WHERE id = ? AND ticket_id = ?', [(int) ($seg[3] ?? 0), $ticket['id']]);
            if (!$msg || !$msg['attachment']) { http_response_code(404); exit; }
            serve_private((string) $msg['attachment']);
        }
        if (is_post() && $member && (int) $ticket['member_id'] === (int) $member['id']) {
            $body = mb_substr(trim((string) ($_POST['body'] ?? '')), 0, 4000);
            if (!csrf_valid()) $err = 'نشست منقضی شده؛ دوباره تلاش کنید.';
            elseif (($_POST['act'] ?? '') === 'close') { q("UPDATE tickets SET status = 'closed', updated_at = ? WHERE id = ?", [now(), $ticket['id']]); unnotify('admin', 'ticket', (int) $ticket['id']); redirect('support/' . $code); }
            elseif (mb_strlen($body) < 2) $err = 'متن پیام را بنویسید.';
            else {
                $att = null;
                if (!empty($_FILES['file']['name'])) { $up = upload_private_image($_FILES['file'], 'tickets'); if (!$up['ok']) $err = $up['error']; else $att = $up['path']; }
                if (!$err) { ticket_add_message($ticket, 'member', $body, $att); redirect('support/' . $code . '#end'); }
            }
        }
        if ($member) q('UPDATE notifications SET is_read = 1 WHERE audience = ? AND ref_type = ? AND ref_id = ?', ['m:' . $member['id'], 'ticket', $ticket['id']]);
        $tmsgs = rows('SELECT * FROM ticket_messages WHERE ticket_id = ? ORDER BY id', [$ticket['id']]);
        $tOrder = $ticket['order_id'] ? row('SELECT * FROM orders WHERE id = ?', [$ticket['order_id']]) : null;
        page_out('ticket', ['ticket' => $ticket, 'msgs' => $tmsgs, 'tOrder' => $tOrder, 'err' => $err, 'bodyClass' => 'page-shop page-ticket'], ['title' => $ticket['subject'] . ' | پشتیبانی', 'noindex' => true]);
        break;

    case 'go':
        $ad = row('SELECT * FROM ads WHERE id = ?', [(int) ($seg[1] ?? 0)]);
        $link = $ad ? safe_link($ad['link']) : '';
        if (!$ad || $link === '') redirect('');
        if (!current_user()) { // panel users testing their own ads aren't counted
            q('UPDATE ads SET clicks = clicks + 1 WHERE id = ?', [$ad['id']]);
            q('INSERT INTO stats_daily (day, ad_clicks) VALUES (?, 1) ON DUPLICATE KEY UPDATE ad_clicks = ad_clicks + 1', [date('Y-m-d')]);
        }
        header('Location: ' . $link, true, 302);
        exit;

    case 'api':
        require NX_APP . '/api.php';
        break;

    /* ======================= Raffles ======================= */
    case 'raffle':
        if (!setting_on('footer_raffle_link') && !current_user()) { http_response_code(410); page_out('section_closed', ['what' => 'قرعه‌کشی', 'icon' => '🎰'], ['title' => 'قرعه‌کشی | ' . setting('site_title'), 'noindex' => true]); break; }
        raffles_due();
        track_visit();
        $slug = (string) ($seg[1] ?? '');
        if ($slug === '') {
            $list = rows("SELECT * FROM raffles WHERE status IN ('active','drawn','failed') ORDER BY (status = 'active') DESC, draw_at DESC LIMIT 40");
            page_out('raffles', ['list' => $list, 'bodyClass' => 'page-raffles'], ['title' => 'قرعه‌کشی‌ها | ' . setting('site_title'),
                'description' => 'قرعه‌کشی‌های ' . setting('site_title') . ' — شرکت کن و جایزه ببر', 'url' => site_url('raffle'),
                'crumbs' => [['خانه', site_url('')], ['قرعه‌کشی‌ها', site_url('raffle')]]]);
            break;
        }
        $raffle = row('SELECT * FROM raffles WHERE slug = ?', [$slug]);
        if (!$raffle || ($raffle['status'] === 'draft' && !current_user())) not_found();
        if (($seg[2] ?? '') === 'receipt') {   // a member's own receipt, or any for the admin
            $req = row('SELECT * FROM raffle_requests WHERE id = ? AND raffle_id = ?', [(int) ($seg[3] ?? 0), $raffle['id']]);
            $me = current_member();
            if (!$req || !$req['receipt'] || (!current_user() && (!$me || (int) $me['id'] !== (int) $req['member_id']))) not_found();
            serve_private((string) $req['receipt']);
        }
        if (!empty($_GET['ref']) && preg_match('~^[A-Za-z0-9-]{3,20}$~', (string) $_GET['ref'])) $_SESSION['raffle_ref'][(int) $raffle['id']] = (string) $_GET['ref'];
        if (!empty($_GET['ref']) && !current_member() && ($io = invite_owner((string) $_GET['ref']))) $_SESSION['invite'] = ['code' => member_invite_code($io), 'by' => (int) $io['id'], 'name' => (string) $io['first_name']];
        $raffle = raffle_maybe_draw($raffle);
        if (!raffle_page_allowed($raffle, current_member())) {
            http_response_code(403);
            page_out('raffle_locked', ['raffle' => $raffle, 'bodyClass' => 'page-raffle'], ['title' => $raffle['title'] . ' | ' . setting('site_title'), 'noindex' => true,
                'url' => site_url('raffle/' . rawurlencode($raffle['slug'])), 'crumbs' => [['خانه', site_url('')], ['قرعه‌کشی‌ها', site_url('raffle')]]]);
            break;
        }
        $prz = raffle_prizes($raffle);
        page_out('raffle', ['raffle' => $raffle, 'bodyClass' => 'page-raffle'], [
            'title' => $raffle['title'] . ' | قرعه‌کشی ' . setting('site_title'),
            'description' => '🎁 جایزه: ' . implode('، ', array_column($prz, 'title')) . ' — ' . str_excerpt((string) $raffle['description'], 200),
            'image' => $raffle['image'] && !str_ends_with(strtolower((string) $raffle['image']), '.svg') ? site_url($raffle['image']) : '',
            'url' => site_url('raffle/' . rawurlencode($raffle['slug'])), 'noindex' => $raffle['status'] === 'draft',
            'crumbs' => [['خانه', site_url('')], ['قرعه‌کشی‌ها', site_url('raffle')], [$raffle['title'], site_url('raffle/' . rawurlencode($raffle['slug']))]],
        ]);
        break;

    /* ======================= Prizes & notifications (members) ======================= */
    case 'prizes':
    case 'notifications':
        $member = current_member();
        if (!$member) { $_SESSION['after_login'] = $seg[0]; redirect('auth'); }
        header('Cache-Control: no-store');
        if ($seg[0] === 'prizes') {
            page_out('prizes', ['member' => $member, 'list' => prizes_for_member((int) $member['id']), 'bodyClass' => 'page-prizes'], ['title' => 'جایزه‌های من | ' . setting('site_title'), 'noindex' => true]);
        } else {
            $aud = 'm:' . $member['id']; notifs_cleanup();
            $per = 10; $total = (int) val('SELECT COUNT(*) FROM notifications WHERE audience = ?', [$aud]); $pages = max(1, (int) ceil($total / $per));
            $page = max(1, min($pages, (int) ($_GET['p'] ?? 1)));
            $list = rows('SELECT * FROM notifications WHERE audience = ? ORDER BY updated_at DESC, id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per), [$aud]);
            q('UPDATE notifications SET is_read = 1 WHERE audience = ? AND is_read = 0', [$aud]);
            page_out('notifications', ['list' => $list, 'page' => $page, 'pages' => $pages, 'bodyClass' => 'page-prizes'], ['title' => 'اعلان‌های من | ' . setting('site_title'), 'noindex' => true]);
        }
        break;

    /* ======================= Lucky wheel ======================= */
    case 'wheel':
        if (!setting_on('footer_wheel_link') && !current_user()) { http_response_code(410); page_out('section_closed', ['what' => 'گردونه شانس', 'icon' => '🎡'], ['title' => 'گردونه شانس | ' . setting('site_title'), 'noindex' => true]); break; }
        track_visit();
        $slug = (string) ($seg[1] ?? '');
        if ($slug === '') {
            $list = array_values(array_filter(rows("SELECT * FROM wheels WHERE audience = 'public' AND status = 'active' ORDER BY id DESC LIMIT 30"), 'wheel_open'));
            if (count($list) === 1) redirect('wheel/' . rawurlencode($list[0]['slug']));
            page_out('wheels', ['list' => $list, 'bodyClass' => 'page-wheels'], ['title' => 'گردونه شانس | ' . setting('site_title'),
                'description' => 'گردونه شانس ' . setting('site_title') . ' — بچرخون و جایزه ببر', 'url' => site_url('wheel'),
                'crumbs' => [['خانه', site_url('')], ['گردونه شانس', site_url('wheel')]]]);
            break;
        }
        $wheel = row('SELECT * FROM wheels WHERE slug = ?', [$slug]);
        if (!$wheel || $wheel['audience'] !== 'public') not_found();
        if (!wheel_open($wheel) && !current_user()) { http_response_code(410); page_out('section_closed', ['what' => $wheel['title'], 'icon' => '🎡'], ['title' => $wheel['title'] . ' | ' . setting('site_title'), 'noindex' => true]); break; }
        if (!empty($_GET['ref']) && preg_match('~^[A-Za-z0-9-]{3,20}$~', (string) $_GET['ref'])) $_SESSION['wheel_ref'][(int) $wheel['id']] = (string) $_GET['ref'];
        if (!empty($_GET['ref']) && !current_member() && ($io = invite_owner((string) $_GET['ref']))) $_SESSION['invite'] = ['code' => member_invite_code($io), 'by' => (int) $io['id'], 'name' => (string) $io['first_name']];
        wheel_track_ref($wheel, current_member());
        page_out('wheel', ['wheel' => $wheel, 'bodyClass' => 'page-wheel'], [
            'title' => $wheel['title'] . ' | گردونه شانس ' . setting('site_title'),
            'description' => '🎡 بچرخون و جایزه ببر: ' . implode('، ', array_slice(array_column(array_filter(wheel_items($wheel), fn($it) => $it['kind'] === 'prize'), 'label'), 0, 4)) . ' — ' . str_excerpt((string) $wheel['description'], 180),
            'image' => $wheel['image'] && !str_ends_with(strtolower((string) $wheel['image']), '.svg') ? site_url($wheel['image']) : '',
            'url' => site_url('wheel/' . rawurlencode($wheel['slug'])), 'noindex' => $wheel['status'] !== 'active',
            'crumbs' => [['خانه', site_url('')], ['گردونه شانس', site_url('wheel')], [$wheel['title'], site_url('wheel/' . rawurlencode($wheel['slug']))]],
        ]);
        break;

    case 'sitemap.xml':
        header('Content-Type: application/xml; charset=utf-8');
        echo view('sitemap');
        break;

    case 'news-sitemap.xml':
        if (!setting_on('news_sitemap_on')) not_found();
        header('Content-Type: application/xml; charset=utf-8');
        echo view('news-sitemap');
        break;

    case 'robots.txt':
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nDisallow: /admin/\nDisallow: /install/\nDisallow: /search\nDisallow: /cart\nDisallow: /checkout\nDisallow: /order/\nDisallow: /orders\nDisallow: /support\nSitemap: " . site_url('sitemap.xml') . "\n" . (setting_on('news_sitemap_on') ? 'Sitemap: ' . site_url('news-sitemap.xml') . "\n" : '');
        $rx = trim(str_replace("\r", '', (string) setting('robots_extra')));
        if ($rx !== '') echo "\n" . $rx . "\n";
        break;

    default:
        not_found();
}
