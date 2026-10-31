<?php

declare(strict_types=1);

namespace Osmium\Services\SeoAudit\Models;

/**
 * Drives one audit run in small steps. The admin page calls step() repeatedly, each call fetching a few pages inside
 * a time budget, so no background process is needed. The last step runs the checks and closes the run.
 */
class AuditRunner
{
    private const BATCH = 5;

    /**
     * @param array{pageCap: int, delayMs: int, baseUrl: string, thresholds: array<string, int>} $settings
     * @param string $siteUrl the site's public address (config FQDN), whose host marks a link as internal
     * @param bool $isLive whether the site's environment is live, which switches on the robots.txt and https checks
     */
    public function __construct(
        private AuditStore $store,
        private Crawler $crawler,
        private array $settings,
        private string $siteUrl,
        private bool $isLive,
    ) {}

    /** The address requests go to: the setting, or the site's own address */
    public function crawlBase(): string
    {
        $configured = $this->settings['baseUrl'];

        return $configured !== '' ? $configured : \rtrim($this->siteUrl, '/');
    }

    /**
     * @param array<int, array{path: string, inSitemap: bool}> $pages the site's own pages
     * @return int the new run's id
     * @throws \RuntimeException when a run is already in progress
     */
    public function start(array $pages): int
    {
        $this->store->failStaleRuns();
        $isRunning = $this->store->runningRun() !== null;
        if ($isRunning) throw new \RuntimeException('An audit is already running');

        $token = \bin2hex(\random_bytes(16));
        $runId = $this->store->createRun(baseUrl: $this->crawlBase(), pageCap: $this->settings['pageCap'], token: $token);

        $added = 0;
        foreach ($pages as $page) {
            $full = $added >= $this->settings['pageCap'];
            if ($full) break;
            if ($this->store->addPage(runId: $runId, url: $page['path'], source: 'site', inSitemap: $page['inSitemap'])) $added++;
        }

        return $runId;
    }

    /**
     * Fetch the next few pages, or finish the run when none are left.
     *
     * @return array{status: string, done: int, total: int, finished: bool, score: ?int}
     */
    public function step(int $runId, float $budgetSeconds = 20.0): array
    {
        $run = $this->store->run($runId);
        $notRunning = $run === null || $run['status'] !== 'running';
        if ($notRunning) return $this->state($runId, $run['status'] ?? 'missing', null);

        $started = \microtime(true);
        $token = (string) $run['token'];
        $pending = $this->store->pendingPages($runId, self::BATCH);
        while ($pending !== []) {
            foreach ($pending as $page) {
                $this->fetchPage($runId, $page, $token, (int) $run['page_cap']);
                \usleep($this->settings['delayMs'] * 1000);
            }

            $outOfTime = (\microtime(true) - $started) > $budgetSeconds;
            if ($outOfTime) return $this->state($runId, 'running', null);

            $pending = $this->store->pendingPages($runId, self::BATCH);
        }

        $score = $this->finish($runId, $token);

        return $this->state($runId, 'done', $score);
    }

    /** @return array{status: string, done: int, total: int, finished: bool, score: ?int} */
    private function state(int $runId, string $status, ?int $score): array
    {
        $progress = $this->store->progress($runId);

        return ['status' => $status, 'done' => $progress['done'], 'total' => $progress['total'], 'finished' => $status !== 'running', 'score' => $score];
    }

    /** @param array{id: int, url: string} $page */
    private function fetchPage(int $runId, array $page, string $token, int $pageCap): void
    {
        $response = $this->crawler->fetch($this->crawlBase() . $page['url'], $token);
        $status = $response['status'];
        $contentType = \strtolower($response['headers']['content-type'] ?? '');
        $isHtml = $status === 200 && \str_contains($contentType, 'html');

        $facts = $isHtml ? PageParser::parse($response['body'], $page['url'], $this->siteHost(), $response['headers']['x-robots-tag'] ?? '') : [];
        if ($isHtml) $this->queueLinks($runId, $facts['links'], $pageCap);

        $this->store->savePage(
            id: (int) $page['id'], status: $status, redirect: $this->redirectTarget($response['headers']['location'] ?? null),
            error: $response['error'], facts: $facts, ms: $response['ms'], bytes: $response['bytes'],
        );
    }

    /** @param string[] $links */
    private function queueLinks(int $runId, array $links, int $pageCap): void
    {
        $total = $this->store->progress($runId)['total'];
        foreach ($links as $link) {
            $full = $total >= $pageCap;
            $tooLong = \strlen($link) > 255;
            if ($full) return;
            if ($tooLong) continue;
            if ($this->store->addPage(runId: $runId, url: $link, source: 'linked', inSitemap: false)) $total++;
        }
    }

    /** A redirect's Location as a path when it stays on the site, otherwise the full address */
    private function redirectTarget(?string $location): ?string
    {
        if ($location === null || $location === '') return null;

        $path = PageParser::internalPath($location, '/', $this->siteHost());
        $query = \parse_url($location, PHP_URL_QUERY);

        return $path === null ? \mb_substr($location, 0, 500) : $path . ($query ? '?' . $query : '');
    }

    private function siteHost(): string
    {
        return (string) \parse_url($this->siteUrl, PHP_URL_HOST);
    }

    private function finish(int $runId, string $token): int
    {
        $pages = $this->store->pages($runId);
        $context = [
            'thresholds' => $this->settings['thresholds'],
            'baseUrl' => $this->siteUrl,
            'isLive' => $this->isLive,
            'robotsDisallow' => $this->isLive ? $this->robotsRules($token) : [],
        ];

        $findings = Checks::run($pages, $context);
        $score = Scoring::score($findings, \count($pages));
        $this->store->addFindings($runId, $findings);
        $this->store->finishRun(id: $runId, status: 'done', pages: \count($pages), score: $score);

        return $score;
    }

    /** @return string[] */
    private function robotsRules(string $token): array
    {
        $response = $this->crawler->fetch($this->crawlBase() . '/robots.txt', $token);
        $found = $response['status'] === 200;

        return $found ? Checks::parseRobotsDisallow($response['body']) : [];
    }
}
