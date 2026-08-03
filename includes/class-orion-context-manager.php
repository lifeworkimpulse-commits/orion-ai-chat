<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Orion_Context_Manager {
    public function apply( array $state, array $classification ): array {
        if(!empty($classification['is_new_topic']))$state=array();
        $state=is_array($state)?$state:array();$state['active_intent']=$classification['intent']??'general';$state['active_topic']=$classification['topic']??'';$state['updated_at']=gmdate('c');
        foreach(($classification['state_patch']??array())as$key=>$value){if($value===''||$value===null)continue;$state[$key]=$value;}
        return array_slice($state,0,30,true);
    }
}
