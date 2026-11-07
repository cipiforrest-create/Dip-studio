<?php
/**
 * Front-end fixes: redirects / 410, output rewrites, duplicate lead sentence, robots.txt, llms.txt.
 */

defined( 'ABSPATH' ) || exit;

/** Request path without query string, always starting with "/". */
function dip_seo_request_path(): string {
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
	$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
	$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ); // Handles WP in a subdirectory.
	if ( $home && '/' !== $home && 0 === strpos( $path, rtrim( $home, '/' ) ) ) {
		$path = substr( $path, strlen( rtrim( $home, '/' ) ) );
	}
	return '/' . ltrim( $path, '/' );
}

/* -------------------------------------------------------------------------------------------
 * llms.txt (audit §5.3) — served before WordPress routing.
 * ----------------------------------------------------------------------------------------- */
add_action(
	'init',
	static function () {
		if ( ! dip_studio_seo_setting( 'llms' ) || '/llms.txt' !== dip_seo_request_path() ) {
			return;
		}
		$file = DIP_SEO_DIR . '/llms.txt';
		if ( ! is_readable( $file ) ) {
			return;
		}
		status_header( 200 );
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' ); // Keep it out of the web index; AI fetchers still read it.
		header( 'Cache-Control: public, max-age=3600' );
		readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	},
	0
);

/* -------------------------------------------------------------------------------------------
 * robots.txt (audit §5.3). Only used when no physical robots.txt file exists in the web root.
 *
 * Deviation from the audit: /stagging/ is NOT disallowed. A Disallow stops Google from
 * re-crawling those URLs, so it would never see the 410 and the stale URLs would linger in the
 * index. Letting crawlers fetch the 410 removes them fastest.
 * ----------------------------------------------------------------------------------------- */
add_filter(
	'robots_txt',
	static function ( $output, $public ) {
		if ( ! $public || ! dip_studio_seo_setting( 'robots' ) ) {
			return $output; // "Discourage search engines" is on, or the setting is off.
		}
		$lines = array(
			'User-agent: *',
			'Disallow: /wp-admin/',
			'Allow: /wp-admin/admin-ajax.php',
			'Disallow: /stagging/wp-admin/', // WordPress lives in /stagging/; admin is there.
			'Allow: /stagging/wp-admin/admin-ajax.php',
			'Disallow: /?s=',
			'Disallow: /search/',
			'',
			'User-agent: OAI-SearchBot',
			'Allow: /',
			'',
			'User-agent: ChatGPT-User',
			'Allow: /',
			'',
			'User-agent: GPTBot',
			'Allow: /',
			'',
			'User-agent: PerplexityBot',
			'Allow: /',
			'',
			'User-agent: ClaudeBot',
			'Allow: /',
			'',
			'User-agent: Claude-SearchBot',
			'Allow: /',
			'',
			'User-agent: Google-Extended',
			'Allow: /',
			'',
			'Sitemap: ' . home_url( '/sitemaps.xml' ), // SiteSEO's sitemap index.
			'',
		);
		return apply_filters( 'dip_seo_robots_txt', implode( "\n", $lines ) );
	},
	99,
	2
);

/* -------------------------------------------------------------------------------------------
 * Redirects & 410 (audit §1.1 #1–#3, §2.1 /projekti/, §2.3 hubs, checklist #1 and #3).
 * ----------------------------------------------------------------------------------------- */

/**
 * 404-only fallbacks: they fire ONLY when the URL does not resolve, so publishing a real page at
 * one of these slugs automatically disables its redirect.
 *
 * @return array<string, array{0:string,1:int}> path => [target, status]
 */
function dip_seo_404_fallbacks(): array {
	$map = array(
		'/projekti'               => array( '/galerija/', 301 ),
		'/organizacija-dogadjaja' => array( '/usluge/organizacija-i-koordinacija/', 301 ),
		// Category hubs: temporary until `wp dip-seo create-hubs` pages are published.
		'/specijalni-efekti'      => array( '/usluge/', 302 ),
		'/rasvjeta'               => array( '/usluge/', 302 ),
		'/vatrometi'              => array( '/usluge/', 302 ),
		'/photo-fun'              => array( '/usluge/', 302 ),
	);
	return apply_filters( 'dip_seo_404_fallbacks', $map );
}

add_action(
	'template_redirect',
	static function () {
		$path = dip_seo_request_path();

		// 1. Old /stagging/ front-end URLs → 410 Gone so search engines drop them.
		//    Only WordPress-routed URLs reach this hook; files, wp-admin, wp-login and REST are untouched.
		if ( dip_studio_seo_setting( 'staging_410' ) && dip_seo_is_staging_path( $path ) ) {
			status_header( 410 );
			nocache_headers();
			header( 'X-Robots-Tag: noindex' );
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo '410 Gone';
			exit;
		}

		// 2. Date-prefixed duplicate of a post → 301 to the canonical /%postname%/ URL.
		if ( ! dip_studio_seo_setting( 'redirects' ) ) {
			return;
		}
		$slug = dip_seo_date_prefixed_slug( $path );
		if ( null !== $slug ) {
			$post = is_singular( 'post' ) ? get_queried_object() : get_page_by_path( $slug, OBJECT, 'post' );
			if ( $post instanceof WP_Post && 'publish' === $post->post_status ) {
				$target = get_permalink( $post );
				if ( $target && untrailingslashit( (string) wp_parse_url( $target, PHP_URL_PATH ) ) !== untrailingslashit( $path ) ) {
					wp_safe_redirect( $target, 301, 'DIP SEO' );
					exit;
				}
			}
		}

		// 3. Date archives (/2026/10/24/) → blog index (audit §5.1: date archives must not compete).
		if ( is_date() ) {
			$blog_id = (int) get_option( 'page_for_posts' );
			wp_safe_redirect( $blog_id ? get_permalink( $blog_id ) : home_url( '/' ), 301, 'DIP SEO' );
			exit;
		}

		// 4. 404 fallbacks for orphaned nav targets.
		if ( is_404() ) {
			$key = untrailingslashit( $path );
			$map = dip_seo_404_fallbacks();
			if ( isset( $map[ $key ] ) ) {
				wp_safe_redirect( home_url( $map[ $key ][0] ), $map[ $key ][1], 'DIP SEO' );
				exit;
			}
		}
	},
	1
);

/* -------------------------------------------------------------------------------------------
 * Output rewrites: staging asset URLs + heading <br> tokenization (audit §1.1 #1, §1.2).
 * The staging rewrite is a safety net — still run the DB search-replace from the README.
 * ----------------------------------------------------------------------------------------- */
add_action(
	'template_redirect',
	static function () {
		if ( is_admin() || wp_doing_ajax() || is_customize_preview() || is_feed() ) {
			return;
		}
		$rewrite = dip_studio_seo_setting( 'rewrite_urls' );
		$heading = dip_studio_seo_setting( 'heading_fix' );
		if ( ! $rewrite && ! $heading ) {
			return;
		}
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		ob_start(
			static function ( $html ) use ( $host, $rewrite, $heading ) {
				if ( ! is_string( $html ) || '' === $html ) {
					return $html;
				}
				if ( $rewrite ) {
					$html = dip_seo_rewrite_staging_urls( $html, $host );
				}
				return $heading ? dip_seo_fix_heading_breaks( $html ) : $html;
			}
		);
	},
	99
);

/* -------------------------------------------------------------------------------------------
 * Duplicate opening sentence on single posts (audit §2.5 bug #1).
 * The theme prints the excerpt above the_content(); when the content already starts with that
 * text, suppress the excerpt — but only in the page body (after wp_head), so SEO meta
 * descriptions built from %%post_excerpt%% keep working.
 * ----------------------------------------------------------------------------------------- */
add_filter(
	'get_the_excerpt',
	static function ( $excerpt, $post = null ) {
		if ( ! dip_studio_seo_setting( 'dup_excerpt' ) || is_admin() || ! is_singular( 'post' ) || ! did_action( 'wp_head' ) || doing_action( 'wp_head' ) ) {
			return $excerpt;
		}
		$post = get_post( $post );
		if ( ! $post || (int) $post->ID !== (int) get_queried_object_id() ) {
			return $excerpt;
		}
		return dip_seo_excerpt_duplicates_content( (string) $excerpt, (string) $post->post_content ) ? '' : $excerpt;
	},
	99,
	2
);
