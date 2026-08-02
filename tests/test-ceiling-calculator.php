<?php
use PHPUnit\Framework\TestCase;
final class Orion_Ceiling_Calculator_Test extends TestCase {
    public function test_paint_estimate_is_deterministic(): void {
        $calculator = new Orion_Ceiling_Calculator();
        $result = $calculator->calculate( array( 'area_m2' => 12, 'finish' => 'paint', 'coats' => 2, 'waste_percent' => 10 ) );
        self::assertIsArray( $result );
        self::assertSame( 'painted_ceiling', $result['type'] );
        self::assertGreaterThan( 0, $result['items'][0]['amount'] );
    }
}
