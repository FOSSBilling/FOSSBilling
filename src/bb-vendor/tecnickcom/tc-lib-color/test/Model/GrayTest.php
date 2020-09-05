<?php
/**
 * GrayTest.php
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Color
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-color
 *
 * This file is part of tc-lib-color software library.
 */

namespace Test\Model;

use PHPUnit\Framework\TestCase;

/**
 * Gray Color class test
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Color
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-color
 */
class GrayTest extends TestCase
{
    protected $obj = null;

    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test
        $this->obj = new \Com\Tecnick\Color\Model\Gray(
            array(
                'gray'  => 0.75,
                'alpha' => 0.85
            )
        );
    }

    public function testGetType()
    {
        $res = $this->obj->getType();
        $this->assertEquals('GRAY', $res);
    }

    public function testGetNormalizedValue()
    {
        $res = $this->obj->getNormalizedValue(0.5, 255);
        $this->assertEquals(128, $res);
    }

    public function testGetHexValue()
    {
        $res = $this->obj->getHexValue(0.5, 255);
        $this->assertEquals('80', $res);
    }

    public function testGetRgbaHexColor()
    {
        $res = $this->obj->getRgbaHexColor();
        $this->assertEquals('#bfbfbfd9', $res);
    }

    public function testGetRgbHexColor()
    {
        $res = $this->obj->getRgbHexColor();
        $this->assertEquals('#bfbfbf', $res);
    }

    public function testGetArray()
    {
        $res = $this->obj->getArray();
        $this->assertEquals(array('G' => 0.75, 'A' => 0.85), $res);
    }

    public function testGetNormalizedArray()
    {
        $res = $this->obj->getNormalizedArray(255);
        $this->assertEquals(array('G' => 191, 'A' => 0.85), $res);
    }

    public function testGetCssColor()
    {
        $res = $this->obj->getCssColor();
        $this->assertEquals('rgba(75%,75%,75%,0.85)', $res);
    }

    public function testGetJsPdfColor()
    {
        $res = $this->obj->getJsPdfColor();
        $this->assertEquals('["G",0.750000]', $res);

        $col = new \Com\Tecnick\Color\Model\Gray(
            array(
                'gray'    => 0.5,
                'alpha'   => 0
            )
        );
        $res = $col->getJsPdfColor();
        $this->assertEquals('["T"]', $res);
    }

    public function testGetComponentsString()
    {
        $res = $this->obj->getComponentsString();
        $this->assertEquals('0.750000', $res);
    }

    public function testGetPdfColor()
    {
        $res = $this->obj->getPdfColor();
        $this->assertEquals('0.750000 g'."\n", $res);
        
        $res = $this->obj->getPdfColor(false);
        $this->assertEquals('0.750000 g'."\n", $res);
        
        $res = $this->obj->getPdfColor(true);
        $this->assertEquals('0.750000 G'."\n", $res);
    }

    public function testToGrayArray()
    {
        $res = $this->obj->toGrayArray();
        $this->assertEquals(
            array(
                'gray'  => 0.75,
                'alpha' => 0.85
            ),
            $res
        );
    }

    public function testToRgbArray()
    {
        $res = $this->obj->toRgbArray();
        $this->assertEquals(
            array(
                'red'   => 0.75,
                'green' => 0.75,
                'blue'  => 0.75,
                'alpha' => 0.85
            ),
            $res
        );
    }

    public function testToHslArray()
    {
        $res = $this->obj->toHslArray();
        $this->assertEquals(
            array(
                'hue'        => 0,
                'saturation' => 0,
                'lightness'  => 0.75,
                'alpha'      => 0.85
            ),
            $res
        );
    }

    public function testToCmykArray()
    {
        $res = $this->obj->toCmykArray();
        $this->assertEquals(
            array(
                'cyan'    => 0,
                'magenta' => 0,
                'yellow'  => 0,
                'key'     => 0.75,
                'alpha'   => 0.85
            ),
            $res
        );
    }

    public function testInvertColor()
    {
        $this->obj->invertColor();
        $res = $this->obj->toGrayArray();
        $this->assertEquals(
            array(
                'gray'  => 0.25,
                'alpha' => 0.85
            ),
            $res
        );
    }
}
