<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Orion_Semantic_Product_Planner {
    private Orion_Product_Search $products;
    public function __construct( Orion_Product_Search $products ){$this->products=$products;}
    public function recommend(array $classification,array $state,array $settings):array{
        $plan=$classification['search_plan']??array();if(!$plan)return array('products'=>array(),'missing_roles'=>array(),'plan'=>array());$groups=array();$candidate_ids=array();
        foreach(array_slice($plan,0,10)as$item){$role=sanitize_key($item['role']??'');if($role==='')continue;$found=$this->products->search(array('query'=>$item['query'],'in_stock'=>true,'limit'=>5));$compact=array();foreach($found as$product){$id=(int)($product['id']??0);if(!$id)continue;$candidate_ids[$role][$id]=true;$compact[]=array('id'=>$id,'name'=>$product['name'],'type'=>$product['type'],'price'=>$product['price_text'],'categories'=>$product['categories'],'attributes'=>$product['attributes'],'description'=>$product['short_description'],'stock'=>$product['stock_status']);}$groups[]=array('role'=>$role,'required'=>!empty($item['required']),'requirements'=>$item['requirements']??array(),'candidates'=>$compact);}
        $provider=Orion_AI_Provider_Factory::create($settings);$prompt='Select products only from the supplied candidate IDs. Match each product semantically to its role and requirements. Never reuse one product for multiple roles. Reject candidates whose description or attributes do not confirm suitability. Return JSON only: {"selections":[{"role":"...","product_id":123,"reason":"..."}],"missing_roles":["..."]}.';
        $result=$provider->chat(array(array('role'=>'system','content'=>$prompt),array('role'=>'user','content'=>wp_json_encode(array('state'=>$state,'groups'=>$groups)))));$data=!empty($result['ok'])?$this->decode((string)($result['message']['content']??'')):null;$selected=array();$used=array();$missing=array();
        if(is_array($data)){foreach(($data['selections']??array())as$choice){$role=sanitize_key((string)($choice['role']??''));$id=absint($choice['product_id']??0);if(!$role||!$id||empty($candidate_ids[$role][$id])||isset($used[$id]))continue;$product=$this->products->get_product($id);if(!$product||empty($product['in_stock']))continue;$used[$id]=true;$product['kit_role']=$role;$product['recommendation_reason']=sanitize_text_field((string)($choice['reason']??''));$selected[]=$product;}foreach(($data['missing_roles']??array())as$role)$missing[]=sanitize_key((string)$role);}
        $selected_roles=array_column($selected,'kit_role');foreach($groups as$group)if(!in_array($group['role'],$selected_roles,true))$missing[]=$group['role'];
        return array('products'=>array_slice($selected,0,(int)$settings['max_products']),'missing_roles'=>array_values(array_unique($missing)),'plan'=>$groups);
    }
    private function decode(string $text):?array{$text=trim(preg_replace('/^```(?:json)?\s*|\s*```$/i','',trim($text)));$data=json_decode($text,true);if(is_array($data))return$data;if(preg_match('/\{[\s\S]*\}/',$text,$m)){$data=json_decode($m[0],true);return is_array($data)?$data:null;}return null;}
}
