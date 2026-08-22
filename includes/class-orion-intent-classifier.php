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
        $system = 'You are an open semantic planner for a WooCommerce shopping assistant. Call route_request exactly once. Preserve active project context for short answers. Ask at most three questions and only when missing information materially changes suitability, safety or quantity. Never calculate area_m2 until the dimension unit is known. Build search_plan as an open list of concrete product needs derived from the customer project. Each role is a short semantic need key such as primary_coating, diamond_drill_bit or waterproof_membrane. Known catalogue roles may be used when they fit, but they are hints rather than a closed list. Never force a new product type into an unrelated known role. For complete painting kits prioritise the principal material, roller, tray, brush, masking tape and floor-appropriate protection before conditional preparation needs. Only principal materials should normally be required by default. Questions about delivery, shipping, collection, a destination or postcode are store_policy. Never use personas, professions or services as product needs.';
        $tool = array('type'=>'function','function'=>array(
            'name'=>'route_request','description'=>'Return the semantic route and an open product-need plan.',
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
                'search_plan'=>array('type'=>'array','maxItems'=>12,'items'=>array('type'=>'object','additionalProperties'=>false,'properties'=>array(
                    'role'=>array('type'=>'string','minLength'=>2,'maxLength'=>64,'description'=>'Free-form product need key. Use a known role only when it accurately describes the need.'),
                    'description'=>array('type'=>'string','description'=>'Plain-language description of why the project needs this product type.'),
                    'query'=>array('type'=>'string'),'required'=>array('type'=>'boolean'),'requirements'=>array('type'=>'object'),
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
        $out['_diagnostic'] = array('status'=>'ok','usage'=>$result['usage'] ?? array(),'attempts'=>$result['attempts'] ?? array(),'fallback_used'=>!empty($result['fallback_used']),'open_need_contract'=>true);
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
        foreach (array('painted concrete','concrete','epoxy','wood','plaster','masonry','brick','metal') as $surface) {
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
        $out['needs'] = $out['search_plan'];
        $out['_diagnostic'] = array('status'=>'deterministic_clarification_resolution','resolved_fields'=>array_keys($patch),'open_need_contract'=>true);
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
            if (!array_key_exists($key, $patch) || $patch[$key] === '' || $patch[$key] === null) { continue; }
            $value = is_numeric($patch[$key]) ? (float)$patch[$key] : sanitize_text_field((string)$patch[$key]);
            if ('surface' === $key && is_string($value)) { $value = $this->canonical_surface($value); }
            $out['state_patch'][$key] = $value;
        }
        if ((isset($out['state_patch']['dimension_length']) || isset($out['state_patch']['dimension_width'])) && empty($out['state_patch']['dimension_unit'])) { unset($out['state_patch']['area_m2']); }
        $plan = is_array($data['search_plan'] ?? null) ? $data['search_plan'] : array();
        foreach (array_slice($plan,0,12) as $item) {
            if (!is_array($item)) { continue; }
            $query = sanitize_text_field((string)($item['query'] ?? ''));
            $role = Orion_Role_Registry::open_key((string)($item['role'] ?? ''), $query);
            if (!Orion_Role_Registry::supported($role) || !$query) { continue; }
            $requirements = is_array($item['requirements'] ?? null) ? $item['requirements'] : array(); $clean = array();
            foreach (array_slice($requirements,0,10,true) as $key => $value) { $clean[sanitize_key((string)$key)] = sanitize_text_field(is_scalar($value) ? (string)$value : wp_json_encode($value)); }
            $known = Orion_Role_Registry::known($role);
            $out['search_plan'][] = array(
                'role'=>$role,
                'need_key'=>$role,
                'role_hint'=>$known ? $role : '',
                'description'=>sanitize_text_field((string)($item['description'] ?? $query)),
                'query'=>$query,
                'required'=>!empty($item['required']) && (!$known || !Orion_Role_Registry::optional($role)),
                'requirements'=>$clean,
            );
        }
        $combined_state = array_merge($out['state_patch'], array('project_type'=>$out['state_patch']['project_type'] ?? $out['topic']));
        $out['search_plan'] = $this->normalise_plan($out['search_plan'], $combined_state);
        $out['needs'] = $out['search_plan'];
        if ($out['intent'] === 'product_search' && (!empty($out['state_patch']['project_type']) || isset($out['state_patch']['area_m2'])) && count($out['search_plan']) > 1) { $out['intent'] = 'project_recommendation'; }
        return $out;
    }

    private function normalise_plan(array $plan, array $state): array {
        $out = array(); $seen = array(); $floor = Orion_Routing_Rules::is_floor_project($state);
        foreach ($plan as $item) {
            $role = Orion_Role_Registry::open_key((string)($item['role'] ?? ''), (string)($item['query'] ?? ''));
            if ('dust_sheet' === $role && $floor) { continue; }
            if (!Orion_Role_Registry::supported($role) || isset($seen[$role])) { continue; }
            $item['role'] = $role;
            $item['need_key'] = $role;
            $item['role_hint'] = Orion_Role_Registry::role_hint($role);
            $out[] = $item; $seen[$role] = true;
        }
        $complete = Orion_Routing_Rules::wants_complete_kit($state) || count($out) >= 6;
        if ($complete && (isset($seen['primary_coating']) || isset($seen['primary_product']))) {
            $surface = sanitize_text_field((string)($state['surface'] ?? 'painted surface'));
            $core = array(
                'roller'=>'complete paint roller system with frame and sleeve for '.$surface,
                'tray'=>'paint roller tray compatible with the selected roller',
                'brush'=>'paint brush for cutting in '.$surface,
                'masking_tape'=>'decorators masking tape for protecting edges',
            );
            if ($floor) { $core['cleaner'] = 'floor cleaner or degreaser for coating preparation'; }
            else { $core['dust_sheet'] = 'protective dust sheet for painting'; }
            foreach ($core as $role => $query) {
                if (!isset($seen[$role])) {
                    $out[] = array('role'=>$role,'need_key'=>$role,'role_hint'=>$role,'description'=>$query,'query'=>$query,'required'=>false,'requirements'=>array('surface'=>$surface));
                    $seen[$role] = true;
                }
            }
        }
        usort($out,static fn($first,$second)=>Orion_Role_Registry::priority((string)($first['role'] ?? ''))<=>Orion_Role_Registry::priority((string)($second['role'] ?? '')));
        return array_slice($out,0,12);
    }

    private function canonical_surface(string $surface): string {
        $surface = strtolower(trim($surface));
        if (str_contains($surface,'painted concrete')) { return 'painted concrete'; }
        if (str_contains($surface,'concrete')) { return 'concrete'; }
        foreach (array('ceiling','wall','plaster','masonry','brick','wood','timber','metal','tile') as $known) { if (str_contains($surface,$known)) { return $known; } }
        return sanitize_text_field($surface);
    }

    private function fallback(): array {
        return array('intent'=>'manager_follow_up','topic'=>'unclassified','is_new_topic'=>false,'confidence'=>0.0,'needs_clarification'=>false,'clarifying_questions'=>array(),'policy_query'=>'','state_patch'=>array(),'search_plan'=>array(),'needs'=>array(),'_diagnostic'=>array('status'=>'fallback'));
    }

    private function decode(string $text): ?array {
        $text = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text))); $data = json_decode($text, true);
        if (is_array($data)) { return $data; }
        if (preg_match('/\{[\s\S]*\}/', $text, $matches)) { $data = json_decode($matches[0], true); return is_array($data) ? $data : null; }
        return null;
    }
}
