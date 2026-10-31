<?php

declare(strict_types=1);

namespace Osmium\Services\SeoAudit\Models;

/**
 * The SEO checks. Takes every page of a finished crawl and returns findings, each with a check key, a severity, the
 * page and a short detail. Pure: no network, no DB, so each rule is testable on plain arrays.
 */
class Checks
{
    /** Every check: label, severity, group and a plain-English fix. The report is drawn from this. */
    public const CATALOGUE = [
        'page_error' => ['label' => 'Page returns an error', 'severity' => 'critical', 'group' => 'Indexing', 'fix' => 'A page in your sitemap does not load. Fix the page, or remove or redirect it (Pages, Redirects).'],
        'noindex_in_sitemap' => ['label' => 'Noindex page listed for indexing', 'severity' => 'critical', 'group' => 'Indexing', 'fix' => 'The page tells search engines not to index it, but the site map lists it. Change the page\'s robots setting or remove it (Pages).'],
        'blocked_by_robots' => ['label' => 'Blocked by robots.txt', 'severity' => 'critical', 'group' => 'Indexing', 'fix' => 'robots.txt stops search engines reading this page, so it cannot rank. Remove the matching Disallow rule.'],
        'redirect_in_sitemap' => ['label' => 'Redirected page in sitemap', 'severity' => 'high', 'group' => 'Indexing', 'fix' => 'This page redirects elsewhere. List the final address instead (Pages, Redirects).'],
        'canonical_mismatch' => ['label' => 'Canonical points elsewhere', 'severity' => 'high', 'group' => 'Indexing', 'fix' => 'The page names a different page as the original, so it will not rank itself. Correct the canonical tag.'],
        'canonical_missing' => ['label' => 'No canonical tag', 'severity' => 'medium', 'group' => 'Indexing', 'fix' => 'Add a canonical tag so search engines know the preferred address of this page (theme head).'],
        'redirect_chain' => ['label' => 'Redirect chain', 'severity' => 'medium', 'group' => 'Indexing', 'fix' => 'This redirect leads to another redirect. Point it straight at the final page (Redirects).'],
        'title_missing' => ['label' => 'Missing title', 'severity' => 'high', 'group' => 'Titles and descriptions', 'fix' => 'Give the page a title (Pages > edit). It is the main thing shown in search results.'],
        'title_duplicate' => ['label' => 'Duplicate title', 'severity' => 'medium', 'group' => 'Titles and descriptions', 'fix' => 'Several pages share this title. Give each page its own (Pages > edit).'],
        'title_long' => ['label' => 'Title too long', 'severity' => 'medium', 'group' => 'Titles and descriptions', 'fix' => 'Search results cut long titles off. Shorten it (Pages > edit, or General > Page Titles).'],
        'title_short' => ['label' => 'Title too short', 'severity' => 'low', 'group' => 'Titles and descriptions', 'fix' => 'A short title wastes space in search results. Describe the page more fully (Pages > edit).'],
        'description_missing' => ['label' => 'Missing meta description', 'severity' => 'medium', 'group' => 'Titles and descriptions', 'fix' => 'Write a description (Pages > edit). Search engines show it under the title.'],
        'description_duplicate' => ['label' => 'Duplicate meta description', 'severity' => 'medium', 'group' => 'Titles and descriptions', 'fix' => 'Several pages share this description. Write a different one for each (Pages > edit).'],
        'description_long' => ['label' => 'Description too long', 'severity' => 'low', 'group' => 'Titles and descriptions', 'fix' => 'Search results cut long descriptions off. Shorten it (Pages > edit).'],
        'description_short' => ['label' => 'Description too short', 'severity' => 'low', 'group' => 'Titles and descriptions', 'fix' => 'Use the space to sell the page (Pages > edit).'],
        'h1_missing' => ['label' => 'Missing main heading (h1)', 'severity' => 'high', 'group' => 'Headings', 'fix' => 'Each page needs one h1 saying what it is about (page content or theme).'],
        'h1_multiple' => ['label' => 'More than one h1', 'severity' => 'medium', 'group' => 'Headings', 'fix' => 'Keep one h1 per page and make the others h2 or lower.'],
        'heading_skip' => ['label' => 'Skipped heading level', 'severity' => 'low', 'group' => 'Headings', 'fix' => 'Headings jump a level (h2 straight to h4). Use them in order.'],
        'thin_content' => ['label' => 'Thin content', 'severity' => 'medium', 'group' => 'Content', 'fix' => 'Very little text. Add useful content, or mark the page noindex if it is not meant to rank.'],
        'duplicate_content' => ['label' => 'Duplicate content', 'severity' => 'high', 'group' => 'Content', 'fix' => 'These pages say the same thing. Merge them, or make each one distinct.'],
        'image_alt_missing' => ['label' => 'Images without alt text', 'severity' => 'medium', 'group' => 'Content', 'fix' => 'Add alt text describing each image. It helps image search and accessibility.'],
        'broken_link' => ['label' => 'Broken internal link', 'severity' => 'high', 'group' => 'Links', 'fix' => 'This page links to a page that does not load. Fix or remove the link.'],
        'link_to_redirect' => ['label' => 'Link to a redirected page', 'severity' => 'medium', 'group' => 'Links', 'fix' => 'Link straight to the final address instead of through the redirect.'],
        'orphan_page' => ['label' => 'Orphan page', 'severity' => 'medium', 'group' => 'Links', 'fix' => 'No other page links here, so crawlers and visitors struggle to find it. Link to it from a related page or the menu.'],
        'og_missing' => ['label' => 'Missing social sharing tags', 'severity' => 'low', 'group' => 'Social and schema', 'fix' => 'Add og:title and og:image so shared links show a title and picture (theme head).'],
        'jsonld_invalid' => ['label' => 'Broken structured data', 'severity' => 'medium', 'group' => 'Social and schema', 'fix' => 'The JSON-LD block does not parse, so search engines ignore it. Fix the markup.'],
        'no_https' => ['label' => 'Site not on HTTPS', 'severity' => 'critical', 'group' => 'Technical', 'fix' => 'The live site address does not use https. Browsers warn visitors and search engines prefer secure sites.'],
        'viewport_missing' => ['label' => 'Missing viewport tag', 'severity' => 'medium', 'group' => 'Technical', 'fix' => 'Without a viewport tag the page is not mobile friendly (theme head).'],
        'slow_response' => ['label' => 'Slow response', 'severity' => 'medium', 'group' => 'Technical', 'fix' => 'The server took too long to answer. Check hosting, caching and heavy queries.'],
        'heavy_page' => ['label' => 'Heavy page', 'severity' => 'low', 'group' => 'Technical', 'fix' => 'The page HTML is very large. Trim inline scripts and repeated markup.'],
        'noindex_content' => ['label' => 'Noindex page with real content', 'severity' => 'medium', 'group' => 'Indexing', 'fix' => 'This page has substantial content but tells search engines not to index it. If that is not deliberate, change its robots setting (Pages > edit).'],
    ];

    public const SEVERITIES = ['critical', 'high', 'medium', 'low'];

    public const DEFAULT_THRESHOLDS = [
        'titleMin' => 15, 'titleMax' => 60, 'descriptionMin' => 70, 'descriptionMax' => 160,
        'thinWords' => 150, 'noindexWords' => 300, 'slowMs' => 1500, 'heavyBytes' => 1500000,
    ];

    /**
     * @param array<int, array<string, mixed>> $pages every crawled page (rows as stored, with 'links' decoded to a list)
     * @param array{thresholds?: array<string, int>, robotsDisallow?: string[], baseUrl?: string, isLive?: bool} $context
     * @return array<int, array{check_key: string, severity: string, page_url: ?string, detail: ?string}>
     */
    public static function run(array $pages, array $context = []): array
    {
        $thresholds = ($context['thresholds'] ?? []) + self::DEFAULT_THRESHOLDS;
        $byKey = [];
        foreach ($pages as $page) {
            $byKey[$page['url']] = $page;
        }

        $findings = [];
        foreach ($pages as $page) {
            $hasHtml = (int) ($page['status_code'] ?? 0) === 200;
            $isNoindex = $hasHtml && PageParser::isNoindex((string) ($page['robots'] ?? ''));
            $findings = \array_merge(
                $findings,
                self::indexingFindings($page, $context),
                $isNoindex ? self::noindexFindings($page, $thresholds) : [],
                ($hasHtml && !$isNoindex) ? self::contentFindings($page, $thresholds) : [],
                $hasHtml ? self::linkFindings($page, $byKey) : [],
                $hasHtml ? self::technicalFindings($page, $thresholds) : [],
            );
        }

        $findings = \array_merge(
            $findings,
            self::duplicates($pages, 'title', 'title_duplicate'),
            self::duplicates($pages, 'description', 'description_duplicate'),
            self::duplicates($pages, 'body_hash', 'duplicate_content'),
            self::orphans($pages),
            self::redirectChains($pages, $byKey),
            self::siteWide($context),
        );

        return \array_values($findings);
    }

    /** Comparison key for a path: lowercase, one leading slash, no trailing slash (the home page is '/') */
    public static function key(string $path): string
    {
        $trimmed = \trim(\strtolower($path), '/');

        return '/' . $trimmed;
    }

    /** Whether robots.txt's `User-agent: *` rules disallow this path */
    public static function isDisallowed(string $path, array $disallowPrefixes): bool
    {
        foreach ($disallowPrefixes as $prefix) {
            $matches = $prefix !== '' && \str_starts_with($path, $prefix);
            if ($matches) return true;
        }

        return false;
    }

    /** @return string[] the Disallow paths that apply to every crawler in a robots.txt */
    public static function parseRobotsDisallow(string $robotsTxt): array
    {
        $rules = [];
        $appliesToAll = false;
        foreach (\preg_split('/\R/', $robotsTxt) ?: [] as $line) {
            $clean = \trim((string) \preg_replace('/#.*$/', '', $line));
            if (\preg_match('/^user-agent:\s*(.*)$/i', $clean, $match)) $appliesToAll = \trim($match[1]) === '*';
            if ($appliesToAll && \preg_match('/^disallow:\s*(\S+)/i', $clean, $match)) $rules[] = $match[1];
        }

        return $rules;
    }

    private static function finding(string $key, ?string $url, ?string $detail = null): array
    {
        return ['check_key' => $key, 'severity' => self::CATALOGUE[$key]['severity'], 'page_url' => $url, 'detail' => $detail];
    }

    private static function indexingFindings(array $page, array $context): array
    {
        $url = $page['url'];
        $status = (int) ($page['status_code'] ?? 0);
        $inSitemap = !empty($page['in_sitemap']);
        $findings = [];

        $failed = $status === 0 || $status >= 400;
        $redirected = $status >= 300 && $status < 400;
        if ($failed && $inSitemap) $findings[] = self::finding('page_error', $url, $status === 0 ? ($page['fetch_error'] ?: 'No response') : "Status {$status}");
        if ($redirected && $inSitemap) $findings[] = self::finding('redirect_in_sitemap', $url, 'Redirects to ' . ($page['redirect_target'] ?? '?'));
        if ($status !== 200) return $findings;

        $isNoindex = PageParser::isNoindex((string) ($page['robots'] ?? ''));
        if ($isNoindex && $inSitemap) $findings[] = self::finding('noindex_in_sitemap', $url);

        $isBlocked = self::isDisallowed($url, $context['robotsDisallow'] ?? []);
        if ($isBlocked && $inSitemap) $findings[] = self::finding('blocked_by_robots', $url);

        $canonical = (string) ($page['canonical'] ?? '');
        if ($canonical === '' && !$isNoindex) $findings[] = self::finding('canonical_missing', $url);

        $canonicalPath = self::canonicalPath($canonical, (string) ($context['baseUrl'] ?? ''));
        $mismatch = $canonicalPath !== null && self::key($canonicalPath) !== self::key($url);
        if ($mismatch) $findings[] = self::finding('canonical_mismatch', $url, "Canonical is {$canonical}");

        return $findings;
    }

    /** The path of a canonical URL when it is on this site, null when it is another site or empty */
    private static function canonicalPath(string $canonical, string $baseUrl): ?string
    {
        if ($canonical === '') return null;

        $parts = \parse_url($canonical);
        $baseHost = \parse_url($baseUrl, PHP_URL_HOST);
        $sameSite = !isset($parts['host']) || $baseHost === null || \strtolower($parts['host']) === \strtolower((string) $baseHost);
        if (!$sameSite) return null;

        return $parts['path'] ?? '/';
    }

    /** A noindex page is not meant to rank, so the ranking checks are skipped: only one carrying real content is raised */
    private static function noindexFindings(array $page, array $thresholds): array
    {
        $words = (int) ($page['word_count'] ?? 0);
        if ($words < $thresholds['noindexWords']) return [];

        return [self::finding('noindex_content', $page['url'], "{$words} words, but marked noindex")];
    }

    private static function contentFindings(array $page, array $thresholds): array
    {
        $url = $page['url'];
        $findings = [];
        $title = (string) ($page['title'] ?? '');
        $description = (string) ($page['description'] ?? '');
        $titleLength = \mb_strlen($title);
        $descriptionLength = \mb_strlen($description);

        if ($title === '') $findings[] = self::finding('title_missing', $url);
        if ($title !== '' && $titleLength > $thresholds['titleMax']) $findings[] = self::finding('title_long', $url, "{$titleLength} characters");
        if ($title !== '' && $titleLength < $thresholds['titleMin']) $findings[] = self::finding('title_short', $url, "{$titleLength} characters");

        if ($description === '') $findings[] = self::finding('description_missing', $url);
        if ($description !== '' && $descriptionLength > $thresholds['descriptionMax']) $findings[] = self::finding('description_long', $url, "{$descriptionLength} characters");
        if ($description !== '' && $descriptionLength < $thresholds['descriptionMin']) $findings[] = self::finding('description_short', $url, "{$descriptionLength} characters");

        $h1Count = (int) ($page['h1_count'] ?? 0);
        if ($h1Count === 0) $findings[] = self::finding('h1_missing', $url);
        if ($h1Count > 1) $findings[] = self::finding('h1_multiple', $url, "{$h1Count} h1 headings");
        if (self::skipsLevel((string) ($page['heading_levels'] ?? ''))) $findings[] = self::finding('heading_skip', $url);

        $words = (int) ($page['word_count'] ?? 0);
        if ($words < $thresholds['thinWords']) $findings[] = self::finding('thin_content', $url, "{$words} words");

        $missingAlt = (int) ($page['images_missing_alt'] ?? 0);
        if ($missingAlt > 0) $findings[] = self::finding('image_alt_missing', $url, "{$missingAlt} image(s)");

        $needsSocial = empty($page['og_title']) || empty($page['og_image']);
        if ($needsSocial) $findings[] = self::finding('og_missing', $url);

        $badJsonLd = (int) ($page['jsonld_invalid'] ?? 0);
        if ($badJsonLd > 0) $findings[] = self::finding('jsonld_invalid', $url, "{$badJsonLd} block(s)");

        return $findings;
    }

    private static function skipsLevel(string $levels): bool
    {
        $previous = 0;
        foreach (\array_filter(\explode(',', $levels)) as $level) {
            $level = (int) $level;
            if ($previous > 0 && $level > $previous + 1) return true;
            $previous = $level;
        }

        return false;
    }

    private static function linkFindings(array $page, array $byKey): array
    {
        $findings = [];
        foreach ($page['links'] ?? [] as $target) {
            $targetPage = $byKey[$target] ?? null;
            if ($targetPage === null) continue;

            $status = (int) ($targetPage['status_code'] ?? 0);
            $isBroken = $targetPage['fetched'] && ($status === 0 || $status >= 400);
            $isRedirect = $status >= 300 && $status < 400;
            if ($isBroken) $findings[] = self::finding('broken_link', $page['url'], "Links to {$target} ({$status})");
            if ($isRedirect) $findings[] = self::finding('link_to_redirect', $page['url'], "Links to {$target}");
        }

        return $findings;
    }

    /** A redirect whose target is itself a redirect */
    private static function redirectChains(array $pages, array $byKey): array
    {
        $findings = [];
        foreach ($pages as $page) {
            $status = (int) ($page['status_code'] ?? 0);
            $isRedirect = $status >= 300 && $status < 400;
            if (!$isRedirect) continue;

            $targetPath = (string) \parse_url((string) ($page['redirect_target'] ?? ''), PHP_URL_PATH);
            $targetStatus = (int) ($byKey[$targetPath]['status_code'] ?? 0);
            $isChain = $targetStatus >= 300 && $targetStatus < 400;
            if ($isChain) $findings[] = self::finding('redirect_chain', $page['url'], "Then redirects again from {$targetPath}");
        }

        return $findings;
    }

    private static function technicalFindings(array $page, array $thresholds): array
    {
        $url = $page['url'];
        $findings = [];

        $hasViewport = !empty($page['has_viewport']);
        if (!$hasViewport) $findings[] = self::finding('viewport_missing', $url);

        $ms = (int) ($page['response_ms'] ?? 0);
        if ($ms > $thresholds['slowMs']) $findings[] = self::finding('slow_response', $url, "{$ms} ms");

        $bytes = (int) ($page['bytes'] ?? 0);
        if ($bytes > $thresholds['heavyBytes']) $findings[] = self::finding('heavy_page', $url, \number_format($bytes / 1000) . ' KB');

        return $findings;
    }

    /** One finding per page that shares a non-empty value with another page */
    private static function duplicates(array $pages, string $field, string $checkKey): array
    {
        $groups = [];
        foreach ($pages as $page) {
            $value = \trim((string) ($page[$field] ?? ''));
            $indexable = !PageParser::isNoindex((string) ($page['robots'] ?? ''));
            $countable = (int) ($page['status_code'] ?? 0) === 200 && $value !== '' && $indexable;
            if ($countable) $groups[$value][] = $page['url'];
        }

        $findings = [];
        foreach ($groups as $urls) {
            if (\count($urls) < 2) continue;

            foreach ($urls as $url) {
                $others = \count($urls) - 1;
                $findings[] = self::finding($checkKey, $url, "Shared with {$others} other page(s)");
            }
        }

        return $findings;
    }

    /** Pages in the sitemap that no other crawled page links to (the home page is exempt) */
    private static function orphans(array $pages): array
    {
        $linkedTo = [];
        foreach ($pages as $page) {
            foreach ($page['links'] ?? [] as $target) {
                if (self::key($target) !== self::key($page['url'])) $linkedTo[self::key($target)] = true;
            }
        }

        $findings = [];
        foreach ($pages as $page) {
            $isOrphan = !empty($page['in_sitemap']) && self::key($page['url']) !== '/' && !isset($linkedTo[self::key($page['url'])]);
            if ($isOrphan) $findings[] = self::finding('orphan_page', $page['url']);
        }

        return $findings;
    }

    private static function siteWide(array $context): array
    {
        $baseUrl = (string) ($context['baseUrl'] ?? '');
        $insecure = !empty($context['isLive']) && !\str_starts_with($baseUrl, 'https://');

        return $insecure ? [self::finding('no_https', null, $baseUrl)] : [];
    }
}
