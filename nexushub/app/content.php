<?php
/**
 * کوئری‌ها و رندر محتوا (اخبار، دسته‌ها، برچسب‌ها)
 */

const POST_SELECT = 'SELECT p.*, c.name AS cat_name, c.slug AS cat_slug, c.color AS cat_color, c.icon AS cat_icon,
    u.display_name AS author_name, u.username AS author_username, u.avatar AS author_avatar
    FROM posts p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN users u ON u.id = p.author_id';

/** شرط خبرهای منتشر شده (زمان‌بندی‌شده‌ها تا زمانشان نرسد نمایش داده نمی‌شوند) */
function pub_where(string $alias = 'p'): string
{
    return "$alias.status = 'published' AND $alias.published_at <= '" . now() . "'";
}

function get_posts(array $o = []): array
{
    $where = [pub_where()];
    $params = [];
    if (!empty($o['category_id'])) { $where[] = 'p.category_id = ?'; $params[] = (int) $o['category_id']; }
    if (!empty($o['featured'])) $where[] = 'p.is_featured = 1';
    if (!empty($o['breaking'])) $where[] = 'p.is_breaking = 1';
    if (!empty($o['author_id'])) { $where[] = 'p.author_id = ?'; $params[] = (int) $o['author_id']; }
    if (!empty($o['exclude'])) {
        $ex = array_map('intval', (array) $o['exclude']);
        $where[] = 'p.id NOT IN (' . implode(',', $ex ?: [0]) . ')';
    }
    if (!empty($o['tag_id'])) { $where[] = 'p.id IN (SELECT post_id FROM post_tags WHERE tag_id = ?)'; $params[] = (int) $o['tag_id']; }
    if (!empty($o['since'])) { $where[] = 'p.published_at >= ?'; $params[] = $o['since']; }
    if (!empty($o['search'])) {
        $s = '%' . str_replace(['%', '_'], ['\%', '\_'], $o['search']) . '%';
        $where[] = '(p.title LIKE ? OR p.excerpt LIKE ? OR p.content LIKE ?)';
        array_push($params, $s, $s, $s);
    }
    $order = match ($o['order'] ?? 'new') {
        'views' => 'p.views DESC, p.published_at DESC',
        'random' => 'RAND()',
        default => 'p.published_at DESC, p.id DESC',
    };
    $limit = (int) ($o['limit'] ?? 10);
    $offset = (int) ($o['offset'] ?? 0);
    $sql = POST_SELECT . ' WHERE ' . implode(' AND ', $where) . " ORDER BY $order LIMIT $limit OFFSET $offset";
    $list = rows($sql, $params);
    if (!empty($o['count'])) {
        $total = (int) val('SELECT COUNT(*) FROM posts p WHERE ' . implode(' AND ', $where), $params);
        return [$list, $total];
    }
    return $list;
}

function get_post_by_slug(string $slug): ?array
{
    return row(POST_SELECT . ' WHERE p.slug = ? AND ' . pub_where(), [$slug]);
}

function get_categories(bool $withCount = false): array
{
    static $cache = [];
    $k = $withCount ? 1 : 0;
    if (!isset($cache[$k])) {
        $cache[$k] = $withCount
            ? rows('SELECT c.*, (SELECT COUNT(*) FROM posts p WHERE p.category_id = c.id AND ' . pub_where() . ') AS cnt FROM categories c ORDER BY c.sort_order, c.id')
            : rows('SELECT * FROM categories ORDER BY sort_order, id');
    }
    return $cache[$k];
}

function post_tags(int $postId): array
{
    return rows('SELECT t.* FROM tags t JOIN post_tags pt ON pt.tag_id = t.id WHERE pt.post_id = ? ORDER BY t.name', [$postId]);
}

function popular_tags(int $limit = 20): array
{
    return rows("SELECT t.*, COUNT(pt.post_id) AS cnt FROM tags t JOIN post_tags pt ON pt.tag_id = t.id GROUP BY t.id ORDER BY cnt DESC LIMIT $limit");
}

function sync_tags(int $postId, string $csv): void
{
    q('DELETE FROM post_tags WHERE post_id = ?', [$postId]);
    $names = array_unique(array_filter(array_map(fn($t) => trim(str_replace('#', '', $t)), preg_split('~[,،\n]+~u', $csv))));
    foreach (array_slice($names, 0, 20) as $name) {
        $name = mb_substr($name, 0, 60);
        $slug = slugify($name);
        $tag = row('SELECT id FROM tags WHERE slug = ?', [$slug]);
        $tid = $tag ? (int) $tag['id'] : insert('tags', ['name' => $name, 'slug' => $slug]);
        q('INSERT IGNORE INTO post_tags (post_id, tag_id) VALUES (?, ?)', [$postId, $tid]);
    }
}

function post_url(array $p): string
{
    return url('post/' . rawurlencode($p['slug']));
}

function cat_url(array $c): string
{
    return url('category/' . rawurlencode($c['slug'] ?? $c['cat_slug'] ?? ''));
}

function tag_url(array $t): string
{
    return url('tag/' . rawurlencode($t['slug']));
}

function menu_link(string $u): string
{
    if (preg_match('~^https?://~i', $u)) return $u;
    if ($u === '/' || $u === '') return url('');
    return url(ltrim($u, '/'));
}

/** تصویر کاور یا کاور جایگزین گرافیکی */
function cover_html(array $p, string $class = '', bool $eager = false): string
{
    $color = $p['cat_color'] ?? '#ff1f3d';
    if (!empty($p['cover'])) {
        return '<img class="cover ' . e($class) . '" src="' . e(media_url($p['cover'])) . '" alt="' . e($p['title']) . '"' . ($eager ? ' fetchpriority="high"' : ' loading="lazy"') . ' decoding="async">';
    }
    return '<div class="cover cover-fallback ' . e($class) . '" style="--c:' . e($color) . '"><span class="cf-icon">' . e($p['cat_icon'] ?? '📰') . '</span><span class="cf-mark">NX</span></div>';
}

/** رندر متن خبر: شورت‌کدها + تبلیغ میانی */
function render_content(string $html, bool $withAds = true): string
{
    // [video src="uploads/…/x.mp4" title="…"] → professional player + download button
    $html = preg_replace_callback('~(?:<p>\s*)?\[video\s+src="(?:[^"\s]*?/)?(uploads/[A-Za-z0-9_./-]+\.(?:mp4|webm))"(?:\s+title="([^"\]]{0,120})")?\s*\](?:\s*</p>)?~u', function ($m) {
        if (str_contains($m[1], '..')) return '';
        $file = NX_ROOT . '/' . $m[1];
        $src = media_url($m[1]);
        $title = trim($m[2] ?? '');
        $size = is_file($file) ? filesize($file) : 0;
        $sizeTxt = $size ? fa_num(number_format($size / 1048576, $size > 104857600 ? 0 : 1)) . ' مگابایت' : '';
        $ext = strtoupper(pathinfo($m[1], PATHINFO_EXTENSION));
        $dlName = ($title !== '' ? preg_replace('~[\\/:*?"<>|]+~u', '', $title) : 'nexus-video') . '.' . strtolower($ext);
        return '<figure class="nx-player">'
            . '<div class="np-stage"><video src="' . e($src) . '#t=0.1" preload="metadata" playsinline controls controlslist="nodownload"></video></div>'
            . '<figcaption class="np-foot"><span class="np-info">' . icon('youtube', 16) . ' <b>' . e($title !== '' ? $title : 'ویدیو') . '</b>'
            . ($sizeTxt ? '<small>' . e($ext) . ' · ' . $sizeTxt . '</small>' : '') . '</span>'
            . '<a class="np-dl" href="' . e($src) . '" download="' . e($dlName) . '">' . icon('arrow-up', 16) . ' دانلود ویدیو</a></figcaption>'
            . '</figure>';
    }, $html);
    // uploads/… paths → full URLs (after [video]: its src must still be the stored relative path)
    $html = content_for_display($html);
    // [telegram post="Channel/123"]
    $html = preg_replace_callback('~\[telegram\s+post="([A-Za-z0-9_]{3,64}/\d{1,9})"\]~', function ($m) {
        return '<div class="tg-embed"><script async src="https://telegram.org/js/telegram-widget.js?22" data-telegram-post="' . e($m[1]) . '" data-width="100%" data-dark="1"></script></div>';
    }, $html);
    // [aparat id="xxxxx"]
    $html = preg_replace_callback('~\[aparat\s+id="([A-Za-z0-9]{3,20})"\]~', function ($m) {
        return '<div class="video-embed"><iframe src="https://www.aparat.com/video/video/embed/videohash/' . e($m[1]) . '/vt/frame" allowfullscreen loading="lazy"></iframe></div>';
    }, $html);
    // [youtube id="xxxxx"]
    $html = preg_replace_callback('~\[youtube\s+id="([A-Za-z0-9_\-]{6,20})"\]~', function ($m) {
        return '<div class="video-embed"><iframe src="https://www.youtube-nocookie.com/embed/' . e($m[1]) . '" allowfullscreen loading="lazy"></iframe></div>';
    }, $html);
    // پوشش ویدئوی iframe
    $html = preg_replace('~(?<!<div class="video-embed">)(<iframe\b[^>]*></iframe>)~i', '<div class="video-embed">$1</div>', $html);

    if ($withAds) {
        $adHtml = ad_slot('post_middle');
        if ($adHtml !== '') {
            $n = max(1, (int) setting('post_middle_paragraph', 3));
            $parts = preg_split('~(</p>)~i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
            $count = 0;
            $out = '';
            $done = false;
            foreach ($parts as $part) {
                $out .= $part;
                if (!$done && strtolower($part) === '</p>') {
                    $count++;
                    if ($count === $n) { $out .= $adHtml; $done = true; }
                }
            }
            $html = $done ? $out : $html . $adHtml;
        }
    }
    return $html;
}

/** ثبت آمار بازدید روزانه */
function track_visit(bool $isPost = false): void
{
    if (preg_match('~bot|crawl|spider|slurp|facebookexternalhit|preview~i', $_SERVER['HTTP_USER_AGENT'] ?? '')) return;
    if (current_user()) return; // the site's own admins/editors don't inflate the stats
    // the same page opened again by the same visitor within 30 min (Back button, refresh) is not a new view
    $path = substr((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), 0, 190);
    $pv = array_filter($_SESSION['pv'] ?? [], fn($t) => $t > time() - 1800);
    if (isset($pv[$path])) { $_SESSION['pv'] = $pv; return; }
    $pv[$path] = time();
    $_SESSION['pv'] = array_slice($pv, -80, null, true);
    $today = date('Y-m-d');
    $newVisitor = ($_COOKIE['nx_v'] ?? '') !== $today;
    if ($newVisitor) {
        setcookie('nx_v', $today, ['expires' => strtotime('tomorrow'), 'path' => nx_base_path() . '/', 'samesite' => 'Lax']);
    }
    try {
        q('INSERT INTO stats_daily (day, views, visitors, post_views) VALUES (?, 1, ?, ?)
           ON DUPLICATE KEY UPDATE views = views + 1, visitors = visitors + VALUES(visitors), post_views = post_views + VALUES(post_views)',
            [$today, $newVisitor ? 1 : 0, $isPost ? 1 : 0]);
    } catch (Throwable $e) {
    }
}

function count_post_view(array $post): void
{
    if (current_user()) return; // panel users checking their own posts aren't counted
    if (preg_match('~bot|crawl|spider|slurp|facebookexternalhit|preview~i', $_SERVER['HTTP_USER_AGENT'] ?? '')) return;
    $seen = $_SESSION['seen'] ?? [];
    if (!in_array((int) $post['id'], $seen, true)) {
        q('UPDATE posts SET views = views + 1 WHERE id = ?', [$post['id']]);
        $seen[] = (int) $post['id'];
        $_SESSION['seen'] = array_slice($seen, -200);
    }
}

function post_reactions(int $postId): array
{
    $list = array_filter(array_map('trim', explode(',', (string) setting('reactions_list'))));
    $counts = [];
    foreach (rows('SELECT emoji, cnt FROM reactions WHERE post_id = ?', [$postId]) as $r) $counts[$r['emoji']] = (int) $r['cnt'];
    $out = [];
    foreach ($list as $em) $out[$em] = $counts[$em] ?? 0;
    return $out;
}

function approved_comments(int $postId): array
{
    $all = rows("SELECT c.*, u.avatar, u.role FROM comments c LEFT JOIN users u ON u.id = c.user_id WHERE c.post_id = ? AND c.status = 'approved' ORDER BY c.created_at ASC", [$postId]);
    $tree = [];
    $children = [];
    foreach ($all as $c) {
        if ($c['parent_id']) $children[(int) $c['parent_id']][] = $c;
        else $tree[] = $c;
    }
    foreach ($tree as &$t) $t['replies'] = $children[(int) $t['id']] ?? [];
    return $tree;
}

/** نمایش ایموجی بدون صدمه */
function social_links(): array
{
    $map = [
        'social_telegram' => ['telegram', 'کانال تلگرام'],
        'social_group' => ['comment', 'گروه گفتگو'],
        'social_instagram' => ['instagram', 'اینستاگرام'],
        'social_x' => ['x', 'ایکس'],
        'social_youtube' => ['youtube', 'یوتیوب'],
        'social_aparat' => ['youtube', 'آپارات'],
    ];
    $out = [];
    foreach ($map as $k => [$ic, $label]) {
        $v = safe_link((string) setting($k));
        if ($v !== '') $out[] = ['url' => $v, 'icon' => $ic, 'label' => $label];
    }
    return $out;
}

function telegram_handle(): string
{
    $tg = (string) setting('social_telegram');
    if (preg_match('~t\.me/([A-Za-z0-9_]+)~', $tg, $m)) return '@' . $m[1];
    return '';
}

/** مسیر تصاویر آپلودی داخل متن را نسبی ذخیره می‌کند تا با جابجایی سایت (XAMPP → هاست) خراب نشود */
function content_to_store(string $html): string
{
    $prefixes = [preg_quote(rtrim(site_url(''), '/') . '/', '~'), preg_quote(nx_base_path() . '/', '~')];
    foreach ($prefixes as $p) {
        $html = preg_replace('~(src|href)="' . $p . '(uploads/)~i', '$1="$2', $html);
    }
    return $html;
}

function content_for_display(string $html): string
{
    return preg_replace('~(src|href)="(uploads/)~i', '$1="' . nx_base_path() . '/$2', $html);
}

/** استوری‌های فعال و منقضی‌نشده همراه اسلایدهایشان (برای صفحه اصلی) */
function get_active_stories(): array
{
    try {
        $list = rows('SELECT * FROM stories WHERE is_active = 1 AND (expires_at IS NULL OR expires_at > ?) ORDER BY sort_order, id DESC', [now()]);
    } catch (Throwable $e) {
        return [];
    }
    if (!$list) return [];
    $ids = array_map(fn($s) => (int) $s['id'], $list);
    $items = [];
    foreach (rows('SELECT * FROM story_items WHERE story_id IN (' . implode(',', $ids) . ') ORDER BY sort_order, id') as $it) {
        $items[(int) $it['story_id']][] = [
            'type' => $it['media_type'] === 'video' ? 'video' : 'image',
            'src' => media_url($it['media']),
            'caption' => (string) $it['caption'],
            'link' => safe_link((string) $it['link']),
            'linkText' => (string) ($it['link_text'] ?: 'مشاهده'),
            'duration' => max(2, min(30, (int) $it['duration'])),
        ];
    }
    $out = [];
    foreach ($list as $s) {
        if (empty($items[(int) $s['id']])) continue;                       // a story without slides is not shown
        $first = $items[(int) $s['id']][0];
        $cover = $s['cover'] ? media_url($s['cover']) : ($first['type'] === 'image' ? $first['src'] : media_url((string) setting('logo')));
        $out[] = [
            'id' => (int) $s['id'], 'title' => $s['title'], 'cover' => $cover,
            'v' => strtotime($s['updated_at']),                              // changes when edited → shows as unseen again
            'ago' => jago($s['updated_at']), 'items' => $items[(int) $s['id']],
        ];
    }
    return $out;
}


/* ---------------- آنلاین‌ها ---------------- */
const NX_ONLINE_WINDOW = 300;   // active in the last 5 minutes = online

/** ثبت حضور بازدیدکننده (در هر صفحه و با ضربان هر ۶۰ ثانیه) */
function track_online(): void
{
    if (preg_match('~bot|crawl|spider|slurp|facebookexternalhit|preview|curl|wget|python~i', $_SERVER['HTTP_USER_AGENT'] ?? '')) return;
    if (session_status() !== PHP_SESSION_ACTIVE || session_id() === '') return;
    try {
        q('INSERT INTO online (sid, last_seen, is_member) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE last_seen = VALUES(last_seen), is_member = VALUES(is_member)',
            [sha1(session_id() . (config('secret') ?? '')), time(), current_member() ? 1 : 0]);
        if (random_int(1, 50) === 1) q('DELETE FROM online WHERE last_seen < ?', [time() - 3600]);
    } catch (Throwable $e) {
    }
}

/** تعداد آنلاین‌ها: [کل, اعضای واردشده] */
function online_count(): array
{
    try {
        $r = row('SELECT COUNT(*) total, COALESCE(SUM(is_member), 0) members FROM online WHERE last_seen >= ?', [time() - NX_ONLINE_WINDOW]);
        return [(int) $r['total'], (int) $r['members']];
    } catch (Throwable $e) {
        return [0, 0];
    }
}
