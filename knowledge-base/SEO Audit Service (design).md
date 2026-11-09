# SEO Audit Service (design)

Status (2026-10-31): **design only**, nothing built. Proposed service id `osmium-seo-audit`, category tools. Draft for
Gavin's review.

## Why

Core already makes the SEO basics (`/sitemap.xml`, `/robots.txt`, Page Titles settings, per-page meta description and
robots, redirects). Nothing tells a site owner what is wrong or what to fix first. Goal: one admin page that audits the
whole site and lists fixes ranked by priority.

## Split of responsibility

- **Service**: crawler, checks, results tables, admin report. No theme markup, no front-end output.
- **Core**: no changes planned. The audit reads what core already exposes (Pages table, sitemap entries, redirects,
  settings). If a check needs a hook, add it then.

## How it audits

It fetches the site's **own live pages over HTTP**, starting from the visitor-facing page list (not a link-follow
crawl), so it sees exactly what Google sees: theme output, service-injected tags, status codes and redirects. Same-origin
only, polite delay, capped page count, runs as a background job (admin button starts it, progress shown, report kept).
Each run is stored so runs can be compared.

## Keeping its own traffic out of stats

The audit's requests must not count anywhere.

- Every audit request sends `User-Agent: OsmiumSEOAudit/1.0 (bot)` and a header `X-Osmium-Audit: <token>`. The token is
  random per run, stored by the service, and only valid while that run is active, so nobody can spoof it to hide
  their own traffic.
- Osmium Analytics already skips any User-Agent containing `bot`, and the JS trackers (GA4, Clarity, Meta Pixel) never
  run because the audit does not execute JavaScript. That makes this part safe even without a core change.
- **Core change needed**: redirect and 404 logging (`redirectlog->add()` in `OsmiumController` and `RedirectHandler`)
  counts every hit, and the audit deliberately requests redirected and missing URLs. Core gets one small check, "is
  this a valid audit request?", that those call sites use to skip logging. The check asks the service through a hook
  (`request.internal`, handle-style) so core knows nothing about the token. Other services that count requests can use
  it too.
- On-demand only (decided 2026-10-31).

## Checks (first version)

| Group | Checks |
|---|---|
| Indexing | non-200 pages in the sitemap, redirected URLs in the sitemap, noindex pages in the sitemap, missing/wrong canonical, robots.txt blocking indexable pages |
| Titles and descriptions | missing, too long/short, duplicated across pages |
| Headings | missing or multiple `h1`, skipped levels |
| Content | thin pages (word count), images without `alt`, duplicate content (same body hash) |
| Links | broken internal links, links to redirected URLs, orphan pages (in sitemap, no internal links in) |
| Social and schema | missing `og:title`/`og:image`, JSON-LD that does not parse |
| Technical | HTTPS and `www` consistency, redirect chains, missing viewport, slow response, page weight |

Not in v1: Core Web Vitals (needs a real browser or the PageSpeed API), backlinks, keyword rankings. Those need
third-party data, so they stay out until there is a concrete reason.

## Priority

Every finding gets a severity: **Critical** (stops indexing), **High** (hurts ranking or clicks), **Medium**, **Low**.
The report groups by severity, then by check, each showing affected-page count, the pages, and one plain-English
"how to fix" line with a link to the admin screen where it is fixed (Pages edit, Redirects, Page Titles). A site score
(0-100) is shown for tracking runs, weighted by severity and share of pages affected.

## Admin

Own menu item. Pages: **Report** (latest run, severity summary, grouped findings, filter by severity/check), **Runs**
(history, diff vs the previous run: fixed, new, unchanged), **Settings** (page cap, request delay, which checks are on,
thresholds for title/description length). Lists use ListView.

## Schema (all names prefixed `{PREFIX}`)

- `seo_audit_runs`: id, started_at, finished_at, status, pages_checked, score
- `seo_audit_pages`: run_id, url, status_code, redirect_target, title, description, h1_count, word_count, canonical,
  robots, body_hash, response_ms, bytes
- `seo_audit_findings`: run_id, check_key, severity, page_url, detail

## Open questions

1. Dev and staging have no public sitemap (live only). Proposed: the audit builds its page list from the Pages table
   directly, so it works on any environment, and flags "environment is not live" as context.
2. MCP tools (start audit, read report) like Blog and FAQs have? Proposed: later, not v1.
