<?php
/**
 * BarcodeTest.php
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 *
 * This file is part of tc-lib-barcode software library.
 */

namespace Test;

use PHPUnit\Framework\TestCase;

/**
 * Barcode class test
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class BarcodeTest extends TestCase
{
    protected $obj = null;

    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test
        $this->obj = new \Com\Tecnick\Barcode\Barcode;
    }

    public function testGetTypes()
    {
        $types = $this->obj->getTypes();
        $this->assertEquals(36, count($types));
    }

    /**
     * @expectedException \Com\Tecnick\Barcode\Exception
     */
    public function testGetBarcodeObjException()
    {
        $this->obj->getBarcodeObj(
            'ERROR',
            '01001100011100001111,10110011100011110000',
            -2,
            -2,
            'purple'
        );
    }

    /**
     * @expectedException \Com\Tecnick\Barcode\Exception
     */
    public function testSetPaddingException()
    {
        $this->obj->getBarcodeObj(
            'LRAW,AB,12,E3F',
            '01001100011100001111,10110011100011110000',
            -2,
            -2,
            'purple',
            array(10)
        );
    }

    /**
     * @expectedException \Com\Tecnick\Barcode\Exception
     */
    public function testEmptyColumns()
    {
        $this->obj->getBarcodeObj('LRAW', '');
    }

    /**
     * @expectedException \Com\Tecnick\Barcode\Exception
     */
    public function testEmptyInput()
    {
        $this->obj->getBarcodeObj('LRAW', array());
    }

    public function testSpotColor()
    {
        $bobj = $this->obj->getBarcodeObj(
            'LRAW',
            '01001100011100001111,10110011100011110000',
            -2,
            -2,
            'all',
            array(-2, 3, 0, 1)
        );
        $bobjarr = $bobj->getArray();
        $this->assertEquals('#000000ff', $bobjarr['color_obj']->getRgbaHexColor());
        $this->assertNUll($bobjarr['bg_color_obj']);
    }

    public function testBackgroundColor()
    {
        $bobj = $this->obj->getBarcodeObj(
            'LRAW',
            '01001100011100001111,10110011100011110000',
            -2,
            -2,
            'all',
            array(-2, 3, 0, 1)
        )->setBackgroundColor('mediumaquamarine');
        $bobjarr = $bobj->getArray();
        $this->assertEquals('#66cdaaff', $bobjarr['bg_color_obj']->getRgbaHexColor());
    }

    /**
     * @expectedException \Com\Tecnick\Barcode\Exception
     */
    public function testNoColorException()
    {
        $this->obj->getBarcodeObj(
            'LRAW',
            '01001100011100001111,10110011100011110000',
            -2,
            -2,
            '',
            array(-2, 3, 0, 1)
        );
    }

    public function testExportMethods()
    {
        $bobj = $this->obj->getBarcodeObj(
            'LRAW,AB,12,E3F',
            '01001100011100001111,10110011100011110000',
            -2,
            -2,
            'purple',
            array(-2, 3, 0, 1)
        );

        $this->assertEquals('01001100011100001111,10110011100011110000', $bobj->getExtendedCode());

        $barr = $bobj->getArray();
        $this->assertEquals('linear', $barr['type']);
        $this->assertEquals('LRAW', $barr['format']);
        $this->assertEquals(array('AB', '12', 'E3F'), $barr['params']);
        $this->assertEquals('01001100011100001111,10110011100011110000', $barr['code']);
        $this->assertEquals('01001100011100001111,10110011100011110000', $barr['extcode']);
        $this->assertEquals(20, $barr['ncols']);
        $this->assertEquals(2, $barr['nrows']);
        $this->assertEquals(40, $barr['width']);
        $this->assertEquals(4, $barr['height']);
        $this->assertEquals(2, $barr['width_ratio']);
        $this->assertEquals(2, $barr['height_ratio']);
        $this->assertEquals(array('T' => 4, 'R' => 3, 'B' => 0, 'L' => 1), $barr['padding']);
        $this->assertEquals(44, $barr['full_width']);
        $this->assertEquals(8, $barr['full_height']);

        $expected = array(
            array(1,0,1,1),
            array(4,0,2,1),
            array(9,0,3,1),
            array(16,0,4,1),
            array(0,1,1,1),
            array(2,1,2,1),
            array(6,1,3,1),
            array(12,1,4,1),
        );
        $this->assertEquals($expected, $barr['bars']);
        $this->assertEquals('#800080ff', $barr['color_obj']->getRgbaHexColor());

        $grid = $bobj->getGrid('A', 'B');
        $expected = "ABAABBAAABBBAAAABBBB\nBABBAABBBAAABBBBAAAA\n";
        $this->assertEquals($expected, $grid);

        $svg = $bobj->setBackgroundColor('yellow')->getSvgCode();
        $expected = '<?xml version="1.0" standalone="no" ?>
<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
<svg width="44.000000" height="8.000000"'
        .' viewBox="0 0 44.000000 8.000000" version="1.1" xmlns="http://www.w3.org/2000/svg">
	<desc>01001100011100001111,10110011100011110000</desc>
	<rect x="0" y="0" width="44.000000" height="8.000000" fill="#ffff00"'
        .' stroke="none" stroke-width="0" stroke-linecap="square" />
	<g id="bars" fill="#800080" stroke="none" stroke-width="0" stroke-linecap="square">
		<rect x="3.000000" y="4.000000" width="2.000000" height="2.000000" />
		<rect x="9.000000" y="4.000000" width="4.000000" height="2.000000" />
		<rect x="19.000000" y="4.000000" width="6.000000" height="2.000000" />
		<rect x="33.000000" y="4.000000" width="8.000000" height="2.000000" />
		<rect x="1.000000" y="6.000000" width="2.000000" height="2.000000" />
		<rect x="5.000000" y="6.000000" width="4.000000" height="2.000000" />
		<rect x="13.000000" y="6.000000" width="6.000000" height="2.000000" />
		<rect x="25.000000" y="6.000000" width="8.000000" height="2.000000" />
		<rect x="1.000000" y="6.000000" width="2.000000" height="2.000000" />
		<rect x="3.000000" y="4.000000" width="2.000000" height="2.000000" />
		<rect x="5.000000" y="6.000000" width="2.000000" height="2.000000" />
		<rect x="7.000000" y="6.000000" width="2.000000" height="2.000000" />
		<rect x="9.000000" y="4.000000" width="2.000000" height="2.000000" />
		<rect x="11.000000" y="4.000000" width="2.000000" height="2.000000" />
		<rect x="13.000000" y="6.000000" width="2.000000" height="2.000000" />
		<rect x="15.000000" y="6.000000" width="2.000000" height="2.000000" />
		<rect x="17.000000" y="6.000000" width="2.000000" height="2.000000" />
		<rect x="19.000000" y="4.000000" width="2.000000" height="2.000000" />
		<rect x="21.000000" y="4.000000" width="2.000000" height="2.000000" />
		<rect x="23.000000" y="4.000000" width="2.000000" height="2.000000" />
		<rect x="25.000000" y="6.000000" width="2.000000" height="2.000000" />
		<rect x="27.000000" y="6.000000" width="2.000000" height="2.000000" />
		<rect x="29.000000" y="6.000000" width="2.000000" height="2.000000" />
		<rect x="31.000000" y="6.000000" width="2.000000" height="2.000000" />
		<rect x="33.000000" y="4.000000" width="2.000000" height="2.000000" />
		<rect x="35.000000" y="4.000000" width="2.000000" height="2.000000" />
		<rect x="37.000000" y="4.000000" width="2.000000" height="2.000000" />
		<rect x="39.000000" y="4.000000" width="2.000000" height="2.000000" />
	</g>
</svg>
';
        $this->assertEquals($expected, $svg);

        $hdiv = $bobj->setBackgroundColor('lightcoral')->getHtmlDiv();
        $expected = '<div style="width:44.000000px;height:8.000000px;position:relative;font-size:0;'
        .'border:none;padding:0;margin:0;background-color:rgba(94%,50%,50%,1);">
	<div style="background-color:rgba(50%,0%,50%,1);left:3.000000px;top:4.000000px;'
        .'width:2.000000px;height:2.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(50%,0%,50%,1);left:9.000000px;top:4.000000px;'
        .'width:4.000000px;height:2.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(50%,0%,50%,1);left:19.000000px;top:4.000000px;'
        .'width:6.000000px;height:2.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(50%,0%,50%,1);left:33.000000px;top:4.000000px;'
        .'width:8.000000px;height:2.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(50%,0%,50%,1);left:1.000000px;top:6.000000px;'
        .'width:2.000000px;height:2.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(50%,0%,50%,1);left:5.000000px;top:6.000000px;'
        .'width:4.000000px;height:2.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(50%,0%,50%,1);left:13.000000px;top:6.000000px;'
        .'width:6.000000px;height:2.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(50%,0%,50%,1);left:25.000000px;top:6.000000px;'
        .'width:8.000000px;height:2.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(50%,0%,50%,1);left:1.000000px;top:6.000000px;'
        .'width:2.000000px;height:2.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(50%,0%,50%,1);left:3.000000px;top:4.000000px;'
        .'width:2.000000px;height:2.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(50%,0%,50%,1);left:5.000000px;top:6.000000px;'
        .'width:2.000000px;height:2.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(50%,0%,50%,1);left:7.000000px;top:6.000000px;'
        .'width:2.000000px;height:2.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(50%,0%,50%,1);left:9.000000px;top:4.000000px;'
        .'width:2.000000px;height:2.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(50%,0%,50%,1);left:11.000000px;top:4.000000px;'
        .'width:2.000000px;height:2.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(50%,0%,50%,1);left:13.000000px;top:6.000000px;'
        .'width:2.000000px;height:2.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(50%,0%,50%,1);left:15.000000px;top:6.000000px;'
        .'width:2.000000px;height:2.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(50%,0%,50%,1);left:17.000000px;top:6.000000px;'
        .'width:2.000000px;height:2.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(50%,0%,50%,1);left:19.000000px;top:4.000000px;'
        .'width:2.000000px;height:2.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(50%,0%,50%,1);left:21.000000px;top:4.000000px;'
        .'width:2.000000px;height:2.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(50%,0%,50%,1);left:23.000000px;top:4.000000px;'
        .'width:2.000000px;height:2.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(50%,0%,50%,1);left:25.000000px;top:6.000000px;'
        .'width:2.000000px;height:2.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(50%,0%,50%,1);left:27.000000px;top:6.000000px;'
        .'width:2.000000px;height:2.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(50%,0%,50%,1);left:29.000000px;top:6.000000px;'
        .'width:2.000000px;height:2.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(50%,0%,50%,1);left:31.000000px;top:6.000000px;'
        .'width:2.000000px;height:2.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(50%,0%,50%,1);left:33.000000px;top:4.000000px;'
        .'width:2.000000px;height:2.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(50%,0%,50%,1);left:35.000000px;top:4.000000px;'
        .'width:2.000000px;height:2.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(50%,0%,50%,1);left:37.000000px;top:4.000000px;'
        .'width:2.000000px;height:2.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
	<div style="background-color:rgba(50%,0%,50%,1);left:39.000000px;top:4.000000px;'
        .'width:2.000000px;height:2.000000px;position:absolute;border:none;padding:0;margin:0;">&nbsp;</div>
</div>
';
        $this->assertEquals($expected, $hdiv);

        if (extension_loaded('imagick')) {
            $pngik = $bobj->setBackgroundColor('white')->getPngData(true);
            $this->assertEquals('PNG', substr($pngik, 1, 3));
        }
        
        $pnggd = $bobj->setBackgroundColor('white')->getPngData(false);
        $this->assertEquals('PNG', substr($pnggd, 1, 3));

        $pnggd = $bobj->setBackgroundColor('')->getPngData(false);
        $this->assertEquals('PNG', substr($pnggd, 1, 3));
    }
    
    /**
     * @runInSeparateProcess
     */
    public function testGetSvg()
    {
        $bobj = $this->obj->getBarcodeObj(
            'LRAW,AB,12,E3F',
            '01001100011100001111,10110011100011110000',
            -2,
            -2,
            'purple'
        );
        ob_start();
        $bobj->getSvg();
        $svg = ob_get_clean();
        $this->assertEquals('86e0362768e8b1b26032381232c0367f', md5($svg));
    }
    
    /**
     * @runInSeparateProcess
     */
    public function testGetPng()
    {
        $bobj = $this->obj->getBarcodeObj(
            'LRAW,AB,12,E3F',
            '01001100011100001111,10110011100011110000',
            -2,
            -2,
            'purple'
        );
        ob_start();
        $bobj->getPng();
        $png = ob_get_clean();
        $this->assertEquals('PNG', substr($png, 1, 3));
    }
}
