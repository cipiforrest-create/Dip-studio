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
		'Circular video booth platform with a credible camera arm and safe space around it — prostor' => '360 Video Booth za dinamične slow-motion video snimke gostiju na proslavi',
		'Elegant photo booth kiosk, simple backdrop, real camera and clear guest access — jesu li'    => 'Moderan Photobooth foto kutak sa pozadinom i rasvjetom za vjenčanja i proslave – DIP Studio Sarajevo',
		'Elegant photo booth kiosk, simple backdrop, real camera and clear guest access — photobooth' => 'Najam Photobooth kabine za trenutno štampanje fotografija i uspomene gostiju na svadbi',
		'Elegant photo booth kiosk, simple backdrop, real camera and clear guest access — atrakcije' => 'Photobooth kutak za fotografisanje gostiju sa rekvizitima na korporativnom događaju i vjenčanju',
		'Event planning desk with blank room floorplan'                                               => 'Organizacija i koordinacija vjenčanja – planiranje rasporeda sale i satnice događaja u BiH – DIP Studio',
		'Dense warm pinlights forming a starry ceiling canopy'                                        => 'Dekorativna rasvjeta zvjezdano nebo iznad plesnog podija i mladenačkog stola u svečanoj sali',
		'Daytime outdoor colored aerial smoke plumes'                                                 => 'Dnevni vatromet u boji sa gustim dimnim fontanama na otvorenom za vjenčanje i proslavu',
		'Low white floor-hugging fog around a couple'                                                 => 'Niski teški dim za prvi ples mladenaca – efekat plesa na oblacima uz pod svečane sale',
		'Two carefully positioned indoor cold-spark fountains'                                        => 'Hladne prskalice (scenske fontane) postavljene uz plesni podij za prvi ples mladenaca u zatvorenoj sali',
		'A celebratory controlled release of small white and silver balloons'                         => 'Balonska eksplozija bijelih i srebrnih balona iznad plesnog podija tokom svečanog ulaska i prvog plesa',
		// 1.3.0: the prefixes the first table missed (second audit, 6 Nov 2026).
		'Elegant contemporary corporate event venue with blank stage screen'                          => 'Sala za korporativni događaj s binom i praznim projekcijskim platnom',
		'Elegant outdoor wedding garden with paths, tables and sheltered lighting'                    => 'Vrt za vjenčanje na otvorenom sa stazama, stolovima i rasvjetom',
		'Warm architectural uplighting that separates zones of an elegant event venue'                => 'Topla ambijentalna rasvjeta zidova koja dijeli salu na zone',
		'Blue and green laser fans above an empty controlled dance floor'                             => 'Plavi i zeleni laserski snopovi iznad plesnog podija',
		'Controlled white CO2 vapor plume directed above a distant adult party crowd'                 => 'Bijeli mlaz CO2 party guna iznad gostiju na proslavi',
		'Ivory rotary audio guestbook phone on a reception table'                                     => 'Audio Guestbook telefon za glasovne poruke gostiju na stolu',
		'Warm white illuminated draped canopy over an elegant reception area'                         => 'Svjetleći baldahin od toplih bijelih lampica iznad svečanog prostora',
		'Professional gold and white aerial fireworks well beyond an outdoor wedding venue at night'  => 'Zlatni i bijeli noćni vatromet iznad mjesta vjenčanja na otvorenom',
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

/* ---------------------------------------------------------------------------------------------
 * 1.4.0 — re-audit (7 Nov 2026) helpers. Pure; unit-tested in tests/run.php.
 * Prices always come from the live /usluge/ table (dip_seo_price_map()), never typed here.
 * ------------------------------------------------------------------------------------------- */

/** "0–2.000 KM" → "do 2.000 KM", everything else unchanged (same rule as dip_seo_range_display). */
function dip_seo_range_text( string $range ): string {
	return (string) preg_replace( '/^\s*0\s*(?:–|-)\s*/u', 'do ', trim( $range ) );
}

/**
 * "## Cijena usluge" block in the same shape as the one on /usluge/vatropad/.
 * $name = service name, $range = "200–300 KM" from the price map.
 */
function dip_seo_price_block_html( string $name, string $range, string $base_url = 'https://dipstudio.ba/' ): string {
	$range = dip_seo_range_text( $range );
	if ( '' === $range || ! preg_match( '/\d/', $range ) ) {
		return '';
	}
	$name = trim( (string) preg_replace( '/\s*\(.*\)$/u', '', $name ) );
	$base = rtrim( $base_url, '/' );
	$esc  = static function ( $s ) {
		return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
	};
	return '<section class="dip-price-block" id="cijena">'
		. '<h2>Cijena usluge: ' . $esc( $name ) . ' ' . $esc( $range ) . '</h2>'
		. '<p>Okvirna cijena za uslugu ' . $esc( $name ) . ' iznosi <strong>' . $esc( $range ) . '</strong>. '
		. 'Tačan iznos unutar raspona zavisi od obima usluge, lokacije, trajanja i specifičnih zahtjeva, a termin se potvrđuje tek nakon dogovora s DIP Studiom.</p>'
		. '<p><a href="' . $esc( $base . '/konfigurator/' ) . '">Provjerite dostupnost termina</a> · '
		. '<a href="' . $esc( $base . '/usluge/#cjenovnik' ) . '">Cjenovnik svih usluga</a></p>'
		. '</section>';
}

/** True when the page already has a price heading ("Cijena usluge…" / "Cjenovnik…") or a price block. */
function dip_seo_has_price_block( string $html ): bool {
	return false !== strpos( $html, 'dip-price-block' )
		|| (bool) preg_match( '#<h([23])\b[^>]*>(?:(?!</h\1>).)*?(?:cijena usluge|cjenovnik)#isu', $html );
}

/**
 * Inserts $block before the first FAQ heading ("… pitanja"), else before </main>, else leaves the page.
 * Works on the finished page HTML.
 */
function dip_seo_insert_price_block( string $html, string $block ): string {
	if ( '' === $block || dip_seo_has_price_block( $html ) ) {
		return $html;
	}
	$start = stripos( $html, '<main' );
	$start = false === $start ? 0 : $start;
	if ( preg_match( '#<h2\b[^>]*>(?:(?!</h2>).)*pitanja#isu', $html, $m, PREG_OFFSET_CAPTURE, $start ) ) {
		$pos = $m[0][1];
	} else {
		$pos = strripos( $html, '</main>' );
		if ( false === $pos ) {
			return $html;
		}
	}
	return substr( $html, 0, $pos ) . $block . substr( $html, $pos );
}

/**
 * An <h1> that also contains the page's tagline/intro ("Photobooth Zabavni sadržaj i uspomene …").
 * Keeps the service name in the <h1> and moves the rest into <p class="dip-h1-lead"> right after it.
 * Only acts when the H1 has more than $max_words words. Split point, in order: first <br>, first
 * inline child element after leading text, or right after $title when the H1 starts with the title.
 */
function dip_seo_split_stuffed_h1( string $html, string $title = '', int $max_words = 6 ): string {
	if ( ! preg_match( '#<h1\b([^>]*)>(.*?)</h1\s*>#is', $html, $m, PREG_OFFSET_CAPTURE ) ) {
		return $html;
	}
	$attrs = $m[1][0];
	$inner = $m[2][0];
	$words = preg_split( '/\s+/u', trim( dip_seo_plain( $inner ) ), -1, PREG_SPLIT_NO_EMPTY );
	if ( count( $words ) <= $max_words ) {
		return $html;
	}
	$head = null;
	$tail = null;
	if ( preg_match( '#^(.*?\S.*?)\s*<br\b[^>]*>\s*(.+)$#is', $inner, $p ) && '' !== trim( strip_tags( $p[1] ) ) ) {
		list( , $head, $tail ) = $p;
	} elseif ( preg_match( '#^([^<]*\S[^<]*?)\s*(<(?:span|small|em|strong|i|b|p|div)\b.+)$#is', $inner, $p ) ) {
		list( , $head, $tail ) = $p;
	} elseif ( '' !== $title && 0 === mb_stripos( ltrim( $inner ), $title, 0, 'UTF-8' ) ) {
		$lead = ltrim( $inner );
		$head = mb_substr( $lead, 0, mb_strlen( $title, 'UTF-8' ), 'UTF-8' );
		$tail = ltrim( mb_substr( $lead, mb_strlen( $title, 'UTF-8' ), null, 'UTF-8' ), " \t\n\r\0\x0B:–-|" );
	}
	if ( null === $head || '' === trim( strip_tags( (string) $tail ) ) ) {
		return $html;
	}
	$wrap = preg_match( '#<(?:p|div)\b#i', $tail ) ? 'div' : 'p';
	$new  = '<h1' . $attrs . '>' . trim( $head ) . '</h1><' . $wrap . ' class="dip-h1-lead">' . trim( $tail ) . '</' . $wrap . '>';
	return substr( $html, 0, $m[0][1] ) . $new . substr( $html, $m[0][1] + strlen( $m[0][0] ) );
}

/**
 * Internal links that point at redirecting URLs, rewritten to the final URL so crawlers don't follow
 * 301 chains: /projekti/ → /galerija/ and /YYYY/MM/DD/slug/ → /slug/.
 */
function dip_seo_rewrite_internal_links( string $html, string $host = 'dipstudio.ba' ): string {
	$h      = preg_quote( $host, '~' );
	$prefix = '(href=["\'](?:https?://(?:www\.)?' . $h . ')?)';
	$html   = (string) preg_replace( '~' . $prefix . '/projekti/?(["\'#?])~i', '$1/galerija/$2', $html );
	return (string) preg_replace( '~' . $prefix . '/\d{4}/\d{2}/\d{2}/([^/"\'#?]+)/?(["\'#?])~i', '$1/$2/$3', $html );
}

/** /usluge/#cjenovnik is linked from the homepage: make sure the "Cjenovnik usluga" heading has that id. */
function dip_seo_ensure_cjenovnik_anchor( string $html ): string {
	if ( preg_match( '#\bid=["\']cjenovnik["\']#i', $html ) ) {
		return $html;
	}
	return (string) preg_replace_callback(
		'#<(h[2-3])\b([^>]*)>((?:(?!</\1>).)*cjenovnik(?:(?!</\1>).)*)</\1>#isu',
		static function ( $m ) {
			if ( preg_match( '#\bid=#i', $m[2] ) ) {
				return $m[0];
			}
			return '<' . $m[1] . ' id="cjenovnik"' . $m[2] . '>' . $m[3] . '</' . $m[1] . '>';
		},
		$html,
		1
	);
}

/** Counts H2s, FAQ sections and related-services sections (duplicated blocks check). */
function dip_seo_count_sections( string $html ): array {
	preg_match_all( '#<h2\b[^>]*>(.*?)</h2>#is', $html, $m );
	$faq = 0;
	$rel = 0;
	foreach ( $m[1] as $h ) {
		$t = dip_seo_plain( $h );
		if ( false !== mb_strpos( $t, 'pitanja' ) ) {
			++$faq;
		}
		if ( false !== mb_strpos( $t, 'povezane' ) ) {
			++$rel;
		}
	}
	return array( 'h2' => count( $m[1] ), 'faq' => $faq, 'related' => $rel );
}

/**
 * /llms.txt: entity summary, every service with its live KM range, 36 cities, booking steps, contact.
 *
 * @param array $prices slug => [name, range] (dip_seo_price_map()).
 */
function dip_seo_llms_txt( string $base_url, array $prices ): string {
	$b    = dip_seo_business();
	$base = rtrim( $base_url, '/' );
	$out  = array();

	$out[] = '# DIP Studio Sarajevo (dipstudio.ba)';
	$out[] = '';
	$out[] = '> DIP Studio pruža specijalne efekte, scensku i ambijentalnu rasvjetu, profesionalne vatromete, Photobooth, 360 Video Booth, Audio Guestbook, profesionalno fotografisanje i organizaciju/koordinaciju za vjenčanja, proslave i korporativne događaje u Sarajevu i širom Bosne i Hercegovine. Cijene su izražene u konvertibilnim markama (KM).';
	$out[] = '';
	$out[] = '## Osnovni podaci i kontakt';
	$out[] = '';
	$out[] = '- **Naziv:** ' . $b['name'];
	$out[] = '- **Web:** ' . $base . '/';
	$out[] = '- **Telefon / WhatsApp:** ' . $b['phone_disp'] . ' (' . $b['wa'] . ')';
	$out[] = '- **E-mail:** ' . $b['email'];
	$out[] = '- **Instagram:** ' . implode( ', ', $b['same_as'] );
	$out[] = '- **Sjedište:** ' . ( $b['street'] ? $b['street'] . ', ' : '' ) . $b['postal'] . ' ' . $b['locality'] . ', Bosna i Hercegovina' . ( $b['street'] ? '' : ' (usluge se izvode na lokaciji događaja)' );
	if ( ! empty( $b['hours'] ) ) {
		$out[] = '- **Radno vrijeme:** ' . implode(
			'; ',
			array_map(
				static function ( $h ) {
					return $h[0] . ' ' . $h[1] . '–' . $h[2];
				},
				$b['hours']
			)
		);
	}
	$out[] = '- **Područje rada:** ' . implode( ', ', dip_seo_cities() ) . '.';
	$out[] = '';
	$out[] = '## Kako rezervisati (3 koraka)';
	$out[] = '';
	$out[] = '1. **Upit** – vrsta događaja, datum i usluge kroz konfigurator (' . $base . '/konfigurator/) ili WhatsApp.';
	$out[] = '2. **Provjera** – DIP Studio provjerava dostupnost termina i uslove prostora.';
	$out[] = '3. **Dogovor** – termin je rezervisan tek nakon dogovora; upit nije rezervacija.';
	$out[] = '';
	$out[] = '## Usluge i okvirne cijene';

	$i = 0;
	foreach ( dip_seo_categories() as $cat_slug => $cat ) {
		++$i;
		$hub   = ( isset( $cat['hub'] ) && ! $cat['hub'] ) ? '' : ' (' . $base . '/' . $cat_slug . '/)';
		$out[] = '';
		$out[] = '### ' . $i . '. ' . $cat['name'] . $hub;
		$out[] = '';
		foreach ( $cat['services'] as $slug => $svc ) {
			$range = isset( $prices[ $slug ][1] ) ? dip_seo_range_text( (string) $prices[ $slug ][1] ) : '';
			$out[] = '- [' . $svc[0] . '](' . $base . '/usluge/' . $slug . '/)' . ( '' !== $range ? ' – **' . $range . '**' : '' ) . ': ' . $svc[1];
		}
	}
	$out[] = '';
	$out[] = 'Cijene su okvirni rasponi; tačan iznos zavisi od obima usluge, lokacije, trajanja i zahtjeva, a potvrđuje se u ponudi. Cjenovnik: ' . $base . '/usluge/#cjenovnik';
	$out[] = '';
	$out[] = '## Ostale stranice';
	$out[] = '';
	foreach ( array(
		'vjencanja'              => 'Vjenčanja',
		'korporativni-dogadjaji' => 'Korporativni događaji',
		'proslave'               => 'Proslave',
		'kako-rezervisati'       => 'Kako rezervisati',
		'galerija'               => 'Galerija',
		'blog'                   => 'Blog (vodiči)',
		'o-nama'                 => 'O nama',
		'kontakt'                => 'Kontakt',
	) as $slug => $label ) {
		$out[] = '- [' . $label . '](' . $base . '/' . $slug . '/)';
	}
	return implode( "\n", $out ) . "\n";
}
