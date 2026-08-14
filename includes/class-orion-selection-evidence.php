<?php
if (!defined('ABSPATH')) { exit; }

final class Orion_Selection_Evidence {
    private const FIELDS = array('name', 'categories', 'attributes', 'description', 'facts', 'retrieval', 'stock');
    private const CONFIDENCE = array('high', 'medium', 'low');

    public static function fields(): array { return self::FIELDS; }

    public static function normalise(array $choice): array {
        $fields = is_array($choice['evidence_fields'] ?? null) ? $choice['evidence_fields'] : array();
        $fields = array_values(array_unique(array_filter(array_map(static function ($field): string {
            $field = strtolower(trim((string) $field));
            return in_array($field, self::FIELDS, true) ? $field : '';
        }, $fields))));
        $confidence = strtolower(trim((string) ($choice['confidence'] ?? 'low'));
        if (!in_array($confidence, self::CONFIDENCE, true)) { $confidence = 'low'; }
        return array(
            'evidence_fields' => $fields,
            'confidence' => $confidence,
            'reason' => self::text($choice['reason'] ?? ''),
            'uncertainty' => self::text($choice['uncertainty'] ?? ''),
        );
    }

    public static function sufficient(array $evidence): bool {
        return !empty($evidence['evidence_fields']) && in_array($evidence['confidence'] ?? '', array('high', 'medium'), true);
    }

    private static function text(mixed $value): string {
        $text = trim(strip_tags((string) $value));
        $text = (string) preg_replace('/\s+/', ' ', $text);
        return substr($text, 0, 500);
    }
}
