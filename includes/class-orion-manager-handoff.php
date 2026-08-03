<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Orion_Manager_Handoff {
    private string $table;
    public function __construct(){global$wpdb;$this->table=$wpdb->prefix.'orion_ai_handoffs';}
    public function create(int $conversation_id,string $question,array $context):int{global$wpdb;$wpdb->insert($this->table,array('conversation_id'=>$conversation_id,'question'=>sanitize_textarea_field($question),'context_json'=>wp_json_encode($context),'status'=>'new','created_at'=>current_time('mysql',true),'updated_at'=>current_time('mysql',true)));return(int)$wpdb->insert_id;}
    public function all(int $limit=100):array{global$wpdb;$limit=max(1,min(500,$limit));return$wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table} ORDER BY id DESC LIMIT %d",$limit),ARRAY_A)?:array();}
}
