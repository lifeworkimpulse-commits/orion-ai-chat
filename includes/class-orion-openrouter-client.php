<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class Orion_OpenRouter_Client {
    private const ENDPOINT='https://openrouter.ai/api/v1/chat/completions'; private string $api_key; private string $model;
    public function __construct(string $key,string $model){$this->api_key=trim($key);$this->model=trim($model)?:'openrouter/auto';}
    public function chat(array $messages,array $tools=array()):array{
        if(''===$this->api_key)return array('ok'=>false,'error'=>'OpenRouter API key is not configured.');
        $result=$this->request($messages,$tools);
        if(!$result['ok']&&$tools&&!empty($result['tool_unsupported']))$result=$this->request($messages,array());
        return $result;
    }
    private function request(array $messages,array $tools):array{
        $body=array('model'=>$this->model,'messages'=>$messages,'temperature'=>0.2,'max_tokens'=>900);
        if($tools){$body['tools']=$tools;$body['tool_choice']='auto';$body['parallel_tool_calls']=false;}
        $args=array('timeout'=>45,'redirection'=>0,'headers'=>array('Authorization'=>'Bearer '.$this->api_key,'Content-Type'=>'application/json','HTTP-Referer'=>home_url('/'),'X-OpenRouter-Title'=>get_bloginfo('name').' AI Assistant'),'body'=>wp_json_encode($body));
        $response=wp_remote_post(self::ENDPOINT,$args);
        if(is_wp_error($response))return array('ok'=>false,'error'=>'The AI service could not be reached. Please try again.');
        $code=(int)wp_remote_retrieve_response_code($response);$json=json_decode(wp_remote_retrieve_body($response),true);$raw=is_array($json)?(string)($json['error']['message']??''):'';
        if($code<200||$code>=300){
            $tool_error=$tools&&($code===400||stripos($raw,'tool')!==false);
            $public=match($code){401=>'OpenRouter authentication failed.',402=>'OpenRouter credit is unavailable.',429=>'The AI service is busy or rate limited. Please try again shortly.',default=>'The AI service returned an error. Please try again.'};
            return array('ok'=>false,'error'=>$public,'tool_unsupported'=>$tool_error,'status'=>$code);
        }
        $message=$json['choices'][0]['message']??null;if(!is_array($message))return array('ok'=>false,'error'=>'The AI service returned an invalid response.');
        return array('ok'=>true,'message'=>$message,'usage'=>is_array($json['usage']??null)?$json['usage']:array());
    }
}
