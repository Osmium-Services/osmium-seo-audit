# Osmium SEO Audit

Audits the whole site on demand and lists the fixes from Critical down to Low, with a 0-100 score to track between runs.

Admin: Service Settings > SEO Audit (report, with Download report: the run as a Markdown file to hand to an assistant for suggested fixes), Runs (history and what changed), Settings (page limit, delay, crawl address, thresholds).

## What was checked

The report opens with a table of every check and its result, and the Markdown download and the `seo_audit_latest` MCP tool (`checks` list) carry the same rows.

- **Fail**: the check found something. The detail says how many pages are affected.
- **Pass**: the check ran and found nothing. The detail says what it covered, for example `48 pages tested, flagged when longer than 60 characters`.
- **Skipped**: the check could not run, with the reason. Off live, `blocked_by_robots`, `robots_missing`, `sitemap_missing` and `no_https` are skipped (`Skipped: runs on live only`); `blocked_by_robots` is also skipped when robots.txt could not be read. `mixed_content` is skipped when the site address is not https. A check with nothing to test (no redirects, no images, no noindex pages) is skipped too. An icon hosted on another site is not probed.
- **Unknown**: the run was saved before coverage was recorded, so only its failures are known.

Coverage is recorded with each run (`runs.coverage`, migration 006) by `Checks::coverage()`, and turned into rows by `CheckSummary`.

## How it works

- **Pages**: the home page and every live, non-redirected page from Pages, then any internal link found on the way, up to the page limit.
- **Fetching**: plain HTTP to the site's own address (or the Crawl address setting), no redirects followed, no JavaScript run. Admin presses Run audit and the page calls the `step` action repeatedly, five pages at a time, so no background process is needed. The last step runs the checks.
- **Its own traffic stays out of stats**: every request sends `User-Agent: OsmiumSEOAudit/1.0 (bot)` and an `X-Osmium-Audit` header holding a random per-run token. Osmium Analytics skips bots, the JS trackers never run, and core's redirect/404 log asks this service (hook `request.internal`) whether the token belongs to the run in progress, then skips logging.
- **Checks**: see `models/Checks.php` (`CATALOGUE` holds each check's label, severity and fix text). Not included: Core Web Vitals, backlinks, rankings.
- **Dev**: the PHP container cannot reach `localhost:8300`; set Crawl address to `http://localhost`.

## Data

`seo_audit_runs`, `seo_audit_pages`, `seo_audit_findings` (prefixed). Uninstalling keeps them. Settings: `app/config/services/osmium-seo-audit.json.php`.
