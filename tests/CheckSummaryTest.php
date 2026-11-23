<?php

declare(strict_types=1);

namespace Tests\Services\SeoAudit;

use Osmium\Services\SeoAudit\Models\CheckSummary;
use Osmium\Services\SeoAudit\Models\Checks;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/PageParser.php';
require_once __DIR__ . '/../models/Checks.php';
require_once __DIR__ . '/../models/CheckSummary.php';

/** The "What was checked" rows: pass, fail or skipped for every check, from findings plus recorded coverage */
class CheckSummaryTest extends TestCase
{
    private const LIVE = [
        'live' => true, 'https' => true, 'robots' => true, 'probed' => true, 'sitemapPages' => 52, 'pages' => 50, 'indexable' => 48,
        'noindex' => 2, 'redirects' => 3, 'images' => 120, 'sitemapUrls' => 52, 'favicon' => 'declared', 'apple' => 'conventional',
        'thresholds' => ['titleMin' => 15, 'titleMax' => 60, 'descriptionMin' => 70, 'descriptionMax' => 160, 'thinWords' => 150, 'noindexWords' => 300, 'nearDuplicateBits' => 10, 'slowMs' => 1500, 'heavyBytes' => 1500000],
    ];

    private function finding(string $key, ?string $url, ?string $detail = null): array
    {
        return ['check_key' => $key, 'severity' => Checks::CATALOGUE[$key]['severity'], 'page_url' => $url, 'detail' => $detail];
    }

    /** @return array<string, array<string, mixed>> rows keyed by check */
    private function byKey(array $findings, ?array $coverage): array
    {
        return \array_column(CheckSummary::rows($findings, $coverage), null, 'check_key');
    }

    public function test_there_is_one_row_per_catalogue_check_in_catalogue_order(): void
    {
        $rows = CheckSummary::rows([], self::LIVE);

        $this->assertSame(\array_keys(Checks::CATALOGUE), \array_column($rows, 'check_key'));
        $this->assertSame(\range(1, \count(Checks::CATALOGUE)), \array_column($rows, 'id'));
    }

    public function test_a_fully_covered_live_run_passes_every_check_with_some_coverage_text(): void
    {
        $rows = $this->byKey([], self::LIVE);

        foreach ($rows as $key => $row) {
            $this->assertSame('pass', $row['status'], "{$key}: {$row['detail']}");
            $this->assertNotSame('', $row['detail'], $key);
        }
    }

    public function test_pass_details_state_the_coverage_each_check_really_had(): void
    {
        $rows = $this->byKey([], self::LIVE);

        $this->assertSame('52 sitemap pages tested', $rows['page_error']['detail']);
        $this->assertSame('50 pages tested', $rows['viewport_missing']['detail']);
        $this->assertSame('48 pages tested', $rows['h1_missing']['detail']);
        $this->assertSame('48 pages compared', $rows['title_duplicate']['detail']);
        $this->assertSame('48 pages tested, flagged when longer than 60 characters', $rows['title_long']['detail']);
        $this->assertSame('48 pages tested, flagged when shorter than 15 characters', $rows['title_short']['detail']);
        $this->assertSame('48 pages tested, flagged when longer than 160 characters', $rows['description_long']['detail']);
        $this->assertSame('48 pages tested, flagged when under 150 words', $rows['thin_content']['detail']);
        $this->assertSame('2 noindex pages tested, flagged when with 300 or more words', $rows['noindex_content']['detail']);
        $this->assertSame('50 pages tested, flagged when slower than 1500 ms', $rows['slow_response']['detail']);
        $this->assertSame('50 pages tested, flagged when larger than 1,500 KB', $rows['heavy_page']['detail']);
        $this->assertSame('3 redirects tested', $rows['redirect_chain']['detail']);
        $this->assertSame('120 images tested', $rows['broken_image']['detail']);
        $this->assertSame('robots.txt read, 52 sitemap pages tested', $rows['blocked_by_robots']['detail']);
        $this->assertSame('robots.txt checked', $rows['robots_missing']['detail']);
        $this->assertSame('sitemap.xml read, 52 addresses', $rows['sitemap_missing']['detail']);
        $this->assertSame('Site address checked', $rows['no_https']['detail']);
        $this->assertSame('An address that cannot exist was requested', $rows['soft_404']['detail']);
        $this->assertSame('Declared home page icon checked', $rows['favicon_missing']['detail']);
        $this->assertSame('None declared, /apple-touch-icon.png checked', $rows['apple_icon_missing']['detail']);
    }

    public function test_the_live_only_checks_are_skipped_off_live_and_nothing_else_is(): void
    {
        $rows = $this->byKey([], ['live' => false, 'robots' => false, 'https' => false] + self::LIVE);

        $skipped = [];
        foreach ($rows as $key => $row) {
            if ($row['status'] === 'skipped') $skipped[$key] = $row['detail'];
        }

        $this->assertSame(
            ['blocked_by_robots', 'no_https', 'robots_missing', 'sitemap_missing', 'mixed_content'],
            \array_keys($skipped),
        );
        $this->assertSame('Skipped: runs on live only', $skipped['blocked_by_robots']);
        $this->assertSame('Skipped: runs on live only', $skipped['robots_missing']);
        $this->assertSame('Skipped: runs on live only', $skipped['sitemap_missing']);
        $this->assertSame('Skipped: runs on live only', $skipped['no_https']);
        $this->assertSame('Skipped: the site address is not https', $skipped['mixed_content']);
    }

    public function test_the_robots_check_is_skipped_when_robots_txt_could_not_be_read(): void
    {
        $row = $this->byKey([], ['robots' => false] + self::LIVE)['blocked_by_robots'];

        $this->assertSame('skipped', $row['status']);
        $this->assertSame('Skipped: robots.txt could not be read', $row['detail']);
    }

    public function test_mixed_content_runs_on_an_https_site_off_live_too(): void
    {
        $row = $this->byKey([], ['live' => false, 'robots' => false] + self::LIVE)['mixed_content'];

        $this->assertSame('pass', $row['status']);
    }

    public function test_icon_checks_say_how_the_icon_was_probed(): void
    {
        $rows = $this->byKey([], ['favicon' => 'conventional', 'apple' => 'external'] + self::LIVE);
        $this->assertSame('None declared, /favicon.ico checked', $rows['favicon_missing']['detail']);
        $this->assertSame('skipped', $rows['apple_icon_missing']['status']);
        $this->assertSame('Skipped: the declared icon is hosted elsewhere', $rows['apple_icon_missing']['detail']);

        $unprobed = $this->byKey([], ['favicon' => null] + self::LIVE);
        $this->assertSame('Skipped: the icon was not probed', $unprobed['favicon_missing']['detail']);
    }

    public function test_the_whole_site_checks_are_skipped_when_the_probes_did_not_run(): void
    {
        $rows = $this->byKey([], ['probed' => false] + self::LIVE);

        foreach (['robots_missing', 'sitemap_missing', 'favicon_missing', 'apple_icon_missing', 'soft_404'] as $key) {
            $this->assertSame('Skipped: the whole-site probes did not run', $rows[$key]['detail'], $key);
        }
        $this->assertSame('pass', $rows['page_error']['status']);
    }

    public function test_a_check_with_nothing_to_test_is_skipped_not_passed(): void
    {
        $rows = $this->byKey([], ['sitemapPages' => 0, 'pages' => 0, 'indexable' => 0, 'noindex' => 0, 'redirects' => 0, 'images' => 0] + self::LIVE);

        $this->assertSame('Skipped: no sitemap pages in this run', $rows['page_error']['detail']);
        $this->assertSame('Skipped: no sitemap pages in this run', $rows['blocked_by_robots']['detail']);
        $this->assertSame('Skipped: no pages that loaded in this run', $rows['viewport_missing']['detail']);
        $this->assertSame('Skipped: no indexable pages in this run', $rows['title_missing']['detail']);
        $this->assertSame('Skipped: no noindex pages in this run', $rows['noindex_content']['detail']);
        $this->assertSame('Skipped: no redirects in this run', $rows['redirect_chain']['detail']);
        $this->assertSame('Skipped: no images in this run', $rows['broken_image']['detail']);
    }

    public function test_one_of_something_reads_in_the_singular(): void
    {
        $rows = $this->byKey([], ['sitemapPages' => 1, 'noindex' => 1, 'redirects' => 1, 'images' => 1] + self::LIVE);

        $this->assertSame('1 sitemap page tested', $rows['page_error']['detail']);
        $this->assertSame('1 redirect tested', $rows['redirect_chain']['detail']);
        $this->assertSame('1 image tested', $rows['broken_image']['detail']);
        $this->assertStringStartsWith('1 noindex page tested', $rows['noindex_content']['detail']);
    }

    public function test_a_failing_page_check_shows_how_many_pages_are_affected(): void
    {
        $findings = [$this->finding('title_missing', '/a'), $this->finding('title_missing', '/b'), $this->finding('h1_missing', '/a')];

        $rows = $this->byKey($findings, self::LIVE);

        $this->assertSame('fail', $rows['title_missing']['status']);
        $this->assertSame('2 pages affected', $rows['title_missing']['detail']);
        $this->assertSame('1 page affected', $rows['h1_missing']['detail']);
        $this->assertSame('pass', $rows['title_long']['status']);
    }

    public function test_a_failing_site_wide_check_says_whole_site(): void
    {
        $rows = $this->byKey([$this->finding('robots_missing', null, 'Status 404')], self::LIVE);

        $this->assertSame('fail', $rows['robots_missing']['status']);
        $this->assertSame('Whole site', $rows['robots_missing']['detail']);
    }

    public function test_a_run_with_no_recorded_coverage_shows_unknown_but_still_shows_failures(): void
    {
        $rows = $this->byKey([$this->finding('title_missing', '/a')], null);

        $this->assertSame('fail', $rows['title_missing']['status']);
        $this->assertSame('unknown', $rows['page_error']['status']);
        $this->assertSame('Coverage was not recorded for this run', $rows['page_error']['detail']);
        $this->assertSame('unknown', $rows['robots_missing']['status']);
    }

    public function test_each_status_has_its_word_solid_icon_and_colour(): void
    {
        $rows = $this->byKey([$this->finding('title_missing', '/a')], ['live' => false, 'robots' => false] + self::LIVE);

        $this->assertSame(['Fail', 'bxs-x-circle', 'text-danger'], [$rows['title_missing']['statusLabel'], $rows['title_missing']['statusIcon'], $rows['title_missing']['statusClass']]);
        $this->assertSame(['Pass', 'bxs-check-circle', 'text-success'], [$rows['page_error']['statusLabel'], $rows['page_error']['statusIcon'], $rows['page_error']['statusClass']]);
        $this->assertSame(['Skipped', 'bxs-minus-circle', 'text-muted'], [$rows['robots_missing']['statusLabel'], $rows['robots_missing']['statusIcon'], $rows['robots_missing']['statusClass']]);
        $this->assertSame(['Unknown', 'bxs-help-circle'], [CheckSummary::rows([], null)[0]['statusLabel'], CheckSummary::rows([], null)[0]['statusIcon']]);
        $this->assertLessThan($rows['page_error']['statusRank'], $rows['title_missing']['statusRank']);
    }

    public function test_decode_reads_stored_coverage_and_refuses_anything_else(): void
    {
        $this->assertEquals(self::LIVE, CheckSummary::decode(\json_encode(self::LIVE)));
        $this->assertNull(CheckSummary::decode(null));
        $this->assertNull(CheckSummary::decode(''));
        $this->assertNull(CheckSummary::decode('not json'));
        $this->assertNull(CheckSummary::decode('{"pages":3}'));

        $minimal = CheckSummary::decode('{"live":false,"favicon":"bogus"}');
        $this->assertFalse($minimal['live']);
        $this->assertNull($minimal['favicon']);
        $this->assertSame(0, $minimal['pages']);
    }

    // ---- Checks::coverage, what a run records

    private function page(string $url, array $over = []): array
    {
        return $over + ['url' => $url, 'source' => 'site', 'in_sitemap' => 1, 'status_code' => 200, 'robots' => 'index'];
    }

    public function test_coverage_counts_follow_the_rules_the_checks_use(): void
    {
        $pages = [
            $this->page('/'),
            $this->page('/a'),
            $this->page('/hidden', ['robots' => 'noindex']),
            $this->page('/moved', ['status_code' => 301]),
            $this->page('/gone', ['status_code' => 404]),
            $this->page('/linked', ['in_sitemap' => 0, 'source' => 'linked']),
            $this->page('/pic.jpg', ['source' => 'image', 'in_sitemap' => 0]),
        ];

        $coverage = Checks::coverage($pages, ['isLive' => true, 'baseUrl' => 'https://example.com', 'thresholds' => ['titleMax' => 70], 'site' => ['robots' => 200, 'sitemap' => ['status' => 200, 'urls' => 9], 'favicon' => ['declared' => true, 'status' => 200], 'apple' => ['declared' => false, 'status' => 404], 'notFound' => 404]]);

        $this->assertSame(5, $coverage['sitemapPages']);
        $this->assertSame(4, $coverage['pages']);
        $this->assertSame(3, $coverage['indexable']);
        $this->assertSame(1, $coverage['noindex']);
        $this->assertSame(1, $coverage['redirects']);
        $this->assertSame(1, $coverage['images']);
        $this->assertTrue($coverage['live']);
        $this->assertTrue($coverage['https']);
        $this->assertTrue($coverage['robots']);
        $this->assertTrue($coverage['probed']);
        $this->assertSame(9, $coverage['sitemapUrls']);
        $this->assertSame('declared', $coverage['favicon']);
        $this->assertSame('conventional', $coverage['apple']);
        $this->assertSame(70, $coverage['thresholds']['titleMax']);
        $this->assertSame(Checks::DEFAULT_THRESHOLDS['titleMin'], $coverage['thresholds']['titleMin']);
    }

    public function test_coverage_records_what_was_not_probed_or_not_live(): void
    {
        $offLive = Checks::coverage([], ['isLive' => false, 'baseUrl' => 'http://localhost', 'site' => ['robots' => 200, 'favicon' => ['declared' => true, 'status' => null]]]);
        $this->assertFalse($offLive['live']);
        $this->assertFalse($offLive['robots']);
        $this->assertFalse($offLive['https']);
        $this->assertSame('external', $offLive['favicon']);

        $noRobots = Checks::coverage([], ['isLive' => true, 'site' => ['robots' => 404]]);
        $this->assertFalse($noRobots['robots']);

        $unprobed = Checks::coverage([], []);
        $this->assertFalse($unprobed['probed']);
        $this->assertNull($unprobed['favicon']);
    }

    public function test_a_stored_coverage_round_trips_into_the_summary(): void
    {
        $coverage = Checks::coverage([$this->page('/')], ['isLive' => false, 'site' => ['robots' => 200]]);

        $rows = $this->byKey([], CheckSummary::decode(\json_encode($coverage)));

        $this->assertSame('1 sitemap page tested', $rows['page_error']['detail']);
        $this->assertSame('Skipped: runs on live only', $rows['robots_missing']['detail']);
    }
}
