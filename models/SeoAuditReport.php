<?php

declare(strict_types=1);

namespace Osmium\Services\SeoAudit\Models;

/**
 * Turns stored runs and findings into the rows and numbers the admin pages show. No SQL here: it takes arrays.
 */
class SeoAuditReport
{
    private const SEVERITY_CLASS = ['critical' => 'danger', 'high' => 'warning', 'medium' => 'info', 'low' => 'secondary'];

    /**
     * One row per check that fired, most serious first. The row id packs the run and the check position
     * (run * 100 + position) because the list's expand request carries only an id.
     *
     * @param array<int, array{check_key: string, severity: string, page_url: ?string, detail: ?string}> $findings
     * @return array<int, array<string, mixed>>
     */
    public static function fixRows(int $runId, array $findings): array
    {
        $positions = \array_flip(\array_keys(Checks::CATALOGUE));
        $pagesPerCheck = [];
        foreach ($findings as $finding) {
            $pagesPerCheck[$finding['check_key']][$finding['page_url'] ?? ''] = true;
        }

        $rows = [];
        foreach ($pagesPerCheck as $checkKey => $pages) {
            $check = Checks::CATALOGUE[$checkKey];
            $rows[] = [
                'id' => $runId * 100 + $positions[$checkKey],
                'severityLabel' => \ucfirst($check['severity']),
                'severityClass' => self::SEVERITY_CLASS[$check['severity']],
                'severityRank' => \array_search($check['severity'], Checks::SEVERITIES, true),
                'label' => $check['label'],
                'group' => $check['group'],
                'pages' => \count($pages),
                'fix' => $check['fix'],
            ];
        }

        \usort($rows, fn(array $a, array $b): int => [$a['severityRank'], -$a['pages']] <=> [$b['severityRank'], -$b['pages']]);

        return $rows;
    }

    /** Unpack a fix row id back into the run and the check key, or null when it is not valid */
    public static function unpackId(int $id): ?array
    {
        $keys = \array_keys(Checks::CATALOGUE);
        $checkKey = $keys[$id % 100] ?? null;
        $runId = \intdiv($id, 100);

        return ($checkKey === null || $runId < 1) ? null : ['runId' => $runId, 'checkKey' => $checkKey];
    }

    /**
     * @param array<int, array{check_key: string, severity: string}> $findings
     * @return array{critical: int, high: int, medium: int, low: int}
     */
    public static function severityCounts(array $findings): array
    {
        $counts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($findings as $finding) {
            $counts[$finding['severity']]++;
        }

        return $counts;
    }

    /**
     * @param array<string, mixed> $run
     * @param array{new: int, fixed: int, unchanged: int}|null $diff
     * @return array<string, mixed>
     */
    public static function runRow(array $run, ?array $diff, ?int $previousScore): array
    {
        $score = $run['score'] === null ? null : (int) $run['score'];
        $scoreMove = ($score !== null && $previousScore !== null) ? $score - $previousScore : null;

        return $run + [
            'startedFormatted' => \date('d M Y H:i', (int) \strtotime((string) $run['started_at'])),
            'statusLabel' => \ucfirst((string) $run['status']),
            'scoreLabel' => $score === null ? '-' : $score . ($scoreMove ? ' (' . ($scoreMove > 0 ? '+' : '') . $scoreMove . ')' : ''),
            'scoreSort' => $score ?? -1,
            'changeLabel' => $diff === null ? 'First audit' : "{$diff['fixed']} fixed, {$diff['new']} new, {$diff['unchanged']} unchanged",
        ];
    }
}
