<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Orion_Product_Search {
    public function search( array|string $request, int $legacy_limit = 6 ): array {
        if ( ! function_exists( 'wc_get_products' ) ) return array();
        $args = is_array( $request ) ? $request : array( 'query'=>$request, 'limit'=>$legacy_limit );
        $query=sanitize_text_field((string)($args['query']??''));$category=sanitize_text_field((string)($args['category']??''));$limit=max(1,min(8,absint($args['limit']??6)));
        $in_stock=!isset($args['in_stock'])||filter_var($args['in_stock'],FILTER_VALIDATE_BOOLEAN);$sort=in_array($args['sort']??'relevance',array('relevance','price_asc','price_desc'),true)?$args['sort']:'relevance';
        $query_args=array('status'=>'publish','visibility'=>'visible','limit'=>$limit,'return'=>'ids','search'=>$query);
        if($in_stock)$query_args['stock_status']=array('instock','onbackorder');
        if(isset($args['min_price'])&&is_numeric($args['min_price']))$query_args['min_price']=max(0,(float)$args['min_price']);
        if(isset($args['max_price'])&&is_numeric($args['max_price']))$query_args['max_price']=max(0,(float)$args['max_price']);
        if('relevance'!==$sort){$query_args['orderby']='price';$query_args['order']='price_desc'===$sort?'DESC':'ASC';}
        $slugs=$this->category_slugs($category);if($slugs)$query_args['category']=$slugs;
        $ids=array_map('absint',wc_get_products($query_args));
        if(!$ids&&$slugs){unset($query_args['category']);$ids=array_map('absint',wc_get_products($query_args));}
        if(!$ids&&$query!=='')foreach(array_slice($this->keywords($query),0,5)as$token){$query_args['search']=$token;$ids=array_values(array_unique(array_merge($ids,array_map('absint',wc_get_products($query_args)))));if(count($ids)>=$limit)break;}
        if(!$ids&&$query!==''){$sku=wc_get_product_id_by_sku($query);if($sku)$ids[]=(int)$sku;}
        $out=array();foreach(array_slice($ids,0,$limit)as$id){$item=$this->get_product($id);if($item&&(!$in_stock||$item['in_stock']))$out[]=$item;}return$out;
    }
    public function get_product(int $id):?array{if(!function_exists('wc_get_product')||$id<1)return null;try{$p=wc_get_product($id);return(!$p||!$p->is_visible())?null:$this->format($p);}catch(Throwable $e){error_log('Orion AI product lookup failed for '.absint($id));return null;}}
    private function category_slugs(string $text):array{if($text==='')return array();$out=array();foreach(array_merge(array($text),array_slice($this->keywords($text),0,4))as$term){$found=get_terms(array('taxonomy'=>'product_cat','hide_empty'=>true,'search'=>$term,'number'=>4));if(!is_wp_error($found))foreach($found as$item)$out[]=$item->slug;}return array_values(array_unique($out));}
    private function keywords(string $text):array{$stop=array('what','which','with','that','this','from','have','need','show','find','looking','please','would','could','product','products','for','the','and','are','you','your','some','best');$tokens=preg_split('/[^a-z0-9-]+/i',strtolower($text))?:array();return array_values(array_unique(array_filter($tokens,static fn($t)=>strlen($t)>2&&!in_array($t,$stop,true))));}
    private function format(WC_Product $p):array{
        $attrs=array();foreach(array_slice($p->get_attributes(),0,8)as$a){if($a->is_taxonomy()){$values=wc_get_product_terms($p->get_id(),$a->get_name(),array('fields'=>'names'));$label=wc_attribute_label($a->get_name());}else{$values=$a->get_options();$label=$a->get_name();}if(!is_wp_error($values)&&$values)$attrs[$label]=array_values(array_map('strval',$values));}
        $cats=wp_get_post_terms($p->get_id(),'product_cat',array('fields'=>'names'));if(is_wp_error($cats))$cats=array();$html=$p->get_price_html();
        $item=array('id'=>$p->get_id(),'sku'=>$p->get_sku(),'name'=>$p->get_name(),'type'=>$p->get_type(),'url'=>$p->get_permalink(),'image'=>wp_get_attachment_image_url($p->get_image_id(),'woocommerce_thumbnail')?:wc_placeholder_img_src('woocommerce_thumbnail'),'price_text'=>trim(wp_strip_all_tags($html)),'price'=>$p->get_price(),'in_stock'=>$p->is_in_stock(),'stock_status'=>$p->get_stock_status(),'short_description'=>mb_substr(wp_strip_all_tags($p->get_short_description()),0,500),'categories'=>array_values($cats),'attributes'=>$attrs,'can_add_to_cart'=>$p->is_type('simple')&&$p->is_purchasable()&&$p->is_in_stock());
        if($p->is_type('variable')){$item['variation_attributes']=$p->get_variation_attributes();$item['variation_count']=count($p->get_children());}return$item;
    }
}
