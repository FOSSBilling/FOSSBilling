<?php
/**
 * UpcETest.php
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

namespace Test\Linear;

use PHPUnit\Framework\TestCase;

/**
 * Barcode class test
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2019 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class UpcETest extends TestCase
{
    protected $obj = null;

    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test
        $this->obj = new \Com\Tecnick\Barcode\Barcode;
    }

    public function testGetGrid()
    {
        $bobj = $this->obj->getBarcodeObj('UPCE', '725270');
        $grid = $bobj->getGrid();
        $expected = "101001000100100110110001001101101110110100111010101\n";
        $this->assertEquals($expected, $grid);
        
        $bobj = $this->obj->getBarcodeObj('UPCE', '725271');
        $grid = $bobj->getGrid();
        $expected = "101001000100100110111001001001101110110110011010101\n";
        $this->assertEquals($expected, $grid);
        
        $bobj = $this->obj->getBarcodeObj('UPCE', '725272');
        $grid = $bobj->getGrid();
        $expected = "101001000100100110111001001001100100010010011010101\n";
        $this->assertEquals($expected, $grid);
        
        $bobj = $this->obj->getBarcodeObj('UPCE', '725273');
        $grid = $bobj->getGrid();
        $expected = "101001000100100110110001001101101110110100001010101\n";
        $this->assertEquals($expected, $grid);
        
        $bobj = $this->obj->getBarcodeObj('UPCE', '725274');
        $grid = $bobj->getGrid();
        $expected = "101001000100100110110001001101100100010100011010101\n";
        $this->assertEquals($expected, $grid);
        
        $bobj = $this->obj->getBarcodeObj('UPCE', '725275');
        $grid = $bobj->getGrid();
        $expected = "101001000100100110111001001101101110110110001010101\n";
        $this->assertEquals($expected, $grid);
        
        $bobj = $this->obj->getBarcodeObj('UPCE', '725276');
        $grid = $bobj->getGrid();
        $expected = "101001000100110110110001001101101110110101111010101\n";
        $this->assertEquals($expected, $grid);
        
        $bobj = $this->obj->getBarcodeObj('UPCE', '725277');
        $grid = $bobj->getGrid();
        $expected = "101001000100100110111001001001101110110010001010101\n";
        $this->assertEquals($expected, $grid);
        
        $bobj = $this->obj->getBarcodeObj('UPCE', '725278');
        $grid = $bobj->getGrid();
        $expected = "101001000100100110110001001101100100010110111010101\n";
        $this->assertEquals($expected, $grid);
        
        $bobj = $this->obj->getBarcodeObj('UPCE', '725279');
        $grid = $bobj->getGrid();
        $expected = "101001000100110110110001001001100100010001011010101\n";
        $this->assertEquals($expected, $grid);

        $bobj = $this->obj->getBarcodeObj('UPCE', '0123456789');
        $grid = $bobj->getGrid();
        $expected = "101010011100110010010011010000100111010001011010101\n";
        $this->assertEquals($expected, $grid);

        $bobj = $this->obj->getBarcodeObj('UPCE', '012345678912');
        $grid = $bobj->getGrid();
        $expected = "101011001100110110111101010001101110010011001010101\n";
        $this->assertEquals($expected, $grid);

        $bobj = $this->obj->getBarcodeObj('UPCE', '4210000526');
        $grid = $bobj->getGrid();
        $expected = "101001110100100110111001001101101011110011001010101\n";
        $this->assertEquals($expected, $grid);

        $bobj = $this->obj->getBarcodeObj('UPCE', '4240000526');
        $grid = $bobj->getGrid();
        $expected = "101001110100110110100011001101101011110111101010101\n";
        $this->assertEquals($expected, $grid);

        $bobj = $this->obj->getBarcodeObj('UPCE', '4241000526');
        $grid = $bobj->getGrid();
        $expected = "101001110100100110011101001100101011110011101010101\n";
        $this->assertEquals($expected, $grid);
    }
}
