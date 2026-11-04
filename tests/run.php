<?php
/**
 * Dependency-free tests for the pure helpers. Run: php tests/run.php
 */

define( 'DIP_SEO_TESTING', true );
$base = dirname( __DIR__ ) . '/wordpress/wp-content/mu-plugins/dip-seo-fixes';
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
$llms = file_get_contents( $base . '/llms.txt' );
$missing = array();
foreach ( array_keys( dip_seo_services() ) as $slug ) {
	if ( false === strpos( $llms, "/usluge/{$slug}/" ) ) {
		$missing[] = $slug;
	}
}
eq( $missing, array(), 'llms.txt links every service' );

echo "\n" . ( $count - $fails ) . "/{$count} passed\n";
exit( $fails ? 1 : 0 );
