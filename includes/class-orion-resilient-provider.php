<?php
if(!defined('ABSPATH')){exit;}
final class Orion_Resilient_Provider implements Orion_AI_Provider{
 private Orion_AI_Provider $primary;private ?Orion_AI_Provider $fallback;private string $stage;
 public function __construct(Orion_AI_Provider $primary,?Orion_AI_Provider $fallback=null,string $stage='answer'){$this->primary=$primary;$this->fallback=$fallback;$this->stage=$stage;}
 public function chat(array $messages,array $tools=array()):array{
  if('selection'===$this->stage)$messages=Orion_Selection_Policy::apply($messages);
  $first=$this->primary->chat($messages,$tools);$attempts=array($this->attempt($first,'primary'));
  if('selection'===$this->stage&&$this->acceptable($first,$tools)&&Orion_Selection_Policy::needs_review($first,$messages)){
   $review=$this->primary->chat(Orion_Selection_Policy::review_messages($messages,$first),$tools);$preferred=$this->acceptable($review,$tools)&&Orion_Selection_Policy::prefer_review($first,$review);$attempts[]=$this->attempt($review,$preferred?'selection_review_selected':'selection_review');if($preferred)$first=$review;
  }
  if('selection'===$this->stage&&$this->acceptable($first,$tools)&&Orion_Selection_Policy::needs_review($first,$messages)&&Orion_Vision_Review::supported($first)){
   $vision=Orion_Vision_Review::build($messages,$first,3);
   if($vision['image_count']>0){$review=$this->primary->chat($vision['messages'],$tools);$preferred=$this->acceptable($review,$tools)&&Orion_Selection_Policy::prefer_review($first,$review);$attempts[]=$this->attempt($review,$preferred?'vision_review_selected':'vision_review',array('image_count'=>$vision['image_count'],'candidate_ids'=>$vision['candidate_ids']));if($preferred)$first=$review;}
  }
  if($this->acceptable($first,$tools)){$first['attempts']=$attempts;return$first;}if(!$this->fallback){$first['attempts']=$attempts;return$first;}$second=$this->fallback->chat($messages,$tools);$attempts[]=$this->attempt($second,'fallback');$second['attempts']=$attempts;$second['fallback_used']=true;return$second;
 }
 private function acceptable(array $result,array $tools):bool{if(empty($result['ok']))return false;if(!$tools)return true;if('selection'===$this->stage)return Orion_Selection_Policy::valid_result($result);$calls=$result['message']['tool_calls']??array();if($calls){foreach($calls as$call){$data=json_decode((string)($call['function']['arguments']??''),true);if(is_array($data))return true;}return false;}$content=trim((string)($result['message']['content']??''));$content=preg_replace('/^```(?:json)?\s*|\s*```$/i','',$content);return is_array(json_decode($content,true));}
 private function attempt(array $result,string $kind,array $meta=array()):array{return array_merge(array('kind'=>$kind,'provider'=>$result['provider']??'','model'=>$result['model']??'','ok'=>!empty($result['ok']),'status'=>$result['status']??null,'error'=>$result['error']??'','duration_ms'=>$result['duration_ms']??0),$meta);}
}
