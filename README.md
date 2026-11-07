# DIP Studio – SEO for dipstudio.ba

One WordPress plugin, **DIP Studio — SEO** (`wordpress/wp-content/plugins/dip-studio-seo/`), with every
code-level fix from the dipstudio.ba audits (Nov 2026). It merges "DIP Studio — Schema Enrichment",
"DIP Studio – SEO/AEO fixes" and the re-audit work. Every fix has an on/off switch under **Tools → DIP SEO**.

| Path | Purpose |
| --- | --- |
| `wordpress/wp-content/plugins/dip-studio-seo/` | The plugin (`./build.sh` → `dist/dip-studio-seo-<version>.zip`) |
| `wordpress/wp-content/plugins/dip-studio-seo/README.md` | What each version changed, install, rollback |
| `server/apache-root.htaccess` / `server/nginx.conf` | noindex for the /stagging/ staging copy, 301 for date-prefixed posts |
| `tests/run.php` | Tests for the pure helpers (`php tests/run.php`) |
| `docs/IMPLEMENTATION_STATUS.md` | Every audit item → done / manual step |

## Install / update (from wp-admin, works on a phone)

1. Backup (Backuply / Softaculous).
2. Plugins → Add New → Upload Plugin → `dip-studio-seo-1.4.0.zip` → **Replace current with uploaded**.
   Settings are kept. Only this one plugin should be active; it refuses to run next to the old ones.
3. Tools → DIP SEO: check the switches, then run the alt-text tool at the bottom.
4. Purge SpeedyCache and Cloudflare.
5. Staging: add `server/apache-root.htaccess` to the **live** `.htaccess` (or password-protect
   /stagging/ in cPanel → Directory Privacy), then Search Console → Removals → `https://dipstudio.ba/stagging/`.

## Configuration

- Prices are never typed in: they are read from the "Cjenovnik usluga" table on /usluge/.
- Street address and working hours: `street` and `hours` in `inc/services.php` (not published yet, so not invented).
