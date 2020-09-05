<?php
/**
 * StepXTest.php
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
class StepXTest extends TestCase
{
    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test
    }
    
    /**
     * @dataProvider stepXDataProvider
     */
    public function testStepX($ordarr, $pel, $expected)
    {
        $stepx = new \Com\Tecnick\Unicode\Bidi\StepX($ordarr, $pel);
        $this->assertEquals($expected, $stepx->getChrData());
    }

    public function stepXDataProvider()
    {
        return array(
            array(
                // BD13 Example 1: text1·RLE·text2·PDF·RLE·text3·PDF·text4
                array(33,8235,34,8236,8235,38,8236,39),
                0,
                array(
                    array('pos' => 0, 'char' => 33,   'level' => 0, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 2, 'char' => 34,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 5, 'char' => 38,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 7, 'char' => 39,   'level' => 0, 'type' => 'ON', 'otype' => 'ON')
                )
            ),
            array(
                // BD13 Example 2: text1·RLI·text2·PDI·RLI·text3·PDI·text4
                array(33,8295,34,8297,8295,38,8297,39),
                0,
                array(
                    array('pos' => 0, 'char' => 33,   'level' => 0, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 1, 'char' => 8295, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                    array('pos' => 2, 'char' => 34,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 3, 'char' => 8297, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                    array('pos' => 4, 'char' => 8295, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                    array('pos' => 5, 'char' => 38,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 6, 'char' => 8297, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                    array('pos' => 7, 'char' => 39,   'level' => 0, 'type' => 'ON', 'otype' => 'ON')
                )
            ),
            array(
                // BD13 Example 3: text1·RLI·text2·LRI·text3·RLE·text4·PDF·text5·PDI·text6·PDI·text7
                array(33,8295,34,8294,38,8235,39,8236,40,8297,41,8297,42),
                0,
                array(
                    array('pos' => 0, 'char' => 33,   'level' => 0, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 1, 'char' => 8295, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                    array('pos' => 2, 'char' => 34,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 3, 'char' => 8294, 'level' => 1, 'type' => 'NI', 'otype' => 'NI'),
                    array('pos' => 4, 'char' => 38,   'level' => 2, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 6, 'char' => 39,   'level' => 3, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 8, 'char' => 40,   'level' => 2, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 9, 'char' => 8297, 'level' => 1, 'type' => 'NI', 'otype' => 'NI'),
                    array('pos' => 10, 'char' => 41,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 11, 'char' => 8297, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                    array('pos' => 12, 'char' => 42,   'level' => 0, 'type' => 'ON', 'otype' => 'ON')
                )
            ),
            array(
                // X10 Example 1: text1·RLE·text2·LRE·text3·PDF·text4·PDF·RLE·text5·PDF·text6
                array(33,8235,34,8234,38,8236,39,8236,8235,40,8236,41),
                0,
                array(
                    array('pos' => 0, 'char' => 33,   'level' => 0, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 2, 'char' => 34,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 4, 'char' => 38,   'level' => 2, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 6, 'char' => 39,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 9, 'char' => 40,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 11, 'char' => 41,   'level' => 0, 'type' => 'ON', 'otype' => 'ON')
                )
            ),
            array(
                // X10 Example 2: text1·RLI·text2·LRI·text3·PDI·text4·PDI·RLI·text5·PDI·text6
                array(33,8295,34,8294,38,8297,39,8297,8295,40,8297,41),
                0,
                array(
                    array('pos' => 0, 'char' => 33,   'level' => 0, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 1, 'char' => 8295, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                    array('pos' => 2, 'char' => 34,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 3, 'char' => 8294, 'level' => 1, 'type' => 'NI', 'otype' => 'NI'),
                    array('pos' => 4, 'char' => 38,   'level' => 2, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 5, 'char' => 8297, 'level' => 1, 'type' => 'NI', 'otype' => 'NI'),
                    array('pos' => 6, 'char' => 39,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 7, 'char' => 8297, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                    array('pos' => 8, 'char' => 8295, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                    array('pos' => 9, 'char' => 40,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 10, 'char' => 8297, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                    array('pos' => 11, 'char' => 41,   'level' => 0, 'type' => 'ON', 'otype' => 'ON')
                )
            ),
            array(
                // X10 Example 3: text1·RLE·text2·LRI·text3·RLE·text4·PDI·text5·PDF·text6
                array(33,8235,34,8294,38,8235,39,8297,40,8236,41),
                0,
                array(
                    array('pos' => 0, 'char' => 33,   'level' => 0, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 2, 'char' => 34,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 3, 'char' => 8294, 'level' => 1, 'type' => 'NI', 'otype' => 'NI'),
                    array('pos' => 4, 'char' => 38,   'level' => 2, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 6, 'char' => 39,   'level' => 3, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 7, 'char' => 8297, 'level' => 1, 'type' => 'NI', 'otype' => 'NI'),
                    array('pos' => 8, 'char' => 40,   'level' => 1, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 10, 'char' => 41,   'level' => 0, 'type' => 'ON', 'otype' => 'ON')
                )
            ),
            array(
                // text1·RLO·text2·LRO·text3·RLO·text4·PDF·text5·PDF·text6
                array(33,8238,34,8237,38,8238,39,8236,40,8236,41),
                0,
                array(
                    array('pos' => 0, 'char' => 33,   'level' => 0, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 2, 'char' => 34,   'level' => 1, 'type' => 'R',  'otype' => 'ON'),
                    array('pos' => 4, 'char' => 38,   'level' => 2, 'type' => 'L',  'otype' => 'ON'),
                    array('pos' => 6, 'char' => 39,   'level' => 3, 'type' => 'R',  'otype' => 'ON'),
                    array('pos' => 8, 'char' => 40,   'level' => 2, 'type' => 'L',  'otype' => 'ON'),
                    array('pos' => 10, 'char' => 41,   'level' => 1, 'type' => 'R',  'otype' => 'ON')
                )
            ),
            array(
                // text1·FSI·text2·PDI·text3
                array(33,8296,34,8297,38),
                0,
                array(
                    array('pos' => 0, 'char' => 33,   'level' => 0, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 1, 'char' => 8296, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                    array('pos' => 2, 'char' => 34,   'level' => 2, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 3, 'char' => 8297, 'level' => 0, 'type' => 'NI', 'otype' => 'NI'),
                    array('pos' => 4, 'char' => 38,   'level' => 0, 'type' => 'ON', 'otype' => 'ON'),
                )
            ),
            array(
                // text1·FSI·text2·PDI·text3
                array(1488,8296,1489,8297,1490),
                1,
                array(
                    array('pos' => 0, 'char' => 1488, 'level' => 1, 'type' => 'R',  'otype' => 'R'),
                    array('pos' => 1, 'char' => 8296, 'level' => 1, 'type' => 'NI', 'otype' => 'NI'),
                    array('pos' => 2, 'char' => 1489, 'level' => 3, 'type' => 'R',  'otype' => 'R'),
                    array('pos' => 3, 'char' => 8297, 'level' => 1, 'type' => 'NI', 'otype' => 'NI'),
                    array('pos' => 4, 'char' => 1490, 'level' => 1, 'type' => 'R',  'otype' => 'R'),
                )
            ),
            array(
                // text1·BN·text2·BN·text3
                array(33,1807,34,1807,38),
                0,
                array(
                    array('pos' => 0, 'char' => 33, 'level' => 0, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 2, 'char' => 34, 'level' => 0, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 4, 'char' => 38, 'level' => 0, 'type' => 'ON', 'otype' => 'ON'),
                )
            ),
            array(
                // Test overflow: text1·130xLRE·LRI·PDF·PDI·PDF·PDI·text2
                array(
                    33,
                    8234,8234,8234,8234,8234,8234,8234,8234,8234,8234,
                    8234,8234,8234,8234,8234,8234,8234,8234,8234,8234,
                    8234,8234,8234,8234,8234,8234,8234,8234,8234,8234,
                    8234,8234,8234,8234,8234,8234,8234,8234,8234,8234,
                    8234,8234,8234,8234,8234,8234,8234,8234,8234,8234,
                    8234,8234,8234,8234,8234,8234,8234,8234,8234,8234,
                    8234,8234,8234,8234,8234,8234,8234,8234,8234,8234,
                    8234,8234,8234,8234,8234,8234,8234,8234,8234,8234,
                    8234,8234,8234,8234,8234,8234,8234,8234,8234,8234,
                    8234,8234,8234,8234,8234,8234,8234,8234,8234,8234,
                    8234,8234,8234,8234,8234,8234,8234,8234,8234,8234,
                    8234,8234,8234,8234,8234,8234,8234,8234,8234,8234,
                    8294,8236,8297,8236,8297,
                    34
                ),
                0,
                array(
                    array('pos' => 0, 'char' => 33,   'level' => 0,   'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 121, 'char' => 8294, 'level' => 124, 'type' => 'NI', 'otype' => 'NI'),
                    array('pos' => 126, 'char' => 34,   'level' => 124, 'type' => 'ON', 'otype' => 'ON'),
                )
            ),
        );
    }
}
