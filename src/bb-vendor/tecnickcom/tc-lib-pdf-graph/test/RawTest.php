<?php
/**
 * RawTest.php
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
 * Raw Test
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfGraph
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-graph
 */
class RawTest extends TestCase
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

    public function testGetRawPoint()
    {
        $this->assertEquals('2.250000 71.250000 m'."\n", $this->obj->getRawPoint(3, 5));
    }

    public function testGetRawLine()
    {
        $this->assertEquals('2.250000 71.250000 l'."\n", $this->obj->getRawLine(3, 5));
    }

    public function testGetRawRect()
    {
        $this->assertEquals('2.250000 71.250000 5.250000 -8.250000 re'."\n", $this->obj->getRawRect(3, 5, 7, 11));
    }

    public function testGetRawCurve()
    {
        $this->assertEquals(
            '2.250000 71.250000 5.250000 66.750000 9.750000 62.250000 c'."\n",
            $this->obj->getRawCurve(3, 5, 7, 11, 13, 17)
        );
    }

    public function testGetRawCurveV()
    {
        $this->assertEquals('2.250000 71.250000 5.250000 66.750000 v'."\n", $this->obj->getRawCurveV(3, 5, 7, 11));
    }

    public function testGetRawCurveY()
    {
        $this->assertEquals('2.250000 71.250000 5.250000 66.750000 y'."\n", $this->obj->getRawCurveY(3, 5, 7, 11));
    }

    public function testGetRawEllipticalArc()
    {
        $res = $this->obj->getRawEllipticalArc(0, 0, 0, 0);
        $this->assertEquals('', $res);
    
        $res = $this->obj->getRawEllipticalArc(3, 5, 7, 11);
        $this->assertEquals(
            '7.500000 71.250000 m'."\n"
            .'7.500000 73.189135 7.064930 75.067534 6.271733 76.552998 c'."\n"
            .'5.478536 78.038462 4.376901 79.037937 3.161653 79.374664 c'."\n"
            .'1.946405 79.711391 0.693671 79.364277 -0.375000 78.394710 c'."\n"
            .'-1.443671 77.425142 -2.261335 75.893857 -2.683386 74.071666 c'."\n"
            .'-3.105437 72.249475 -3.105437 70.250525 -2.683386 68.428334 c'."\n"
            .'-2.261335 66.606143 -1.443671 65.074858 -0.375000 64.105290 c'."\n"
            .'0.693671 63.135723 1.946405 62.788609 3.161653 63.125336 c'."\n"
            .'4.376901 63.462063 5.478536 64.461538 6.271733 65.947002 c'."\n"
            .'7.064930 67.432466 7.500000 69.310865 7.500000 71.250000 c'."\n",
            $res
        );

        $bbox = array();
        $res = $this->obj->getRawEllipticalArc(
            3,
            5,
            7,
            11,
            0,
            -180,
            -90,
            true,
            1.8,
            false,
            false,
            true,
            $bbox
        );
        $this->assertEquals(
            '2.250000 71.250000 m'."\n"
            .'-3.000000 71.250000 l'."\n"
            .'-3.000000 73.118591 -2.596009 74.932867 -1.854615 76.393791 c'."\n"
            .'-1.113221 77.854714 -0.077525 78.877355 1.081765 79.293155 c'."\n"
            .'2.241055 79.708956 3.456544 79.493745 4.527890 78.682993 c'."\n"
            .'5.599235 77.872242 6.464154 76.513084 6.980087 74.829541 c'."\n"
            .'7.496019 73.145998 7.632972 71.235944 7.368372 69.414202 c'."\n"
            .'7.103771 67.592460 6.453000 65.964938 5.523321 64.799890 c'."\n"
            .'4.593643 63.634843 3.439104 63.000000 2.250000 63.000000 c'."\n"
            .'2.250000 71.250000 l'."\n",
            $res
        );

        $bbox = array();
        $res = $this->obj->getRawEllipticalArc(3, 5, 7, 11, 0, 90, 45);
        $this->assertEquals(
            '2.250000 79.500000 m'."\n"
            .'1.084822 79.500000 -0.047846 78.890447 -0.968406 77.767993 c'."\n"
            .'-1.888967 76.645539 -2.546049 75.072821 -2.835467 73.299209 c'."\n"
            .'-3.124884 71.525598 -3.030486 69.650067 -2.567240 67.970002 c'."\n"
            .'-2.103993 66.289938 -1.297750 64.899093 -0.276348 64.018002 c'."\n"
            .'0.745054 63.136911 1.924617 62.814741 3.075308 63.102576 c'."\n"
            .'4.225999 63.390411 5.283605 64.272189 6.080433 65.608093 c'."\n"
            .'6.877260 66.943998 7.368843 68.659482 7.477234 70.482537 c'."\n"
            .'7.585626 72.305591 7.304778 74.134484 6.679223 75.679223 c'."\n",
            $res
        );
    }

    public function testGetVectorsAngle()
    {
        $res = $this->obj->getVectorsAngle(0, 0, 0, 0);
        $this->assertEquals(0, $res, '', 0.01);
    
        $res = $this->obj->getVectorsAngle(0, 1, 0, 1);
        $this->assertEquals(0, $res, '', 0.01);

        $res = $this->obj->getVectorsAngle(1, 1, 2, 2);
        $this->assertEquals(0, $res, '', 0.01);

        $res = $this->obj->getVectorsAngle(1, 0, 0, 1);
        $this->assertEquals(1.57, $res, '', 0.01);

        $res = $this->obj->getVectorsAngle(0, 1, 1, 0);
        $this->assertEquals(-1.57, $res, '', 0.01);

        $res = $this->obj->getVectorsAngle(1, 0, 1, 1);
        $this->assertEquals(0.79, $res, '', 0.01);

        $res = $this->obj->getVectorsAngle(-1, -1, 1, 1);
        $this->assertEquals(M_PI, $res, '', 0.01);

        $res = $this->obj->getVectorsAngle(1, 0, -1, 0);
        $this->assertEquals(M_PI, $res, '', 0.01);
    }
}
