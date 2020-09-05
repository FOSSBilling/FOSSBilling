<?php
/**
 * BTest.php
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
class BTest extends TestCase
{
    protected $obj = null;

    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test
        $this->obj = new \Com\Tecnick\Barcode\Barcode;
    }

    public function testGetGrid()
    {
        $bobj = $this->obj->getBarcodeObj('C128B', '0123456789');
        $grid = $bobj->getGrid();
        $expected = "11010010000100111011001001110011011001110010110010111001100100111011011100"
            ."10011001110100111011011101110100110011100101100110000101001100011101011\n";
        $this->assertEquals($expected, $grid);
        
        $bobj = $this->obj->getBarcodeObj('C128B', chr(241).'01234567891');
        $grid = $bobj->getGrid();
        $expected = "11010010000111101011101001110110010011100110110011100101100101110011001001"
            ."110110111001001100111010011101101110111010011001110010110010011100110100001100101100011101011\n";
        $this->assertEquals($expected, $grid);
    }

    /**
     * @expectedException \Com\Tecnick\Barcode\Exception
     */
    public function testInvalidInput()
    {
        $this->obj->getBarcodeObj('C128B', chr(246).'01234567891');
    }
}
