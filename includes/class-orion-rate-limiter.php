<?php
if (!defined('ABSPATH')) { exit; }

final class Orion_Rate_Limiter {
    public function check(array $settings, string $bucket = 'chat') {
        $limit = (int) ($settings['requests_per_minute'] ?? 12);
        if ($bucket === 'cart') $limit = max(3, (int) ceil($limit / 2));

        $fingerprint = hash_hmac(
            'sha256',
            $bucket . '|' . ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300),
            wp_salt('nonce')
        );
        $key = 'orion_ai_rate_' . substr($fingerprint, 0, 40);
        $state = get_transient($key);
        $now = time();

        if (!is_array($state) || empty($state['reset']) || (int) $state['reset'] <= $now) {
            $state = array('count' => 0, 'reset' => $now + MINUTE_IN_SECONDS);
        }
        if ((int) $state['count'] >= $limit) {
            return new WP_Error(
                'rate_limited',
                'Please wait a moment before sending another message.',
                array('status' => 429, 'retry_after' => max(1, (int) $state['reset'] - $now))
            );
        }

        $state['count']++;
        set_transient($key, $state, max(1, (int) $state['reset'] - $now));
        return true;
    }
}
