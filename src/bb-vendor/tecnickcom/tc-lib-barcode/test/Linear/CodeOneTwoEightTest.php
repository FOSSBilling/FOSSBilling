<?php
/**
 * CodeOneTwoEightTest.php
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
 * @copyright   2015-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class CodeOneTwoEightTest extends TestCase
{
    protected $obj = null;

    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test
        $this->obj = new \Com\Tecnick\Barcode\Barcode;
    }

    public function testGetGrid()
    {
        $bobj = $this->obj->getBarcodeObj('C128', '0123456789');
        $grid = $bobj->getGrid();
        $expected = "110100111001100110110011101101110101110110001000010110011011011110100001101001100011101011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $this->obj->getBarcodeObj('C128', '1PBK500EI');
        $grid = $bobj->getGrid();
        $expected = "110100100001001110011011101110110100010110001011000111011011100100100111011001001"
            ."11011001000110100011000100010111011001001100011101011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $this->obj->getBarcodeObj('C128', 'SCB500J1C3Y');
        $grid = $bobj->getGrid();
        $expected = "110100100001101110100010001000110100010110001101110010010011101100100111011001011"
            ."011100010011100110100010001101100101110011101101000110110111101100011101011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $this->obj->getBarcodeObj('C128', '067023611120229212');
        $grid = $bobj->getGrid();
        $expected = "110100111001001100100010110000100111011011101100100001011000100100110010011101100111010010101111"
            ."00010110011100110110111101100011101011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $this->obj->getBarcodeObj('C128', 'Data:28102003');
        $grid = $bobj->getGrid();
        $expected = "110100100001011000100010010110000100111101001001011000011100100110101110111101110011010011001000"
            ."1001100100111010010011000100010001101100011101011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $this->obj->getBarcodeObj('C128', '12345678901');
        $grid = $bobj->getGrid();
        $expected = "110100100001001110011010111011110111011011101011101100010000101100110110111101100110110011001100"
                . "1101100011101011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $this->obj->getBarcodeObj('C128', '1234');
        $grid = $bobj->getGrid();
        $expected = "110100111001011001110010001011000100100111101100011101011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $this->obj->getBarcodeObj('C128', 'hello');
        $grid = $bobj->getGrid();
        $expected = "110100100001001100001010110010000110010100001100101000010001111010100010011001100011101011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $this->obj->getBarcodeObj('C128', 'HI345678');
        $grid = $bobj->getGrid();
        $expected = "110100100001100010100011000100010101110111101000101100011100010110110000101001000010011011000"
            ."11101011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $this->obj->getBarcodeObj('C128', 'HI34567A');
        $grid = $bobj->getGrid();
        $expected = "1101001000011000101000110001000101100101110010111011110101110110001000010110010111101110101000"
            ."11000100100011001100011101011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $this->obj->getBarcodeObj('C128', 'Barcode 1');
        $grid = $bobj->getGrid();
        $expected = "110100100001000101100010010110000100100111101000010110010001111010100001001101011001"
            ."00001101100110010011100110111011000101100011101011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $this->obj->getBarcodeObj('C128', "C1\tC2\tC3");
        $grid = $bobj->getGrid();
        $expected = "11010000100100010001101001110011010000110100100010001101100111001010000110100100010001101100101"
            ."1100100110011101100011101011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $this->obj->getBarcodeObj('C128', 'A1b2c3D4e5');
        $grid = $bobj->getGrid();
        $expected = "1101001000010100011000100111001101001000011011001110010100001011001100101110010110001"
            ."000110010011101011001000011011100100100001101001100011101011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $this->obj->getBarcodeObj('C128', chr(241).'0000801234999999999');
        $grid = $bobj->getGrid();
        $expected = "1101001000011110101110100111011001011101111011011001100100011001001100110110011101101"
            ."1101101000111010111011110101110111101011101111010111011110110100010001100011101011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $this->obj->getBarcodeObj('C128', chr(241)."000000\tABCDEF");
        $grid = $bobj->getGrid();
        $expected = "1101001110011110101110111101011101101100110011011001100110110011001110101111010000110100101000"
            ."110001000101100010001000110101100010001000110100010001100010100001100101100011101011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $this->obj->getBarcodeObj('C128', "\tABCD\tEFGH");
        $grid = $bobj->getGrid();
        $expected = "1101000010010000110100101000110001000101100010001000110101100010001000011010010001101000100011"
            ."000101101000100011000101000100011101101100011101011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $this->obj->getBarcodeObj('C128', "\tABCD\tabc\tABCdef");
        $grid = $bobj->getGrid();
        $expected = "1101000010010000110100101000110001000101100010001000110101100010001000011010010111101110100101"
            ."10000100100001101000010110011101011110100001101001010001100010001011000100010001101011110111010000100"
            ."1101011001000010110000100100001100101100011101011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $this->obj->getBarcodeObj('C128', "\tABCD\tabc\tdef");
        $grid = $bobj->getGrid();
        $expected = "1101000010010000110100101000110001000101100010001000110101100010001000011010010111101110100101"
            ."10000100100001101000010110011110100010100001101001000010011010110010000101100001001111010100011000111"
            ."01011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $this->obj->getBarcodeObj('C128', chr(241)."\tABCD");
        $grid = $bobj->getGrid();
        $expected = "1101000010011110101110111101011101000011010010100011000100010110001000100011010110001000110111"
            ."011101100011101011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $this->obj->getBarcodeObj('C128', "\ta");
        $grid = $bobj->getGrid();
        $expected = "11010000100100001101001111010001010010110000110111000101100011101011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $this->obj->getBarcodeObj('C128', chr(241).'00123456780000000001');
        $grid = $bobj->getGrid();
        $expected = "1101001110011110101110111101011101101100110010110011100100010110001110001011011000010100110110"
                    ."0110011011001100110110011001101100110011001101100100101111001100011101011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $this->obj->getBarcodeObj('C128', chr(241).'42029651'.chr(241).'9405510200864168997758');
        $grid = $bobj->getGrid();
        $expected = "11010011100111101011101111010111010110111000110011001101011110001011011101000101111011101111010"
                    ."1110101110111101000101111010001001100110111010001100110011011011001100111101001001100010001010"
                    ."000100110101110111101111011101011101100010100100110001100011101011\n";
        $this->assertEquals($expected, $grid);
    }
}
