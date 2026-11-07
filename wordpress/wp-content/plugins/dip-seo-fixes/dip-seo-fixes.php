<?php
/**
 * Plugin Name: DIP Studio – SEO/AEO fixes
 * Description: Implements the code-level fixes from the dipstudio.ba Technical SEO/AEO audit: staging 410 + URL rewrite, date-permalink 301s, heading tokenization fix, duplicate lead sentence, robots.txt, llms.txt with KM prices, JSON-LD @graph (prices, Person, BlogPosting), service-page price blocks and H1 cleanup, /kontakt/ NAP block, blog reviewer block and WP-CLI tools (wp dip-seo …). Updated for the November 2026 re-audit.
 * Version: 1.1.0
 * Requires PHP: 7.4
 * Text Domain: dip-seo-fixes
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'DIP_SEO_DIR' ) ) {
	return; // Already loaded (e.g. a second copy installed).
}

define( 'DIP_SEO_DIR', __DIR__ );

require_once DIP_SEO_DIR . '/inc/services.php';
require_once DIP_SEO_DIR . '/inc/lib.php';
require_once DIP_SEO_DIR . '/inc/hooks.php';
require_once DIP_SEO_DIR . '/inc/schema.php';
require_once DIP_SEO_DIR . '/inc/cli.php';
