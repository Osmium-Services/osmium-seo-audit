<?php

declare(strict_types=1);

namespace Osmium\Services\SeoAudit\Models;

/**
 * request.internal: tells core a request is the audit's own crawler, so the redirect and 404 log skips it.
 */
class SeoAuditHooks
{
    /**
     * @param array{headers?: array<string, string>, dataSource?: object} $payload
     * @return bool|null true for the audit's own request, null for anyone else's
     */
    public static function requestInternal(array $payload): ?bool
    {
        $token = (string) ($payload['headers'][Crawler::TOKEN_HEADER] ?? '');
        if ($token === '') return null;

        $dataSource = $payload['dataSource'] ?? null;
        if ($dataSource === null) return null;

        return (new AuditStore($dataSource))->isRunningToken($token) ? true : null;
    }
}
