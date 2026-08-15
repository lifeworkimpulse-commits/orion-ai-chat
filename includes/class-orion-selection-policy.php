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
        return 'Generic validation policy: separate evidence that a product performs the requested function from evidence for secondary compatibility details. A direct product-type or function match in the live title, category, attributes or description is enough to select the product. If its function is supported but an exact size, connector, fit, capacity or cross-product compatibility detail is not explicit, select it with medium confidence, record that exact unresolved detail in uncertainty, and do not claim compatibility. Add a need to missing_needs only when the requested product function itself is unsupported, evidence is too weak to identify the product type, or selection would be unsafe. Never convert unresolved compatibility into a positive compatibility claim.';
    }
}
