<?php
if(!defined('ABSPATH')){exit;}

final class Orion_Catalogue_Audit{
 public function run(int $limit=0):array{
  if(!function_exists('wc_get_products'))return array('summary'=>array('error'=>'WooCommerce is not active.'),'issue_counts'=>array(),'severity_counts'=>array(),'issues'=>array());
  $ids=array_map('absint',wc_get_products(array('status'=>'publish','return'=>'ids','limit'=>$limit>0?min(5000,$limit):-1,'orderby'=>'ID','order'=>'ASC')));
  $issues=array();$sku_map=array();$malformed_sku_map=array();$title_map=array();$variable=0;$variations=0;
  foreach($ids as$id){
   $p=wc_get_product($id);if(!$p)continue;
   $name=$p->get_name();$short=trim(wp_strip_all_tags($p->get_short_description()));$description=trim(wp_strip_all_tags($p->get_description()));$cats=$this->categories($id);$attrs=$this->attributes($p);
   $identity=strtolower(html_entity_decode($name.' '.implode(' ',$cats),ENT_QUOTES|ENT_HTML5,'UTF-8'));
   $text=strtolower(html_entity_decode($identity.' '.$short.' '.$description.' '.wp_json_encode($attrs),ENT_QUOTES|ENT_HTML5,'UTF-8'));
   $title_key=$this->normalise_title($name);if($title_key!=='')$title_map[$title_key][]=$id;
   $sku=trim((string)$p->get_sku());
   if($sku==='')$this->issue($issues,$id,$name,$p->is_type('variable')?'missing_parent_sku':'missing_sku',$p->is_type('variable')?'info':'warning',$p->is_type('variable')?'Variable parent has no SKU; verify that every variation has a unique SKU.':'Product has no SKU.');
   else$this->collect_sku($sku_map,$malformed_sku_map,$sku,$id);
   if($short===''&&$description==='')$this->issue($issues,$id,$name,'missing_description','critical','Product has no description for customer or AI suitability checks.');
   elseif(mb_strlen($short.' '.$description)<80)$this->issue($issues,$id,$name,'short_description','warning','Combined description is under 80 characters.');
   if(!array_filter($cats,static fn($v)=>strtolower((string)$v)!=='uncategorized'))$this->issue($issues,$id,$name,'missing_category','critical','Product has no useful category.');
   if(!$p->get_image_id())$this->issue($issues,$id,$name,'missing_image','warning','Product has no featured image.');
   if($p->is_type('variable')){
    $variable++;$children=$p->get_children();
    if(!$children)$this->issue($issues,$id,$name,'broken_variable_product','critical','Variable product has no variations and cannot provide a purchasable price or option.');
    else foreach($children as$child_id){
     $v=wc_get_product($child_id);if(!$v)continue;$variations++;
     if($v->get_price()==='')$this->issue($issues,$id,$name,'variation_missing_price','critical','Variation #'.$child_id.' has no price.',$child_id);
     $vsku=trim((string)$v->get_sku());
     if($vsku==='')$this->issue($issues,$id,$name,'variation_missing_sku','warning','Variation #'.$child_id.' has no SKU.',$child_id);
     else$this->collect_sku($sku_map,$malformed_sku_map,$vsku,$child_id);
     $vattrs=$v->get_variation_attributes();if(!$vattrs||!array_filter($vattrs,static fn($value)=>trim((string)$value)!==''))$this->issue($issues,$id,$name,'variation_missing_attributes','warning','Variation #'.$child_id.' has no selected attributes.',$child_id);
    }
   }elseif($p->get_price()==='')$this->issue($issues,$id,$name,'missing_price','critical','Simple product has no active price.');
   if($this->is_application_material($identity)&&!$this->is_accessory_or_hardware($identity)&&!preg_match('/\b(floor|wall|ceiling|exterior|external|outdoor|interior|internal|masonry|concrete|wood|timber|metal|plaster|brick|roof|tile|bathroom|kitchen|garage|multi[- ]?surface|all surface|universal)\b/',$text))$this->issue($issues,$id,$name,'suitability_unknown','warning','Material description does not establish a surface or use environment.');
   if(preg_match('/\b(floor|garage|epoxy)\b/',$text)&&preg_match('/walls? and ceilings?|walls? only|ceilings? only/',$text)&&!preg_match('/suitable for[^.]{0,100}\bfloor/',$text))$this->issue($issues,$id,$name,'suitability_conflict','critical','Floor-related product text conflicts with wall/ceiling-only suitability.');
  }
  $duplicate_sku_groups=0;
  foreach($sku_map as$sku=>$entity_ids){
   $entity_ids=array_values(array_unique(array_map('absint',$entity_ids)));if(count($entity_ids)<2)continue;$duplicate_sku_groups++;
   $parent_ids=$this->parent_product_ids($entity_ids);$representative=(int)($parent_ids[0]??$entity_ids[0]);$p=wc_get_product($representative);
   $this->issue($issues,$representative,$p?$p->get_name():'','duplicate_sku','critical','SKU "'.$sku.'" is used by IDs '.implode(', ',$entity_ids).'.',0,array('group_key'=>$sku,'entity_ids'=>$entity_ids,'affected_product_ids'=>$parent_ids));
  }
  $malformed_sku_groups=0;
  foreach($malformed_sku_map as$sku=>$entity_ids){
   $entity_ids=array_values(array_unique(array_map('absint',$entity_ids)));$malformed_sku_groups++;
   $parent_ids=$this->parent_product_ids($entity_ids);$representative=(int)($parent_ids[0]??$entity_ids[0]);$p=wc_get_product($representative);
   $this->issue($issues,$representative,$p?$p->get_name():'','malformed_sku','warning','SKU "'.$sku.'" looks like multiple values were concatenated during import; affected IDs: '.implode(', ',$entity_ids).'.',0,array('group_key'=>$sku,'entity_ids'=>$entity_ids,'affected_product_ids'=>$parent_ids));
  }
  $duplicate_title_groups=0;
  foreach($title_map as$title=>$product_ids){
   $product_ids=array_values(array_unique(array_map('absint',$product_ids)));if(count($product_ids)<2)continue;$duplicate_title_groups++;
   $representative=(int)$product_ids[0];$p=wc_get_product($representative);
   $this->issue($issues,$representative,$p?$p->get_name():'','duplicate_title','warning','Normalised title is used by product IDs '.implode(', ',$product_ids).'.',0,array('group_key'=>$title,'entity_ids'=>$product_ids,'affected_product_ids'=>$product_ids));
  }
  $counts=array_count_values(array_column($issues,'code'));ksort($counts);
  $severity=array_count_values(array_column($issues,'severity'));foreach(array('critical','warning','info')as$level)if(!isset($severity[$level]))$severity[$level]=0;
  $affected_by=array();$all_affected=array();
  foreach($issues as$issue)foreach($this->affected_ids($issue)as$product_id){$affected_by[$issue['severity']][$product_id]=true;$all_affected[$product_id]=true;}
  $critical_products=count($affected_by['critical']??array());$warning_products=count($affected_by['warning']??array());$info_products=count($affected_by['info']??array());$affected=count($all_affected);
  $ai_codes=array('missing_description','short_description','missing_category','suitability_unknown','suitability_conflict','broken_variable_product','missing_price','variation_missing_price');$ai_affected=array();
  foreach($issues as$issue)if(in_array($issue['code'],$ai_codes,true))foreach($this->affected_ids($issue)as$product_id)$ai_affected[$product_id]=true;
  $total=count($ids);
  return array(
   'summary'=>array('products_checked'=>$total,'variable_products'=>$variable,'variations_checked'=>$variations,'products_with_issues'=>$affected,'critical_products'=>$critical_products,'warning_products'=>$warning_products,'info_products'=>$info_products,'duplicate_sku_groups'=>$duplicate_sku_groups,'duplicate_title_groups'=>$duplicate_title_groups,'malformed_sku_groups'=>$malformed_sku_groups,'operational_ready_products'=>max(0,$total-$critical_products),'operational_readiness_percent'=>$total?round(($total-$critical_products)/$total*100,1):0,'ai_ready_products'=>max(0,$total-count($ai_affected)),'ai_readiness_percent'=>$total?round(($total-count($ai_affected))/$total*100,1):0,'total_issues'=>count($issues)),
   'issue_counts'=>$counts,'severity_counts'=>$severity,'issues'=>$issues
  );
 }
 private function issue(array &$issues,int $product_id,string $name,string $code,string $severity,string $message,int $variation_id=0,array $extra=array()):void{$issues[]=array_merge(array('product_id'=>$product_id,'variation_id'=>$variation_id,'product'=>$name,'code'=>$code,'severity'=>$severity,'message'=>$message),$extra);}
 private function collect_sku(array &$sku_map,array &$malformed_sku_map,string $sku,int $entity_id):void{$key=strtolower(trim($sku));$sku_map[$key][]=$entity_id;if($this->is_malformed_sku($sku))$malformed_sku_map[$key][]=$entity_id;}
 private function is_malformed_sku(string $sku):bool{return(bool)(preg_match('/\(\s*\)/',$sku)||preg_match('/\)\s+\(/',$sku)||substr_count($sku,'(')>1||substr_count($sku,')')>1);}
 private function parent_product_ids(array $entity_ids):array{$out=array();foreach($entity_ids as$entity_id){$parent=wp_get_post_parent_id((int)$entity_id);$out[]=$parent?:absint($entity_id);}return array_values(array_unique(array_map('absint',$out)));}
 private function affected_ids(array $issue):array{$ids=$issue['affected_product_ids']??array($issue['product_id']);return array_values(array_filter(array_unique(array_map('absint',(array)$ids))));}
 private function is_application_material(string $identity):bool{return(bool)preg_match('/\b(paint|primer|undercoat|coating|varnish|stain|sealer|sealant|silicone|caulk|filler|adhesive|mortar|render|plaster|grout|levelling compound|leveling compound)\b/',$identity);}
 private function is_accessory_or_hardware(string $identity):bool{return(bool)preg_match('/\b(roller|sleeve|frame|brush|tray|tape|dust sheet|carpet protection|surface protection|protection sheet|glove|ppe|coverall|screw|fixing|tool|scraper|wrench|spirit level|blade|bit|sandpaper|steel wool|cord|fire seal|weather strip|wallpaper stripper|storage system|vapour mate|roller set|painting pack|filler tap|mixer filler|mixer tap|bath mixer|basin mixer|body filler set|filler set)\b/',$identity);}
 private function normalise_title(string $title):string{$title=strtolower(html_entity_decode(wp_strip_all_tags($title),ENT_QUOTES|ENT_HTML5,'UTF-8'));$title=preg_replace('/[^a-z0-9]+/',' ',remove_accents($title));return trim((string)preg_replace('/\s+/',' ',$title));}
 private function categories(int $id):array{$cats=wp_get_post_terms($id,'product_cat',array('fields'=>'names'));return is_wp_error($cats)?array():array_values(array_map('strval',$cats));}
 private function attributes(WC_Product $p):array{$out=array();foreach($p->get_attributes()as$a){$name=$a->get_name();$values=$a->is_taxonomy()?wc_get_product_terms($p->get_id(),$name,array('fields'=>'names')):$a->get_options();$out[$name]=is_wp_error($values)?array():$values;}return$out;}
}
