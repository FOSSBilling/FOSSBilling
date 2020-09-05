<?php
/**
 * RegionTest.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfPage
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-page
 *
 * This file is part of tc-lib-pdf-page software library.
 */

namespace Test;

use PHPUnit\Framework\TestCase;

/**
 * Page Test
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfPage
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-page
 */
class RegionTest extends TestCase
{
    protected $obj = null;

    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test

        $col = new \Com\Tecnick\Color\Pdf;
        $enc = new \Com\Tecnick\Pdf\Encrypt\Encrypt(false);
        $this->obj = new \Com\Tecnick\Pdf\Page\Page('mm', $col, $enc, false, false);
    }

    public function testRegion()
    {
        $this->obj->add(array('columns' => 3));
        $res = $this->obj->selectRegion(1);
        $exp = array(
            'RX' => 70,
            'RY' => 0,
            'RW' => 70,
            'RH' => 297,
            'RL' => 140,
            'RR' => 70,
            'RT' => 297,
            'RB' => 0,
            'x'  => 70,
            'y'  => 0,
        );
        $this->assertEquals($exp, $res, '', 0.01);

        $res = $this->obj->getCurrentRegion();
        $this->assertEquals($exp, $res, '', 0.01);

        $res = $this->obj->getNextRegion();
        $this->assertEquals(2, $res['currentRegion'], '', 0.01);

        $res = $this->obj->getNextRegion();
        $this->assertEquals(0, $res['currentRegion'], '', 0.01);

        $this->obj->setCurrentPage(0);
        $res = $this->obj->getNextRegion();
        $this->assertEquals(0, $res['currentRegion'], '', 0.01);

        $res = $this->obj->checkRegionBreak(1000);
        $this->assertEquals(1, $res['currentRegion'], '', 0.01);

        $res = $this->obj->checkRegionBreak();
        $this->assertEquals(1, $res['currentRegion'], '', 0.01);

        $this->obj->setX(13)->setY(17);
        $this->assertEquals(13, $this->obj->getX(), '', 0.01);
        $this->assertEquals(17, $this->obj->getY(), '', 0.01);
    }

    public function testRegionBoundaries()
    {
        $this->obj->add(array('columns' => 3));
        $region = $this->obj->getCurrentRegion();

        $res = $this->obj->isYOutRegion(null, 1);
        $this->assertFalse($res);
        $res = $this->obj->isYOutRegion(-1);
        $this->assertTrue($res);
        $res = $this->obj->isYOutRegion($region['RY']);
        $this->assertFalse($res);
        $res = $this->obj->isYOutRegion(0);
        $this->assertFalse($res);
        $res = $this->obj->isYOutRegion(100);
        $this->assertFalse($res);
        $res = $this->obj->isYOutRegion(297);
        $this->assertFalse($res);
        $res = $this->obj->isYOutRegion($region['RT']);
        $this->assertFalse($res);
        $res = $this->obj->isYOutRegion(298);
        $this->assertTrue($res);

        $this->obj->getNextRegion();
        $region = $this->obj->getCurrentRegion();

        $res = $this->obj->isXOutRegion(null, 1);
        $this->assertFalse($res);
        $res = $this->obj->isXOutRegion(69);
        $this->assertTrue($res);
        $res = $this->obj->isXOutRegion($region['RX']);
        $this->assertFalse($res);
        $res = $this->obj->isXOutRegion(70);
        $this->assertFalse($res);
        $res = $this->obj->isXOutRegion(90);
        $this->assertFalse($res);
        $res = $this->obj->isXOutRegion(140);
        $this->assertFalse($res);
        $res = $this->obj->isXOutRegion($region['RL']);
        $this->assertFalse($res);
        $res = $this->obj->isXOutRegion(141);
        $this->assertTrue($res);
    }
}
