<?php
if (!defined('ABSPATH')) { exit; }

final class Orion_Manager_Handoff {
    private string $table;
    private string $events_table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'orion_ai_handoffs';
        $this->events_table = $wpdb->prefix . 'orion_ai_handoff_events';
    }

    public function create(int $conversation_id, string $question, array $context): int {
        global $wpdb;

        $failure_stage = sanitize_key((string)($context['failure_stage'] ?? 'manager_follow_up'));
        $trace_id = absint($context['trace_id'] ?? 0);
        $now = current_time('mysql', true);
        $inserted = $wpdb->insert($this->table, array(
            'conversation_id' => $conversation_id,
            'trace_id' => $trace_id ?: null,
            'question' => sanitize_textarea_field($question),
            'context_json' => wp_json_encode($context),
            'reason_code' => $failure_stage ?: 'manager_follow_up',
            'priority' => Orion_Manager_Queue_Policy::PRIORITY_NORMAL,
            'assigned_user_id' => null,
            'resolution_note' => '',
            'manager_response' => '',
            'response_status' => Orion_Manager_Queue_Policy::RESPONSE_NONE,
            'response_approved_by' => null,
            'response_approved_at' => null,
            'response_delivered_at' => null,
            'status' => Orion_Manager_Queue_Policy::STATUS_NEW,
            'resolved_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ));

        if (false === $inserted) return 0;
        $id = (int)$wpdb->insert_id;
        $this->record_queue_event($id, 0, 'created', '', Orion_Manager_Queue_Policy::STATUS_NEW, array(
            'reason_code' => $failure_stage ?: 'manager_follow_up',
            'trace_id' => $trace_id,
        ));
        return $id;
    }

    public function get(int $id): ?array {
        global $wpdb;
        if ($id < 1) return null;
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id), ARRAY_A);
        return is_array($item) ? $item : null;
    }

    public function all(int $limit = 100, array $filters = array()): array {
        global $wpdb;
        $limit = max(1, min(500, $limit));
        $where = array('1=1');
        $params = array();

        $status = sanitize_key((string)($filters['status'] ?? ''));
        if (Orion_Manager_Queue_Policy::is_status($status)) {
            $where[] = 'status = %s';
            $params[] = $status;
        }

        $priority = sanitize_key((string)($filters['priority'] ?? ''));
        if (in_array($priority, Orion_Manager_Queue_Policy::priorities(), true)) {
            $where[] = 'priority = %s';
            $params[] = $priority;
        }

        $assigned_user_id = absint($filters['assigned_user_id'] ?? 0);
        if ($assigned_user_id > 0) {
            $where[] = 'assigned_user_id = %d';
            $params[] = $assigned_user_id;
        }

        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where)
            . " ORDER BY CASE status WHEN 'new' THEN 0 WHEN 'in_progress' THEN 1 WHEN 'resolved' THEN 2 ELSE 3 END,"
            . " CASE priority WHEN 'urgent' THEN 0 ELSE 1 END, id DESC LIMIT %d";
        $params[] = $limit;
        $prepared = $wpdb->prepare($sql, $params);

        return $wpdb->get_results($prepared, ARRAY_A) ?: array();
    }

    public function counts(): array {
        global $wpdb;
        $counts = array_fill_keys(Orion_Manager_Queue_Policy::statuses(), 0);
        $rows = $wpdb->get_results("SELECT status, COUNT(*) AS total FROM {$this->table} GROUP BY status", ARRAY_A) ?: array();
        foreach ($rows as $row) {
            $status = (string)($row['status'] ?? '');
            if (array_key_exists($status, $counts)) $counts[$status] = (int)($row['total'] ?? 0);
        }
        return $counts;
    }

    public function transition(int $id, string $status, string $note = '', int $user_id = 0, string $priority = ''): bool {
        global $wpdb;
        $item = $this->get($id);
        if (!$item) return false;

        $current = (string)$item['status'];
        $status = sanitize_key($status);
        if (!Orion_Manager_Queue_Policy::can_transition($current, $status)) return false;

        $now = current_time('mysql', true);
        $priority = $priority !== ''
            ? Orion_Manager_Queue_Policy::normalize_priority(sanitize_key($priority))
            : Orion_Manager_Queue_Policy::normalize_priority((string)($item['priority'] ?? ''));

        $data = array(
            'status' => $status,
            'priority' => $priority,
            'resolution_note' => sanitize_textarea_field($note),
            'updated_at' => $now,
            'resolved_at' => Orion_Manager_Queue_Policy::is_terminal($status) ? $now : null,
        );

        if ($status === Orion_Manager_Queue_Policy::STATUS_NEW) {
            $data['assigned_user_id'] = null;
        } elseif ($user_id > 0) {
            $data['assigned_user_id'] = $user_id;
        }

        $updated = $wpdb->update($this->table, $data, array('id' => $id));
        if (false === $updated) return false;

        $event = $current === $status ? 'details_updated' : 'status_changed';
        $this->record_queue_event($id, $user_id, $event, $current, $status, array('priority' => $priority));
        return true;
    }

    public function save_response(int $id, string $response, int $user_id, bool $approve = false): bool {
        global $wpdb;
        $item = $this->get($id);
        if (!$item || (string)$item['status'] === Orion_Manager_Queue_Policy::STATUS_DISMISSED) return false;

        $response = trim(sanitize_textarea_field($response));
        $response = mb_substr($response, 0, 4000);
        if ($response === '') return false;

        $now = current_time('mysql', true);
        $response_status = $approve ? Orion_Manager_Queue_Policy::RESPONSE_APPROVED : Orion_Manager_Queue_Policy::RESPONSE_DRAFT;
        $data = array(
            'manager_response' => $response,
            'response_status' => $response_status,
            'response_approved_by' => $approve ? $user_id : null,
            'response_approved_at' => $approve ? $now : null,
            'response_delivered_at' => null,
            'updated_at' => $now,
        );
        if ($user_id > 0 && empty($item['assigned_user_id'])) $data['assigned_user_id'] = $user_id;

        $updated = $wpdb->update($this->table, $data, array('id' => $id));
        if (false === $updated) return false;

        $this->record_queue_event(
            $id,
            $user_id,
            $approve ? 'response_approved' : 'response_draft_saved',
            (string)$item['status'],
            (string)$item['status'],
            array('response_status' => $response_status)
        );
        return true;
    }

    public function pending_response(int $conversation_id): ?array {
        global $wpdb;
        if ($conversation_id < 1) return null;
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE conversation_id = %d AND response_status = %s AND manager_response <> '' ORDER BY response_approved_at ASC, id ASC LIMIT 1",
            $conversation_id,
            Orion_Manager_Queue_Policy::RESPONSE_APPROVED
        ), ARRAY_A);
        return is_array($item) ? $item : null;
    }

    public function mark_response_delivered(int $id): bool {
        global $wpdb;
        $now = current_time('mysql', true);
        $changed = $wpdb->update($this->table, array(
            'response_status' => Orion_Manager_Queue_Policy::RESPONSE_DELIVERED,
            'response_delivered_at' => $now,
            'updated_at' => $now,
        ), array(
            'id' => $id,
            'response_status' => Orion_Manager_Queue_Policy::RESPONSE_APPROVED,
        ));
        if (1 !== $changed) return false;
        $this->record_queue_event($id, 0, 'response_delivered', '', '', array());
        return true;
    }

    public function history(int $id, int $limit = 30): array {
        global $wpdb;
        if ($id < 1) return array();
        $limit = max(1, min(100, $limit));
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->events_table} WHERE handoff_id = %d ORDER BY id DESC LIMIT %d",
            $id,
            $limit
        ), ARRAY_A) ?: array();
    }

    public function resolve(int $id): bool {
        return $this->transition(
            $id,
            Orion_Manager_Queue_Policy::STATUS_RESOLVED,
            '',
            get_current_user_id()
        );
    }

    private function record_queue_event(int $handoff_id, int $user_id, string $event, string $from, string $to, array $payload): void {
        global $wpdb;
        $wpdb->insert($this->events_table, array(
            'handoff_id' => $handoff_id,
            'user_id' => $user_id ?: null,
            'event_type' => sanitize_key($event),
            'from_status' => sanitize_key($from),
            'to_status' => sanitize_key($to),
            'payload_json' => $payload ? wp_json_encode($payload) : null,
            'created_at' => current_time('mysql', true),
        ));
    }
}
