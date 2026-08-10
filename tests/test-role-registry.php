<?php
use PHPUnit\Framework\TestCase;
final class Orion_Role_Registry_Test extends TestCase {
    public function test_aliases_are_canonicalised(): void {
        self::assertSame('primary_coating',Orion_Role_Registry::canonical('primarycoating'));
        self::assertSame('masking_tape',Orion_Role_Registry::canonical('masking tape'));
        self::assertSame('roller',Orion_Role_Registry::canonical('roller_sleeve'));
    }
    public function test_unknown_role_is_not_supported(): void { self::assertFalse(Orion_Role_Registry::supported('researcher')); self::assertTrue(Orion_Role_Registry::supported('tray')); }
    public function test_principal_material_has_highest_priority(): void { self::assertLessThan(Orion_Role_Registry::priority('primer'),Orion_Role_Registry::priority('primary_coating')); }
}
