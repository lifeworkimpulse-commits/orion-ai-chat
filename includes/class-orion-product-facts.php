<?php
if (!defined('ABSPATH')) { exit; }

final class Orion_Product_Facts {
    public function extract(array $product): array {
        $text = $this->text($product);
        $identity = strtolower(html_entity_decode((string)($product['name'] ?? '') . ' ' . implode(' ', (array)($product['categories'] ?? array())), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $functions = array();
        $patterns = array(
            'primary_coating'=>'/\b(paint|coating|epoxy|varnish|stain)\b/', 'primer'=>'/\b(primer|undercoat)\b/',
            'cleaner'=>'/\b(cleaner|degreaser|sugar soap)\b/', 'filler'=>'/\b(filler|repair compound)\b/',
            'brush'=>'/\b(paint brush|decorating brush|masonry brush|cutting.?in brush)\b/',
            'tray'=>'/\b(paint tray|roller tray|scuttle|painting pack|roller set)\b/',
            'masking_tape'=>'/\b(masking tape|painter[’\x27]?s tape|decorators? tape)\b/',
            'dust_sheet'=>'/\b(dust sheet|protective sheet|polythene sheet)\b/',
        );
        foreach ($patterns as $function => $pattern) { if (preg_match($pattern, $identity)) { $functions[] = $function; } }
        $roller = $this->roller_component($product);
        if ('unknown' !== $roller['kind']) { $functions[] = 'roller'; }
        $surfaces = array();
        foreach (array('floor','garage','wall','ceiling','masonry','concrete','wood','timber','metal','plaster','brick','roof','tile','bathroom','kitchen','exterior','external','outdoor','interior','internal') as $surface) {
            if (preg_match('/\b' . preg_quote($surface, '/') . '\b/', $text)) { $surfaces[] = $surface; }
        }
        $coverage = null;
        if (preg_match('/\b(\d+(?:\.\d+)?)\s*(?:m2|m²|sq\.?\s*m(?:etres?)?)\s*(?:per\s*(?:litre|liter|l)|\/\s*l)?\b/i', $text, $matches)) { $coverage = (float)$matches[1]; }
        return array(
            'functions'=>array_values(array_unique($functions)), 'surfaces'=>array_values(array_unique($surfaces)),
            'roller_component'=>$roller['kind'], 'roller_width_inches'=>$roller['width'], 'tray_width_inches'=>$this->tray_width($product),
            'included_components'=>$this->included_components($product), 'coverage_m2_per_litre'=>$coverage, 'is_bundle'=>$this->is_bundle($product),
        );
    }

    public function roller_component(array $product): array {
        $name = strtolower(html_entity_decode((string)($product['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = $this->text($product);
        $name_frame = (bool) preg_match('/\bframes?\b/', $name);
        $name_sleeve = (bool) preg_match('/\bsleeves?\b|\broller covers?\b|\broller refills?\b/', $name);
        $name_set = (bool) preg_match('/\b(set|kit|pack|\d+\s*pc)\b/', $name);
        $name_roller = (bool) preg_match('/\brollers?\b/', $name);
        if ($name_frame && !$name_set && !$name_sleeve) { $kind = 'frame'; }
        elseif ($name_sleeve && !$name_set && !$name_frame) { $kind = 'sleeve'; }
        else {
            $explicit = (bool) preg_match('/\b(includes?|contains?|comes with|supplied with|comprises?)\b[^.]{0,180}(?:\bframes?\b[^.]{0,120}\bsleeves?\b|\bsleeves?\b[^.]{0,120}\bframes?\b)/', $text);
            if (($name_frame && $name_sleeve) || ($name_set && $name_frame && $name_roller) || $explicit) { $kind = 'complete'; }
            else {
                $frame = (bool) preg_match('/\b(?:roller\s+)?(?:cage\s+)?frames?\b/', $text);
                $sleeve = (bool) preg_match('/\b(?:roller\s+)?sleeves?\b|\broller\s+covers?\b|\broller\s+refills?\b/', $text);
                $kind = $name_set && $frame && $sleeve ? 'complete' : ($frame ? 'frame' : ($sleeve ? 'sleeve' : 'unknown'));
            }
        }
        return array('kind'=>$kind, 'width'=>$this->width_inches($name . ' ' . $text));
    }
    public function tray_width(array $product): float { return $this->width_inches($this->text($product)); }
    public function text(array $product): string {
        return strtolower(html_entity_decode((string)($product['name'] ?? '') . ' ' . (string)($product['description'] ?? '') . ' ' . (string)($product['short_description'] ?? '') . ' ' . implode(' ', (array)($product['categories'] ?? array())) . ' ' . json_encode($product['attributes'] ?? array()), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    public function is_bundle(array $product): bool { return (bool) preg_match('/\b(set|kit|bundle|complete set|painting pack)\b/', $this->text($product)); }
    private function included_components(array $product): array {
        $text = $this->text($product);
        if (!preg_match('/\b(includes?|contains?|comes with|supplied with|comprises?)\b(.{0,240})/', $text, $matches)) { return array(); }
        $out = array();
        foreach (array('frame','sleeve','roller','tray','brush','masking tape','dust sheet') as $component) {
            if (preg_match('/\b' . preg_quote($component, '/') . 's?\b/', $matches[2])) { $out[] = str_replace(' ', '_', $component); }
        }
        return array_values(array_unique($out));
    }
    private function width_inches(string $text): float {
        if (preg_match('/\b(\d{1,2}(?:\.\d+)?)\s*(?:"|″|inch(?:es)?\b)/i', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $matches)) { return (float)$matches[1]; }
        return 0.0;
    }
}
