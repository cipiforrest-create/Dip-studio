<?php
/**
 * Fixes from the "Comprehensive Audit of dipstudio.ba" (2026-11-05) that were verified on the live site.
 *
 *  - /galerija/: 136 of 248 grid items were -w960/-w480 copies of the same photo, plus 4 system
 *    images (header preview, apple-touch icon, two file_0000… hashes). Hidden via DIP Core's
 *    `dip_core_gallery_excluded_ids` filter; nothing is deleted from Media.
 *  - /author/…: archives duplicate the blog and expose the admin login name → 301 to the blog.
 *  - Footer column titles were the only <h2> on several pages → rendered as <p> with the same class.
 */

defined( 'ABSPATH' ) || exit;

/** Splits an upload file name into [group key, width or 0 for the original]. Pure; unit-tested. */
function dip_seo_media_variant( string $file ): array {
	$name = strtolower( basename( $file ) );
	$name = (string) preg_replace( '/\.(webp|jpe?g|png|avif)$/', '', $name );
	$name = (string) preg_replace( '/-v\d+-[0-9a-f]{6,}$/', '', $name ); // Content hash suffix.
	$width = 0;
	if ( preg_match( '/^(.*)-w(\d{3,4})$/', $name, $m ) ) {
		$name  = $m[1];
		$width = (int) $m[2];
	}
	return array( dirname( strtolower( $file ) ) . '/' . $name, $width );
}

/** True for icons, previews and raw hash exports that are not event photos. */
function dip_seo_is_system_media( string $file ): bool {
	return (bool) preg_match( '/(website-header-preview|apple-touch-icon|favicon|site-?icon|(^|\/)file_0{4,}[0-9a-f]+)/i', $file );
}

/**
 * Given attachment id => file, returns the ids to hide: system images, and every size copy of a
 * photo except one (the original if present, otherwise the widest copy).
 *
 * @param array<int,string> $files
 * @return int[]
 */
function dip_seo_gallery_duplicate_ids( array $files ): array {
	$hide   = array();
	$groups = array();
	foreach ( $files as $id => $file ) {
		if ( dip_seo_is_system_media( $file ) ) {
			$hide[] = (int) $id;
			continue;
		}
		list( $key, $width ) = dip_seo_media_variant( $file );
		$groups[ $key ][ (int) $id ] = $width;
	}
	foreach ( $groups as $members ) {
		if ( count( $members ) < 2 ) {
			continue;
		}
		// Keep the original (width 0) if there is one, else the widest copy.
		uasort(
			$members,
			static function ( $a, $b ) {
				if ( 0 === $a || 0 === $b ) {
					return 0 === $a ? -1 : 1;
				}
				return $b <=> $a;
			}
		);
		$keep = array_key_first( $members );
		foreach ( $members as $id => $unused ) {
			if ( $id !== $keep ) {
				$hide[] = (int) $id;
			}
		}
	}
	return $hide;
}

add_filter(
	'dip_core_gallery_excluded_ids',
	static function ( $ids ) {
		if ( ! dip_studio_seo_setting( 'gallery_clean' ) ) {
			return $ids;
		}
		global $wpdb;
		$files = array();
		foreach ( (array) $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file'" ) as $row ) {
			$files[ (int) $row->post_id ] = (string) $row->meta_value;
		}
		return array_merge( (array) $ids, dip_seo_gallery_duplicate_ids( $files ) );
	}
);

/* Author archives → blog (301). */
add_action(
	'template_redirect',
	static function () {
		if ( ! dip_studio_seo_setting( 'author_redirect' ) || ! is_author() ) {
			return;
		}
		$blog_id = (int) get_option( 'page_for_posts' );
		wp_safe_redirect( $blog_id ? get_permalink( $blog_id ) : home_url( '/blog/' ), 301, 'DIP SEO' );
		exit;
	},
	2
);

/** Footer column titles: <h2 class="dip-footer__heading" …>…</h2> → <p …>…</p>. Pure; unit-tested. */
function dip_seo_fix_footer_headings( string $html ): string {
	if ( false === strpos( $html, 'dip-footer__heading' ) ) {
		return $html;
	}
	return (string) preg_replace( '#<h2(\s[^>]*class="[^"]*\bdip-footer__heading\b[^"]*"[^>]*)>(.*?)</h2>#is', '<p$1>$2</p>', $html );
}

add_action(
	'template_redirect',
	static function () {
		if ( ! dip_studio_seo_setting( 'footer_headings' ) || is_admin() || wp_doing_ajax() || is_feed() ) {
			return;
		}
		ob_start(
			static function ( $html ) {
				return is_string( $html ) && '' !== $html ? dip_seo_fix_footer_headings( $html ) : $html;
			}
		);
	},
	98
);
