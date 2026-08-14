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
    public function test_principal_material_has_highest_priority(): void { self::assertLessThan(Orion_Role_Registry::priority('primer'),Orion_Role_Registry::priority('primary_coating')); }
}
