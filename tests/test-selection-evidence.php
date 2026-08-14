<?php
use PHPUnit\Framework\TestCase;
final class Orion_Selection_Evidence_Test extends TestCase {
    public function test_explicit_medium_confidence_evidence_is_sufficient(): void {
        $evidence = Orion_Selection_Evidence::normalise(array(
            'evidence_fields'=>array('name','categories','invented_field','name'),
            'confidence'=>'medium','reason'=>'  Category and title match.  ','uncertainty'=>'Check the exact size.',
        ));
        self::assertSame(array('name','categories'),$evidence['evidence_fields']);
        self::assertSame('medium',$evidence['confidence']);
        self::assertTrue(Orion_Selection_Evidence::sufficient($evidence));
    }
    public function test_low_or_empty_evidence_is_rejected(): void {
        self::assertFalse(Orion_Selection_Evidence::sufficient(Orion_Selection_Evidence::normalise(array('confidence'=>'low','evidence_fields'=>array('name')))));
        self::assertFalse(Orion_Selection_Evidence::sufficient(Orion_Selection_Evidence::normalise(array('confidence'=>'high','evidence_fields'=>array('unsupported')))));
    }
}
