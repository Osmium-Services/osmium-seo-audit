# Osmium SEO Audit

Audits the whole site on demand and lists the fixes from Critical down to Low, with a 0-100 score to track between runs.

Admin: Traffic > SEO Audit (report), Runs (history and what changed), Settings (page limit, delay, crawl address, thresholds).

## How it works

- **Pages**: the home page and every live, non-redirected page from Pages, then any internal link found on the way, up to the page limit.
- **Fetching**: plain HTTP to the site's own address (or the Crawl address setting), no redirects followed, no JavaScript run. Admin presses Run audit and the page calls the `step` action repeatedly, five pages at a time, so no background process is needed. The last step runs the checks.
- **Its own traffic stays out of stats**: every request sends `User-Agent: OsmiumSEOAudit/1.0 (bot)` and an `X-Osmium-Audit` header holding a random per-run token. Osmium Analytics skips bots, the JS trackers never run, and core's redirect/404 log asks this service (hook `request.internal`) whether the token belongs to the run in progress, then skips logging.
- **Checks**: see `models/Checks.php` (`CATALOGUE` holds each check's label, severity and fix text). Not included: Core Web Vitals, backlinks, rankings.
- **Dev**: the PHP container cannot reach `localhost:8300`; set Crawl address to `http://localhost`.

## Data

`seo_audit_runs`, `seo_audit_pages`, `seo_audit_findings` (prefixed). Uninstalling keeps them. Settings: `app/config/services/osmium-seo-audit.json.php`.
