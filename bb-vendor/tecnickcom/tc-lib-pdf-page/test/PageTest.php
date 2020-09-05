<?php
/**
 * PageTest.php
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
class PageTest extends TestCase
{
    protected $obj = null;

    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test

        $col = new \Com\Tecnick\Color\Pdf;
        $enc = new \Com\Tecnick\Pdf\Encrypt\Encrypt(false);
        $this->obj = new \Com\Tecnick\Pdf\Page\Page('mm', $col, $enc, false, false);
    }

    public function testGetKUnit()
    {
        $this->assertEquals(2.83464566929134, $this->obj->getKUnit(), '', 0.001);
    }

    public function testEnableSignatureApproval()
    {
        $res = $this->obj->enableSignatureApproval(true);
        $this->assertNotNull($res);
    }

    public function testAdd()
    {
        // 1
        $res = $this->obj->add();

        $box = array(
            'llx' => 0,
            'lly' => 0,
            'urx' => 595.2765,
            'ury' => 841.890,
            'bci' => array(
                'color' => '#000000',
                'width' => 0.353,
                'style' => 'S',
                'dash' => array(0 => 3)
            )
        );

        $exp = array(
            'group' => 0,
            'rotation' => 0,
            'zoom' => 1,
            'orientation' => 'P',
            'format' => 'A4',
            'pheight' => 841.890,
            'pwidth' => 595.2765,
            'width' => 210,
            'height' => 297,
            'box' => array(
                'MediaBox' => $box,
                'CropBox'  => $box,
                'BleedBox' => $box,
                'TrimBox'  => $box,
                'ArtBox'   => $box,
            ),
            'margin' => array(
                'PL' => 0,
                'PR' => 0,
                'PT' => 0,
                'HB' => 0,
                'CT' => 0,
                'CB' => 0,
                'FT' => 0,
                'PB' => 0,
            ),
            'ContentWidth' => 210,
            'ContentHeight' => 297,
            'HeaderHeight' => 0,
            'FooterHeight' => 0,
            'region' => array (
                array (
                    'RX' => 0,
                    'RY' => 0,
                    'RW' => 210,
                    'RH' => 297,
                    'RL' => 210,
                    'RR' => 0.0,
                    'RT' => 297,
                    'RB' => 0.0,
                    'x'  => 0.0,
                    'y'  => 0.0,
                ),
            ),
            'currentRegion' => 0,
            'columns' => 1,
            'content' => array(0 => ''),
            'annotrefs' => array(),
            'content_mark' => array(0 => 0),
            'autobreak' => true,
        );
        
        unset($res['time']);
        $this->assertEquals($exp, $res, '', 0.01);

        // 2
        $res = $this->obj->add();
        unset($res['time']);
        $this->assertEquals($exp, $res, '', 0.01);

        // 3
        $res = $this->obj->add(array('group' => 1));
        unset($res['time']);
        $exp['group'] = 1;
        $this->assertEquals($exp, $res, '', 0.01);

        // 3
        $res = $this->obj->add(array('columns' => 2));
        unset($res['time']);
        $exp['group'] = 0;
        $exp['columns'] = 2;
        $exp['region'] = array (
            0 => array (
                'RX' => 0,
                'RY' => 0,
                'RW' => 105,
                'RH' => 297,
                'RL' => 105,
                'RR' => 105,
                'RT' => 297,
                'RB' => 0,
                'x'  => 0,
                'y'  => 0,
            ),
            1 => array (
                'RX' => 105,
                'RY' => 0,
                'RW' => 105,
                'RH' => 297,
                'RL' => 210,
                'RR' => 0.0,
                'RT' => 297,
                'RB' => 0,
                'x'  => 105,
                'y'  => 0,
            ),
        );
        $this->assertEquals($exp, $res, '', 0.01);
    }

    public function testGetNextPage()
    {
        $this->obj->add();
        $this->obj->add();
        $this->obj->add();
        $this->obj->add();

        $this->obj->setCurrentPage(2);
        $this->obj->getNextPage();
        $this->obj->enableAutoPageBreak(false);
        $this->obj->getNextPage();
        $this->obj->enableAutoPageBreak(true);
        $this->obj->getNextPage();
        $this->obj->getNextPage();

        $this->assertCount(6, $this->obj->getPages());
    }

    public function testDelete()
    {
        $this->obj->add();
        $this->obj->add();
        $this->obj->add();
        $this->assertCount(3, $this->obj->getPages());
        $res = $this->obj->delete(1);
        $this->assertCount(2, $this->obj->getPages());
        $this->assertArrayHasKey('time', $res);
    }

    /**
     * @expectedException \Com\Tecnick\Pdf\Page\Exception
     */
    public function testDeleteEx()
    {
        $this->obj->delete(2);
    }

    public function testPop()
    {
        $this->obj->add();
        $this->obj->add();
        $this->obj->add();
        $this->assertCount(3, $this->obj->getPages());
        $res = $this->obj->pop();
        $this->assertCount(2, $this->obj->getPages());
        $this->assertArrayHasKey('time', $res);
    }

    public function testMove()
    {
        $this->obj->add();
        $this->obj->add(array('group' => 1));
        $this->obj->add(array('group' => 2));
        $this->obj->add(array('group' => 3));

        $this->assertEquals($this->obj->getPage(3), $this->obj->getCurrentPage());
        
        $this->obj->move(3, 0);
        $this->assertCount(4, $this->obj->getPages());

        $res = $this->obj->getPage(0);
        $this->assertEquals(3, $res['group']);
    }

    /**
     * @expectedException \Com\Tecnick\Pdf\Page\Exception
     */
    public function testMoveEx()
    {
        $this->obj->move(1, 2);
    }

    /**
     * @expectedException \Com\Tecnick\Pdf\Page\Exception
     */
    public function testGetPageEx()
    {
        $this->obj->getPage(2);
    }

    public function testContent()
    {
        $this->obj->add();
        $this->obj->addContent('Lorem');
        $this->obj->addContent('ipsum');
        $this->obj->addContentMark();
        $this->obj->addContent('dolor');
        $this->obj->addContent('sit');
        $this->obj->addContent('amet');

        $this->assertEquals('amet', $this->obj->popContent());

        $page = $this->obj->getCurrentPage();
        $this->assertEquals(array(0, 3), $page['content_mark']);
        $this->assertEquals(array('', 'Lorem', 'ipsum', 'dolor', 'sit'), $page['content']);

        $this->obj->popContentToLastMark();
        $page = $this->obj->getCurrentPage();
        $this->assertEquals(array(0), $page['content_mark']);
        $this->assertEquals(array('', 'Lorem', 'ipsum'), $page['content']);
    }

    public function testGetPdfPages()
    {
        $this->obj->add();
        $this->obj->addContent('TEST1');
        $this->obj->add();
        $this->obj->addContent('TEST2');
        $this->obj->add(
            array(
                'group' => 1,
                'transition' => array(
                    'Dur' => 2,
                    'D' => 3,
                    'Dm' => 'V',
                    'S' => 'Glitter',
                    'M' => 'O',
                    'Di' => 315,
                    'SS' => 1.3,
                    'B' => true
                ),
                'annotrefs' => array(10, 20),
            )
        );
        $this->obj->addContent('TEST2');
        $pon = 0;
        $out = $this->obj->getPdfPages($pon);
        $this->assertEquals(2, $this->obj->getResourceDictObjID());
        $this->assertContains('<< /Type /Pages /Kids [ 3 0 R 4 0 R 5 0 R ] /Count 3 >>', $out);
    }
}
