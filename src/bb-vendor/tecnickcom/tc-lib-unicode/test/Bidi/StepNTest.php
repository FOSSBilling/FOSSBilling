<?php
/**
 * StepNTest.php
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
class StepNTest extends TestCase
{
    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test
    }

    /**
     * @dataProvider stepN0DataProvider
     */
    public function testStepN0($seq, $expected)
    {
        $stepn = new \Com\Tecnick\Unicode\Bidi\StepN($seq, false);
        $stepn->processStep('getBracketPairs');
        $stepn->processStep('processN0');
        $this->assertEquals($expected, $stepn->getSequence());
    }

    public function stepN0DataProvider()
    {
        return array(
            array(
                array(
                    'e' => 0,
                    'edir' => 'L',
                    'start' => 0,
                    'end' => 4,
                    'length' => 5,
                    'sos' => 'L',
                    'eos' => 'L',
                    'item' => array(
                        array('pos' => 0, 'char' => 8207,   'level' => 0, 'type' => 'R',  'otype' => 'R'),
                        array('pos' => 1, 'char' => 0x3008, 'level' => 0, 'type' => 'ON', 'otype' => 'ON'),
                        array('pos' => 2, 'char' => 65,     'level' => 0, 'type' => 'L',  'otype' => 'L'),
                        array('pos' => 3, 'char' => 0x3009, 'level' => 0, 'type' => 'ON', 'otype' => 'ON'),
                        array('pos' => 4, 'char' => 8207,   'level' => 0, 'type' => 'R',  'otype' => 'R'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'L',
                    'start' => 0,
                    'end' => 4,
                    'length' => 5,
                    'sos' => 'L',
                    'eos' => 'L',
                    'item' => array(
                        array('pos' => 0, 'char' => 8207,   'level' => 0, 'type' => 'R', 'otype' => 'R'),
                        array('pos' => 1, 'char' => 0x3008, 'level' => 0, 'type' => 'L', 'otype' => 'ON'),
                        array('pos' => 2, 'char' => 65,     'level' => 0, 'type' => 'L', 'otype' => 'L'),
                        array('pos' => 3, 'char' => 0x3009, 'level' => 0, 'type' => 'L', 'otype' => 'ON'),
                        array('pos' => 4, 'char' => 8207,   'level' => 0, 'type' => 'R', 'otype' => 'R'),
                    ),
                )
            ),
            array(
                array(
                    'e' => 1,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 4,
                    'length' => 5,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 65,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                        array('pos' => 1, 'char' => 91,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'), // [
                        array('pos' => 2, 'char' => 65,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                        array('pos' => 3, 'char' => 8207, 'level' => 1, 'type' => 'R',  'otype' => 'R'),
                        array('pos' => 4, 'char' => 93,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'), // ]
                    ),
                ),
                array(
                    'e' => 1,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 4,
                    'length' => 5,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 65, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                        array('pos' => 1, 'char' => 91, 'level' => 1, 'type' => 'R', 'otype' => 'ON'),
                        array('pos' => 2, 'char' => 65, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                        array('pos' => 3, 'char' => 8207, 'level' => 1, 'type' => 'R', 'otype' => 'R'),
                        array('pos' => 4, 'char' => 93, 'level' => 1, 'type' => 'R', 'otype' => 'ON'),
                    ),
                )
            ),
            array(
                array(
                    'e' => 1,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 4,
                    'length' => 5,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 65,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                        array('pos' => 1, 'char' => 91,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'), // [
                        array('pos' => 2, 'char' => 5760, 'level' => 1, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 3, 'char' => 8207, 'level' => 1, 'type' => 'R',  'otype' => 'R'),
                        array('pos' => 4, 'char' => 93,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'), // ]
                    ),
                ),
                array(
                    'e' => 1,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 4,
                    'length' => 5,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 65, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                        array('pos' => 1, 'char' => 91, 'level' => 1, 'type' => 'R', 'otype' => 'ON'),
                        array('pos' => 2, 'char' => 5760, 'level' => 1, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 3, 'char' => 8207, 'level' => 1, 'type' => 'R', 'otype' => 'R'),
                        array('pos' => 4, 'char' => 93, 'level' => 1, 'type' => 'R', 'otype' => 'ON'),
                    ),
                )
            ),
            array(
                array(
                    'e' => 0,
                    'edir' => 'L',
                    'start' => 0,
                    'end' => 4,
                    'length' => 5,
                    'sos' => 'L',
                    'eos' => 'L',
                    'item' => array(
                        array('pos' => 0, 'char' => 8207, 'level' => 0, 'type' => 'R',  'otype' => 'R'),
                        array('pos' => 1, 'char' => 91,   'level' => 0, 'type' => 'ON', 'otype' => 'ON'), // [
                        array('pos' => 2, 'char' => 8207, 'level' => 0, 'type' => 'R',  'otype' => 'R'),
                        array('pos' => 3, 'char' => 93,   'level' => 0, 'type' => 'ON', 'otype' => 'ON'), // ]
                        array('pos' => 4, 'char' => 65,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'L',
                    'start' => 0,
                    'end' => 4,
                    'length' => 5,
                    'sos' => 'L',
                    'eos' => 'L',
                    'item' => array(
                        array('pos' => 0, 'char' => 8207, 'level' => 0, 'type' => 'R', 'otype' => 'R'),
                        array('pos' => 1, 'char' => 91, 'level' => 0, 'type' => 'R', 'otype' => 'ON'),
                        array('pos' => 2, 'char' => 8207, 'level' => 0, 'type' => 'R', 'otype' => 'R'),
                        array('pos' => 3, 'char' => 93, 'level' => 0, 'type' => 'R', 'otype' => 'ON'),
                        array('pos' => 4, 'char' => 65, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    ),
                )
            ),
            array(
                array(
                    'e' => 1,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 5,
                    'length' => 6,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 65,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                        array('pos' => 1, 'char' => 91,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'), // [
                        array('pos' => 2, 'char' => 65,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                        array('pos' => 3, 'char' => 5760, 'level' => 1, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 4, 'char' => 93,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'), // ]
                        array('pos' => 5, 'char' => 65,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    ),
                ),
                array(
                    'e' => 1,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 5,
                    'length' => 6,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 65, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                        array('pos' => 1, 'char' => 91, 'level' => 1, 'type' => 'L', 'otype' => 'ON'),
                        array('pos' => 2, 'char' => 65, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                        array('pos' => 3, 'char' => 5760, 'level' => 1, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 4, 'char' => 93, 'level' => 1, 'type' => 'L', 'otype' => 'ON'),
                        array('pos' => 5, 'char' => 65, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    ),
                )
            ),
            array(
                array(
                    'e' => 1,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 4,
                    'length' => 5,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 8207, 'level' => 1, 'type' => 'R',  'otype' => 'R'),
                        array('pos' => 1, 'char' => 91,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'), // [
                        array('pos' => 2, 'char' => 65,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                        array('pos' => 3, 'char' => 93,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'), // ]
                        array('pos' => 4, 'char' => 65,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    ),
                ),
                array(
                    'e' => 1,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 4,
                    'length' => 5,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 8207, 'level' => 1, 'type' => 'R', 'otype' => 'R'),
                        array('pos' => 1, 'char' => 91, 'level' => 1, 'type' => 'R', 'otype' => 'ON'),
                        array('pos' => 2, 'char' => 65, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                        array('pos' => 3, 'char' => 93, 'level' => 1, 'type' => 'R', 'otype' => 'ON'),
                        array('pos' => 4, 'char' => 65, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    ),
                )
            ),
            array(
                array(
                    'e' => 1,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 4,
                    'length' => 5,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 8207, 'level' => 1, 'type' => 'R',  'otype' => 'R'),
                        array('pos' => 1, 'char' => 91,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'), // [
                        array('pos' => 2, 'char' => 65,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                        array('pos' => 3, 'char' => 93,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'), // ]
                        array('pos' => 4, 'char' => 8207, 'level' => 1, 'type' => 'R',  'otype' => 'R'),
                    ),
                ),
                array(
                    'e' => 1,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 4,
                    'length' => 5,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 8207, 'level' => 1, 'type' => 'R', 'otype' => 'R'),
                        array('pos' => 1, 'char' => 91, 'level' => 1, 'type' => 'R', 'otype' => 'ON'),
                        array('pos' => 2, 'char' => 65, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                        array('pos' => 3, 'char' => 93, 'level' => 1, 'type' => 'R', 'otype' => 'ON'),
                        array('pos' => 4, 'char' => 8207, 'level' => 1, 'type' => 'R', 'otype' => 'R'),
                    ),
                )
            ),
            array(
                array(
                    'e' => 1,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 4,
                    'length' => 5,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 8207, 'level' => 1, 'type' => 'R',  'otype' => 'R'),
                        array('pos' => 1, 'char' => 91,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'), // [
                        array('pos' => 2, 'char' => 5760, 'level' => 1, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 3, 'char' => 93,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'), // ]
                        array('pos' => 4, 'char' => 65,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    ),
                ),
                array(
                    'e' => 1,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 4,
                    'length' => 5,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 8207, 'level' => 1, 'type' => 'R', 'otype' => 'R'),
                        array('pos' => 1, 'char' => 91, 'level' => 1, 'type' => 'ON', 'otype' => 'ON'),
                        array('pos' => 2, 'char' => 5760, 'level' => 1, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 3, 'char' => 93, 'level' => 1, 'type' => 'ON', 'otype' => 'ON'),
                        array('pos' => 4, 'char' => 65, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    ),
                )
            ),
            array(
                array(
                    'e' => 1,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 4,
                    'length' => 5,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 8207, 'level' => 1, 'type' => 'R',  'otype' => 'R'),
                        array('pos' => 1, 'char' => 5760, 'level' => 1, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 91,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'), // [
                        array('pos' => 3, 'char' => 65,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                        array('pos' => 4, 'char' => 93,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'), // ]
                    ),
                ),
                array(
                    'e' => 1,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 4,
                    'length' => 5,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 8207, 'level' => 1, 'type' => 'R',  'otype' => 'R'),
                        array('pos' => 1, 'char' => 5760, 'level' => 1, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 91,   'level' => 1, 'type' => 'R',  'otype' => 'ON'),
                        array('pos' => 3, 'char' => 65,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                        array('pos' => 4, 'char' => 93,   'level' => 1, 'type' => 'R',  'otype' => 'ON'),
                    ),
                )
            ),
            array(
                array(
                    'e' => 1,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 4,
                    'length' => 5,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 5760, 'level' => 1, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 1, 'char' => 5760, 'level' => 1, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 91,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'), // [
                        array('pos' => 3, 'char' => 65,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                        array('pos' => 4, 'char' => 93,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'), // ]
                    ),
                ),
                array(
                    'e' => 1,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 4,
                    'length' => 5,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 5760, 'level' => 1, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 1, 'char' => 5760, 'level' => 1, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 91,   'level' => 1, 'type' => 'R',  'otype' => 'ON'),
                        array('pos' => 3, 'char' => 65,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                        array('pos' => 4, 'char' => 93,   'level' => 1, 'type' => 'R',  'otype' => 'ON'),
                    ),
                )
            ),
            array(
                array(
                    'e' => 0,
                    'edir' => 'L',
                    'start' => 0,
                    'end' => 5,
                    'length' => 6,
                    'sos' => 'L',
                    'eos' => 'L',
                    'item' => array(
                        array('pos' => 0, 'char' => 8207,   'level' => 0, 'type' => 'R',  'otype' => 'R'),
                        array('pos' => 1, 'char' => 0x3008, 'level' => 0, 'type' => 'ON', 'otype' => 'ON'),
                        array('pos' => 2, 'char' => 65,     'level' => 0, 'type' => 'L',  'otype' => 'L'),
                        array('pos' => 3, 'char' => 0x3009, 'level' => 0, 'type' => 'ON', 'otype' => 'ON'),
                        array('pos' => 4, 'char' => 1809,   'level' => 0, 'type' => 'NSM',  'otype' => 'NSM'),
                        array('pos' => 5, 'char' => 1809,   'level' => 0, 'type' => 'NSM',  'otype' => 'NSM'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'L',
                    'start' => 0,
                    'end' => 5,
                    'length' => 6,
                    'sos' => 'L',
                    'eos' => 'L',
                    'item' => array(
                        array('pos' => 0, 'char' => 8207,   'level' => 0, 'type' => 'R', 'otype' => 'R'),
                        array('pos' => 1, 'char' => 0x3008, 'level' => 0, 'type' => 'L', 'otype' => 'ON'),
                        array('pos' => 2, 'char' => 65,     'level' => 0, 'type' => 'L', 'otype' => 'L'),
                        array('pos' => 3, 'char' => 0x3009, 'level' => 0, 'type' => 'L', 'otype' => 'ON'),
                        array('pos' => 4, 'char' => 1809,   'level' => 0, 'type' => 'L', 'otype' => 'NSM'),
                        array('pos' => 5, 'char' => 1809,   'level' => 0, 'type' => 'L', 'otype' => 'NSM'),
                    ),
                )
            ),
        );
    }

    /**
     * @dataProvider stepN1DataProvider
     */
    public function testStepN1($seq, $expected)
    {
        $stepn = new \Com\Tecnick\Unicode\Bidi\StepN($seq, false);
        $stepn->processStep('processN1');
        $this->assertEquals($expected, $stepn->getSequence());
    }

    public function stepN1DataProvider()
    {
        return array(
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
                        array('pos' => 0, 'char' => 65,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 65,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
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
                        array('pos' => 0, 'char' => 65, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'L', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 65, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
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
                        array('pos' => 0, 'char' => 8207, 'level' => 0, 'type' => 'R',  'otype' => 'R'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 8207, 'level' => 0, 'type' => 'R',  'otype' => 'R'),
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
                        array('pos' => 0, 'char' => 8207, 'level' => 0, 'type' => 'R', 'otype' => 'R'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'R', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 8207, 'level' => 0, 'type' => 'R', 'otype' => 'R'),
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
                        array('pos' => 0, 'char' => 8207, 'level' => 0, 'type' => 'R',  'otype' => 'R'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 1632, 'level' => 0, 'type' => 'AN', 'otype' => 'AN'),
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
                        array('pos' => 0, 'char' => 8207, 'level' => 0, 'type' => 'R', 'otype' => 'R'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'R', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 1632, 'level' => 0, 'type' => 'AN', 'otype' => 'AN'),
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
                        array('pos' => 0, 'char' => 8207, 'level' => 0, 'type' => 'R',  'otype' => 'R'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
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
                        array('pos' => 0, 'char' => 8207, 'level' => 0, 'type' => 'R', 'otype' => 'R'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'R', 'otype' => 'NI'),
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
                        array('pos' => 0, 'char' => 1632, 'level' => 0, 'type' => 'AN', 'otype' => 'AN'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 8207, 'level' => 0, 'type' => 'R',  'otype' => 'R'),
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
                        array('pos' => 0, 'char' => 1632, 'level' => 0, 'type' => 'AN', 'otype' => 'AN'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'R', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 8207, 'level' => 0, 'type' => 'R', 'otype' => 'R'),
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
                        array('pos' => 0, 'char' => 1632, 'level' => 0, 'type' => 'AN', 'otype' => 'AN'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 1632, 'level' => 0, 'type' => 'AN', 'otype' => 'AN'),
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
                        array('pos' => 0, 'char' => 1632, 'level' => 0, 'type' => 'AN', 'otype' => 'AN'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'R', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 1632, 'level' => 0, 'type' => 'AN', 'otype' => 'AN'),
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
                        array('pos' => 0, 'char' => 1632, 'level' => 0, 'type' => 'AN', 'otype' => 'AN'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
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
                        array('pos' => 0, 'char' => 1632, 'level' => 0, 'type' => 'AN', 'otype' => 'AN'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'R', 'otype' => 'NI'),
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
                        array('pos' => 0, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 8207, 'level' => 0, 'type' => 'R',  'otype' => 'R'),
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
                        array('pos' => 0, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'R', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 8207, 'level' => 0, 'type' => 'R', 'otype' => 'R'),
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
                        array('pos' => 0, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 1632, 'level' => 0, 'type' => 'AN', 'otype' => 'AN'),
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
                        array('pos' => 0, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'R', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 1632, 'level' => 0, 'type' => 'AN', 'otype' => 'AN'),
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
                        array('pos' => 0, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
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
                        array('pos' => 0, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'R', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
                    ),
                )
            ),
            array(
                array(
                    'e' => 0,
                    'edir' => 'L',
                    'start' => 0,
                    'end' => 3,
                    'length' => 4,
                    'sos' => 'L',
                    'eos' => 'L',
                    'item' => array(
                        array('pos' => 0, 'char' => 65,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 5760, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 3, 'char' => 65,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'L',
                    'start' => 0,
                    'end' => 3,
                    'length' => 4,
                    'sos' => 'L',
                    'eos' => 'L',
                    'item' => array(
                        array('pos' => 0, 'char' => 65,   'level' => 0, 'type' => 'L', 'otype' => 'L'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'L', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 5760, 'level' => 0, 'type' => 'L', 'otype' => 'NI'),
                        array('pos' => 3, 'char' => 65,   'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    ),
                )
            ),
        );
    }

    /**
     * @dataProvider stepN2DataProvider
     */
    public function testStepN2($seq, $expected)
    {
        $stepn = new \Com\Tecnick\Unicode\Bidi\StepN($seq, false);
        $stepn->processStep('processN2');
        $this->assertEquals($expected, $stepn->getSequence());
    }

    public function stepN2DataProvider()
    {
        return array(
            array(
                array(
                    'e' => 0,
                    'edir' => 'L',
                    'start' => 0,
                    'end' => 1,
                    'length' => 1,
                    'sos' => 'L',
                    'eos' => 'L',
                    'item' => array(
                        array('pos' => 0, 'char' => 5760, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'L',
                    'start' => 0,
                    'end' => 1,
                    'length' => 1,
                    'sos' => 'L',
                    'eos' => 'L',
                    'item' => array(
                        array('pos' => 0, 'char' => 5760, 'level' => 0, 'type' => 'L', 'otype' => 'NI'),
                    ),
                )
            ),
        );
    }

    /**
     * @dataProvider stepNDataProvider
     */
    public function testStepN($seq, $expected)
    {
        $stepn = new \Com\Tecnick\Unicode\Bidi\StepN($seq);
        $this->assertEquals($expected, $stepn->getSequence());
    }

    public function stepNDataProvider()
    {
        return array(
            array(
                array(
                    'e' => 0,
                    'edir' => 'L',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'L',
                    'item' => array(
                        array('pos' => 0, 'char' => 65,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 65,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'L',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'L',
                    'item' => array(
                        array('pos' => 0, 'char' => 65, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'L', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 65, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
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
                    'sos' => 'R',
                    'eos' => 'L',
                    'item' => array(
                        array('pos' => 0, 'char' => 8207, 'level' => 0, 'type' => 'R',  'otype' => 'R'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 65,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'L',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'L',
                    'item' => array(
                        array('pos' => 0, 'char' => 8207, 'level' => 0, 'type' => 'R', 'otype' => 'R'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'L', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 65, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
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
                    'sos' => 'R',
                    'eos' => 'L',
                    'item' => array(
                        array('pos' => 0, 'char' => 65,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 8207, 'level' => 0, 'type' => 'R',  'otype' => 'R'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'L',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'L',
                    'item' => array(
                        array('pos' => 0, 'char' => 65, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'L', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 8207, 'level' => 0, 'type' => 'R', 'otype' => 'R'),
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
                    'sos' => 'R',
                    'eos' => 'L',
                    'item' => array(
                        array('pos' => 0, 'char' => 8207, 'level' => 0, 'type' => 'R',  'otype' => 'R'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 8207, 'level' => 0, 'type' => 'R',  'otype' => 'R'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'L',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'L',
                    'item' => array(
                        array('pos' => 0, 'char' => 8207, 'level' => 0, 'type' => 'R', 'otype' => 'R'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'R', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 8207, 'level' => 0, 'type' => 'R', 'otype' => 'R'),
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
                    'sos' => 'R',
                    'eos' => 'L',
                    'item' => array(
                        array('pos' => 0, 'char' => 5760, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 1, 'char' => 91,   'level' => 0, 'type' => 'ON', 'otype' => 'ON'),
                        array('pos' => 2, 'char' => 5760, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'L',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'L',
                    'item' => array(
                        array('pos' => 0, 'char' => 5760, 'level' => 0, 'type' => 'L', 'otype' => 'NI'),
                        array('pos' => 1, 'char' => 91,   'level' => 0, 'type' => 'ON', 'otype' => 'ON'),
                        array('pos' => 2, 'char' => 5760, 'level' => 0, 'type' => 'L', 'otype' => 'NI'),
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
                    'sos' => 'R',
                    'eos' => 'L',
                    'item' => array(
                        array('pos' => 0, 'char' => 8207, 'level' => 0, 'type' => 'R',  'otype' => 'R'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 5760, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                    ),
                ),
                array(
                    'e' => 0,
                    'edir' => 'L',
                    'start' => 0,
                    'end' => 2,
                    'length' => 3,
                    'sos' => 'R',
                    'eos' => 'L',
                    'item' => array(
                        array('pos' => 0, 'char' => 8207, 'level' => 0, 'type' => 'R',  'otype' => 'R'),
                        array('pos' => 1, 'char' => 5760, 'level' => 0, 'type' => 'L', 'otype' => 'NI'),
                        array('pos' => 2, 'char' => 5760, 'level' => 0, 'type' => 'L', 'otype' => 'NI'),
                    ),
                )
            ),
        );
    }
}
