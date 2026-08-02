<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Orion_REST_Controller {
    private Orion_Chat_Orchestrator $chat; private Orion_Rate_Limiter $rate_limiter; private Orion_Conversation_Service $conversations;
    public function __construct($chat,$rate,$conversations){$this->chat=$chat;$this->rate_limiter=$rate;$this->conversations=$conversations;}
    public function register_routes():void{
        register_rest_route('orion-ai/v1','/chat',array('methods'=>WP_REST_Server::CREATABLE,'callback'=>array($this,'chat'),'permission_callback'=>array($this,'allow_public_request'),'args'=>array('message'=>array('required'=>true,'type'=>'string','sanitize_callback'=>'sanitize_textarea_field','validate_callback'=>array($this,'validate_message')),'session'=>array('required'=>false,'type'=>'string','sanitize_callback'=>'sanitize_text_field','validate_callback'=>array($this,'validate_session')))));
        register_rest_route('orion-ai/v1','/cart',array('methods'=>WP_REST_Server::CREATABLE,'callback'=>array($this,'cart'),'permission_callback'=>array($this,'allow_public_request'),'args'=>array('product_id'=>array('required'=>true,'type'=>'integer','sanitize_callback'=>'absint','validate_callback'=>static fn($v)=>absint($v)>0),'quantity'=>array('default'=>1,'type'=>'integer','sanitize_callback'=>'absint','validate_callback'=>static fn($v)=>absint($v)>=1&&absint($v)<=99))));
    }
    public function allow_public_request(WP_REST_Request $request):bool|WP_Error{
        $origin=(string)$request->get_header('origin');$host=strtolower((string)wp_parse_url(home_url('/'),PHP_URL_HOST));
        if($origin&&strtolower((string)wp_parse_url($origin,PHP_URL_HOST))!==$host)return new WP_Error('invalid_origin','This request origin is not allowed.',array('status'=>403));
        $length=(int)($_SERVER['CONTENT_LENGTH']??0);if($length>32768)return new WP_Error('request_too_large','The request is too large.',array('status'=>413));
        $this->ensure_visitor_cookie();return true;
    }
    public function chat(WP_REST_Request $request):WP_REST_Response|WP_Error{$session=(string)$request->get_param('session');if(''===$session)$session=(string)($_COOKIE['orion_ai_session']??'');$result=$this->chat->respond((string)$request->get_param('message'),$session);if(is_wp_error($result))return $result;$response=new WP_REST_Response($result,200);$this->set_cookie('orion_ai_session',(string)$result['session'],DAY_IN_SECONDS);return $response;}
    public function cart(WP_REST_Request $request):WP_REST_Response|WP_Error{
        $rate=$this->rate_limiter->check(Orion_AI_Settings::get(),'cart');if(is_wp_error($rate))return $rate;if(!class_exists('WooCommerce'))return new WP_Error('woocommerce_missing','WooCommerce is unavailable.',array('status'=>503));
        $id=absint($request->get_param('product_id'));$qty=max(1,min(99,absint($request->get_param('quantity')?:1)));$product=wc_get_product($id);
        if(!$product||!$product->is_type('simple')||!$product->is_visible()||!$product->is_purchasable()||!$product->is_in_stock())return new WP_Error('not_purchasable','Please choose options on the product page.',array('status'=>400));
        if($product->is_sold_individually())$qty=1;if($product->managing_stock()&&!$product->backorders_allowed()&&$product->get_stock_quantity()!==null)$qty=min($qty,max(0,(int)$product->get_stock_quantity()));if($qty<1)return new WP_Error('out_of_stock','This product is currently unavailable.',array('status'=>400));
        if(function_exists('wc_load_cart')&&!WC()->cart)wc_load_cart();if(!WC()->cart)return new WP_Error('cart_unavailable','The basket is unavailable.',array('status'=>503));$key=WC()->cart->add_to_cart($id,$qty);if(!$key)return new WP_Error('cart_error','The product could not be added to the basket.',array('status'=>400));
        $this->conversations->record_event('cart_added',array('product_id'=>$id,'quantity'=>$qty));return new WP_REST_Response(array('ok'=>true,'message'=>'Added to basket.','cartUrl'=>wc_get_cart_url(),'count'=>WC()->cart->get_cart_contents_count(),'fragments'=>apply_filters('woocommerce_add_to_cart_fragments',array())),200);
    }
    public function validate_message($v){$n=is_scalar($v)?mb_strlen(trim((string)$v)):0;return$n>0&&$n<=1200;}
    public function validate_session($v){return$v===null||$v===''||(is_scalar($v)&&(bool)preg_match('/^[a-f0-9-]{0,36}$/i',(string)$v));}
    private function ensure_visitor_cookie():void{if(get_current_user_id()||!empty($_COOKIE['orion_ai_visitor']))return;$id=wp_generate_uuid4();$_COOKIE['orion_ai_visitor']=$id;$this->set_cookie('orion_ai_visitor',$id,30*DAY_IN_SECONDS);}
    private function set_cookie(string $name,string $value,int $ttl):void{if(''===$value||headers_sent())return;setcookie($name,$value,array('expires'=>time()+$ttl,'path'=>COOKIEPATH?:'/','domain'=>COOKIE_DOMAIN,'secure'=>is_ssl(),'httponly'=>true,'samesite'=>'Lax'));}
}
