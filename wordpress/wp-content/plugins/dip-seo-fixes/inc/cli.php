<?php
/**
 * WP-CLI commands: `wp dip-seo check|audit-pages|create-hubs|create-service-pages|rename-legacy-slugs|gallery-alt|cleanup`.
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

class DIP_SEO_CLI {

	/**
	 * Read-only health check against the audit's critical findings.
	 *
	 * ## EXAMPLES
	 *     wp dip-seo check
	 */
	public function check() {
		global $wpdb;

		$like  = '%' . $wpdb->esc_like( '/stagging/' ) . '%';
		$like2 = '%' . $wpdb->esc_like( '/staging/wp-content' ) . '%';
		$posts = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE %s OR post_content LIKE %s", $like, $like2 ) );
		$meta  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_value LIKE %s OR meta_value LIKE %s", $like, $like2 ) );
		$opts  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_value LIKE %s OR option_value LIKE %s", $like, $like2 ) );
		$this->line( 0 === $posts + $meta + $opts, "Staging URLs in DB (posts: {$posts}, postmeta: {$meta}, options: {$opts})" );

		$staging_dir = ABSPATH . 'stagging';
		$this->line( ! is_dir( $staging_dir ) && ! is_dir( ABSPATH . 'staging' ), 'No /stagging/ or /staging/ directory in web root' );

		foreach ( dip_seo_categories() as $slug => $cat ) {
			if ( isset( $cat['hub'] ) && ! $cat['hub'] ) {
				continue;
			}
			$p = get_page_by_path( $slug );
			$this->line( $p && 'publish' === $p->post_status, "Hub page /{$slug}/ published" );
		}
		$blog = (int) get_option( 'page_for_posts' );
		$this->line( $blog && 'blog' === get_post_field( 'post_name', $blog ), 'Blog index page set to /blog/ (Settings → Reading)' );

		$this->line( '/%postname%/' === get_option( 'permalink_structure' ), 'Permalink structure is /%postname%/' );
		$this->line( ! file_exists( ABSPATH . 'robots.txt' ), 'No physical robots.txt (so the plugin\'s robots.txt is served)' );
		$this->line( (bool) get_option( 'blog_public' ), 'Search engine visibility enabled' );
		$this->line( '' !== dip_seo_business()['street'], 'Street address filled in (inc/services.php → dip_seo_business)' );
		$this->line( ! empty( dip_seo_business()['hours'] ), 'Working hours filled in (inc/services.php → dip_seo_business)' );

		$hello = get_posts( array( 'name' => 'hello-world', 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1 ) );
		$this->line( ! $hello, 'No default "Hello world!" post (wp dip-seo cleanup)' );

		$missing = array();
		foreach ( array_keys( dip_seo_services() ) as $slug ) {
			$p = get_page_by_path( "usluge/{$slug}" );
			if ( ! $p || 'publish' !== $p->post_status ) {
				$missing[] = $slug;
			}
		}
		$this->line( ! $missing, 'All 23 service pages published' . ( $missing ? ' — missing: ' . implode( ', ', $missing ) . ' (create-service-pages / rename-legacy-slugs)' : '' ) );
		$this->line( (bool) get_page_by_path( 'paketi' ), 'Page /paketi/ exists' );

		$counts = array();
		foreach ( $this->images() as $img ) {
			foreach ( array( 'alt' => $this->alt_of( $img ), 'title' => (string) $img->post_title ) as $f => $v ) {
				$t = dip_seo_classify_alt( $v )['type'];
				if ( 'ok' !== $t ) {
					$counts[ "{$f}:{$t}" ] = ( $counts[ "{$f}:{$t}" ] ?? 0 ) + 1;
				}
			}
		}
		$this->line( ! $counts, 'Media alt/titles clean ' . ( $counts ? wp_json_encode( $counts ) : '' ) . ' (run `wp dip-seo gallery-alt` for details)' );
	}

	/**
	 * Creates the 4 category hub pages and the /blog/ index page (audit §2.3, checklist #2).
	 * Existing pages are never overwritten.
	 *
	 * ## OPTIONS
	 *
	 * [--publish]
	 * : Publish immediately. Default creates drafts for review.
	 *
	 * ## EXAMPLES
	 *     wp dip-seo create-hubs
	 *     wp dip-seo create-hubs --publish
	 */
	public function create_hubs( $args, $assoc ) {
		$status = ! empty( $assoc['publish'] ) ? 'publish' : 'draft';

		foreach ( dip_seo_categories() as $slug => $cat ) {
			if ( isset( $cat['hub'] ) && ! $cat['hub'] ) {
				continue;
			}
			if ( get_page_by_path( $slug, OBJECT, 'page' ) ) {
				WP_CLI::log( "Skip /{$slug}/ — a page with this slug already exists." );
				continue;
			}
			$id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => $status,
					'post_name'    => $slug,
					'post_title'   => $cat['h1'],
					'post_excerpt' => $cat['desc'],
					'post_content' => $this->hub_content( $cat ),
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				WP_CLI::warning( "/{$slug}/: " . $id->get_error_message() );
				continue;
			}
			// SiteSEO per-post title/description. Verify these keys against your SiteSEO version.
			update_post_meta( $id, apply_filters( 'dip_seo_meta_title_key', '_siteseo_titles_title' ), $cat['title'] );
			update_post_meta( $id, apply_filters( 'dip_seo_meta_desc_key', '_siteseo_titles_desc' ), $cat['desc'] );
			WP_CLI::success( "Created /{$slug}/ ({$status}, ID {$id})" );
		}

		if ( ! get_page_by_path( 'blog', OBJECT, 'page' ) ) {
			$id = wp_insert_post(
				array(
					'post_type'   => 'page',
					'post_status' => $status,
					'post_name'   => 'blog',
					'post_title'  => 'Blog',
				),
				true
			);
			if ( ! is_wp_error( $id ) ) {
				WP_CLI::success( "Created /blog/ ({$status}, ID {$id})" );
			}
		}
		$blog = get_page_by_path( 'blog', OBJECT, 'page' );
		if ( $blog && 'publish' === $blog->post_status && ! get_option( 'page_for_posts' ) ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_for_posts', $blog->ID );
			WP_CLI::success( '/blog/ set as the posts page.' );
		} elseif ( $blog && 'publish' !== $blog->post_status ) {
			WP_CLI::log( 'Publish /blog/, then set it under Settings → Reading → Posts page.' );
		}
		flush_rewrite_rules( false );
	}

	/**
	 * Audits (and optionally fixes) image alt text AND Media Library title / caption / description:
	 * leaked English AI prompts, slug-style titles without diacritics, hash filenames and UI icons
	 * (audit §2.6, re-audit §4, action #10).
	 *
	 * Re-audit finding: alt texts were translated but the Media Library titles were not. When the
	 * alt is already a good Bosnian description, it is copied into the bad title/caption/description.
	 *
	 * ## OPTIONS
	 *
	 * [--apply]
	 * : Write the fixes. Without it, dry run.
	 *
	 * [--include-slugs]
	 * : Also write generated alts for slug-style alts ("Dip Galerija …"). Review the dry run first.
	 *
	 * [--brand-suffix]
	 * : Append " – DIP Studio Sarajevo" to good alts that don't mention DIP Studio (re-audit §4).
	 *
	 * [--format=<format>]
	 * : table, csv or json. Default: table.
	 *
	 * ## EXAMPLES
	 *     wp dip-seo gallery-alt
	 *     wp dip-seo gallery-alt --apply
	 *     wp dip-seo gallery-alt --format=csv > alt-review.csv
	 */
	public function gallery_alt( $args, $assoc ) {
		$apply  = ! empty( $assoc['apply'] );
		$slugs  = ! empty( $assoc['include-slugs'] );
		$suffix = ! empty( $assoc['brand-suffix'] );
		$rows   = array();
		$wrote  = 0;

		foreach ( $this->images() as $img ) {
			$fields = array(
				'alt'         => (string) get_post_meta( $img->ID, '_wp_attachment_image_alt', true ),
				'title'       => (string) $img->post_title,
				'caption'     => (string) $img->post_excerpt,
				'description' => (string) $img->post_content,
			);

			// The text every bad field should become.
			$alt_c = dip_seo_classify_alt( $fields['alt'] );
			$good  = null;
			if ( '' !== trim( $fields['alt'] ) && 'ok' === $alt_c['type'] ) {
				$good = trim( $fields['alt'] );
				if ( $suffix && false === stripos( $good, 'DIP Studio' ) ) {
					$good = rtrim( $good, ' .' ) . ' – DIP Studio Sarajevo';
				}
			} elseif ( $alt_c['suggestion'] && ( $alt_c['auto'] || $slugs ) ) {
				$good = $alt_c['suggestion'];
			} elseif ( '' === trim( $fields['alt'] ) ) {
				$t = dip_seo_classify_alt( $fields['title'] );
				if ( $t['suggestion'] && ( $t['auto'] || $slugs ) ) {
					$good = $t['suggestion'];
				}
			}

			$changes = array();
			foreach ( $fields as $field => $value ) {
				if ( '' === trim( $value ) && 'alt' !== $field ) {
					continue;
				}
				$c       = dip_seo_classify_alt( $value );
				$is_bad  = 'ok' !== $c['type'] || ( 'alt' === $field && '' === trim( $value ) );
				$is_bad  = $is_bad || ( 'alt' === $field && $good && $good !== $value && $suffix );
				if ( ! $is_bad ) {
					continue;
				}
				$type = '' === trim( $value ) ? 'empty' : $c['type'];
				if ( 'ui_icon' === $type ) {
					$action = 'Remove from the /galerija/ gallery block';
					$target = null;
				} else {
					$target = $good;
					$action = $target ? 'Fix' : ( 'hash' === $type ? 'Rename file + write alt manually' : 'Rewrite manually' );
				}
				if ( $apply && $target ) {
					$changes[ $field ] = $target;
					$action            = 'UPDATED';
				}
				$rows[] = array(
					'ID'         => $img->ID,
					'field'      => $field,
					'type'       => $type,
					'current'    => mb_substr( $value, 0, 90, 'UTF-8' ),
					'suggestion' => (string) $target,
					'action'     => $action,
				);
			}

			if ( $changes ) {
				if ( isset( $changes['alt'] ) ) {
					update_post_meta( $img->ID, '_wp_attachment_image_alt', $changes['alt'] );
				}
				$post = array( 'ID' => $img->ID );
				foreach ( array( 'title' => 'post_title', 'caption' => 'post_excerpt', 'description' => 'post_content' ) as $f => $col ) {
					if ( isset( $changes[ $f ] ) ) {
						$post[ $col ] = $changes[ $f ];
					}
				}
				if ( count( $post ) > 1 ) {
					wp_update_post( $post );
				}
				++$wrote;
			}
		}

		\WP_CLI\Utils\format_items( $assoc['format'] ?? 'table', $rows, array( 'ID', 'field', 'type', 'current', 'suggestion', 'action' ) );
		WP_CLI::log( sprintf( '%d field(s) need attention; %d image(s) updated.', count( $rows ), $wrote ) );
		if ( ! $apply ) {
			WP_CLI::log( 'Dry run. Re-run with --apply to write the fixes.' );
		}
	}

	/**
	 * Creates draft pages under /usluge/ for services that have no page yet (e.g. the
	 * inaccessible /usluge/360-video-booth/), with intro, price block and booking link.
	 * Existing pages are never touched.
	 *
	 * ## OPTIONS
	 *
	 * [--publish]
	 * : Publish immediately instead of creating drafts.
	 *
	 * ## EXAMPLES
	 *     wp dip-seo create-service-pages
	 */
	public function create_service_pages( $args, $assoc ) {
		$status = ! empty( $assoc['publish'] ) ? 'publish' : 'draft';
		$parent = get_page_by_path( 'usluge' );
		if ( ! $parent ) {
			WP_CLI::error( 'Parent page /usluge/ not found.' );
		}
		$legacy = array_flip( dip_seo_legacy_slugs() );
		foreach ( dip_seo_services() as $slug => $svc ) {
			if ( get_page_by_path( "usluge/{$slug}" ) ) {
				continue;
			}
			if ( isset( $legacy[ $slug ] ) && get_page_by_path( 'usluge/' . $legacy[ $slug ] ) ) {
				WP_CLI::log( "Skip /usluge/{$slug}/ — run `wp dip-seo rename-legacy-slugs` instead." );
				continue;
			}
			$name    = preg_replace( '/\s*\(.*\)$/u', '', $svc[0] );
			$content = '<!-- wp:paragraph --><p>' . esc_html( $svc[1] ) . '</p><!-- /wp:paragraph -->'
				. '<!-- wp:html -->' . dip_seo_price_block_html( $slug, home_url( '/' ) ) . '<!-- /wp:html -->';
			$id      = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => $status,
					'post_parent'  => $parent->ID,
					'post_name'    => $slug,
					'post_title'   => $name,
					'post_excerpt' => $svc[1],
					'post_content' => $content,
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				WP_CLI::warning( "/usluge/{$slug}/: " . $id->get_error_message() );
				continue;
			}
			WP_CLI::success( "Created /usluge/{$slug}/ ({$status}, ID {$id}) — add the full copy before publishing." );
		}
	}

	/**
	 * Renames the legacy compound service slugs (re-audit §2, action #9):
	 *   /usluge/rotirajuce-prskalice-vatropad/ → /usluge/rotirajuce-prskalice/
	 *   /usluge/vatrena-vjetrenjaca-lepeza/    → /usluge/vatrena-vjetrenjaca/
	 * The plugin then 301s the old URLs. Also rename the page title/H1 so it targets one service.
	 *
	 * ## OPTIONS
	 *
	 * [--apply]
	 * : Rename. Without it, dry run.
	 */
	public function rename_legacy_slugs( $args, $assoc ) {
		foreach ( dip_seo_legacy_slugs() as $old => $new ) {
			$page = get_page_by_path( "usluge/{$old}" );
			if ( ! $page ) {
				WP_CLI::log( "/usluge/{$old}/ not found (already renamed?)." );
				continue;
			}
			if ( get_page_by_path( "usluge/{$new}" ) ) {
				WP_CLI::warning( "/usluge/{$new}/ already exists — merge by hand, then trash /usluge/{$old}/." );
				continue;
			}
			if ( empty( $assoc['apply'] ) ) {
				WP_CLI::log( "Would rename /usluge/{$old}/ (ID {$page->ID}) → /usluge/{$new}/" );
				continue;
			}
			wp_update_post( array( 'ID' => $page->ID, 'post_name' => $new ) );
			WP_CLI::success( "Renamed /usluge/{$old}/ → /usluge/{$new}/ (old URL now 301s)." );
		}
		if ( empty( $assoc['apply'] ) ) {
			WP_CLI::log( 'Dry run. Re-run with --apply.' );
		}
	}

	/**
	 * Trashes the default "Hello world!" post and flushes page/object caches so every template
	 * shows the current 5-category / 23-service menu (re-audit §1, actions #1 and #4).
	 *
	 * ## EXAMPLES
	 *     wp dip-seo cleanup
	 */
	public function cleanup() {
		foreach ( get_posts( array( 'name' => 'hello-world', 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => 5 ) ) as $p ) {
			wp_trash_post( $p->ID );
			WP_CLI::success( "Trashed \"{$p->post_title}\" (ID {$p->ID})." );
		}
		wp_cache_flush();
		$flushed = array( 'object cache' );
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
			$flushed[] = 'WP Rocket';
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
			$flushed[] = 'W3 Total Cache';
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
			$flushed[] = 'WP Super Cache';
		}
		if ( has_action( 'litespeed_purge_all' ) ) {
			do_action( 'litespeed_purge_all' );
			$flushed[] = 'LiteSpeed';
		}
		if ( class_exists( 'autoptimizeCache' ) ) {
			autoptimizeCache::clearall();
			$flushed[] = 'Autoptimize';
		}
		do_action( 'sg_cachepress_purge_cache' ); // SiteGround, no-op if absent.
		flush_rewrite_rules( false );
		WP_CLI::success( 'Flushed: ' . implode( ', ', $flushed ) . ', rewrite rules. Also purge any CDN/hosting cache.' );
		WP_CLI::log( 'The /stagging/ install has its own database: delete that folder (README step 3) to remove its "Hello world!" post.' );
	}

	/**
	 * Fetches the live pages named in the re-audit and reports status, H1 length, H2 count,
	 * duplicated FAQ/related sections, missing price blocks and "Cijene na upit".
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, csv or json. Default: table.
	 *
	 * ## EXAMPLES
	 *     wp dip-seo audit-pages
	 */
	public function audit_pages( $args, $assoc ) {
		$paths = array( '/', '/usluge/', '/vjencanja/', '/korporativni-dogadjaji/', '/proslave/', '/galerija/', '/o-nama/', '/kontakt/', '/blog/', '/paketi/', '/konfigurator/', '/kako-rezervisati/', '/llms.txt', '/robots.txt', '/stagging/', '/organizacija-dogadjaja/', '/projekti/' );
		foreach ( dip_seo_categories() as $slug => $cat ) {
			if ( ! isset( $cat['hub'] ) || $cat['hub'] ) {
				$paths[] = "/{$slug}/";
			}
		}
		foreach ( array_keys( dip_seo_services() ) as $slug ) {
			$paths[] = "/usluge/{$slug}/";
		}
		foreach ( array_keys( dip_seo_legacy_slugs() ) as $slug ) {
			$paths[] = "/usluge/{$slug}/";
		}

		$rows = array();
		foreach ( $paths as $path ) {
			$res    = wp_remote_get( home_url( $path ), array( 'redirection' => 0, 'timeout' => 20, 'sslverify' => false ) );
			$code   = is_wp_error( $res ) ? 'ERR' : (string) wp_remote_retrieve_response_code( $res );
			$loc    = is_wp_error( $res ) ? $res->get_error_message() : (string) wp_remote_retrieve_header( $res, 'location' );
			$html   = is_wp_error( $res ) ? '' : (string) wp_remote_retrieve_body( $res );
			$issues = array();
			if ( '200' === $code && false !== stripos( $html, '<html' ) ) {
				preg_match_all( '#<h1\b[^>]*>(.*?)</h1>#is', $html, $h1 );
				$words = isset( $h1[1][0] ) ? count( preg_split( '/\s+/u', dip_seo_plain( $h1[1][0] ), -1, PREG_SPLIT_NO_EMPTY ) ) : 0;
				$sec   = dip_seo_count_sections( $html );
				if ( 1 !== count( $h1[1] ) ) {
					$issues[] = count( $h1[1] ) . ' H1';
				}
				if ( $words > 8 ) {
					$issues[] = "H1 {$words} words";
				}
				if ( 0 === $sec['h2'] ) {
					$issues[] = 'no H2';
				}
				if ( $sec['faq'] > 1 ) {
					$issues[] = "{$sec['faq']} FAQ sections";
				}
				if ( $sec['related'] > 1 ) {
					$issues[] = "{$sec['related']} related sections";
				}
				if ( preg_match( '/na\s+upit/iu', dip_seo_plain( $html ) ) ) {
					$issues[] = '"na upit"';
				}
				if ( 0 === strpos( $path, '/usluge/' ) && '/usluge/' !== $path && ! dip_seo_has_price_block( $html ) ) {
					$issues[] = 'no price block';
				}
				if ( preg_match( '#/stagg?ing/wp-#i', $html ) ) {
					$issues[] = 'staging asset URL';
				}
				if ( 0 === $sec['faq'] && in_array( $path, array( '/usluge/', '/vjencanja/', '/korporativni-dogadjaji/', '/proslave/', '/kako-rezervisati/' ), true ) ) {
					$issues[] = 'no FAQ section';
				}
			}
			$rows[] = array(
				'path'     => $path,
				'status'   => $code,
				'location' => $loc,
				'issues'   => implode( '; ', $issues ),
			);
		}
		\WP_CLI\Utils\format_items( $assoc['format'] ?? 'table', $rows, array( 'path', 'status', 'location', 'issues' ) );
	}

	/* ------------------------------------------------------------------------------------- */

	private function hub_content( array $cat ): string {
		$html  = '<!-- wp:paragraph --><p>' . esc_html( $cat['intro'] ) . '</p><!-- /wp:paragraph -->';
		foreach ( $cat['services'] as $slug => $svc ) {
			$url   = esc_url( home_url( "/usluge/{$slug}/" ) );
			$html .= '<!-- wp:heading --><h2 class="wp-block-heading"><a href="' . $url . '">' . esc_html( $svc[0] ) . '</a></h2><!-- /wp:heading -->';
			$html .= '<!-- wp:paragraph --><p>' . esc_html( $svc[1] ) . ' <a href="' . $url . '">Detalji usluge →</a></p><!-- /wp:paragraph -->';
		}
		$html .= '<!-- wp:heading --><h2 class="wp-block-heading">Provjerite dostupnost termina</h2><!-- /wp:heading -->';
		$html .= '<!-- wp:paragraph --><p>Odaberite vrstu događaja, datum i usluge u <a href="' . esc_url( home_url( '/konfigurator/' ) ) . '">konfiguratoru upita</a> i pošaljite upit direktno na WhatsApp, ili nas kontaktirajte na <a href="tel:' . esc_attr( dip_seo_business()['telephone'] ) . '">' . esc_html( dip_seo_business()['phone_disp'] ) . '</a>.</p><!-- /wp:paragraph -->';
		return $html;
	}

	private function images(): array {
		return get_posts(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
			)
		);
	}

	private function alt_of( WP_Post $img ): string {
		$alt = (string) get_post_meta( $img->ID, '_wp_attachment_image_alt', true );
		return '' !== trim( $alt ) ? $alt : (string) $img->post_title;
	}

	private function line( bool $ok, string $msg ) {
		WP_CLI::log( ( $ok ? WP_CLI::colorize( '%G✔%n ' ) : WP_CLI::colorize( '%R✘%n ' ) ) . $msg );
	}
}

WP_CLI::add_command( 'dip-seo', 'DIP_SEO_CLI' );
