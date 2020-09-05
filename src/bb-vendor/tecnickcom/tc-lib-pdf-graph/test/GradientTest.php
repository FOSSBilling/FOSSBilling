<?php
/**
 * GradientTest.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfGraph
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-graph
 *
 * This file is part of tc-lib-pdf-graph software library.
 */

namespace Test;

use PHPUnit\Framework\TestCase;

/**
 * Gradient Test
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfGraph
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-graph
 */
class GradientTest extends TestCase
{
    protected $obj = null;

    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test
        $this->obj = new \Com\Tecnick\Pdf\Graph\Draw(
            0.75,
            80,
            100,
            new \Com\Tecnick\Color\Pdf(),
            new \Com\Tecnick\Pdf\Encrypt\Encrypt(),
            false
        );
    }

    public function testGetClippingRect()
    {
        $this->assertEquals(
            '2.250000 71.250000 5.250000 -8.250000 re W n'."\n",
            $this->obj->getClippingRect(3, 5, 7, 11)
        );
    }

    public function testGetGradientTransform()
    {
        $this->assertEquals(
            '5.250000 0.000000 0.000000 8.250000 2.250000 63.000000 cm'."\n",
            $this->obj->getGradientTransform(3, 5, 7, 11)
        );
    }

    public function testGetLinearGradient()
    {
        $this->assertEquals(
            'q'."\n"
            .'2.250000 71.250000 5.250000 -8.250000 re W n'."\n"
            .'5.250000 0.000000 0.000000 8.250000 2.250000 63.000000 cm'."\n"
            .'/Sh1 sh'."\n"
            .'Q'."\n",
            $this->obj->getLinearGradient(3, 5, 7, 11, 'red', 'green', array(1,2,3,4))
        );
    }

    public function testGetRadialGradient()
    {
        $this->assertEquals(
            'q'."\n"
            .'2.250000 71.250000 5.250000 -8.250000 re W n'."\n"
            .'5.250000 0.000000 0.000000 8.250000 2.250000 63.000000 cm'."\n"
            .'/Sh1 sh'."\n"
            .'Q'."\n",
            $this->obj->getRadialGradient(3, 5, 7, 11, 'red', 'green', array(0.6,0.5,0.4,0.3,1))
        );
    }

    public function testGetGradientPDFA()
    {
        $obj = new \Com\Tecnick\Pdf\Graph\Draw(
            0.75,
            80,
            100,
            new \Com\Tecnick\Color\Pdf(),
            new \Com\Tecnick\Pdf\Encrypt\Encrypt(),
            true
        );
        $this->assertEquals(
            '',
            $obj->getGradient(2, array(), array(), '', false)
        );
    }

    public function testGetGradient()
    {
        $stops = array(
            array('color' => 'red', 'exponent' => 1, 'opacity' => 0.5),
            array('color' => 'blue', 'offset' => 0.2, 'exponent' => 1, 'opacity' => 0.6),
            array('color' => '#98fb98', 'exponent' => 1, 'opacity' => 0.7),
            array('color' => 'rgb(64,128,191)', 'offset' => 0.8, 'exponent' => 1, 'opacity' => 0.8),
            array('color' => 'skyblue', 'exponent' => 1, 'opacity' => 0.9),
        );
        $this->assertEquals(
            '/TGS1 gs'."\n"
            .'/Sh1 sh'."\n",
            $this->obj->getGradient(2, array(0,0,1,0), $stops, '', false)
        );

        $exp = array (
            1 => array (
                'type' => 2,
                'coords' => array (
                    0 => 0,
                    1 => 0,
                    2 => 1,
                    3 => 0,
                ),
                'antialias' => false,
                'colors' => array (
                    0 => array (
                        'color' => 'red',
                        'exponent' => 1,
                        'opacity' => 0.5,
                        'offset' => 0,
                    ),
                    1 => array (
                        'color' => 'blue',
                        'exponent' => 1,
                        'opacity' => 0.60,
                        'offset' => 0.20,
                    ),
                    2 => array (
                        'color' => '#98fb98',
                        'exponent' => 1,
                        'opacity' => 0.70,
                        'offset' => 0.47,
                    ),
                    3 => array (
                        'color' => 'rgb(64,128,191)',
                        'exponent' => 1,
                        'opacity' => 0.80,
                        'offset' => 0.80,
                    ),
                    4 => array (
                        'color' => 'skyblue',
                        'exponent' => 1,
                        'opacity' => 0.90,
                        'offset' => 1,
                    ),
                ),
                'transparency' => true,
                'background' => null,
                'colspace' => 'DeviceCMYK',
            ),
        );
        $this->assertEquals($exp, $this->obj->getGradientsArray(), '', 0.01);
    }

    public function testGetCoonsPatchMeshPDFA()
    {
        $obj = new \Com\Tecnick\Pdf\Graph\Draw(
            0.75,
            80,
            100,
            new \Com\Tecnick\Color\Pdf(),
            new \Com\Tecnick\Pdf\Encrypt\Encrypt(),
            true
        );
        $this->assertEquals(
            '',
            $obj->getCoonsPatchMesh(3, 5, 7, 11)
        );
    }

    public function testGetCoonsPatchMesh()
    {
        $this->assertEquals(
            'q'."\n"
            .'2.250000 71.250000 5.250000 -8.250000 re W n'."\n"
            .'5.250000 0.000000 0.000000 8.250000 2.250000 63.000000 cm'."\n"
            .'/Sh1 sh'."\n"
            .'Q'."\n",
            $this->obj->getCoonsPatchMesh(3, 5, 7, 11)
        );

        $patch_array = array (
            0 => array (
                'f' => 0,
                'points' => array (
                    0 => 0.0,
                    1 => 0.0,
                    2 => 0.33,
                    3 => 0.0,
                    4 => 0.67,
                    5 => 0.0,
                    6 => 1.0,
                    7 => 0.0,
                    8 => 1.0,
                    9 => 0.33,
                    10 => 0.80,
                    11 => 0.67,
                    12 => 1.0,
                    13 => 1.0,
                    14 => 0.67,
                    15 => 0.800,
                    16 => 0.33,
                    17 => 1.8,
                    18 => 0.0,
                    19 => 1.0,
                    20 => 0.0,
                    21 => 0.67,
                    22 => 0.0,
                    23 => 0.33,
                ),
                'colors' => array (
                    0 => array (
                        'red' => 255,
                        'green' => 255,
                        'blue' => 0,
                    ),
                    1 => array (
                        'red' => 0,
                        'green' => 0,
                        'blue' => 255,
                    ),
                    2 => array (
                        'red' => 0,
                        'green' => 255,
                        'blue' => 0,
                    ),
                    3 => array (
                        'red' => 255,
                        'green' => 0,
                        'blue' => 0,
                    ),
                ),
            ),
            1 => array (
                'f' => 2,
                'points' => array (
                    0 => 0.0,
                    1 => 1.33,
                    2 => 0.0,
                    3 => 1.67,
                    4 => 0.0,
                    5 => 2.0,
                    6 => 0.33,
                    7 => 2.0,
                    8 => 0.67,
                    9 => 2.0,
                    10 => 1.0,
                    11 => 2.0,
                    12 => 1.0,
                    13 => 1.67,
                    14 => 1.5,
                    15 => 1.33,
                ),
                'colors' => array (
                    0 => array (
                        'red' => 0,
                        'green' => 0,
                        'blue' => 0,
                    ),
                    1 => array (
                        'red' => 255,
                        'green' => 0,
                        'blue' => 255,
                    ),
                ),
            ),
            2 => array (
                'f' => 3,
                'points' => array (
                    0 => 1.33,
                    1 => 0.80,
                    2 => 1.67,
                    3 => 1.5,
                    4 => 2.0,
                    5 => 1.0,
                    6 => 2.0,
                    7 => 1.33,
                    8 => 2.0,
                    9 => 1.67,
                    10 => 2.0,
                    11 => 2.0,
                    12 => 1.66,
                    13 => 2.0,
                    14 => 1.33,
                    15 => 2.0,
                ),
                'colors' => array (
                    0 => array (
                        'red' => 0,
                        'green' => 255,
                        'blue' => 255,
                    ),
                    1 => array (
                        'red' => 0,
                        'green' => 0,
                        'blue' => 0,
                    ),
                ),
            ),
            3 => array (
                'f' => 1,
                'points' => array (
                    0 => 2.0,
                    1 => 0.67,
                    2 => 2.0,
                    3 => 0.33,
                    4 => 2.0,
                    5 => 0.0,
                    6 => 1.67,
                    7 => 0.0,
                    8 => 1.33,
                    9 => 0.0,
                    10 => 1.0,
                    11 => 0.0,
                    12 => 1.0,
                    13 => 0.33,
                    14 => 0.80,
                    15 => 0.67,
                ),
                'colors' => array (
                    0 => array (
                        'red' => 0,
                        'green' => 0,
                        'blue' => 0,
                    ),
                    1 => array (
                        'red' => 0,
                        'green' => 0,
                        'blue' => 255,
                    ),
                ),
            ),
        );

        $this->assertEquals(
            'q'."\n"
            .'7.500000 41.250000 142.500000 -150.000000 re W n'."\n"
            .'142.500000 0.000000 0.000000 150.000000 7.500000 -108.750000 cm'."\n"
            .'/Sh2 sh'."\n"
            .'Q'."\n",
            $this->obj->getCoonsPatchMesh(10, 45, 190, 200, '', '', '', '', $patch_array, 0, 2)
        );
    }

    public function testGetColorRegistrationBar()
    {
        $res = $this->obj->getColorRegistrationBar(50, 70, 40, 40);
        $this->assertEquals(
            'q'."\n"
            .'37.500000 22.500000 30.000000 -3.750000 re W n'."\n"
            .'30.000000 0.000000 0.000000 3.750000 37.500000 18.750000 cm'."\n"
            .'/Sh1 sh'."\n"
            .'Q'."\n"
            .'q'."\n"
            .'37.500000 18.750000 30.000000 -3.750000 re W n'."\n"
            .'30.000000 0.000000 0.000000 3.750000 37.500000 15.000000 cm'."\n"
            .'/Sh2 sh'."\n"
            .'Q'."\n"
            .'q'."\n"
            .'37.500000 15.000000 30.000000 -3.750000 re W n'."\n"
            .'30.000000 0.000000 0.000000 3.750000 37.500000 11.250000 cm'."\n"
            .'/Sh3 sh'."\n"
            .'Q'."\n"
            .'q'."\n"
            .'37.500000 11.250000 30.000000 -3.750000 re W n'."\n"
            .'30.000000 0.000000 0.000000 3.750000 37.500000 7.500000 cm'."\n"
            .'/Sh4 sh'."\n"
            .'Q'."\n"
            .'q'."\n"
            .'37.500000 7.500000 30.000000 -3.750000 re W n'."\n"
            .'30.000000 0.000000 0.000000 3.750000 37.500000 3.750000 cm'."\n"
            .'/Sh5 sh'."\n"
            .'Q'."\n"
            .'q'."\n"
            .'37.500000 3.750000 30.000000 -3.750000 re W n'."\n"
            .'30.000000 0.000000 0.000000 3.750000 37.500000 0.000000 cm'."\n"
            .'/Sh6 sh'."\n"
            .'Q'."\n"
            .'q'."\n"
            .'37.500000 0.000000 30.000000 -3.750000 re W n'."\n"
            .'30.000000 0.000000 0.000000 3.750000 37.500000 -3.750000 cm'."\n"
            .'/Sh7 sh'."\n"
            .'Q'."\n"
            .'q'."\n"
            .'37.500000 -3.750000 30.000000 -3.750000 re W n'."\n"
            .'30.000000 0.000000 0.000000 3.750000 37.500000 -7.500000 cm'."\n"
            .'/Sh8 sh'."\n"
            .'Q'."\n",
            $res
        );

        $res = $this->obj->getColorRegistrationBar(50, 70, 40, 40, true);
        $this->assertEquals(
            'q'."\n"
            .'37.500000 22.500000 3.750000 -30.000000 re W n'."\n"
            .'3.750000 0.000000 0.000000 30.000000 37.500000 -7.500000 cm'."\n"
            .'/Sh9 sh'."\n"
            .'Q'."\n"
            .'q'."\n"
            .'41.250000 22.500000 3.750000 -30.000000 re W n'."\n"
            .'3.750000 0.000000 0.000000 30.000000 41.250000 -7.500000 cm'."\n"
            .'/Sh10 sh'."\n"
            .'Q'."\n"
            .'q'."\n"
            .'45.000000 22.500000 3.750000 -30.000000 re W n'."\n"
            .'3.750000 0.000000 0.000000 30.000000 45.000000 -7.500000 cm'."\n"
            .'/Sh11 sh'."\n"
            .'Q'."\n"
            .'q'."\n"
            .'48.750000 22.500000 3.750000 -30.000000 re W n'."\n"
            .'3.750000 0.000000 0.000000 30.000000 48.750000 -7.500000 cm'."\n"
            .'/Sh12 sh'."\n"
            .'Q'."\n"
            .'q'."\n"
            .'52.500000 22.500000 3.750000 -30.000000 re W n'."\n"
            .'3.750000 0.000000 0.000000 30.000000 52.500000 -7.500000 cm'."\n"
            .'/Sh13 sh'."\n"
            .'Q'."\n"
            .'q'."\n"
            .'56.250000 22.500000 3.750000 -30.000000 re W n'."\n"
            .'3.750000 0.000000 0.000000 30.000000 56.250000 -7.500000 cm'."\n"
            .'/Sh14 sh'."\n"
            .'Q'."\n"
            .'q'."\n"
            .'60.000000 22.500000 3.750000 -30.000000 re W n'."\n"
            .'3.750000 0.000000 0.000000 30.000000 60.000000 -7.500000 cm'."\n"
            .'/Sh15 sh'."\n"
            .'Q'."\n"
            .'q'."\n"
            .'63.750000 22.500000 3.750000 -30.000000 re W n'."\n"
            .'3.750000 0.000000 0.000000 30.000000 63.750000 -7.500000 cm'."\n"
            .'/Sh16 sh'."\n"
            .'Q'."\n",
            $res
        );

        $res = $this->obj->getColorRegistrationBar(
            50,
            70,
            40,
            40,
            true,
            array(
                '',
                'g(50%)',
                'rgb(50%,50%,50%)',
                'cmyk(50%,50%,50,50%)',
                array('rgb(100%,0%,0%)'),
                array('red', 'white'),
                array('black', 'black'),
                array('g(11%)', 'g(11%)'),
                array('rgb(30%,50%,70%)', 'rgb(170%,150%,130%)'),
                array('cmyk(10%,20%,30,40%)', 'cmyk(100%,90%,80,70%)'),
                array(),
            )
        );
        $this->assertEquals(
            'q'."\n"
            .'0.500000 g'."\n"
            .'40.227273 22.500000 m'."\n"
            .'42.954545 22.500000 l'."\n"
            .'42.954545 -7.500000 l'."\n"
            .'40.227273 -7.500000 l'."\n"
            .'40.227273 22.500000 l'."\n"
            .'f'."\n"
            .'40.227273 22.500000 m'."\n"
            .'42.954545 22.500000 l'."\n"
            .'S'."\n"
            .'42.954545 22.500000 m'."\n"
            .'42.954545 -7.500000 l'."\n"
            .'S'."\n"
            .'42.954545 -7.500000 m'."\n"
            .'40.227273 -7.500000 l'."\n"
            .'S'."\n"
            .'40.227273 -7.500000 m'."\n"
            .'40.227273 22.500000 l'."\n"
            .'S'."\n"
            .'Q'."\n"
            .'q'."\n"
            .'0.500000 0.500000 0.500000 rg'."\n"
            .'42.954545 22.500000 m'."\n"
            .'45.681818 22.500000 l'."\n"
            .'45.681818 -7.500000 l'."\n"
            .'42.954545 -7.500000 l'."\n"
            .'42.954545 22.500000 l'."\n"
            .'f'."\n"
            .'42.954545 22.500000 m'."\n"
            .'45.681818 22.500000 l'."\n"
            .'S'."\n"
            .'45.681818 22.500000 m'."\n"
            .'45.681818 -7.500000 l'."\n"
            .'S'."\n"
            .'45.681818 -7.500000 m'."\n"
            .'42.954545 -7.500000 l'."\n"
            .'S'."\n"
            .'42.954545 -7.500000 m'."\n"
            .'42.954545 22.500000 l'."\n"
            .'S'."\n"
            .'Q'."\n"
            .'q'."\n"
            .'0.500000 0.500000 0.500000 0.500000 k'."\n"
            .'45.681818 22.500000 m'."\n"
            .'48.409091 22.500000 l'."\n"
            .'48.409091 -7.500000 l'."\n"
            .'45.681818 -7.500000 l'."\n"
            .'45.681818 22.500000 l'."\n"
            .'f'."\n"
            .'45.681818 22.500000 m'."\n"
            .'48.409091 22.500000 l'."\n"
            .'S'."\n"
            .'48.409091 22.500000 m'."\n"
            .'48.409091 -7.500000 l'."\n"
            .'S'."\n"
            .'48.409091 -7.500000 m'."\n"
            .'45.681818 -7.500000 l'."\n"
            .'S'."\n"
            .'45.681818 -7.500000 m'."\n"
            .'45.681818 22.500000 l'."\n"
            .'S'."\n"
            .'Q'."\n"
            .'q'."\n"
            .'1.000000 0.000000 0.000000 rg'."\n"
            .'48.409091 22.500000 m'."\n"
            .'51.136364 22.500000 l'."\n"
            .'51.136364 -7.500000 l'."\n"
            .'48.409091 -7.500000 l'."\n"
            .'48.409091 22.500000 l'."\n"
            .'f'."\n"
            .'48.409091 22.500000 m'."\n"
            .'51.136364 22.500000 l'."\n"
            .'S'."\n"
            .'51.136364 22.500000 m'."\n"
            .'51.136364 -7.500000 l'."\n"
            .'S'."\n"
            .'51.136364 -7.500000 m'."\n"
            .'48.409091 -7.500000 l'."\n"
            .'S'."\n"
            .'48.409091 -7.500000 m'."\n"
            .'48.409091 22.500000 l'."\n"
            .'S'."\n"
            .'Q'."\n"
            .'q'."\n"
            .'51.136364 22.500000 2.727273 -30.000000 re W n'."\n"
            .'2.727273 0.000000 0.000000 30.000000 51.136364 -7.500000 cm'."\n"
            .'/Sh17 sh'."\n"
            .'Q'."\n"
            .'q'."\n"
            .'0.000000 0.000000 0.000000 1.000000 k'."\n"
            .'53.863636 22.500000 m'."\n"
            .'56.590909 22.500000 l'."\n"
            .'56.590909 -7.500000 l'."\n"
            .'53.863636 -7.500000 l'."\n"
            .'53.863636 22.500000 l'."\n"
            .'f'."\n"
            .'53.863636 22.500000 m'."\n"
            .'56.590909 22.500000 l'."\n"
            .'S'."\n"
            .'56.590909 22.500000 m'."\n"
            .'56.590909 -7.500000 l'."\n"
            .'S'."\n"
            .'56.590909 -7.500000 m'."\n"
            .'53.863636 -7.500000 l'."\n"
            .'S'."\n"
            .'53.863636 -7.500000 m'."\n"
            .'53.863636 22.500000 l'."\n"
            .'S'."\n"
            .'Q'."\n"
            .'q'."\n"
            .'0.110000 g'."\n"
            .'56.590909 22.500000 m'."\n"
            .'59.318182 22.500000 l'."\n"
            .'59.318182 -7.500000 l'."\n"
            .'56.590909 -7.500000 l'."\n"
            .'56.590909 22.500000 l'."\n"
            .'f'."\n"
            .'56.590909 22.500000 m'."\n"
            .'59.318182 22.500000 l'."\n"
            .'S'."\n"
            .'59.318182 22.500000 m'."\n"
            .'59.318182 -7.500000 l'."\n"
            .'S'."\n"
            .'59.318182 -7.500000 m'."\n"
            .'56.590909 -7.500000 l'."\n"
            .'S'."\n"
            .'56.590909 -7.500000 m'."\n"
            .'56.590909 22.500000 l'."\n"
            .'S'."\n"
            .'Q'."\n"
            .'q'."\n"
            .'59.318182 22.500000 2.727273 -30.000000 re W n'."\n"
            .'2.727273 0.000000 0.000000 30.000000 59.318182 -7.500000 cm'."\n"
            .'/Sh18 sh'."\n"
            .'Q'."\n"
            .'q'."\n"
            .'62.045455 22.500000 2.727273 -30.000000 re W n'."\n"
            .'2.727273 0.000000 0.000000 30.000000 62.045455 -7.500000 cm'."\n"
            .'/Sh19 sh'."\n"
            .'Q'."\n",
            $res
        );

        $res = $this->obj->getColorRegistrationBar(
            50,
            70,
            40,
            40,
            false,
            array()
        );
        $this->assertEquals('', $res);
    }

    public function testGetCropMark()
    {
        $res = $this->obj->getCropMark(3, 5, 7, 11, '');
        $this->assertEquals('', $res);

        $res = $this->obj->getCropMark(3, 5, 7, 11, 'TBLR');
        $this->assertEquals(
            'q'."\n"
            .'2.250000 79.500000 m'."\n"
            .'2.250000 73.312500 l'."\n"
            .'S'."\n"
            .'2.250000 69.187500 m'."\n"
            .'2.250000 63.000000 l'."\n"
            .'S'."\n"
            .'-3.000000 71.250000 m'."\n"
            .'0.937500 71.250000 l'."\n"
            .'S'."\n"
            .'3.562500 71.250000 m'."\n"
            .'7.500000 71.250000 l'."\n"
            .'S'."\n"
            .'Q'."\n",
            $res
        );
     
        $style = array(
            'lineWidth' => 0.3,
            'lineColor' => 'black',
            'lineCap'   => 'butt',
            'lineJoin'  => 'miter',
        );
        $res = $this->obj->getCropMark(3, 5, 7, 11, 'TBLR', $style);
        $this->assertEquals(
            'q'."\n"
            .'0.225000 w'."\n"
            .'0 J'."\n"
            .'0 j'."\n"
            .'/CS1 CS 1.000000 SCN'."\n"
            .'2.250000 79.500000 m'."\n"
            .'2.250000 73.312500 l'."\n"
            .'S'."\n"
            .'2.250000 69.187500 m'."\n"
            .'2.250000 63.000000 l'."\n"
            .'S'."\n"
            .'-3.000000 71.250000 m'."\n"
            .'0.937500 71.250000 l'."\n"
            .'S'."\n"
            .'3.562500 71.250000 m'."\n"
            .'7.500000 71.250000 l'."\n"
            .'S'."\n"
            .'Q'."\n",
            $res
        );
    }

    public function testGetOverprint()
    {
        $res = $this->obj->getOverprint();
        $this->assertEquals(
            '/GS1 gs'."\n",
            $res
        );

        $res = $this->obj->getOverprint(false, true, 1);
        $this->assertEquals(
            '/GS2 gs'."\n",
            $res
        );
    }

    public function testGetAlpha()
    {
        $res = $this->obj->getAlpha();
        $this->assertEquals(
            '/GS1 gs'."\n",
            $res
        );

        $res = $this->obj->getAlpha(0.5, '/Missing', 0.4, true);
        $this->assertEquals(
            '/GS2 gs'."\n",
            $res
        );
    }
}
