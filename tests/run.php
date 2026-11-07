<?php
/**
 * Dependency-free tests for the pure helpers. Run: php tests/run.php
 */

define( 'DIP_SEO_TESTING', true );
$base = dirname( __DIR__ ) . '/wordpress/wp-content/plugins/dip-seo-fixes';
require $base . '/inc/services.php';
require $base . '/inc/lib.php';

$fails = 0;
$count = 0;
function eq( $actual, $expected, string $name ) {
	global $fails, $count;
	++$count;
	if ( $actual === $expected ) {
		echo "  ok   {$name}\n";
		return;
	}
	++$fails;
	echo "  FAIL {$name}\n       expected: " . var_export( $expected, true ) . "\n       actual:   " . var_export( $actual, true ) . "\n";
}

echo "Staging URL rewrite\n";
eq(
	dip_seo_rewrite_staging_urls( '<img src="https://dipstudio.ba/stagging/wp-content/uploads/2026/10/dip-logo-white-optimized.webp">' ),
	'<img src="https://dipstudio.ba/wp-content/uploads/2026/10/dip-logo-white-optimized.webp">',
	'absolute https URL'
);
eq(
	dip_seo_rewrite_staging_urls( '{"url":"https:\/\/dipstudio.ba\/stagging\/wp-content\/uploads\/a.webp"}' ),
	'{"url":"https:\/\/dipstudio.ba\/wp-content\/uploads\/a.webp"}',
	'JSON-escaped URL'
);
eq(
	dip_seo_rewrite_staging_urls( '<link href="/staging/wp-content/x.css"> srcset="//www.dipstudio.ba/stagging/wp-includes/y.js 2x"' ),
	'<link href="/wp-content/x.css"> srcset="//www.dipstudio.ba/wp-includes/y.js 2x"',
	'root- and protocol-relative, both spellings'
);
eq(
	dip_seo_rewrite_staging_urls( '<a href="https://dipstudio.ba/stagging/">x</a> <a href="https://other.com/stagging/wp-content/a">' ),
	'<a href="https://dipstudio.ba/stagging/">x</a> <a href="https://other.com/stagging/wp-content/a">',
	'leaves non-asset staging links and other hosts alone'
);

echo "Heading <br> tokenization\n";
eq(
	dip_seo_fix_heading_breaks( '<h1 class="hero">STVARAMO<br>TRENUTKE<br/><em>koji se pamte</em></h1>' ),
	'<h1 class="hero">STVARAMO <br>TRENUTKE <br/><em>koji se pamte</em></h1>',
	'h1 with <br> and <br/>'
);
eq(
	dip_seo_fix_heading_breaks( '<h2>OD UPITA <br>DO REALIZACIJE</h2><p>a<br>b</p>' ),
	'<h2>OD UPITA <br>DO REALIZACIJE</h2><p>a<br>b</p>',
	'existing space kept, paragraphs untouched'
);
eq(
	dip_seo_fix_heading_breaks( "<h2>SVE<span></span><br class=\"x\">\nNA</h2>" ),
	"<h2>SVE<span></span> <br class=\"x\">\nNA</h2>",
	'br after a tag'
);

echo "Duplicate excerpt detection\n";
$content = '<p>CO₂ Party Gun i laserski efekti imaju različitu vizuelnu ulogu. Prvi daje kratak mlaz…</p>';
eq( dip_seo_excerpt_duplicates_content( 'CO₂ Party Gun i laserski efekti imaju različitu vizuelnu ulogu.', $content ), true, 'excerpt equals lead sentence' );
eq( dip_seo_excerpt_duplicates_content( 'CO₂ Party Gun i laserski efekti imaju različitu vizuelnu ulogu. Prvi daje […]', $content ), true, 'auto-excerpt with [...] suffix' );
eq( dip_seo_excerpt_duplicates_content( 'Vodič kroz efekte za prvi ples i proslave.', $content ), false, 'different excerpt kept' );
eq( dip_seo_excerpt_duplicates_content( 'CO₂', $content ), false, 'too-short excerpt ignored' );

echo "Paths\n";
eq( dip_seo_date_prefixed_slug( '/2026/10/24/dnevni-vatromet-u-boji-kako-izgleda-na-vjencanju/' ), 'dnevni-vatromet-u-boji-kako-izgleda-na-vjencanju', 'date-prefixed post' );
eq( dip_seo_date_prefixed_slug( '/2026/10/24/' ), null, 'date archive is not a post' );
eq( dip_seo_date_prefixed_slug( '/usluge/lepeza/' ), null, 'normal page' );
eq( dip_seo_is_staging_path( '/stagging/' ), true, '/stagging/' );
eq( dip_seo_is_staging_path( '/staging/wp-content/a.webp' ), true, '/staging/…' );
eq( dip_seo_is_staging_path( '/stagingx/' ), false, 'lookalike slug' );

echo "Alt text classification\n";
$c = dip_seo_classify_alt( 'Low white floor-hugging fog around a couple on a wedding dance floor, clear upper air — prvi ples i vjenčanja.' );
eq( array( $c['type'], $c['auto'] ), array( 'prompt', true ), 'known prompt is auto-fixable' );
eq( $c['suggestion'], 'Niski teški dim za prvi ples mladenaca – efekat plesa na oblacima uz pod svečane sale', 'known prompt replacement' );
$c = dip_seo_classify_alt( 'Elegant photo booth kiosk, simple backdrop, real camera and clear guest access — photobooth i uspomene.' );
eq( $c['suggestion'], 'Najam Photobooth kabine za trenutno štampanje fotografija i uspomene gostiju na svadbi', 'same prompt, different heading → correct variant' );
eq( dip_seo_classify_alt( 'Spinning neon wheel of sparks above a dark stage — vatromet.' )['type'], 'prompt', 'unknown prompt flagged' );
eq( dip_seo_classify_alt( 'Spinning neon wheel of sparks above a dark stage — vatromet.' )['auto'], false, 'unknown prompt not auto-applied' );
eq( dip_seo_classify_alt( 'Dip Galerija Svjetleci Baldahin Mladenacki Sto' )['suggestion'], 'Svjetleći baldahin mladenački sto – DIP Studio Sarajevo', 'slug title gets diacritics' );
eq( dip_seo_classify_alt( 'Dip Galerija Niski Dim Prskalice Cb' )['suggestion'], 'Niski dim prskalice – DIP Studio Sarajevo', 'stray suffix dropped' );
eq( dip_seo_classify_alt( 'File 00000000a3c48210818367b81de63d80' )['type'], 'hash', 'hex hash' );
eq( dip_seo_classify_alt( 'Apple Touch Icon' )['type'], 'ui_icon', 'UI icon' );
eq( dip_seo_classify_alt( 'DIP Studio tim priprema produkciju događaja iza scene' )['type'], 'ok', 'good Bosnian caption untouched' );

echo "Data\n";
eq( count( dip_seo_services() ), 23, '23 services' );
eq( count( dip_seo_categories() ), 5, '5 categories' );
eq( count( dip_seo_cities() ), 36, '36 cities' );
eq( count( array_filter( dip_seo_services(), static function ( $s ) { return null !== $s[4]; } ) ), 23, 'every service has a published price' );

echo "Prices\n";
eq( dip_seo_price_text( 'svjetleci-baldahini' ), '1.000–5.000 KM', 'thousands separator' );
eq( dip_seo_price_text( 'hladne-prskalice' ), '50–100 KM (par)', 'unit' );
eq( dip_seo_price_text( 'organizacija-i-koordinacija' ), 'do 2.000 KM', 'upper bound only' );
eq( dip_seo_price_text( 'nope' ), null, 'unknown slug' );
$block = dip_seo_price_block_html( 'lepeza' );
eq( false !== strpos( $block, '<h2>Cijena usluge: Lepeza iskri 200–300 KM</h2>' ), true, 'price block heading' );
eq( dip_seo_has_price_block( $block ), true, 'detects own block' );
eq( dip_seo_has_price_block( '<h2 class="x">Cijena usluge: <span>Vatropad</span></h2>' ), true, 'detects existing vatropad-style block' );
eq( dip_seo_has_price_block( '<h2>Opis</h2><p>cijena usluge</p>' ), false, 'ignores non-heading text' );
$c = '<p>Intro</p><!-- wp:heading --><h2>Česta pitanja</h2><!-- /wp:heading --><p>Q</p>';
eq( dip_seo_insert_price_block( $c, '[B]' ), "<p>Intro</p>[B]\n<!-- wp:heading --><h2>Česta pitanja</h2><!-- /wp:heading --><p>Q</p>", 'inserted before FAQ block' );
eq( dip_seo_insert_price_block( '<p>x</p>', '[B]' ), "<p>x</p>\n[B]", 'appended without FAQ' );
eq( dip_seo_insert_price_block( $block, '[B]' ), $block, 'not inserted twice' );
eq( dip_seo_replace_na_upit( '<p>Cijene su na upit.</p><span>Cijena na upit</span>', '200–300 KM' ), '<p>Okvirna cijena: 200–300 KM.</p><span>Okvirna cijena: 200–300 KM</span>', 'na upit replaced' );

echo "Stuffed H1\n";
eq(
	dip_seo_split_stuffed_h1( '<h1 class="t">Photobooth <span class="sub">Zabavni sadržaj i uspomene za goste: foto kutak s rekvizitima</span></h1>', 'Photobooth' ),
	'<h1 class="t">Photobooth</h1><p class="dip-h1-lead"><span class="sub">Zabavni sadržaj i uspomene za goste: foto kutak s rekvizitima</span></p>',
	'child element split'
);
eq(
	dip_seo_split_stuffed_h1( '<h1>Hladne prskalice<br>Fontane svjetlucavih iskri za prvi ples</h1>' ),
	'<h1>Hladne prskalice</h1><p class="dip-h1-lead">Fontane svjetlucavih iskri za prvi ples</p>',
	'br split'
);
eq(
	dip_seo_split_stuffed_h1( '<h1>Photobooth Zabavni sadržaj i uspomene za goste i još</h1>', 'Photobooth' ),
	'<h1>Photobooth</h1><p class="dip-h1-lead">Zabavni sadržaj i uspomene za goste i još</p>',
	'title-prefix split'
);
eq( dip_seo_split_stuffed_h1( '<h1>Hladne prskalice</h1>' ), '<h1>Hladne prskalice</h1>', 'short H1 untouched' );
eq( dip_seo_split_stuffed_h1( '<h1>Jedna duga rečenica bez ikakve strukture u naslovu</h1>', 'Vatropad' ), '<h1>Jedna duga rečenica bez ikakve strukture u naslovu</h1>', 'no split point → untouched' );

echo "Internal links & anchors\n";
eq(
	dip_seo_rewrite_internal_links( '<a href="https://dipstudio.ba/projekti/">a</a><a href="/2026/10/24/photobooth-ili-360/#x">b</a><a href="https://other.ba/projekti/">c</a>' ),
	'<a href="https://dipstudio.ba/galerija/">a</a><a href="/photobooth-ili-360/#x">b</a><a href="https://other.ba/projekti/">c</a>',
	'projekti + date-prefixed, other hosts untouched'
);
eq(
	dip_seo_rewrite_internal_links( '<a href="/usluge/rotirajuce-prskalice-vatropad/">x</a>', 'dipstudio.ba', array( '/usluge/rotirajuce-prskalice-vatropad/' => '/usluge/rotirajuce-prskalice/' ) ),
	'<a href="/usluge/rotirajuce-prskalice/">x</a>',
	'renamed legacy slug'
);
eq( dip_seo_ensure_cjenovnik_anchor( '<h2 class="a">Cjenovnik usluga</h2>' ), '<h2 id="cjenovnik" class="a">Cjenovnik usluga</h2>', 'id added' );
eq( dip_seo_ensure_cjenovnik_anchor( '<div id="cjenovnik"></div><h2>Cjenovnik</h2>' ), '<div id="cjenovnik"></div><h2>Cjenovnik</h2>', 'existing anchor kept' );
eq( dip_seo_count_sections( '<h2>Česta pitanja</h2><h2>Dodatna pitanja prije dogovora</h2><h2>Povezane usluge</h2><h2>Povezane mogućnosti</h2>' ), array( 'h2' => 4, 'faq' => 2, 'related' => 2 ), 'duplicate sections counted' );

echo "llms.txt\n";
$llms    = dip_seo_llms_txt();
$missing = array();
foreach ( array_keys( dip_seo_services() ) as $slug ) {
	if ( false === strpos( $llms, "/usluge/{$slug}/" ) ) {
		$missing[] = $slug;
	}
}
eq( $missing, array(), 'links every service' );
eq( false !== strpos( $llms, '[Svjetleći baldahini](https://dipstudio.ba/usluge/svjetleci-baldahini/) – **1.000–5.000 KM**' ), true, 'includes KM prices' );
eq( false !== strpos( $llms, '**Upit**' ) && false !== strpos( $llms, '**Provjera**' ) && false !== strpos( $llms, '**Dogovor**' ), true, 'booking protocol' );
eq( false !== strpos( $llms, 'Tešanj' ), true, 'city list' );

echo "\n" . ( $count - $fails ) . "/{$count} passed\n";
exit( $fails ? 1 : 0 );
