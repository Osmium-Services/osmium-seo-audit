<?php

declare(strict_types=1);

namespace Osmium\Services\SeoAudit\Models;

/**
 * "What was checked": one row per check in the catalogue saying whether it passed, failed or could not run, and why.
 * A fail comes from the run's findings. A pass or a skip comes from the coverage the run recorded (Checks::coverage),
 * so a run saved before coverage was kept shows its non-failing checks as unknown instead of guessing. Pure: takes arrays.
 */
class CheckSummary
{
    public const PASS = 'pass';
    public const FAIL = 'fail';
    public const SKIPPED = 'skipped';
    public const UNKNOWN = 'unknown';

    /** Status => word, Boxicon and colour class, and the order a status list sorts in */
    private const STATUS = [
        self::FAIL => ['label' => 'Fail', 'icon' => 'bxs-x-circle', 'class' => 'text-danger', 'rank' => 0],
        self::SKIPPED => ['label' => 'Skipped', 'icon' => 'bxs-minus-circle', 'class' => 'text-muted', 'rank' => 1],
        self::UNKNOWN => ['label' => 'Unknown', 'icon' => 'bxs-help-circle', 'class' => 'text-muted', 'rank' => 2],
        self::PASS => ['label' => 'Pass', 'icon' => 'bxs-check-circle', 'class' => 'text-success', 'rank' => 3],
    ];

    /** What each page-based check looks at: the count it is tested over, the nouns, and the verb for the detail */
    private const SCOPES = [
        'sitemap' => ['count' => 'sitemapPages', 'one' => 'sitemap page', 'many' => 'sitemap pages', 'none' => 'no sitemap pages'],
        'pages' => ['count' => 'pages', 'one' => 'page', 'many' => 'pages', 'none' => 'no pages that loaded'],
        'indexable' => ['count' => 'indexable', 'one' => 'page', 'many' => 'pages', 'none' => 'no indexable pages'],
        'noindex' => ['count' => 'noindex', 'one' => 'noindex page', 'many' => 'noindex pages', 'none' => 'no noindex pages'],
        'redirects' => ['count' => 'redirects', 'one' => 'redirect', 'many' => 'redirects', 'none' => 'no redirects'],
        'images' => ['count' => 'images', 'one' => 'image', 'many' => 'images', 'none' => 'no images'],
    ];

    /** Page-based checks: key => [scope, verb, threshold note (coverage key and template) or null] */
    private const PAGE_CHECKS = [
        'page_error' => ['sitemap', 'tested', null], 'noindex_in_sitemap' => ['sitemap', 'tested', null],
        'redirect_in_sitemap' => ['sitemap', 'tested', null], 'orphan_page' => ['sitemap', 'tested', null],
        'canonical_mismatch' => ['pages', 'tested', null], 'broken_link' => ['pages', 'tested', null],
        'link_to_redirect' => ['pages', 'tested', null], 'viewport_missing' => ['pages', 'tested', null],
        'lang_missing' => ['pages', 'tested', null], 'mixed_content' => ['pages', 'tested', null],
        'slow_response' => ['pages', 'tested', ['slowMs', 'slower than %s ms']],
        'heavy_page' => ['pages', 'tested', ['heavyBytes', 'larger than %s KB']],
        'canonical_missing' => ['indexable', 'tested', null], 'title_missing' => ['indexable', 'tested', null],
        'title_duplicate' => ['indexable', 'compared', null], 'title_long' => ['indexable', 'tested', ['titleMax', 'longer than %s characters']],
        'title_short' => ['indexable', 'tested', ['titleMin', 'shorter than %s characters']],
        'description_missing' => ['indexable', 'tested', null], 'description_duplicate' => ['indexable', 'compared', null],
        'description_long' => ['indexable', 'tested', ['descriptionMax', 'longer than %s characters']],
        'description_short' => ['indexable', 'tested', ['descriptionMin', 'shorter than %s characters']],
        'h1_missing' => ['indexable', 'tested', null], 'h1_multiple' => ['indexable', 'tested', null],
        'heading_skip' => ['indexable', 'tested', null], 'thin_content' => ['indexable', 'tested', ['thinWords', 'under %s words']],
        'duplicate_content' => ['indexable', 'compared', null], 'image_alt_missing' => ['indexable', 'tested', null],
        'og_missing' => ['indexable', 'tested', null], 'jsonld_invalid' => ['indexable', 'tested', null],
        'noindex_content' => ['noindex', 'tested', ['noindexWords', 'with %s or more words']],
        'redirect_chain' => ['redirects', 'tested', null], 'broken_image' => ['images', 'tested', null],
    ];

    /** @return array<string, mixed>|null null when the run recorded no coverage */
    public static function decode(mixed $stored): ?array
    {
        $decoded = \is_string($stored) ? \json_decode($stored, associative: true) : null;
        $isValid = \is_array($decoded) && \array_key_exists('live', $decoded);
        if (!$isValid) return null;

        $counts = [];
        foreach (['sitemapPages', 'pages', 'indexable', 'noindex', 'redirects', 'images'] as $name) {
            $counts[$name] = (int) ($decoded[$name] ?? 0);
        }
        $thresholds = [];
        foreach ((array) ($decoded['thresholds'] ?? []) as $name => $value) {
            $thresholds[(string) $name] = (int) $value;
        }

        return $counts + [
            'live' => (bool) $decoded['live'], 'https' => (bool) ($decoded['https'] ?? false), 'robots' => (bool) ($decoded['robots'] ?? false),
            'probed' => (bool) ($decoded['probed'] ?? false), 'sitemapUrls' => (int) ($decoded['sitemapUrls'] ?? 0),
            'favicon' => self::iconState($decoded['favicon'] ?? null), 'apple' => self::iconState($decoded['apple'] ?? null),
            'thresholds' => $thresholds,
        ];
    }

    /**
     * @param array<int, array{check_key: string, page_url: ?string, detail: ?string}> $findings
     * @param array<string, mixed>|null $coverage from decode()
     * @return array<int, array<string, mixed>> one row per catalogue check, in catalogue order
     */
    public static function rows(array $findings, ?array $coverage): array
    {
        $byCheck = [];
        foreach ($findings as $finding) {
            $byCheck[$finding['check_key']][] = $finding;
        }

        $rows = [];
        $position = 0;
        foreach (Checks::CATALOGUE as $checkKey => $check) {
            [$status, $detail] = self::outcome($checkKey, $byCheck[$checkKey] ?? [], $coverage);
            $rows[] = [
                'id' => ++$position, 'check_key' => $checkKey, 'label' => $check['label'], 'group' => $check['group'],
                'status' => $status, 'detail' => $detail, 'statusLabel' => self::STATUS[$status]['label'],
                'statusIcon' => self::STATUS[$status]['icon'], 'statusClass' => self::STATUS[$status]['class'],
                'statusRank' => self::STATUS[$status]['rank'],
            ];
        }

        return $rows;
    }

    private static function iconState(mixed $state): ?string
    {
        return \in_array($state, ['declared', 'conventional', 'external'], true) ? $state : null;
    }

    /** @return array{0: string, 1: string} status and detail */
    private static function outcome(string $checkKey, array $hits, ?array $coverage): array
    {
        if ($hits !== []) return [self::FAIL, self::failDetail($hits)];
        if ($coverage === null) return [self::UNKNOWN, 'Coverage was not recorded for this run'];

        $isPageCheck = isset(self::PAGE_CHECKS[$checkKey]);

        return $isPageCheck ? self::pageOutcome($checkKey, $coverage) : self::siteOutcome($checkKey, $coverage);
    }

    /** @param array<int, array{page_url: ?string}> $hits */
    private static function failDetail(array $hits): string
    {
        $urls = \array_filter(\array_unique(\array_column($hits, 'page_url')), fn($url): bool => $url !== null);
        $pages = \count($urls);
        if ($pages === 0) return 'Whole site';

        return $pages === 1 ? '1 page affected' : "{$pages} pages affected";
    }

    /** @return array{0: string, 1: string} */
    private static function pageOutcome(string $checkKey, array $coverage): array
    {
        [$scopeName, $verb, $threshold] = self::PAGE_CHECKS[$checkKey];
        $needsHttps = $checkKey === 'mixed_content' && !$coverage['https'];
        if ($needsHttps) return [self::SKIPPED, 'Skipped: the site address is not https'];

        $scope = self::SCOPES[$scopeName];
        $count = (int) $coverage[$scope['count']];
        if ($count === 0) return [self::SKIPPED, 'Skipped: ' . $scope['none'] . ' in this run'];

        $detail = $count . ' ' . ($count === 1 ? $scope['one'] : $scope['many']) . ' ' . $verb;

        return [self::PASS, $detail . self::thresholdNote($threshold, $coverage)];
    }

    /** @param array{0: string, 1: string}|null $threshold coverage key and a template holding one %s */
    private static function thresholdNote(?array $threshold, array $coverage): string
    {
        $hasNote = $threshold !== null && isset($coverage['thresholds'][$threshold[0]]);
        if (!$hasNote) return '';

        $value = (int) $coverage['thresholds'][$threshold[0]];
        $shown = $threshold[0] === 'heavyBytes' ? \number_format($value / 1000) : (string) $value;

        return ', flagged when ' . \sprintf($threshold[1], $shown);
    }

    /** @return array{0: string, 1: string} the checks that depend on the live site or on the whole-site probes */
    private static function siteOutcome(string $checkKey, array $coverage): array
    {
        $liveOnly = \in_array($checkKey, ['blocked_by_robots', 'robots_missing', 'sitemap_missing', 'no_https'], true);
        if ($liveOnly && !$coverage['live']) return [self::SKIPPED, 'Skipped: runs on live only'];

        $needsProbes = \in_array($checkKey, ['robots_missing', 'sitemap_missing', 'favicon_missing', 'apple_icon_missing', 'soft_404'], true);
        if ($needsProbes && !$coverage['probed']) return [self::SKIPPED, 'Skipped: the whole-site probes did not run'];

        return match ($checkKey) {
            'blocked_by_robots' => self::robotsOutcome($coverage),
            'robots_missing' => [self::PASS, 'robots.txt checked'],
            'sitemap_missing' => [self::PASS, 'sitemap.xml read, ' . $coverage['sitemapUrls'] . ' addresses'],
            'no_https' => [self::PASS, 'Site address checked'],
            'soft_404' => [self::PASS, 'An address that cannot exist was requested'],
            'favicon_missing' => self::iconOutcome($coverage['favicon'], '/favicon.ico'),
            default => self::iconOutcome($coverage['apple'], '/apple-touch-icon.png'),
        };
    }

    /** @return array{0: string, 1: string} */
    private static function robotsOutcome(array $coverage): array
    {
        if (!$coverage['robots']) return [self::SKIPPED, 'Skipped: robots.txt could not be read'];

        $count = $coverage['sitemapPages'];
        if ($count === 0) return [self::SKIPPED, 'Skipped: no sitemap pages in this run'];

        return [self::PASS, 'robots.txt read, ' . $count . ' sitemap ' . ($count === 1 ? 'page' : 'pages') . ' tested'];
    }

    /** @return array{0: string, 1: string} */
    private static function iconOutcome(?string $state, string $conventional): array
    {
        return match ($state) {
            'declared' => [self::PASS, 'Declared home page icon checked'],
            'conventional' => [self::PASS, "None declared, {$conventional} checked"],
            'external' => [self::SKIPPED, 'Skipped: the declared icon is hosted elsewhere'],
            default => [self::SKIPPED, 'Skipped: the icon was not probed'],
        };
    }
}
