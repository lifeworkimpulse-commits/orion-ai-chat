<?php
if (!defined('ABSPATH')) { exit; }

final class Orion_Kit_Validator {
    private Orion_Product_Facts $facts;
    public function __construct(?Orion_Product_Facts $facts = null) { $this->facts = $facts ?? new Orion_Product_Facts(); }

    public function validate(array $products, array $state): array {
        $rejections = array(); $roller_indexes = array();
        foreach ($products as $index => $product) { if (in_array('roller', (array)($product['logical_roles'] ?? array()), true)) { $roller_indexes[] = $index; } }
        $valid_rollers = $this->valid_roller_indexes($products, $roller_indexes);
        foreach ($roller_indexes as $index) {
            if (!in_array($index, $valid_rollers, true)) {
                $rejections[] = array('role'=>'roller','product_id'=>(int)($products[$index]['id'] ?? 0),'reason'=>'A complete roller requires an explicit frame-and-sleeve set or a width-compatible pair.');
                $this->strip_role($products[$index], 'roller');
            } else { $products[$index]['roller_system_complete'] = true; }
        }
        $products = array_values(array_filter($products, static fn($product)=>!empty($product['logical_roles'])));
        $roller_width = 0.0;
        foreach ($products as $product) { if (in_array('roller', (array)($product['logical_roles'] ?? array()), true)) { $roller_width = max($roller_width, (float)($this->facts->roller_component($product)['width'] ?? 0)); } }
        foreach ($products as $index => $product) {
            if (!in_array('tray', (array)($product['logical_roles'] ?? array()), true) || $roller_width <= 0) { continue; }
            $tray_width = $this->facts->tray_width($product);
            if ($tray_width > 0 && $tray_width + 0.01 < $roller_width) {
                $rejections[] = array('role'=>'tray','product_id'=>(int)($product['id'] ?? 0),'reason'=>'Tray width is smaller than the selected roller width.');
                $this->strip_role($products[$index], 'tray');
            }
        }
        $products = array_values(array_filter($products, static fn($product)=>!empty($product['logical_roles'])));
        return array($products, $rejections);
    }
    private function valid_roller_indexes(array $products, array $indexes): array {
        $valid = array(); $frames = array(); $sleeves = array();
        foreach ($indexes as $index) {
            $component = $this->facts->roller_component($products[$index]);
            if ('complete' === $component['kind']) { $valid[] = $index; }
            elseif ('frame' === $component['kind']) { $frames[$index] = (float)$component['width']; }
            elseif ('sleeve' === $component['kind']) { $sleeves[$index] = (float)$component['width']; }
        }
        foreach ($frames as $frame_index => $frame_width) { foreach ($sleeves as $sleeve_index => $sleeve_width) {
            if ($frame_width > 0 && $sleeve_width > 0 && abs($frame_width - $sleeve_width) < 0.1) { $valid[] = $frame_index; $valid[] = $sleeve_index; break; }
        } }
        return array_values(array_unique($valid));
    }
    private function strip_role(array &$product, string $role): void {
        $product['logical_roles'] = array_values(array_filter((array)($product['logical_roles'] ?? array()), static fn($value)=>$value !== $role));
        $display_roles = 'roller' === $role ? array('roller','roller_frame','roller_sleeve') : array($role);
        $product['kit_roles'] = array_values(array_filter((array)($product['kit_roles'] ?? array()), static fn($value)=>!in_array($value, $display_roles, true)));
        $product['kit_role'] = $product['kit_roles'][0] ?? '';
    }
}
