<?php
/* Google News sitemap: only posts from the last 2 days (Google's rule), newest first */
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$posts = rows('SELECT slug, title, published_at FROM posts p WHERE ' . pub_where() . ' AND p.published_at >= ? ORDER BY published_at DESC LIMIT 1000', [date('Y-m-d H:i:s', time() - 2 * 86400)]);
$name = (string) setting('site_title');
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">
<?php foreach ($posts as $p): ?><url><loc><?= e(site_url('post/' . rawurlencode($p['slug']))) ?></loc><news:news><news:publication><news:name><?= e($name) ?></news:name><news:language>fa</news:language></news:publication><news:publication_date><?= date('c', strtotime((string) $p['published_at'])) ?></news:publication_date><news:title><?= e($p['title']) ?></news:title></news:news></url>
<?php endforeach; ?>
</urlset>
