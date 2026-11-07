<?php
/**
 * Fixes from the second client audit ("Post-Pricing Re-Audit", 2026-11-06), each checked on the live site.
 *
 *  price_copy      Old "Cijene su na upit" sentences that now contradict the public KM price list.
 *  blog_prices     Pricing blog post: price table, FAQ answer, links to the service pages.
 *  price_badges    "od 200 KM" on service cards and price ranges in the configurator list.
 *  home_faq        Homepage FAQ (4 questions, built from text already on the site) + FAQPage JSON-LD.
 *  gallery_hide_ai Illustrative AI images (dip-img-scena-*) are not shown on /galerija/ as event photos.
 *  alt_render      English prompt / file-name / empty alt texts fixed in the page HTML.
 *  schema_tidy     One Organization @id, dated BlogPosting @id, Bosnian breadcrumb, 0 KM minPrice.
 *  xml_decl        Removes the stray `<?xml encoding="utf-8" ?>` printed inside page content.
 *
 * Prices are never typed in by hand here: they are read from the "Cjenovnik usluga" table on /usluge/
 * every time that page is generated, so a change in the table reaches every place below.
 */

defined( 'ABSPATH' ) || exit;

const DIP_SEO_PRICE_OPTION = 'dip_seo_price_map';

/* ---------------------------------------------------------------------------------------------
 * Price map
 * ------------------------------------------------------------------------------------------- */

/** Fallback copy of the /usluge/ table (6 Nov 2026), used only until /usluge/ is generated once. */
function dip_seo_default_price_map(): array {
	return array(
		'niski-dim-za-prvi-ples'        => array( 'Niski dim', '200–300 KM' ),
		'hladne-prskalice'              => array( 'Prskalice (par)', '50–100 KM' ),
		'vatrena-vjetrenjaca-lepeza'    => array( 'Vjetrenjača', '300–500 KM' ),
		'balonska-eksplozija'           => array( 'Balonska eksplozija', '300–600 KM' ),
		'vatropad'                      => array( 'Vatropad', '300–500 KM' ),
		'rotirajuce-prskalice-vatropad' => array( 'Rotirajuće prskalice', '200–400 KM' ),
		'lepeza'                        => array( 'Lepeza', '200–300 KM' ),
		'magicni-laser'                 => array( 'Laser', '500–700 KM' ),
		'baloncici'                     => array( 'Balončići', '200–300 KM' ),
		'co2-party-gun'                 => array( 'Party Gun', '200–400 KM' ),
		'photobooth'                    => array( 'Photobooth', '200–600 KM' ),
		'360-video-booth'               => array( '360 Video Booth', '200–500 KM' ),
		'audio-guestbook'               => array( 'Audio Guestbook', '100–300 KM' ),
		'zdravica'                      => array( 'Zdravica', '200–500 KM' ),
		'profesionalni-fotograf'        => array( 'Profesionalni fotograf', '450–2.000 KM' ),
		'vatromet-u-boji'               => array( 'Dnevni vatromet u boji', '500–1.200 KM' ),
		'vatromet-za-vjencanja'         => array( 'Noćni profesionalni vatromet', '500–5.000 KM' ),
		'niski-vatromet'                => array( 'Niski vatromet', '300–500 KM' ),
		'rimske-svijece'                => array( 'Rimske svijeće', '200–400 KM' ),
		'ambijentalna-rasvjeta'         => array( 'Ambijentalna rasvjeta', '300–900 KM' ),
		'zvjezdano-nebo'                => array( 'Zvjezdano nebo', '500–3.000 KM' ),
		'svjetleci-baldahini'           => array( 'Svjetleći baldahini', '1.000–5.000 KM' ),
		'organizacija-i-koordinacija'   => array( 'Organizacija i koordinacija vjenčanja i događaja', '0–2.000 KM' ),
	);
}

/** Reads the "Cjenovnik usluga" table rows: slug => [name, range]. Pure; unit-tested. */
function dip_seo_parse_price_table( string $html ): array {
	$out = array();
	if ( false === strpos( $html, 'dip-pricing' ) ) {
		return $out;
	}
	if ( preg_match_all( '#<th[^>]*>\s*<a[^>]+href="[^"]*/usluge/([a-z0-9-]+)/[^"]*"[^>]*>(.*?)</a>\s*</th>\s*<td[^>]*>(.*?)</td>#is', $html, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $row ) {
			$name  = trim( html_entity_decode( wp_strip_all_tags( $row[2] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			$range = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $row[3] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
			if ( '' !== $name && preg_match( '/\d/', $range ) ) {
				$out[ $row[1] ] = array( $name, $range );
			}
		}
	}
	return $out;
}

function dip_seo_price_map(): array {
	$saved = get_option( DIP_SEO_PRICE_OPTION );
	return ( is_array( $saved ) && count( $saved ) >= 10 ) ? $saved : dip_seo_default_price_map();
}

/** "1.000–5.000 KM" => [1000, 5000]. Pure. */
function dip_seo_range_numbers( string $range ): array {
	if ( ! preg_match_all( '/\d[\d.]*/', $range, $m ) ) {
		return array( 0, 0 );
	}
	$nums = array_map( static function ( $n ) { return (int) str_replace( '.', '', $n ); }, $m[0] );
	return array( $nums[0], end( $nums ) );
}

function dip_seo_km( int $n ): string {
	return number_format( $n, 0, ',', '.' ) . ' KM';
}

/** "200–300 KM" => "od 200 KM"; "0–2.000 KM" => "do 2.000 KM". Pure. */
function dip_seo_range_short( string $range ): string {
	list( $min, $max ) = dip_seo_range_numbers( $range );
	return $min > 0 ? 'od ' . dip_seo_km( $min ) : 'do ' . dip_seo_km( $max );
}

/** "0–2.000 KM" reads like a database error; show "do 2.000 KM". Pure. */
function dip_seo_range_display( string $range ): string {
	list( $min, $max ) = dip_seo_range_numbers( $range );
	return 0 === $min && $max > 0 ? 'do ' . dip_seo_km( $max ) : $range;
}

/* ---------------------------------------------------------------------------------------------
 * Text helpers
 * ------------------------------------------------------------------------------------------- */

/**
 * Regex that matches a plain Bosnian sentence whether the HTML holds "č" or "&#269;" / "&ccaron;".
 * Pure; unit-tested.
 */
function dip_seo_loose_pattern( string $plain ): string {
	$out   = '';
	$chars = preg_split( '//u', $plain, -1, PREG_SPLIT_NO_EMPTY );
	foreach ( $chars as $ch ) {
		if ( ' ' === $ch ) {
			$out .= '(?:\s|&nbsp;|&\#160;)+';
			continue;
		}
		if ( strlen( $ch ) === 1 ) {
			$out .= preg_quote( $ch, '#' );
			continue;
		}
		$cp   = mb_ord( $ch, 'UTF-8' );
		$alts = array( preg_quote( $ch, '#' ), '&\#' . $cp . ';', '&\#x' . dechex( $cp ) . ';', '&\#x' . strtoupper( dechex( $cp ) ) . ';' );
		$named = htmlentities( $ch, ENT_HTML5, 'UTF-8' );
		if ( '&' === substr( $named, 0, 1 ) ) {
			$alts[] = preg_quote( $named, '#' );
		}
		$out .= '(?:' . implode( '|', array_unique( $alts ) ) . ')';
	}
	return '#' . $out . '#u';
}

/** Applies [plain needle => replacement] pairs, entity-tolerant. Pure. */
function dip_seo_replace_loose( string $html, array $pairs ): string {
	foreach ( $pairs as $needle => $replacement ) {
		$out = preg_replace( dip_seo_loose_pattern( $needle ), str_replace( array( '\\', '$' ), array( '\\\\', '\\$' ), $replacement ), $html );
		if ( is_string( $out ) ) {
			$html = $out;
		}
	}
	return $html;
}

/** Splits a page into [head part, body part] so meta tags and JSON-LD are left alone. */
function dip_seo_split_body( string $html ): array {
	$pos = stripos( $html, '<body' );
	return false === $pos ? array( '', $html ) : array( substr( $html, 0, $pos ), substr( $html, $pos ) );
}

/* ---------------------------------------------------------------------------------------------
 * price_copy + blog_prices
 * ------------------------------------------------------------------------------------------- */

/** Leftover "na upit" sentences. Order matters: longer sentences first. Pure; unit-tested. */
function dip_seo_fix_price_copy( string $body, array $map ): string {
	$min_all = PHP_INT_MAX;
	foreach ( $map as $row ) {
		list( $min ) = dip_seo_range_numbers( $row[1] );
		if ( $min > 0 ) {
			$min_all = min( $min_all, $min );
		}
	}
	$from = PHP_INT_MAX === $min_all ? '' : 'Cijene od ' . dip_seo_km( $min_all );

	$body = dip_seo_replace_loose(
		$body,
		array(
			'DIP Studio konkretne cijene dostavlja na upit.'                              => 'Okvirni rasponi cijena za sve usluge objavljeni su u <a href="' . esc_url( home_url( '/usluge/#cjenovnik' ) ) . '">cjenovniku usluga</a>, a tačan iznos potvrđujemo u ponudi za vaš događaj.',
			'Cijena je na upit i zavisi od'                                              => 'Tačan iznos unutar navedenog raspona zavisi od',
			'Cijene su na upit, a dostupnost i uslovi potvrđuju se nakon razgovora.'      => 'Tačna cijena unutar raspona, dostupnost i uslovi potvrđuju se nakon razgovora.',
			'Cijene su na upit, a termin se potvrđuje'                                    => 'Okvirne cijene objavljene su u cjenovniku usluga, a termin se potvrđuje',
			'Cijene su na upit; dostupnost'                                               => 'Okvirne cijene pojedinačnih usluga su u cjenovniku; dostupnost',
			'Cijene su na upit. Slanje upita'                                             => 'Tačna cijena potvrđuje se u ponudi. Slanje upita',
			'Cijene su na upit.'                                                          => 'Okvirne cijene su u cjenovniku, a tačan iznos potvrđujemo u ponudi.',
			'Cijena na upit.'                                                             => 'Tačna cijena potvrđuje se u ponudi.',
			'Cijene na upit.'                                                             => 'Okvirne cijene pojedinačnih usluga su u cjenovniku.',
		)
	);
	if ( '' !== $from ) {
		$body = (string) preg_replace( '#(<p class="dip-inquiry__price">)\s*Cijene na upit\s*(</p>)#u', '$1' . $from . '$2', $body );
	}
	// "0–2.000 KM" => "do 2.000 KM" wherever it is printed.
	$body = (string) preg_replace( '#(?<![\d.])0\s*(?:–|&ndash;|&\#8211;|-)\s*([\d.]+\s*KM)#u', 'do $1', $body );
	return $body;
}

/** Price table markup in the same classes as /usluge/ so the theme styles it. Pure. */
function dip_seo_price_table_html( array $map ): string {
	$rows = '';
	foreach ( $map as $slug => $row ) {
		$rows .= '<tr><th scope="row"><a href="' . esc_url( home_url( '/usluge/' . $slug . '/#cijena' ) ) . '">' . esc_html( $row[0] ) . '</a></th><td>' . esc_html( dip_seo_range_display( $row[1] ) ) . '</td></tr>';
	}
	return '<section class="dip-pricing dip-pricing--table dip-pricing--post"><h2>Cjenovnik usluga</h2><table><caption>Rasponi cijena u konvertibilnim markama (KM)</caption><thead><tr><th scope="col">Usluga</th><th scope="col">Cijena od–do</th></tr></thead><tbody>' . $rows . '</tbody></table><p>Konačna cijena unutar raspona zavisi od obima usluge, lokacije, trajanja i specifičnih zahtjeva.</p></section><style>.dip-pricing--post td{white-space:nowrap}</style>';
}

/** Pricing blog post: table, FAQ answers, links to services. Pure; unit-tested. */
function dip_seo_fix_pricing_post( string $body, array $map ): string {
	$r = static function ( string $slug ) use ( $map ): string {
		return isset( $map[ $slug ] ) ? dip_seo_range_display( $map[ $slug ][1] ) : '';
	};
	$answer = 'Da. Na stranici <a href="' . esc_url( home_url( '/usluge/#cjenovnik' ) ) . '">Usluge</a> objavljen je cjenovnik s rasponima cijena u KM za sve usluge';
	$examples = array_filter(
		array(
			$r( 'niski-dim-za-prvi-ples' ) ? 'niski dim ' . $r( 'niski-dim-za-prvi-ples' ) : '',
			$r( 'hladne-prskalice' ) ? 'hladne prskalice ' . $r( 'hladne-prskalice' ) . ' po paru' : '',
			$r( 'photobooth' ) ? 'Photobooth ' . $r( 'photobooth' ) : '',
		)
	);
	$answer .= $examples ? ', na primjer ' . implode( ', ', $examples ) . '.' : '.';
	$answer .= ' Tačan iznos unutar raspona potvrđujemo u ponudi, prema lokaciji, trajanju i obimu.';

	$link = static function ( string $slug, string $text ): string {
		return '<a href="' . esc_url( home_url( '/usluge/' . $slug . '/' ) ) . '">' . $text . '</a>';
	};

	$body = dip_seo_replace_loose(
		$body,
		array(
			'Na web stranici se cijene usluga dostavljaju na upit, prema konkretnom događaju.' => $answer,
			'Za željenu kombinaciju moguće je poslati upit radi individualne provjere.'        => 'Da. U <a href="' . esc_url( home_url( '/konfigurator/' ) ) . '">konfiguratoru upita</a> odaberite više usluga i pošaljite jedan upit, pa dobijate jednu ponudu za cijelu kombinaciju.',
			'Niski dim, prskalice, ambijentalna rasvjeta i profesionalni vatromet nisu'         => $link( 'niski-dim-za-prvi-ples', 'Niski dim' ) . ', ' . $link( 'hladne-prskalice', 'prskalice' ) . ', ' . $link( 'ambijentalna-rasvjeta', 'ambijentalna rasvjeta' ) . ' i ' . $link( 'vatromet-za-vjencanja', 'profesionalni vatromet' ) . ' nisu',
		)
	);
	if ( false === strpos( $body, 'dip-pricing--post' ) ) {
		$pattern = dip_seo_loose_pattern( '<h2>Vrsta i obim efekta</h2>' );
		$table   = dip_seo_price_table_html( $map );
		$new     = preg_replace( $pattern, str_replace( array( '\\', '$' ), array( '\\\\', '\\$' ), $table ) . '$0', $body, 1 );
		if ( is_string( $new ) ) {
			$body = $new;
		}
	}
	return $body;
}

/* ---------------------------------------------------------------------------------------------
 * price_badges
 * ------------------------------------------------------------------------------------------- */

/** Adds "od 200 KM" under the title of every service card that links to /usluge/<slug>/. Pure. */
function dip_seo_card_prices( string $body, array $map ): string {
	if ( false === strpos( $body, 'dip-card__link' ) ) {
		return $body;
	}
	return (string) preg_replace_callback(
		'#<a class="dip-card__link" href="[^"]*/usluge/([a-z0-9-]+)/"[^>]*>.*?</a>#s',
		static function ( $m ) use ( $map ) {
			if ( empty( $map[ $m[1] ] ) || false !== strpos( $m[0], 'dip-card__price' ) ) {
				return $m[0];
			}
			$badge = '<span class="dip-card__price">' . esc_html( dip_seo_range_short( $map[ $m[1] ][1] ) ) . '</span>';
			$out   = preg_replace( '#(<span class="dip-card__title">.*?</span>)#s', '$1' . $badge, $m[0], 1 );
			return is_string( $out ) ? $out : $m[0];
		},
		$body
	);
}

/**
 * Configurator: "Niski dim / teški dim" => "Niski dim / teški dim (200–300 KM)" in the builder JSON.
 * $slug_of maps a service post id to its slug. The JSON stays ASCII-only (the builder relies on it).
 * Pure; unit-tested.
 */
function dip_seo_configurator_prices( string $body, array $map, callable $slug_of ): string {
	return (string) preg_replace_callback(
		'#(<script[^>]*class="dip-builder__data"[^>]*>)(.*?)(</script>)#s',
		static function ( $m ) use ( $map, $slug_of ) {
			$data = json_decode( $m[2], true );
			if ( ! is_array( $data ) || empty( $data['services'] ) || ! is_array( $data['services'] ) ) {
				return $m[0];
			}
			foreach ( $data['services'] as &$service ) {
				if ( ! is_array( $service ) || empty( $service['id'] ) || ! isset( $service['label'] ) ) {
					continue;
				}
				$slug = (string) $slug_of( (int) $service['id'] );
				if ( '' === $slug || empty( $map[ $slug ] ) || false !== strpos( (string) $service['label'], ' KM)' ) ) {
					continue;
				}
				$service['label'] .= ' (' . dip_seo_range_display( $map[ $slug ][1] ) . ')';
			}
			unset( $service );
			$json = json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
			return false === $json ? $m[0] : $m[1] . $json . $m[3];
		},
		$body
	);
}

/* ---------------------------------------------------------------------------------------------
 * home_faq
 * ------------------------------------------------------------------------------------------- */

/** @return array<int,array{0:string,1:string}> question => answer (answers may contain links). Pure. */
function dip_seo_home_faq_items( array $map ): array {
	$r = static function ( string $slug ) use ( $map ): string {
		return isset( $map[ $slug ] ) ? dip_seo_range_display( $map[ $slug ][1] ) : '';
	};
	$prices = array_filter(
		array(
			$r( 'niski-dim-za-prvi-ples' ) ? 'niski dim za prvi ples ' . $r( 'niski-dim-za-prvi-ples' ) : '',
			$r( 'hladne-prskalice' ) ? 'hladne prskalice ' . $r( 'hladne-prskalice' ) . ' po paru' : '',
			$r( 'photobooth' ) ? 'Photobooth ' . $r( 'photobooth' ) : '',
			$r( '360-video-booth' ) ? '360 Video Booth ' . $r( '360-video-booth' ) : '',
			$r( 'ambijentalna-rasvjeta' ) ? 'ambijentalna rasvjeta ' . $r( 'ambijentalna-rasvjeta' ) : '',
			$r( 'zvjezdano-nebo' ) ? 'zvjezdano nebo ' . $r( 'zvjezdano-nebo' ) : '',
		)
	);
	$usluge = esc_url( home_url( '/usluge/#cjenovnik' ) );
	$konf   = esc_url( home_url( '/konfigurator/' ) );
	return array(
		array(
			'Koliko koštaju usluge DIP Studija?',
			'Okvirne cijene su: ' . implode( ', ', $prices ) . '. Rasponi za sve usluge su u <a href="' . $usluge . '">cjenovniku</a>; tačan iznos zavisi od lokacije, trajanja i obima.',
		),
		array(
			'Od čega zavisi konačna cijena?',
			'Sve cijene su izražene u konvertibilnim markama (KM). Konačna cijena zavisi od obima usluge, lokacije, trajanja i specifičnih zahtjeva klijenta.',
		),
		array(
			'U kojim gradovima radite?',
			'DIP Studio je iz Sarajeva i radi širom Bosne i Hercegovine. Do sada smo radili u više od 25 gradova, među njima Mostar, Tuzla, Zenica, Banja Luka, Bihać, Brčko, Bijeljina, Trebinje, Doboj i Travnik.',
		),
		array(
			'Kako provjeriti dostupnost i rezervisati termin?',
			'Pošaljite vrstu događaja, datum i željene usluge kroz <a href="' . $konf . '">konfigurator upita</a> ili na WhatsApp +387 60 308 0020. Upit nije rezervacija: termin je rezervisan tek nakon što potvrdimo dostupnost i dogovorimo uslove.',
		),
	);
}

function dip_seo_home_faq_html( array $items ): string {
	$out = '<section class="dip-home-faq" aria-labelledby="dip-home-faq-title"><div class="dip-home-faq__inner"><h2 class="wp-block-heading" id="dip-home-faq-title">ČESTA PITANJA</h2>';
	foreach ( $items as $item ) {
		$out .= '<details class="wp-block-details dip-home-faq__item"><summary>' . esc_html( $item[0] ) . '</summary><p>' . wp_kses( $item[1], array( 'a' => array( 'href' => true ) ) ) . '</p></details>';
	}
	$out .= '</div></section>';
	$out .= '<style>.dip-home-faq{padding:clamp(56px,8vw,112px) var(--wp--custom--gutter,20px)}.dip-home-faq__inner{max-width:880px;margin:0 auto}.dip-home-faq h2{margin:0 0 28px}.dip-home-faq__item{border-top:1px solid rgba(255,255,255,.16);padding:18px 0}.dip-home-faq__item:last-child{border-bottom:1px solid rgba(255,255,255,.16)}.dip-home-faq__item summary{cursor:pointer;font-size:1.08rem;line-height:1.4}.dip-home-faq__item p{margin:12px 0 0;opacity:.86;line-height:1.6}.dip-home-faq__item a{text-decoration:underline}.dip-card__price{display:block;margin-top:4px;font-size:.85em;opacity:.75;letter-spacing:.02em}</style>';
	return $out;
}

function dip_seo_faq_jsonld( array $items ): string {
	$entities = array();
	foreach ( $items as $item ) {
		$entities[] = array(
			'@type'          => 'Question',
			'name'           => $item[0],
			'acceptedAnswer' => array( '@type' => 'Answer', 'text' => trim( wp_strip_all_tags( $item[1] ) ) ),
		);
	}
	$json = json_encode(
		array( '@context' => 'https://schema.org', '@type' => 'FAQPage', '@id' => home_url( '/#faq' ), 'mainEntity' => $entities ),
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
	);
	return false === $json ? '' : '<script type="application/ld+json" data-dip-seo="home-faq">' . $json . '</script>';
}

/** Inserts the FAQ before </main> (or before the footer). Pure; unit-tested. */
function dip_seo_insert_home_faq( string $html, array $items ): string {
	if ( false !== strpos( $html, 'dip-home-faq' ) ) {
		return $html;
	}
	$section = dip_seo_home_faq_html( $items );
	$pos     = strripos( $html, '</main>' );
	if ( false === $pos ) {
		$pos = stripos( $html, '<footer' );
	}
	if ( false === $pos ) {
		return $html;
	}
	$html = substr( $html, 0, $pos ) . $section . substr( $html, $pos );
	if ( false === strpos( $html, '"FAQPage"' ) ) {
		$json = dip_seo_faq_jsonld( $items );
		$head = stripos( $html, '</head>' );
		if ( '' !== $json && false !== $head ) {
			$html = substr( $html, 0, $head ) . $json . "\n" . substr( $html, $head );
		}
	}
	return $html;
}

/* ---------------------------------------------------------------------------------------------
 * alt_render
 * ------------------------------------------------------------------------------------------- */

/** Bosnian alt texts for gallery files that have an empty alt (keyed by file name stem). */
function dip_seo_empty_alt_map(): array {
	return array(
		'galerija-korporativni-dogadjaj-bina'         => 'Scenska rasvjeta i bina na korporativnom događaju',
		'galerija-360-booth-prskalice-siroko'         => '360 Video Booth platforma uz hladne prskalice na vjenčanju',
		'galerija-co2-party-gun-mlada'                => 'Mlada s CO2 party gunom na plesnom podiju',
		'galerija-360-booth-ring-light'               => 'Gosti na 360 Video Booth platformi s kružnim svjetlom',
		'galerija-360-booth-niski-dim-prskalice'      => '360 Video Booth uz niski dim i hladne prskalice',
	);
}

/** File name stem without size and hash suffixes: ".../galerija-x-bina-w800.webp" => "galerija-x-bina". */
function dip_seo_file_stem( string $src ): string {
	$name = strtolower( basename( (string) wp_parse_url( $src, PHP_URL_PATH ) ) );
	$name = (string) preg_replace( '/\.(webp|jpe?g|png|avif)$/', '', $name );
	$name = (string) preg_replace( '/-\d+x\d+$/', '', $name );
	$name = (string) preg_replace( '/-v\d+-[0-9a-f]{6,}$/', '', $name );
	return (string) preg_replace( '/-w\d{3,4}$/', '', $name );
}

/** Best alt for one <img>, or null to leave it. Pure; unit-tested. */
function dip_seo_better_alt( string $alt, string $src ): ?string {
	$alt = trim( html_entity_decode( $alt, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	if ( '' === $alt ) {
		$map  = dip_seo_empty_alt_map();
		$stem = dip_seo_file_stem( $src );
		return $map[ $stem ] ?? null;
	}
	if ( preg_match( '/^dip\s+(?!studio\b)/i', $alt ) && ! preg_match( '/[a-zčćšđž]\s[a-zčćšđž]/u', $alt ) ) {
		// "Dip Niski Dim Prskalice Baloni Prvi Ples" => file-name alt; treat like "Dip Galerija …".
		$alt = (string) preg_replace( '/^dip\s+(galerija\s+)?/i', 'Dip Galerija ', $alt );
	}
	$c = dip_seo_classify_alt( $alt );
	if ( 'prompt' === $c['type'] ) {
		if ( ! empty( $c['suggestion'] ) ) {
			return $c['suggestion'];
		}
		foreach ( dip_seo_alt_prompt_map() as $prefix => $replacement ) {
			$en = trim( (string) preg_replace( '/\s+—.*$/u', '', $prefix ) );
			if ( '' !== $en && 0 === stripos( $alt, $en ) ) {
				return $replacement;
			}
		}
		return null;
	}
	if ( 'slug' === $c['type'] && ! empty( $c['suggestion'] ) ) {
		return (string) preg_replace( '/\s+–\s+DIP Studio Sarajevo$/u', '', $c['suggestion'] );
	}
	return null;
}

function dip_seo_fix_img_alts( string $body ): string {
	return (string) preg_replace_callback(
		'#<img\b[^>]*>#i',
		static function ( $m ) {
			$tag = $m[0];
			if ( ! preg_match( '#\salt="([^"]*)"#', $tag, $a ) ) {
				return $tag;
			}
			$src = preg_match( '#\ssrc="([^"]*)"#', $tag, $s ) ? $s[1] : '';
			$new = dip_seo_better_alt( $a[1], $src );
			if ( null === $new || '' === $new ) {
				return $tag;
			}
			return str_replace( $a[0], ' alt="' . esc_attr( $new ) . '"', $tag );
		},
		$body
	);
}

/* ---------------------------------------------------------------------------------------------
 * schema_tidy
 * ------------------------------------------------------------------------------------------- */

/** Fixes one decoded JSON-LD document in place. Pure; unit-tested. */
function dip_seo_tidy_jsonld( array &$doc, string $home, array $opts ): void {
	$home = rtrim( $home, '/' ) . '/';
	$walk = static function ( &$v ) use ( &$walk, $home, $opts ) {
		if ( ! is_array( $v ) ) {
			if ( is_string( $v ) && $v === $home . '#Organization' ) {
				$v = $home . '#organization';
			}
			return;
		}
		$types = isset( $v['@type'] ) ? (array) $v['@type'] : array();
		if ( in_array( 'Article', $types, true ) || in_array( 'BlogPosting', $types, true ) ) {
			if ( isset( $v['@id'] ) && is_string( $v['@id'] ) ) {
				$v['@id'] = (string) preg_replace( '#^(' . preg_quote( $home, '#' ) . ')\d{4}/\d{2}/\d{2}/#', '$1', $v['@id'] );
			}
			if ( isset( $v['Author'] ) && isset( $v['author'] ) ) {
				unset( $v['Author'] );
			}
			if ( isset( $v['name'] ) && in_array( $v['name'], array( 'Article', 'BlogPosting' ), true ) && ! empty( $v['headline'] ) ) {
				$v['name'] = $v['headline'];
			}
		}
		if ( in_array( 'BreadcrumbList', $types, true ) && ! empty( $v['itemListElement'] ) && is_array( $v['itemListElement'] ) ) {
			$items = array_values( $v['itemListElement'] );
			foreach ( $items as &$it ) {
				if ( isset( $it['item']['name'] ) && 'Home' === $it['item']['name'] ) {
					$it['item']['name'] = 'Početna';
				}
				if ( isset( $it['name'] ) && 'Home' === $it['name'] ) {
					$it['name'] = 'Početna';
				}
			}
			unset( $it );
			// Homepage: "Home" (https://dipstudio.ba) + "Početna" (https://dipstudio.ba/) is the same page twice.
			$seen  = array();
			$items = array_values(
				array_filter(
					$items,
					static function ( $it ) use ( &$seen ) {
						$id = is_array( $it['item'] ?? null ) ? (string) ( $it['item']['@id'] ?? '' ) : (string) ( $it['item'] ?? '' );
						$id = rtrim( $id, '/' );
						if ( '' !== $id && isset( $seen[ $id ] ) ) {
							return false;
						}
						$seen[ $id ] = true;
						return true;
					}
				)
			);
			if ( ! empty( $opts['service_crumb'] ) && count( $items ) === 2 ) {
				$usluge = $home . 'usluge/';
				array_splice( $items, 1, 0, array( array( '@type' => 'ListItem', 'item' => array( '@id' => $usluge, 'name' => 'Usluge' ) ) ) );
			}
			foreach ( $items as $i => &$it ) {
				$it['position'] = $i + 1;
			}
			unset( $it );
			$v['itemListElement'] = $items;
		}
		if ( in_array( 'PriceSpecification', $types, true ) && isset( $v['minPrice'] ) && 0 === (int) $v['minPrice'] && ! empty( $v['maxPrice'] ) ) {
			unset( $v['minPrice'] );
		}
		if ( in_array( 'PostalAddress', $types, true ) && isset( $v['addressCountry'] ) && 'Bosna i Hercegovina' === $v['addressCountry'] ) {
			$v['addressCountry'] = 'BA';
		}
		if ( in_array( 'LocalBusiness', $types, true ) && ! empty( $opts['price_range'] ) && isset( $v['priceRange'] ) && preg_match( '/^\d+\s*-\s*\d+$/', (string) $v['priceRange'] ) ) {
			$v['priceRange'] = $opts['price_range'];
		}
		foreach ( $v as &$child ) {
			$walk( $child );
		}
		unset( $child );
	};
	$walk( $doc );
}

function dip_seo_tidy_all_jsonld( string $html, string $home, array $opts ): string {
	return (string) preg_replace_callback(
		'~(<script\b[^>]*\btype\s*=\s*["\']application/ld\+json["\'][^>]*>)([\s\S]*?)(</script\s*>)~i',
		static function ( $m ) use ( $home, $opts ) {
			$doc = json_decode( trim( $m[2] ), true );
			if ( ! is_array( $doc ) ) {
				return $m[0];
			}
			$before = $doc;
			dip_seo_tidy_jsonld( $doc, $home, $opts );
			if ( $doc === $before ) {
				return $m[0];
			}
			$json = json_encode( $doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG );
			return false === $json ? $m[0] : $m[1] . $json . $m[3];
		},
		$html
	);
}

/* ---------------------------------------------------------------------------------------------
 * WordPress wiring
 * ------------------------------------------------------------------------------------------- */

/** Hide AI illustrations from /galerija/ (they stay in Media and in blog posts). */
add_filter(
	'dip_core_gallery_excluded_ids',
	static function ( $ids ) {
		if ( ! dip_studio_seo_setting( 'gallery_hide_ai' ) ) {
			return $ids;
		}
		global $wpdb;
		$rows = $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE '%dip-img-scena-%'" );
		return array_merge( (array) $ids, array_map( 'intval', (array) $rows ) );
	},
	20
);

/** The whole-page pass. Runs on the finished HTML, so it also sees the theme and DIP Core output. */
function dip_seo_audit_1106_filter( string $html ): string {
	if ( '' === $html || false === stripos( $html, '<html' ) ) {
		return $html;
	}
	$on  = 'dip_studio_seo_setting';
	$map = dip_seo_price_map();

	// Learn current prices from the real table on /usluge/.
	if ( false !== strpos( $html, 'dip-pricing--table' ) && false === strpos( $html, 'dip-pricing--post' ) && is_page( 'usluge' ) ) {
		$fresh = dip_seo_parse_price_table( $html );
		if ( count( $fresh ) >= 10 && $fresh !== get_option( DIP_SEO_PRICE_OPTION ) ) {
			update_option( DIP_SEO_PRICE_OPTION, $fresh, false );
			$map = $fresh;
		}
	}

	list( $head, $body ) = dip_seo_split_body( $html );

	if ( $on( 'xml_decl' ) ) {
		$body = (string) preg_replace( '#<\?xml[^>]*\?>\s*#i', '', $body );
	}
	if ( is_page( 'usluge' ) ) {
		$body = (string) preg_replace( '#<section class="dip-pricing dip-pricing--table">#', '<section class="dip-pricing dip-pricing--table" id="cjenovnik">', $body, 1 );
	}
	if ( $on( 'blog_prices' ) && is_single( 'sta-utice-na-cijenu-specijalnih-efekata-za-vjencanje' ) ) {
		$body = dip_seo_fix_pricing_post( $body, $map );
	}
	if ( $on( 'price_copy' ) ) {
		$body = dip_seo_fix_price_copy( $body, $map );
	}
	if ( $on( 'price_badges' ) ) {
		$body = dip_seo_card_prices( $body, $map );
		if ( false !== strpos( $body, 'dip-builder__data' ) ) {
			$body = dip_seo_configurator_prices(
				$body,
				$map,
				static function ( int $id ): string {
					return (string) get_post_field( 'post_name', $id );
				}
			);
		}
		if ( false !== strpos( $body, 'dip-card__price' ) && false === strpos( $body, '.dip-card__price{' ) ) {
			$body = str_ireplace( '</body>', '<style>.dip-card__price{display:block;margin-top:4px;font-size:.85em;opacity:.75;letter-spacing:.02em}</style></body>', $body );
		}
	}
	if ( $on( 'alt_render' ) ) {
		$body = dip_seo_fix_img_alts( $body );
	}

	$html = $head . $body;

	if ( $on( 'home_faq' ) && is_front_page() ) {
		$html = dip_seo_insert_home_faq( $html, dip_seo_home_faq_items( $map ) );
	}
	if ( $on( 'schema_tidy' ) ) {
		$min = PHP_INT_MAX;
		$max = 0;
		foreach ( $map as $row ) {
			list( $a, $b ) = dip_seo_range_numbers( $row[1] );
			if ( $a > 0 ) {
				$min = min( $min, $a );
			}
			$max = max( $max, $b );
		}
		$html = dip_seo_tidy_all_jsonld(
			$html,
			home_url( '/' ),
			array(
				'service_crumb' => is_singular( 'dip_service' ),
				'price_range'   => ( PHP_INT_MAX !== $min && $max ) ? dip_seo_km( $min ) . ' – ' . dip_seo_km( $max ) : '',
			)
		);
	}
	return $html;
}

add_action(
	'template_redirect',
	static function () {
		if ( is_admin() || wp_doing_ajax() || is_feed() || is_customize_preview() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		ob_start(
			static function ( $html ) {
				if ( ! is_string( $html ) ) {
					return $html;
				}
				try {
					return dip_seo_audit_1106_filter( $html );
				} catch ( Throwable $e ) {
					error_log( 'DIP SEO audit fixes: ' . $e->getMessage() );
					return $html;
				}
			}
		);
	},
	97
);
