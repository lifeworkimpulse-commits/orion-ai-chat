<?php
use PHPUnit\Framework\TestCase;
final class Orion_Product_Facts_Test extends TestCase {
    private Orion_Product_Facts $facts;
    protected function setUp(): void { $this->facts=new Orion_Product_Facts(); }
    public function test_frame_description_does_not_prove_included_sleeve(): void {
        $product=array('name'=>'9" Roller Cage Frame','description'=>'Compatible with 9 inch roller sleeves','categories'=>array('Rollers & Brushes'));
        self::assertSame('frame',$this->facts->roller_component($product)['kind']);
    }
    public function test_explicit_set_is_complete(): void {
        $product=array('name'=>'9 inch 13pc Roller & Frame Set','description'=>'Includes frame and two sleeves','categories'=>array('Rollers & Brushes')); $facts=$this->facts->extract($product);
        self::assertSame('complete',$facts['roller_component']); self::assertSame(9.0,$facts['roller_width_inches']); self::assertTrue($facts['is_bundle']);
    }
    public function test_structured_product_functions_are_extracted(): void {
        $product=array('name'=>'Heavy Duty Floor Paint Grey 5L','description'=>'Suitable for concrete garage floors. Coverage 8 m2 per litre.','categories'=>array('Floor Paint')); $facts=$this->facts->extract($product);
        self::assertContains('primary_coating',$facts['functions']); self::assertContains('floor',$facts['surfaces']); self::assertSame(8.0,$facts['coverage_m2_per_litre']);
    }
}
