<?php
if (!defined('ABSPATH')) { exit; }

final class Orion_Ceiling_Calculator {
    /**
     * Calculates a transparent estimate. It intentionally does not pick products:
     * product availability and pack sizes are resolved separately by WooCommerce.
     */
    public function calculate(array $args): array|WP_Error {
        $area = isset($args['area_m2']) && is_numeric($args['area_m2']) ? (float) $args['area_m2'] : 0.0;
        if ($area < 1 || $area > 10000) {
            return new WP_Error('invalid_area', 'Provide a ceiling area between 1 and 10,000 m².', array('status' => 400));
        }

        $finish = sanitize_key($args['finish'] ?? 'paint');
        $waste = isset($args['waste_percent']) && is_numeric($args['waste_percent'])
            ? max(5, min(25, (float) $args['waste_percent']))
            : 10.0;
        $multiplier = 1 + ($waste / 100);

        if ($finish === 'panels') {
            $panel_width = isset($args['panel_width_m']) && is_numeric($args['panel_width_m']) ? (float) $args['panel_width_m'] : 0.0;
            $panel_length = isset($args['panel_length_m']) && is_numeric($args['panel_length_m']) ? (float) $args['panel_length_m'] : 0.0;
            $pack_units = isset($args['pack_units']) && is_numeric($args['pack_units']) ? (int) $args['pack_units'] : 1;
            if ($panel_width <= 0 || $panel_length <= 0 || $pack_units < 1) {
                return new WP_Error('missing_panel_dimensions', 'Panel width, length and pack quantity are required for a panel estimate.', array('status' => 400));
            }
            $panel_area = $panel_width * $panel_length;
            $panels = (int) ceil(($area * $multiplier) / $panel_area);
            return array(
                'type' => 'ceiling_panels',
                'area_m2' => $area,
                'waste_percent' => $waste,
                'items' => array(
                    array('label' => 'Ceiling panels', 'amount' => $panels, 'unit' => 'panels'),
                    array('label' => 'Panel packs', 'amount' => (int) ceil($panels / $pack_units), 'unit' => 'packs'),
                    array('label' => 'Fixings', 'amount' => 'Select to the manufacturer specification', 'unit' => ''),
                ),
                'assumptions' => array('A ' . $waste . '% cutting allowance is included.', 'Check panel and fixing compatibility with the manufacturer.'),
            );
        }

        $coverage = isset($args['coverage_m2_per_litre']) && is_numeric($args['coverage_m2_per_litre'])
            ? max(3, min(25, (float) $args['coverage_m2_per_litre']))
            : 10.0;
        $primer_coverage = isset($args['primer_coverage_m2_per_litre']) && is_numeric($args['primer_coverage_m2_per_litre'])
            ? max(3, min(25, (float) $args['primer_coverage_m2_per_litre']))
            : 8.0;
        $coats = isset($args['coats']) && is_numeric($args['coats']) ? max(1, min(4, (int) $args['coats'])) : 2;
        $paint = ceil((($area * $coats * $multiplier) / $coverage) * 100) / 100;
        $primer = ceil((($area * $multiplier) / $primer_coverage) * 100) / 100;

        return array(
            'type' => 'painted_ceiling',
            'area_m2' => $area,
            'waste_percent' => $waste,
            'items' => array(
                array('label' => 'Ceiling paint', 'amount' => $paint, 'unit' => 'litres'),
                array('label' => 'Primer', 'amount' => $primer, 'unit' => 'litres'),
                array('label' => 'Roller sleeves', 'amount' => $area > 50 ? 2 : 1, 'unit' => 'minimum'),
                array('label' => 'Brush, tray and masking materials', 'amount' => 1, 'unit' => 'set'),
            ),
            'assumptions' => array(
                $coats . ' coat(s) at ' . $coverage . ' m²/L coverage.',
                'A ' . $waste . '% allowance is included.',
                'Confirm coverage and preparation requirements on the selected product label.',
            ),
        );
    }
}
