<?php

declare(strict_types=1);

namespace Osmium\Services\SeoAudit\Models;

/**
 * Fetches one URL of the site for the audit. Never follows redirects (the audit wants to see them), sends the
 * audit's User-Agent and its per-run token header, reads at most 3 MB. headOnly asks for the status without the body.
 */
class Crawler
{
    public const USER_AGENT = 'OsmiumSEOAudit/1.0 (bot)';
    public const TOKEN_HEADER = 'X-Osmium-Audit';
    private const MAX_BYTES = 3000000;

    /**
     * @return array{status: int, headers: array<string, string>, body: string, ms: int, bytes: int, error: ?string}
     */
    public function fetch(string $url, string $token, bool $headOnly = false): array
    {
        $headers = [];
        $body = '';
        $curl = \curl_init($url);
        \curl_setopt_array($curl, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_NOBODY => $headOnly,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_HTTPHEADER => [self::TOKEN_HEADER . ': ' . $token, 'Accept: text/html,application/xhtml+xml'],
            CURLOPT_HEADERFUNCTION => function ($curl, string $line) use (&$headers): int {
                $parts = \explode(':', $line, 2);
                $isHeader = \count($parts) === 2;
                if ($isHeader) $headers[\strtolower(\trim($parts[0]))] = \trim($parts[1]);

                return \strlen($line);
            },
            CURLOPT_WRITEFUNCTION => function ($curl, string $chunk) use (&$body): int {
                $body .= $chunk;
                $tooBig = \strlen($body) > self::MAX_BYTES;

                return $tooBig ? 0 : \strlen($chunk);
            },
        ]);

        $started = \microtime(true);
        $ok = \curl_exec($curl);
        $ms = (int) \round((\microtime(true) - $started) * 1000);
        $tooBigAbort = \curl_errno($curl) === CURLE_WRITE_ERROR;
        $error = ($ok === false && !$tooBigAbort) ? \curl_error($curl) : null;
        $status = (int) \curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        \curl_close($curl);

        return ['status' => $status, 'headers' => $headers, 'body' => $body, 'ms' => $ms, 'bytes' => \strlen($body), 'error' => $error];
    }
}
