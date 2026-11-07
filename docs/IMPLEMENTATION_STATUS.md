# Audit → implementation status

✅ = automated in this repo · 🛠 = tool provided, one manual run/review needed · ✍️ = manual (content/owner data/off-site)

## Checklist §6 (in audit order)

| # | Audit item | Status | How |
| --- | --- | --- | --- |
| 1 | Eliminate `/stagging/`, fix cross-environment links | ✅ 🛠 | 410 via plugin + `server/` rules; output URL rewrite; run `wp search-replace` (README step 3) and delete the folder |
| 2 | Publish the inaccessible hub pages + `/blog/` | 🛠 | `wp dip-seo create-hubs` (4 hubs + /blog/); `/organizacija-dogadjaja/` 301s to its only service. Until published, hubs 302 to `/usluge/` |
| 3a | Unify header/footer navigation (Template A vs B) | ✍️ | Theme/menu change: assign the 23-service menu to every template, incl. `/usluge/photobooth/` and single posts |
| 3b | 301 date-prefixed posts → `/%postname%/` | ✅ | Plugin + `server/` rules; `wp dip-seo check` verifies permalink setting |
| 4 | De-cannibalise service pairs | ✍️ | Rotirajuće prskalice ↔ Vatropad, Vatrena vjetrenjača ↔ Lepeza, Photobooth ↔ 360 Video Booth: rewrite titles/H1/copy so each URL targets one service (targets in audit §2.4) |
| 5 | Remediate gallery media | 🛠 ✍️ | `wp dip-seo gallery-alt --apply` fixes the 12 leaked prompts; `--include-slugs` for the 29 slug titles after review; remove the 2 UI icons from the gallery block and rename the 2 hash files by hand |
| 6 | SiteSEO Pro, JSON-LD, robots.txt, llms.txt | ✅ ✍️ | Schema/robots/llms automated; SiteSEO title templates + archive noindex are dashboard settings (README step 6) |
| 7 | Quantitative specs, price anchors, address, GBP, OLX, reviews | ✍️ | See "Content & off-site" below |

## Other findings

| Audit § | Finding | Status |
| --- | --- | --- |
| 1.2 | Headings concatenated around `<br>` | ✅ space inserted before `<br>` in h1–h6 |
| 2.1 `/` | H1 without keywords; "25+ gradova" vs 36 listed; Review schema for 18 testimonials | ✍️ change H1/counter in the page builder. Review markup intentionally not added: Google ignores self-serving LocalBusiness reviews |
| 2.1 `/o-nama/` | ~85 words, no H2s | ✍️ expand to 600+ words (story, team, Ajla Husagić bio, safety standards, address) |
| 2.1 `/kontakt/` | No address/hours/map | ✍️ add street, 71000, hours, map; fill `street` in `inc/services.php` |
| 2.1 `/konfigurator/` | Thin utility page | ✅ WebApplication schema · ✍️ add ~350 words of guidance below the tool |
| 2.1 `/paketi/` | No named packages/prices | ✍️ 4 named packages with "od X KM" anchors |
| 2.1 `/kako-rezervisati/` | Not in nav | ✍️ add to footer "Brzi linkovi"; HowTo/FAQ copy |
| 2.1 `/projekti/` | Orphan, overlaps gallery | ✅ 301 → `/galerija/` (fallback; disabled automatically if a real page is published) |
| 2.2 | Event pages: no H3s/FAQs | ✍️ per-page plans in audit §2.2 |
| 2.3 | `/usluge/` hub | ✍️ H1, comparison matrix, 5 FAQs |
| 2.4 | 23 service pages: boilerplate, evasive FAQs | ✍️ rewrite with verified specs; then add FAQPage JSON-LD via the `dip_seo_schema_graph` filter |
| 2.5 #1 | Duplicate opening sentence | ✅ |
| 2.5 #2–3 | Liability-heavy tone; no author/reviewer byline | ✍️ add author + "Stručni pregled" block in the post template |
| 3–4 | Citability, NAP, Google Business Profile, OLX.ba shop, review workflow, more social profiles | ✍️ off-site; add new profile URLs to `same_as` in `inc/services.php` |

## Content & off-site (owner input required)

These need facts only DIP Studio has; the audit's numbers are competitor benchmarks, not DIP's:

- Starting prices ("od X KM") per service and package.
- Equipment specs: fog machine type + duration, spark height/duration/unit counts, 360 platform
  diameter/capacity, rental hours, etc.
- Full street address and working hours (NAP), Google Business Profile verification.
- Two-sentence direct answers for every FAQ (number first, customisation second).

## Re-audit, November 2026 — 13-step action plan

Only findings about dipstudio.ba itself are implemented; third-party benchmark sites in the report
are not used as data.

| # | Action | Status | How |
| --- | --- | --- | --- |
| 1 | Deindex/remove `/stagging/` + "Hello world!" | ✅ 🛠 | 410 + `X-Robots-Tag: noindex, nofollow` (plugin + `server/`); not disallowed in robots.txt so Google sees the 410. ✍️ Delete the folder (removes its own "Hello world!" post), request URL-prefix removal in Search Console. `wp dip-seo cleanup` trashes a "Hello world!" in the production DB |
| 2 | Restore 7 inaccessible URLs | ✅ 🛠 | 404 fallbacks for all 7 (`/paketi/` → `/usluge/#cjenovnik`, `/usluge/360-video-booth/` → `/usluge/photobooth/`, hubs → `/usluge/`); `create-hubs`, `create-service-pages` for real pages. ✍️ If they fail with 5xx/connection errors rather than 404, that's hosting — check server error logs |
| 3 | 301 date-prefixed blog URLs | ✅ | Already in v1.0 (plugin + `server/`); internal links to them are now rewritten too |
| 4 | Purge stale template/menu caches | 🛠 | `wp dip-seo cleanup` (object cache, WP Rocket, W3TC, Super Cache, LiteSpeed, Autoptimize, SiteGround). ✍️ Purge CDN; make sure single posts use the same menu location |
| 5 | Stuffed `<h1>` | ✅ ✍️ | `/usluge/*`: H1 split into name + `<p class="dip-h1-lead">` at render time. ✍️ Homepage: add a keyword subheading under "STVARAMO TRENUTKE" in the page builder, e.g. *Specijalni efekti, rasvjeta i atrakcije za vjenčanja i događaje u Sarajevu i BiH* |
| 6 | Merge duplicate FAQ / related sections | ✍️ 🛠 | `/usluge/hladne-prskalice/`, `/usluge/niski-dim-za-prvi-ples/`, `/usluge/photobooth/`: delete the legacy "Dodatna pitanja prije dogovora" and "Povezane mogućnosti" blocks in the editor. `audit-pages` flags any page with >1 |
| 7 | KM price block on all service pages | ✅ | Price block + "Cijene na upit" replacement from the published /usluge/ price table |
| 8 | Decouple Photobooth / 360 Video Booth | 🛠 ✍️ | `create-service-pages` drafts `/usluge/360-video-booth/` (200–500 KM); ✍️ rewrite `/usluge/photobooth/` H1/intro/"Šta dobijate" to cover only the photobooth |
| 9 | Internal links + compound slugs | ✅ 🛠 | `/projekti/` links rewritten to `/galerija/`; `#cjenovnik` id ensured; `rename-legacy-slugs --apply` + automatic 301s |
| 10 | Gallery media library | 🛠 ✍️ | `gallery-alt --apply` fixes alt + title + caption + description (prompts, 29 slug titles, 2 hash titles); `--brand-suffix` for alts #32, #34–#60. ✍️ Remove "Website Header Preview" and "Apple Touch Icon" from the gallery; add H2 category headings on `/galerija/` |
| 11 | llms.txt, robots.txt, sitemap | ✅ | llms.txt with 23 KM prices/36 cities/booking steps; Applebot(-Extended) added; date-prefixed URLs aren't in WP sitemaps with `/%postname%/`. ✍️ Remove the 7 URLs from any manually curated sitemap until they return 200 |
| 12 | `/kontakt/`, `/o-nama/`, hub FAQs, blog | ✅ ✍️ | `/kontakt/` NAP + booking H2 block; reviewer block + dates on every post; BlogPosting schema. ✍️ `/o-nama/` H2 sections; FAQ blocks on `/usluge/`, `/vjencanja/`, `/korporativni-dogadjaji/`, `/proslave/`, `/kako-rezervisati/`; `/blog/` post titles as `<h2>` (theme template) |
| 13 | Google Business Profile, citations, photographer portfolio | ✍️ | Off-site. Person schema for Ajla Husagić added (sameAs her portfolio); ✍️ embed a portfolio gallery on `/usluge/profesionalni-fotograf/` |

Still needs the owner: street address and working hours (`inc/services.php`), GBP verification.
