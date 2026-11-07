<?php
if (!defined('ABSPATH')) { exit; }
final class DIP_Enrichment_Plugin {
    private static ?self $instance = null;
    private bool $buffering = false;
    private array $context = [];
    private const OPTION = DIP_STUDIO_SEO_OPTION;
    private const OLD_OPTION = 'dip_enrichment_settings';
    private const SPEC_META = '_dip_enrich_verified_specs';
    private const VIDEO_META = '_dip_enrich_video';

    public static function boot(): void {
        if (self::$instance !== null) { return; }
        self::$instance = new self();
        add_action('wp_head', [self::$instance, 'start_head_buffer'], -99999);
        add_action('wp_head', [self::$instance, 'end_head_buffer'], PHP_INT_MAX);
        if (is_admin()) {
            add_action('add_meta_boxes_dip_service', [self::$instance, 'register_service_box']);
            add_action('save_post_dip_service', [self::$instance, 'save_service_meta'], 10, 2);
            add_action('admin_menu', [self::$instance, 'admin_menu']);
            add_action('admin_post_dip_enrichment_save', [self::$instance, 'save_settings']);
            add_action('admin_post_dip_seo_alt_fix', [self::$instance, 'apply_alt_fixes']);
        }
        add_action('save_post_dip_service', [self::$instance, 'clear_catalog_cache'], 99);
        add_action('deleted_post', [self::$instance, 'clear_catalog_cache'], 99);
        add_action('trashed_post', [self::$instance, 'clear_catalog_cache'], 99);
    }
    public static function activate(): void {
        if (get_option(self::OPTION, null) === null) {
            $settings = dip_studio_seo_defaults();
            $old = get_option(self::OLD_OPTION, null);
            if (is_array($old)) { foreach ($old as $k=>$v) { if (array_key_exists($k, $settings)) { $settings[$k] = !empty($v) ? 1 : 0; } } }
            add_option(self::OPTION, $settings, '', false);
        }
    }
    private function setting(string $key): bool {
        return dip_studio_seo_setting($key);
    }
    private function is_public_request(): bool {
        if (!$this->setting('enabled') || is_admin() || is_feed() || is_preview() || is_404() || (defined('REST_REQUEST') && REST_REQUEST) || (defined('DOING_AJAX') && DOING_AJAX)) {
            return false;
        }
        if (function_exists('is_robots') && is_robots()) { return false; }
        if (is_singular() && (get_post_status(get_queried_object_id()) !== 'publish' || post_password_required(get_queried_object_id()))) {
            return false;
        }
        return true;
    }
    public function start_head_buffer(): void {
        if (!$this->is_public_request()) { return; }
        $this->context = $this->build_context();
        if (empty($this->context)) { return; }
        $this->context['home'] = home_url('/');
        if (!$this->setting('normalize_staging_id')) { $this->context['normalize_staging_id'] = false; }
        $this->buffering = true;
        ob_start();
    }
    public function end_head_buffer(): void {
        if (!$this->buffering) { return; }
        $this->buffering = false;
        $html = ob_get_clean();
        if (!is_string($html)) { return; }
        try { echo DIP_Enrichment_Graph::rewrite($html, $this->context); }
        catch (Throwable $e) { error_log('DIP Enrichment: ' . $e->getMessage()); echo $html; }
    }
    private function build_context(): array {
        $ctx = [];
        if (is_singular('dip_service')) {
            $post = get_queried_object();
            if ($post instanceof WP_Post) {
                $ctx['service'] = $this->service_node($post);
                if ($this->setting('auto_faq')) {
                    $faq = $this->faq_from_visible_details($post);
                    if ($faq !== null) { $ctx['faq'] = $faq; }
                }
            }
        } elseif (is_front_page() && $this->setting('catalog')) {
            $catalog = $this->service_catalog();
            if ($catalog) { $ctx['catalog'] = $catalog; }
        } elseif (is_page('usluge') && $this->setting('catalog')) {
            $list = $this->service_list();
            if ($list) { $ctx['list'] = $list; }
        } elseif (is_singular('post') && $this->setting('blog')) {
            $post = get_queried_object();
            if ($post instanceof WP_Post) { $ctx['blog'] = $this->article_node($post); }
        }
        if (is_page('o-nama')) { $ctx['page_type'] = 'AboutPage'; }
        elseif (is_page('kontakt')) { $ctx['page_type'] = 'ContactPage'; }
        elseif (is_page(['usluge','specijalni-efekti','rasvjeta','vatrometi','photo-fun','organizacija-dogadjaja','galerija'])) {
            $ctx['page_type'] = 'CollectionPage';
        }
        // Always audit and repair *only* the exact legacy /stagging/ Organization URI.
        // This does not create any additional Organization or LocalBusiness node.
        if (!$ctx && $this->setting('normalize_staging_id')) { $ctx['normalize_staging_id'] = true; }
        return $ctx;
    }
    private function service_node(WP_Post $post): array {
        $url = get_permalink($post);
        $name = trim(wp_strip_all_tags(get_the_title($post), true));
        $desc = trim(wp_strip_all_tags($post->post_excerpt, true));
        if ($desc === '') {
            $desc = wp_trim_words(wp_strip_all_tags(strip_shortcodes($post->post_content), true), 45, '…');
        }
        $node = [
            '@type'=>'Service',
            '@id'=>trailingslashit($url) . '#service',
            'name'=>$name,
            'url'=>$url,
            'serviceType'=>$name,
            'provider'=>['@id'=>home_url('/#organization')],
            'areaServed'=>['@type'=>'Country', 'name'=>'Bosna i Hercegovina'],
            'mainEntityOfPage'=>['@id'=>trailingslashit($url).'#webpage'],
            'availableChannel'=>[
                '@type'=>'ServiceChannel',
                'serviceUrl'=>home_url('/konfigurator/'),
            ],
        ];
        if ($desc !== '') { $node['description'] = $desc; }
        $image_id = (int)get_post_thumbnail_id($post);
        if ($image_id) {
            $image = wp_get_attachment_image_src($image_id, 'full');
            if ($image && !empty($image[0])) {
                $node['image'] = [
                    '@type'=>'ImageObject',
                    'contentUrl'=>esc_url_raw($image[0]),
                    'width'=>(int)$image[1],
                    'height'=>(int)$image[2],
                ];
                $alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
                if (is_string($alt) && trim($alt) !== '') { $node['image']['caption'] = wp_strip_all_tags($alt); }
            }
        }
        $properties = $this->parse_spec_lines((string)get_post_meta($post->ID, self::SPEC_META, true));
        $visible_text = wp_strip_all_tags(strip_shortcodes($post->post_content));
        $properties = array_values(array_filter($properties, static function ($property) use ($visible_text) {
            $value = (string)$property['value'];
            return function_exists('mb_stripos') ? mb_stripos($visible_text, $value, 0, 'UTF-8') !== false : stripos($visible_text, $value) !== false;
        }));
        if ($properties) { $node['additionalProperty'] = $properties; }
        $video = get_post_meta($post->ID, self::VIDEO_META, true);
        if (is_array($video) && !empty($video['url']) && !empty($video['thumbnail']) && !empty($video['date'])) {
            $url = esc_url_raw($video['url']);
            // Schema for a VIDEO only if the real asset is present within the visible post content.
            if (strpos($post->post_content, $video['url']) !== false && preg_match('~^https://~i', $url)) {
                $node['subjectOf'] = [
                    '@type'=>'VideoObject',
                    'name'=>$name . ' — video',
                    'description'=>$desc ?: $name,
                    'thumbnailUrl'=>esc_url_raw($video['thumbnail']),
                    'uploadDate'=>sanitize_text_field($video['date']),
                ];
                if (preg_match('~\.(?:mp4|webm)(?:\?.*)?$~i', $url)) { $node['subjectOf']['contentUrl'] = $url; }
                else { $node['subjectOf']['embedUrl'] = $url; }
            }
        }
        return $node;
    }
    public function parse_spec_lines(string $text): array {
        $properties = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim($line);
            if ($line === '' || !str_contains($line, ':')) { continue; }
            [$name, $value] = array_map('trim', explode(':', $line, 2));
            $name = sanitize_text_field($name);
            $value = sanitize_text_field($value);
            if ($name === '' || $value === '') { continue; }
            if (strlen($name) > 120 || strlen($value) > 400) { continue; }
            // Never expose an attribute in schema unless its value also appears visibly in this page's content.
            $properties[] = ['@type'=>'PropertyValue', 'name'=>$name, 'value'=>$value];
            if (count($properties) >= 20) { break; }
        }
        return $properties;
    }
    private function faq_from_visible_details(WP_Post $post): ?array {
        $content = $post->post_content;
        if (!str_contains($content, '<details')) { return null; }
        // Drop Gutenberg block comments (<!-- wp:paragraph -->) so they never leak into answer text.
        $content = (string)preg_replace('/<!--.*?-->/s', '', $content);
        // PHP's DOM extension is optional on many hosting plans. A conservative native
        // <details>/<summary> fallback supports core WordPress FAQ markup when absent.
        if (!class_exists('DOMDocument')) {
            $questions = [];
            if (preg_match_all('~<details\b[^>]*>(.*?)</details>~si', $content, $matches)) {
                foreach ($matches[1] as $body) {
                    if (!preg_match('~<summary\b[^>]*>(.*?)</summary>~si', $body, $parts)) { continue; }
                    $question = trim(html_entity_decode(wp_strip_all_tags($parts[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    $answer = trim(html_entity_decode(wp_strip_all_tags(str_replace($parts[0], '', $body)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    $question = trim(preg_replace('/\s+/u', ' ', $question));
                    $answer = trim(preg_replace('/\s+/u', ' ', $answer));
                    if (strlen($question) < 7 || strlen($answer) < 12) { continue; }
                    $questions[] = ['@type'=>'Question', 'name'=>$question, 'acceptedAnswer'=>['@type'=>'Answer', 'text'=>$answer]];
                    if (count($questions) >= 8) { break; }
                }
            }
            return $questions ? ['@type'=>'FAQPage', '@id'=>trailingslashit(get_permalink($post)) . '#faq', 'mainEntity'=>$questions] : null;
        }
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="utf-8"?><div>' . $content . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) { return null; }
        $questions = [];
        foreach ($dom->getElementsByTagName('details') as $detail) {
            $summary_node = null;
            foreach ($detail->childNodes as $child) {
                if ($child instanceof DOMElement && strtolower($child->tagName) === 'summary') { $summary_node = $child; break; }
            }
            if (!$summary_node) { continue; }
            $question = trim(preg_replace('/\s+/u', ' ', $summary_node->textContent));
            $answer = '';
            foreach ($detail->childNodes as $child) {
                if ($child === $summary_node) { continue; }
                $answer .= ' ' . $child->textContent;
            }
            $answer = trim(preg_replace('/\s+/u', ' ', $answer));
            $qlen = function_exists('mb_strlen') ? mb_strlen($question) : strlen($question);
            $alen = function_exists('mb_strlen') ? mb_strlen($answer) : strlen($answer);
            if ($qlen < 7 || $alen < 12) { continue; }
            $questions[] = ['@type'=>'Question', 'name'=>wp_strip_all_tags($question), 'acceptedAnswer'=>['@type'=>'Answer', 'text'=>wp_strip_all_tags($answer)]];
            if (count($questions) >= 8) { break; }
        }
        if (!$questions) { return null; }
        return ['@type'=>'FAQPage', '@id'=>trailingslashit(get_permalink($post)) . '#faq', 'mainEntity'=>$questions];
    }
    private function service_ids(): array {
        $cached = get_transient('dip_enrich_service_ids_v1');
        if (is_array($cached)) { return $cached; }
        $ids = get_posts([
            'post_type'=>'dip_service', 'post_status'=>'publish',
            'posts_per_page'=>-1, 'fields'=>'ids', 'orderby'=>'title', 'order'=>'ASC',
            'no_found_rows'=>true, 'suppress_filters'=>false,
        ]);
        $ids = array_values(array_map('intval', $ids));
        set_transient('dip_enrich_service_ids_v1', $ids, 12 * HOUR_IN_SECONDS);
        return $ids;
    }
    private function catalog_elements(): array {
        $items = [];
        foreach ($this->service_ids() as $id) {
            $url = get_permalink($id);
            if (!$url) { continue; }
            $items[] = [
                '@type'=>'ListItem', 'position'=>count($items)+1,
                'item'=>['@id'=>trailingslashit($url).'#service', 'name'=>wp_strip_all_tags(get_the_title($id)), 'url'=>$url],
            ];
        }
        return $items;
    }
    private function service_catalog(): ?array {
        $items = $this->catalog_elements();
        if (!$items) { return null; }
        return ['@type'=>'OfferCatalog', '@id'=>home_url('/#services-catalog'), 'name'=>'Usluge DIP Studija', 'itemListElement'=>$items];
    }
    private function service_list(): ?array {
        $items = $this->catalog_elements();
        if (!$items) { return null; }
        return ['@type'=>'ItemList', '@id'=>home_url('/usluge/#service-list'), 'name'=>'Sve usluge DIP Studija', 'itemListElement'=>$items];
    }
    private function article_node(WP_Post $post): array {
        $url = get_permalink($post);
        $node = [
            '@type'=>'BlogPosting', '@id'=>trailingslashit($url).'#article',
            'headline'=>wp_strip_all_tags(get_the_title($post)),
            'mainEntityOfPage'=>['@id'=>trailingslashit($url).'#webpage'],
            'publisher'=>['@id'=>home_url('/#organization')],
            'datePublished'=>get_the_date('c', $post),
            'dateModified'=>get_the_modified_date('c', $post),
        ];
        $author = trim((string)get_the_author_meta('display_name', $post->post_author));
        if ($author !== '') { $node['author'] = ['@type'=>'Person','name'=>$author]; }
        $excerpt = trim(wp_strip_all_tags($post->post_excerpt));
        if ($excerpt !== '') { $node['description'] = $excerpt; }
        $image = get_the_post_thumbnail_url($post, 'full');
        if ($image) { $node['image'] = esc_url_raw($image); }
        return $node;
    }
    public function clear_catalog_cache(): void { delete_transient('dip_enrich_service_ids_v1'); }
    public function register_service_box(): void {
        add_meta_box('dip-enrichment-data', 'DIP SEO — potvrđeni podaci', [$this, 'render_service_box'], 'dip_service', 'normal', 'default');
    }
    public function render_service_box(WP_Post $post): void {
        wp_nonce_field('dip_enrich_save_service', 'dip_enrich_nonce');
        $specs = (string)get_post_meta($post->ID, self::SPEC_META, true);
        $video = get_post_meta($post->ID, self::VIDEO_META, true);
        $video = is_array($video) ? $video : [];
        echo '<p>Unesite samo provjerene činjenice koje su također vidljive na ovoj stranici. Svaki red: <code>Naziv: Vrijednost</code>.</p>';
        echo '<textarea name="dip_enrich_specs" rows="6" style="width:100%" placeholder="Namjena: Prvi ples">'.esc_textarea($specs).'</textarea>';
        echo '<p><strong>Video (opcionalno):</strong> Schema se dodaje samo ako je isti video URL prisutan u sadržaju stranice.</p>';
        foreach (['url'=>'Video URL', 'thumbnail'=>'Thumbnail URL', 'date'=>'Datum objave (ISO 8601)'] as $key=>$label) {
            $type = $key === 'date' ? 'text' : 'url';
            echo '<p><label for="dip-video-'.esc_attr($key).'">'.esc_html($label).'</label><br>';
            echo '<input id="dip-video-'.esc_attr($key).'" type="'.esc_attr($type).'" style="width:100%" name="dip_enrich_video['.esc_attr($key).']" value="'.esc_attr((string)($video[$key]??'')).'"></p>';
        }
    }
    public function save_service_meta(int $post_id, WP_Post $post): void {
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)) { return; }
        if (empty($_POST['dip_enrich_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dip_enrich_nonce'])), 'dip_enrich_save_service')) { return; }
        if (!current_user_can('edit_post', $post_id)) { return; }
        $text = isset($_POST['dip_enrich_specs']) && is_string($_POST['dip_enrich_specs']) ? sanitize_textarea_field(wp_unslash($_POST['dip_enrich_specs'])) : '';
        update_post_meta($post_id, self::SPEC_META, $text);
        $v = isset($_POST['dip_enrich_video']) && is_array($_POST['dip_enrich_video']) ? wp_unslash($_POST['dip_enrich_video']) : [];
        $url = isset($v['url']) && is_string($v['url']) ? esc_url_raw($v['url'], ['https']) : '';
        $thumb = isset($v['thumbnail']) && is_string($v['thumbnail']) ? esc_url_raw($v['thumbnail'], ['https']) : '';
        $date = isset($v['date']) && is_string($v['date']) ? sanitize_text_field($v['date']) : '';
        if ($date && !preg_match('/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2})?(Z|[+-]\d{2}:\d{2})?)?$/', $date)) { $date = ''; }
        update_post_meta($post_id, self::VIDEO_META, ['url'=>$url,'thumbnail'=>$thumb,'date'=>$date]);
        $this->clear_catalog_cache();
    }
    public function admin_menu(): void {
        add_management_page('DIP SEO', 'DIP SEO', 'manage_options', 'dip-seo-enrichment', [$this, 'render_admin_page']);
    }
    public function render_admin_page(): void {
        if (!current_user_can('manage_options')) { return; }
        echo '<div class="wrap"><h1>DIP Studio — SEO <small>'.esc_html(DIP_STUDIO_SEO_VERSION).'</small></h1>';
        echo '<p>Nadograđuje postojeću SiteSEO, Service i FAQ JSON-LD strukturu i sadrži tehničke SEO popravke. Ne mijenja meta naslove ili opise i ne dodaje izmišljene cijene. Nakon svake promjene očistite SpeedyCache.</p>';
        if (isset($_GET['updated'])) { echo '<div class="notice notice-success"><p>Postavke sačuvane.</p></div>'; }
        if (isset($_GET['alt_fixed'])) { echo '<div class="notice notice-success"><p>Ispravljeno alt tekstova: '.(int)$_GET['alt_fixed'].'.</p></div>'; }
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        echo '<input type="hidden" name="action" value="dip_enrichment_save">';
        wp_nonce_field('dip_enrichment_settings');
        echo '<h2>Schema (JSON-LD)</h2>';
        $labels = [
            'enabled'=>'Uključi schema enrichment',
            'normalize_staging_id'=>'Ispravi samo zastarjeli /stagging/ Organization @id',
            'auto_faq'=>'FAQ iz vidljivih details pitanja, samo ako drugi plugin nema FAQPage',
            'catalog'=>'Katalog usluga na početnoj i ItemList na /usluge/',
            'blog'=>'BlogPosting gdje ne postoji postojeći Article/BlogPosting',
            '__fixes'=>'Tehnički popravci',
            'staging_410'=>'Stari /stagging/ linkovi vraćaju 410 (Google ih briše iz indeksa)',
            'redirects'=>'301 sa /2026/10/24/naslov/ na /naslov/, datumske arhive na blog, stari linkovi (/projekti, /organizacija-dogadjaja, kategorije) na prave stranice',
            'rewrite_urls'=>'U HTML-u prepiši /stagging/wp-content/ linkove u /wp-content/',
            'heading_fix'=>'Razmak prije <br> u naslovima (da Google ne čita „STVARAMOTRENUTKE”)',
            'dup_excerpt'=>'Ukloni duplu prvu rečenicu na blog članku',
            'robots'=>'robots.txt sa AI botovima i /sitemaps.xml',
            'gallery_clean'=>'Galerija: sakrij duple veličine iste slike (-w960/-w480) i sistemske slike (ikona, header preview, file_0000…)',
            'author_redirect'=>'/author/… arhive preusmjeri (301) na blog',
            'footer_headings'=>'Naslovi kolona u footeru kao <p> umjesto <h2> (izgled ostaje isti)',
            '__audit'=>'Drugi audit (6. 11. 2026)',
            'price_copy'=>'Zamijeni stare rečenice „Cijene su na upit” (sada postoji javni cjenovnik) i prikaži „0–2.000 KM” kao „do 2.000 KM”',
            'blog_prices'=>'Članak „Šta utiče na cijenu…”: tabela cijena, novi odgovor na „Ima li DIP Studio javni cjenovnik?” i linkovi na usluge',
            'price_badges'=>'„od 200 KM” na karticama usluga i raspon cijene uz svaku uslugu u konfiguratoru',
            'home_faq'=>'Česta pitanja na početnoj (4 pitanja) + FAQPage schema',
            'gallery_hide_ai'=>'Galerija: ne prikazuj ilustracije (dip-img-scena-*) kao fotografije s događaja',
            'alt_render'=>'Popravi engleske, prazne i „Dip Galerija …” alt tekstove u HTML-u stranice',
            'schema_tidy'=>'Schema: jedan Organization @id, BlogPosting bez datuma u @id, „Početna” i „Usluge” u breadcrumbu, raspon cijena',
            'xml_decl'=>'Ukloni zalutali <?xml encoding="utf-8" ?> iz sadržaja stranica',
            'llms'=>'Vlastiti /llms.txt iz dodatka (isključeno: SiteSEO već pravi llms.txt)',
        ];
        foreach ($labels as $key=>$label) {
            if ($key === '__fixes' || $key === '__audit') { echo '<h2>'.esc_html($label).'</h2>'; continue; }
            echo '<p><label><input type="checkbox" name="dip_enrich_settings['.esc_attr($key).']" value="1" '.checked($this->setting($key), true, false).'> '.esc_html($label).'</label></p>';
        }
        submit_button('Sačuvaj postavke');
        echo '</form><h2>Objavljene usluge ('.count($this->service_ids()).')</h2>';
        echo '<p>Za potvrđene tehničke specifikacije otvorite uslugu, zatim sekciju “DIP SEO — potvrđeni podaci”.</p><ul>';
        foreach ($this->service_ids() as $id) {
            $url = get_edit_post_link($id);
            echo '<li><a href="'.esc_url($url).'">'.esc_html(get_the_title($id)).'</a></li>';
        }
        echo '</ul>';
        $this->render_alt_tool();
        echo '<p>Validacija: <a href="https://validator.schema.org/" target="_blank" rel="noopener noreferrer">Schema.org Validator</a> · <a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener noreferrer">Google Rich Results Test</a></p></div>';
    }
    public function save_settings(): void {
        if (!current_user_can('manage_options')) { wp_die('Insufficient permissions'); }
        check_admin_referer('dip_enrichment_settings');
        $incoming = isset($_POST['dip_enrich_settings']) && is_array($_POST['dip_enrich_settings']) ? wp_unslash($_POST['dip_enrich_settings']) : [];
        $new = [];
        foreach (array_keys(dip_studio_seo_defaults()) as $key) { $new[$key] = !empty($incoming[$key]) ? 1 : 0; }
        update_option(self::OPTION, $new, false);
        wp_safe_redirect(add_query_arg(['page'=>'dip-seo-enrichment','updated'=>1], admin_url('tools.php')));
        exit;
    }

    /* ---------------- Gallery alt-text fixer (admin version of `wp dip-seo gallery-alt`) ---------------- */
    private function alt_candidates(): array {
        $rows = [];
        $ids = get_posts(['post_type'=>'attachment','post_mime_type'=>'image','post_status'=>'inherit','posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true]);
        foreach ($ids as $id) {
            $alt = (string)get_post_meta($id, '_wp_attachment_image_alt', true);
            if (trim($alt) === '' && function_exists('dip_seo_empty_alt_map')) {
                $stem = dip_seo_file_stem((string)get_post_meta($id, '_wp_attached_file', true));
                $empty = dip_seo_empty_alt_map();
                if (isset($empty[$stem])) {
                    $rows[] = ['id'=>(int)$id, 'type'=>'empty', 'current'=>'(prazno)', 'suggestion'=>$empty[$stem], 'auto'=>true];
                    continue;
                }
            }
            if (trim($alt) === '') { $alt = (string)get_the_title($id); }
            if (preg_match('/^dip\s+(?!studio\b|galerija\b)/i', $alt) && !preg_match('/[a-zčćšđž]\s[a-zčćšđž]/u', $alt)) {
                $alt = (string)preg_replace('/^dip\s+/i', 'Dip Galerija ', $alt);
            }
            $c = dip_seo_classify_alt($alt);
            if ($c['type'] !== 'prompt' && $c['type'] !== 'slug') { continue; }
            if ($c['type'] === 'prompt' && !$c['suggestion']) {
                // Match on the English part only (before " — "), in case the stored alt has no Bosnian suffix.
                foreach (dip_seo_alt_prompt_map() as $prefix=>$replacement) {
                    $en = trim((string)preg_replace('/\s+—.*$/u', '', $prefix));
                    if ($en !== '' && stripos($alt, $en) === 0) { $c['suggestion'] = $replacement; $c['auto'] = true; break; }
                }
            }
            $rows[] = ['id'=>(int)$id, 'type'=>$c['type'], 'current'=>$alt, 'suggestion'=>(string)$c['suggestion'], 'auto'=>!empty($c['auto'])];
        }
        return $rows;
    }
    private function render_alt_tool(): void {
        $rows = $this->alt_candidates();
        echo '<h2>Alt tekst slika</h2>';
        if (!$rows) { echo '<p>Nema slika sa engleskim ili slug alt tekstom.</p>'; return; }
        echo '<p>Slike sa engleskim (AI prompt) ili slug alt tekstom: '.count($rows).'. Označene su predložene zamjene; ostale prepravite ručno u Medijima. Ništa se ne mijenja dok ne kliknete dugme.</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="dip_seo_alt_fix">';
        wp_nonce_field('dip_seo_alt_fix');
        echo '<table class="widefat striped"><thead><tr><th></th><th>Slika</th><th>Sadašnji alt</th><th>Prijedlog</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $box = $r['suggestion'] !== '' ? '<input type="checkbox" name="dip_alt_ids[]" value="'.(int)$r['id'].'" '.checked($r['auto'], true, false).'>' : '';
            $edit = get_edit_post_link($r['id']);
            echo '<tr><td>'.$box.'</td><td><a href="'.esc_url((string)$edit).'">#'.(int)$r['id'].'</a></td><td>'.esc_html($r['current']).'</td><td>'.($r['suggestion'] !== '' ? esc_html($r['suggestion']) : '<em>prepraviti ručno</em>').'</td></tr>';
        }
        echo '</tbody></table>';
        submit_button('Primijeni označene prijedloge');
        echo '</form>';
    }
    public function apply_alt_fixes(): void {
        if (!current_user_can('upload_files') || !current_user_can('manage_options')) { wp_die('Insufficient permissions'); }
        check_admin_referer('dip_seo_alt_fix');
        $ids = isset($_POST['dip_alt_ids']) && is_array($_POST['dip_alt_ids']) ? array_map('intval', wp_unslash($_POST['dip_alt_ids'])) : [];
        $by_id = [];
        foreach ($this->alt_candidates() as $r) { if ($r['suggestion'] !== '') { $by_id[$r['id']] = $r['suggestion']; } }
        $done = 0;
        foreach (array_unique($ids) as $id) {
            if (!isset($by_id[$id])) { continue; }
            update_post_meta($id, '_wp_attachment_image_alt', $by_id[$id]);
            ++$done;
        }
        wp_safe_redirect(add_query_arg(['page'=>'dip-seo-enrichment','alt_fixed'=>$done], admin_url('tools.php')));
        exit;
    }
}
