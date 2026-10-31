<?php

declare(strict_types=1);

namespace Osmium\Services\SeoAudit\Models;

/**
 * Compares two runs' findings: what is new, what was fixed, what is still there.
 */
class RunDiff
{
    /**
     * @param array<string, true> $previous "check|page" keys of the earlier run
     * @param array<string, true> $current "check|page" keys of the later run
     * @return array{new: int, fixed: int, unchanged: int}
     */
    public static function compare(array $previous, array $current): array
    {
        return [
            'new' => \count(\array_diff_key($current, $previous)),
            'fixed' => \count(\array_diff_key($previous, $current)),
            'unchanged' => \count(\array_intersect_key($current, $previous)),
        ];
    }
}
