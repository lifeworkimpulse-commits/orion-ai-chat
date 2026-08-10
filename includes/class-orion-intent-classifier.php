<?php
if (!defined('ABSPATH')) { exit; }

final class Orion_Intent_Classifier {
    public function classify(string $message, array $history, array $state, array $settings): array {
        $resolved = $this->resolve_pending($message, $history, $state);
        if ($resolved) { return $resolved; }
        $policy = $this->deterministic_store_policy($message);
        if ($policy) { return $policy; }
        $provider = Orion_AI_Provider_Factory::create($settings, 'routing');
        $fallback = $this->fallback();
        $system = 'You are a semantic router for a WooCommerce shopping assistant. Call route_request exactly once. Preserve active project context for short answers. Ask at most three questions and only when missing information materially changes suitability, safety or quantity. Never calculate area_m2 until the dimension unit is known. Use only the supported catalogue roles. For complete painting kits prioritise the principal material, roller, tray, brush, masking tape and floor-appropriate protection before conditional preparation roles. Only the principal material may be required by default. Questions about delivery, shipping, collection, a destination or postcode are store_policy. Never use personas or services as product roles.';
        $tool = array('type'=>'function','function'=>array(
            'name'=>'route_request','description'=>'Return the semantic route.',
            'parameters'=>array('type'=>'object','additionalProperties'=>false,'properties'=>array(
                'intent'=>array('type'=>'string','enum'=>array('project_recommendation','product_search','store_policy','general','manager_follow_up')),
                'topic'=>array('type'=>'string'),'is_new_topic'=>array('type'=>'boolean'),'confidence'=>array('type'=>'number'),
                'needs_clarification'=>array('type'=>'boolean'),'clarifying_questions'=>array('type'=>'array','items'=>array('type'=>'string'),'maxItems'=>3),
                'policy_query'=>array('type'=>'string'),
                'state_patch'=>array('type'=>'object','properties'=>array(
                    'project_type'=>array('type'=>'string'),'surface'=>array('type'=>'string'),'area_m2'=>array('type'=>'number'),
                    'environment'=>array('type'=>'string'),'traffic'=>array('type'=>'string'),'colour'=>array('type'=>'string'),
                    'finish'=>array('type'=>'string'),'dimensions'=>array('type'=>'string'),'dimension_length'=>array('type'=>'number'),
                    'dimension_width'=>array('type'=>'number'),'dimension_unit'=>array('type'=>'string'),'notes'=>array('type'=>'string'),
                )),
                'search_plan'=>array('type'=>'array','maxItems'=>10,'items'=>array('type'=>'object','additionalProperties'=>false,'properties'=>array(
                    'role'=>array('type'=>'string','enum'=>Orion_Role_Registry::all()),'query'=>array('type'=>'string'),
                    'required'=>array('type'=>'boolean'),'requirements'=>array('type'=>'object'),
                ),'required'=>array('role','query','required'))),
            ),'required'=>array('intent','topic','is_new_topic','confidence','needs_clarification','clarifying_questions','policy_query','state_patch','search_plan')),
        ));
        $context = array('saved_state'=>$state,'recent_messages'=>array_slice($history,-8),'current_message'=>$message);
        $result = $provider->chat(array(array('role'=>'system','content'=>$system),array('role'=>'user','content'=>wp_json_encode($context))), array($tool));
        if (empty($result['ok'])) {
            $fallback['_diagnostic'] = array('status'=>'provider_error','error'=>sanitize_text_field((string)($result['error'] ?? 'Unknown provider error')));
            return $fallback;
        }
        $data = null; $calls = $result['message']['tool_calls'] ?? array();
        if ($calls) { $data = json_decode((string)($calls[0]['function']['arguments'] ?? '{}'), true); }
        if (!is_array($data)) { $data = $this->decode((string)($result['message']['content'] ?? '')); }
        if (!is_array($data)) {
            $fallback['_diagnostic'] = array('status'=>'invalid_output','tool_calls'=>count($calls),'content_excerpt'=>mb_substr(sanitize_textarea_field((string)($result['message']['content'] ?? '')),0,500));
            return $fallback;
        }
        $out = $this->validate($data, $fallback);
        if (in_array($out['intent'], array('project_recommendation','product_search'), true)) {
            foreach (Orion_Routing_Rules::extract_dimensions($message) as $key => $value) { if (!isset($out['state_patch'][$key])) { $out['state_patch'][$key] = $value; } }
        }
        $out['_diagnostic'] = array('status'=>'ok','usage'=>$result['usage'] ?? array(),'attempts'=>$result['attempts'] ?? array(),'fallback_used'=>!empty($result['fallback_used']));
        return $out;
    }

    private function deterministic_store_policy(string $message): ?array {
        $signals = Orion_Routing_Rules::delivery_policy_signals($message);
        if (!$signals) { return null; }
        $out = $this->fallback();
        $out['intent'] = 'store_policy'; $out['topic'] = 'delivery'; $out['is_new_topic'] = true; $out['confidence'] = 1.0;
        $out['policy_query'] = sanitize_text_field($message);
        $out['_diagnostic'] = array('status'=>'deterministic_store_policy_routing','signals'=>$signals);
        return $out;
    }

    private function resolve_pending(string $message, array $history, array $state): ?array {
        $pending = is_array($state['pending_clarifications'] ?? null) ? $state['pending_clarifications'] : array();
        if (!$pending) { return null; }
        $text = strtolower(trim($message)); $patch = array();
        if (preg_match('/^(m|metre|metres|meter|meters)(\b|[ ,])/i', $text)) { $patch['dimension_unit'] = 'm'; }
        elseif (preg_match('/^(ft|foot|feet)(\b|[ ,])/i', $text)) { $patch['dimension_unit'] = 'ft'; }
        foreach (array('concrete','painted concrete','epoxy','wood','plaster','masonry','brick','metal') as $surface) {
            if (str_contains($text, $surface)) { $patch['surface'] = $surface; break; }
        }
        if (!$patch) { return null; }
        $dimensions = array();
        foreach (array_reverse($history) as $item) {
            if (($item['role'] ?? '') !== 'user') { continue; }
            $dimensions = Orion_Routing_Rules::extract_dimensions((string)($item['content'] ?? ''));
            if ($dimensions) { break; }
        }
        foreach ($dimensions as $key => $value) { if (!isset($patch[$key])) { $patch[$key] = $value; } }
        $remaining = array();
        foreach ($pending as $question) {
            $low = strtolower((string)$question);
            $answered = (preg_match('/met(er|re)|feet|foot|\bft\b/', $low) && isset($patch['dimension_unit'])) || (preg_match('/surface|substrate|made of/', $low) && isset($patch['surface']));
            if (!$answered) { $remaining[] = sanitize_text_field((string)$question); }
        }
        $out = $this->fallback();
        $out['intent'] = $state['active_intent'] ?? 'project_recommendation'; $out['topic'] = $state['active_topic'] ?? 'project'; $out['confidence'] = 1.0;
        $out['needs_clarification'] = !empty($remaining); $out['clarifying_questions'] = $remaining; $out['state_patch'] = $patch;
        $out['search_plan'] = is_array($state['pending_search_plan'] ?? null) ? $state['pending_search_plan'] : array();
        $out['_diagnostic'] = array('status'=>'deterministic_clarification_resolution','resolved_fields'=>array_keys($patch));
        return $out;
    }

    private function validate(array $data, array $out): array {
        $allowed_intents = array('project_recommendation','product_search','store_policy','general','manager_follow_up');
        $out['intent'] = in_array($data['intent'] ?? '', $allowed_intents, true) ? $data['intent'] : 'manager_follow_up';
        $out['topic'] = sanitize_key((string)($data['topic'] ?? '')); $out['is_new_topic'] = !empty($data['is_new_topic']);
        $out['confidence'] = max(0, min(1, (float)($data['confidence'] ?? 0))); $out['needs_clarification'] = !empty($data['needs_clarification']);
        $out['policy_query'] = sanitize_text_field((string)($data['policy_query'] ?? ''));
        $questions = is_array($data['clarifying_questions'] ?? null) ? $data['clarifying_questions'] : array();
        $out['clarifying_questions'] = array_slice(array_values(array_filter(array_map(static fn($value)=>sanitize_text_field((string)$value), $questions))),0,3);
        $allowed_state = array('project_type','surface','area_m2','environment','traffic','colour','finish','dimensions','dimension_length','dimension_width','dimension_unit','notes');
        $patch = is_array($data['state_patch'] ?? null) ? $data['state_patch'] : array();
        foreach ($allowed_state as $key) {
            if (array_key_exists($key, $patch) && $patch[$key] !== '' && $patch[$key] !== null) { $out['state_patch'][$key] = is_numeric($patch[$key]) ? (float)$patch[$key] : sanitize_text_field((string)$patch[$key]); }
        }
        if ((isset($out['state_patch']['dimension_length']) || isset($out['state_patch']['dimension_width'])) && empty($out['state_patch']['dimension_unit'])) { unset($out['state_patch']['area_m2']); }
        $plan = is_array($data['search_plan'] ?? null) ? $data['search_plan'] : array();
        foreach (array_slice($plan,0,10) as $item) {
            if (!is_array($item)) { continue; }
            $role = Orion_Role_Registry::canonical((string)($item['role'] ?? '')); $query = sanitize_text_field((string)($item['query'] ?? ''));
            if (!Orion_Role_Registry::supported($role) || !$query) { continue; }
            $requirements = is_array($item['requirements'] ?? null) ? $item['requirements'] : array(); $clean = array();
            foreach (array_slice($requirements,0,10,true) as $key => $value) { $clean[sanitize_key((string)$key)] = sanitize_text_field(is_scalar($value) ? (string)$value : wp_json_encode($value)); }
            $out['search_plan'][] = array('role'=>$role,'query'=>$query,'required'=>!empty($item['required']) && !Orion_Role_Registry::optional($role),'requirements'=>$clean);
        }
        $combined_state = array_merge($out['state_patch'], array('project_type'=>$out['topic']));
        if (Orion_Routing_Rules::is_floor_project($combined_state)) { $out['search_plan'] = array_values(array_filter($out['search_plan'], static fn($item)=>($item['role'] ?? '') !== 'dust_sheet')); }
        if ($out['intent'] === 'product_search' && (!empty($out['state_patch']['project_type']) || isset($out['state_patch']['area_m2'])) && count($out['search_plan']) > 1) { $out['intent'] = 'project_recommendation'; }
        return $out;
    }

    private function fallback(): array { return array('intent'=>'manager_follow_up','topic'=>'unclassified','is_new_topic'=>false,'confidence'=>0.0,'needs_clarification'=>false,'clarifying_questions'=>array(),'policy_query'=>'','state_patch'=>array(),'search_plan'=>array(),'_diagnostic'=>array('status'=>'fallback')); }
    private function decode(string $text): ?array {
        $text = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text))); $data = json_decode($text, true);
        if (is_array($data)) { return $data; }
        if (preg_match('/\{[\s\S]*\}/', $text, $matches)) { $data = json_decode($matches[0], true); return is_array($data) ? $data : null; }
        return null;
    }
}
