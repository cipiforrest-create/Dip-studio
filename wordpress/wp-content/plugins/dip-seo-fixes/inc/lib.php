<?php
/**
 * Pure helper functions (no WordPress calls) so they can be unit-tested with plain PHP.
 */

defined( 'DIP_SEO_TESTING' ) || defined( 'ABSPATH' ) || exit;

/**
 * Audit §1.1 #1: rewrite leaked staging asset URLs (/stagging/wp-content/… and /staging/wp-content/…)
 * to the production /wp-content/ path. Handles absolute, protocol-relative, root-relative and
 * JSON-escaped (https:\/\/…) forms.
 */
function dip_seo_rewrite_staging_urls( string $html, string $host = 'dipstudio.ba' ): string {
	if ( false === stripos( $html, 'ing' ) ) {
		return $html;
	}
	$h = preg_quote( $host, '#' );

	// Absolute or protocol-relative, plain or JSON-escaped slashes.
	$html = preg_replace(
		'#((?:https?:)?(\\\\?/){2}(?:www\.)?' . $h . ')\\\\?/stagg?ing(\\\\?/wp-(?:content|includes)\\\\?/)#i',
		'$1$3',
		$html
	);

	// Root-relative inside attributes: "/stagging/wp-content/…".
	$html = preg_replace( '#(["\'(\s=])/stagg?ing(/wp-(?:content|includes)/)#i', '$1$2', $html );

	return $html;
}

/**
 * Audit §1.2: headings use <br> with no surrounding whitespace, so crawlers/LLM tokenizers read
 * "STVARAMOTRENUTKE". Insert a space before every <br> inside <h1>–<h6> that isn't already
 * preceded by whitespace. Visual rendering is unchanged.
 */
function dip_seo_fix_heading_breaks( string $html ): string {
	if ( false === stripos( $html, '<br' ) ) {
		return $html;
	}
	return preg_replace_callback(
		'#<(h[1-6])\b([^>]*)>(.*?)</\1\s*>#is',
		static function ( $m ) {
			$inner = preg_replace( '#(?<!\s)(<br\b[^>]*>)#i', ' $1', $m[3] );
			return '<' . $m[1] . $m[2] . '>' . $inner . '</' . $m[1] . '>';
		},
		$html
	);
}

/** Normalise HTML/text for loose comparison. */
function dip_seo_plain( string $text ): string {
	$text = html_entity_decode( strip_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = preg_replace( '/\[(?:&hellip;|…|\.\.\.)\]|…|\.\.\.$/u', '', $text );
	$text = preg_replace( '/\s+/u', ' ', $text );
	return trim( mb_strtolower( $text, 'UTF-8' ) );
}

/**
 * Audit §2.5 bug #1: the single-post template prints the excerpt AND the content, so the
 * lead sentence appears twice. True when $content starts with $excerpt (ignoring markup/case).
 */
function dip_seo_excerpt_duplicates_content( string $excerpt, string $content ): bool {
	$e = dip_seo_plain( $excerpt );
	if ( mb_strlen( $e, 'UTF-8' ) < 20 ) {
		return false;
	}
	$c = dip_seo_plain( $content );
	return 0 === strpos( $c, $e );
}

/**
 * Audit §2.5 / checklist #3: map a date-prefixed post path (/2026/10/24/slug/) to its slug.
 * Returns null when the path is not of that shape.
 */
function dip_seo_date_prefixed_slug( string $path ): ?string {
	if ( preg_match( '#^/?\d{4}/\d{2}/\d{2}/([^/]+)/?$#', $path, $m ) ) {
		return $m[1];
	}
	return null;
}

/** True for /stagging/… and /staging/… (audit checklist #1 → 410 Gone). */
function dip_seo_is_staging_path( string $path ): bool {
	return (bool) preg_match( '#^/?stagg?ing(?:/|$)#i', $path );
}

/* ---------------------------------------------------------------------------------------------
 * Gallery alt-text remediation (audit §2.6)
 * ------------------------------------------------------------------------------------------- */

/** Exact replacements from the audit table, keyed by the leaked English prompt prefix. */
function dip_seo_alt_prompt_map(): array {
	return array(
		'Circular video booth platform with a credible camera arm and safe space around it — jesu li' => '360 Video Booth platforma sa rotirajućom kamerom za snimanje gostiju na vjenčanju u Sarajevu – DIP Studio',
		'Circular video booth platform with a credible camera arm and safe space around it — prostor' => '360 Video Booth atrakcija za dinamične slow-motion video snimke gostiju na proslavi',
		'Elegant photo booth kiosk, simple backdrop, real camera and clear guest access — jesu li'    => 'Moderan Photobooth foto kutak sa pozadinom i rasvjetom za vjenčanja i proslave – DIP Studio Sarajevo',
		'Elegant photo booth kiosk, simple backdrop, real camera and clear guest access — photobooth' => 'Najam Photobooth kabine za trenutno štampanje fotografija i uspomene gostiju na svadbi',
		'Elegant photo booth kiosk, simple backdrop, real camera and clear guest access — atrakcije' => 'Photobooth kutak za fotografisanje gostiju sa rekvizitima na korporativnom događaju i vjenčanju',
		'Event planning desk with blank room floorplan'                                               => 'Organizacija i koordinacija vjenčanja – planiranje rasporeda sale i satnice događaja u BiH – DIP Studio',
		'Dense warm pinlights forming a starry ceiling canopy'                                        => 'Dekorativna rasvjeta zvjezdano nebo iznad plesnog podija i mladenačkog stola u svečanoj sali',
		'Daytime outdoor colored aerial smoke plumes'                                                 => 'Dnevni vatromet u boji sa gustim dimnim fontanama na otvorenom za vjenčanje i proslavu',
		'Low white floor-hugging fog around a couple'                                                 => 'Niski teški dim za prvi ples mladenaca – efekat plesa na oblacima uz pod svečane sale',
		'Two carefully positioned indoor cold-spark fountains'                                        => 'Hladne prskalice (scenske fontane) postavljene uz plesni podij za prvi ples mladenaca u zatvorenoj sali',
		'A celebratory controlled release of small white and silver balloons'                         => 'Balonska eksplozija bijelih i srebrnih balona iznad plesnog podija tokom svečanog ulaska i prvog plesa',
	);
}

/** Restores Bosnian diacritics for words that appear in slug-generated titles. */
function dip_seo_diacritics_map(): array {
	return array(
		'svjetleci'   => 'svjetleći',
		'mladenacki'  => 'mladenački',
		'mladenackog' => 'mladenačkog',
		'rotirajuce'  => 'rotirajuće',
		'baloncici'   => 'balončići',
		'magicni'     => 'magični',
		'vjencanje'   => 'vjenčanje',
		'vjencanja'   => 'vjenčanja',
		'vjetrenjaca' => 'vjetrenjača',
		'svijece'     => 'svijeće',
		'nocni'       => 'noćni',
		'cestitke'    => 'čestitke',
		'dogadjaj'    => 'događaj',
		'dogadjaji'   => 'događaji',
		'dogadjaja'   => 'događaja',
		'cb'          => '', // Stray filename suffix ("Niski Dim Prskalice Cb").
	);
}

/**
 * Classifies an attachment alt/title and proposes a fix.
 *
 * @return array{type:string, suggestion:?string, auto:bool}
 *   type: ok | prompt | slug | hash | ui_icon
 *   auto: whether the suggestion is safe to apply without human review.
 */
function dip_seo_classify_alt( string $alt ): array {
	$alt = trim( $alt );

	if ( preg_match( '/^(website header preview|apple touch icon)/i', $alt ) ) {
		return array( 'type' => 'ui_icon', 'suggestion' => null, 'auto' => false );
	}
	if ( preg_match( '/^file\s*0{4,}[0-9a-f]+/i', $alt ) || preg_match( '/^[0-9a-f]{16,}$/i', $alt ) ) {
		return array( 'type' => 'hash', 'suggestion' => null, 'auto' => false );
	}
	foreach ( dip_seo_alt_prompt_map() as $prefix => $replacement ) {
		if ( 0 === stripos( $alt, $prefix ) ) {
			return array( 'type' => 'prompt', 'suggestion' => $replacement, 'auto' => true );
		}
	}
	// Any other "English sentence — bosnian heading" pattern: flag for manual rewrite.
	if ( preg_match( '/^[A-Z][a-z]+(?: [a-z,\-]+){4,}.* — /u', $alt ) ) {
		return array( 'type' => 'prompt', 'suggestion' => null, 'auto' => false );
	}
	if ( preg_match( '/^dip galerija\s+/i', $alt ) ) {
		$words = preg_split( '/\s+/', trim( preg_replace( '/^dip galerija\s+/i', '', $alt ) ) );
		$map   = dip_seo_diacritics_map();
		$out   = array();
		foreach ( $words as $w ) {
			$lw = mb_strtolower( $w, 'UTF-8' );
			$r  = array_key_exists( $lw, $map ) ? $map[ $lw ] : $lw;
			if ( '' !== $r ) {
				$out[] = $r;
			}
		}
		$text = implode( ' ', $out );
		$text = mb_strtoupper( mb_substr( $text, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $text, 1, null, 'UTF-8' );
		// Slug titles lose context; add brand + city so the alt is descriptive. Human review recommended.
		return array( 'type' => 'slug', 'suggestion' => $text . ' – DIP Studio Sarajevo', 'auto' => false );
	}
	return array( 'type' => 'ok', 'suggestion' => null, 'auto' => false );
}
