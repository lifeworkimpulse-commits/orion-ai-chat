<?php
if (!defined('ABSPATH')) { exit; }

final class Orion_Tool_Executor {
    private Orion_Product_Search $products;
    private Orion_Knowledge_Base $knowledge;
    private Orion_Ceiling_Calculator $calculator;

    public function __construct(Orion_Product_Search $products, Orion_Knowledge_Base $knowledge, Orion_Ceiling_Calculator $calculator) {
        $this->products = $products;
        $this->knowledge = $knowledge;
        $this->calculator = $calculator;
    }

    public function definitions(int $max_products): array {
        return array(
            array('type' => 'function', 'function' => array(
                'name' => 'search_products',
                'description' => 'Search the live WooCommerce catalogue. Use it before naming, pricing, linking or recommending products.',
                'parameters' => array('type' => 'object', 'properties' => array(
                    'query' => array('type' => 'string', 'description' => 'Concise product, material or use-case terms.'),
                    'category' => array('type' => 'string', 'description' => 'WooCommerce category wording when known.'),
                    'min_price' => array('type' => 'number'),
                    'max_price' => array('type' => 'number'),
                    'in_stock' => array('type' => 'boolean'),
                    'sort' => array('type' => 'string', 'enum' => array('relevance', 'price_asc', 'price_desc')),
                    'limit' => array('type' => 'integer', 'minimum' => 1, 'maximum' => $max_products),
                ), 'required' => array('query')),
            )),
            array('type' => 'function', 'function' => array(
                'name' => 'get_product_details',
                'description' => 'Get live details for one visible WooCommerce product by product ID or SKU.',
                'parameters' => array('type' => 'object', 'properties' => array(
                    'product_id' => array('type' => 'integer', 'minimum' => 1),
                    'sku' => array('type' => 'string'),
                ))),
            ),
            array('type' => 'function', 'function' => array(
                'name' => 'get_store_policy',
                'description' => 'Find store policy or service information in the approved knowledge base.',
                'parameters' => array('type' => 'object', 'properties' => array(
                    'query' => array('type' => 'string'),
                ), 'required' => array('query')),
            )),
            array('type' => 'function', 'function' => array(
                'name' => 'calculate_ceiling_materials',
                'description' => 'Calculate an indicative ceiling-material estimate. Use only after the customer gives an area and finish type.',
                'parameters' => array('type' => 'object', 'properties' => array(
                    'area_m2' => array('type' => 'number', 'minimum' => 1),
                    'finish' => array('type' => 'string', 'enum' => array('paint', 'panels')),
                    'coats' => array('type' => 'integer', 'minimum' => 1, 'maximum' => 4),
                    'coverage_m2_per_litre' => array('type' => 'number'),
                    'primer_coverage_m2_per_litre' => array('type' => 'number'),
                    'waste_percent' => array('type' => 'number'),
                    'panel_width_m' => array('type' => 'number'),
                    'panel_length_m' => array('type' => 'number'),
                    'pack_units' => array('type' => 'integer'),
                ), 'required' => array('area_m2', 'finish')),
            )),
            array('type' => 'function', 'function' => array(
                'name' => 'build_project_checklist',
                'description' => 'Return the material categories that a customer should consider for a project; it does not select products.',
                'parameters' => array('type' => 'object', 'properties' => array(
                    'project_type' => array('type' => 'string', 'enum' => array('ceiling_paint', 'ceiling_panels', 'walls', 'general')),
                ), 'required' => array('project_type')),
            )),
        );
    }

    public function execute(string $name, array $arguments, int $max_products): array {
        switch ($name) {
            case 'search_products':
                $arguments['query'] = sanitize_text_field((string) ($arguments['query'] ?? ''));
                $arguments['limit'] = max(1, min($max_products, absint($arguments['limit'] ?? $max_products)));
                $arguments['in_stock'] = !isset($arguments['in_stock']) || filter_var($arguments['in_stock'], FILTER_VALIDATE_BOOLEAN);
                $products = $this->products->search($arguments);
                return array('data' => array('products' => $products), 'products' => $products, 'sources' => array());

            case 'get_product_details':
                $product_id = absint($arguments['product_id'] ?? 0);
                if (!$product_id && !empty($arguments['sku']) && function_exists('wc_get_product_id_by_sku')) {
                    $product_id = (int) wc_get_product_id_by_sku(sanitize_text_field((string) $arguments['sku']));
                }
                $product = $this->products->get_product($product_id);
                return $product
                    ? array('data' => array('product' => $product), 'products' => array($product), 'sources' => array())
                    : array('data' => array('error' => 'No visible product was found.'), 'products' => array(), 'sources' => array());

            case 'get_store_policy':
                $documents = $this->knowledge->relevant(sanitize_text_field((string) ($arguments['query'] ?? '')));
                return array('data' => array('documents' => $documents), 'products' => array(), 'sources' => $documents);

            case 'calculate_ceiling_materials':
                $estimate = $this->calculator->calculate($arguments);
                if (is_wp_error($estimate)) {
                    return array('data' => array('error' => $estimate->get_error_message()), 'products' => array(), 'sources' => array());
                }
                return array('data' => array('estimate' => $estimate), 'products' => array(), 'sources' => array(), 'estimate' => $estimate);

            case 'build_project_checklist':
                $project_type = sanitize_key($arguments['project_type'] ?? 'general');
                return array('data' => array('checklist' => $this->checklist($project_type)), 'products' => array(), 'sources' => array());

            default:
                return array('data' => array('error' => 'This tool is not available.'), 'products' => array(), 'sources' => array());
        }
    }

    private function checklist(string $project_type): array {
        $items = array(
            'ceiling_paint' => array('surface preparation', 'primer', 'ceiling paint', 'roller and brush', 'masking and protection'),
            'ceiling_panels' => array('panel measurements', 'panels', 'compatible fixings', 'trims or profiles', 'cutting tools and protection'),
            'walls' => array('surface preparation', 'filler or primer', 'finish material', 'application tools', 'masking and protection'),
            'general' => array('surface preparation', 'main material', 'compatible accessories', 'application tools', 'safety and protection'),
        );
        return $items[$project_type] ?? $items['general'];
    }
}
