<?php
if(!defined('ABSPATH')){exit;}
final class Orion_AI_Evaluation_Service{
 public function run(array $settings,string $suite_file):array{
  $cases=json_decode((string)file_get_contents($suite_file),true);if(!is_array($cases))return array('rows'=>array(),'summary'=>array('error'=>'Invalid evaluation suite.'));
  $rows=array();$passed=0;$total_ms=0;$tokens=0;
  foreach($cases as$case){
   $state=array();$history=array();$last=array();$started=microtime(true);
   foreach(($case['messages']??array())as$message){$last=(new Orion_Intent_Classifier())->classify((string)$message,$history,$state,$settings);$state=(new Orion_Context_Manager())->apply($state,$last);$history[]=array('role'=>'user','content'=>(string)$message);$history[]=array('role'=>'assistant','content'=>!empty($last['clarifying_questions'])?implode(' ',(array)$last['clarifying_questions']):'Route recorded.');$tokens+=(int)($last['_diagnostic']['usage']['total_tokens']??0);}
   $duration=(int)round((microtime(true)-$started)*1000);$total_ms+=$duration;$failures=array();
   if(isset($case['expected_intent'])&&(string)($last['intent']??'')!==(string)$case['expected_intent'])$failures[]='intent';
   foreach(($case['expected_state']??array())as$key=>$value)if(!isset($state[$key])||(string)$state[$key]!==(string)$value)$failures[]='state.'.$key;
   $actual_plan=is_array($last['needs']??null)?$last['needs']:(array)($last['search_plan']??array());
   $actual_roles=array_values(array_unique(array_column($actual_plan,'role')));
   foreach(($case['expected_roles']??array())as$role)if(!in_array($role,$actual_roles,true))$failures[]='missing_role.'.$role;
   foreach(($case['expected_optional_roles']??array())as$role){$optional=false;foreach($actual_plan as$item)if(($item['role']??'')===$role&&empty($item['required']))$optional=true;if(!$optional)$failures[]='optional_role.'.$role;}
   foreach(($case['unexpected_roles']??array())as$role)if(in_array($role,$actual_roles,true))$failures[]='unexpected_role.'.$role;
   $minimum=max(0,(int)($case['expected_min_needs']??0));if($minimum&&count($actual_plan)<$minimum)$failures[]='need_count';
   $need_text=strtolower((string)wp_json_encode($actual_plan,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
   foreach(($case['expected_need_keywords']??array())as$keyword){$keyword=strtolower(trim((string)$keyword));if($keyword!==''&&!str_contains($need_text,$keyword))$failures[]='missing_need_keyword.'.$keyword;}
   $diagnostic=(string)($last['_diagnostic']['status']??'');if(in_array($diagnostic,array('provider_error','invalid_output'),true))$failures[]='diagnostic.'.$diagnostic;
   if(!empty($case['require_open_need_contract'])&&empty($last['_diagnostic']['open_need_contract']))$failures[]='open_need_contract';
   $ok=!$failures;if($ok)$passed++;
   $details=$ok?'':wp_json_encode(array('failures'=>$failures,'expected_intent'=>$case['expected_intent']??null,'expected_state'=>$case['expected_state']??array(),'actual_state'=>$state,'expected_roles'=>$case['expected_roles']??array(),'actual_roles'=>$actual_roles,'expected_need_keywords'=>$case['expected_need_keywords']??array(),'actual_needs'=>$actual_plan),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
   $rows[]=array('case'=>sanitize_text_field((string)($case['name']??'Unnamed')),'result'=>$ok?'PASS':'FAIL','intent'=>(string)($last['intent']??''),'diagnostic'=>$diagnostic,'duration_ms'=>$duration,'details'=>$details);
  }
  return array('rows'=>$rows,'summary'=>array('passed'=>$passed,'total'=>count($rows),'pass_rate'=>$rows?round($passed/count($rows)*100,1):0,'duration_ms'=>$total_ms,'average_ms'=>$rows?(int)round($total_ms/count($rows)):0,'total_tokens'=>$tokens));
 }
}
