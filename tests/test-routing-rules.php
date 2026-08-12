<?php
use PHPUnit\Framework\TestCase;
final class Orion_Routing_Rules_Test extends TestCase {
    public function test_delivery_queries_are_detected(): void {
        self::assertNotNull(Orion_Routing_Rules::delivery_policy_signals('Can you get a pallet to M1?'));
        self::assertNotNull(Orion_Routing_Rules::delivery_policy_signals('Do you deliver bulk materials to Manchester?'));
        self::assertNotNull(Orion_Routing_Rules::delivery_policy_signals('Can this order be sent to SW1A 1AA?'));
    }
    public function test_product_request_is_not_delivery_policy(): void { self::assertNull(Orion_Routing_Rules::delivery_policy_signals('I need a pallet of floor paint')); }
    public function test_dimensions_require_explicit_unit_for_unit_field(): void {
        $dimensions=Orion_Routing_Rules::extract_dimensions('My garage is six by four'); self::assertSame(6.0,$dimensions['dimension_length']); self::assertArrayNotHasKey('dimension_unit',$dimensions);
        $metric=Orion_Routing_Rules::extract_dimensions('My garage is 6 x 4 metres'); self::assertSame('m',$metric['dimension_unit']);
    }
    public function test_floor_and_complete_kit_state(): void { $state=array('project_type'=>'garage floor coating','notes'=>'Find everything needed'); self::assertTrue(Orion_Routing_Rules::is_floor_project($state)); self::assertTrue(Orion_Routing_Rules::wants_complete_kit($state)); }
    public function test_negated_conditions_do_not_activate_unrelated_preparation_roles(): void {
        $state=array('surface'=>'concrete','notes'=>'Concrete is bare, clean, dry and sound, with no oil, cracks, damp or previous coating.');
        self::assertTrue(Orion_Routing_Rules::preparation_role_needed('primer',$state));
        self::assertFalse(Orion_Routing_Rules::preparation_role_needed('cleaner',$state));
        self::assertFalse(Orion_Routing_Rules::preparation_role_needed('filler',$state));
        self::assertFalse(Orion_Routing_Rules::preparation_role_needed('sandpaper',$state));
        self::assertFalse(Orion_Routing_Rules::preparation_role_needed('scraper',$state));
    }
    public function test_positive_conditions_activate_only_matching_preparation_roles(): void {
        self::assertTrue(Orion_Routing_Rules::preparation_role_needed('cleaner',array('notes'=>'The floor has oily grease contamination.')));
        self::assertFalse(Orion_Routing_Rules::preparation_role_needed('filler',array('notes'=>'The floor has oily grease contamination.')));
        self::assertTrue(Orion_Routing_Rules::preparation_role_needed('filler',array('notes'=>'The wall has cracks and two holes.')));
        self::assertFalse(Orion_Routing_Rules::preparation_role_needed('cleaner',array('notes'=>'The wall has cracks and two holes.')));
        self::assertTrue(Orion_Routing_Rules::preparation_role_needed('sandpaper',array('notes'=>'The previous coating is flaking.')));
        self::assertTrue(Orion_Routing_Rules::preparation_role_needed('scraper',array('notes'=>'The previous coating is peeling.')));
        self::assertTrue(Orion_Routing_Rules::preparation_role_needed('primer',array('surface'=>'new plaster')));
    }
}
