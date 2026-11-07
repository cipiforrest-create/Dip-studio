<?php
/**
 * Plugin Name: DIP Studio — SEO
 * Description: One plugin for dipstudio.ba SEO/AEO: enriches the existing SiteSEO / DIP Core JSON-LD (no duplicate Organization, Service or FAQ nodes), old /stagging/ URLs → 410, date-permalink 301s, heading spacing fix, duplicate lead sentence fix, robots.txt, price blocks on service pages, /kontakt/ contact block, blog reviewer block, llms.txt with live prices and a gallery alt-text + Media title fixer under Tools → DIP SEO. Replaces "DIP Studio — Schema Enrichment" and "DIP Studio – SEO/AEO fixes".
 * Version: 1.4.0
 * Author: Digital X
 * Requires PHP: 7.4
 * Text Domain: dip-studio-seo
 */

defined( 'ABSPATH' ) || exit;

/*
 * Stop if one of the two old plugins is still active, so code never loads twice.
 * The admin notice tells the administrator to deactivate the old one.
 */
if ( defined( 'DIP_SEO_DIR' ) || class_exists( 'DIP_Enrichment_Plugin', false ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-error"><p><strong>DIP Studio — SEO</strong> nije pokrenut: prvo deaktivirajte stare dodatke „DIP Studio — Schema Enrichment” i „DIP Studio – SEO/AEO fixes”. Novi dodatak sadrži oba.</p></div>';
			}
		}
	);
	return;
}

define( 'DIP_SEO_DIR', __DIR__ );
define( 'DIP_STUDIO_SEO_VERSION', '1.4.0' );
define( 'DIP_STUDIO_SEO_OPTION', 'dip_studio_seo_settings' );

/** Every switch on Tools → DIP SEO with its default. */
function dip_studio_seo_defaults(): array {
	return array(
		// Schema enrichment (adds to existing JSON-LD, never creates a second business/Service/FAQ).
		'enabled'              => 1,
		'normalize_staging_id' => 1,
		'auto_faq'             => 1,
		'catalog'              => 1,
		'blog'                 => 1,
		// Technical fixes.
		'staging_410'          => 1,
		'redirects'            => 1,
		'rewrite_urls'         => 1,
		'heading_fix'          => 1,
		'dup_excerpt'          => 1,
		'robots'               => 1,
		'llms'                 => 0, // SiteSEO already serves /llms.txt; turn on only to replace it.
		'gallery_clean'        => 1,
		'author_redirect'      => 1,
		'footer_headings'      => 1,
		// Second audit (6 Nov 2026).
		'price_copy'           => 1,
		'blog_prices'          => 1,
		'price_badges'         => 1,
		'home_faq'             => 1,
		'gallery_hide_ai'      => 1,
		'alt_render'           => 1,
		'schema_tidy'          => 1,
		'xml_decl'             => 1,
		// Third audit / re-audit (7 Nov 2026).
		'price_block'          => 1,
		'h1_split'             => 1,
		'link_fix'             => 1,
		'kontakt_nap'          => 1,
		'review_block'         => 1,
		'person_schema'        => 1,
	);
}

function dip_studio_seo_setting( string $key ): bool {
	$saved = get_option( DIP_STUDIO_SEO_OPTION, array() );
	$saved = is_array( $saved ) ? $saved : array();
	if ( array_key_exists( $key, $saved ) ) {
		return ! empty( $saved[ $key ] );
	}
	$defaults = dip_studio_seo_defaults();
	return ! empty( $defaults[ $key ] );
}

require_once DIP_SEO_DIR . '/inc/services.php';
require_once DIP_SEO_DIR . '/inc/lib.php';
require_once DIP_SEO_DIR . '/inc/hooks.php';
require_once DIP_SEO_DIR . '/inc/cli.php';
require_once DIP_SEO_DIR . '/inc/audit-fixes.php';
require_once DIP_SEO_DIR . '/inc/audit-2026-11-06.php';
require_once DIP_SEO_DIR . '/inc/audit-2026-11-07.php';
require_once DIP_SEO_DIR . '/includes/class-dip-enrichment-graph.php';
require_once DIP_SEO_DIR . '/includes/class-dip-enrichment-plugin.php';

add_action( 'plugins_loaded', array( 'DIP_Enrichment_Plugin', 'boot' ) );
register_activation_hook( __FILE__, array( 'DIP_Enrichment_Plugin', 'activate' ) );
