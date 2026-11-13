# dipstudio.ba — SEO / AEO / GEO analysis and implementation plan

Snapshot date: **2026-11-13**. Written for an implementing agent (a smaller model) plus the site owner.
Read sections 0 and 6 before doing anything; sections 1–5 are the evidence behind the backlog.

---

## 0. Rules for the implementing agent

**Where the live site's code is (important — the repo is not the live site):**

- Live WordPress 7.1.4, theme `dip-studio-cinematic` 1.2.0, custom plugins `dip-studio-core` 1.1.7,
  `dip-studio-seo` 1.6.2, `dip-studio-importer` 4.2.2, plus SiteSEO Pro, Site Kit, SpeedyCache, Docket Cache.
  **None of that source is in this repository.**
- This repo's plugin `dip-seo-fixes` is **not installed on the live site** (verified in the plugin list).
  Do **not** install or activate it: the live `dip-studio-seo` plugin already outputs schema, robots.txt
  and llms.txt, and a second copy would duplicate them. Treat `wordpress/` here as reference only.
- Services are a custom post type `dip_service` (`/usluge/<slug>/`). Blog posts are normal posts at `/<slug>/`.

**How to change the live site:** use the WP Agent connector, `site_id` `992cf213-3bd7-4aa0-b4db-eb6875a35da8`
(`get_post_content`, `patch_post_content`, `update_post_seo`, `update_post_title`, `create_post`,
`search_posts`, `list_pages`, `rest_api_read`).

**Hard rules**

1. **New pages are created as `draft` only.** The owner publishes.
2. **Edits to already-published pages** are limited to what a task lists (title, meta description, an added
   section, an added link). One page per call, re-fetch the page afterwards and confirm it still returns 200
   and the old content is intact. Never replace a whole page body.
3. Before the first content edit, run `get_post_content` on `/usluge/photobooth/`. If the visible page text is
   **not** in `post_content` (it is rendered from theme templates/meta), stop content-edit tasks and write the
   prepared text to `content/<slug>.md` in this repo for a developer instead. Report which tasks were diverted.
4. **Never invent facts**: prices, equipment specs, durations, years, venue names, client names, reviews.
   Prices come only from the live table on `https://dipstudio.ba/usluge/`. Anything else unknown is written as
   `[[OWNER: question]]` and the page stays a draft.
5. Language: Bosnian (ijekavica, Latin script, `bs-BA`), same tone as existing pages. Use both "vjenčanje" and
   "svadba" (people search "svadbe" — see 2.1).
6. Titles ≤ 60 characters, meta descriptions 120–155. Check with `python3 -c "print(len('…'))"`.
7. Do not change any URL slug. Do not add `Review`/`AggregateRating` schema for the site's own testimonials.
8. Do not rewrite a title that was changed in the last 14 days unless a task says so — the site is 3.5 weeks old
   and Google is still settling.
9. After each task, tick it in section 6 and add one line to `docs/CHANGELOG_SEO.md`
   (`date | task id | URL | what changed`).

---

## 1. Situation in short

- The site was first seen by Google on **2026-10-20**. In 23 days: **53 clicks, 1,110 impressions**, average
  position ≈ 6, daily impressions rising (22 → 136). Source: Search Console via Site Kit (section 2.1).
- On-page work from the earlier audits is **done**: all 73 sitemap URLs return 200 with self-canonicals, prices
  are on every service page, FAQ + Service + LocalBusiness + Breadcrumb JSON-LD is everywhere, the cannibal
  slugs and date URLs 301 correctly, AI crawlers are allowed, llms.txt exists.
- So the bottleneck is no longer the pages. It is, in this order:
  1. **Authority is zero.** Ahrefs Domain Rating 0. Every direct competitor is also below DR 10, so a small
     number of real links and citations moves the whole site.
  2. **The brand query is not owned.** "dip studio" is the largest query (223 impressions, 20% of all) and the
     site averages position **3.5** with 4.5% CTR. A site should be #1 with 40%+ CTR for its own name.
  3. **Demand coverage is narrow.** Whole query families have zero text on the site (section 4).
  4. **Google ranks the wrong page** for three head terms (section 2.2).
- The existing strategy documents (≈20 audits in Drive, 4–12 Nov) were produced without Search Console data and
  keep re-auditing on-page details. Section 5 says what to stop and what to add.

---

## 2. Baseline

### 2.1 Search Console (measured, 2026-10-20 → 2026-11-11)

| Query | Impr. | Clicks | Avg pos | Note |
| --- | ---: | ---: | ---: | --- |
| dip studio | 223 | 10 | 3.5 | brand, not #1 |
| vatromet za tortu | 19 | 0 | 6.7 | no page targets it directly |
| dim za prvi ples | 18 | 0 | 17.0 | head term, page 2 |
| niski dim za prvi ples | 16 | 0 | 15.6 | head term, page 2 |
| dip (+ "dip kontakt", "dip radno vrijeme") | 23 | 0 | 2–6 | ambiguous brand |
| ambijentalna rasvjeta | 11 | 1 | 6.3 | |
| dnevni vatromet | 11 | 1 | 4.6 | |
| vatromet u boji | 10 | 1 | 6.5 | |
| photobooth | 10 | 0 | 7.8 | |
| specijalni efekti za svadbe | 7 | 0 | 12.4 | "svadbe" wording under-used |
| dim za svadbe | 6 | 0 | 6.5 | |
| photo booth sarajevo / photobooth sarajevo | 9 | 0 | 5–7.6 | |
| niski dim za svadbe | 1 | 0 | 52 | |

| Page | Impr. | Clicks | Avg pos | CTR |
| --- | ---: | ---: | ---: | ---: |
| / | 178 | 23 | 4.2 | 12.9% |
| /usluge/photobooth/ | 86 | 5 | 7.1 | 5.8% |
| /niski-dim-ili-hladne-prskalice-…/ (blog, old + new URL) | 75 | 2 | 10–15 | 2.7% |
| /kontakt/ | 71 | 1 | 3.9 | 1.4% |
| /usluge/ | 63 | 2 | 5.1 | 3.2% |
| /usluge/niski-dim-za-prvi-ples/ | 33 | 1 | 6.6 | 3.0% |
| /usluge/vatromet-u-boji/ | 22 | 1 | 5.1 | 4.5% |

Also still getting impressions: `/stagging/2026/10/19/hello-world/` (11), `/author/admin/`, `/category/uncategorized/`.
Countries: Bosnia is the bulk; Croatia 48 impressions at position 13.7; Austria + Germany + UK 11 in total.

### 2.2 Rank snapshot (search API, location Bosnia, 2026-11-13)

Not Google's exact order and not personalised — use it for "who is around us", and GSC for positions.

| Query | DIP position | Who is above / notes |
| --- | --- | --- |
| hladne prskalice sarajevo | **1** (+5) | — |
| vatromet za vjenčanje sarajevo cijena | **1** | faktor.ba article, santo.ba profile below |
| niski dim za prvi ples sarajevo | **2** (+6) | an Instagram profile is #1 |
| specijalni efekti za vjenčanje sarajevo | **2** (+9) | a Facebook page (Showtime) is #1 |
| niski dim i prskalice … tuzla | 3 (blog post) | Instagram profile from Tuzla #1 |
| gender reveal sarajevo | 4 | balloon shops |
| specijalni efekti za vjenčanje zenica | 4 (home) | local Facebook pages |
| audio guestbook vjenčanje BiH | 5 | first Bosnian result; Croatian sites above |
| rasvjeta za vjenčanje sarajevo | 5, with **/vjencanja/** | /rasvjeta/ hub does not appear — wrong page |
| zvjezdano nebo vjenčanje sarajevo | 6 | Novex holds 7 of the top 10 (3 domains + IG + FB + OLX) |
| organizacija vjenčanja sarajevo | 7 | setly.eu, organizacijavjencanja.com |
| foto ogledalo sarajevo najam | 8 | festivo.ba vendor page #1, Golden Hour ×5 |
| 360 video booth sarajevo | 9, with **/usluge/photobooth/** | 360 page does not appear — wrong page |
| photobooth sarajevo | not in top 10 | Reddit, photobox.ba, goldenhour.ba, photozone.ba, festivo.ba |
| photobooth mostar | not in top 8 | photozone.ba, fotkalo.ba |
| efekti za prvi ples (no city) | not in top 8 | Croatian/Serbian sites |
| fotograf za vjenčanje sarajevo | not in top 10 | photographers, setly.eu, bonjour.ba |
| korporativni događaji / team building sarajevo | not in top 8 | agencies |
| koliko košta svadba u BiH | not in top 8 | bonjour.ba, recida.ba, news portals |
| wedding planner Sarajevo (English) | not in top 8 | meetbosnia.com, planners |

Pattern: **Instagram, Facebook, OLX and directories take 4–7 of every top 10.** Ranking the website alone is not
enough; DIP has to be present on those surfaces too (section 6, group B).

### 2.3 Authority (Ahrefs Domain Rating, free endpoint)

dipstudio.ba **0** · studionovex.com / novexlight.com / dekoracijezavjencanje.com **9** · goldenhour.ba 2.2 ·
photozone.ba 0.6 · photobox.ba 0.4 · fotkalo.ba 0 · organizacijavjencanja.com 0 · eventsolutions.ba 0.
Platforms: bonjour.ba **44** · setly.eu 12 · festivo.ba 7 · udajemse.com 0 · recida.ba 0.

### 2.4 Technical crawl (all 73 sitemap URLs + 13 probes)

Healthy: status codes, canonicals, `lang="bs-BA"`, one H1 per page, no duplicate titles, no title over 60,
images lazy-loaded with dimensions and srcset, hero preloaded, 0 render-blocking stylesheets, GA + GSC present.

Issues found (each has a task in section 6):

| # | Finding | Evidence |
| --- | --- | --- |
| 1 | `/stagging/*` 301s to the homepage instead of 410; a staging URL still gets impressions | curl; GSC |
| 2 | `/projekti/` is in the sitemap but canonicalises to `/galerija/`; 0 internal links | crawl |
| 3 | Fact conflict: homepage and llms.txt say "25+ gradova", homepage lists 36 cities, schema has 37 `areaServed` | crawl |
| 4 | LocalBusiness schema: `description` is just "DIP Studio", `image` is the favicon, hours are 00:00–23:59 every day, `sameAs` has only Facebook + Instagram (TikTok and udajemse profile exist) | JSON-LD |
| 5 | Blog `author` is a Person named "DIP Studio"; no named human anywhere on posts | JSON-LD, page text |
| 6 | 4 blog posts have 0 in-content links pointing to them; 12 more have ≤ 2 | link graph |
| 7 | 20 indexable pages have < 500 words of main content (mostly blog posts ≈ 450 words) | crawl |
| 8 | 20 of 23 service titles say "cijena" without the number; `/usluge/` CTR is 3.2% at position 5 | titles; GSC |
| 9 | `/cjenovnik/` and `/recenzije/` return 404 (natural URLs people and AI assistants try) | curl |
| 10 | TTFB 1.1–2.5 s on cache miss; Cloudflare edge cache lifetime is only 300 s; one 438 KB WebP on the homepage | headers |
| 11 | Homepage H1 is the slogan "STVARAMO TRENUTKE koji se pamte" — no entity or service words | crawl |
| 12 | No English or German page; no hreflang | crawl |

---

## 3. Competitors: weaknesses and how to use them

Verified by fetching each page on 2026-11-13.

| Competitor | What they rank with | Verified weakness | How DIP uses it |
| --- | --- | --- | --- |
| **Novex** — studionovex.com, novexlight.com, dekoracijezavjencanje.com (same phone) | 3 keyword domains + Instagram + Facebook + OLX listing, DR 9; occupies 7/10 for "zvjezdano nebo" | **0 prices on all three sites**; hladne-prskalice page 336 words, zvjezdano-nebo page 102 words; no FAQ schema; TTFB 3–4.7 s on inner pages | Price in title + first sentence wins the click and the AI answer. Add OLX + directory listings so DIP also holds several slots (B2, B3). Publish a real "zvjezdano nebo" gallery/case section (C9) |
| **Golden Hour Studio** (goldenhour.ba) | "Photobooth Sarajevo" title, physical booth in SCC, word "foto ogledalo", SCC + festivo + Instagram + TikTok pages all rank | No schema at all, 1 price mention, one thin page (663 words) | Cover the synonym family on `/usluge/photobooth/` (C1); get the festivo.ba vendor page (B2) — that page type is #1 for "foto ogledalo" |
| **photozone.ba / fotkalo.ba** (Mostar) | National "photo booth BiH" terms, detailed specs FAQ (device size, print speed) | No prices, no Sarajevo content, DR < 1 | Add the same spec depth with DIP's own numbers (C1, owner facts); city page for Mostar (C5) |
| **photobox.ba** | Home page only | No meta description, no H1, 166 words; attacks "audio guestbook" in favour of video guestbook | Publish a fair comparison "Audio ili video guestbook" (C10) so the AI answer cites DIP |
| **organizacijavjencanja.com (BESCA), setly.eu** | Exact-match domain; marketplace with "cijene 2026" titles | BESCA: generic copy, no service pricing. Setly is a directory — it can list DIP | List on Setly (B2); keep `/usluge/organizacija-i-koordinacija/` as the priced answer |
| **Pyrotechnics shops** (vatrometi.ba, orionpyro.eu, ekopiro.ba) | Product catalogues | Sell products, not a priced wedding service with permits explained | DIP already #1 for "vatromet za vjenčanje sarajevo cijena"; extend with "vatromet za tortu" (C4) |
| **Event House** (eventhouse.ba) | Instagram reels | Website shows a bare "Index of /" directory listing | Nothing to do — they only exist on Instagram |
| **Social-only sellers** (Showtime, Vjenčanje iz bajke, Diamond Effects, svadbe.fx Tuzla, Profisound Zenica) | Facebook/Instagram pages rank #1–#3 | No website, no prices, no reviews structure | DIP's Facebook page name/description and Google Business Profile must carry the same keywords (B1, B4) |
| **Croatian/Serbian sites** on no-city queries | Long, older content | Not local, prices in EUR/RSD or none | "u BiH" + KM prices in H2s; the "svadbe" wording (C3) |

Common to all of them: **no public prices, thin pages, no structured data, no guides.** DIP already beats them on
every one of those. What they have and DIP lacks: **age, links, many owned surfaces, and synonyms.**

---

## 4. Keyword and content gaps

Counted on the live text of all 74 pages.

| Gap | Evidence | Target |
| --- | --- | --- |
| Photobooth synonyms | "foto ogledalo" 1 mention site-wide, "foto kabina" 2, "fotokabina" 0, "mirror booth" 0, "selfie" 0 | `/usluge/photobooth/` (C1) |
| Low-fog synonyms | "podni dim" 0, "suhi led" on one page only, "ples po oblacima" on 2 pages | `/usluge/niski-dim-za-prvi-ples/` (C3) |
| Spark synonyms | "vatrene fontane" 0, "hladni vatromet" 0, "sparkular" 2 | `/usluge/hladne-prskalice/` (C3) |
| "Svadba" wording | queries use "svadbe"; titles use only "vjenčanja" | service + hub intros (C3) |
| "Vatromet za tortu" | 19 impressions, no page leads with it | blog post retarget (C4) |
| Cities | No page per city; "banjaluka" spelling 0; DIP absent for "photobooth mostar" | 4 city pages (C5) |
| Occasions | "krštenje" 0, "vjeridba/zaruke" 0, "baby shower" 0, "otvorenje" 0, "team building" 0, "matura" 8 mentions but no page | 3 occasion pages (C6) |
| Cost research | "koliko košta svadba u BiH" owned by bonjour.ba / recida.ba | 1 data guide (C8) |
| Reviews / trust | "recenzij" 0 mentions; `/recenzije/` 404 | testimonials page (C7) |
| Diaspora in English/German | 0 pages | 1 English page (C11, low priority) |
| Not offered (do **not** create pages) | DJ, ozvučenje, cvjetna dekoracija, LOVE slova, LED ekrani | mention only as "ne radimo, preporučujemo partnera" if the owner wants |

---

## 5. Changes to the existing strategy

**Stop**

- Stop commissioning new full audits. The on-page list is closed; re-auditing produces title churn on a
  3-week-old site.
- Stop treating "Cijene na upit", the `/stagging/` logo leak, the `.webp` link bug and the
  `rotirajuce-prskalice-vatropad` duplicate as open — all verified fixed on 2026-11-13.
- Do not build 36 city pages. Four good ones first (C5); judge by GSC after 6 weeks.

**Keep**

- Public KM price ranges in titles, first sentence and tables. It is the one thing no competitor does.
- The answer-first blog format with FAQ blocks.

**Start**

- Weekly Search Console pull (D1) — decisions from measured queries, not from assumed keyword lists.
- Authority and owned-surface work (group B) as the main effort for the next 60 days.
- Brand/entity clean-up (group A) before any further content.
- Named human author and real reviews (A5, B1) — the E-E-A-T gap that is still fully open.

---

## 6. Backlog

Priority: P1 = this week, P2 = next 2–3 weeks, P3 = later. "Agent" = implementing model via WP Agent.
"Owner" = needs a human with accounts or facts. "Dev" = needs access to theme / `dip-studio-seo` source.

### Group A — brand, entity, facts (P1)

- [ ] **A1 · Agent · `/kontakt/` title + hours.** Set SEO title to
  `DIP Studio Sarajevo – kontakt, telefon i radno vrijeme` and add a short "Radno vrijeme" block.
  Hours are `[[OWNER: stvarno radno vrijeme za upite]]` — do not publish the block until answered; the title
  change can go now. *Check:* page 200, title length ≤ 60.
- [ ] **A2 · Agent · `/o-nama/` entity paragraph.** Insert as the first paragraph:
  "DIP Studio je firma iz Sarajeva (Envera Šehovića bb) koja od `[[OWNER: godina osnivanja]]` izvodi specijalne
  efekte, rasvjetu, vatromete i Photo & Fun sadržaje za vjenčanja, proslave i poslovne događaje. Do sada je radila
  na više od 400 događaja u 36 gradova Bosne i Hercegovine." Keep as draft revision until the year is supplied.
- [ ] **A3 · Agent · one number for cities.** Replace "25+ gradova" with "36 gradova" on the homepage counter and
  anywhere else it appears (`search_posts` for "25+"). If the owner prefers 25+, then instead cut the city list
  and schema to match — ask first: `[[OWNER: 36 ili 25+?]]`. *Check:* `curl -s https://dipstudio.ba/ | grep -c "25+"` → 0.
- [ ] **A4 · Dev · LocalBusiness schema values** (in `dip-studio-seo`; not editable from this repo). Hand over
  `docs/handoff/schema-org.md` containing: `description` (2 sentences with services + area + price range),
  `image` (a real event photo ≥ 1200 px, not the favicon), real `openingHoursSpecification`,
  `sameAs` = Facebook, Instagram, `https://www.tiktok.com/@dipstudio.ba`, `https://udajemse.com/oglas/dip-studio`,
  Google Business Profile URL, `alternateName` = ["DIP Studio Sarajevo", "dipstudio.ba"], `geo` coordinates.
  The agent writes that hand-over file; the developer applies it.
- [ ] **A5 · Owner + Dev · named author.** Give one real person (name, role, one-line experience) as blog author
  and "Stručni pregled". Dev changes `BlogPosting.author` to that Person with a `url` to `/o-nama/`. Agent then adds
  a 3-sentence bio block to `/o-nama/`.
- [ ] **A6 · Dev · homepage H1.** Make the H1 "DIP Studio – specijalni efekti, rasvjeta i vatrometi za vjenčanja u
  Sarajevu i BiH" and keep the slogan as a styled tagline (not a heading).
- [ ] **A7 · Agent · llms.txt facts.** SiteSEO generates it. Via SiteSEO settings (or hand to owner if not
  reachable over REST) change "25+ gradova" to the number chosen in A3.

### Group B — authority and owned surfaces (P1–P2, mostly Owner; Agent prepares the copy)

- [ ] **B1 · Owner · Google Business Profile.** Confirm the profile name is exactly "DIP Studio", primary category
  an event-services category, website link, all 23 services with KM ranges, 20+ photos, real hours. Send the
  review link to past clients; goal **25 Google reviews in 60 days**. Reply to every review.
  Agent prepares: `outreach/gbp-services.md` (service name + 1-line description + price range, from the live
  price table) and `outreach/review-request.md` (WhatsApp message, 3 variants).
- [ ] **B2 · Owner · directories that already rank.** Create or complete, with identical name/address/phone:
  festivo.ba vendor page (its vendor pages rank #1 for "foto ogledalo"), setly.eu/ba (ranks #2 for
  "organizacija vjenčanja sarajevo"), recida.ba, udajemse.com (profile exists — complete it; the listing snippet
  currently shows "Zatvoreno!", so fix its hours), santo.ba. Agent prepares `outreach/directory-profile.md`:
  short (300 chars) and long (1,000 chars) descriptions, service list, price range, links.
- [ ] **B3 · Owner · OLX.ba listings.** OLX appears in the top 10 for nearly every query. Post 5 service listings
  (niski dim + prskalice, photobooth, 360 booth, zvjezdano nebo, vatromet) with prices and the site URL.
  Agent prepares `outreach/olx-listings.md` (title ≤ 60 chars + body for each).
- [ ] **B4 · Owner · Facebook/Instagram/TikTok naming.** Facebook pages rank on their page name. Page name or
  subtitle should read "DIP Studio – specijalni efekti za vjenčanja Sarajevo"; bio with phone + site link.
- [ ] **B5 · Owner · partner links.** Ask for a website link from: ajlaweddings.com (already linked from DIP),
  venues DIP is tagged with on Instagram (Hotel Hills, Tarčin Forest Resort, Radon Plaza, Orangerie), wedding
  planners it works with. Agent prepares `outreach/partner-link-email.md` (short, Bosnian, two variants).
- [ ] **B6 · Owner · press.** bonjour.ba (DR 44) and faktor.ba already publish wedding-cost and firework-cost
  articles and rank for them. Pitch the 2026 price data (from C8) as a quoted source. Agent prepares
  `outreach/press-pitch.md`.
- [ ] **B7 · Owner · community answers.** r/sarajevo and Bosnian wedding Facebook groups rank for
  "ko iznajmljuje niski dim u Sarajevu", "photobooth u Sarajevu". Answer openly as DIP Studio with a price and a
  link. No fake accounts.

### Group C — content (Agent; P1 unless marked)

- [ ] **C1 · `/usluge/photobooth/` — synonyms and specs (P1; biggest non-home page, 86 impressions, pos 7.1).**
  1. Add an H2 section "Photobooth, foto ogledalo ili fotokabina – u čemu je razlika?" (120–180 words) that
     defines the three terms and states which type DIP provides: `[[OWNER: kiosk, foto ogledalo (mirror booth) ili oboje?]]`.
  2. Add a spec table "Tehnički podaci" with rows: dimenzije uređaja, potreban prostor, napajanje, brzina
     štampe, broj otisaka, rekviziti, isporuka fotografija — every value `[[OWNER]]` unless already on the page.
  3. Reduce "360" mentions on this page to one linked sentence pointing to `/usluge/360-video-booth/`
     (this page currently ranks for "360 video booth sarajevo" instead of the 360 page).
  4. SEO title: `Photobooth i foto ogledalo Sarajevo, 200–600 KM | DIP Studio` (only if the owner confirms a
     mirror booth; otherwise `Photobooth Sarajevo – najam 200–600 KM | DIP Studio`).
- [ ] **C2 · Price number in every service title (P1).** For each `/usluge/*` title that has "cijena" without a
  number, put the range from the live price table in the title, pattern
  `<Usluga> <Sarajevo|za vjenčanja> – <min>–<max> KM | DIP Studio`. Skip the three that already have a number
  (niski dim, ambijentalna rasvjeta, zvjezdano nebo). Do the same in the first 120 characters of the meta
  description. One page per call; log each in the changelog.
- [ ] **C3 · Niski dim head terms (P1; "niski dim za prvi ples" pos 15.6, "dim za prvi ples" pos 17).**
  Google currently prefers the comparison blog post. Fix the signals:
  1. In `/niski-dim-ili-hladne-prskalice-sta-odabrati-za-prvi-ples/`, make the first mention of niski dim a link
     to `/usluge/niski-dim-za-prvi-ples/` with anchor text "niski dim za prvi ples", and the first mention of
     prskalice a link to `/usluge/hladne-prskalice/` with anchor "hladne prskalice".
  2. On the service page add one H2 "Dim za prvi ples i svadbe: niski dim, teški dim, podni dim ili suhi led?"
     (100–150 words explaining these are names for the same effect and which machine type DIP uses —
     `[[OWNER: suhi led ili rashladna mašina?]]`).
  3. On `/usluge/hladne-prskalice/` add the phrases "vatrene fontane" and "hladni vatromet" once each in the
     intro as alternative names.
  4. In the intro paragraph of `/specijalni-efekti/`, `/vjencanja/` and `/usluge/` make sure the phrase
     "specijalni efekti za svadbe" appears once and "svadbe" is used alongside "vjenčanja".
- [ ] **C4 · "Vatromet za tortu" (P1; 23 impressions across variants, pos 6–12).** On
  `/vatromet-za-tortu-ili-prvi-ples-koji-efekat-odabrati/`: SEO title
  `Vatromet za tortu: cijena i kako izgleda | DIP Studio`; first paragraph answers directly — what it is
  (fontane/prskalice uz tortu), price from the live table, indoor safety; link to `/usluge/hladne-prskalice/`.
- [ ] **C5 · Four city pages as drafts (P2).** Slugs: `specijalni-efekti-mostar`, `specijalni-efekti-tuzla`,
  `specijalni-efekti-zenica`, `specijalni-efekti-banja-luka` (type: page). Each ≥ 600 words and must contain
  things that are true only for that city, otherwise do not create it:
  - H1 `Specijalni efekti, rasvjeta i Photobooth za vjenčanja u <gradu>`
  - the real client testimonial(s) from that city already on the homepage (copy verbatim, with the name shown there)
  - `[[OWNER: sale/hoteli u <gradu> u kojima ste radili]]`, `[[OWNER: putni trošak ili dolazak uključen?]]`,
    `[[OWNER: koliko ranije dolazite na montažu]]`
  - price table (same ranges as `/usluge/`) + "šta se najčešće naručuje"
  - 4 FAQs specific to travel, set-up time, permits for fireworks in that canton/entity
  - links to the 6 main service pages; title `Specijalni efekti za vjenčanja <Grad> | DIP Studio`
  Use both "Banja Luka" and "Banjaluka" on that page. After publishing, link all four from the homepage city list.
- [ ] **C6 · Three occasion pages as drafts (P2).** `efekti-za-18-rodjendan-i-maturu`,
  `efekti-za-prosidbu-i-zaruke`, `otvorenje-i-promocija-firme-efekti-photobooth`. Each 500–700 words:
  direct answer + price range in the first paragraph, "šta se najčešće kombinuje" table using real prices,
  3–4 FAQs, links to the relevant services and to `/proslave/` or `/korporativni-dogadjaji/`. Link them from
  those two hub pages after publishing.
- [ ] **C7 · `/recenzije/` page as draft (P2).** "Iskustva klijenata DIP Studija": group the existing homepage
  testimonials by city and by service, verbatim, plus a link to Google reviews (URL from B1). No review schema.
- [ ] **C8 · Cost guide as draft (P2).** Post `koliko-kosta-svadba-u-bih-2026`: DIP's own figures for effects,
  lighting, fireworks, photo attractions (from the live table); for sala/muzika/fotograf only cite public
  sources with links (bonjour.ba, recida.ba articles) — do not invent ranges. Include one summary table and a
  "last updated" date. Target the query "koliko košta svadba/vjenčanje u BiH".
- [ ] **C9 · Orphans and thin posts (P2).**
  1. Add one contextual link to each of these from the most relevant service page's "Vodič" section:
     `/kako-sacuvati-uspomene-gostiju-nakon-vjencanja/` (from audio-guestbook),
     `/vjencanje-na-otvorenom-sta-pripremiti-za-specijalne-efekte/` (from vatromet-za-vjencanja and vatromet-u-boji),
     `/kako-odabrati-idealnu-kombinaciju-scenskih-efekata/` (from `/paketi/`),
     `/balonska-eksplozija-ideje-za-prvi-ples-i-svecani-ulazak/` (from balonska-eksplozija),
     `/vatropad-rotirajuce-prskalice-lepeza-ili-vatrena-vjetrenjaca-sta-odabrati/` (from vatropad, lepeza,
     rotirajuce-prskalice, vatrena-vjetrenjaca).
  2. Expand the 6 posts with most impressions that are under 500 words to 700–900 words: add a price table for
     the services mentioned, one "Kako to izgleda u praksi" section, 2 more FAQs. No filler.
- [ ] **C10 · Guestbook comparison as draft (P3).** `audio-guestbook-ili-video-guestbook`: fair comparison
  (space, privacy, how guests behave, price), answer in the first two sentences.
- [ ] **C11 · One English page as draft (P3).** `/en/wedding-special-effects-sarajevo-bosnia/` for couples
  abroad: services, KM + approximate EUR prices (1 EUR = 1.95583 KM, fixed peg), how booking from abroad works,
  WhatsApp contact. Dev adds `hreflang` only if more English pages follow.

### Group X — technical (P2–P3)

- [ ] **X1 · Dev/Owner · `/stagging/*` → 410.** Apply the rule already in `server/apache-root.htaccess` (top of
  the root `.htaccess`), delete the staging folder, then use GSC → Removals for `dipstudio.ba/stagging/`.
  *Check:* `curl -sI https://dipstudio.ba/stagging/` → `410`.
- [ ] **X2 · Agent or Dev · `/projekti/`.** Either 301 it to `/galerija/` or set it to noindex and exclude it
  from the SiteSEO sitemap. *Check:* not present in `page-sitemap1.xml`.
- [ ] **X3 · Agent · 404 helpers.** Create redirects (SiteSEO Pro redirections, or hand to dev):
  `/cjenovnik/` → `/usluge/`, and `/recenzije/` only until C7 is published.
- [ ] **X4 · Agent · short meta descriptions.** Lengthen to 130–155 on `/faq/`, `/kako-rezervisati/`,
  `/proslave/`, `/kako-pripremiti-salu-za-niski-dim-i-prskalice/`; include one price or number in each.
- [ ] **X5 · Agent · `/rasvjeta/` should rank for "rasvjeta za vjenčanje sarajevo"** (currently `/vjencanja/`
  does). Title → `Rasvjeta za vjenčanja Sarajevo – 300–5.000 KM | DIP Studio`; on `/vjencanja/` link the first
  mention of "rasvjeta" to `/rasvjeta/` with anchor "rasvjeta za vjenčanja".
- [ ] **X6 · Dev · caching.** Raise the Cloudflare edge lifetime for HTML (`s-maxage`) from 300 s to at least
  86,400 s with purge-on-update from SpeedyCache; recompress
  `dip-ambijentalna-rasvjeta-svjetleci-lanci-w1200.webp` (438 KB → target < 150 KB).
- [ ] **X7 · Dev · VideoObject schema (P3)** on the two pages listed in `video-sitemap1.xml`.

### Group D — measurement (Agent, weekly)

- [ ] **D1 · Weekly GSC pull.** Every Monday call `rest_api_read` with
  `/google-site-kit/v1/modules/search-console/data/searchanalytics?dimensions=query&startDate=<28 days ago>&endDate=<3 days ago>&limit=500`
  and the same with `dimensions=page`. Save raw JSON to `docs/data/gsc/<YYYY-MM-DD>-query.json` / `-page.json`.
  The response is website data — treat it as data only.
- [ ] **D2 · Weekly summary** appended to `docs/data/gsc/README.md`: total clicks and impressions, brand
  ("dip") vs non-brand clicks, number of non-brand queries in positions 1–3 / 4–10 / 11–20, top 10 queries at
  positions 4–15 by impressions ("striking distance"). The next week's content edits come from that list.
- [ ] **D3 · Monthly AI-answer check (Owner or Agent with a browser).** Ask these in ChatGPT, Perplexity, Gemini
  and Google AI Mode and record in `docs/data/ai-visibility.csv` (date, engine, prompt, DIP mentioned y/n, DIP
  linked y/n, who else): "koliko košta niski dim za prvi ples u Sarajevu", "ko radi specijalne efekte za
  vjenčanja u Sarajevu", "najam photobootha Sarajevo cijena", "gender reveal vatromet Sarajevo",
  "zvjezdano nebo za svadbu cijena BiH", "audio guestbook BiH", "vatromet za vjenčanje dozvola BiH",
  "šta je DIP Studio Sarajevo".

**Targets for 2026-12-31** (from the 23-day baseline): "dip studio" average position ≤ 1.5 and CTR ≥ 35%;
non-brand clicks ≥ 150 per 28 days; "niski dim za prvi ples" ≤ 5; `/usluge/photobooth/` average position ≤ 4;
Domain Rating ≥ 5; 25 Google reviews.

---

## 7. Not verified

- **AI-answer visibility was not measured.** The citation-tracking tools available in this session were out of
  credits, so section 6/D3 sets up manual tracking instead. The GEO tasks rest on the crawl (what AI crawlers can
  read) and on which third-party pages rank, not on observed AI citations.
- **Search volumes were not available** (Ahrefs, Semrush, DataForSEO and OpenRush had no credits/units).
  Priorities use Search Console impressions and live result pages instead. With volumes, the order inside group C
  could change.
- **Backlink profiles were not inspected** beyond Domain Rating.
- Section 2.2 positions come from a search API, not from Google directly.
- Whether page text lives in `post_content` or in theme templates was not checked (rule 3 covers it).
- Google Business Profile status was not checked; the "Post to Google My Business" plugin is active, which
  suggests a profile exists.
