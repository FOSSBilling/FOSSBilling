<?php
/**
 * ImbPreTest.php
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
class ImbPreTest extends TestCase
{
    protected $obj = null;

    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test
        $this->obj = new \Com\Tecnick\Barcode\Barcode;
    }

    public function testGetGrid()
    {
        $bobj = $this->obj->getBarcodeObj(
            'IMBPRE',
            'fatdfatdfatdfatdfatdfatdfatdfatdfatdfatdfatdfatdfatdfatdfatdfatdf'
        );
        $grid = $bobj->getGrid();
        $expected = "101000001010000010100000101000001010000010100000101000001010"
            ."000010100000101000001010000010100000101000001010000010100000101000001\n"
            ."1010101010101010101010101010101010101010101010101010101010101010101"
            ."01010101010101010101010101010101010101010101010101010101010101\n"
            ."1000001010000010100000101000001010000010100000101000001010000010100"
            ."00010100000101000001010000010100000101000001010000010100000101\n";
        $this->assertEquals($expected, $grid);
    }

    /**
     * @expectedException \Com\Tecnick\Barcode\Exception
     */
    public function testInvalidInput()
    {
        $this->obj->getBarcodeObj('IMBPRE', 'fatd');
    }
}
