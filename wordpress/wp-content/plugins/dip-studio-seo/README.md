# DIP Studio — SEO 1.2.0

One plugin that replaces **DIP Studio — Schema Enrichment 1.0.0** and **DIP Studio – SEO/AEO fixes 1.0.1**.

## New in 1.2.0 (from the "Comprehensive Audit of dipstudio.ba")

- **Gallery cleanup:** /galerija/ showed 248 photos, but 136 of them were -w960/-w480 copies of the same pictures. There were also 4 system images (website header preview, apple-touch icon and two file_0000… exports). Those 140 are now hidden, which leaves 108 real photos. Nothing is deleted from Media; the switch is under Tools → DIP SEO.
- **Author archives:** `/author/admin/` and any other author archive now redirect (301) to the blog. This also stops showing the admin login name.
- **Footer headings:** the three footer column titles (Brzi linkovi, Usluge, Kontakt) are now `<p>` instead of `<h2>`, with the same look.
- **FAQ schema bug:** the automatic FAQ schema on /usluge/photobooth/ started its answer with "wp:paragraph", which was leftover editor code. That is fixed.

Updating from 1.1.0: Plugins → Add New → Upload → choose the zip → "Replace current with uploaded". Settings are kept.

## What changed in the merge (1.1.0)

- **Schema:** the enrichment plugin's approach is kept. It only adds to the JSON-LD that SiteSEO and DIP Core already print. The SEO-fixes plugin's own schema (a second business entity plus extra WebSite and Service nodes) was **removed**, because it duplicated what is already on every page.
- **robots.txt:** the sitemap line now points to `/sitemaps.xml` (SiteSEO). The old file pointed to `/sitemap_index.xml`, which doesn't exist. It also blocks `/stagging/wp-admin/`, where the real admin lives.
- **"atrakcije"** removed from category names and alt-text suggestions, as the client asked.
- **llms.txt:** the static file is now **off by default**, because SiteSEO already serves `/llms.txt`. You can turn it on in settings.
- **Gallery alt text:** new table under **Tools → DIP SEO** that lists English or prompt-style alt texts with Bosnian replacements and applies them with one button. No WP-CLI is needed.
- **Settings:** every fix has its own on/off switch under **Tools → DIP SEO**. Old enrichment settings are carried over on activation.
- **Safety guard:** if either old plugin is still active, this one does nothing and shows a red notice.

## Install (wp-admin only)

1. Make a Backuply backup.
2. **Plugins:** deactivate *DIP Studio — Schema Enrichment* and *DIP Studio – SEO/AEO fixes* if they are installed. Don't delete them yet.
3. **Plugins → Add New → Upload Plugin:** upload `dip-studio-seo-1.2.0.zip`, then click **Activate**.
4. Open **Tools → DIP SEO** and check the switches. All are on except *Vlastiti /llms.txt*.
5. Purge SpeedyCache. Cloudflare keeps HTML for about 5 minutes.
6. Check these:
   - `https://dipstudio.ba/robots.txt` should show `Sitemap: https://dipstudio.ba/sitemaps.xml`.
   - `https://dipstudio.ba/stagging/sample-page/` should return `410 Gone`.
   - View the source of `/` and `/usluge/niski-dim-za-prvi-ples/`. It should show no `stagging/#Organization`, one Service and one FAQPage.
   - Run https://validator.schema.org/ on a service page.
7. **Optional, alt text:** in **Tools → DIP SEO → Alt tekst slika**, review the suggestions, untick any you don't want, then click **Primijeni označene prijedloge**. Purge SpeedyCache afterwards.
8. Once everything looks right, delete the two old plugins.

## Rollback

Deactivate **DIP Studio — SEO** and purge SpeedyCache. Everything goes back to what it was before. Alt texts that you applied stay changed, and you can edit them in Media.

## Not included

- Prices, addresses, opening hours: nothing was invented.
- The hub-page generator from the old plugin (`wp dip-seo create-hubs`) is still there, but it needs WP-CLI. Until hub pages exist, `/specijalni-efekti/`, `/rasvjeta/`, `/vatrometi/` and `/photo-fun/` redirect to `/usluge/` (302).

## 1.3.0 (second audit, 6 Nov 2026)

New switches under Tools → DIP SEO → "Drugi audit": price_copy, blog_prices, price_badges, home_faq,
gallery_hide_ai, alt_render, schema_tidy, xml_decl. Prices are read from the "Cjenovnik usluga" table on
/usluge/ each time that page is generated (option `dip_seo_price_map`), so nothing here is typed by hand.
