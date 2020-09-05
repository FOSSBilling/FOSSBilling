<?php
/**
 * TransformTest.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfGraph
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2017 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-graph
 *
 * This file is part of tc-lib-pdf-graph software library.
 */

namespace Test;

use PHPUnit\Framework\TestCase;

/**
 * Transform Test
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfGraph
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2017 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-graph
 */
class TransformTest extends TestCase
{
    protected $obj = null;

    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test
        $this->obj = new \Com\Tecnick\Pdf\Graph\Draw(
            1,
            0,
            0,
            new \Com\Tecnick\Color\Pdf(),
            new \Com\Tecnick\Pdf\Encrypt\Encrypt(),
            false
        );
        $this->assertEquals(-1, $this->obj->getTransformIndex());
        $this->assertEquals('q'."\n", $this->obj->getStartTransform());
    }

    public function tearDown()
    {
        $this->assertEquals('Q'."\n", $this->obj->getStopTransform());
        $this->assertEquals(-1, $this->obj->getTransformIndex());
    }

    public function testGetStartStopTransform()
    {
        $obj = new \Com\Tecnick\Pdf\Graph\Draw(
            1,
            0,
            0,
            new \Com\Tecnick\Color\Pdf(),
            new \Com\Tecnick\Pdf\Encrypt\Encrypt(),
            false
        );
        $this->assertEquals(-1, $obj->getTransformIndex());
        $this->assertEquals('q'."\n", $obj->getStartTransform());
        $this->assertEquals(0, $obj->getTransformIndex());

        $tmx = array(0.1, 1.2, 2.3, 3.4, 4.5, 5.6);
        $this->assertEquals(
            '0.100000 1.200000 2.300000 3.400000 4.500000 5.600000 cm'."\n",
            $obj->getTransformation($tmx)
        );

        $this->assertEquals(
            array(0 => array(0 => array(0.1, 1.2, 2.3, 3.4, 4.5, 5.6))),
            $obj->getTransformStack(),
            '',
            0.0001
        );

        $this->assertEquals('Q'."\n", $obj->getStopTransform());
        $this->assertEquals(-1, $obj->getTransformIndex());
        $this->assertEquals('', $obj->getStopTransform());
        $this->assertEquals(-1, $obj->getTransformIndex());
    }
 
 
    public function testGetTransform()
    {
        $tmx = array(0.1, 1.2, 2.3, 3.4, 4.5, 5.6);
        $this->assertEquals(
            '0.100000 1.200000 2.300000 3.400000 4.500000 5.600000 cm'."\n",
            $this->obj->getTransformation($tmx)
        );
    }

    public function testSetPageHeight()
    {
        $obj = new \Com\Tecnick\Pdf\Graph\Draw(
            1,
            0,
            0,
            new \Com\Tecnick\Color\Pdf(),
            new \Com\Tecnick\Pdf\Encrypt\Encrypt(),
            false
        );
        $obj->setPageHeight(100);
        $this->assertEquals('q'."\n", $obj->getStartTransform());
        $this->assertEquals(
            '3.000000 0.000000 0.000000 5.000000 -14.000000 -356.000000 cm'."\n",
            $obj->getScaling(3, 5, 7, 11)
        );
    }

    public function testSetKUnit()
    {
        $obj = new \Com\Tecnick\Pdf\Graph\Draw(
            1,
            0,
            0,
            new \Com\Tecnick\Color\Pdf(),
            new \Com\Tecnick\Pdf\Encrypt\Encrypt(),
            false
        );
        $obj->setKUnit(0.75);
        $this->assertEquals('q'."\n", $obj->getStartTransform());
        $this->assertEquals(
            '3.000000 0.000000 0.000000 5.000000 -10.500000 33.000000 cm'."\n",
            $obj->getScaling(3, 5, 7, 11)
        );
    }

    public function testGetScaling()
    {
        $this->assertEquals(
            '3.000000 0.000000 0.000000 5.000000 -14.000000 44.000000 cm'."\n",
            $this->obj->getScaling(3, 5, 7, 11)
        );
        $this->assertEquals(
            '3.000000 0.000000 0.000000 3.000000 -14.000000 22.000000 cm'."\n",
            $this->obj->getScaling(3, 3, 7, 11)
        );
    }

    /**
     * @expectedException \Com\Tecnick\Pdf\Graph\Exception
     */
    public function testGetScalingEx()
    {
        $this->obj->getScaling(0, 0, 7, 11);
    }

    public function testGetHorizScaling()
    {
        $this->assertEquals(
            '3.000000 0.000000 0.000000 1.000000 -14.000000 0.000000 cm'."\n",
            $this->obj->getHorizScaling(3, 7, 11)
        );
    }

    public function testGetVertScaling()
    {
        $this->assertEquals(
            '1.000000 0.000000 0.000000 5.000000 0.000000 44.000000 cm'."\n",
            $this->obj->getVertScaling(5, 7, 11)
        );
    }

    public function testGetPropScaling()
    {
        $this->assertEquals(
            '3.000000 0.000000 0.000000 3.000000 -14.000000 22.000000 cm'."\n",
            $this->obj->getPropScaling(3, 7, 11)
        );
    }

    public function testGetRotation()
    {
        $this->assertEquals(
            '0.707107 0.707107 -0.707107 0.707107 -5.727922 -8.171573 cm'."\n",
            $this->obj->getRotation(45, 7, 11)
        );
    }

    public function testGetHorizMirroring()
    {
        $this->assertEquals(
            '-1.000000 0.000000 0.000000 1.000000 14.000000 0.000000 cm'."\n",
            $this->obj->getHorizMirroring(7)
        );
    }

    public function testGetVertMirroring()
    {
        $this->assertEquals(
            '1.000000 0.000000 0.000000 -1.000000 0.000000 -22.000000 cm'."\n",
            $this->obj->getVertMirroring(11)
        );
    }

    public function testGetPointMirroring()
    {
        $this->assertEquals(
            '-1.000000 0.000000 0.000000 -1.000000 14.000000 -22.000000 cm'."\n",
            $this->obj->getPointMirroring(7, 11)
        );
    }

    public function testGetReflection()
    {
        $this->assertEquals(
            '-1.000000 0.000000 0.000000 1.000000 14.000000 0.000000 cm'."\n"
            .'0.000000 1.000000 -1.000000 0.000000 -4.000000 -18.000000 cm'."\n",
            $this->obj->getReflection(45, 7, 11)
        );
    }

    public function testGetTranslation()
    {
        $this->assertEquals(
            '1.000000 0.000000 0.000000 1.000000 3.000000 -5.000000 cm'."\n",
            $this->obj->getTranslation(3, 5)
        );
    }

    public function testGetHorizTranslation()
    {
        $this->assertEquals(
            '1.000000 0.000000 0.000000 1.000000 3.000000 0.000000 cm'."\n",
            $this->obj->getHorizTranslation(3)
        );
    }

    public function testGetVertTranslation()
    {
        $this->assertEquals(
            '1.000000 0.000000 0.000000 1.000000 0.000000 -5.000000 cm'."\n",
            $this->obj->getVertTranslation(5)
        );
    }

    public function testGetSkewing()
    {
        $this->assertEquals(
            '1.000000 0.087489 0.052408 1.000000 0.576486 -0.612421 cm'."\n",
            $this->obj->getSkewing(3, 5, 7, 11)
        );
    }

    /**
     * @expectedException \Com\Tecnick\Pdf\Graph\Exception
     */
    public function testGetSkewingEx()
    {
        $this->obj->getSkewing(90, -90, 7, 11);
    }

    public function testGetHorizSkewing()
    {
        $this->assertEquals(
            '1.000000 0.000000 0.052408 1.000000 0.576486 0.000000 cm'."\n",
            $this->obj->getHorizSkewing(3, 7, 11)
        );
    }

    public function testGetVertSkewing()
    {
        $this->assertEquals(
            '1.000000 0.087489 0.000000 1.000000 0.000000 -0.612421 cm'."\n",
            $this->obj->getVertSkewing(5, 7, 11)
        );
    }

    public function testGetCtmProduct()
    {
        $tma = array(3.1, 5.2, 7.3, 11.4, 13.5, 17.6);
        $tmb = array(19.1, 23.2, 29.3, 31.4, 37.5, 41.6);
        $ctm = $this->obj->getCtmProduct($tma, $tmb);
        $this->assertEquals(array(228.570, 363.800, 320.050, 510.320, 433.430, 686.840), $ctm, '', 0.001);
    }
}
