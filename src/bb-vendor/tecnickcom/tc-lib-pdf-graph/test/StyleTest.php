<?php
/**
 * StyleTest.php
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
 * Style Test
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfGraph
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2017 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-graph
 */
class StyleTest extends TestCase
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
    }

    public function testStyle()
    {
        $style = array();
        $res = $this->obj->add($style, true);
        $exp1 = '1.000000 w'."\n"
            .'0 J'."\n"
            .'0 j'."\n"
            .'10.000000 M'."\n"
            .'/CS1 CS 1.000000 SCN'."\n"
            .'/CS1 cs 1.000000 scn'."\n";
        $this->assertEquals($exp1, $res);

        $style = array(
            'lineWidth'  => 3,
            'lineCap'    => 'round',
            'lineJoin'   => 'bevel',
            'miterLimit' => 11,
            'dashArray'  => array(5, 7),
            'dashPhase'  => 1,
            'lineColor'  => 'greenyellow',
            'fillColor'  => '["RGB",0.250000,0.500000,0.750000]',
        );
        $res = $this->obj->add($style, false);
        $exp2 = '3.000000 w'."\n"
            .'1 J'."\n"
            .'2 j'."\n"
            .'11.000000 M'."\n"
            .'[5.000000 7.000000] 1.000000 d'."\n"
            .'0.678431 1.000000 0.184314 RG'."\n"
            .'0.250000 0.500000 0.750000 rg'."\n";
        $this->assertEquals($exp2, $res);
        $this->assertEquals($style, $this->obj->getCurrentStyleArray());

        $style = array(
            'lineCap'    => 'round',
            'lineJoin'   => 'bevel',
            'lineColor'  => 'transparent',
            'fillColor'  => 'cmyk(67,33,0,25)',
        );
        $res = $this->obj->add($style, true);
        $exp3 = '3.000000 w'."\n"
            .'1 J'."\n"
            .'2 j'."\n"
            .'11.000000 M'."\n"
            .'[5.000000 7.000000] 1.000000 d'."\n"
            .'0.670000 0.330000 0.000000 0.250000 k'."\n";
        $this->assertEquals($exp3, $res);

        $style = array('lineWidth'  => 7.123);
        $res = $this->obj->add($style, false);
        $exp4 = '7.123000 w'."\n";
        $this->assertEquals($exp4, $res);

        $res = $this->obj->pop();
        $this->assertEquals($exp4, $res);

        $res = $this->obj->pop();
        $this->assertEquals($exp3, $res);

        $res = $this->obj->pop();
        $this->assertEquals($exp2, $res);

        $res = $this->obj->pop();
        $this->assertEquals($exp1, $res);
    }

    /**
     * @expectedException \Com\Tecnick\Pdf\Graph\Exception
     */
    public function testStyleEx()
    {
        $this->obj->pop();
    }

    public function testSaveRestoreStyle()
    {
        $this->obj->add(array('lineWidth' => 1), false);
        $this->obj->add(array('lineWidth' => 2), false);
        $this->obj->add(array('lineWidth' => 3), false);
        $this->obj->saveStyleStaus();
        $this->obj->add(array('lineWidth' => 4), false);
        $this->obj->add(array('lineWidth' => 5), false);
        $this->obj->add(array('lineWidth' => 6), false);
        $this->assertEquals(array('lineWidth' => 6), $this->obj->getCurrentStyleArray());
        $this->obj->restoreStyleStaus();
        $this->assertEquals(array('lineWidth' => 3), $this->obj->getCurrentStyleArray());
    }

    public function testStyleItem()
    {
        $res = $this->obj->getCurrentStyleItem('lineCap');
        $this->assertEquals('butt', $res);
    }

    /**
     * @expectedException \Com\Tecnick\Pdf\Graph\Exception
     */
    public function testStyleItemEx()
    {
        $this->obj->getCurrentStyleItem('wrongField');
    }

    public function testGetLastStyleProperty()
    {
        $this->obj->add(array('lineWidth' => 1), false);
        $this->obj->add(array('lineWidth' => 2), false);
        $this->obj->add(array('lineWidth' => 3), false);
        $this->assertEquals(3, $this->obj->getLastStyleProperty('lineWidth', 0));
        $this->obj->add(array('lineWidth' => 4), false);
        $this->assertEquals(4, $this->obj->getLastStyleProperty('lineWidth', 0));
        $this->assertEquals(7, $this->obj->getLastStyleProperty('unknown', 7));
    }

    public function testGetPathPaintOp()
    {
        $res = $this->obj->getPathPaintOp('', '');
        $this->assertEquals('', $res);
    
        $res = $this->obj->getPathPaintOp('');
        $this->assertEquals('S'."\n", $res);
    
        $res = $this->obj->getPathPaintOp('', 'df');
        $this->assertEquals('b'."\n", $res);
    
        $res = $this->obj->getPathPaintOp('CEO');
        $this->assertEquals('W* n'."\n", $res);
    
        $res = $this->obj->getPathPaintOp('F*D');
        $this->assertEquals('B*'."\n", $res);
    }

    public function testIsFillingMode()
    {
        $this->assertTrue($this->obj->isFillingMode('f'));
        $this->assertTrue($this->obj->isFillingMode('f*'));
        $this->assertTrue($this->obj->isFillingMode('B'));
        $this->assertTrue($this->obj->isFillingMode('B*'));
        $this->assertTrue($this->obj->isFillingMode('b'));
        $this->assertTrue($this->obj->isFillingMode('b*'));
        $this->assertFalse($this->obj->isFillingMode('S'));
        $this->assertFalse($this->obj->isFillingMode('s'));
        $this->assertFalse($this->obj->isFillingMode('n'));
        $this->assertFalse($this->obj->isFillingMode(''));
    }

    public function testIsStrokingMode()
    {
        $this->assertTrue($this->obj->isStrokingMode('S'));
        $this->assertTrue($this->obj->isStrokingMode('s'));
        $this->assertTrue($this->obj->isStrokingMode('B'));
        $this->assertTrue($this->obj->isStrokingMode('B*'));
        $this->assertTrue($this->obj->isStrokingMode('b'));
        $this->assertTrue($this->obj->isStrokingMode('b*'));
        $this->assertFalse($this->obj->isStrokingMode('f'));
        $this->assertFalse($this->obj->isStrokingMode('f*'));
        $this->assertFalse($this->obj->isStrokingMode('n'));
        $this->assertFalse($this->obj->isStrokingMode(''));
    }

    public function testIsClosingMode()
    {
        $this->assertTrue($this->obj->isClosingMode('s'));
        $this->assertTrue($this->obj->isClosingMode('b'));
        $this->assertTrue($this->obj->isClosingMode('b*'));
        $this->assertFalse($this->obj->isClosingMode('f'));
        $this->assertFalse($this->obj->isClosingMode('f*'));
        $this->assertFalse($this->obj->isClosingMode('S'));
        $this->assertFalse($this->obj->isClosingMode('B'));
        $this->assertFalse($this->obj->isClosingMode('B*'));
        $this->assertFalse($this->obj->isClosingMode('n'));
        $this->assertFalse($this->obj->isClosingMode(''));
    }

    public function testGetModeWithoutClose()
    {
        $this->assertEquals('', $this->obj->getModeWithoutClose(''));
        $this->assertEquals('S', $this->obj->getModeWithoutClose('s'));
        $this->assertEquals('B', $this->obj->getModeWithoutClose('b'));
        $this->assertEquals('B*', $this->obj->getModeWithoutClose('b*'));
        $this->assertEquals('n', $this->obj->getModeWithoutClose('n'));
    }

    public function testGetModeWithoutFill()
    {
        $this->assertEquals('', $this->obj->getModeWithoutFill(''));
        $this->assertEquals('', $this->obj->getModeWithoutFill('f'));
        $this->assertEquals('', $this->obj->getModeWithoutFill('f*'));
        $this->assertEquals('S', $this->obj->getModeWithoutFill('B'));
        $this->assertEquals('S', $this->obj->getModeWithoutFill('B*'));
        $this->assertEquals('s', $this->obj->getModeWithoutFill('b'));
        $this->assertEquals('s', $this->obj->getModeWithoutFill('b*'));
        $this->assertEquals('n', $this->obj->getModeWithoutFill('n'));
    }

    public function testGetModeWithoutStroke()
    {
        $this->assertEquals('', $this->obj->getModeWithoutStroke(''));
        $this->assertEquals('', $this->obj->getModeWithoutStroke('S'));
        $this->assertEquals('h', $this->obj->getModeWithoutStroke('s'));
        $this->assertEquals('f', $this->obj->getModeWithoutStroke('B'));
        $this->assertEquals('f*', $this->obj->getModeWithoutStroke('B*'));
        $this->assertEquals('h f', $this->obj->getModeWithoutStroke('b'));
        $this->assertEquals('h f*', $this->obj->getModeWithoutStroke('b*'));
        $this->assertEquals('n', $this->obj->getModeWithoutStroke('n'));
    }

    public function testGetExtGState()
    {
        $this->assertEquals(
            '/GS1 gs'."\n",
            $this->obj->getExtGState(array('A' => 'B'))
        );
        $this->assertEquals(
            '/GS1 gs'."\n",
            $this->obj->getExtGState(array('A' => 'B'))
        );
        $this->assertEquals(
            '/GS2 gs'."\n",
            $this->obj->getExtGState(array('C' => 'D'))
        );
    }

    public function testGetExtGStatePdfa()
    {
        $obj = new \Com\Tecnick\Pdf\Graph\Draw(
            1,
            0,
            0,
            new \Com\Tecnick\Color\Pdf(),
            new \Com\Tecnick\Pdf\Encrypt\Encrypt(),
            true
        );
        $this->assertEquals(
            '',
            $obj->getExtGState(array('A' => 'B'))
        );
    }
}
