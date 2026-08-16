<?php
if (!defined('ABSPATH')) { exit; }

final class Orion_Vision_Review {
    public static function supported(array $result): bool {
        if ('openrouter' !== strtolower((string) ($result['provider'] ?? ''))) { return false; }
        $model = strtolower((string) ($result['model'] ?? ''));
        $markers = array('gpt-4o','gpt-4.1','gpt-5','claude-3','claude-4','sonnet-4','gemini','qwen-vl','llava','vision');
        $supported = false;
        foreach ($markers as $marker) { if (str_contains($model,$marker)) { $supported = true; break; } }
        if (!$supported) { return false; }
        return !function_exists('apply_filters') || (bool) apply_filters('orion_ai_enable_vision_review',true,$result);
    }

    public static function build(array $messages, array $selection, int $limit = 3): array {
        $missing = Orion_Selection_Policy::missing_needs($selection);
        if (!$missing || !function_exists('wc_get_product')) { return array('messages'=>$messages,'image_count'=>0,'candidate_ids'=>array()); }
        $payload = self::payload($messages);
        if (!$payload) { return array('messages'=>$messages,'image_count'=>0,'candidate_ids'=>array()); }
        $products = array();
        foreach ((array) ($payload['products'] ?? array()) as $product) { $id=(int)($product['id']??0); if($id)$products[$id]=$product; }
        $wanted = array();
        foreach ((array) ($payload['needs'] ?? array()) as $need) {
            $key=(string)($need['need_key']??''); if(!in_array($key,$missing,true))continue;
            foreach ((array)($need['candidate_ids']??array()) as $id) { $id=(int)$id; if($id&&!isset($wanted[$id]))$wanted[$id]=$key; }
        }
        $content = array(array('type'=>'text','text'=>'Optional visual review for unresolved product functions only. Images may confirm visible product type or visible components. They must not prove dimensions, capacity, coverage, hidden bundle contents, technical suitability or cross-product compatibility. Select a direct visible function match with medium confidence and keep every unverified constraint in uncertainty.'));
        $used = array(); $search = new Orion_Product_Search(); $limit=max(1,min(5,$limit));
        foreach ($wanted as $id=>$need_key) {
            $live=$search->get_product((int)$id); $url=(string)($live['image']??'');
            if(!$live||!filter_var($url,FILTER_VALIDATE_URL)||str_contains(strtolower($url),'placeholder'))continue;
            $name=(string)($products[$id]['name']??$live['name']??'');
            $content[]=array('type'=>'text','text'=>'Candidate '.$id.' for '.$need_key.': '.$name);
            $content[]=array('type'=>'image_url','image_url'=>array('url'=>$url));
            $used[]=(int)$id; if(count($used)>=$limit)break;
        }
        if(!$used)return array('messages'=>$messages,'image_count'=>0,'candidate_ids'=>array());
        $messages[]=array('role'=>'user','content'=>$content);
        return array('messages'=>$messages,'image_count'=>count($used),'candidate_ids'=>$used);
    }

    private static function payload(array $messages): ?array {
        for($index=count($messages)-1;$index>=0;$index--){$content=$messages[$index]['content']??null;if(!is_string($content))continue;$data=json_decode($content,true);if(is_array($data)&&isset($data['products'],$data['needs']))return$data;}
        return null;
    }
}
