<?php

declare(strict_types=1);

namespace Tests\Services\SeoAudit;

use Osmium\Services\SeoAudit\Models\Checks;
use Osmium\Services\SeoAudit\Models\PageParser;
use Osmium\Services\SeoAudit\Models\Scoring;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/PageParser.php';
require_once __DIR__ . '/../models/Checks.php';
require_once __DIR__ . '/../models/Scoring.php';

class ChecksTest extends TestCase
{
    /** A healthy page; each test overrides what it wants to break */
    private function page(string $url, array $over = []): array
    {
        return $over + [
            'url' => $url, 'in_sitemap' => 1, 'fetched' => 1, 'status_code' => 200, 'redirect_target' => null, 'fetch_error' => null,
            'title' => 'A perfectly good page title for ' . $url, 'description' => \str_repeat('A useful description. ', 5) . $url,
            'canonical' => 'https://example.com' . $url, 'robots' => 'index,follow', 'h1_count' => 1, 'heading_levels' => '1,2,3',
            'word_count' => 400, 'images_missing_alt' => 0, 'og_title' => 1, 'og_image' => 1, 'has_viewport' => 1, 'jsonld_invalid' => 0,
            'body_hash' => \md5($url), 'response_ms' => 200, 'bytes' => 20000, 'links' => [],
        ];
    }

    /** @return string[] check keys raised for a url */
    private function keysFor(array $findings, string $url): array
    {
        $keys = [];
        foreach ($findings as $finding) {
            if ($finding['page_url'] === $url) $keys[] = $finding['check_key'];
        }
        \sort($keys);

        return $keys;
    }

    private function audit(array $pages, array $context = []): array
    {
        $home = $this->page('/', ['links' => ['/a/', '/b/']]);

        return Checks::run([$home, ...$pages], $context + ['baseUrl' => 'https://example.com']);
    }

    public function test_a_healthy_site_has_no_findings(): void
    {
        $pages = [$this->page('/a/'), $this->page('/b/')];

        $this->assertSame([], $this->audit($pages));
    }

    public function test_indexing_problems_in_the_sitemap_are_critical_or_high(): void
    {
        $pages = [
            $this->page('/a/', ['status_code' => 404, 'title' => null]),
            $this->page('/b/', ['robots' => 'noindex,follow']),
            $this->page('/c/', ['status_code' => 301, 'redirect_target' => '/b/']),
        ];

        $findings = $this->audit($pages);

        $this->assertSame(['page_error'], $this->keysFor($findings, '/a/'));
        $this->assertContains('noindex_in_sitemap', $this->keysFor($findings, '/b/'));
        $this->assertContains('redirect_in_sitemap', $this->keysFor($findings, '/c/'));
    }

    public function test_robots_txt_blocking_a_listed_page_is_critical(): void
    {
        $rules = Checks::parseRobotsDisallow("User-agent: Googlebot\nDisallow: /nothing/\n\nUser-agent: *\nDisallow: /admin/\nDisallow: /private/ # comment\n");

        $this->assertSame(['/admin/', '/private/'], $rules);

        $findings = $this->audit([$this->page('/private/x/')], ['robotsDisallow' => $rules]);
        $this->assertContains('blocked_by_robots', $this->keysFor($findings, '/private/x/'));
    }

    public function test_canonical_missing_and_pointing_elsewhere(): void
    {
        $pages = [
            $this->page('/a/', ['canonical' => '']),
            $this->page('/b/', ['canonical' => 'https://example.com/a/']),
            $this->page('/c/', ['canonical' => 'https://other.com/c/']),
        ];

        $findings = $this->audit($pages);

        $this->assertContains('canonical_missing', $this->keysFor($findings, '/a/'));
        $this->assertContains('canonical_mismatch', $this->keysFor($findings, '/b/'));
        $this->assertNotContains('canonical_mismatch', $this->keysFor($findings, '/c/'));
    }

    public function test_title_and_description_rules(): void
    {
        $pages = [
            $this->page('/a/', ['title' => '', 'description' => '']),
            $this->page('/b/', ['title' => \str_repeat('x', 80), 'description' => \str_repeat('y', 200)]),
            $this->page('/c/', ['title' => 'Short', 'description' => 'Tiny']),
        ];

        $findings = $this->audit($pages);

        $this->assertSame(['description_missing', 'title_missing'], \array_values(\array_intersect($this->keysFor($findings, '/a/'), ['title_missing', 'description_missing'])));
        $this->assertContains('title_long', $this->keysFor($findings, '/b/'));
        $this->assertContains('description_long', $this->keysFor($findings, '/b/'));
        $this->assertContains('title_short', $this->keysFor($findings, '/c/'));
        $this->assertContains('description_short', $this->keysFor($findings, '/c/'));
    }

    public function test_duplicates_are_flagged_on_every_page_sharing_the_value(): void
    {
        $pages = [
            $this->page('/a/', ['title' => 'Same title for both pages', 'body_hash' => 'x']),
            $this->page('/b/', ['title' => 'Same title for both pages', 'body_hash' => 'x']),
        ];

        $findings = $this->audit($pages);

        $this->assertContains('title_duplicate', $this->keysFor($findings, '/a/'));
        $this->assertContains('title_duplicate', $this->keysFor($findings, '/b/'));
        $this->assertContains('duplicate_content', $this->keysFor($findings, '/b/'));
    }

    public function test_headings_content_and_social_rules(): void
    {
        $pages = [
            $this->page('/a/', ['h1_count' => 0, 'heading_levels' => '2,4', 'word_count' => 20, 'images_missing_alt' => 3, 'og_image' => 0, 'jsonld_invalid' => 1]),
            $this->page('/b/', ['h1_count' => 2]),
        ];

        $keys = $this->keysFor($this->audit($pages), '/a/');

        foreach (['h1_missing', 'heading_skip', 'thin_content', 'image_alt_missing', 'og_missing', 'jsonld_invalid'] as $expected) {
            $this->assertContains($expected, $keys);
        }
        $this->assertContains('h1_multiple', $this->keysFor($this->audit($pages), '/b/'));
    }

    public function test_links_to_broken_and_redirected_pages(): void
    {
        $pages = [
            $this->page('/a/', ['links' => ['/gone/', '/moved/']]),
            $this->page('/b/'),
            $this->page('/gone/', ['status_code' => 404, 'in_sitemap' => 0]),
            $this->page('/moved/', ['status_code' => 301, 'redirect_target' => '/b/', 'in_sitemap' => 0]),
        ];

        $keys = $this->keysFor($this->audit($pages), '/a/');

        $this->assertContains('broken_link', $keys);
        $this->assertContains('link_to_redirect', $keys);
    }

    public function test_a_listed_page_nothing_links_to_is_an_orphan_but_the_home_page_is_not(): void
    {
        $findings = $this->audit([$this->page('/a/'), $this->page('/b/'), $this->page('/lonely/')]);

        $this->assertContains('orphan_page', $this->keysFor($findings, '/lonely/'));
        $this->assertNotContains('orphan_page', $this->keysFor($findings, '/'));
    }

    public function test_a_redirect_that_leads_to_another_redirect_is_a_chain(): void
    {
        $pages = [
            $this->page('/a/'), $this->page('/b/'),
            $this->page('/old/', ['status_code' => 301, 'redirect_target' => '/older/', 'in_sitemap' => 0, 'links' => ['/a/']]),
            $this->page('/older/', ['status_code' => 301, 'redirect_target' => '/a/', 'in_sitemap' => 0]),
        ];

        $this->assertContains('redirect_chain', $this->keysFor($this->audit($pages), '/old/'));
    }

    public function test_technical_rules_and_https(): void
    {
        $pages = [$this->page('/a/', ['has_viewport' => 0, 'response_ms' => 4000, 'bytes' => 2000000]), $this->page('/b/')];

        $findings = $this->audit($pages, ['isLive' => true, 'baseUrl' => 'http://example.com']);

        foreach (['viewport_missing', 'slow_response', 'heavy_page'] as $expected) {
            $this->assertContains($expected, $this->keysFor($findings, '/a/'));
        }
        $this->assertContains('no_https', \array_column($findings, 'check_key'));
    }

    public function test_every_finding_uses_a_catalogued_check_and_its_severity(): void
    {
        $pages = [$this->page('/a/', ['status_code' => 500, 'title' => '']), $this->page('/b/', ['h1_count' => 0])];

        foreach ($this->audit($pages) as $finding) {
            $this->assertSame(Checks::CATALOGUE[$finding['check_key']]['severity'], $finding['severity']);
        }
    }

    public function test_the_score_drops_with_severity_and_spread(): void
    {
        $one = [['check_key' => 'h1_missing', 'severity' => 'high', 'page_url' => '/a/']];
        $all = [['check_key' => 'h1_missing', 'severity' => 'high', 'page_url' => '/a/'], ['check_key' => 'h1_missing', 'severity' => 'high', 'page_url' => '/b/']];
        $critical = [['check_key' => 'page_error', 'severity' => 'critical', 'page_url' => '/a/']];

        $this->assertSame(100, Scoring::score([], 10));
        $this->assertGreaterThan(Scoring::score($all, 2), Scoring::score($one, 10));
        $this->assertGreaterThan(Scoring::score($critical, 10), Scoring::score($one, 10));
        $this->assertGreaterThanOrEqual(0, Scoring::score($critical, 1));
    }
}
