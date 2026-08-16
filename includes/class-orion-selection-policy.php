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
        return 'Generic validation policy: separate evidence that a product performs the requested function from evidence for secondary compatibility details. When a live title, category, attributes or description directly identifies the requested product type or function, you MUST select that product even if the need says compatible, explicitly compatible or must match and an exact size, connector, fit, capacity or cross-product compatibility detail remains unverified. Use medium confidence, record the exact unresolved detail in uncertainty, and do not claim compatibility. Add a need to missing_needs only when the requested product function itself is unsupported, evidence is too weak to identify the product type, or selection would be unsafe. Never convert unresolved compatibility into a positive compatibility claim. Keep each selection reason and uncertainty concise, using one sentence and no more than 180 characters for each field, so the tool call remains complete.';
    }

    public static function valid_result(array $result): bool {
        $data=self::tool_data($result);
        return is_array($data)&&is_array($data['selections']??null)&&is_array($data['missing_needs']??null);
    }

    public static function needs_review(array $result, array $messages = array()): bool { return !empty(self::review_needs($result,$messages)); }

    public static function missing_needs(array $result): array {
        $data=self::tool_data($result);$missing=is_array($data['missing_needs']??null)?$data['missing_needs']:array();
        return array_values(array_filter(array_map('strval',$missing),static fn($key)=>$key!==''));
    }

    public static function review_needs(array $result, array $messages = array()): array {
        $missing=self::missing_needs($result);if(!$missing||!$messages)return$missing;
        $payload=self::payload($messages);if(!$payload)return$missing;
        $review=array();$core=array('primary_coating','primary_product','roller','tray','brush','masking_tape');
        foreach((array)($payload['needs']??array())as$need){$key=(string)($need['need_key']??'');if(!in_array($key,$missing,true))continue;$hint=Orion_Role_Registry::role_hint((string)($need['role_hint']??$key));if(!empty($need['required'])||in_array($hint,$core,true))$review[]=$key;}
        return array_values(array_unique($review));
    }

    public static function review_messages(array $messages, array $result): array {
        $missing=self::review_needs($result,$messages);
        $messages[] = array('role'=>'system','content'=>'Selection compliance review: re-evaluate these unresolved needs once: '.implode(', ',$missing).'. If a candidate name, category, attributes or description directly identifies the requested product function, select it with medium confidence and put every unverified compatibility constraint in uncertainty. Do not leave a direct function match missing merely because exact cross-product compatibility is unverified. Keep it missing when the function itself is unsupported or selection would be unsafe.');
        return $messages;
    }

    public static function prefer_review(array $current, array $review): bool {$first=self::counts($current);$second=self::counts($review);return$second['score']>$first['score'];}

    private static function counts(array $result): array {$data=self::tool_data($result);$selected=is_array($data['selections']??null)?count($data['selections']):0;$missing=is_array($data['missing_needs']??null)?count($data['missing_needs']):0;return array('selected'=>$selected,'missing'=>$missing,'score'=>($selected*10)-$missing);}
    private static function payload(array $messages): ?array {for($index=count($messages)-1;$index>=0;$index--){$content=$messages[$index]['content']??null;if(!is_string($content))continue;$data=json_decode($content,true);if(is_array($data)&&isset($data['needs']))return$data;}return null;}
    private static function tool_data(array $result): ?array {$calls=$result['message']['tool_calls']??array();if($calls){$data=json_decode((string)($calls[0]['function']['arguments']??'{}'),true);return is_array($data)?$data:null;}$content=trim((string)($result['message']['content']??''));$content=(string)preg_replace('/^```(?:json)?\s*|\s*```$/i','',$content);$data=json_decode($content,true);return is_array($data)?$data:null;}
}
