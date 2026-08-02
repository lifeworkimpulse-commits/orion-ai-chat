<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Orion_Gemini_Client implements Orion_AI_Provider {
    private string $api_key; private string $model;
    public function __construct( string $api_key, string $model ) { $this->api_key=trim($api_key);$this->model=preg_replace('/[^a-zA-Z0-9._-]/','',trim($model))?:'gemini-3.6-flash'; }
    public function chat( array $messages, array $tools=array() ):array {
        if(''===$this->api_key)return array('ok'=>false,'error'=>'Google Gemini API key is not configured.');
        $body=$this->body($messages,$tools);$url='https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($this->model).':generateContent';
        $response=wp_remote_post($url,array('timeout'=>45,'redirection'=>0,'headers'=>array('Content-Type'=>'application/json','x-goog-api-key'=>$this->api_key),'body'=>wp_json_encode($body)));
        if(is_wp_error($response))return array('ok'=>false,'error'=>'Google Gemini could not be reached: '.sanitize_text_field($response->get_error_message()));
        $code=(int)wp_remote_retrieve_response_code($response);$json=json_decode(wp_remote_retrieve_body($response),true);
        if($code<200||$code>=300){
            $raw=is_array($json)?trim(wp_strip_all_tags((string)($json['error']['message']??''))):'';
            $public=match($code){400=>'Google Gemini rejected the request. Check the selected model.',401=>'Google Gemini API key is invalid.',403=>'Google Gemini access is forbidden. Check the key and whether the Gemini API is enabled.',404=>'Google Gemini model was not found. Enter a model available to this API key.',429=>'Google Gemini is rate limited or its free quota is exhausted.',default=>'Google Gemini returned HTTP '.$code.'.'};
            if($raw!=='')$public.=' Details: '.sanitize_text_field($raw);
            return array('ok'=>false,'error'=>$public,'status'=>$code);
        }
        $parts=$json['candidates'][0]['content']['parts']??array();$text='';$calls=array();
        foreach($parts as$part){
            if(isset($part['text']))$text.=(string)$part['text'];
            if(isset($part['functionCall']['name'])){
                $api_name=(string)$part['functionCall']['name'];
                $tool_name=str_contains($api_name,':')?(string)substr($api_name,strrpos($api_name,':')+1):$api_name;
                $signature=(string)($part['thoughtSignature']??$part['thought_signature']??'');
                $call=array('id'=>'gemini_'.wp_generate_uuid4(),'type'=>'function','function'=>array('name'=>sanitize_key($tool_name),'arguments'=>wp_json_encode($part['functionCall']['args']??array())),'gemini_function_name'=>$api_name);
                if($signature!=='')$call['thought_signature']=$signature;
                $calls[]=$call;
            }
        }
        $message=array('role'=>'assistant','content'=>trim($text));if($calls)$message['tool_calls']=$calls;
        if(''===$message['content']&&!$calls)return array('ok'=>false,'error'=>'Google Gemini returned an empty response.');
        $usage=$json['usageMetadata']??array();return array('ok'=>true,'message'=>$message,'usage'=>array('prompt_tokens'=>(int)($usage['promptTokenCount']??0),'completion_tokens'=>(int)($usage['candidatesTokenCount']??0),'total_tokens'=>(int)($usage['totalTokenCount']??0)));
    }
    private function body(array $messages,array $tools):array {
        $system=array();$contents=array();$call_names=array();
        foreach($messages as$message){$role=(string)($message['role']??'user');
            if('system'===$role){$system[]=(string)($message['content']??'');continue;}
            if('assistant'===$role&&isset($message['tool_calls'])){
                $parts=array();if(!empty($message['content']))$parts[]=array('text'=>(string)$message['content']);
                foreach($message['tool_calls']as$call){
                    $id=(string)($call['id']??'');$api_name=(string)($call['gemini_function_name']??$call['function']['name']??'');$call_names[$id]=$api_name;
                    $args=json_decode((string)($call['function']['arguments']??'{}'),true);
                    $function_part=array('functionCall'=>array('name'=>$api_name,'args'=>is_array($args)?$args:array()));
                    $signature=(string)($call['thought_signature']??'');if($signature!=='')$function_part['thoughtSignature']=$signature;
                    $parts[]=$function_part;
                }
                $contents[]=array('role'=>'model','parts'=>$parts);continue;
            }
            if('tool'===$role){$id=(string)($message['tool_call_id']??'');$name=$call_names[$id]??'tool_result';$data=json_decode((string)($message['content']??'{}'),true);$contents[]=array('role'=>'user','parts'=>array(array('functionResponse'=>array('name'=>$name,'response'=>is_array($data)?$data:array('result'=>(string)($message['content']??''))))));continue;}
            $contents[]=array('role'=>'assistant'===$role?'model':'user','parts'=>array(array('text'=>(string)($message['content']??''))));
        }
        $body=array('contents'=>$contents,'generationConfig'=>array('temperature'=>0.2,'maxOutputTokens'=>900));if($system)$body['systemInstruction']=array('parts'=>array(array('text'=>implode("\n",$system))));
        if($tools){$decl=array();foreach($tools as$tool){$fn=$tool['function']??array();$decl[]=array('name'=>$fn['name']??'','description'=>$fn['description']??'','parameters'=>$fn['parameters']??array('type'=>'object'));}$body['tools']=array(array('functionDeclarations'=>$decl));}
        return$body;
    }
}
