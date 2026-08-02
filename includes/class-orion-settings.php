<?php
if (!defined('ABSPATH')) { exit; }

final class Orion_AI_Settings {
    public const OPTION = 'orion_ai_settings';

    public static function defaults(): array {
        return array(
            'enabled' => '1',
            'api_key' => '',
            'model' => 'openrouter/free',
            'primary_color' => '#2783DE',
            'position' => 'right',
            'title' => 'Orion AI Assistant',
            'greeting' => "Hi! Tell me what you're working on, and I'll help you find the right products.",
            'limit_message' => "You've reached today's chat limit. Please try again tomorrow or contact our team.",
            'questions_per_session' => 5,
            'sessions_per_day' => 2,
            'session_minutes' => 30,
            'requests_per_minute' => 12,
            'max_products' => 6,
            'retention_days' => 0,
            'system_prompt' => 'You are Orion Supplies AI shopping assistant. Reply in clear British English. Use only supplied store knowledge and live WooCommerce product results. Ask concise clarification questions when needed. Never invent prices, stock, discounts, compatibility, delivery terms, return rules, quantities or product links. For safety-critical building work, recommend checking manufacturer instructions or consulting a qualified professional. Keep answers concise.',
        );
    }

    public static function get(): array {
        return wp_parse_args(get_option(self::OPTION, array()), self::defaults());
    }

    public static function sanitize($input): array {
        $input = is_array($input) ? $input : array();
        $old = self::get();
        $out = self::defaults();

        $out['enabled'] = empty($input['enabled']) ? '0' : '1';
        $out['api_key'] = !empty($input['api_key'])
            ? sanitize_text_field(wp_unslash($input['api_key']))
            : (string) ($old['api_key'] ?? '');

        foreach (array('model', 'title', 'greeting', 'limit_message', 'system_prompt') as $key) {
            $out[$key] = sanitize_textarea_field(wp_unslash($input[$key] ?? $out[$key]));
        }

        $out['primary_color'] = sanitize_hex_color($input['primary_color'] ?? '') ?: '#2783DE';
        $out['position'] = in_array($input['position'] ?? 'right', array('left', 'right'), true)
            ? $input['position']
            : 'right';
        $out['questions_per_session'] = max(1, min(20, absint($input['questions_per_session'] ?? 5)));
        $out['sessions_per_day'] = max(1, min(10, absint($input['sessions_per_day'] ?? 2)));
        $out['session_minutes'] = max(5, min(240, absint($input['session_minutes'] ?? 30)));
        $out['requests_per_minute'] = max(3, min(120, absint($input['requests_per_minute'] ?? 12)));
        $out['max_products'] = max(1, min(8, absint($input['max_products'] ?? 6)));
        $out['retention_days'] = max(0, min(3650, absint($input['retention_days'] ?? 0)));

        return $out;
    }

    public static function api_key(array $settings): string {
        $configured = defined('ORION_AI_OPENROUTER_KEY') ? trim((string) ORION_AI_OPENROUTER_KEY) : '';
        return $configured !== '' ? $configured : trim((string) ($settings['api_key'] ?? ''));
    }
}
