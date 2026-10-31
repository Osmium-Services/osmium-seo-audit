<?php

declare(strict_types=1);

namespace Osmium\Services\SeoAudit\Models;

/**
 * Reads one fetched HTML page into the facts the checks need: title, description, headings, word count, images
 * without alt text, canonical, robots, social tags, structured data and the internal links. Pure: no network, no DB.
 */
class PageParser
{
    private const NOT_PAGE_LINKS = ['mailto:', 'tel:', 'javascript:', 'sms:', 'data:', 'whatsapp:'];

    /**
     * @param string $html the page body
     * @param string $pagePath the page's own path, for resolving relative links
     * @param string $host the site's host, so links to it count as internal
     * @param string $robotsHeader the X-Robots-Tag response header, if any
     * @return array<string, mixed>
     */
    public static function parse(string $html, string $pagePath, string $host, string $robotsHeader = ''): array
    {
        $dom = self::load($html);
        $xpath = new \DOMXPath($dom);

        $headingLevels = self::headingLevels($xpath);
        $jsonLdInvalid = self::invalidJsonLd($xpath);
        $links = self::internalLinks($xpath, $pagePath, $host);
        $text = self::visibleText($xpath);
        $metaRobots = self::metaContent($xpath, 'name', 'robots');
        $robots = \strtolower(\trim($metaRobots . ',' . $robotsHeader, ', '));

        return [
            'title' => self::firstText($xpath, '//title'),
            'description' => self::metaContent($xpath, 'name', 'description'),
            'canonical' => self::attribute($xpath, '//link[@rel="canonical"]', 'href'),
            'robots' => $robots,
            'h1_count' => \count(\array_keys($headingLevels, 1, true)),
            'heading_levels' => \implode(',', $headingLevels),
            'word_count' => self::wordCount($text),
            'images_missing_alt' => self::imagesMissingAlt($xpath),
            'og_title' => self::metaContent($xpath, 'property', 'og:title') !== '',
            'og_image' => self::metaContent($xpath, 'property', 'og:image') !== '',
            'has_viewport' => self::metaContent($xpath, 'name', 'viewport') !== '',
            'jsonld_invalid' => $jsonLdInvalid,
            'body_hash' => $text === '' ? null : \md5(\preg_replace('/\s+/', ' ', \strtolower($text)) ?? $text),
            'links' => $links,
        ];
    }

    /** Whether a robots value (meta and header joined) tells search engines not to index */
    public static function isNoindex(string $robots): bool
    {
        return \str_contains(\strtolower($robots), 'noindex') || \str_contains(\strtolower($robots), 'none');
    }

    private static function load(string $html): \DOMDocument
    {
        $dom = new \DOMDocument();
        $previous = \libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_COMPACT);
        \libxml_clear_errors();
        \libxml_use_internal_errors($previous);

        return $dom;
    }

    private static function firstText(\DOMXPath $xpath, string $query): string
    {
        $node = $xpath->query($query)->item(0);

        return $node === null ? '' : \trim((string) \preg_replace('/\s+/', ' ', $node->textContent));
    }

    private static function attribute(\DOMXPath $xpath, string $query, string $name): string
    {
        $node = $xpath->query($query)->item(0);
        $isElement = $node instanceof \DOMElement;

        return $isElement ? \trim($node->getAttribute($name)) : '';
    }

    private static function metaContent(\DOMXPath $xpath, string $attribute, string $value): string
    {
        $query = '//meta[translate(@' . $attribute . ', "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="' . $value . '"]';

        return self::attribute($xpath, $query, 'content');
    }

    /** @return int[] the heading levels in document order, e.g. [1, 2, 2, 3] */
    private static function headingLevels(\DOMXPath $xpath): array
    {
        $levels = [];
        $headings = $xpath->query('//h1|//h2|//h3|//h4|//h5|//h6');
        foreach ($headings as $heading) {
            $levels[] = (int) \substr($heading->nodeName, 1);
        }

        return $levels;
    }

    /** The text a visitor reads: the body's text nodes, space-separated, without scripts, styles and templates */
    private static function visibleText(\DOMXPath $xpath): string
    {
        $query = '//body//text()[not(ancestor::script or ancestor::style or ancestor::noscript or ancestor::template)]';
        $parts = [];
        foreach ($xpath->query($query) as $node) {
            $parts[] = $node->nodeValue;
        }

        return \trim((string) \preg_replace('/\s+/u', ' ', \implode(' ', $parts)));
    }

    private static function wordCount(string $text): int
    {
        if ($text === '') return 0;

        return \count(\preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    private static function imagesMissingAlt(\DOMXPath $xpath): int
    {
        $missing = 0;
        foreach ($xpath->query('//img') as $image) {
            $hasAlt = $image->hasAttribute('alt'); // alt="" is valid: it marks a decorative image
            if (!$hasAlt) $missing++;
        }

        return $missing;
    }

    private static function invalidJsonLd(\DOMXPath $xpath): int
    {
        $invalid = 0;
        foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
            \json_decode($script->textContent);
            $isBroken = \json_last_error() !== JSON_ERROR_NONE;
            if ($isBroken) $invalid++;
        }

        return $invalid;
    }

    /** @return string[] distinct internal link paths (no query or fragment), resolved against the page */
    private static function internalLinks(\DOMXPath $xpath, string $pagePath, string $host): array
    {
        $paths = [];
        foreach ($xpath->query('//a[@href]') as $anchor) {
            $path = self::internalPath(\trim($anchor->getAttribute('href')), $pagePath, $host);
            if ($path !== null) $paths[$path] = true;
        }

        return \array_keys($paths);
    }

    /** The path a link points to when it stays on this site, or null for anything else */
    public static function internalPath(string $href, string $pagePath, string $host): ?string
    {
        $isEmpty = $href === '' || $href[0] === '#';
        if ($isEmpty) return null;

        $lower = \strtolower($href);
        foreach (self::NOT_PAGE_LINKS as $prefix) {
            if (\str_starts_with($lower, $prefix)) return null;
        }

        $parts = \parse_url(\str_starts_with($href, '//') ? 'https:' . $href : $href);
        if ($parts === false) return null;

        $linkHost = isset($parts['host']) ? \strtolower($parts['host']) : null;
        $isExternal = $linkHost !== null && $linkHost !== \strtolower($host) && $linkHost !== 'www.' . \strtolower($host) && 'www.' . $linkHost !== \strtolower($host);
        if ($isExternal) return null;

        $path = $parts['path'] ?? '';
        $isRelative = $path === '' || $path[0] !== '/';
        if ($isRelative) $path = self::resolveRelative($path, $pagePath);

        $isProxyPath = \str_starts_with($path, '/cdn-cgi/'); // Cloudflare's own paths (email hiding) exist only through its proxy
        if ($isProxyPath) return null;

        $isFile = (bool) \preg_match('/\.(jpe?g|png|gif|webp|svg|ico|pdf|zip|css|js|xml|txt|mp4|mp3|docx?|xlsx?)$/i', $path);
        if ($isFile) return null;

        return $path === '' ? '/' : $path;
    }

    private static function resolveRelative(string $path, string $pagePath): string
    {
        $isSamePage = $path === '';
        if ($isSamePage) return $pagePath;

        $directory = \str_ends_with($pagePath, '/') ? $pagePath : \dirname($pagePath) . '/';
        $segments = [];
        foreach (\explode('/', $directory . $path) as $segment) {
            if ($segment === '..') \array_pop($segments);
            if ($segment !== '' && $segment !== '.' && $segment !== '..') $segments[] = $segment;
        }

        $trailing = \str_ends_with($path, '/') ? '/' : '';

        return '/' . \implode('/', $segments) . $trailing;
    }
}
