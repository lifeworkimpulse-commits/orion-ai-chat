<?php
if (!defined('ABSPATH')) { exit; }

final class Orion_Selection_Policy {
    public static function apply(array $messages): array {
        $policy = self::instructions();
        foreach ($messages as $index => $message) {
            if (!is_array($message) || 'system' !== ($message['role'] ?? '')) { continue; }
            $messages[$index]['content'] = rtrim((string) ($message['content'] ?? '')) . "\n\n" . $policy;
            return $messages;
        }
        array_unshift($messages, array('role'=>'system','content'=>$policy));
        return $messages;
    }

    public static function instructions(): string {
        return 'Generic validation policy: separate evidence that a product performs the requested function from evidence for secondary compatibility details. When a live title, category, attributes or description directly identifies the requested product type or function, you MUST select that product even if the need says compatible, explicitly compatible or must match and an exact size, connector, fit, capacity or cross-product compatibility detail remains unverified. Use medium confidence, record the exact unresolved detail in uncertainty, and do not claim compatibility. Add a need to missing_needs only when the requested product function itself is unsupported, evidence is too weak to identify the product type, or selection would be unsafe. Never convert unresolved compatibility into a positive compatibility claim.';
    }

    public static function needs_review(array $result): bool {
        $data = self::tool_data($result);
        return is_array($data) && !empty($data['missing_needs']);
    }

    public static function review_messages(array $messages, array $result): array {
        $data = self::tool_data($result);
        $missing = is_array($data['missing_needs'] ?? null) ? array_values(array_filter(array_map('strval',$data['missing_needs']))) : array();
        $messages[] = array(
            'role'=>'system',
            'content'=>'Selection compliance review: re-evaluate these unresolved needs once: '.implode(', ',$missing).'. If a candidate name, category, attributes or description directly identifies the requested product function, select it with medium confidence and put every unverified compatibility constraint in uncertainty. Do not leave a direct function match missing merely because exact cross-product compatibility is unverified. Keep it missing when the function itself is unsupported or selection would be unsafe.',
        );
        return $messages;
    }

    public static function prefer_review(array $current, array $review): bool {
        $first = self::counts($current); $second = self::counts($review);
        return $second['score'] > $first['score'];
    }

    private static function counts(array $result): array {
        $data = self::tool_data($result);
        $selected = is_array($data['selections'] ?? null) ? count($data['selections']) : 0;
        $missing = is_array($data['missing_needs'] ?? null) ? count($data['missing_needs']) : 0;
        return array('selected'=>$selected,'missing'=>$missing,'score'=>($selected*10)-$missing);
    }

    private static function tool_data(array $result): ?array {
        $calls = $result['message']['tool_calls'] ?? array();
        if ($calls) {
            $data = json_decode((string) ($calls[0]['function']['arguments'] ?? '{}'),true);
            return is_array($data) ? $data : null;
        }
        $content = trim((string) ($result['message']['content'] ?? ''));
        $content = (string) preg_replace('/^```(?:json)?\s*|\s*```$/i','',$content);
        $data = json_decode($content,true);
        return is_array($data) ? $data : null;
    }
}
