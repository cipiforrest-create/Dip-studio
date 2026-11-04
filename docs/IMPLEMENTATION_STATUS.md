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
