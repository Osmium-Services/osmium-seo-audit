<?php

declare(strict_types=1);

namespace Tests\Services\SeoAudit;

use Osmium\Services\SeoAudit\Models\AuditSettings;
use Osmium\Services\SeoAudit\Models\Checks;
use Osmium\Services\SeoAudit\Models\ReportExport;
use Osmium\Services\SeoAudit\Models\RunDiff;
use Osmium\Services\SeoAudit\Models\SeoAuditHooks;
use Osmium\Services\SeoAudit\Models\SeoAuditReport;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/Checks.php';
require_once __DIR__ . '/../models/PageParser.php';
require_once __DIR__ . '/../models/RunDiff.php';
require_once __DIR__ . '/../models/SeoAuditReport.php';
require_once __DIR__ . '/../models/ReportExport.php';
require_once __DIR__ . '/../models/AuditSettings.php';
require_once __DIR__ . '/../models/Crawler.php';
require_once __DIR__ . '/../models/SeoAuditHooks.php';

class ReportAndSettingsTest extends TestCase
{
    private string $settingsPath;

    protected function setUp(): void
    {
        $this->settingsPath = \sys_get_temp_dir() . '/seo-audit-settings-' . \uniqid() . '.json.php';
    }

    protected function tearDown(): void
    {
        if (\file_exists($this->settingsPath)) \unlink($this->settingsPath);
    }

    public function test_fix_rows_are_ordered_by_severity_then_pages_and_ids_unpack(): void
    {
        $findings = [
            ['check_key' => 'title_short', 'severity' => 'low', 'page_url' => '/a/', 'detail' => null],
            ['check_key' => 'h1_missing', 'severity' => 'high', 'page_url' => '/a/', 'detail' => null],
            ['check_key' => 'h1_missing', 'severity' => 'high', 'page_url' => '/b/', 'detail' => null],
            ['check_key' => 'page_error', 'severity' => 'critical', 'page_url' => '/c/', 'detail' => null],
        ];

        $rows = SeoAuditReport::fixRows(7, $findings);

        $this->assertSame(['Critical', 'High', 'Low'], \array_column($rows, 'severityLabel'));
        $this->assertSame(2, $rows[1]['pages']);
        $this->assertSame(['runId' => 7, 'checkKey' => 'h1_missing'], SeoAuditReport::unpackId($rows[1]['id']));
        $this->assertNull(SeoAuditReport::unpackId(5));
        $this->assertNull(SeoAuditReport::unpackId(700 + \count(Checks::CATALOGUE)));
    }

    public function test_severity_counts(): void
    {
        $counts = SeoAuditReport::severityCounts([['severity' => 'high', 'check_key' => 'x'], ['severity' => 'high', 'check_key' => 'y'], ['severity' => 'low', 'check_key' => 'z']]);

        $this->assertSame(['critical' => 0, 'high' => 2, 'medium' => 0, 'low' => 1], $counts);
    }

    public function test_run_diff_counts_new_fixed_and_unchanged(): void
    {
        $diff = RunDiff::compare(['a|/x' => true, 'b|/y' => true], ['b|/y' => true, 'c|/z' => true]);

        $this->assertSame(['new' => 1, 'fixed' => 1, 'unchanged' => 1], $diff);
    }

    public function test_settings_round_trip_and_clamp(): void
    {
        $saved = AuditSettings::save(['pageCap' => 99999, 'delayMs' => -5, 'baseUrl' => 'http://localhost/', 'thresholds' => ['titleMax' => 70]], $this->settingsPath);

        $this->assertSame(2000, $saved['pageCap']);
        $this->assertSame(0, $saved['delayMs']);
        $this->assertSame('http://localhost', $saved['baseUrl']);
        $this->assertSame(70, AuditSettings::get($this->settingsPath)['thresholds']['titleMax']);
        $this->assertSame(Checks::DEFAULT_THRESHOLDS['titleMin'], AuditSettings::get($this->settingsPath)['thresholds']['titleMin']);
        $this->assertStringStartsWith('<?php exit(); ?>', (string) \file_get_contents($this->settingsPath));
    }

    public function test_a_crawl_address_with_a_path_or_bad_scheme_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        AuditSettings::save(['baseUrl' => 'ftp://example.com/x'], $this->settingsPath);
    }

    public function test_missing_settings_file_gives_defaults(): void
    {
        $this->assertSame(AuditSettings::defaults(), AuditSettings::get($this->settingsPath));
    }

    public function test_the_hook_ignores_requests_without_a_token_or_a_data_source(): void
    {
        $this->assertNull(SeoAuditHooks::requestInternal(['headers' => []]));
        $this->assertNull(SeoAuditHooks::requestInternal(['headers' => ['X-Osmium-Audit' => \str_repeat('a', 32)]]));
    }

    public function test_the_markdown_report_lists_fixes_by_priority_with_their_pages(): void
    {
        $run = ['id' => 4, 'started_at' => '2026-10-31 10:00:00', 'pages_checked' => 12, 'score' => 71];
        $findings = [
            ['check_key' => 'page_error', 'severity' => 'critical', 'page_url' => '/gone', 'detail' => 'Status 404'],
            ['check_key' => 'h1_missing', 'severity' => 'high', 'page_url' => '/a', 'detail' => null],
            ['check_key' => 'h1_missing', 'severity' => 'high', 'page_url' => '/b', 'detail' => null],
        ];

        $markdown = ReportExport::markdown($run, $findings, 'https://example.com');

        $this->assertStringContainsString('# SEO audit: https://example.com', $markdown);
        $this->assertStringContainsString('12 pages checked. Site score: 71/100.', $markdown);
        $this->assertStringContainsString('| Critical | 1 |', $markdown);
        $this->assertStringContainsString('- `/gone` - Status 404', $markdown);
        $this->assertStringContainsString('### Missing main heading (h1) (2)', $markdown);
        $this->assertLessThan(\strpos($markdown, '## High'), \strpos($markdown, '## Critical'));
    }

    public function test_an_audit_with_no_findings_says_so(): void
    {
        $markdown = ReportExport::markdown(['id' => 1, 'started_at' => '2026-10-31 10:00:00', 'pages_checked' => 3, 'score' => 100], [], 'https://example.com');

        $this->assertStringContainsString('Nothing to fix.', $markdown);
    }
}
