<?php
use PHPUnit\Framework\TestCase;
final class Orion_Kit_Validator_Test extends TestCase {
    public function test_frame_alone_is_rejected(): void {
        $validator=new Orion_Kit_Validator(); $products=array(array('id'=>1,'name'=>'9" Roller Cage Frame','description'=>'For 9 inch sleeves','logical_roles'=>array('roller'),'kit_roles'=>array('roller_frame'),'kit_role'=>'roller_frame'));
        [$valid,$rejections]=$validator->validate($products,array()); self::assertCount(0,$valid); self::assertCount(1,$rejections);
    }
    public function test_matching_frame_and_sleeve_are_kept(): void {
        $validator=new Orion_Kit_Validator(); $products=array(
            array('id'=>1,'name'=>'9" Roller Cage Frame','logical_roles'=>array('roller'),'kit_roles'=>array('roller_frame'),'kit_role'=>'roller_frame'),
            array('id'=>2,'name'=>'9" Medium Pile Roller Sleeve','logical_roles'=>array('roller'),'kit_roles'=>array('roller_sleeve'),'kit_role'=>'roller_sleeve'));
        [$valid,$rejections]=$validator->validate($products,array()); self::assertCount(2,$valid); self::assertCount(0,$rejections);
    }
    public function test_narrow_tray_is_rejected(): void {
        $validator=new Orion_Kit_Validator(); $products=array(
            array('id'=>1,'name'=>'12" Roller Frame and Sleeve Set','logical_roles'=>array('roller'),'kit_roles'=>array('roller'),'kit_role'=>'roller'),
            array('id'=>2,'name'=>'9" Paint Roller Tray','logical_roles'=>array('tray'),'kit_roles'=>array('tray'),'kit_role'=>'tray'));
        [$valid,$rejections]=$validator->validate($products,array()); self::assertCount(1,$valid); self::assertSame('tray',$rejections[0]['role']);
    }
}
