<?php

declare(strict_types=1);

namespace Osmium\Services\SeoAudit\Models;

use Osmium\Core\Library\OsmiumPDO;
use Osmium\Core\Models\Model;

/**
 * Reads and writes the audit's three tables: runs, the pages each run fetched, and the findings the checks raised.
 */
class AuditStore extends Model
{
    protected ?string $sqlDir = __DIR__ . '/sql';

    public function __construct(OsmiumPDO $database)
    {
        parent::__construct(database: $database, tableName: 'seo_audit_runs');
    }

    public function createRun(string $baseUrl, int $pageCap, string $token): int
    {
        $this->database->insert(
            tableName: $this->tablePrefix . 'seo_audit_runs',
            data: ['base_url' => $baseUrl, 'page_cap' => $pageCap, 'token' => $token],
        );

        return (int) $this->database->lastInsertID();
    }

    /** Runs that began over an hour ago and never finished are written off, so a crashed run cannot block the next */
    public function failStaleRuns(): void
    {
        $this->database->prepare(sql: $this->loadSqlFile('run-fail-stale.sql'))->execute();
    }

    /** @return array<string, mixed>|null */
    public function runningRun(): ?array
    {
        $row = $this->database->prepare(sql: $this->loadSqlFile('run-running.sql'))->single();

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function run(int $id): ?array
    {
        $row = $this->database->prepare(sql: $this->loadSqlFile('run-get.sql'), bindings: [':id' => $id])->single();

        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public function recentRuns(int $limit = 30): array
    {
        return $this->database->prepare(sql: $this->loadSqlFile('run-recent.sql'), bindings: [':limit' => $limit])->resultset();
    }

    /** The finished run just before this one, for the comparison */
    public function previousDoneRun(int $beforeId): ?array
    {
        $row = $this->database->prepare(sql: $this->loadSqlFile('run-latest-done.sql'), bindings: [':before' => $beforeId])->single();

        return $row ?: null;
    }

    public function finishRun(int $id, string $status, int $pages, ?int $score): void
    {
        $this->database->prepare(
            sql: $this->loadSqlFile('run-finish.sql'),
            bindings: [':status' => $status, ':pages' => $pages, ':score' => $score, ':id' => $id],
        )->execute();
    }

    /** Whether a header token belongs to the run in progress */
    public function isRunningToken(string $token): bool
    {
        $isShape = (bool) \preg_match('/^[a-f0-9]{32}$/', $token);
        if (!$isShape) return false;

        $row = $this->database->prepare(sql: $this->loadSqlFile('run-token-valid.sql'), bindings: [':token' => $token])->single();

        return (bool) $row;
    }

    public function addPage(int $runId, string $url, string $source, bool $inSitemap): bool
    {
        $stmt = $this->database->prepare(
            sql: $this->loadSqlFile('page-add.sql'),
            bindings: [':run' => $runId, ':url' => $url, ':source' => $source, ':inSitemap' => (int) $inSitemap],
        );
        $stmt->execute();

        return $stmt->rowCount() === 1;
    }

    /** @return array{total: int, done: int} */
    public function progress(int $runId): array
    {
        $row = $this->database->prepare(sql: $this->loadSqlFile('page-count.sql'), bindings: [':run' => $runId])->single();

        return ['total' => (int) ($row['total'] ?? 0), 'done' => (int) ($row['done'] ?? 0)];
    }

    public function imageCount(int $runId): int
    {
        $row = $this->database->prepare(sql: $this->loadSqlFile('page-count-images.sql'), bindings: [':run' => $runId])->single();

        return (int) ($row['total'] ?? 0);
    }

    /** @return array<int, array{id: int, url: string, source: string}> */
    public function pendingPages(int $runId, int $limit): array
    {
        return $this->database->prepare(sql: $this->loadSqlFile('page-pending.sql'), bindings: [':run' => $runId, ':limit' => $limit])->resultset();
    }

    /** @param array<string, mixed> $facts the parsed page (see PageParser), or empty for a page that did not return HTML */
    public function savePage(int $id, int $status, ?string $redirect, ?string $error, array $facts, ?int $ms, ?int $bytes): void
    {
        $this->database->prepare(sql: $this->loadSqlFile('page-save.sql'), bindings: [
            ':status' => $status, ':redirect' => $redirect, ':error' => $error,
            ':title' => $facts['title'] ?? null, ':description' => $facts['description'] ?? null,
            ':canonical' => $facts['canonical'] ?? null, ':robots' => $facts['robots'] ?? null,
            ':h1' => (int) ($facts['h1_count'] ?? 0), ':levels' => $facts['heading_levels'] ?? null,
            ':words' => (int) ($facts['word_count'] ?? 0), ':alt' => (int) ($facts['images_missing_alt'] ?? 0),
            ':ogTitle' => (int) ($facts['og_title'] ?? false), ':ogImage' => (int) ($facts['og_image'] ?? false),
            ':viewport' => (int) ($facts['has_viewport'] ?? false), ':jsonld' => (int) ($facts['jsonld_invalid'] ?? 0),
            ':hash' => $facts['body_hash'] ?? null, ':ms' => $ms, ':bytes' => $bytes,
            ':links' => \json_encode($facts['links'] ?? []), ':lang' => $facts['lang'] ?? null,
            ':icons' => isset($facts['icons']) ? \json_encode($facts['icons']) : null,
            ':images' => \json_encode($facts['images'] ?? []), ':insecure' => \json_encode($facts['insecure'] ?? []), ':id' => $id,
        ])->execute();
    }

    /** @return array<int, array<string, mixed>> every page of a run, with `links` decoded to a list */
    public function pages(int $runId): array
    {
        $rows = $this->database->prepare(sql: $this->loadSqlFile('page-all.sql'), bindings: [':run' => $runId])->resultset();
        foreach ($rows as &$row) {
            $row['links'] = \json_decode((string) ($row['links'] ?? '[]'), associative: true) ?: [];
            $row['insecure'] = \json_decode((string) ($row['insecure'] ?? '[]'), associative: true) ?: [];
            $row['images'] = \json_decode((string) ($row['images'] ?? '[]'), associative: true) ?: [];
            $row['icons'] = \json_decode((string) ($row['icons'] ?? ''), associative: true) ?: ['icon' => [], 'apple' => []];
        }

        return $rows;
    }

    /** @param array<int, array{check_key: string, severity: string, page_url: ?string, detail: ?string}> $findings */
    public function addFindings(int $runId, array $findings): void
    {
        $this->database->beginTransaction();
        foreach ($findings as $finding) {
            $this->database->prepare(sql: $this->loadSqlFile('finding-add.sql'), bindings: [
                ':run' => $runId, ':check' => $finding['check_key'], ':severity' => $finding['severity'],
                ':url' => $finding['page_url'], ':detail' => $finding['detail'] === null ? null : \mb_substr($finding['detail'], 0, 500),
            ])->execute();
        }
        $this->database->endTransaction();
    }

    /** @return array<int, array{check_key: string, severity: string, page_url: ?string, detail: ?string}> */
    public function findings(int $runId): array
    {
        return $this->database->prepare(sql: $this->loadSqlFile('finding-all.sql'), bindings: [':run' => $runId])->resultset();
    }

    /** @return array<string, true> a set of "check|page" keys, to compare two runs */
    public function findingKeys(int $runId): array
    {
        $rows = $this->database->prepare(sql: $this->loadSqlFile('finding-keys.sql'), bindings: [':run' => $runId])->resultset();
        $keys = [];
        foreach ($rows as $row) {
            $keys[$row['check_key'] . '|' . ($row['page_url'] ?? '')] = true;
        }

        return $keys;
    }
}
