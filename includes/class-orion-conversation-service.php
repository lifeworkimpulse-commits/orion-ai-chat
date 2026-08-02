<?php
if (!defined('ABSPATH')) { exit; }

final class Orion_Conversation_Service {
    private string $conversations_table;
    private string $messages_table;
    private string $events_table;

    public function __construct() {
        global $wpdb;
        $this->conversations_table = $wpdb->prefix . 'orion_ai_conversations';
        $this->messages_table = $wpdb->prefix . 'orion_ai_messages';
        $this->events_table = $wpdb->prefix . 'orion_ai_events';
    }

    public function resolve(string $session_key, array $settings): array|WP_Error {
        global $wpdb;
        $client_hash = $this->client_hash();
        $day = wp_date('Y-m-d');
        $cutoff = gmdate('Y-m-d H:i:s', time() - ((int) $settings['session_minutes'] * MINUTE_IN_SECONDS));
        $question_limit = (int) $settings['questions_per_session'];

        if ($this->is_valid_session_key($session_key)) {
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->conversations_table} WHERE session_key = %s AND client_hash = %s AND day_key = %s AND last_activity >= %s LIMIT 1",
                $session_key,
                $client_hash,
                $day,
                $cutoff
            ), ARRAY_A);
            if ($existing && (int) $existing['question_count'] < $question_limit) return $existing;
        }

        $current = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->conversations_table} WHERE client_hash = %s AND day_key = %s AND last_activity >= %s AND question_count < %d ORDER BY id DESC LIMIT 1",
            $client_hash,
            $day,
            $cutoff,
            $question_limit
        ), ARRAY_A);
        if ($current) return $current;

        $sessions_today = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->conversations_table} WHERE client_hash = %s AND day_key = %s",
            $client_hash,
            $day
        ));
        if ($sessions_today >= (int) $settings['sessions_per_day']) {
            return new WP_Error('daily_limit', (string) $settings['limit_message'], array('status' => 429));
        }

        $now = current_time('mysql', true);
        $wpdb->insert($this->conversations_table, array(
            'client_hash' => $client_hash,
            'session_key' => wp_generate_uuid4(),
            'day_key' => $day,
            'question_count' => 0,
            'last_activity' => $now,
            'created_at' => $now,
        ));
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->conversations_table} WHERE id = %d", $wpdb->insert_id), ARRAY_A);
    }

    public function history(int $conversation_id): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT role, content FROM {$this->messages_table} WHERE conversation_id = %d ORDER BY id DESC LIMIT 10",
            $conversation_id
        ), ARRAY_A) ?: array();
        return array_reverse($rows);
    }

    public function save_exchange(int $conversation_id, string $question, string $answer, array $usage = array()): void {
        $this->save_message($conversation_id, 'user', $question, array());
        $this->save_message($conversation_id, 'assistant', $answer, $usage);
    }

    public function increment(int $conversation_id, int $question_limit): bool {
        global $wpdb;
        return false !== $wpdb->query($wpdb->prepare(
            "UPDATE {$this->conversations_table} SET question_count = question_count + 1, last_activity = %s WHERE id = %d AND question_count < %d",
            current_time('mysql', true),
            $conversation_id,
            $question_limit
        ));
    }

    public function record_event(string $event_type, array $payload = array(), int $conversation_id = 0): void {
        global $wpdb;
        $wpdb->insert($this->events_table, array(
            'conversation_id' => $conversation_id ?: null,
            'event_type' => sanitize_key($event_type),
            'payload_json' => wp_json_encode($payload),
            'created_at' => current_time('mysql', true),
        ));
    }

    public function analytics(int $days = 30): array {
        global $wpdb;
        $days = max(1, min(365, $days));
        $since = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        $events = $wpdb->get_results($wpdb->prepare(
            "SELECT event_type, COUNT(*) AS total FROM {$this->events_table} WHERE created_at >= %s GROUP BY event_type ORDER BY total DESC",
            $since
        ), ARRAY_A) ?: array();
        $conversations = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->conversations_table} WHERE created_at >= %s",
            $since
        ));
        return array('days' => $days, 'conversations' => $conversations, 'events' => $events);
    }

    private function save_message(int $conversation_id, string $role, string $content, array $usage): void {
        global $wpdb;
        $wpdb->insert($this->messages_table, array(
            'conversation_id' => $conversation_id,
            'role' => $role,
            'content' => $content,
            'usage_json' => $usage ? wp_json_encode($usage) : null,
            'created_at' => current_time('mysql', true),
        ));
    }

    private function client_hash(): string {
        $user_id = get_current_user_id();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300);
        $identity = $user_id ? 'user:' . $user_id : 'guest:' . $ip . '|' . $user_agent;
        return hash_hmac('sha256', $identity, wp_salt('auth'));
    }

    private function is_valid_session_key(string $value): bool {
        return (bool) preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $value);
    }
}
