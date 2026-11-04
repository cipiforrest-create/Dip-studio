<?php
/**
 * Plugin Name: DIP Studio – SEO/AEO fixes
 * Description: Implements the code-level fixes from the dipstudio.ba Technical SEO/AEO audit: staging 410 + URL rewrite, date-permalink 301s, heading tokenization fix, duplicate lead sentence, robots.txt, llms.txt, JSON-LD @graph and WP-CLI tools (wp dip-seo …).
 * Version: 1.0.0
 * Requires PHP: 7.4
 *
 * Must-use plugin: copy this file AND the dip-seo-fixes/ folder into wp-content/mu-plugins/.
 */

defined( 'ABSPATH' ) || exit;

define( 'DIP_SEO_DIR', __DIR__ . '/dip-seo-fixes' );

require_once DIP_SEO_DIR . '/inc/services.php';
require_once DIP_SEO_DIR . '/inc/lib.php';
require_once DIP_SEO_DIR . '/inc/hooks.php';
require_once DIP_SEO_DIR . '/inc/schema.php';
require_once DIP_SEO_DIR . '/inc/cli.php';
