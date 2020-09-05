<?php
/**
 * CodeNineThreeTest.php
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
class CodeNineThreeTest extends TestCase
{
    protected $obj = null;

    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test
        $this->obj = new \Com\Tecnick\Barcode\Barcode;
    }

    public function testGetGrid()
    {
        $bobj = $this->obj->getBarcodeObj('C93', '0123456789');
        $grid = $bobj->getGrid();
        $expected = "101011110100010100101001000101000100101000010100101000100"
            ."1001001001000101010100001000100101000010101001011001001100101010111101\n";
        $this->assertEquals($expected, $grid);

        
        $bobj = $this->obj->getBarcodeObj('C93', '012345678901234567890123456789');
        $grid = $bobj->getGrid();
        $expected = "10101111010001010010100100010100010010100001010010100010010010010010001010101"
            ."000010001001010000101010001010010100100010100010010100001010010100010010010010010001"
            ."010101000010001001010000101010001010010100100010100010010100001010010100010010010010"
            ."01000101010100001000100101000010101001001001001010001010111101\n";
        $this->assertEquals($expected, $grid);
    }

    /**
     * @expectedException \Com\Tecnick\Barcode\Exception
     */
    public function testInvalidInput()
    {
        $this->obj->getBarcodeObj('C93', chr(255));
    }
}
