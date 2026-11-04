# DIP Studio – SEO/AEO fixes for dipstudio.ba

Implementation of the code-level items from the *Technical, Content and AEO/GEO/SEO Audit* of
https://dipstudio.ba/. The site is WordPress, so the fixes ship as a regular **WordPress plugin** plus
server rules. Content work that needs the owner's real data (prices, specs, address) is listed in
[`docs/IMPLEMENTATION_STATUS.md`](docs/IMPLEMENTATION_STATUS.md).

## What's here

| Path | Purpose |
| --- | --- |
| `wordpress/wp-content/plugins/dip-seo-fixes/` | The plugin (`./build.sh` → `dist/dip-seo-fixes.zip`) |
| `server/apache-root.htaccess` / `server/nginx.conf` | 410 for `/stagging/`, 301 for date-prefixed posts at server level |
| `tests/run.php` | Tests for the pure helpers (`php tests/run.php`) |
| `docs/IMPLEMENTATION_STATUS.md` | Every audit item → done here / manual step |

### What the plugin does (automatically, once activated)

- **`/stagging/*` and `/staging/*` → 410 Gone** (audit §1.1 #1).
- **Rewrites leaked `/stagging/wp-content/…` asset URLs** in page output to `/wp-content/…` (e.g. the logo on `/vjencanja/`). A safety net — run the DB search-replace below as well.
- **301 `/2026/10/24/slug/` → `/slug/`** and date archives → `/blog/` (§1.1 #3, §5.1).
- **404 fallbacks**: `/projekti/` → `/galerija/` (301), `/organizacija-dogadjaja/` → `/usluge/organizacija-i-koordinacija/` (301), and the 4 category hubs → `/usluge/` (302) until their pages are published. A fallback switches itself off as soon as a real page exists at that URL.
- **Heading tokenization fix**: inserts a space before `<br>` inside `<h1>`–`<h6>` so "STVARAMO<br>TRENUTKE" is no longer read as "STVARAMOTRENUTKE" (§1.2). No visual change.
- **Duplicate lead sentence on blog posts**: hides the theme's excerpt when the post body already starts with it (§2.5 #1). Meta descriptions built from the excerpt are unaffected.
- **`/robots.txt`** with explicit AI-crawler allows and the sitemap (§5.3).
- **`/llms.txt`** entity summary with all 23 services (§5.3).
- **JSON-LD `@graph`**: EntertainmentBusiness/LocalBusiness + 23-service OfferCatalog + 36 cities + WebSite on every page, `Service` on each `/usluge/*` page, `WebApplication` on `/konfigurator/` (§5.2).

### WP-CLI tools

```bash
wp dip-seo check                    # pass/fail against the audit's critical findings
wp dip-seo create-hubs              # draft pages: /specijalni-efekti/ /rasvjeta/ /vatrometi/ /photo-fun/ /blog/
wp dip-seo create-hubs --publish    # …or publish straight away (also sets /blog/ as the posts page)
wp dip-seo gallery-alt              # dry run: lists leaked AI prompts, slug titles, hashes, UI icons
wp dip-seo gallery-alt --apply      # writes the audit's Bosnian alt text for the leaked prompts
```

## Install

1. **Back up** files + database.
2. Run `./build.sh` (or use the zip from the release), then in WordPress go to
   **Plugins → Add New → Upload Plugin**, upload `dip-seo-fixes.zip` and click **Activate**.
   Do not upload the whole repository zip — WordPress rejects it ("Nisu pronađeni ispravni plugini").
3. Fix the staging URLs stored in the database (dry run first):
   ```bash
   wp search-replace 'https://dipstudio.ba/stagging/wp-content/' 'https://dipstudio.ba/wp-content/' --all-tables --dry-run
   wp search-replace 'https://dipstudio.ba/stagging/wp-content/' 'https://dipstudio.ba/wp-content/' --all-tables
   ```
   Then delete the `/stagging/` folder from the web root.
4. Add the server rules from `server/` (Apache: top of the root `.htaccess`; nginx: the `server` block).
   This makes the 410 work even while the staging folder still exists.
5. Delete any physical `robots.txt` in the web root (otherwise WordPress can't serve the new one).
6. In **SiteSEO**: turn off its own Organization/Knowledge-Graph schema (this plugin outputs it),
   keep **Breadcrumbs** on, set date/author/tag archives to `noindex`, and apply the title templates
   from audit §5.1.
7. `wp dip-seo create-hubs`, review the drafts, publish, then `wp dip-seo gallery-alt --apply`.
8. `wp dip-seo check` until everything is green, then validate a few URLs in
   [Rich Results Test](https://search.google.com/test/rich-results) and request re-indexing in
   Search Console.

## Configuration

- `define( 'DIP_SEO_DISABLE_SCHEMA', true );` in `wp-config.php` turns the JSON-LD off.
- Business facts (phone, e-mail, **street address**), categories, services and cities live in
  `dip-seo-fixes/inc/services.php` — one place feeds the schema, hub pages and checks.
- Filters: `dip_seo_robots_txt`, `dip_seo_404_fallbacks`, `dip_seo_schema_graph`,
  `dip_seo_meta_title_key` / `dip_seo_meta_desc_key` (SiteSEO post-meta keys used by `create-hubs`;
  defaults `_siteseo_titles_title` / `_siteseo_titles_desc` — confirm against your SiteSEO version).

## Deliberate deviations from the audit

- **`/stagging/` is not `Disallow`ed in robots.txt.** Blocking it would stop Google from re-crawling
  those URLs, so it would never see the 410 and the stale URLs would stay indexed longer.
- **No FAQPage or price markup yet.** FAQ schema must match visible answers, and the audit itself
  says its figures (e.g. "4 units", "3–5 minutes") are competitor benchmarks to be calibrated to
  DIP Studio's own equipment first. Add them once the copy is rewritten.
- **No street address** in schema/llms.txt — it isn't published anywhere yet; add it in
  `services.php` when available.
- **No hub page for "Organizacija i koordinacija"** — it has one service, so the audit's redirect
  option is used.
- **BreadcrumbList is left to SiteSEO** to avoid duplicate breadcrumb markup.
- `WebApplication.applicationCategory` uses `LifestyleApplication` (a recognised value) instead of
  the audit's non-standard `EventPlanningApplication`.
