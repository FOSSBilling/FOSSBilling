<?php
/**
 * SpotTest.php
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

namespace Test;

use PHPUnit\Framework\TestCase;

/**
 * Spot Color class test
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Color
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-color
 */
class SpotTest extends TestCase
{
    protected $obj = null;

    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test
        $this->obj = new \Com\Tecnick\Color\Spot;
    }

    public function testGetSpotColors()
    {
        $res = $this->obj->getSpotColors();
        $this->assertEquals(0, count($res));
    }

    public function testNormalizeSpotColorName()
    {
        $res = $this->obj->normalizeSpotColorName('abc.FG12!-345');
        $this->assertEquals('abcfg12345', $res);
    }

    public function testGetSpotColor()
    {
        $res = $this->obj->getSpotColor('none');
        $this->assertEquals('0.000000 0.000000 0.000000 0.000000 k'."\n", $res['color']->getPdfColor());
        $res = $this->obj->getSpotColor('all');
        $this->assertEquals('1.000000 1.000000 1.000000 1.000000 k'."\n", $res['color']->getPdfColor());
        $res = $this->obj->getSpotColor('red');
        $this->assertEquals('0.000000 1.000000 1.000000 0.000000 K'."\n", $res['color']->getPdfColor(true));
    }

    public function testGetSpotColorObj()
    {
        $res = $this->obj->getSpotColorObj('none');
        $this->assertEquals('0.000000 0.000000 0.000000 0.000000 k'."\n", $res->getPdfColor());
        $res = $this->obj->getSpotColorObj('all');
        $this->assertEquals('1.000000 1.000000 1.000000 1.000000 k'."\n", $res->getPdfColor());
        $res = $this->obj->getSpotColorObj('red');
        $this->assertEquals('0.000000 1.000000 1.000000 0.000000 K'."\n", $res->getPdfColor(true));
    }

    public function testAddSpotColor()
    {
        $cmyk = new \Com\Tecnick\Color\Model\Cmyk(
            array(
                'cyan'    => 0.666,
                'magenta' => 0.333,
                'yellow'  => 0,
                'key'     => 0.25,
                'alpha'   => 0.85
            )
        );
        $this->obj->addSpotColor('test', $cmyk);
        $res = $this->obj->getSpotColors();
        $this->assertArrayHasKey('test', $res);
        $this->assertEquals(1, $res['test']['i']);
        $this->assertEquals('test', $res['test']['name']);
        $this->assertEquals('0.666000 0.333000 0.000000 0.250000 k'."\n", $res['test']['color']->getPdfColor());

        // test overwrite
        $cmyk = new \Com\Tecnick\Color\Model\Cmyk(
            array(
                'cyan'    => 0.25,
                'magenta' => 0.35,
                'yellow'  => 0.45,
                'key'     => 0.55,
                'alpha'   => 0.65
            )
        );
        $this->obj->addSpotColor('test', $cmyk);
        $res = $this->obj->getSpotColors();
        $this->assertArrayHasKey('test', $res);
        $this->assertEquals(1, $res['test']['i']);
        $this->assertEquals('test', $res['test']['name']);
        $this->assertEquals('0.250000 0.350000 0.450000 0.550000 k'."\n", $res['test']['color']->getPdfColor());
    }

    public function testGetPdfSpotObjectsEmpty()
    {
        $obj = 1;
        $res = $this->obj->getPdfSpotObjects($obj);
        $this->assertEquals(1, $obj);
        $this->assertEquals('', $res);
    }

    public function testGetPdfSpotResourcesEmpty()
    {
        $res = $this->obj->getPdfSpotResources();
        $this->assertEquals('', $res);
    }

    public function testGetPdfSpotObjects()
    {
        $cmyk = new \Com\Tecnick\Color\Model\Cmyk(
            array(
                'cyan'    => 0.666,
                'magenta' => 0.333,
                'yellow'  => 0,
                'key'     => 0.25,
                'alpha'   => 0.85
            )
        );
        $this->obj->addSpotColor('test', $cmyk);
        $this->obj->getSpotColor('cyan');
        $this->obj->getSpotColor('magenta');
        $this->obj->getSpotColor('yellow');
        $this->obj->getSpotColor('key');

        $obj = 1;
        $res = $this->obj->getPdfSpotObjects($obj);
        $this->assertEquals(6, $obj);
        $this->assertEquals(
            '2 0 obj'."\n"
            .'[/Separation /test /DeviceCMYK <</Range [0 1 0 1 0 1 0 1] /C0 [0 0 0 0]'
            .' /C1 [0.666000 0.333000 0.000000 0.250000] /FunctionType 2 /Domain [0 1] /N 1>>]'."\n"
            .'endobj'."\n"
            .'3 0 obj'."\n"
            .'[/Separation /cyan /DeviceCMYK <</Range [0 1 0 1 0 1 0 1] /C0 [0 0 0 0]'
            .' /C1 [1.000000 0.000000 0.000000 0.000000] /FunctionType 2 /Domain [0 1] /N 1>>]'."\n"
            .'endobj'."\n"
            .'4 0 obj'."\n"
            .'[/Separation /magenta /DeviceCMYK <</Range [0 1 0 1 0 1 0 1] /C0 [0 0 0 0]'
            .' /C1 [0.000000 1.000000 0.000000 0.000000] /FunctionType 2 /Domain [0 1] /N 1>>]'."\n"
            .'endobj'."\n"
            .'5 0 obj'."\n"
            .'[/Separation /yellow /DeviceCMYK <</Range [0 1 0 1 0 1 0 1] /C0 [0 0 0 0]'
            .' /C1 [0.000000 0.000000 1.000000 0.000000] /FunctionType 2 /Domain [0 1] /N 1>>]'."\n"
            .'endobj'."\n"
            .'6 0 obj'."\n"
            .'[/Separation /key /DeviceCMYK <</Range [0 1 0 1 0 1 0 1] /C0 [0 0 0 0]'
            .' /C1 [0.000000 0.000000 0.000000 1.000000] /FunctionType 2 /Domain [0 1] /N 1>>]'."\n"
            .'endobj'."\n",
            $res
        );

        $res = $this->obj->getPdfSpotResources();
        $this->assertEquals('/ColorSpace << /CS1 2 0 R /CS2 3 0 R /CS3 4 0 R /CS4 5 0 R /CS5 6 0 R >>'."\n", $res);
    }
}
