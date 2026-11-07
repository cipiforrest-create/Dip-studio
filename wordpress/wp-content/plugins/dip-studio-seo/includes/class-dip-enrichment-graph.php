<?php
/**
 * Pure JSON-LD reconciliation. No WordPress runtime dependency.
 * Never deletes or disables schema emitted by another plugin.
 */
if (!defined('DIP_ENRICHMENT_GRAPH_LOADED')) {
    define('DIP_ENRICHMENT_GRAPH_LOADED', true);
    final class DIP_Enrichment_Graph {
        public static function rewrite(string $head, array $context): string {
            if (empty($context['home'])) { return $head; }
            $home = rtrim((string) $context['home'], '/') . '/';
            $org_id = $home . '#organization';
            $has_service = false;
            $has_faq = false;
            $has_article = false;
            $has_org = false;
            $has_list = false;
            $json_pattern = '~(<script\b[^>]*\btype\s*=\s*["\']application/ld\+json["\'][^>]*>)([\s\S]*?)(</script\s*>)~i';
            $replacement = preg_replace_callback($json_pattern, function ($m) use ($context, $home, $org_id, &$has_service, &$has_faq, &$has_article, &$has_org, &$has_list) {
                $doc = json_decode(trim($m[2]), true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($doc)) {
                    return $m[0];
                }
                $before = $doc;
                if (!array_key_exists('normalize_staging_id', $context) || $context['normalize_staging_id'] !== false) {
                    self::normalize_legacy_refs($doc, $home, $org_id);
                }
                self::walk_nodes($doc, function (&$node) use ($context, $org_id, &$has_service, &$has_faq, &$has_article, &$has_org, &$has_list) {
                    if (!isset($node['@type'])) { return; }
                    $types = (array) $node['@type'];
                    if (in_array('Organization', $types, true) || in_array('LocalBusiness', $types, true)) {
                        if (in_array('Organization', $types, true) || !empty($node['@id'])) {
                            $has_org = true;
                        }
                        if (isset($context['catalog']) && in_array('Organization', $types, true) && empty($node['hasOfferCatalog'])) {
                            $node['hasOfferCatalog'] = $context['catalog'];
                        }
                    }
                    if (in_array('WebPage', $types, true) && !empty($context['page_type'])) {
                        $specialized = ['AboutPage', 'ContactPage', 'CollectionPage', 'ProfilePage', 'FAQPage', 'CheckoutPage'];
                        if (!array_intersect($types, $specialized)) {
                            $node['@type'] = count($types) === 1 ? $context['page_type'] : array_values(array_unique(array_merge($types, [$context['page_type']])));
                        }
                    }
                    if ((in_array('WebPage', $types, true) || in_array('CollectionPage', $types, true)) && !empty($context['list']) && empty($node['mainEntity'])) {
                        $node['mainEntity'] = $context['list'];
                        $has_list = true;
                    }
                    if (in_array('ItemList', $types, true)) { $has_list = true; }
                    if (in_array('Service', $types, true) && !empty($context['service'])) {
                        $has_service = true;
                        self::fill_service($node, $context['service']);
                    }
                    if (in_array('FAQPage', $types, true)) { $has_faq = true; }
                    if (in_array('Article', $types, true) || in_array('BlogPosting', $types, true)) {
                        $has_article = true;
                        if (!empty($context['blog'])) {
                            self::fill_missing($node, $context['blog'], ['headline', 'datePublished', 'dateModified', 'mainEntityOfPage', 'author', 'publisher', 'image', 'about']);
                        }
                    }
                });
                if ($doc === $before) { return $m[0]; }
                $encoded = self::encode($doc);
                return $encoded === false ? $m[0] : $m[1] . $encoded . $m[3];
            }, $head);
            if ($replacement === null) { return $head; }
            $extra = [];
            if (!$has_service && !empty($context['service'])) { $extra[] = $context['service']; }
            if (!$has_faq && !empty($context['faq'])) { $extra[] = $context['faq']; }
            if (!$has_article && !empty($context['blog'])) { $extra[] = $context['blog']; }
            if (!$has_list && !empty($context['list'])) { $extra[] = $context['list']; }
            // A catalog belongs to Organization and is never emitted as a second Organization node.
            foreach ($extra as $node) {
                $encoded = self::encode(['@context' => 'https://schema.org'] + $node);
                if ($encoded !== false) {
                    $replacement .= "\n<script type=\"application/ld+json\" data-dip-enrichment=\"1\">" . $encoded . '</script>';
                }
            }
            return $replacement;
        }
        private static function normalize_legacy_refs(array &$value, string $home, string $new_id): void {
            $legacy = [$home . 'stagging/#Organization', $home . 'staging/#Organization'];
            foreach ($value as &$v) {
                if (is_array($v)) { self::normalize_legacy_refs($v, $home, $new_id); }
                elseif (is_string($v) && in_array($v, $legacy, true)) { $v = $new_id; }
            }
            unset($v);
        }
        private static function walk_nodes(array &$value, callable $callback): void {
            if (isset($value['@type'])) { $callback($value); }
            foreach ($value as &$v) {
                if (is_array($v)) { self::walk_nodes($v, $callback); }
            }
            unset($v);
        }
        private static function fill_missing(array &$target, array $source, array $keys): void {
            foreach ($keys as $key) {
                if (empty($target[$key]) && !empty($source[$key])) { $target[$key] = $source[$key]; }
            }
        }
        private static function fill_service(array &$target, array $source): void {
            self::fill_missing($target, $source, ['@id', 'url', 'name', 'description', 'serviceType', 'provider', 'areaServed', 'image', 'mainEntityOfPage', 'availableChannel', 'subjectOf']);
            if (isset($target['provider']) && is_array($target['provider']) && empty($target['provider']['@id']) && !empty($source['provider']['@id'])) {
                $target['provider']['@id'] = $source['provider']['@id'];
            }
            if (!empty($source['additionalProperty']) && is_array($source['additionalProperty'])) {
                $existing = isset($target['additionalProperty']) && is_array($target['additionalProperty']) ? $target['additionalProperty'] : [];
                $seen = [];
                foreach ($existing as $item) {
                    if (is_array($item)) { $seen[strtolower((string)($item['name'] ?? '') . '|' . (string)($item['value'] ?? ''))] = true; }
                }
                foreach ($source['additionalProperty'] as $item) {
                    if (!is_array($item)) { continue; }
                    $key = strtolower((string)($item['name'] ?? '') . '|' . (string)($item['value'] ?? ''));
                    if (!isset($seen[$key])) { $existing[] = $item; $seen[$key] = true; }
                }
                if ($existing) { $target['additionalProperty'] = $existing; }
            }
        }
        private static function encode(array $data) {
            return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_INVALID_UTF8_SUBSTITUTE);
        }
    }
}
