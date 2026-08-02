<?php
if (!defined('ABSPATH')) { exit; }

final class Orion_Product_Search {
    /**
     * Searches published WooCommerce products using text, category, stock and price filters.
     *
     * @param array|string $request Structured tool arguments or a legacy query string.
     */
    public function search(array|string $request, int $legacy_limit = 6): array {
        if (!class_exists('WooCommerce')) return array();

        $args = is_array($request) ? $request : array('query' => $request, 'limit' => $legacy_limit);
        $query = sanitize_text_field((string) ($args['query'] ?? ''));
        $category = sanitize_text_field((string) ($args['category'] ?? ''));
        $limit = max(1, min(8, (int) ($args['limit'] ?? 6)));
        $min_price = isset($args['min_price']) && is_numeric($args['min_price']) ? max(0, (float) $args['min_price']) : null;
        $max_price = isset($args['max_price']) && is_numeric($args['max_price']) ? max(0, (float) $args['max_price']) : null;
        $in_stock = !isset($args['in_stock']) || filter_var($args['in_stock'], FILTER_VALIDATE_BOOLEAN);
        $sort = in_array($args['sort'] ?? 'relevance', array('relevance', 'price_asc', 'price_desc'), true) ? $args['sort'] : 'relevance';

        $ids = $this->find_ids($query, $category, $min_price, $max_price, $in_stock, $sort, $limit);

        // Models may use a category label that does not exactly match the store
        // taxonomy. Do not let that hide otherwise relevant catalogue results.
        if (!$ids && $category !== '') {
            $ids = $this->find_ids($query, '', $min_price, $max_price, $in_stock, $sort, $limit);
        }

        // WordPress search can be strict with long natural-language phrases. Retry with useful tokens.
        if (!$ids && $query !== '') {
            $tokens = $this->keywords($query);
            foreach (array_slice($tokens, 0, 5) as $token) {
                $ids = array_merge($ids, $this->find_ids($token, $category, $min_price, $max_price, $in_stock, $sort, $limit));
                $ids = array_values(array_unique($ids));
                if (count($ids) >= $limit) break;
            }
        }

        // SKU fallback.
        if (!$ids && $query !== '') {
            $sku_id = wc_get_product_id_by_sku($query);
            if ($sku_id) $ids[] = $sku_id;
        }

        $products = array();
        foreach (array_slice($ids, 0, $limit) as $id) {
            try {
                $product = wc_get_product($id);
                if (!$product || !$product->is_visible()) continue;
                if ($in_stock && !$product->is_in_stock()) continue;
                $products[] = $this->format($product);
            } catch (Throwable $error) {
                error_log('Orion AI product formatting error for product ' . (int) $id . ': ' . $error->getMessage());
            }
        }
        return $products;
    }

    public function get_product(int $product_id): ?array {
        if (!class_exists('WooCommerce') || $product_id < 1) return null;
        try {
            $product = wc_get_product($product_id);
            if (!$product || !$product->is_visible()) return null;
            return $this->format($product);
        } catch (Throwable $error) {
            error_log('Orion AI product lookup error for product ' . $product_id . ': ' . $error->getMessage());
            return null;
        }
    }

    private function find_ids(string $query, string $category, ?float $min_price, ?float $max_price, bool $in_stock, string $sort, int $limit): array {
        $wp_args = array(
            'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => $limit,
            'fields' => 'ids', 'no_found_rows' => true, 's' => $query,
        );
        $meta_query = array();
        if ($in_stock) $meta_query[] = array('key' => '_stock_status', 'value' => 'outofstock', 'compare' => '!=');
        if ($min_price !== null) $meta_query[] = array('key' => '_price', 'value' => $min_price, 'compare' => '>=', 'type' => 'NUMERIC');
        if ($max_price !== null) $meta_query[] = array('key' => '_price', 'value' => $max_price, 'compare' => '<=', 'type' => 'NUMERIC');
        if ($meta_query) $wp_args['meta_query'] = $meta_query;

        // Only constrain by taxonomy when the model supplied an explicit category.
        // Inferring a category from a generic word such as "white" can otherwise
        // incorrectly route paint searches into an unrelated category.
        $category_ids = $category !== '' ? $this->category_ids($category) : array();
        if ($category_ids) {
            $wp_args['tax_query'] = array(array('taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $category_ids));
        }
        if ($sort !== 'relevance') {
            $wp_args['meta_key'] = '_price'; $wp_args['orderby'] = 'meta_value_num';
            $wp_args['order'] = $sort === 'price_desc' ? 'DESC' : 'ASC';
        }
        return (new WP_Query($wp_args))->posts;
    }

    private function category_ids(string $text): array {
        if ($text === '') return array();
        $matched = array();
        $candidates = array_merge(array($text), array_slice($this->keywords($text), 0, 5));
        foreach ($candidates as $candidate) {
            $terms = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => true, 'search' => $candidate, 'number' => 4));
            if (!is_wp_error($terms)) foreach ($terms as $term) $matched[] = (int) $term->term_id;
        }
        return array_values(array_unique($matched));
    }

    private function keywords(string $text): array {
        $stop = array('what','which','with','that','this','from','have','need','show','find','looking','please','would','could','product','products','for','the','and','are','you','your','some','best');
        $tokens = preg_split('/[^a-z0-9-]+/i', strtolower($text)) ?: array();
        return array_values(array_unique(array_filter($tokens, static fn($token) => strlen($token) > 2 && !in_array($token, $stop, true))));
    }

    private function format(WC_Product $product): array {
        $attributes = array();
        foreach (array_slice($product->get_attributes(), 0, 8) as $attribute) {
            if ($attribute->is_taxonomy()) {
                $values = wc_get_product_terms($product->get_id(), $attribute->get_name(), array('fields' => 'names'));
                $label = wc_attribute_label($attribute->get_name());
            } else {
                $values = $attribute->get_options(); $label = $attribute->get_name();
            }
            if (!is_wp_error($values) && $values) $attributes[$label] = array_values(array_map('strval', $values));
        }
        $category_names = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names'));
        if (is_wp_error($category_names)) $category_names = array();

        $item = array(
            'id' => $product->get_id(), 'sku' => $product->get_sku(), 'name' => $product->get_name(),
            'type' => $product->get_type(), 'url' => $product->get_permalink(),
            'image' => wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail') ?: wc_placeholder_img_src('woocommerce_thumbnail'),
            'price_html' => wp_kses_post($product->get_price_html()),
            'price_text' => trim(wp_strip_all_tags($product->get_price_html())),
            'price' => $product->get_price(),
            'in_stock' => $product->is_in_stock(), 'stock_status' => $product->get_stock_status(),
            'short_description' => mb_substr(wp_strip_all_tags($product->get_short_description()), 0, 500),
            'categories' => array_values($category_names), 'attributes' => $attributes,
            'can_add_to_cart' => $product->is_type('simple') && $product->is_purchasable() && $product->is_in_stock(),
        );
        if ($product->is_type('variable')) {
            $item['variation_attributes'] = $product->get_variation_attributes();
            $item['variation_count'] = count($product->get_children());
        }
        return $item;
    }
}
