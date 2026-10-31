<?php

declare(strict_types=1);

/**
 * SEO Audit lists, rendered by Osmium\Modules\Admin\Core\ListView. 'fixes' has one row per check that fired, most
 * serious first, and expands (get_items) to the pages it affects. 'runs' is the history; each row opens its report.
 * Rows are prepared by SeoAuditReport.
 */
$severityBadge = fn(array $row, array $context): string => '<span class="badge bg-label-' . \htmlspecialchars((string) $row['severityClass']) . '">'
    . \htmlspecialchars((string) $row['severityLabel']) . '</span>';

return [
    'fixes' => [
        'id' => 'seo-fixes',
        'title' => 'Priority Fixes',
        'icon' => 'bx-list-check',
        'iconClass' => 'text-success',
        'noun' => ['issue', 'issues'],
        'emptyText' => 'Nothing to fix. Run an audit to check the site',
        'endpoint' => 'seo-audit/action/',
        'expand' => [
            'action' => 'get_items',
            'itemsKey' => 'items',
            'emptyText' => 'No pages',
            'columns' => [
                ['key' => 'page_url', 'label' => 'Page', 'strong' => true, 'fallback' => 'Whole site'],
                ['key' => 'detail', 'label' => 'Detail', 'fallback' => '-'],
            ],
        ],
        'columns' => [
            ['key' => 'severityLabel', 'label' => 'Priority', 'type' => 'html', 'nowrap' => true, 'sortKey' => 'severityRank', 'render' => $severityBadge],
            ['key' => 'label', 'label' => 'Issue', 'type' => 'text', 'strong' => true, 'sub' => ['key' => 'group', 'style' => 'muted']],
            ['key' => 'pages', 'label' => 'Pages', 'type' => 'text', 'align' => 'end', 'nowrap' => true],
            ['key' => 'fix', 'label' => 'How to fix', 'type' => 'text', 'fill' => true, 'small' => true],
        ],
        'actions' => [],
    ],

    'runs' => [
        'id' => 'seo-runs',
        'title' => 'Audit Runs',
        'icon' => 'bx-history',
        'iconClass' => 'text-primary',
        'noun' => ['run', 'runs'],
        'emptyText' => 'No audits yet',
        'columns' => [
            ['key' => 'startedFormatted', 'label' => 'Started', 'type' => 'html', 'sortKey' => 'id', 'nowrap' => true, 'render' => fn(array $row, array $context): string => '<a href="' . \htmlspecialchars($context['adminUrl']('seo-audit/?run=' . (int) $row['id'])) . '">' . \htmlspecialchars((string) $row['startedFormatted']) . '</a>'],
            ['key' => 'statusLabel', 'label' => 'Status', 'type' => 'text', 'nowrap' => true],
            ['key' => 'scoreLabel', 'label' => 'Score', 'type' => 'text', 'strong' => true, 'align' => 'end', 'sortKey' => 'scoreSort'],
            ['key' => 'pages_checked', 'label' => 'Pages', 'type' => 'text', 'align' => 'end'],
            ['key' => 'finding_count', 'label' => 'Issues', 'type' => 'text', 'align' => 'end'],
            ['key' => 'changeLabel', 'label' => 'Since previous run', 'type' => 'text', 'fill' => true, 'small' => true],
        ],
        'actions' => [],
    ],
];
