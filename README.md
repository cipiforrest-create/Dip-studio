# DIP Studio – SEO/AEO fixes for dipstudio.ba

Implementation of the code-level items from the *Technical, Content and AEO/GEO/SEO Audit* of
https://dipstudio.ba/ and the **November 2026 Re-Audit** (v1.1.0). The site is WordPress, so the fixes ship as a regular **WordPress plugin** plus
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
- **404 fallbacks**: `/projekti/` → `/galerija/` (301), `/organizacija-dogadjaja/` → `/usluge/organizacija-i-koordinacija/` (301), the 4 category hubs → `/usluge/` (302), `/paketi/` → `/usluge/#cjenovnik` (302) and `/usluge/360-video-booth/` → `/usluge/photobooth/` (302) until their pages are published. A fallback switches itself off as soon as a real page exists at that URL.
- **Legacy compound slugs**: once renamed (`wp dip-seo rename-legacy-slugs --apply`), `/usluge/rotirajuce-prskalice-vatropad/` → `/usluge/rotirajuce-prskalice/` and `/usluge/vatrena-vjetrenjaca-lepeza/` → `/usluge/vatrena-vjetrenjaca/` (301). Before the rename, the new URLs 302 to the old pages.
- **Internal links** to `/projekti/`, date-prefixed posts and renamed slugs are rewritten in the HTML to their final URL; `id="cjenovnik"` is added to the "Cjenovnik usluga" heading on `/usluge/` so `/usluge/#cjenovnik` works.
- **Service pages (`/usluge/*`)**: a "## Cijena usluge: … X–Y KM" block (same layout as `/usluge/vatropad/`) is added before the FAQ wherever a page has no price heading, "Cijene na upit" is replaced by the published range, and a stuffed `<h1>` (service name + tagline) is split into a clean `<h1>` plus `<p class="dip-h1-lead">`.
- **`/kontakt/`**: NAP block with H2s (DIP Studio, 71000 Sarajevo, phone, e-mail, 3-step booking; street/hours once filled in).
- **Blog posts**: "Stručni pregled sadržaja: DIP Studio Sarajevo" block with published/updated dates.
- **Heading tokenization fix**: inserts a space before `<br>` inside `<h1>`–`<h6>` so "STVARAMO<br>TRENUTKE" is no longer read as "STVARAMOTRENUTKE" (§1.2). No visual change.
- **Duplicate lead sentence on blog posts**: hides the theme's excerpt when the post body already starts with it (§2.5 #1). Meta descriptions built from the excerpt are unaffected.
- **`/robots.txt`** with explicit AI-crawler allows (OAI-SearchBot, ChatGPT-User, PerplexityBot, ClaudeBot, Google-Extended, Applebot-Extended…) and the sitemap.
- **`/llms.txt`** generated from `inc/services.php`: entity summary, 23 services **with KM price ranges**, 36 cities, Upit → Provjera → Dogovor booking steps and contacts.
- **JSON-LD `@graph`**: EntertainmentBusiness/LocalBusiness (priceRange 50–5.000 KM, opening hours once set) + 23-service OfferCatalog with BAM prices + 36 cities + WebSite + Person (Ajla Husagić) on every page, `Service` with `AggregateOffer` on each `/usluge/*` page, `BlogPosting` on posts, `WebApplication` on `/konfigurator/`.

### WP-CLI tools

```bash
wp dip-seo check                          # pass/fail against the audit's critical findings
wp dip-seo audit-pages                    # fetches every audited URL: status, H1, H2s, duplicate FAQ/related, price block, "na upit"
wp dip-seo cleanup                        # trash "Hello world!", flush object/page caches (stale menus)
wp dip-seo create-hubs [--publish]        # /specijalni-efekti/ /rasvjeta/ /vatrometi/ /photo-fun/ /blog/
wp dip-seo create-service-pages           # drafts for missing /usluge/* pages (e.g. 360-video-booth) with price block
wp dip-seo rename-legacy-slugs [--apply]  # rotirajuce-prskalice-vatropad → rotirajuce-prskalice, vatrena-vjetrenjaca-lepeza → vatrena-vjetrenjaca
wp dip-seo gallery-alt                    # dry run: alt + Media Library title/caption/description problems
wp dip-seo gallery-alt --apply            # fixes prompts; copies good Bosnian alts into bad titles/captions/descriptions
wp dip-seo gallery-alt --apply --brand-suffix   # also appends "– DIP Studio Sarajevo" to alts without it
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
8. `wp dip-seo rename-legacy-slugs --apply`, `wp dip-seo create-service-pages` (finish and publish
   the 360 Video Booth draft), then `wp dip-seo cleanup`.
9. `wp dip-seo check` and `wp dip-seo audit-pages` until everything is green, then validate a few URLs in
   [Rich Results Test](https://search.google.com/test/rich-results) and request re-indexing in
   Search Console.

## Configuration

- `define( 'DIP_SEO_DISABLE_SCHEMA', true );` in `wp-config.php` turns the JSON-LD off.
- Prices per service live in `inc/services.php` (from the "Cjenovnik usluga" on `/usluge/`). Change them
  there and the price blocks, schema and llms.txt follow.
- Street address and working hours: fill `street` and `hours` in `dip_seo_business()`.
- Business facts (phone, e-mail, **street address**), categories, services and cities live in
  `dip-seo-fixes/inc/services.php` — one place feeds the schema, hub pages and checks.
- Filters: `dip_seo_robots_txt`, `dip_seo_404_fallbacks`, `dip_seo_schema_graph`, `dip_seo_split_h1`,
  `dip_seo_kontakt_nap`, `dip_seo_post_review_block`, `dip_seo_blogposting_schema` (return `false` to disable),
  `dip_seo_meta_title_key` / `dip_seo_meta_desc_key` (SiteSEO post-meta keys used by `create-hubs`;
  defaults `_siteseo_titles_title` / `_siteseo_titles_desc` — confirm against your SiteSEO version).

## Deliberate deviations from the audit

- **`/stagging/` is not `Disallow`ed in robots.txt.** Blocking it would stop Google from re-crawling
  those URLs, so it would never see the 410 and the stale URLs would stay indexed longer.
- **No FAQPage markup.** Three service pages still render two FAQ sections; FAQ schema must match a
  single visible block. Merge the sections first (`wp dip-seo audit-pages` lists them).
- **No street address or hours invented** — they aren't published on dipstudio.ba yet; add them in
  `services.php` when available. Until then `/kontakt/` says services are performed on location.
- **Blog author** is the organization (DIP Studio), not a named person — no named author is
  published on the site. Change the BlogPosting `author` via `dip_seo_schema_graph` when one is.
- **No hub page for "Organizacija i koordinacija"** — it has one service, so the audit's redirect
  option is used.
- **BreadcrumbList is left to SiteSEO** to avoid duplicate breadcrumb markup.
- `WebApplication.applicationCategory` uses `LifestyleApplication` (a recognised value) instead of
  the audit's non-standard `EventPlanningApplication`.
