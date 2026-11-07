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

/** True when a published page exists at $path (e.g. "usluge/vatropad"). Cached per request. */
function dip_seo_page_exists( string $path ): bool {
	static $cache = array();
	if ( ! isset( $cache[ $path ] ) ) {
		$p              = get_page_by_path( trim( $path, '/' ) );
		$cache[ $path ] = $p && 'publish' === $p->post_status;
	}
	return $cache[ $path ];
}

/** Service slug when the request is /usluge/{slug}/ for one of the 23 services, else null. */
function dip_seo_current_service(): ?string {
	if ( ! preg_match( '#^/usluge/([^/]+)/?$#', dip_seo_request_path(), $m ) ) {
		return null;
	}
	$slug = $m[1];
	$map  = dip_seo_legacy_slugs();
	if ( isset( $map[ $slug ] ) ) {
		$slug = $map[ $slug ]; // Legacy page still live: treat it as its new service.
	}
	return isset( dip_seo_services()[ $slug ] ) ? $slug : null;
}

/* -------------------------------------------------------------------------------------------
 * llms.txt (audit §5.3, re-audit §6) — generated from inc/services.php incl. KM prices.
 * ----------------------------------------------------------------------------------------- */
add_action(
	'init',
	static function () {
		if ( '/llms.txt' !== dip_seo_request_path() ) {
			return;
		}
		status_header( 200 );
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' ); // Keep it out of the web index; AI fetchers still read it.
		header( 'Cache-Control: public, max-age=3600' );
		echo dip_seo_llms_txt( home_url( '/' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- text/markdown.
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
		if ( ! $public ) {
			return $output; // "Discourage search engines" is on — respect it.
		}
		$lines = array(
			'User-agent: *',
			'Disallow: /wp-admin/',
			'Allow: /wp-admin/admin-ajax.php',
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
			'User-agent: Applebot',
			'Allow: /',
			'',
			'User-agent: Applebot-Extended',
			'Allow: /',
			'',
			'Sitemap: ' . home_url( '/sitemap_index.xml' ),
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
		// Re-audit §1: also inaccessible. Temporary until the pages are (re)published.
		'/paketi'                 => array( '/usluge/#cjenovnik', 302 ),
		'/usluge/360-video-booth' => array( '/usluge/photobooth/', 302 ),
	);
	return apply_filters( 'dip_seo_404_fallbacks', $map );
}

add_action(
	'template_redirect',
	static function () {
		$path = dip_seo_request_path();

		// 1. Staging install removed → 410 Gone so search engines drop the URLs.
		if ( dip_seo_is_staging_path( $path ) ) {
			status_header( 410 );
			nocache_headers();
			header( 'X-Robots-Tag: noindex, nofollow' );
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo '410 Gone';
			exit;
		}

		// 2. Date-prefixed duplicate of a post → 301 to the canonical /%postname%/ URL.
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

		// 4. Re-audit §2: legacy compound slugs. Once the page has been renamed (wp dip-seo
		//    rename-legacy-slugs), the old URL 301s to the new one; until then the new URL (already
		//    used by schema/llms.txt) 302s to the old page so nothing 404s.
		foreach ( dip_seo_legacy_slugs() as $old => $new ) {
			$key = untrailingslashit( $path );
			if ( "/usluge/{$old}" === $key && dip_seo_page_exists( "usluge/{$new}" ) ) {
				wp_safe_redirect( home_url( "/usluge/{$new}/" ), 301, 'DIP SEO' );
				exit;
			}
			if ( "/usluge/{$new}" === $key && is_404() && dip_seo_page_exists( "usluge/{$old}" ) ) {
				wp_safe_redirect( home_url( "/usluge/{$old}/" ), 302, 'DIP SEO' );
				exit;
			}
		}

		// 5. 404 fallbacks for orphaned nav targets.
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
 * Output rewrites: staging asset URLs + heading <br> tokenization (audit §1.1 #1, §1.2), internal
 * links to redirecting URLs, #cjenovnik anchor, stuffed service-page H1s and "Cijene na upit"
 * (re-audit §2–§3).
 * The staging rewrite is a safety net — still run the DB search-replace from the README.
 * ----------------------------------------------------------------------------------------- */
add_action(
	'template_redirect',
	static function () {
		if ( is_admin() || wp_doing_ajax() || is_customize_preview() ) {
			return;
		}
		$host    = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$path    = dip_seo_request_path();
		$service = dip_seo_current_service();
		$title   = is_singular() ? wp_strip_all_tags( get_the_title( get_queried_object_id() ) ) : '';
		$renamed = array();
		foreach ( dip_seo_legacy_slugs() as $old => $new ) {
			if ( dip_seo_page_exists( "usluge/{$new}" ) ) {
				$renamed[ "/usluge/{$old}/" ] = "/usluge/{$new}/";
			}
		}
		ob_start(
			static function ( $html ) use ( $host, $path, $service, $title, $renamed ) {
				if ( ! is_string( $html ) || '' === $html || false === stripos( $html, '<html' ) ) {
					return $html;
				}
				$html = dip_seo_rewrite_staging_urls( $html, $host );
				$html = dip_seo_fix_heading_breaks( $html );
				$html = dip_seo_rewrite_internal_links( $html, $host, $renamed );
				if ( '/usluge/' === trailingslashit( $path ) ) {
					$html = dip_seo_ensure_cjenovnik_anchor( $html );
				}
				if ( $service && apply_filters( 'dip_seo_split_h1', true, $service ) ) {
					$html = dip_seo_split_stuffed_h1( $html, $title );
				}
				if ( $service && ( $price = dip_seo_price_text( $service ) ) ) {
					$html = dip_seo_replace_na_upit( $html, $price );
				}
				return $html;
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
		if ( is_admin() || ! is_singular( 'post' ) || ! did_action( 'wp_head' ) || doing_action( 'wp_head' ) ) {
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

/* -------------------------------------------------------------------------------------------
 * Content additions (re-audit §3, §5, §7).
 * ----------------------------------------------------------------------------------------- */
add_filter(
	'the_content',
	static function ( $content ) {
		if ( is_admin() || ! in_the_loop() || ! is_main_query() || is_feed() ) {
			return $content;
		}

		// "## Cijena usluge" block on every service page that doesn't have one yet (action #7).
		if ( is_page() && ( $service = dip_seo_current_service() ) ) {
			return dip_seo_insert_price_block( $content, dip_seo_price_block_html( $service, home_url( '/' ) ) );
		}

		// NAP block with H2 on /kontakt/ (action #12). Street/hours appear once set in services.php.
		if ( is_page( 'kontakt' ) && false === stripos( $content, 'dip-nap' ) && apply_filters( 'dip_seo_kontakt_nap', true ) ) {
			return $content . dip_seo_nap_html();
		}

		// Reviewer + dates block on blog posts (action #12, re-audit §7).
		if ( is_singular( 'post' ) && apply_filters( 'dip_seo_post_review_block', true ) ) {
			$post = get_post();
			return $content . sprintf(
				'<aside class="dip-review"><p><strong>Stručni pregled sadržaja: DIP Studio Sarajevo</strong> – tim koji izvodi specijalne efekte, rasvjetu i vatromete na događajima širom BiH.</p><p>Objavljeno: <time datetime="%1$s">%2$s</time> · Ažurirano: <time datetime="%3$s">%4$s</time></p></aside>',
				esc_attr( get_the_date( 'c', $post ) ),
				esc_html( get_the_date( 'j. n. Y.', $post ) ),
				esc_attr( get_the_modified_date( 'c', $post ) ),
				esc_html( get_the_modified_date( 'j. n. Y.', $post ) )
			);
		}
		return $content;
	},
	20
);

function dip_seo_nap_html(): string {
	$b    = dip_seo_business();
	$addr = ( $b['street'] ? esc_html( $b['street'] ) . '<br>' : '' ) . esc_html( $b['postal'] . ' ' . $b['locality'] ) . ', Bosna i Hercegovina';
	$html = '<section class="dip-nap"><h2>Kontakt podaci DIP Studio Sarajevo</h2><p><strong>' . esc_html( $b['name'] ) . '</strong><br>' . $addr . '</p>'
		. '<p>Telefon / WhatsApp: <a href="tel:' . esc_attr( $b['telephone'] ) . '">' . esc_html( $b['phone_disp'] ) . '</a><br>'
		. 'E-mail: <a href="mailto:' . esc_attr( $b['email'] ) . '">' . esc_html( $b['email'] ) . '</a></p>';
	if ( $b['hours'] ) {
		$html .= '<h3>Radno vrijeme</h3><ul>';
		foreach ( $b['hours'] as $h ) {
			$html .= '<li>' . esc_html( $h[0] . ': ' . $h[1] . '–' . $h[2] ) . '</li>';
		}
		$html .= '</ul>';
	}
	if ( ! $b['street'] ) {
		$html .= '<p>Usluge izvodimo na lokaciji vašeg događaja u Sarajevu i u 36 gradova širom BiH.</p>';
	}
	$html .= '<h2>Kako rezervisati termin</h2><ol><li><strong>Upit</strong> – pošaljite datum, lokaciju i usluge putem <a href="' . esc_url( home_url( '/konfigurator/' ) ) . '">konfiguratora</a> ili WhatsAppa.</li>'
		. '<li><strong>Provjera</strong> – provjeravamo dostupnost termina i uslove prostora.</li>'
		. '<li><strong>Dogovor</strong> – termin je potvrđen tek nakon dogovora.</li></ol></section>';
	return $html;
}
