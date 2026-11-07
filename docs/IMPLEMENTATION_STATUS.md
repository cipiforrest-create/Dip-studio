# Audit → implementation status

✅ = automated in this repo · 🛠 = tool provided, one manual run/review needed · ✍️ = manual (content/owner data/off-site)

## Checklist §6 (in audit order)

| # | Audit item | Status | How |
| --- | --- | --- | --- |
| 1 | Eliminate `/stagging/`, fix cross-environment links | ✅ | Done: live moved to the web root; /stagging/ is now a separate staging install (see re-audit #1 below for deindexing). Output URL rewrite kept as a safety net |
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

## Re-audit, November 2026 — 13-step action plan (plugin 1.4.0)

Only findings about dipstudio.ba itself are used. /stagging/ is a separate staging install; the live
site runs from the web root.

| # | Action | Status | How |
| --- | --- | --- | --- |
| 1 | Deindex /stagging/ + "Hello world!" | 🛠 ✍️ | `server/apache-root.htaccess` sends `X-Robots-Tag: noindex, nofollow` for /stagging/ (or password-protect it); Search Console → Removals; delete "Hello world!" in the staging admin. Staging's "Search engine visibility" is already off |
| 2 | Restore 7 inaccessible URLs | ✅ ✍️ | 302 fallbacks while they 404 (hubs → /usluge/, /paketi/ → /usluge/#cjenovnik, 360 booth → /usluge/photobooth/). ✍️ Publish the pages; if they fail with 5xx instead of 404, check hosting error logs |
| 3 | 301 date-prefixed blog URLs | ✅ | Plugin + `server/` rule; internal links rewritten (`link_fix`) |
| 4 | Purge stale menu caches | ✍️ | Purge SpeedyCache + Cloudflare; `wp dip-seo cleanup` if WP-CLI is available |
| 5 | Stuffed H1 | ✅ ✍️ | Service pages: `h1_split`. ✍️ Homepage: add a keyword subheading under "STVARAMO TRENUTKE" |
| 6 | Duplicate FAQ / related sections | ✍️ | Delete "Dodatna pitanja prije dogovora" + "Povezane mogućnosti" on hladne-prskalice, niski-dim-za-prvi-ples, photobooth |
| 7 | KM price block on all service pages | ✅ | `price_block` (from the live /usluge/ table) + 1.3.0 `price_copy` |
| 8 | Decouple Photobooth / 360 Video Booth | ✍️ | Publish /usluge/360-video-booth/; rewrite the Photobooth H1/intro to cover only Photobooth |
| 9 | Internal links + compound slugs | ✅ ✍️ | `link_fix`. ✍️ Change the slug of the two compound services in the editor (WordPress then redirects the old URL itself) |
| 10 | Gallery media library | ✅ 🛠 | 1.3.0 hides duplicates/system/AI images; alt tool now also fixes Media titles. ✍️ H2 headings on /galerija/ |
| 11 | llms.txt / robots.txt / sitemap | ✅ | robots.txt with AI bots incl. Applebot-Extended; llms.txt with live prices (switch "llms") |
| 12 | /kontakt/, /o-nama/, hub FAQs, blog | ✅ ✍️ | `kontakt_nap`, `review_block`, BlogPosting schema. ✍️ /o-nama/ H2 sections, FAQs on hub pages |
| 13 | Google Business Profile, citations, photographer | ✅ ✍️ | `person_schema`. ✍️ GBP, directory listings, embed Ajla's portfolio on the page |

Still needs the owner: street address and working hours (`inc/services.php`), Google Business Profile.
