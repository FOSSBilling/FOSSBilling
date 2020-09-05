<?php
/**
 * CTest.php
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

namespace Test\Linear\CodeOneTwoEight;

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
class CTest extends TestCase
{
    protected $obj = null;

    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test
        $this->obj = new \Com\Tecnick\Barcode\Barcode;
    }

    public function testGetGrid()
    {
        $bobj = $this->obj->getBarcodeObj('C128C', '0123456789');
        $grid = $bobj->getGrid();
        $expected = "110100111001100110110011101101110101110110001000010110011011011110100001101001100011101011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $this->obj->getBarcodeObj('C128C', chr(241).'0123456789');
        $grid = $bobj->getGrid();
        $expected = "11010011100111101011101100110110011101101110101110110001000010110011011011110111101101101100"
            ."011101011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $this->obj->getBarcodeObj('C128C', chr(241).'00123456780000000001');
        $grid = $bobj->getGrid();
        $expected = "11010011100111101011101101100110010110011100100010110001110001011011000010100110110011001101"
            ."1001100110110011001101100110011001101100100010011001100011101011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $this->obj->getBarcodeObj('C128C', chr(241).'42029651'.chr(241).'9405510200864168997758');
        $grid = $bobj->getGrid();
        $expected = "11010011100111101011101011011100011001100110101111000101101110100011110101110100010111101000"
            ."100110011011101000110011001101101100110011110100100110001000101000010011010111011110111101110101110"
            ."1100010111100101001100011101011\n";
        $this->assertEquals($expected, $grid);
    }

    /**
     * @expectedException \Com\Tecnick\Barcode\Exception
     */
    public function testInvalidLength()
    {
        $this->obj->getBarcodeObj('C128C', '12345678901');
    }

    /**
     * @expectedException \Com\Tecnick\Barcode\Exception
     */
    public function testInvalidChar()
    {
        $this->obj->getBarcodeObj('C128C', '1A2345678901');
    }
}
