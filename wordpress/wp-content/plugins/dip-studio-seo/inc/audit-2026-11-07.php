<?php
/**
 * 1.4.0 — fixes from the "SEO, Technical & GEO/AEO Re-Audit" (7 Nov 2026) that 1.3.0 did not cover.
 * Each one has its own switch under Tools → DIP SEO → "Treći audit".
 *
 *  price_block    "Cijena usluge: … X–Y KM" block on service pages that have no price section yet
 *                 (range read from the live /usluge/ table via dip_seo_price_map()).
 *  h1_split       Service-page <h1> that also holds the tagline → clean <h1> + lead paragraph.
 *  link_fix       Links to /projekti/ and date-prefixed posts point at their final URL;
 *                 id="cjenovnik" on the /usluge/ price heading so /usluge/#cjenovnik works.
 *  kontakt_nap    /kontakt/: contact block with H2 (name, 71000 Sarajevo, phone, e-mail, booking steps).
 *  review_block   Blog posts: "Stručni pregled sadržaja: DIP Studio Sarajevo" + published/updated dates.
 *  person_schema  Person (Ajla Husagić) JSON-LD on /usluge/profesionalni-fotograf/.
 */

defined( 'ABSPATH' ) || exit;

/** Slug of the dip_service being viewed, or ''. */
function dip_seo_current_service_slug(): string {
	if ( ! is_singular( 'dip_service' ) ) {
		return '';
	}
	$post = get_queried_object();
	return $post instanceof WP_Post ? (string) $post->post_name : '';
}

/* Whole-page pass: price block, H1, links, #cjenovnik. */
add_action(
	'template_redirect',
	static function () {
		if ( is_admin() || wp_doing_ajax() || is_feed() || is_404() ) {
			return;
		}
		$on = array(
			'price_block' => dip_studio_seo_setting( 'price_block' ),
			'h1_split'    => dip_studio_seo_setting( 'h1_split' ),
			'link_fix'    => dip_studio_seo_setting( 'link_fix' ),
		);
		if ( ! array_filter( $on ) ) {
			return;
		}
		$slug  = dip_seo_current_service_slug();
		$title = '' !== $slug ? wp_strip_all_tags( get_the_title( get_queried_object_id() ) ) : '';
		$host  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$hub   = is_page( 'usluge' );
		$block = '';
		if ( '' !== $slug && $on['price_block'] ) {
			$map   = dip_seo_price_map();
			$block = isset( $map[ $slug ] ) ? dip_seo_price_block_html( $title ?: $map[ $slug ][0], (string) $map[ $slug ][1], home_url( '/' ) ) : '';
		}

		ob_start(
			static function ( $html ) use ( $on, $slug, $title, $host, $hub, $block ) {
				if ( ! is_string( $html ) || false === stripos( $html, '<html' ) ) {
					return $html;
				}
				if ( $on['link_fix'] ) {
					$html = dip_seo_rewrite_internal_links( $html, $host );
					if ( $hub ) {
						$html = dip_seo_ensure_cjenovnik_anchor( $html );
					}
				}
				if ( '' !== $slug && $on['h1_split'] ) {
					$html = dip_seo_split_stuffed_h1( $html, $title );
				}
				if ( '' !== $block ) {
					$html = dip_seo_insert_price_block( $html, $block );
				}
				return $html;
			}
		);
	},
	97
);

/* /kontakt/ contact block and blog reviewer block. */
add_filter(
	'the_content',
	static function ( $content ) {
		if ( is_admin() || is_feed() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		if ( is_page( 'kontakt' ) && dip_studio_seo_setting( 'kontakt_nap' ) && false === strpos( $content, 'dip-nap' ) ) {
			return $content . dip_seo_nap_html();
		}
		if ( is_singular( 'post' ) && dip_studio_seo_setting( 'review_block' ) && false === strpos( $content, 'dip-review' ) ) {
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
	if ( ! empty( $b['hours'] ) ) {
		$html .= '<h3>Radno vrijeme</h3><ul>';
		foreach ( $b['hours'] as $h ) {
			$html .= '<li>' . esc_html( $h[0] . ': ' . $h[1] . '–' . $h[2] ) . '</li>';
		}
		$html .= '</ul>';
	}
	if ( ! $b['street'] ) {
		$html .= '<p>Usluge izvodimo na lokaciji vašeg događaja u Sarajevu i širom Bosne i Hercegovine.</p>';
	}
	$html .= '<h2>Kako rezervisati termin</h2><ol>'
		. '<li><strong>Upit</strong> – pošaljite datum, lokaciju i usluge kroz <a href="' . esc_url( home_url( '/konfigurator/' ) ) . '">konfigurator upita</a> ili na WhatsApp.</li>'
		. '<li><strong>Provjera</strong> – provjeravamo dostupnost termina i uslove prostora.</li>'
		. '<li><strong>Dogovor</strong> – termin je rezervisan tek nakon dogovora.</li></ol></section>';
	return $html;
}

/* Person schema for the photographer. */
add_action(
	'wp_head',
	static function () {
		if ( ! dip_studio_seo_setting( 'person_schema' ) || 'profesionalni-fotograf' !== dip_seo_current_service_slug() ) {
			return;
		}
		$p    = dip_seo_business()['photographer'];
		$json = wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@type'    => 'Person',
				'@id'      => home_url( '/usluge/profesionalni-fotograf/#ajla-husagic' ),
				'name'     => $p['name'],
				'jobTitle' => $p['job'],
				'url'      => home_url( '/usluge/profesionalni-fotograf/' ),
				'sameAs'   => $p['same_as'],
				'worksFor' => array( '@id' => home_url( '/#organization' ) ),
			),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
		);
		echo "\n<script type=\"application/ld+json\" data-dip-seo=\"person\">" . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	},
	20
);
