<?php
/**
 * WP-CLI commands: `wp dip-seo check|audit-pages|cleanup|create-hubs|gallery-alt`.
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

		$counts = array();
		foreach ( $this->images() as $img ) {
			$t            = dip_seo_classify_alt( $this->alt_of( $img ) )['type'];
			$counts[ $t ] = ( $counts[ $t ] ?? 0 ) + 1;
		}
		$bad = array_sum( $counts ) - ( $counts['ok'] ?? 0 );
		$this->line( 0 === $bad, 'Image alt text: ' . wp_json_encode( $counts ) . ' (run `wp dip-seo gallery-alt` for details)' );
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
	 * Audits (and optionally fixes) image alt text: leaked English AI prompts, slug-style titles,
	 * hash filenames and UI icons (audit §2.6, checklist #5).
	 *
	 * ## OPTIONS
	 *
	 * [--apply]
	 * : Write the exact replacements from the audit table for leaked-prompt alts.
	 *
	 * [--include-slugs]
	 * : With --apply, also write the suggested alt for slug-style titles (review the dry run first).
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
		$rows   = array();
		$wrote  = 0;

		foreach ( $this->images() as $img ) {
			$alt = $this->alt_of( $img );
			$c   = dip_seo_classify_alt( $alt );
			if ( 'ok' === $c['type'] ) {
				continue;
			}
			$action = array(
				'ui_icon' => 'Remove from the /galerija/ gallery block',
				'hash'    => 'Rename file + write alt manually',
				'prompt'  => $c['suggestion'] ? 'Auto-fix' : 'Rewrite manually (unknown prompt)',
				'slug'    => 'Review suggestion',
			)[ $c['type'] ];

			if ( $apply && $c['suggestion'] && ( $c['auto'] || ( $slugs && 'slug' === $c['type'] ) ) ) {
				update_post_meta( $img->ID, '_wp_attachment_image_alt', $c['suggestion'] );
				// Title is often shown as the caption/tooltip in galleries — keep it consistent.
				wp_update_post( array( 'ID' => $img->ID, 'post_title' => $c['suggestion'] ) );
				$action = 'UPDATED';
				++$wrote;
			}

			$rows[] = array(
				'ID'         => $img->ID,
				'type'       => $c['type'],
				'current'    => $alt,
				'suggestion' => (string) $c['suggestion'],
				'action'     => $action,
			);
		}

		\WP_CLI\Utils\format_items( $assoc['format'] ?? 'table', $rows, array( 'ID', 'type', 'current', 'suggestion', 'action' ) );
		WP_CLI::log( sprintf( '%d image(s) need attention; %d updated.', count( $rows ), $wrote ) );
		if ( ! $apply ) {
			WP_CLI::log( 'Dry run. Re-run with --apply to write the audit\'s replacements.' );
		}
	}

	/**
	 * Fetches the live pages named in the audits and reports status, H1 length, H2 count, duplicated
	 * FAQ/related sections, missing price blocks and leftover "na upit" (1.4.0).
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
		foreach ( array_keys( dip_seo_price_map() ) as $slug ) {
			$paths[] = "/usluge/{$slug}/";
		}
		$rows = array();
		foreach ( array_unique( $paths ) as $path ) {
			$res    = wp_remote_get( home_url( $path ), array( 'redirection' => 0, 'timeout' => 20 ) );
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
				if ( 0 === $sec['faq'] && in_array( $path, array( '/usluge/', '/vjencanja/', '/korporativni-dogadjaji/', '/proslave/', '/kako-rezervisati/' ), true ) ) {
					$issues[] = 'no FAQ section';
				}
			}
			$rows[] = array( 'path' => $path, 'status' => $code, 'location' => $loc, 'issues' => implode( '; ', $issues ) );
		}
		\WP_CLI\Utils\format_items( $assoc['format'] ?? 'table', $rows, array( 'path', 'status', 'location', 'issues' ) );
	}

	/**
	 * Trashes a published default "Hello world!" post and flushes object/page caches so every template
	 * shows the current menu (1.4.0).
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
		do_action( 'speedycache_purge_all' ); // SpeedyCache; no-op if absent.
		flush_rewrite_rules( false );
		WP_CLI::success( 'Object cache and rewrite rules flushed. Also purge SpeedyCache (Settings) and Cloudflare.' );
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
