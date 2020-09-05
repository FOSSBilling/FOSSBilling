<?php
/**
 * StepITest.php
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
class StepITest extends TestCase
{
    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test
    }

    /**
     * @dataProvider stepIDataProvider
     */
    public function testStepI($seq, $expected)
    {
        $stepi = new \Com\Tecnick\Unicode\Bidi\StepI($seq);
        $this->assertEquals($expected, $stepi->getSequence());
    }

    public function stepIDataProvider()
    {
        return array(
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
                        array('pos' => 1, 'char' => 8207, 'level' => 0, 'type' => 'R',  'otype' => 'R'),
                        array('pos' => 2, 'char' => 1632, 'level' => 0, 'type' => 'AN', 'otype' => 'AN'),
                        array('pos' => 3, 'char' => 1776, 'level' => 0, 'type' => 'EN', 'otype' => 'EN'),
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
                        array('pos' => 0, 'char' => 65, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                        array('pos' => 1, 'char' => 8207, 'level' => 1, 'type' => 'R', 'otype' => 'R'),
                        array('pos' => 2, 'char' => 1632, 'level' => 2, 'type' => 'AN', 'otype' => 'AN'),
                        array('pos' => 3, 'char' => 1776, 'level' => 2, 'type' => 'EN', 'otype' => 'EN'),
                    ),
                    'maxlevel' => 2,
                )
            ),
            array(
                array(
                    'e' => 1,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 3,
                    'length' => 4,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 65,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                        array('pos' => 1, 'char' => 8207, 'level' => 1, 'type' => 'R',  'otype' => 'R'),
                        array('pos' => 2, 'char' => 1632, 'level' => 1, 'type' => 'AN', 'otype' => 'AN'),
                        array('pos' => 3, 'char' => 1776, 'level' => 1, 'type' => 'EN', 'otype' => 'EN'),
                    ),
                ),
                array(
                    'e' => 1,
                    'edir' => 'R',
                    'start' => 0,
                    'end' => 3,
                    'length' => 4,
                    'sos' => 'R',
                    'eos' => 'R',
                    'item' => array(
                        array('pos' => 0, 'char' => 65, 'level' => 2, 'type' => 'L', 'otype' => 'L'),
                        array('pos' => 1, 'char' => 8207, 'level' => 1, 'type' => 'R', 'otype' => 'R'),
                        array('pos' => 2, 'char' => 1632, 'level' => 2, 'type' => 'AN', 'otype' => 'AN'),
                        array('pos' => 3, 'char' => 1776, 'level' => 2, 'type' => 'EN', 'otype' => 'EN'),
                    ),
                    'maxlevel' => 2,
                )
            ),
        );
    }
}
