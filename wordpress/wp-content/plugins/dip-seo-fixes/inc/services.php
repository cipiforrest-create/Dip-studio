<?php
/**
 * Single source of truth for DIP Studio's 5 categories / 23 services and service area.
 * Used by the JSON-LD schema, hub page generator and llms.txt.
 *
 * No WordPress functions are called in this file (it is loaded by the test runner too).
 */

defined( 'DIP_SEO_TESTING' ) || defined( 'ABSPATH' ) || exit;

function dip_seo_categories(): array {
	return array(
		'specijalni-efekti'      => array(
			'name'     => 'Specijalni efekti',
			'title'    => 'Specijalni efekti za vjenčanja i proslave Sarajevo | DIP',
			'h1'       => 'Specijalni efekti za prvi ples, tortu i vrhunac zabave',
			'desc'     => 'Niski dim, hladne prskalice, vatropad, balonska eksplozija, magični laser i CO2 party gun za vjenčanja i proslave u Sarajevu i cijeloj BiH.',
			'intro'    => 'Specijalni efekti pretvaraju ključne trenutke večeri – svečani ulazak, prvi ples, rezanje torte i vrhunac zabave – u prizore koji ostaju na fotografijama i snimku. Na ovoj stranici su sve vrste efekata koje DIP Studio postavlja u salama i na otvorenom širom Bosne i Hercegovine.',
			'services' => array(
				'niski-dim-za-prvi-ples'       => array( 'Niski dim za prvi ples (Ples na oblacima)', 'Gust bijeli sloj magle uz sam pod za prvi ples, svečani ulazak i plesne trenutke bez zaklanjanja lica mladenaca.' ),
				'hladne-prskalice'             => array( 'Hladne prskalice (Scenske fontane)', 'Fontane svjetlucavih iskri oko plesnog podija ili staze za prvi ples, ulazak mladenaca i rezanje torte.' ),
				'rotirajuce-prskalice-vatropad' => array( 'Rotirajuće prskalice', 'Krugovi svjetlucavih iskri u pokretu oko mladenaca za ključne trenutke večeri.' ),
				'vatropad'                     => array( 'Vatropad (Zavjesa od iskri)', 'Svjetlucava zavjesa iskri koja se spušta odozgo poput kiše.' ),
				'vatrena-vjetrenjaca-lepeza'   => array( 'Vatrena vjetrenjača', 'Rotirajući točak od iskri za upečatljiv vizuelni okvir.' ),
				'lepeza'                       => array( 'Lepeza iskri', 'Široki lepezasti svjetlosni efekat iskri za svečani ulazak, prvi ples ili završnicu programa.' ),
				'balonska-eksplozija'          => array( 'Balonska eksplozija', 'Kontrolisano iznenađenje iz balona za prvi ples i svečani ulazak.' ),
				'baloncici'                    => array( 'Balončići za prvi ples i tortu', 'Sitni mjehurići u prostoru za prvi ples, rezanje torte i svečani izlazak.' ),
				'magicni-laser'                => array( 'Magični laser', 'Scenski snopovi svjetla u ritmu muzike za plesni podij.' ),
				'co2-party-gun'                => array( 'CO2 Party Gun', 'Kratak, naglašen bijeli mlaz za vrhunac zabave i plesa.' ),
			),
		),
		'rasvjeta'               => array(
			'name'     => 'Scenska i ambijentalna rasvjeta',
			'title'    => 'Dekorativna i ambijentalna rasvjeta Sarajevo | DIP Studio',
			'h1'       => 'Scenska i ambijentalna rasvjeta, zvjezdano nebo i baldahini',
			'desc'     => 'Ambijentalna rasvjeta i bojenje prostora, zvjezdano nebo i svjetleći baldahini za svečane sale, terase i mladenački sto u Sarajevu i BiH.',
			'intro'    => 'Rasvjeta određuje atmosferu cijele večeri. DIP Studio boji zidove i stubove sale, postavlja zvjezdano nebo iznad plesnog podija ili mladenačkog stola i osvjetljava dekorativne baldahine, u zatvorenim salama i na otvorenim terasama.',
			'services' => array(
				'ambijentalna-rasvjeta' => array( 'Ambijentalna rasvjeta i bojenje prostora', 'Dekorativno bojenje prostora i svjetlost koja povezuje zone svečane sale ili terase.' ),
				'zvjezdano-nebo'        => array( 'Zvjezdano nebo', 'Dekorativni stropni sloj toplih lampica iznad plesnog podija ili mladenačkog stola.' ),
				'svjetleci-baldahini'   => array( 'Svjetleći baldahini', 'Osvijetljeni dekorativni baldahini za mladenački sto i svečane prostore.' ),
			),
		),
		'vatrometi'              => array(
			'name'     => 'Vatrometi',
			'title'    => 'Vatrometi za vjenčanja i dnevni vatromet u boji | DIP',
			'h1'       => 'Profesionalni noćni, niski i dnevni vatrometi u boji u BiH',
			'desc'     => 'Dnevni vatromet u boji, noćni profesionalni vatromet, niski vatromet i rimske svijeće za vjenčanja i proslave na otvorenom širom BiH.',
			'intro'    => 'Vatromet je najglasniji i najvidljiviji trenutak proslave. Dnevni vatromet u boji namijenjen je ceremonijama i fotografisanju na otvorenom, dok su noćni, niski vatromet i rimske svijeće vrhunac večernjeg programa.',
			'services' => array(
				'vatromet-u-boji'      => array( 'Dnevni vatromet u boji', 'Dnevne dimne fontane u boji za ceremonije na otvorenom i fotografisanje.' ),
				'vatromet-za-vjencanja' => array( 'Noćni profesionalni vatromet za vjenčanja', 'Večernji profesionalni vatromet za vrhunac vjenčanja ili proslave.' ),
				'niski-vatromet'       => array( 'Niski vatromet', 'Scenski niski vatromet prilagođen terasama i otvorenim prostorima.' ),
				'rimske-svijece'       => array( 'Rimske svijeće', 'Svjetlosni efekti rimskih svijeća za proslave na otvorenom.' ),
			),
		),
		'photo-fun'              => array(
			'name'     => 'Photo & Fun atrakcije',
			'title'    => 'Photobooth, 360 Video Booth i Audio Guestbook Sarajevo',
			'h1'       => 'Photobooth, 360 Video Booth i interaktivne uspomene za goste',
			'desc'     => 'Najam Photobootha, 360 Video Bootha i Audio Guestbooka, svečana zdravica i profesionalni fotograf za vjenčanja i događaje u Sarajevu i BiH.',
			'intro'    => 'Photo & Fun atrakcije zabavljaju goste i ostavljaju uspomene koje mladenci i organizatori dobijaju nakon događaja: odštampane fotografije, kratke 360 video snimke, glasovne čestitke i profesionalne fotografije.',
			'services' => array(
				'photobooth'              => array( 'Najam Photobootha', 'Klasični foto kutak s pozadinom, rekvizitima i personalizovanim okvirom fotografije.' ),
				'360-video-booth'         => array( 'Najam 360 Video Bootha', 'Platforma s rotirajućom kamerom koja snima kratke dinamične video snimke gostiju u pokretu.' ),
				'audio-guestbook'         => array( 'Audio Guestbook (Telefon za glasovne poruke)', 'Retro telefon za snimanje glasovnih poruka i čestitki gostiju.' ),
				'zdravica'                => array( 'Svečana zdravica (Champagne Tower)', 'Organizacija svečane zdravice uz ulazak mladenaca ili rezanje torte.' ),
				'profesionalni-fotograf'  => array( 'Profesionalni fotograf (Ajla Husagić)', 'Profesionalno fotografisanje događaja usklađeno s rasvjetom i specijalnim efektima.' ),
			),
		),
		'organizacija-dogadjaja' => array(
			'name'     => 'Organizacija i koordinacija',
			// Only one service in this category, so no hub page: /organizacija-dogadjaja/ 301s to it (audit §2.3).
			'hub'      => false,
			'title'    => 'Organizacija i koordinacija vjenčanja Sarajevo | DIP Studio',
			'h1'       => 'Organizacija i koordinacija vjenčanja i događaja u BiH',
			'desc'     => 'Planiranje prije događaja i koordinacija satnice, sale i izvođača na sam dan vjenčanja ili događaja u Sarajevu i BiH.',
			'intro'    => 'Organizacija obuhvata planiranje prije događaja, a koordinacija vođenje satnice i izvođača na sam dan događaja – sale, benda ili DJ-a, fotografa i tima za specijalne efekte.',
			'services' => array(
				'organizacija-i-koordinacija' => array( 'Organizacija i koordinacija vjenčanja i događaja', 'Planiranje prije događaja i koordinacija satnice i izvođača na sam dan događaja.' ),
			),
		),
	);
}

/** Flat list: slug => [name, description, category_slug]. */
function dip_seo_services(): array {
	$out = array();
	foreach ( dip_seo_categories() as $cat_slug => $cat ) {
		foreach ( $cat['services'] as $slug => $svc ) {
			$out[ $slug ] = array( $svc[0], $svc[1], $cat_slug );
		}
	}
	return $out;
}

/** 36 cities listed on the homepage + the country. */
function dip_seo_cities(): array {
	return array(
		'Sarajevo', 'Mostar', 'Tuzla', 'Zenica', 'Banja Luka', 'Bihać', 'Brčko', 'Bijeljina', 'Trebinje',
		'Doboj', 'Travnik', 'Cazin', 'Srebrenik', 'Gradačac', 'Konjic', 'Jablanica', 'Goražde', 'Foča',
		'Višegrad', 'Prijedor', 'Gradiška', 'Sanski Most', 'Visoko', 'Kakanj', 'Živinice', 'Zavidovići',
		'Žepče', 'Maglaj', 'Bugojno', 'Jajce', 'Livno', 'Tomislavgrad', 'Široki Brijeg', 'Čapljina',
		'Posušje', 'Tešanj',
	);
}

/** Business facts. Only verified data — street address intentionally omitted until the owner supplies it. */
function dip_seo_business(): array {
	return array(
		'name'       => 'DIP Studio',
		'url'        => 'https://dipstudio.ba/',
		'telephone'  => '+387603080020',
		'phone_disp' => '+387 60 308 0020',
		'email'      => 'kontakt@dipstudio.ba',
		'locality'   => 'Sarajevo',
		'region'     => 'Kanton Sarajevo',
		'postal'     => '71000',
		'country'    => 'BA',
		'street'     => '', // TODO(owner): fill in to complete NAP — see docs/IMPLEMENTATION_STATUS.md.
		'logo'       => 'https://dipstudio.ba/wp-content/uploads/2026/10/dip-logo-white-optimized.webp',
		'same_as'    => array( 'https://www.instagram.com/dipstudio.ba/' ),
	);
}
