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
        $plan = $this->normalise_plan($classification['search_plan'] ?? array(), $state);
        if (!$plan) { return $this->empty_result('no_search_plan'); }
        $groups = array(); $candidate_ids = array(); $catalogue = array(); $roles_payload = array(); $conditional_roles = array(); $plan_count = count($plan);
        foreach ($plan as $item) {
            $query = sanitize_text_field((string)($item['query'] ?? ''));
            $role = Orion_Role_Registry::canonical_for_query((string)($item['role'] ?? ''), $query);
            if (!Orion_Role_Registry::supported($role)) { continue; }
            $required = !empty($item['required']); $requirements = is_array($item['requirements'] ?? null) ? $item['requirements'] : array();
            if (!$required && $this->defer_conditional($role, $state, $plan_count)) {
                $conditional_roles[] = $role; $groups[] = array('role'=>$role,'required'=>false,'conditional'=>true,'requirements'=>$requirements,'candidates'=>array()); continue;
            }
            $limit = 'roller' === $role ? 8 : ($required ? 8 : 6); $found = $this->search_candidates($item, $role, $state, $limit); $ids = array(); $group_candidates = array();
            foreach ($found as $product) {
                $id = (int)($product['id'] ?? 0); if (!$id) { continue; }
                $candidate_ids[$role][$id] = true; $ids[] = $id;
                if (!isset($catalogue[$id])) {
                    $catalogue[$id] = array(
                        'id'=>$id,'name'=>$product['name'] ?? '','type'=>$product['type'] ?? '','price'=>$product['price_text'] ?? '',
                        'categories'=>$product['categories'] ?? array(),'attributes'=>$product['attributes'] ?? array(),
                        'description'=>mb_substr((string)($product['description'] ?? $product['short_description'] ?? ''),0,380),
                        'stock'=>$product['stock_status'] ?? '','facts'=>$this->facts->extract($product),'retrieval'=>$product['retrieval'] ?? array(),
                    );
                }
                $group_candidates[] = $catalogue[$id];
            }
            $groups[] = array('role'=>$role,'required'=>$required,'conditional'=>false,'requirements'=>$requirements,'candidates'=>$group_candidates);
            $roles_payload[] = array('role'=>$role,'required'=>$required,'requirements'=>$requirements,'candidate_ids'=>$ids);
        }
        if (!$candidate_ids) {
            $missing = array_values(array_filter(array_column($groups,'role'), static fn($role)=>!in_array($role,$conditional_roles,true)));
            return array('products'=>array(),'missing_roles'=>$missing,'conditional_roles'=>array_values(array_unique($conditional_roles)),'plan'=>$groups,'diagnostic'=>array('status'=>'no_relevant_candidates','catalogue_products'=>0,'role_candidates'=>0));
        }

        $payload = array('state'=>$state,'products'=>array_values($catalogue),'roles'=>$roles_payload); $payload_json = wp_json_encode($payload);
        $provider = Orion_AI_Provider_Factory::create($settings,'selection'); $selection_roles = array_values(array_keys($candidate_ids));
        $tool = array('type'=>'function','function'=>array('name'=>'select_products','description'=>'Select only verified candidate IDs and report unresolved roles.','parameters'=>array(
            'type'=>'object','additionalProperties'=>false,'properties'=>array(
                'selections'=>array('type'=>'array','maxItems'=>20,'items'=>array('type'=>'object','additionalProperties'=>false,'properties'=>array(
                    'role'=>array('type'=>'string','enum'=>$selection_roles),'product_id'=>array('type'=>'integer'),'reason'=>array('type'=>'string'),
                ),'required'=>array('role','product_id','reason'))),
                'missing_roles'=>array('type'=>'array','items'=>array('type'=>'string','enum'=>$selection_roles)),
            ),'required'=>array('selections','missing_roles'),
        )));
        $prompt = 'Use the supplied structured facts and live catalogue text to match product IDs to roles. Select only IDs allowed for that role. Never infer coverage, suitability or included components. A roller role requires either one explicitly complete frame-and-sleeve set or a width-compatible frame and sleeve pair. A tray must not be narrower than the selected roller. Reuse an ID across roles only for an explicit set, kit or bundle whose contents support every role. Prefer the practical core kit over conditional preparation products. Call select_products exactly once.';
        $result = $provider->chat(array(array('role'=>'system','content'=>$prompt),array('role'=>'user','content'=>$payload_json)),array($tool));
        $data = null; $calls = $result['message']['tool_calls'] ?? array();
        if ($calls) { $data = json_decode((string)($calls[0]['function']['arguments'] ?? '{}'),true); }
        if (!is_array($data) && !empty($result['ok'])) { $data = $this->decode((string)($result['message']['content'] ?? '')); }

        $selected = array(); $selected_index = array();
        if (is_array($data)) { foreach (($data['selections'] ?? array()) as $choice) {
            $role = Orion_Role_Registry::canonical((string)($choice['role'] ?? '')); $id = absint($choice['product_id'] ?? 0);
            if (!$role || !$id || empty($candidate_ids[$role][$id])) { continue; }
            $product = $this->products->get_product($id);
            if (!$product || empty($product['in_stock']) || !$this->candidate_allowed($product,$role,$state)) { continue; }
            if (isset($selected_index[$id])) {
                if (!$this->facts->is_bundle($product)) { continue; }
                $index = $selected_index[$id]; $this->add_role($selected[$index],$role); $reason = sanitize_text_field((string)($choice['reason'] ?? ''));
                if ($reason !== '') { $selected[$index]['recommendation_reason'] .= ' ' . $reason; }
                continue;
            }
            $product = $this->decorate_selection($product,$role); $product['recommendation_reason'] = sanitize_text_field((string)($choice['reason'] ?? ''));
            $selected_index[$id] = count($selected); $selected[] = $product;
        } }

        $max_products = max(1,(int)($settings['max_products'] ?? 6));
        [$selected,$rejections] = $this->validator->validate($selected,$state);
        [$selected,$recovery] = $this->recover_roller_system($selected,$candidate_ids,$state);
        [$selected,$core_recovery] = $this->recover_core_roles($selected,$candidate_ids,$state,$max_products);
        if ($core_recovery) { $recovery['core_roles'] = $core_recovery; }
        [$selected,$recovery_rejections] = $this->validator->validate($selected,$state);
        $rejections = array_values(array_merge($rejections,$recovery_rejections));
        $selected = $this->annotate_quantity_guidance($selected,$state);
        usort($selected,fn($first,$second)=>$this->priority($first)<=>$this->priority($second)); $selected_before_limit = count($selected);
        $displayed = array_slice($selected,0,$max_products);
        [$displayed,$display_rejections] = $this->validator->validate($displayed,$state); $rejections = array_values(array_merge($rejections,$display_rejections));
        $displayed_roles = array(); foreach ($displayed as $product) { foreach ((array)($product['logical_roles'] ?? array()) as $role) { if ($role !== '') { $displayed_roles[] = $role; } } }
        $missing = array(); foreach ($groups as $group) { if (empty($group['conditional']) && !in_array($group['role'],$displayed_roles,true)) { $missing[] = $group['role']; } }
        if (empty($result['ok'])) {
            $diagnostic = array('status'=>'selector_provider_error','error'=>sanitize_text_field((string)($result['error'] ?? '')),'catalogue_products'=>count($catalogue),'payload_chars'=>strlen((string)$payload_json),'deterministic_recovery'=>$recovery,'quantity_guidance'=>$this->quantity_diagnostics($displayed));
        } elseif (!is_array($data)) {
            $diagnostic = array('status'=>'invalid_selector_output','tool_calls'=>count($calls),'content_excerpt'=>mb_substr(sanitize_textarea_field((string)($result['message']['content'] ?? '')),0,500),'usage'=>$result['usage'] ?? array(),'attempts'=>$result['attempts'] ?? array(),'catalogue_products'=>count($catalogue),'payload_chars'=>strlen((string)$payload_json),'deterministic_recovery'=>$recovery,'quantity_guidance'=>$this->quantity_diagnostics($displayed));
        } else {
            $diagnostic = array('status'=>'ok','usage'=>$result['usage'] ?? array(),'attempts'=>$result['attempts'] ?? array(),'fallback_used'=>!empty($result['fallback_used']),
                'catalogue_products'=>count($catalogue),'role_candidates'=>array_sum(array_map('count',array_column($roles_payload,'candidate_ids'))),
                'payload_chars'=>strlen((string)$payload_json),'selected_before_card_limit'=>$selected_before_limit,'displayed_products'=>count($displayed),
                'roller_system_complete'=>in_array('roller',$displayed_roles,true),'selection_rejections'=>$rejections,'deterministic_recovery'=>$recovery,
                'quantity_guidance'=>$this->quantity_diagnostics($displayed));
        }
        return array('products'=>$displayed,'missing_roles'=>array_values(array_unique($missing)),'conditional_roles'=>array_values(array_unique($conditional_roles)),'plan'=>$groups,'diagnostic'=>$diagnostic);
    }

    private function normalise_plan(array $plan,array $state): array {
        $out = array(); $seen = array();
        foreach ($plan as $item) {
            if (!is_array($item)) { continue; }
            $query = sanitize_text_field((string)($item['query'] ?? ''));
            $role = Orion_Role_Registry::canonical_for_query((string)($item['role'] ?? ''), $query);
            if (!Orion_Role_Registry::supported($role) || isset($seen[$role])) { continue; }
            if ('dust_sheet' === $role && Orion_Routing_Rules::is_floor_project($state)) { continue; }
            $item['role'] = $role; $item['query'] = $query; $out[] = $item; $seen[$role] = true;
        }
        $complete = Orion_Routing_Rules::wants_complete_kit($state) || count($out) >= 6;
        if ($complete && (isset($seen['primary_coating']) || isset($seen['primary_product']))) {
            $surface = sanitize_text_field((string)($state['surface'] ?? 'painted surface'));
            $core = array('roller'=>'complete paint roller system with frame and sleeve for '.$surface,'tray'=>'paint roller tray compatible with the selected roller','brush'=>'paint brush for cutting in '.$surface,'masking_tape'=>'decorators masking tape for protecting edges');
            if (Orion_Routing_Rules::is_floor_project($state)) { $core['cleaner'] = 'floor cleaner or degreaser for coating preparation'; }
            else { $core['dust_sheet'] = 'protective dust sheet for painting'; }
            foreach ($core as $role => $query) { if (!isset($seen[$role])) { $out[] = array('role'=>$role,'query'=>$query,'required'=>false,'requirements'=>array('surface'=>$surface)); $seen[$role] = true; } }
        }
        usort($out,static fn($first,$second)=>Orion_Role_Registry::priority((string)($first['role'] ?? ''))<=>Orion_Role_Registry::priority((string)($second['role'] ?? '')));
        return array_slice($out,0,10);
    }

    private function search_candidates(array $item,string $role,array $state,int $limit): array {
        $query = sanitize_text_field((string)($item['query'] ?? '')); $requirements = is_array($item['requirements'] ?? null) ? $item['requirements'] : array(); $queries = array($query);
        if ('roller' === $role) { $surface = sanitize_text_field((string)($state['surface'] ?? '')); $queries[] = 'complete paint roller set with frame and sleeve '.$surface; $queries[] = 'paint roller frame '.$surface; $queries[] = 'paint roller sleeve '.$surface; }
        $found = array();
        foreach (array_unique(array_filter($queries)) as $candidate_query) { foreach ($this->products->search(array('query'=>$candidate_query,'role'=>$role,'requirements'=>$requirements,'in_stock'=>true,'limit'=>$limit)) as $product) {
            $id = (int)($product['id'] ?? 0); if ($id && !isset($found[$id]) && $this->candidate_allowed($product,$role,$state)) { $found[$id] = $product; }
        } }
        if ('roller' === $role) { uasort($found,function($first,$second): int {
            $first_meta = $this->facts->roller_component($first); $second_meta = $this->facts->roller_component($second); $weight = array('complete'=>300,'frame'=>200,'sleeve'=>190,'unknown'=>0);
            return (($weight[$second_meta['kind']] ?? 0)+(int)($second['retrieval']['score'] ?? 0)) <=> (($weight[$first_meta['kind']] ?? 0)+(int)($first['retrieval']['score'] ?? 0));
        }); }
        return array_slice(array_values($found),0,$limit);
    }

    private function candidate_allowed(array $product,string $role,array $state): bool {
        $facts = $this->facts->extract($product); $functions = $facts['functions']; $identity = strtolower((string)($product['name'] ?? '').' '.implode(' ',(array)($product['categories'] ?? array())));
        if ('primary_coating' === $role) { return in_array('primary_coating',$functions,true) && !preg_match('/\b(brush(?:es)?|roller|sleeve|frame|tray|plasterboard|board|tile|carpet|adhesive|filler)\b/',$identity); }
        if (in_array($role,array('cleaner','brush','tray','masking_tape','dust_sheet'),true) && !in_array($role,$functions,true)) { return false; }
        if ('roller' !== $role) { return true; }
        $component = $this->facts->roller_component($product); if ('unknown' === $component['kind']) { return false; }
        $text = $this->facts->text($product); $area = (float)($state['area_m2'] ?? 0);
        if ($area >= 10 && (($component['width'] > 0 && $component['width'] <= 4.5) || preg_match('/\b(mini|radiator)\b/',$text))) { return false; }
        if (Orion_Routing_Rules::is_floor_project($state) && preg_match('/\b(gloss|emulsion|wall|ceiling|radiator|mini)\b/',$text) && !preg_match('/\b(floor|garage|epoxy|floor coating|heavy duty coating)\b/',$text)) { return false; }
        return true;
    }

    private function recover_roller_system(array $selected,array $candidate_ids,array $state): array {
        foreach ($selected as $product) { if (in_array('roller',(array)($product['logical_roles'] ?? array()),true)) { return array($selected,array()); } }
        $complete = array(); $frames = array(); $sleeves = array();
        foreach (array_keys((array)($candidate_ids['roller'] ?? array())) as $id) {
            $product = $this->products->get_product((int)$id);
            if (!$product || empty($product['in_stock']) || !$this->candidate_allowed($product,'roller',$state)) { continue; }
            $meta = $this->facts->roller_component($product); $entry = array('product'=>$product,'width'=>(float)$meta['width']);
            if ('complete' === $meta['kind']) { $complete[] = $entry; }
            elseif ('frame' === $meta['kind']) { $frames[] = $entry; }
            elseif ('sleeve' === $meta['kind']) { $sleeves[] = $entry; }
        }
        if ($complete) {
            $id = (int)($complete[0]['product']['id'] ?? 0);
            foreach ($selected as $index => $product) {
                if ((int)($product['id'] ?? 0) === $id && $this->facts->is_bundle($product)) { $this->add_role($selected[$index],'roller'); return array($selected,array('roller_complete_set'=>$id)); }
            }
            $product = $this->decorate_selection($complete[0]['product'],'roller'); $product['recommendation_reason'] = 'Selected by deterministic complete roller-system recovery.'; $selected[] = $product;
            return array($selected,array('roller_complete_set'=>$id));
        }
        foreach ($frames as $frame) { foreach ($sleeves as $sleeve) {
            if ($frame['width'] <= 0 || $sleeve['width'] <= 0 || abs($frame['width']-$sleeve['width']) > 0.15) { continue; }
            $frame_product = $this->decorate_selection($frame['product'],'roller'); $frame_product['recommendation_reason'] = 'Selected as the compatible roller frame.';
            $sleeve_product = $this->decorate_selection($sleeve['product'],'roller'); $sleeve_product['recommendation_reason'] = 'Selected as the width-compatible roller sleeve.';
            $selected[] = $frame_product; $selected[] = $sleeve_product;
            return array($selected,array('roller_pair'=>array((int)($frame_product['id'] ?? 0),(int)($sleeve_product['id'] ?? 0))));
        } }
        return array($selected,array());
    }

    private function recover_core_roles(array $selected,array $candidate_ids,array $state,int $max_products): array {
        $recovered = array(); $roles = array('tray','brush','cleaner','masking_tape','dust_sheet');
        foreach ($roles as $role) {
            $has_role = false; foreach ($selected as $product) { if (in_array($role,(array)($product['logical_roles'] ?? array()),true)) { $has_role = true; break; } }
            if ($has_role || empty($candidate_ids[$role])) { continue; }
            foreach (array_keys((array)$candidate_ids[$role]) as $id) {
                $product = $this->products->get_product((int)$id);
                if (!$product || empty($product['in_stock']) || !$this->candidate_allowed($product,$role,$state)) { continue; }
                $existing = null; foreach ($selected as $index => $item) { if ((int)($item['id'] ?? 0) === (int)$id) { $existing = $index; break; } }
                if (null !== $existing) {
                    if (!$this->facts->is_bundle($product)) { continue; }
                    $this->add_role($selected[$existing],$role); $recovered[$role] = (int)$id; break;
                }
                if (count($selected) >= $max_products) { break; }
                $product = $this->decorate_selection($product,$role); $product['recommendation_reason'] = 'Added from a verified catalogue candidate to complete the requested core kit.';
                $selected[] = $product; $recovered[$role] = (int)$id; break;
            }
        }
        return array($selected,$recovered);
    }

    private function annotate_quantity_guidance(array $selected,array $state): array {
        $area = (float)($state['area_m2'] ?? 0);
        foreach ($selected as $index => $product) {
            if (!in_array('primary_coating',(array)($product['logical_roles'] ?? array()),true)) { continue; }
            $facts = is_array($product['orion_facts'] ?? null) ? $product['orion_facts'] : $this->facts->extract($product);
            $coverage = isset($facts['coverage_m2_per_litre']) ? (float)$facts['coverage_m2_per_litre'] : 0.0;
            $volume = isset($facts['pack_volume_litres']) ? (float)$facts['pack_volume_litres'] : 0.0;
            $status = 'not_calculable'; $reason = 'Project quantity was not calculated because verified manufacturer coverage, coat count or pack-volume evidence is incomplete.';
            if ($area <= 0) { $reason = 'Project area is not available, so quantity was not calculated.'; }
            elseif ($coverage > 0 && $volume > 0) { $status = 'evidence_present_review_required'; $reason = 'Coverage and pack volume are explicit, but coat count and manufacturer instructions must be confirmed before calculating packs.'; }
            $selected[$index]['quantity_guidance'] = array('status'=>$status,'can_calculate'=>false,'area_m2'=>$area>0?$area:null,'coverage_m2_per_litre'=>$coverage>0?$coverage:null,'pack_volume_litres'=>$volume>0?$volume:null,'reason'=>$reason);
        }
        return $selected;
    }

    private function quantity_diagnostics(array $products): array {
        $out = array(); foreach ($products as $product) { if (!empty($product['quantity_guidance'])) { $out[] = array('id'=>(int)($product['id'] ?? 0),'guidance'=>$product['quantity_guidance']); } }
        return $out;
    }

    private function decorate_selection(array $product,string $role): array {
        $facts = $this->facts->extract($product); $display = $role;
        if ('roller' === $role) { $display = array('frame'=>'roller_frame','sleeve'=>'roller_sleeve','complete'=>'roller')[$facts['roller_component']] ?? 'roller'; }
        $product['kit_role'] = $display; $product['kit_roles'] = array($display); $product['logical_roles'] = array($role); $product['orion_facts'] = $facts;
        if ('roller' === $role) { $product['roller_component'] = $facts['roller_component']; $product['roller_width_inches'] = $facts['roller_width_inches']; }
        if ('tray' === $role) { $product['tray_width_inches'] = $facts['tray_width_inches']; }
        return $product;
    }
    private function add_role(array &$product,string $role): void {
        $display = $role; if ('roller' === $role) { $kind = $this->facts->roller_component($product)['kind']; $display = array('frame'=>'roller_frame','sleeve'=>'roller_sleeve','complete'=>'roller')[$kind] ?? 'roller'; }
        if (!in_array($display,(array)($product['kit_roles'] ?? array()),true)) { $product['kit_roles'][] = $display; }
        if (!in_array($role,(array)($product['logical_roles'] ?? array()),true)) { $product['logical_roles'][] = $role; }
        $product['kit_role'] = $product['kit_roles'][0] ?? $display;
    }
    private function defer_conditional(string $role,array $state,int $plan_count): bool {
        if ($plan_count <= 1 || !in_array($role,array('primer','cleaner','filler','sandpaper','scraper'),true)) { return false; }
        return !Orion_Routing_Rules::preparation_role_needed($role,$state);
    }
    private function priority(array $product): int { $values = array_map(static fn($role)=>Orion_Role_Registry::priority((string)$role),(array)($product['kit_roles'] ?? array($product['kit_role'] ?? ''))); return $values ? min($values) : 80; }
    private function empty_result(string $status): array { return array('products'=>array(),'missing_roles'=>array(),'conditional_roles'=>array(),'plan'=>array(),'diagnostic'=>array('status'=>$status)); }
    private function decode(string $text): ?array {
        $text = trim((string)preg_replace('/^```(?:json)?\s*|\s*```$/i','',trim($text))); $data = json_decode($text,true); if (is_array($data)) { return $data; }
        if (preg_match('/\{[\s\S]*\}/',$text,$matches)) { $data = json_decode($matches[0],true); return is_array($data) ? $data : null; }
        return null;
    }
}
