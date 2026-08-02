<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Orion_Project_Planner {
    private Orion_Product_Search $products;
    public function __construct( Orion_Product_Search $products ) { $this->products=$products; }

    public function analyse( string $message, array $history ): array {
        $current=mb_strtolower(trim($message));$type=$this->detect_type($current);$is_new=''!==$type;
        if(!$is_new&&$this->looks_like_continuation($current)){
            foreach(array_reverse($history)as$item){if(($item['role']??'')!=='user')continue;$type=$this->detect_type(mb_strtolower((string)$item['content']));if($type!=='')break;}
        }
        if(''===$type)return array('type'=>'','questions'=>array(),'area_m2'=>null,'surface'=>'','colour'=>'','finish'=>'','is_project'=>false);
        $context=$current;
        if(!$is_new)foreach(array_reverse($history)as$item){if(($item['role']??'')!=='user')continue;$context.="\n".mb_strtolower((string)$item['content']);if($this->detect_type(mb_strtolower((string)$item['content']))!=='')break;}
        $area=$this->area($context);$ambiguous=$area===null&&(bool)preg_match('/\b\d+(?:[\.,]\d+)?\s*m\b(?!\s*[²2])/iu',$context);
        $surface=$this->surface($context);$colour=$this->colour($context);$finish=$this->finish($context,$type);$questions=array();
        if($area===null)$questions[]=$ambiguous?'Do you mean an area in m², or a length in metres? If it is a length, what is the height?':'What is the approximate area in m²? You can also give dimensions such as 5 m × 2 m.';
        if('exterior_wall_painting'===$type&&''===$surface)$questions[]='Is the wall brick, render, concrete, or previously painted?';
        if('floor_painting'===$type&&''===$surface)$questions[]='Is the floor concrete, wood, tiles, or another material?';
        if('ceiling'===$type&&''===$finish)$questions[]='Will the ceiling be painted or finished with panels?';
        return array('type'=>$type,'questions'=>$questions,'area_m2'=>$area,'surface'=>$surface,'colour'=>$colour,'finish'=>$finish,'is_project'=>true,'is_new'=>$is_new);
    }

    public function recommend( array $project, int $max_products ): array {
        $roles=$this->roles($project);$products=array();$missing=array();$max_products=max(1,min(12,$max_products));
        foreach($roles as$role=>$query){if(count($products)>=$max_products)break;$found=$this->products->search(array('query'=>$query,'in_stock'=>true,'limit'=>3));$selected=null;foreach($found as$item){if($this->suitable($item,$role,$project)){$selected=$item;break;}}
            if($selected){$selected['kit_role']=$role;$selected['recommendation_reason']=$this->role_label($role);$products[]=$selected;}else{$missing[]=$this->role_label($role);}
        }
        return array('products'=>$products,'missing_roles'=>$missing,'roles'=>array_map(array($this,'role_label'),array_keys($roles)));
    }

    private function detect_type(string $text):string{
        if(preg_match('/\b(outside|exterior|external|facade|masonry)\b/iu',$text)&&preg_match('/\b(wall|walls|house|paint|painting)\b/iu',$text))return'exterior_wall_painting';
        if(preg_match('/\b(floor|floors|flooring)\b/iu',$text)&&preg_match('/\b(paint|painting|coat|coating)\b/iu',$text))return'floor_painting';
        if(preg_match('/\b(ceiling|ceilings)\b|потол/iu',$text))return'ceiling';
        return'';
    }
    private function looks_like_continuation(string $text):bool{return mb_strlen($text)<140||preg_match('/\b(m2|m²|sqm|square|painted|panels?|finished|beige|white|grey|gray|concrete|wood|brick|render)\b/iu',$text);}
    private function area(string $text):?float{
        if(preg_match('/(?:^|\s)(\d+(?:[\.,]\d+)?)\s*(?:m²|m2|sqm|square\s*met(?:er|re)s?)(?=\s|[,.;:!?]|$)/iu',$text,$m))return(float)str_replace(',','.',$m[1]);
        if(preg_match('/\b(\d+(?:[\.,]\d+)?)\s*m(?:et(?:er|re)s?)?\s*(?:x|×|by)\s*(\d+(?:[\.,]\d+)?)\s*m(?:et(?:er|re)s?)?\b/iu',$text,$m))return round((float)str_replace(',','.',$m[1])*(float)str_replace(',','.',$m[2]),2);
        return null;
    }
    private function surface(string $text):string{foreach(array('concrete','wood','timber','brick','render','plaster','tiles','metal')as$value)if(str_contains($text,$value))return$value;return preg_match('/previously\s+painted/iu',$text)?'previously_painted':'';}
    private function colour(string $text):string{foreach(array('beige','white','magnolia','grey','gray','black','blue','green','red','brown','cream')as$value)if(preg_match('/\b'.preg_quote($value,'/').'\b/iu',$text))return$value;return'';}
    private function finish(string $text,string $type):string{if('floor_painting'===$type||'exterior_wall_painting'===$type)return'paint';if(preg_match('/\b(paint|painted|painting)\b/iu',$text))return'paint';if(preg_match('/\b(panel|panels|panelled|paneled|finished)\b/iu',$text))return'panels';return'';}
    private function roles(array $p):array{$colour=$p['colour']?(' '.$p['colour']):'';$surface=$p['surface']?(' '.$p['surface']):'';
        if('floor_painting'===$p['type'])return array('main_paint'=>'floor paint'.$surface.$colour,'primer'=>'floor primer'.$surface,'roller'=>'paint roller frame floor','sleeve'=>'roller sleeve floor paint','brush'=>'paint brush cutting in','tray'=>'paint tray','repair'=>'floor repair filler'.$surface,'cleaner'=>'floor cleaner degreaser','masking'=>'masking tape protective sheet');
        if('exterior_wall_painting'===$p['type'])return array('main_paint'=>'exterior masonry paint'.$surface.$colour,'primer'=>'exterior masonry primer stabilising solution'.$surface,'roller'=>'masonry roller frame','sleeve'=>'masonry roller sleeve','brush'=>'masonry paint brush','tray'=>'paint scuttle tray','repair'=>'exterior masonry filler'.$surface,'cleaner'=>'exterior masonry wall cleaner','masking'=>'exterior masking protective sheet');
        if('panels'===($p['finish']??''))return array('main_material'=>'ceiling panels','fixings'=>'ceiling panel compatible fixings','trim'=>'ceiling panel trim profile','tools'=>'panel cutting tool','protection'=>'safety glasses gloves');
        return array('repair'=>'ceiling filler','primer'=>'ceiling primer','main_paint'=>'ceiling paint'.$colour,'roller'=>'paint roller frame','sleeve'=>'roller sleeve ceiling','brush'=>'paint brush cutting in','tray'=>'paint tray','masking'=>'masking tape dust sheet');
    }
    private function suitable(array $item,string $role,array $p):bool{$hay=mb_strtolower(($item['name']??'').' '.($item['short_description']??'').' '.implode(' ',$item['categories']??array()).' '.wp_json_encode($item['attributes']??array()));if('main_paint'===$role&&'exterior_wall_painting'===$p['type']&&!preg_match('/exterior|external|masonry|facade/iu',$hay))return false;if('main_paint'===$role&&'floor_painting'===$p['type']&&!preg_match('/floor|garage|concrete paint|floor coating/iu',$hay))return false;return true;}
    private function role_label(string $role):string{return ucwords(str_replace('_',' ',$role));}
}
