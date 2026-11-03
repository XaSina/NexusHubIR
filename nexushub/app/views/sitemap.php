<?php
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$posts = rows('SELECT slug, updated_at FROM posts p WHERE ' . pub_where() . ' ORDER BY published_at DESC LIMIT 5000');
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<url><loc><?= e(site_url('')) ?></loc><changefreq>hourly</changefreq><priority>1.0</priority></url>
<url><loc><?= e(site_url('archive')) ?></loc><changefreq>hourly</changefreq></url>
<?php foreach (get_categories() as $c): ?><url><loc><?= e(site_url('category/' . rawurlencode($c['slug']))) ?></loc><changefreq>daily</changefreq></url>
<?php endforeach; ?>
<?php foreach (rows("SELECT slug, updated_at FROM pages WHERE status = 'published'") as $pg): ?><url><loc><?= e(site_url('page/' . rawurlencode($pg['slug']))) ?></loc><lastmod><?= date('c', strtotime($pg['updated_at'])) ?></lastmod></url>
<?php endforeach; ?>
<?php foreach ($posts as $p): ?><url><loc><?= e(site_url('post/' . rawurlencode($p['slug']))) ?></loc><lastmod><?= date('c', strtotime($p['updated_at'])) ?></lastmod></url>
<?php endforeach; ?>
</urlset>
