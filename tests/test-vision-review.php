<?php
use PHPUnit\Framework\TestCase;
final class Orion_Vision_Review_Test extends TestCase {
    public function test_supported_openrouter_multimodal_models_are_detected(): void {
        self::assertTrue(Orion_Vision_Review::supported(array('provider'=>'openrouter','model'=>'openai/gpt-4o')));
        self::assertTrue(Orion_Vision_Review::supported(array('provider'=>'openrouter','model'=>'google/gemini-3.6-flash')));
    }
    public function test_unverified_or_direct_models_remain_text_only(): void {
        self::assertFalse(Orion_Vision_Review::supported(array('provider'=>'openrouter','model'=>'openai/gpt-5.6-luna')));
        self::assertFalse(Orion_Vision_Review::supported(array('provider'=>'openrouter','model'=>'vendor/text-only-model')));
        self::assertFalse(Orion_Vision_Review::supported(array('provider'=>'google','model'=>'gemini-3.6-flash')));
    }
}
