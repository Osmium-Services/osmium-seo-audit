<?php

declare(strict_types=1);

namespace Osmium\Services\SeoAudit\Models;

/**
 * The 0-100 site score used to track runs. Each check that fired costs points by severity, scaled by how much of the
 * site it touches: a problem on one page costs 30% of its weight, on every page the full weight.
 */
class Scoring
{
    private const WEIGHTS = ['critical' => 25, 'high' => 10, 'medium' => 4, 'low' => 1];

    /**
     * @param array<int, array{check_key: string, severity: string, page_url: ?string}> $findings
     */
    public static function score(array $findings, int $pageCount): int
    {
        $pagesPerCheck = [];
        $severityPerCheck = [];
        foreach ($findings as $finding) {
            $pagesPerCheck[$finding['check_key']][$finding['page_url'] ?? ''] = true;
            $severityPerCheck[$finding['check_key']] = $finding['severity'];
        }

        $penalty = 0.0;
        $pageCount = \max(1, $pageCount);
        foreach ($pagesPerCheck as $checkKey => $pages) {
            $share = \min(1.0, \count($pages) / $pageCount);
            $penalty += self::WEIGHTS[$severityPerCheck[$checkKey]] * (0.3 + 0.7 * $share);
        }

        return (int) \max(0, \round(100 - $penalty));
    }
}
