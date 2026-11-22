<?php

declare(strict_types=1);

namespace Osmium\Services\SeoAudit\Models;

/**
 * Reads the page addresses out of a sitemap.xml (or a sitemap index's first level). Pure: no network.
 */
class SitemapReader
{
    /**
     * @return string[] the paths (with query) of every page <loc> on the site's own host, in file order; none for a
     *         sitemap index, whose child sitemaps come from children()
     */
    public static function paths(string $xml, string $host): array
    {
        $previous = \libxml_use_internal_errors(true);
        $document = \simplexml_load_string($xml);
        \libxml_clear_errors();
        \libxml_use_internal_errors($previous);
        if ($document === false) return [];
        if ($document->getName() === 'sitemapindex') return []; // An index lists sitemaps, not pages: see children()

        $paths = [];
        foreach ($document->xpath('//*[local-name()="loc"]') ?: [] as $loc) {
            $parts = \parse_url(\trim((string) $loc));
            $sameHost = isset($parts['host']) && \strtolower($parts['host']) === \strtolower($host);
            if ($sameHost) $paths[] = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
        }

        return \array_values(\array_unique($paths));
    }

    /**
     * @return string[] the paths (with query) of the child sitemaps a sitemap index lists on the site's own host;
     *         none when the document is not an index
     */
    public static function children(string $xml, string $host): array
    {
        $previous = \libxml_use_internal_errors(true);
        $document = \simplexml_load_string($xml);
        \libxml_clear_errors();
        \libxml_use_internal_errors($previous);
        if ($document === false || $document->getName() !== 'sitemapindex') return [];

        $children = [];
        foreach ($document->xpath('//*[local-name()="sitemap"]/*[local-name()="loc"]') ?: [] as $loc) {
            $parts = \parse_url(\trim((string) $loc));
            $sameHost = isset($parts['host']) && \strtolower($parts['host']) === \strtolower($host);
            if ($sameHost) $children[] = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
        }

        return \array_values(\array_unique($children));
    }
}
