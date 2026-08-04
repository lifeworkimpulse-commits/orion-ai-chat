<?php
if(!defined('ABSPATH')){exit;}
final class Orion_Resilient_Provider implements Orion_AI_Provider{
 private Orion_AI_Provider $primary;private ?Orion_AI_Provider $fallback;public function __construct(Orion_AI_Provider $primary,?Orion_AI_Provider $fallback=null){$this->primary=$primary;$this->fallback=$fallback;}
 public function chat(array $messages,array $tools=array()):array{$first=$this->primary->chat($messages,$tools);$attempts=array($this->attempt($first,'primary'));if($this->acceptable($first,$tools)){$first['attempts']=$attempts;return$first;}if(!$this->fallback){$first['attempts']=$attempts;return$first;}$second=$this->fallback->chat($messages,$tools);$attempts[]=$this->attempt($second,'fallback');$second['attempts']=$attempts;$second['fallback_used']=true;return$second;}
 private function acceptable(array $result,array $tools):bool{if(empty($result['ok']))return false;if(!$tools)return true;$calls=$result['message']['tool_calls']??array();if($calls)return true;$content=trim((string)($result['message']['content']??''));$content=preg_replace('/^```(?:json)?\s*|\s*```$/i','',$content);return is_array(json_decode($content,true));}
 private function attempt(array $result,string $kind):array{return array('kind'=>$kind,'provider'=>$result['provider']??'','model'=>$result['model']??'','ok'=>!empty($result['ok']),'status'=>$result['status']??null,'error'=>$result['error']??'','duration_ms'=>$result['duration_ms']??0);}
}
