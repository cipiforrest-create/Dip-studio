# Hand-over: changes that could not be made through the WordPress connection

Status 2026-11-13. Content edits to existing pages were made directly (see `docs/CHANGELOG_SEO.md`).
Everything here needs either the WordPress admin, the `dip-studio-seo` plugin source, hosting access, or a
decision from the owner.

## Why
- **SEO titles / meta descriptions**: stored by SiteSEO / `dip-studio-seo`, not exposed over the REST API, and
  the WP Agent connector only writes Yoast, Rank Math or AIOSEO fields.
- **New pages**: the WP Agent connector refuses to create posts on dipstudio.ba because the domain is tied to
  another free WP Agent account (paid plan or account transfer needed). Editing existing pages works.

## What to apply
| File / item | Task | Where to apply |
| --- | --- | --- |
| `seo-titles.csv` | C2, A1, C4, X4, X5 | WP admin → each page → SiteSEO box (title + description). 27 rows, lengths pre-checked |
| `schema-org.md` | A4, A5 | `dip-studio-seo` plugin source |
| `../../content/drafts/*.html` | C5, C6, C7 | WP admin → new Page → Code editor → paste. Slug, SEO title and description are in the comment at the top. Fill every `[[OWNER: …]]`, then publish |
| `/stagging/*` → 410 | X1 | rules in `server/apache-root.htaccess` (top of root `.htaccess`); delete the staging folder; GSC → Removals |
| `/projekti/` | X2 | 301 → `/galerija/` (SiteSEO Pro → Redirections) and remove from sitemap. Internal links to it were already removed from `/usluge/photobooth/` and `/vjencanja/` |
| `/cjenovnik/` → `/usluge/` (301), `/recenzije/` | X3 | SiteSEO Pro → Redirections; `/recenzije/` becomes a real page from `content/drafts/recenzije.html` |
| Cloudflare HTML cache 300 s → 86,400 s, recompress `dip-ambijentalna-rasvjeta-svjetleci-lanci-w1200.webp` (438 KB) | X6 | Cloudflare / SpeedyCache |
| Homepage H1 = slogan | A6 | theme `dip-studio-cinematic`, `front-page.php` |
| "25+ gradova" vs 36 listed | A3 | owner decides the number; homepage counter + FAQ are theme-rendered; `/vjencanja/` text says "više od 25 gradova" in 3 places |
