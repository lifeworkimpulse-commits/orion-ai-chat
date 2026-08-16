<?php
use PHPUnit\Framework\TestCase;
final class Orion_Selection_Policy_Test extends TestCase {
    public function test_function_match_policy_preserves_compatibility_uncertainty(): void {
        $messages = Orion_Selection_Policy::apply(array(array('role'=>'system','content'=>'Select catalogue products.')));
        self::assertStringContainsString('MUST select that product',$messages[0]['content']);
        self::assertStringContainsString('Use medium confidence',$messages[0]['content']);
        self::assertStringContainsString('record the exact unresolved detail in uncertainty',$messages[0]['content']);
        self::assertStringContainsString('do not claim compatibility',$messages[0]['content']);
    }
    public function test_policy_creates_system_message_when_missing(): void {
        $messages = Orion_Selection_Policy::apply(array(array('role'=>'user','content'=>'Need a tool.')));
        self::assertSame('system',$messages[0]['role']);
        self::assertCount(2,$messages);
    }
    public function test_missing_needs_trigger_one_compliance_review(): void {
        $result = $this->result(array(),array('application_gun'));
        self::assertTrue(Orion_Selection_Policy::needs_review($result));
        $messages = Orion_Selection_Policy::review_messages(array(array('role'=>'system','content'=>'Select.')),$result);
        self::assertStringContainsString('application_gun',$messages[1]['content']);
    }
    public function test_optional_non_core_need_does_not_trigger_review(): void {
        $result=$this->result(array(),array('protection'));
        $messages=$this->messages(array(array('need_key'=>'protection','role_hint'=>'protection','required'=>false)));
        self::assertFalse(Orion_Selection_Policy::needs_review($result,$messages));
    }
    public function test_required_or_core_need_still_triggers_review(): void {
        $required=$this->result(array(),array('application_gun'));
        self::assertTrue(Orion_Selection_Policy::needs_review($required,$this->messages(array(array('need_key'=>'application_gun','role_hint'=>'','required'=>true)))));
        $roller=$this->result(array(),array('roller'));
        self::assertTrue(Orion_Selection_Policy::needs_review($roller,$this->messages(array(array('need_key'=>'roller','role_hint'=>'roller','required'=>false)))));
    }
    public function test_review_is_preferred_only_when_selection_score_improves(): void {
        $current = $this->result(array(array('product_id'=>1)),array('application_gun'));
        $better = $this->result(array(array('product_id'=>1),array('product_id'=>2)),array());
        $same = $this->result(array(array('product_id'=>1)),array('application_gun'));
        self::assertTrue(Orion_Selection_Policy::prefer_review($current,$better));
        self::assertFalse(Orion_Selection_Policy::prefer_review($current,$same));
    }
    private function messages(array $needs): array {return array(array('role'=>'system','content'=>'Select.'),array('role'=>'user','content'=>json_encode(array('products'=>array(),'needs'=>$needs))));}
    private function result(array $selections,array $missing): array {
        return array('ok'=>true,'message'=>array('tool_calls'=>array(array('function'=>array('arguments'=>json_encode(array('selections'=>$selections,'missing_needs'=>$missing)))))));
    }
}
