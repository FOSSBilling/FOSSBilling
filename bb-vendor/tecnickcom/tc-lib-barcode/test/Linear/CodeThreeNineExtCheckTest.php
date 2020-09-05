<?php
/**
 * CodeThreeNineExtCheckTest.php
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
class CodeThreeNineExtCheckTest extends TestCase
{
    protected $obj = null;

    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test
        $this->obj = new \Com\Tecnick\Barcode\Barcode;
    }

    public function testGetGrid()
    {
        $bobj = $this->obj->getBarcodeObj('C39E+', '0123456789');
        $grid = $bobj->getGrid();
        $expected = "10001011101110101010001110111010111010001010111010111000101011101110111000101010101000"
            ."111010111011101000111010101011100011101010101000101110111011101000101110101011100010111010101"
            ."1100010101110100010111011101\n";
        $this->assertEquals($expected, $grid);
    }

    /**
     * @expectedException \Com\Tecnick\Barcode\Exception
     */
    public function testInvalidInput()
    {
        $this->obj->getBarcodeObj('C39E+', chr(218));
    }
}
