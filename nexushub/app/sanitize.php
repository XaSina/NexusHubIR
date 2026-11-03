<?php
/**
 * پاکسازی HTML محتوای خبر (فقط تگ‌ها و ویژگی‌های امن)
 */

function sanitize_html(string $html): string
{
    $html = trim($html);
    if ($html === '') return '';

    if (!class_exists('DOMDocument')) {
        $clean = strip_tags($html, '<p><br><b><strong><i><em><u><s><del><h2><h3><h4><blockquote><ul><ol><li><a><img><figure><figcaption><hr><pre><code><span><mark><table><thead><tbody><tr><th><td>');
        $clean = preg_replace('~\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)~i', '', $clean);
        return preg_replace('~(href|src)\s*=\s*(["\'])\s*(javascript|data|vbscript):[^"\']*\2~i', '$1="#"', $clean);
    }

    $allowed = [
        'p' => ['style'], 'br' => [], 'b' => [], 'strong' => [], 'i' => [], 'em' => [], 'u' => [], 's' => [], 'strike' => [], 'del' => [], 'ins' => [],
        'h2' => ['style', 'id'], 'h3' => ['style', 'id'], 'h4' => ['style', 'id'], 'h5' => ['style'],
        'blockquote' => ['style'], 'ul' => [], 'ol' => [], 'li' => [],
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height', 'style'],
        'figure' => ['class'], 'figcaption' => [], 'hr' => [], 'pre' => [], 'code' => [],
        'span' => ['style'], 'div' => ['style', 'class'], 'mark' => [], 'sub' => [], 'sup' => [], 'small' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
        'iframe' => ['src', 'width', 'height', 'allowfullscreen', 'frameborder', 'allow'],
        'video' => ['src', 'controls', 'poster', 'width', 'height'], 'source' => ['src', 'type'],
    ];
    $iframeHosts = ['www.aparat.com', 'aparat.com', 'www.youtube.com', 'youtube.com', 'www.youtube-nocookie.com', 'player.vimeo.com', 'open.spotify.com', 'w.soundcloud.com'];

    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="nx-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $root = $doc->getElementById('nx-root');
    if (!$root) return '';

    $walk = function (DOMNode $node) use (&$walk, $allowed, $iframeHosts, $doc) {
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);
            if ($child instanceof DOMComment) {
                $node->removeChild($child);
                continue;
            }
            if (!($child instanceof DOMElement)) continue;
            $tag = strtolower($child->tagName);

            if (in_array($tag, ['script', 'style', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta', 'base', 'svg', 'math'], true)) {
                $node->removeChild($child);
                continue;
            }
            if (!isset($allowed[$tag])) {
                // تگ ناشناخته: محتوایش را نگه دار
                $walk($child);
                while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                $node->removeChild($child);
                continue;
            }
            // ویژگی‌ها
            $attrs = [];
            foreach ($child->attributes as $a) $attrs[] = $a->name;
            foreach ($attrs as $name) {
                $lname = strtolower($name);
                $value = $child->getAttribute($name);
                if (!in_array($lname, $allowed[$tag], true)) {
                    $child->removeAttribute($name);
                    continue;
                }
                if (in_array($lname, ['href', 'src', 'poster'], true)) {
                    $v = trim(html_entity_decode($value, ENT_QUOTES, 'UTF-8'));
                    if (!preg_match('~^(https?://|/|#|mailto:|tel:|uploads/|assets/)~i', $v)) {
                        $child->removeAttribute($name);
                        continue;
                    }
                }
                if ($lname === 'style') {
                    $child->setAttribute('style', clean_style($value));
                    if ($child->getAttribute('style') === '') $child->removeAttribute('style');
                }
                if ($lname === 'class') {
                    $child->setAttribute('class', preg_replace('~[^\w\- ]~', '', $value));
                }
            }
            if ($tag === 'iframe') {
                $host = strtolower((string) parse_url($child->getAttribute('src'), PHP_URL_HOST));
                if (!in_array($host, $iframeHosts, true)) {
                    $node->removeChild($child);
                    continue;
                }
                $child->setAttribute('loading', 'lazy');
            }
            if ($tag === 'a') {
                $href = $child->getAttribute('href');
                if (preg_match('~^https?://~i', $href) && !str_contains($href, (string) ($_SERVER['HTTP_HOST'] ?? '###'))) {
                    $child->setAttribute('target', '_blank');
                    $child->setAttribute('rel', 'noopener nofollow');
                }
            }
            if ($tag === 'img') {
                $child->setAttribute('loading', 'lazy');
            }
            $walk($child);
        }
    };
    $walk($root);

    $out = '';
    foreach ($root->childNodes as $c) $out .= $doc->saveHTML($c);
    return trim($out);
}

function clean_style(string $style): string
{
    $out = [];
    foreach (explode(';', $style) as $decl) {
        if (!str_contains($decl, ':')) continue;
        [$p, $v] = array_map('trim', explode(':', $decl, 2));
        $p = strtolower($p);
        if (!in_array($p, ['text-align', 'color', 'background-color', 'font-weight', 'text-decoration', 'direction', 'width', 'max-width'], true)) continue;
        if (preg_match('~(url|expression|javascript|@import|\\\\)~i', $v)) continue;
        if (!preg_match('~^[#\w\s,.%()\-]+$~', $v)) continue;
        $out[] = $p . ':' . $v;
    }
    return implode(';', $out);
}
