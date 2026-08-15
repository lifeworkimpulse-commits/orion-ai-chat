<?php
use PHPUnit\Framework\TestCase;
final class Orion_Selection_Policy_Test extends TestCase {
    public function test_function_match_policy_preserves_compatibility_uncertainty(): void {
        $messages = Orion_Selection_Policy::apply(array(array('role'=>'system','content'=>'Select catalogue products.')));
        self::assertStringContainsString('direct product-type or function match',$messages[0]['content']);
        self::assertStringContainsString('select it with medium confidence',$messages[0]['content']);
        self::assertStringContainsString('record that exact unresolved detail in uncertainty',$messages[0]['content']);
        self::assertStringContainsString('do not claim compatibility',$messages[0]['content']);
    }
    public function test_policy_creates_system_message_when_missing(): void {
        $messages = Orion_Selection_Policy::apply(array(array('role'=>'user','content'=>'Need a tool.')));
        self::assertSame('system',$messages[0]['role']);
        self::assertCount(2,$messages);
    }
}
