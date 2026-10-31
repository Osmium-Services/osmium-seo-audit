<?php

declare(strict_types=1);

namespace Osmium\Services\SeoAudit\Models;

/**
 * One run as a Markdown file: the score, then the fixes from Critical down, each with how to fix it and every affected
 * page. Written for a person and for an assistant that is asked to suggest or make the fixes. Pure: takes arrays.
 */
class ReportExport
{
    private const MAX_PAGES_PER_CHECK = 200;

    /**
     * @param array<string, mixed> $run a finished run row
     * @param array<int, array{check_key: string, severity: string, page_url: ?string, detail: ?string}> $findings ordered most serious first
     */
    public static function markdown(array $run, array $findings, string $siteUrl): string
    {
        $counts = SeoAuditReport::severityCounts($findings);
        $lines = [
            '# SEO audit: ' . $siteUrl,
            '',
            'Run ' . (int) $run['id'] . ' on ' . \date('d M Y H:i', (int) \strtotime((string) $run['started_at'])) . '. '
                . (int) $run['pages_checked'] . ' pages checked. Site score: ' . ($run['score'] === null ? 'n/a' : (int) $run['score'] . '/100') . '.',
            '',
            'Fix in the order below: Critical stops pages being indexed, High hurts ranking or clicks. Page paths are relative to the site.',
            '',
            '| Priority | Findings |',
            '|---|---|',
        ];
        foreach ($counts as $severity => $count) {
            $lines[] = '| ' . \ucfirst($severity) . ' | ' . $count . ' |';
        }

        return \implode("\n", [...$lines, '', ...self::sections($findings)]) . "\n";
    }

    /** @return string[] */
    private static function sections(array $findings): array
    {
        $byCheck = [];
        foreach ($findings as $finding) {
            $byCheck[$finding['check_key']][] = $finding;
        }

        if ($byCheck === []) return ['Nothing to fix.'];

        $lines = [];
        $currentSeverity = '';
        foreach ($byCheck as $checkKey => $items) {
            $check = Checks::CATALOGUE[$checkKey];
            if ($check['severity'] !== $currentSeverity) {
                $currentSeverity = $check['severity'];
                $lines[] = '## ' . \ucfirst($currentSeverity);
                $lines[] = '';
            }
            $lines = [...$lines, ...self::checkSection($check, $items)];
        }

        return $lines;
    }

    /** @return string[] */
    private static function checkSection(array $check, array $items): array
    {
        $lines = ['### ' . $check['label'] . ' (' . \count($items) . ')', '', '**' . $check['group'] . '.** ' . $check['fix'], ''];

        foreach (\array_slice($items, 0, self::MAX_PAGES_PER_CHECK) as $item) {
            $page = $item['page_url'] === null ? 'Whole site' : '`' . $item['page_url'] . '`';
            $lines[] = '- ' . $page . ($item['detail'] === null || $item['detail'] === '' ? '' : ' - ' . $item['detail']);
        }

        $extra = \count($items) - self::MAX_PAGES_PER_CHECK;
        if ($extra > 0) $lines[] = "- ...and {$extra} more";

        return [...$lines, ''];
    }
}
