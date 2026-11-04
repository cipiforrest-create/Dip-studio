<?php
/**
 * JSON-LD @graph (audit §5.2 A–C).
 *
 *  - Sitewide: EntertainmentBusiness/LocalBusiness + OfferCatalog (23 services) + WebSite.
 *  - /usluge/{slug}/: Service node linked to the organization.
 *  - /konfigurator/: WebApplication.
 *
 * Deliberately NOT emitted here:
 *  - BreadcrumbList — SiteSEO Pro generates it (audit §5.1); emitting it twice causes conflicts.
 *  - FAQPage — FAQ markup must mirror visible on-page answers. Add it per page only after the
 *    FAQ copy has been rewritten with verified figures (audit §3.3).
 *  - Prices — no verified "od X KM" anchors exist yet; add `offers.lowPrice` once published.
 *
 * Disable with `define( 'DIP_SEO_DISABLE_SCHEMA', true );` or filter `dip_seo_schema_graph`.
 * If SiteSEO's own Knowledge Graph "Organization" is enabled, turn it off to avoid two
 * organization entities.
 */

defined( 'ABSPATH' ) || exit;

function dip_seo_org_id(): string {
	return home_url( '/#organization' );
}

function dip_seo_schema_organization(): array {
	$b       = dip_seo_business();
	$catalog = array();
	foreach ( dip_seo_categories() as $cat ) {
		$items = array();
		foreach ( $cat['services'] as $slug => $svc ) {
			$items[] = array(
				'@type'       => 'Offer',
				'itemOffered' => array(
					'@type' => 'Service',
					'@id'   => home_url( "/usluge/{$slug}/#service" ),
					'name'  => $svc[0],
					'url'   => home_url( "/usluge/{$slug}/" ),
				),
			);
		}
		$catalog[] = array(
			'@type'           => 'OfferCatalog',
			'name'            => $cat['name'],
			'itemListElement' => $items,
		);
	}

	$area = array_map(
		static function ( $c ) {
			return array( '@type' => 'City', 'name' => $c );
		},
		dip_seo_cities()
	);
	$area[] = array( '@type' => 'Country', 'name' => 'Bosna i Hercegovina' );

	$address = array_filter(
		array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => $b['street'],
			'addressLocality' => $b['locality'],
			'addressRegion'   => $b['region'],
			'postalCode'      => $b['postal'],
			'addressCountry'  => $b['country'],
		)
	);

	return array(
		'@type'              => array( 'EntertainmentBusiness', 'LocalBusiness' ),
		'@id'                => dip_seo_org_id(),
		'name'               => $b['name'],
		'alternateName'      => 'DIP Studio Sarajevo – Specijalni efekti, rasvjeta i atrakcije za vjenčanja',
		'slogan'             => 'Stvaramo trenutke koji se pamte.',
		'url'                => $b['url'],
		'logo'               => array(
			'@type'      => 'ImageObject',
			'@id'        => home_url( '/#logo' ),
			'url'        => $b['logo'],
			'contentUrl' => $b['logo'],
			'caption'    => 'DIP Studio Sarajevo',
		),
		'image'              => $b['logo'],
		'description'        => 'DIP Studio pruža specijalne efekte za prvi ples (niski dim, hladne prskalice, balonska eksplozija), scensku i ambijentalnu rasvjetu (zvjezdano nebo, svjetleći baldahini), dnevne i noćne vatromete, te najam Photobootha, 360 Video Bootha i Audio Guestbooka za vjenčanja, proslave i korporativne događaje u Sarajevu i cijeloj Bosni i Hercegovini.',
		'telephone'          => $b['telephone'],
		'email'              => $b['email'],
		'priceRange'         => '$$',
		'currenciesAccepted' => 'BAM, EUR',
		'address'            => $address,
		'contactPoint'       => array(
			array(
				'@type'             => 'ContactPoint',
				'telephone'         => $b['telephone'],
				'contactType'       => 'customer service',
				'email'             => $b['email'],
				'areaServed'        => 'BA',
				'availableLanguage' => array( 'bs', 'hr', 'sr', 'en' ),
			),
		),
		'sameAs'             => $b['same_as'],
		'employee'           => array(
			'@type'    => 'Person',
			'@id'      => home_url( '/usluge/profesionalni-fotograf/#ajla-husagic' ),
			'name'     => 'Ajla Husagić',
			'jobTitle' => 'Profesionalni fotograf za vjenčanja i događaje',
			'url'      => home_url( '/usluge/profesionalni-fotograf/' ),
			'worksFor' => array( '@id' => dip_seo_org_id() ),
		),
		'areaServed'         => $area,
		'hasOfferCatalog'    => array(
			'@type'           => 'OfferCatalog',
			'@id'             => home_url( '/usluge/#catalog' ),
			'name'            => 'Katalog usluga za vjenčanja, proslave i korporativne događaje',
			'itemListElement' => $catalog,
		),
		'potentialAction'    => array(
			'@type'  => 'ReserveAction',
			'target' => array(
				'@type'          => 'EntryPoint',
				'urlTemplate'    => home_url( '/konfigurator/' ),
				'inLanguage'     => 'bs-BA',
				'actionPlatform' => array( 'https://schema.org/DesktopWebPlatform', 'https://schema.org/MobileWebPlatform' ),
			),
			'result' => array(
				'@type' => 'Reservation',
				'name'  => 'Provjera dostupnosti termina putem DIP Studio konfiguratora',
			),
		),
	);
}

function dip_seo_schema_graph( string $path ): array {
	$graph   = array( dip_seo_schema_organization() );
	$graph[] = array(
		'@type'      => 'WebSite',
		'@id'        => home_url( '/#website' ),
		'url'        => home_url( '/' ),
		'name'       => 'DIP Studio Sarajevo',
		'inLanguage' => 'bs-BA',
		'publisher'  => array( '@id' => dip_seo_org_id() ),
	);

	$path = trim( $path, '/' );

	if ( preg_match( '#^usluge/([^/]+)$#', $path, $m ) ) {
		$services = dip_seo_services();
		if ( isset( $services[ $m[1] ] ) ) {
			list( $name, $desc, $cat_slug ) = $services[ $m[1] ];
			$related                         = array();
			foreach ( dip_seo_categories()[ $cat_slug ]['services'] as $slug => $unused ) {
				if ( $slug !== $m[1] ) {
					$related[] = array( '@id' => home_url( "/usluge/{$slug}/#service" ) );
				}
			}
			$graph[] = array_filter(
				array(
					'@type'       => 'Service',
					'@id'         => home_url( "/usluge/{$m[1]}/#service" ),
					'name'        => $name,
					'serviceType' => dip_seo_categories()[ $cat_slug ]['name'],
					'url'         => home_url( "/usluge/{$m[1]}/" ),
					'description' => $desc,
					'provider'    => array( '@id' => dip_seo_org_id() ),
					'areaServed'  => array( '@type' => 'Country', 'name' => 'Bosna i Hercegovina' ),
					'audience'    => array(
						'@type'        => 'Audience',
						'audienceType' => 'Mladenci, organizatori vjenčanja, privatnih proslava i korporativnih događaja',
					),
					'isRelatedTo' => $related,
				)
			);
		}
	}

	if ( 'konfigurator' === $path ) {
		$graph[] = array(
			'@type'               => 'WebApplication',
			'@id'                 => home_url( '/konfigurator/#app' ),
			'name'                => 'DIP Studio Konfigurator Upita za Vjenčanja i Događaje',
			'url'                 => home_url( '/konfigurator/' ),
			'applicationCategory' => 'LifestyleApplication',
			'operatingSystem'     => 'All',
			'inLanguage'          => 'bs-BA',
			'description'         => 'Interaktivni konfigurator za odabir vrste događaja, datuma i kombinacije 23 usluge (specijalni efekti, rasvjeta, vatrometi, Photobooth i 360 Video Booth) s direktnim slanjem strukturiranog upita na WhatsApp bez pohranjivanja ličnih podataka na stranici.',
			'offers'              => array(
				'@type'         => 'Offer',
				'price'         => '0',
				'priceCurrency' => 'BAM',
				'description'   => 'Besplatna provjera dostupnosti termina i priprema ponude',
			),
			'provider'            => array( '@id' => dip_seo_org_id() ),
		);
	}

	return apply_filters( 'dip_seo_schema_graph', $graph, $path );
}

add_action(
	'wp_head',
	static function () {
		if ( ( defined( 'DIP_SEO_DISABLE_SCHEMA' ) && DIP_SEO_DISABLE_SCHEMA ) || is_404() ) {
			return;
		}
		$data = array(
			'@context' => 'https://schema.org',
			'@graph'   => dip_seo_schema_graph( dip_seo_request_path() ),
		);
		echo "\n<script type=\"application/ld+json\" class=\"dip-seo-schema\">"
			. wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG )
			. "</script>\n";
	},
	5
);
