<?php
if (!defined('ABSPATH')) { exit; }

final class Orion_Routing_Rules {
    public static function delivery_policy_signals(string $message): ?array {
        $text = mb_strtolower(trim($message));
        $postcode = (bool) preg_match('/\b(?:gir\s*0aa|[a-z]{1,2}\d[a-z\d]?(?:\s*\d[a-z]{2})?)\b/i', $text);
        $action = (bool) preg_match('/\b(deliver|delivered|delivering|ship|shipped|send|sent|dispatch|dispatched|courier|collect|collected|pick[\s-]?up)\b/i', $text);
        $noun = (bool) preg_match('/\b(delivery|shipping|collection)\b/i', $text);
        $destination = (bool) preg_match('/\b(?:to|into|within)\s+(?:the\s+)?[a-z0-9][a-z0-9 .-]{0,50}(?:\?|$)/i', $text);
        $implicit = (bool) preg_match('/\b(?:can|could|will|would|do)\s+you\s+(?:get|bring|take)\b.{0,80}\bto\b/i', $text);
        $policy_form = (bool) preg_match('/\b(?:do|can|could|will|would)\s+(?:you|this|the|my|an?\s+order)\b/i', $text);
        $service = (bool) preg_match('/\b(?:delivery|shipping|collection)\s+(?:area|areas|available|availability|option|options|policy|cost|charge|charges|postcode|service)\b/i', $text);
        $matched = ($action && ($postcode || $destination || $policy_form)) || ($noun && ($postcode || $destination)) || $service || ($implicit && ($postcode || $destination));
        if (!$matched) { return null; }
        return compact('postcode', 'action', 'noun', 'destination', 'implicit', 'policy_form', 'service');
    }

    public static function extract_dimensions(string $text): array {
        $map = array('one'=>1,'two'=>2,'three'=>3,'four'=>4,'five'=>5,'six'=>6,'seven'=>7,'eight'=>8,'nine'=>9,'ten'=>10,'eleven'=>11,'twelve'=>12);
        $normal = strtolower($text);
        foreach ($map as $word => $number) { $normal = preg_replace('/\b' . preg_quote($word, '/') . '\b/', (string) $number, $normal); }
        if (!preg_match('/\b(\d+(?:\.\d+)?)\s*(?:x|by|×)\s*(\d+(?:\.\d+)?)/i', $normal, $matches)) { return array(); }
        $out = array('dimension_length'=>(float)$matches[1], 'dimension_width'=>(float)$matches[2], 'dimensions'=>$matches[1] . 'x' . $matches[2]);
        if (preg_match('/\b(m|metres?|meters?)\b/i', $normal)) { $out['dimension_unit'] = 'm'; }
        elseif (preg_match('/\b(ft|feet|foot)\b/i', $normal)) { $out['dimension_unit'] = 'ft'; }
        return $out;
    }

    public static function is_floor_project(array $state): bool {
        $text = strtolower((string)($state['project_type'] ?? '') . ' ' . (string)($state['surface'] ?? '') . ' ' . (string)($state['environment'] ?? '') . ' ' . (string)($state['notes'] ?? ''));
        return (bool) preg_match('/\b(floor|garage)\b/', $text);
    }
    public static function wants_complete_kit(array $state): bool {
        $text = strtolower((string)($state['notes'] ?? '') . ' ' . (string)($state['project_type'] ?? ''));
        return (bool) preg_match('/\b(everything needed|complete (?:kit|materials|product kit)|all materials|all supplies|including[^.]{0,80}supplies)\b/', $text);
    }
    public static function has_surface_condition(array $state): bool {
        $text = strtolower(implode(' ', array_map('strval', array_filter($state, 'is_scalar'))));
        return (bool) preg_match('/\b(new plaster|bare|porous|stain|stained|damage|damaged|crack|cracked|hole|holes|uneven|loose|flaking|dirty|grease|mould|mold)\b/', $text);
    }
}
