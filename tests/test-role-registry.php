<?php
use PHPUnit\Framework\TestCase;
final class Orion_Role_Registry_Test extends TestCase {
    public function test_aliases_are_canonicalised(): void {
        self::assertSame('primary_coating',Orion_Role_Registry::canonical('primarycoating'));
        self::assertSame('masking_tape',Orion_Role_Registry::canonical('Masking Tape'));
        self::assertSame('roller',Orion_Role_Registry::canonical('roller_sleeve'));
        self::assertSame('dust_sheet',Orion_Role_Registry::canonical_for_query('protection','floor protection dust sheet for painting'));
    }
    public function test_known_roles_are_hints_not_a_closed_gate(): void {
        self::assertTrue(Orion_Role_Registry::known('tray'));
        self::assertFalse(Orion_Role_Registry::known('diamond_drill_bit'));
        self::assertTrue(Orion_Role_Registry::supported('diamond drill bit'));
        self::assertSame('diamond_drill_bit',Orion_Role_Registry::open_key('Diamond drill bit'));
        self::assertSame('',Orion_Role_Registry::role_hint('diamond_drill_bit'));
        self::assertSame('tray',Orion_Role_Registry::role_hint('tray'));
        self::assertFalse(Orion_Role_Registry::supported(''));
    }
    public function test_open_painting_keys_get_known_validator_hints(): void {
        self::assertSame('primary_coating',Orion_Role_Registry::canonical('ceiling_paint'));
        self::assertSame('primary_coating',Orion_Role_Registry::canonical('primary_floor_coating'));
        self::assertSame('primary_coating',Orion_Role_Registry::canonical('ceiling_topcoat'));
        self::assertSame('roller',Orion_Role_Registry::canonical('floor_coating_roller'));
        self::assertSame('tray',Orion_Role_Registry::canonical('paint_tray'));
        self::assertSame('brush',Orion_Role_Registry::canonical('cutting_in_brush'));
        self::assertSame('cleaner',Orion_Role_Registry::canonical('concrete_floor_cleaner'));
        self::assertSame('primer',Orion_Role_Registry::canonical('concrete_floor_primer'));
        self::assertSame('filler',Orion_Role_Registry::canonical('concrete_repair_filler'));
        self::assertSame('sandpaper',Orion_Role_Registry::canonical('sanding_abrasive'));
        self::assertSame('scraper',Orion_Role_Registry::canonical('paint_scraper'));
        self::assertSame('tools',Orion_Role_Registry::canonical('roller_extension_pole'));
        self::assertSame('protection',Orion_Role_Registry::canonical('floor_coating_safety_equipment'));
        self::assertTrue(Orion_Role_Registry::optional('floor_coating_roller'));
    }
    public function test_principal_material_has_highest_priority(): void { self::assertLessThan(Orion_Role_Registry::priority('primer'),Orion_Role_Registry::priority('primary_coating')); }
}
