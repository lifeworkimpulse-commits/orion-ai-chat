<?php
if(!defined('ABSPATH')){exit;}
final class Orion_Cleanup{
 public const HOOK='orion_ai_daily_cleanup';
 public static function schedule():void{if(!wp_next_scheduled(self::HOOK))wp_schedule_event(time()+HOUR_IN_SECONDS,'daily',self::HOOK);}
 public static function run():void{$settings=Orion_AI_Settings::get();$days=absint($settings['retention_days']??30);if($days<1)return;global$wpdb;$cutoff=gmdate('Y-m-d H:i:s',time()-$days*DAY_IN_SECONDS);$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}orion_ai_traces WHERE created_at < %s",$cutoff));$handoffs=new Orion_Manager_Handoff();$handoffs->delete_terminal_before($cutoff,1000);$conversation_ids=$wpdb->get_col($wpdb->prepare("SELECT id FROM {$wpdb->prefix}orion_ai_conversations WHERE last_activity < %s LIMIT 1000",$cutoff));if(!$conversation_ids)return;$ids=array_values(array_filter(array_unique(array_map('absint',$conversation_ids))));if(!$ids)return;$handoffs->expire_responses_for_conversations($ids);$list=implode(',',$ids);$wpdb->query("DELETE FROM {$wpdb->prefix}orion_ai_messages WHERE conversation_id IN ({$list})");$wpdb->query("DELETE FROM {$wpdb->prefix}orion_ai_events WHERE conversation_id IN ({$list})");$wpdb->query("DELETE FROM {$wpdb->prefix}orion_ai_conversations WHERE id IN ({$list})");}
}
