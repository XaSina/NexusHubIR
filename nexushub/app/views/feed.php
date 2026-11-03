<?php /** @var array $posts */
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"; ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
<title><?= e(setting('site_title')) ?></title>
<link><?= e(site_url('')) ?></link>
<description><?= e(setting('site_description')) ?></description>
<language>fa-IR</language>
<atom:link href="<?= e(site_url('feed')) ?>" rel="self" type="application/rss+xml"/>
<?php foreach ($posts as $p): ?>
<item>
<title><?= e($p['title']) ?></title>
<link><?= e(site_url('post/' . rawurlencode($p['slug']))) ?></link>
<guid isPermaLink="true"><?= e(site_url('post/' . rawurlencode($p['slug']))) ?></guid>
<pubDate><?= date(DATE_RSS, strtotime($p['published_at'])) ?></pubDate>
<?php if ($p['cat_name']): ?><category><?= e($p['cat_name']) ?></category><?php endif; ?>
<description><?= e($p['excerpt'] ?: str_excerpt((string) $p['content'], 300)) ?></description>
<?php if ($p['cover'] && !str_ends_with($p['cover'], '.svg')): ?><enclosure url="<?= e(site_url($p['cover'])) ?>" type="image/jpeg" length="0"/><?php endif; ?>
</item>
<?php endforeach; ?>
</channel>
</rss>
