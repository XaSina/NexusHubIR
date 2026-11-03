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
    $content = view($tpl, $data);
    echo view('layout', ['content' => $content, 'meta' => $meta, 'bodyClass' => $data['bodyClass'] ?? 'page-' . $tpl]);
}

function not_found(): never
{
    http_response_code(404);
    page_out('404', [], ['title' => 'صفحه پیدا نشد']);
    exit;
}

$path = route_path();
$seg = $path === '' ? [] : explode('/', $path);
$page = max(1, (int) ($_GET['page'] ?? 1));
$per = max(1, min(60, (int) setting('posts_per_page', 12)));

// حالت تعمیر
if (setting_on('maintenance') && !current_user() && ($seg[0] ?? '') !== 'go') {
    http_response_code(503);
    echo view('maintenance');
    exit;
}

switch ($seg[0] ?? '') {

    case '':
        track_visit();
        page_out('home', ['bodyClass' => 'page-home'], ['title' => setting('site_title') . ' — ' . setting('site_tagline')]);
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
            'description' => $post['meta_desc'] ?: ($post['excerpt'] ?: str_excerpt((string) $post['content'])),
            'image' => $post['cover'] ? site_url($post['cover']) : '',
            'url' => site_url('post/' . rawurlencode($post['slug'])),
            'type' => 'article',
            'published' => $post['published_at'],
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
            'title' => $cat['name'] . ' | ' . setting('site_title'), 'description' => $cat['description'] ?: setting('site_description'),
        ]);
        break;

    case 'tag':
        $tag = row('SELECT * FROM tags WHERE slug = ?', [$seg[1] ?? '']);
        if (!$tag) not_found();
        track_visit();
        $total = (int) val('SELECT COUNT(*) FROM posts p WHERE ' . pub_where() . ' AND p.id IN (SELECT post_id FROM post_tags WHERE tag_id = ?)', [$tag['id']]);
        $pg = paginate($total, $per, $page);
        $posts = get_posts(['tag_id' => $tag['id'], 'limit' => $per, 'offset' => $pg['offset']]);
        page_out('list', ['posts' => $posts, 'pg' => $pg, 'heading' => '#' . $tag['name'], 'icon' => '🏷️', 'sub' => 'اخبار مرتبط با برچسب «' . $tag['name'] . '»', 'base' => tag_url($tag), 'bodyClass' => 'page-list'], [
            'title' => '#' . $tag['name'] . ' | ' . setting('site_title'),
        ]);
        break;

    case 'archive':
        track_visit();
        [$posts, $total] = get_posts(['limit' => $per, 'offset' => ($page - 1) * $per, 'count' => true]);
        $pg = paginate($total, $per, $page);
        page_out('list', ['posts' => $posts, 'pg' => $pg, 'heading' => 'همه اخبار', 'icon' => '📰', 'sub' => 'جدیدترین مطالب ' . setting('site_title'), 'base' => url('archive'), 'bodyClass' => 'page-list'], [
            'title' => 'همه اخبار | ' . setting('site_title'),
        ]);
        break;

    case 'author':
        $author = row('SELECT * FROM users WHERE username = ? AND is_active = 1', [$seg[1] ?? '']);
        if (!$author) not_found();
        [$posts, $total] = get_posts(['author_id' => $author['id'], 'limit' => $per, 'offset' => ($page - 1) * $per, 'count' => true]);
        $pg = paginate($total, $per, $page);
        page_out('list', ['posts' => $posts, 'pg' => $pg, 'heading' => $author['display_name'] ?: $author['username'], 'icon' => '✍️', 'sub' => $author['bio'] ?: 'مطالب این نویسنده', 'base' => url('author/' . rawurlencode($author['username'])), 'bodyClass' => 'page-list'], [
            'title' => ($author['display_name'] ?: $author['username']) . ' | ' . setting('site_title'),
        ]);
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
        page_out('page', ['pageRow' => $pageRow], ['title' => $pageRow['title'] . ' | ' . setting('site_title'), 'description' => str_excerpt((string) $pageRow['content'])]);
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

    case 'go':
        $ad = row('SELECT * FROM ads WHERE id = ?', [(int) ($seg[1] ?? 0)]);
        $link = $ad ? safe_link($ad['link']) : '';
        if (!$ad || $link === '') redirect('');
        q('UPDATE ads SET clicks = clicks + 1 WHERE id = ?', [$ad['id']]);
        q('INSERT INTO stats_daily (day, ad_clicks) VALUES (?, 1) ON DUPLICATE KEY UPDATE ad_clicks = ad_clicks + 1', [date('Y-m-d')]);
        header('Location: ' . $link, true, 302);
        exit;

    case 'api':
        require NX_APP . '/api.php';
        break;

    case 'feed':
        header('Content-Type: application/rss+xml; charset=utf-8');
        echo view('feed', ['posts' => get_posts(['limit' => 30])]);
        break;

    case 'sitemap.xml':
        header('Content-Type: application/xml; charset=utf-8');
        echo view('sitemap');
        break;

    case 'robots.txt':
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nDisallow: /admin/\nDisallow: /install/\nDisallow: /search\nSitemap: " . site_url('sitemap.xml') . "\n";
        break;

    default:
        not_found();
}
