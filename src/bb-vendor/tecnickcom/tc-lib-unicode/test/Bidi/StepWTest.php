<?php
/**
 * StepWTest.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     Unicode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-unicode
 *
 * This file is part of tc-lib-unicode software library.
 */

namespace Test\Bidi;

use PHPUnit\Framework\TestCase;

/**
 * Bidi Test
 *
 * @since       2011-05-23
 * @category    Library
 * @package     Unicode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-unicode
 */
class StepWTest extends TestCase
{
    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test
    }

    /**
     * @dataProvider stepWDataProvider
     */
    public function testStepW($seq, $expected)
    {
        $stepw = new \Com\Tecnick\Unicode\Bidi\StepW($seq);
        $this->assertEquals($expected, $stepw->getSequence());
    }

    public function stepWDataProvider()
    {
        return array(
            array(
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1536, 'level' => 0, 'type' => 'AL', 'otype' => 'AL'),
                        array('pos' => 1, 'char' => 768,  'level' => 0, 'type' => 'NSM', 'otype' => 'NSM'),
                        array('pos' => 2, 'char' => 768,  'level' => 0, 'type' => 'NSM', 'otype' => 'NSM'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1536, 'level' => 0, 'type' => 'R', 'otype' => 'AL'),
                        array('pos' => 1, 'char' => 768,  'level' => 0, 'type' => 'R', 'otype' => 'NSM'),
                        array('pos' => 2, 'char' => 768,  'level' => 0, 'type' => 'R', 'otype' => 'NSM'),
                    ),
                )
            ),
        );
    }
    
    /**
     * @dataProvider stepW1DataProvider
     */
    public function testStepW1($seq, $expected)
    {
        $stepw = new \Com\Tecnick\Unicode\Bidi\StepW($seq, false);
        $stepw->processStep('processW1');
        $this->assertEquals($expected, $stepw->getSequence());
    }

    public function stepW1DataProvider()
    {
        return array(
            array(
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1536, 'level' => 0, 'type' => 'AL', 'otype' => 'AL'),
                        array('pos' => 1, 'char' => 768,  'level' => 0, 'type' => 'NSM', 'otype' => 'NSM'),
                        array('pos' => 2, 'char' => 768,  'level' => 0, 'type' => 'NSM', 'otype' => 'NSM'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1536, 'level' => 0, 'type' => 'AL', 'otype' => 'AL'),
                        array('pos' => 1, 'char' => 768,  'level' => 0, 'type' => 'AL', 'otype' => 'NSM'),
                        array('pos' => 2, 'char' => 768,  'level' => 0, 'type' => 'AL', 'otype' => 'NSM'),
                    ),
                )
            ),
            array(
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 1,
                    'length' => 2,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1470, 'level' => 0, 'type' => 'R',   'otype' => 'R'),
                        array('pos' => 1, 'char' => 768,  'level' => 0, 'type' => 'NSM', 'otype' => 'NSM'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 1,
                    'length' => 2,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1470, 'level' => 0, 'type' => 'R', 'otype' => 'R'),
                        array('pos' => 1, 'char' => 768,  'level' => 0, 'type' => 'R', 'otype' => 'NSM'),
                    ),
                )
            ),
            array(
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 1,
                    'length' => 2,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 8294, 'level' => 0, 'type' => 'NI',  'otype' => 'NI'),
                        array('pos' => 1, 'char' => 768,  'level' => 0, 'type' => 'NSM', 'otype' => 'NSM'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 1,
                    'length' => 2,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 8294, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 1, 'char' => 768,  'level' => 0, 'type' => 'ON', 'otype' => 'NSM'),
                    ),
                )
            ),
            array(
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 1,
                    'length' => 2,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 8297, 'level' => 0, 'type' => 'NI',  'otype' => 'NI'),
                        array('pos' => 1, 'char' => 768,  'level' => 0, 'type' => 'NSM', 'otype' => 'NSM'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 1,
                    'length' => 2,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 8297, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 1, 'char' => 768,  'level' => 0, 'type' => 'ON', 'otype' => 'NSM'),
                    ),
                )
            ),
            array(
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 1,
                    'length' => 2,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 768,  'level' => 0, 'type' => 'NSM', 'otype' => 'NSM'),
                        array('pos' => 1, 'char' => 768,  'level' => 0, 'type' => 'NSM', 'otype' => 'NSM'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 1,
                    'length' => 2,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 768,  'level' => 0, 'type' => 'R', 'otype' => 'NSM'),
                        array('pos' => 1, 'char' => 768,  'level' => 0, 'type' => 'R', 'otype' => 'NSM'),
                    ),
                )
            ),
        );
    }

    /**
     * @dataProvider stepW2DataProvider
     */
    public function testStepW2($seq, $expected)
    {
        $stepw = new \Com\Tecnick\Unicode\Bidi\StepW($seq, false);
        $stepw->processStep('processW2');
        $this->assertEquals($expected, $stepw->getSequence());
    }

    public function stepW2DataProvider()
    {
        return array(
            array(
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 1,
                    'length' => 2,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1536, 'level' => 0, 'type' => 'AL', 'otype' => 'AL'),
                        array('pos' => 1, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 1,
                    'length' => 2,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1536, 'level' => 0, 'type' => 'AL', 'otype' => 'AL'),
                        array('pos' => 1, 'char' => 1776, 'level' => 0, 'type' => 'AN', 'otype' => 'EN'),
                    ),
                )
            ),
            array(
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1536, 'level' => 0, 'type' => 'AL', 'otype' => 'AL'),
                        array('pos' => 1, 'char' => 1769, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1536, 'level' => 0, 'type' => 'AL', 'otype' => 'AL'),
                        array('pos' => 1, 'char' => 1769, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 1776, 'level' => 0, 'type' => 'AN', 'otype' => 'EN'),
                    ),
                )
            ),
            array(
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'L',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1470, 'level' => 0, 'type' => 'L',  'otype' => 'L'),
                        array('pos' => 1, 'char' => 1769, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'L',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1470, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                        array('pos' => 1, 'char' => 1769, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                    ),
                )
            ),
            array(
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 65,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                        array('pos' => 1, 'char' => 1769, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 65, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                        array('pos' => 1, 'char' => 1769, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                    ),
                )
            ),
            array(
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1470, 'level' => 0, 'type' => 'R',  'otype' => 'R'),
                        array('pos' => 1, 'char' => 1769, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1470, 'level' => 0, 'type' => 'R', 'otype' => 'R'),
                        array('pos' => 1, 'char' => 1769, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                    ),
                )
            ),
        );
    }

    /**
     * @dataProvider stepW3DataProvider
     */
    public function testStepW3($seq, $expected)
    {
        $stepw = new \Com\Tecnick\Unicode\Bidi\StepW($seq, false);
        $stepw->processStep('processW3');
        $this->assertEquals($expected, $stepw->getSequence());
    }

    public function stepW3DataProvider()
    {
        return array(
            array(
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 1,
                    'length' => 2,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1536, 'level' => 0, 'type' => 'AL', 'otype' => 'AL'),
                        array('pos' => 1, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 1,
                    'length' => 2,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1536, 'level' => 0, 'type' => 'R', 'otype' => 'AL'),
                        array('pos' => 1, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                    ),
                )
            ),
        );
    }

    /**
     * @dataProvider stepW4DataProvider
     */
    public function testStepW4($seq, $expected)
    {
        $stepw = new \Com\Tecnick\Unicode\Bidi\StepW($seq, false);
        $stepw->processStep('processW4');
        $this->assertEquals($expected, $stepw->getSequence());
    }

    public function stepW4DataProvider()
    {
        return array(
            array(
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                        array('pos' => 1, 'char' => 43,   'level' => 0, 'type' => 'ES', 'otype' => 'ES'),
                        array('pos' => 2, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                        array('pos' => 1, 'char' => 43, 'level' => 0, 'type' => 'EN', 'otype' => 'ES'),
                        array('pos' => 2, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                    ),
                )
            ),
            array(
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                        array('pos' => 1, 'char' => 44,   'level' => 0, 'type' => 'CS', 'otype' => 'CS'),
                        array('pos' => 2, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                        array('pos' => 1, 'char' => 44, 'level' => 0, 'type' => 'EN', 'otype' => 'CS'),
                        array('pos' => 2, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                    ),
                )
            ),
            array(
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1632, 'level' => 0, 'type' => 'AN', 'otype' => 'AN'),
                        array('pos' => 1, 'char' => 44,   'level' => 0, 'type' => 'CS', 'otype' => 'CS'),
                        array('pos' => 2, 'char' => 1632, 'level' => 0, 'type' => 'AN', 'otype' => 'AN'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1632, 'level' => 0, 'type' => 'AN', 'otype' => 'AN'),
                        array('pos' => 1, 'char' => 44, 'level' => 0, 'type' => 'AN', 'otype' => 'CS'),
                        array('pos' => 2, 'char' => 1632, 'level' => 0, 'type' => 'AN', 'otype' => 'AN'),
                    ),
                )
            ),
        );
    }

    /**
     * @dataProvider stepW5DataProvider
     */
    public function testStepW5($seq, $expected)
    {
        $stepw = new \Com\Tecnick\Unicode\Bidi\StepW($seq, false);
        $stepw->processStep('processW5');
        $this->assertEquals($expected, $stepw->getSequence());
    }

    public function stepW5DataProvider()
    {
        return array(
            array(
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1642, 'level' => 0, 'type' => 'ET', 'otype' => 'ET'),
                        array('pos' => 1, 'char' => 1642, 'level' => 0, 'type' => 'ET', 'otype' => 'ET'),
                        array('pos' => 2, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1642, 'level' => 0, 'type' => 'EN', 'otype' => 'ET'),
                        array('pos' => 1, 'char' => 1642, 'level' => 0, 'type' => 'EN', 'otype' => 'ET'),
                        array('pos' => 2, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                    ),
                )
            ),
            array(
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                        array('pos' => 1, 'char' => 1642, 'level' => 0, 'type' => 'ET', 'otype' => 'ET'),
                        array('pos' => 2, 'char' => 1642, 'level' => 0, 'type' => 'ET', 'otype' => 'ET'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                        array('pos' => 1, 'char' => 1642, 'level' => 0, 'type' => 'EN', 'otype' => 'ET'),
                        array('pos' => 2, 'char' => 1642, 'level' => 0, 'type' => 'EN', 'otype' => 'ET'),
                    ),
                )
            ),
            array(
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1632, 'level' => 0, 'type' => 'AN', 'otype' => 'AN'),
                        array('pos' => 1, 'char' => 1642, 'level' => 0, 'type' => 'ET', 'otype' => 'ET'),
                        array('pos' => 2, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1632, 'level' => 0, 'type' => 'AN', 'otype' => 'AN'),
                        array('pos' => 1, 'char' => 1642, 'level' => 0, 'type' => 'EN', 'otype' => 'ET'),
                        array('pos' => 2, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                    ),
                )
            ),
            array(
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 4,
                    'length' => 5,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1642, 'level' => 0, 'type' => 'ET', 'otype' => 'ET'),
                        array('pos' => 1, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                        array('pos' => 2, 'char' => 1642, 'level' => 0, 'type' => 'ET', 'otype' => 'ET'),
                        array('pos' => 3, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                        array('pos' => 4, 'char' => 38,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 4,
                    'length' => 5,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1642, 'level' => 0, 'type' => 'EN', 'otype' => 'ET'),
                        array('pos' => 1, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                        array('pos' => 2, 'char' => 1642, 'level' => 0, 'type' => 'EN', 'otype' => 'ET'),
                        array('pos' => 3, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                        array('pos' => 4, 'char' => 38,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'),
                    ),
                )
            ),
        );
    }

    /**
     * @dataProvider stepW6DataProvider
     */
    public function testStepW6($seq, $expected)
    {
        $stepw = new \Com\Tecnick\Unicode\Bidi\StepW($seq, false);
        $stepw->processStep('processW6');
        $this->assertEquals($expected, $stepw->getSequence());
    }

    public function stepW6DataProvider()
    {
        return array(
            array(
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 1,
                    'length' => 2,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1632, 'level' => 0, 'type' => 'AN', 'otype' => 'AN'),
                        array('pos' => 1, 'char' => 1642, 'level' => 0, 'type' => 'ET', 'otype' => 'ET'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 1,
                    'length' => 2,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1632, 'level' => 0, 'type' => 'AN', 'otype' => 'AN'),
                        array('pos' => 1, 'char' => 1642, 'level' => 0, 'type' => 'ON', 'otype' => 'ET'),
                    ),
                )
            ),
            array(
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 65,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                        array('pos' => 1, 'char' => 43,   'level' => 0, 'type' => 'ES', 'otype' => 'ES'),
                        array('pos' => 2, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 65, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                        array('pos' => 1, 'char' => 43, 'level' => 0, 'type' => 'ON', 'otype' => 'ES'),
                        array('pos' => 2, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                    ),
                )
            ),
            array(
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                        array('pos' => 1, 'char' => 44,   'level' => 0, 'type' => 'CS', 'otype' => 'CS'),
                        array('pos' => 2, 'char' => 1632, 'level' => 0, 'type' => 'AN', 'otype' => 'AN'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                        array('pos' => 1, 'char' => 44, 'level' => 0, 'type' => 'ON', 'otype' => 'CS'),
                        array('pos' => 2, 'char' => 1632, 'level' => 0, 'type' => 'AN', 'otype' => 'AN'),
                    ),
                )
            ),
            array(
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 1,
                    'length' => 2,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1642, 'level' => 0, 'type' => 'ET', 'otype' => 'ET'),
                        array('pos' => 1, 'char' => 1632, 'level' => 0, 'type' => 'AN', 'otype' => 'AN'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 1,
                    'length' => 2,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1642, 'level' => 0, 'type' => 'ON', 'otype' => 'ET'),
                        array('pos' => 1, 'char' => 1632, 'level' => 0, 'type' => 'AN', 'otype' => 'AN'),
                    ),
                )
            ),
        );
    }

    /**
     * @dataProvider stepW7DataProvider
     */
    public function testStepW7($seq, $expected)
    {
        $stepw = new \Com\Tecnick\Unicode\Bidi\StepW($seq, false);
        $stepw->processStep('processW7');
        $this->assertEquals($expected, $stepw->getSequence());
    }

    public function stepW7DataProvider()
    {
        return array(
            array(
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 65,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                        array('pos' => 1, 'char' => 8294, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 65,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                        array('pos' => 1, 'char' => 8294, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 1776, 'level' => 0, 'type' => 'L',  'otype' => 'EN'),
                    ),
                )
            ),
            array(
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1470, 'level' => 0, 'type' => 'R',  'otype' => 'R'),
                        array('pos' => 1, 'char' => 8294, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 1470, 'level' => 0, 'type' => 'R',  'otype' => 'R'),
                        array('pos' => 1, 'char' => 8294, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                    ),
                )
            ),
            array(
                array(
                    'e' => 0,
                    'edir' => 'L',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'L',
                    'eos' => 'L',
                    'item' => array(
                        array('pos' => 0, 'char' => 38,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'),
                        array('pos' => 1, 'char' => 38,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'),
                        array('pos' => 2, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'L',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'L',
                    'eos' => 'L',
                    'item' => array(
                        array('pos' => 0, 'char' => 38,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'),
                        array('pos' => 1, 'char' => 38,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'),
                        array('pos' => 2, 'char' => 1776, 'level' => 0, 'type' => 'L',  'otype' => 'EN'),
                    ),
                )
            ),
        );
    }
}
