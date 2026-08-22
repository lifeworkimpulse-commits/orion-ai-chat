<?php
if (!defined('ABSPATH')) { exit; }

final class Orion_Semantic_Product_Planner {
    private Orion_Product_Search $products;
    private Orion_Product_Facts $facts;
    private Orion_Kit_Validator $validator;

    public function __construct(Orion_Product_Search $products, ?Orion_Product_Facts $facts = null, ?Orion_Kit_Validator $validator = null) {
        $this->products = $products;
        $this->facts = $facts ?? new Orion_Product_Facts();
        $this->validator = $validator ?? new Orion_Kit_Validator($this->facts);
    }

    public function recommend(array $classification, array $state, array $settings): array {
        $source_plan = !empty($classification['needs']) && is_array($classification['needs']) ? $classification['needs'] : ($classification['search_plan'] ?? array());
        $plan = $this->normalise_plan(is_array($source_plan) ? $source_plan : array(), $state);
        if (!$plan) { return $this->empty_result('no_search_plan'); }

        $groups = array(); $candidate_ids = array(); $catalogue = array(); $needs_payload = array(); $need_meta = array(); $conditional_roles = array();
        $plan_count = count($plan);
        foreach ($plan as $item) {
            $query = sanitize_text_field((string) ($item['query'] ?? ''));
            $need_key = Orion_Role_Registry::open_key((string) ($item['need_key'] ?? $item['role'] ?? ''), $query);
            if (!$need_key) { continue; }
            $role_hint = Orion_Role_Registry::role_hint((string) ($item['role_hint'] ?? $need_key));
            $validation_role = $role_hint ?: $need_key;
            $required = !empty($item['required']);
            $requirements = is_array($item['requirements'] ?? null) ? $item['requirements'] : array();
            $description = sanitize_text_field((string) ($item['description'] ?? $query));
            $need_meta[$need_key] = array('role_hint'=>$role_hint,'required'=>$required,'description'=>$description);
            if (!$required && $role_hint && $this->defer_conditional($role_hint, $state, $plan_count)) {
                $conditional_roles[] = $need_key;
                $groups[] = array('role'=>$need_key,'need_key'=>$need_key,'role_hint'=>$role_hint,'description'=>$description,'required'=>false,'conditional'=>true,'requirements'=>$requirements,'candidates'=>array());
                continue;
            }
            $limit = 'roller' === $validation_role ? 8 : ($required ? 8 : 6);
            $found = $this->search_candidates($item, $need_key, $role_hint, $state, $limit);
            $ids = array(); $group_candidates = array();
            foreach ($found as $product) {
                $id = (int) ($product['id'] ?? 0); if (!$id) { continue; }
                $candidate_ids[$need_key][$id] = true; $ids[] = $id;
                if (!isset($catalogue[$id])) {
                    $catalogue[$id] = array(
                        'id'=>$id,'name'=>$product['name'] ?? '','type'=>$product['type'] ?? '','price'=>$product['price_text'] ?? '',
                        'categories'=>$product['categories'] ?? array(),'attributes'=>$product['attributes'] ?? array(),
                        'description'=>mb_substr((string) ($product['description'] ?? $product['short_description'] ?? ''), 0, 500),
                        'stock'=>$product['stock_status'] ?? '','facts'=>$this->facts->extract($product),'retrieval'=>$product['retrieval'] ?? array(),
                    );
                }
                $group_candidates[] = $catalogue[$id];
            }
            $groups[] = array('role'=>$need_key,'need_key'=>$need_key,'role_hint'=>$role_hint,'description'=>$description,'required'=>$required,'conditional'=>false,'requirements'=>$requirements,'candidates'=>$group_candidates);
            $needs_payload[] = array('need_key'=>$need_key,'role_hint'=>$role_hint,'description'=>$description,'required'=>$required,'requirements'=>$requirements,'candidate_ids'=>$ids);
        }

        if (!$candidate_ids) {
            $missing = array_values(array_filter(array_column($groups, 'need_key'), static fn($key)=>!in_array($key, $conditional_roles, true)));
            return array('products'=>array(),'missing_roles'=>$missing,'missing_needs'=>$missing,'conditional_roles'=>array_values(array_unique($conditional_roles)),'plan'=>$groups,'diagnostic'=>array('status'=>'no_relevant_candidates','catalogue_products'=>0,'need_candidates'=>0,'open_need_contract'=>true));
        }

        $payload = array('state'=>$state,'products'=>array_values($catalogue),'needs'=>$needs_payload);
        $payload_json = wp_json_encode($payload);
        $provider = Orion_AI_Provider_Factory::create($settings, 'selection');
        $selection_need_keys = array_values(array_keys($candidate_ids));
        $tool = [
            'type' => 'function',
            'function' => [
                'name' => 'select_products',
                'description' => 'Select evidence-supported candidate IDs for open product needs and report unresolved needs.',
                'parameters' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'selections' => [
                            'type' => 'array',
                            'maxItems' => 20,
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'properties' => [
                                    'need_key' => ['type'=>'string','enum'=>$selection_need_keys],
                                    'product_id' => ['type'=>'integer'],
                                    'reason' => ['type'=>'string'],
                                    'evidence_fields' => ['type'=>'array','minItems'=>1,'maxItems'=>7,'items'=>['type'=>'string','enum'=>Orion_Selection_Evidence::fields()]],
                                    'confidence' => ['type'=>'string','enum'=>['high','medium','low']],
                                    'uncertainty' => ['type'=>'string'],
                                ],
                                'required' => ['need_key','product_id','reason','evidence_fields','confidence','uncertainty'],
                            ],
                        ],
                        'missing_needs' => ['type'=>'array','items'=>['type'=>'string','enum'=>$selection_need_keys]],
                    ],
                    'required' => ['selections','missing_needs'],
                ],
            ],
        ];
        $prompt = 'Match live catalogue candidates to the supplied open product needs. Call select_products exactly once. Select only an ID listed for that exact need_key. Base every selection on explicit candidate fields and list the evidence_fields used. Use high or medium confidence only when the title, category, attributes, description or structured facts directly support the match. If support is weak, confidence is low, suitability is ambiguous, or a claim would require guessing, do not select that product; add the need to missing_needs. Never infer coverage, compatibility, suitability or included components. For a known roller need, require one explicit frame-and-sleeve set or a width-compatible frame and sleeve pair. A tray must not be narrower than the selected roller. Reuse an ID across needs only for an explicit set, kit or bundle whose contents support every need.';
        $result = $provider->chat(array(array('role'=>'system','content'=>$prompt),array('role'=>'user','content'=>$payload_json)), array($tool));
        $data = null; $calls = $result['message']['tool_calls'] ?? array();
        if ($calls) { $data = json_decode((string) ($calls[0]['function']['arguments'] ?? '{}'), true); }
        if (!is_array($data) && !empty($result['ok'])) { $data = $this->decode((string) ($result['message']['content'] ?? '')); }

        $selected = array(); $selected_index = array(); $selector_rejections = array();
        if (is_array($data)) {
            foreach (($data['selections'] ?? array()) as $choice) {
                if (!is_array($choice)) { continue; }
                $need_key = Orion_Role_Registry::open_key((string) ($choice['need_key'] ?? $choice['role'] ?? ''));
                $id = absint($choice['product_id'] ?? 0);
                if (!$need_key || !$id || empty($candidate_ids[$need_key][$id])) { continue; }
                $evidence = Orion_Selection_Evidence::normalise($choice);
                if (!Orion_Selection_Evidence::sufficient($evidence)) {
                    $selector_rejections[] = array('need_key'=>$need_key,'product_id'=>$id,'reason'=>'Selection did not include sufficient explicit catalogue evidence.');
                    continue;
                }
                $role_hint = (string) ($need_meta[$need_key]['role_hint'] ?? '');
                $validation_role = $role_hint ?: $need_key;
                $product = $this->products->get_product($id);
                if (!$product || empty($product['in_stock']) || !$this->candidate_allowed($product, $validation_role, $state)) { continue; }
                $evidence_entry = array_merge(array('need_key'=>$need_key), $evidence);
                if (isset($selected_index[$id])) {
                    if (!$this->facts->is_bundle($product)) { continue; }
                    $index = $selected_index[$id]; $this->add_role($selected[$index], $need_key, $role_hint);
                    $selected[$index]['selection_evidence'][] = $evidence_entry;
                    if ($evidence['reason'] !== '') { $selected[$index]['recommendation_reason'] .= ' ' . $evidence['reason']; }
                    continue;
                }
                $product = $this->decorate_selection($product, $need_key, $role_hint);
                $product['recommendation_reason'] = $evidence['reason']; $product['selection_evidence'] = array($evidence_entry);
                $selected_index[$id] = count($selected); $selected[] = $product;
            }
        }

        $max_products = max(1, (int) ($settings['max_products'] ?? 6));
        [$selected, $rejections] = $this->validator->validate($selected, $state);
        $rejections = array_values(array_merge($selector_rejections, $rejections));
        [$selected, $recovery] = $this->recover_roller_system($selected, $candidate_ids, $state);
        [$selected, $core_recovery] = $this->recover_core_roles($selected, $candidate_ids, $state, $max_products);
        if ($core_recovery) { $recovery['core_roles'] = $core_recovery; }
        [$selected, $recovery_rejections] = $this->validator->validate($selected, $state);
        $rejections = array_values(array_merge($rejections, $recovery_rejections));
        $selected = $this->annotate_quantity_guidance($selected, $state);
        usort($selected, fn($first,$second)=>$this->priority($first)<=>$this->priority($second));
        $selected_before_limit = count($selected); $displayed = array_slice($selected, 0, $max_products);
        [$displayed, $display_rejections] = $this->validator->validate($displayed, $state);
        $rejections = array_values(array_merge($rejections, $display_rejections));
        $displayed_roles = array();
        foreach ($displayed as $product) { foreach ((array) ($product['logical_roles'] ?? array()) as $role) { if ($role !== '') { $displayed_roles[] = $role; } } }
        $missing = array();
        foreach ($groups as $group) { if (empty($group['conditional']) && !in_array($group['need_key'], $displayed_roles, true)) { $missing[] = $group['need_key']; } }
        $reported_missing = is_array($data['missing_needs'] ?? null) ? $data['missing_needs'] : array();
        $reported_missing = array_values(array_filter(array_map(static fn($key)=>Orion_Role_Registry::open_key((string) $key), $reported_missing), static fn($key)=>$key !== ''));

        if (empty($result['ok'])) {
            $diagnostic = array('status'=>'selector_provider_error','error'=>sanitize_text_field((string) ($result['error'] ?? '')),'catalogue_products'=>count($catalogue),'payload_chars'=>strlen((string) $payload_json),'deterministic_recovery'=>$recovery,'quantity_guidance'=>$this->quantity_diagnostics($displayed),'open_need_contract'=>true);
        } elseif (!is_array($data)) {
            $diagnostic = array('status'=>'invalid_selector_output','tool_calls'=>count($calls),'content_excerpt'=>mb_substr(sanitize_textarea_field((string) ($result['message']['content'] ?? '')),0,500),'usage'=>$result['usage'] ?? array(),'attempts'=>$result['attempts'] ?? array(),'catalogue_products'=>count($catalogue),'payload_chars'=>strlen((string) $payload_json),'deterministic_recovery'=>$recovery,'quantity_guidance'=>$this->quantity_diagnostics($displayed),'open_need_contract'=>true);
        } else {
            $diagnostic = array('status'=>'ok','usage'=>$result['usage'] ?? array(),'attempts'=>$result['attempts'] ?? array(),'fallback_used'=>!empty($result['fallback_used']),'catalogue_products'=>count($catalogue),'need_candidates'=>array_sum(array_map('count', array_column($needs_payload,'candidate_ids'))),'payload_chars'=>strlen((string) $payload_json),'selected_before_card_limit'=>$selected_before_limit,'displayed_products'=>count($displayed),'roller_system_complete'=>in_array('roller',$displayed_roles,true),'selection_rejections'=>$rejections,'selector_missing_needs'=>$reported_missing,'deterministic_recovery'=>$recovery,'quantity_guidance'=>$this->quantity_diagnostics($displayed),'open_need_contract'=>true,'selection_evidence_required'=>true);
        }
        $missing = array_values(array_unique(array_merge($missing, $reported_missing)));
        return array('products'=>$displayed,'missing_roles'=>$missing,'missing_needs'=>$missing,'conditional_roles'=>array_values(array_unique($conditional_roles)),'plan'=>$groups,'diagnostic'=>$diagnostic);
    }

    private function normalise_plan(array $plan, array $state): array {
        $out = array(); $seen = array();
        foreach ($plan as $item) {
            if (!is_array($item)) { continue; }
            $query = sanitize_text_field((string) ($item['query'] ?? ''));
            $need_key = Orion_Role_Registry::open_key((string) ($item['need_key'] ?? $item['role'] ?? ''), $query);
            if (!$need_key || isset($seen[$need_key]) || !$query) { continue; }
            $role_hint = Orion_Role_Registry::role_hint((string) ($item['role_hint'] ?? $need_key));
            if ('dust_sheet' === ($role_hint ?: $need_key) && Orion_Routing_Rules::is_floor_project($state)) { continue; }
            $item['role'] = $need_key; $item['need_key'] = $need_key; $item['role_hint'] = $role_hint;
            $item['description'] = sanitize_text_field((string) ($item['description'] ?? $query));
            $out[] = $item; $seen[$need_key] = true;
        }
        $complete = Orion_Routing_Rules::wants_complete_kit($state) || count($out) >= 6;
        if ($complete && (isset($seen['primary_coating']) || isset($seen['primary_product']))) {
            $surface = sanitize_text_field((string) ($state['surface'] ?? 'painted surface'));
            $core = array('roller'=>'complete paint roller system with frame and sleeve for '.$surface,'tray'=>'paint roller tray compatible with the selected roller','brush'=>'paint brush for cutting in '.$surface,'masking_tape'=>'decorators masking tape for protecting edges');
            if (Orion_Routing_Rules::is_floor_project($state)) { $core['cleaner'] = 'floor cleaner or degreaser for coating preparation'; }
            else { $core['dust_sheet'] = 'protective dust sheet for painting'; }
            foreach ($core as $role => $query) { if (!isset($seen[$role])) { $out[] = array('role'=>$role,'need_key'=>$role,'role_hint'=>$role,'description'=>$query,'query'=>$query,'required'=>false,'requirements'=>array('surface'=>$surface)); $seen[$role] = true; } }
        }
        usort($out, static function ($first, $second): int {
            $first_role = !empty($first['role_hint']) ? (string) $first['role_hint'] : (string) ($first['need_key'] ?? '');
            $second_role = !empty($second['role_hint']) ? (string) $second['role_hint'] : (string) ($second['need_key'] ?? '');
            return Orion_Role_Registry::priority($first_role) <=> Orion_Role_Registry::priority($second_role);
        });
        return array_slice($out, 0, 12);
    }

    private function search_candidates(array $item, string $need_key, string $role_hint, array $state, int $limit): array {
        $query = sanitize_text_field((string) ($item['query'] ?? '')); $description = sanitize_text_field((string) ($item['description'] ?? ''));
        $requirements = is_array($item['requirements'] ?? null) ? $item['requirements'] : array(); $queries = array($query);
        if ($description !== '' && $description !== $query) { $queries[] = $description; }
        foreach ((array) ($item['search_terms'] ?? array()) as $term) { $term = sanitize_text_field((string) $term); if ($term !== '') { $queries[] = $term; } }
        $validation_role = $role_hint ?: $need_key;
        if ('roller' === $validation_role) { $surface = sanitize_text_field((string) ($state['surface'] ?? '')); $queries[] = 'complete paint roller set with frame and sleeve '.$surface; $queries[] = 'paint roller frame '.$surface; $queries[] = 'paint roller sleeve '.$surface; }
        $found = array();
        foreach (array_unique(array_filter($queries)) as $candidate_query) { foreach ($this->products->search(array('query'=>$candidate_query,'role'=>$validation_role,'requirements'=>$requirements,'in_stock'=>true,'limit'=>$limit)) as $product) {
            $id = (int) ($product['id'] ?? 0); if ($id && !isset($found[$id]) && $this->candidate_allowed($product, $validation_role, $state)) { $found[$id] = $product; }
        } }
        if ('roller' === $validation_role) { uasort($found, function ($first, $second): int { $first_meta = $this->facts->roller_component($first); $second_meta = $this->facts->roller_component($second); $weight = array('complete'=>300,'frame'=>200,'sleeve'=>190,'unknown'=>0); return (($weight[$second_meta['kind']] ?? 0)+(int)($second['retrieval']['score'] ?? 0)) <=> (($weight[$first_meta['kind']] ?? 0)+(int)($first['retrieval']['score'] ?? 0)); }); }
        return array_slice(array_values($found), 0, $limit);
    }

    private function candidate_allowed(array $product, string $role, array $state): bool {
        $facts = $this->facts->extract($product); $functions = $facts['functions']; $identity = strtolower((string) ($product['name'] ?? '').' '.implode(' ', (array) ($product['categories'] ?? array())));
        if ('primary_coating' === $role) { return in_array('primary_coating',$functions,true) && !preg_match('/\b(brush(?:es)?|roller|sleeve|frame|tray|plasterboard|board|tile|carpet|adhesive|filler)\b/',$identity); }
        if (in_array($role,array('cleaner','brush','tray','masking_tape','dust_sheet'),true) && !in_array($role,$functions,true)) { return false; }
        if ('roller' !== $role) { return true; }
        $component = $this->facts->roller_component($product); if ('unknown' === $component['kind']) { return false; }
        $text = $this->facts->text($product); $area = (float) ($state['area_m2'] ?? 0);
        if ($area >= 10 && (($component['width'] > 0 && $component['width'] <= 4.5) || preg_match('/\b(mini|radiator)\b/',$text))) { return false; }
        if (Orion_Routing_Rules::is_floor_project($state) && preg_match('/\b(gloss|emulsion|wall|ceiling|radiator|mini)\b/',$text) && !preg_match('/\b(floor|garage|epoxy|floor coating|heavy duty coating)\b/',$text)) { return false; }
        return true;
    }

    private function recover_roller_system(array $selected, array $candidate_ids, array $state): array {
        foreach ($selected as $product) { if (in_array('roller',(array)($product['logical_roles'] ?? array()),true)) { return array($selected,array()); } }
        $complete = array(); $frames = array(); $sleeves = array();
        foreach (array_keys((array) ($candidate_ids['roller'] ?? array())) as $id) { $product = $this->products->get_product((int) $id); if (!$product || empty($product['in_stock']) || !$this->candidate_allowed($product,'roller',$state)) { continue; } $meta = $this->facts->roller_component($product); $entry = array('product'=>$product,'width'=>(float)$meta['width']); if ('complete' === $meta['kind']) { $complete[] = $entry; } elseif ('frame' === $meta['kind']) { $frames[] = $entry; } elseif ('sleeve' === $meta['kind']) { $sleeves[] = $entry; } }
        if ($complete) {
            $id = (int) ($complete[0]['product']['id'] ?? 0);
            foreach ($selected as $index => $product) { if ((int) ($product['id'] ?? 0) === $id && $this->facts->is_bundle($product)) { $this->add_role($selected[$index],'roller','roller'); return array($selected,array('roller_complete_set'=>$id)); } }
            $product = $this->decorate_selection($complete[0]['product'],'roller','roller'); $product['recommendation_reason'] = 'Selected by deterministic complete roller-system recovery.'; $product['selection_evidence'] = array($this->deterministic_evidence('roller','Explicit frame-and-sleeve bundle facts support this recovery.')); $selected[] = $product;
            return array($selected,array('roller_complete_set'=>$id));
        }
        foreach ($frames as $frame) { foreach ($sleeves as $sleeve) { if ($frame['width'] <= 0 || $sleeve['width'] <= 0 || abs($frame['width']-$sleeve['width']) > 0.15) { continue; } $frame_product = $this->decorate_selection($frame['product'],'roller','roller'); $frame_product['recommendation_reason'] = 'Selected as the compatible roller frame.'; $frame_product['selection_evidence'] = array($this->deterministic_evidence('roller','Explicit roller component type and width support this frame.')); $sleeve_product = $this->decorate_selection($sleeve['product'],'roller','roller'); $sleeve_product['recommendation_reason'] = 'Selected as the width-compatible roller sleeve.'; $sleeve_product['selection_evidence'] = array($this->deterministic_evidence('roller','Explicit roller component type and width support this sleeve.')); $selected[] = $frame_product; $selected[] = $sleeve_product; return array($selected,array('roller_pair'=>array((int)($frame_product['id'] ?? 0),(int)($sleeve_product['id'] ?? 0)))); } }
        return array($selected,array());
    }

    private function recover_core_roles(array $selected, array $candidate_ids, array $state, int $max_products): array {
        $recovered = array(); $roles = array('tray','brush','cleaner','masking_tape','dust_sheet');
        foreach ($roles as $role) {
            $has_role = false; foreach ($selected as $product) { if (in_array($role,(array)($product['logical_roles'] ?? array()),true)) { $has_role = true; break; } }
            if ($has_role || empty($candidate_ids[$role])) { continue; }
            foreach (array_keys((array) $candidate_ids[$role]) as $id) {
                $product = $this->products->get_product((int) $id); if (!$product || empty($product['in_stock']) || !$this->candidate_allowed($product,$role,$state)) { continue; }
                $existing = null; foreach ($selected as $index => $item) { if ((int)($item['id'] ?? 0) === (int)$id) { $existing = $index; break; } }
                if (null !== $existing) { if (!$this->facts->is_bundle($product)) { continue; } $this->add_role($selected[$existing],$role,$role); $selected[$existing]['selection_evidence'][] = $this->deterministic_evidence($role,'Explicit bundle contents support this known product need.'); $recovered[$role] = (int) $id; break; }
                if (count($selected) >= $max_products) { break; }
                $product = $this->decorate_selection($product,$role,$role); $product['recommendation_reason'] = 'Added from a verified catalogue candidate to complete the requested core kit.'; $product['selection_evidence'] = array($this->deterministic_evidence($role,'Structured catalogue facts explicitly identify this product function.')); $selected[] = $product; $recovered[$role] = (int) $id; break;
            }
        }
        return array($selected,$recovered);
    }

    private function annotate_quantity_guidance(array $selected, array $state): array {
        $area = (float) ($state['area_m2'] ?? 0);
        foreach ($selected as $index => $product) { if (!in_array('primary_coating',(array)($product['logical_roles'] ?? array()),true)) { continue; } $facts = is_array($product['orion_facts'] ?? null) ? $product['orion_facts'] : $this->facts->extract($product); $coverage = isset($facts['coverage_m2_per_litre']) ? (float)$facts['coverage_m2_per_litre'] : 0.0; $volume = isset($facts['pack_volume_litres']) ? (float)$facts['pack_volume_litres'] : 0.0; $status = 'not_calculable'; $reason = 'Project quantity was not calculated because verified manufacturer coverage, coat count or pack-volume evidence is incomplete.'; if ($area <= 0) { $reason = 'Project area is not available, so quantity was not calculated.'; } elseif ($coverage > 0 && $volume > 0) { $status = 'evidence_present_review_required'; $reason = 'Coverage and pack volume are explicit, but coat count and manufacturer instructions must be confirmed before calculating packs.'; } $selected[$index]['quantity_guidance'] = array('status'=>$status,'can_calculate'=>false,'area_m2'=>$area>0?$area:null,'coverage_m2_per_litre'=>$coverage>0?$coverage:null,'pack_volume_litres'=>$volume>0?$volume:null,'reason'=>$reason); }
        return $selected;
    }

    private function quantity_diagnostics(array $products): array { $out = array(); foreach ($products as $product) { if (!empty($product['quantity_guidance'])) { $out[] = array('id'=>(int)($product['id'] ?? 0),'guidance'=>$product['quantity_guidance']); } } return $out; }
    private function decorate_selection(array $product, string $need_key, string $role_hint = ''): array { $facts = $this->facts->extract($product); $validation_role = $role_hint ?: $need_key; $display = $validation_role; if ('roller' === $validation_role) { $display = array('frame'=>'roller_frame','sleeve'=>'roller_sleeve','complete'=>'roller')[$facts['roller_component']] ?? 'roller'; } $product['kit_role'] = $display; $product['kit_roles'] = array($display); $product['logical_roles'] = array($need_key); $product['need_key'] = $need_key; $product['role_hint'] = $role_hint; $product['orion_facts'] = $facts; if ('roller' === $validation_role) { $product['roller_component'] = $facts['roller_component']; $product['roller_width_inches'] = $facts['roller_width_inches']; } if ('tray' === $validation_role) { $product['tray_width_inches'] = $facts['tray_width_inches']; } return $product; }
    private function add_role(array &$product, string $need_key, string $role_hint = ''): void { $validation_role = $role_hint ?: $need_key; $display = $validation_role; if ('roller' === $validation_role) { $kind = $this->facts->roller_component($product)['kind']; $display = array('frame'=>'roller_frame','sleeve'=>'roller_sleeve','complete'=>'roller')[$kind] ?? 'roller'; } if (!in_array($display,(array)($product['kit_roles'] ?? array()),true)) { $product['kit_roles'][] = $display; } if (!in_array($need_key,(array)($product['logical_roles'] ?? array()),true)) { $product['logical_roles'][] = $need_key; } $product['kit_role'] = $product['kit_roles'][0] ?? $display; }
    private function deterministic_evidence(string $need_key, string $reason): array { return array('need_key'=>$need_key,'evidence_fields'=>array('name','facts'),'confidence'=>'high','reason'=>$reason,'uncertainty'=>''); }
    private function defer_conditional(string $role, array $state, int $plan_count): bool { if ($plan_count <= 1 || !in_array($role,array('primer','cleaner','filler','sandpaper','scraper'),true)) { return false; } return !Orion_Routing_Rules::preparation_role_needed($role,$state); }
    private function priority(array $product): int { $values = array_map(static fn($role)=>Orion_Role_Registry::priority((string)$role),(array)($product['kit_roles'] ?? array($product['kit_role'] ?? ''))); return $values ? min($values) : 80; }
    private function empty_result(string $status): array { return array('products'=>array(),'missing_roles'=>array(),'missing_needs'=>array(),'conditional_roles'=>array(),'plan'=>array(),'diagnostic'=>array('status'=>$status,'open_need_contract'=>true)); }
    private function decode(string $text): ?array { $text = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/i','',trim($text))); $data = json_decode($text,true); if (is_array($data)) { return $data; } if (preg_match('/\{[\s\S]*\}/',$text,$matches)) { $data = json_decode($matches[0],true); return is_array($data) ? $data : null; } return null; }
}
