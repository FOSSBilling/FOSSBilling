<?php
/**
 * StepLTest.php
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
class StepLTest extends TestCase
{
    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test
    }

    /**
     * @dataProvider stepLDataProvider
     */
    public function testStepL($chardata, $pel, $maxlevel, $expected)
    {
        $stepl = new \Com\Tecnick\Unicode\Bidi\StepL($chardata, $pel, $maxlevel);
        $this->assertEquals($expected, $stepl->getChrData());
    }

    public function stepLDataProvider()
    {
        return array(
            array(
                // car means CAR.
                // 00000000001110
                array(
                    array('pos' => 0, 'char' => 99,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 1, 'char' => 97,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 2, 'char' => 114,  'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 3, 'char' => 32,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 4, 'char' => 109,  'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 5, 'char' => 101,  'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 6, 'char' => 97,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 7, 'char' => 110,  'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 8, 'char' => 115,  'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 9, 'char' => 32,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 10, 'char' => 67,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 11, 'char' => 65,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 12, 'char' => 82,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 13, 'char' => 46,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                ),
                0,
                1,
                // car means RAC.
                array(
                    array('pos' => 0, 'char' => 99, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 1, 'char' => 97, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 2, 'char' => 114, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 3, 'char' => 32, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 4, 'char' => 109, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 5, 'char' => 101, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 6, 'char' => 97, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 7, 'char' => 110, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 8, 'char' => 115, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 9, 'char' => 32, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 12, 'char' => 82, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 11, 'char' => 65, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 10, 'char' => 67, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 13, 'char' => 46, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                )
            ),
            array(
                // <car MEANS CAR.=
                // 0222111111111110
                array(
                    array('pos' => 0, 'char' => 8295, 'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 1, 'char' => 99,   'level' => 2, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 2, 'char' => 97,   'level' => 2, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 3, 'char' => 114,  'level' => 2, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 4, 'char' => 32,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 5, 'char' => 77,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 6, 'char' => 69,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 7, 'char' => 65,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 8, 'char' => 78,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 9, 'char' => 83,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 10, 'char' => 32,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 11, 'char' => 67,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 12, 'char' => 65,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 13, 'char' => 82,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 14, 'char' => 46,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 15, 'char' => 8297, 'level' => 0, 'type' => 'L',  'otype' => 'L'),
                ),
                0,
                2,
                // <.RAC SNAEM car=
                array(
                    array('pos' => 0, 'char' => 8295, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 14, 'char' => 46,   'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 13, 'char' => 82,   'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 12, 'char' => 65,   'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 11, 'char' => 67,   'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 10, 'char' => 32,   'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 9, 'char' => 83,   'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 8, 'char' => 78,   'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 7, 'char' => 65,   'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 6, 'char' => 69,   'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 5, 'char' => 77,   'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 4, 'char' => 32,   'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 1, 'char' => 99,   'level' => 2, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 2, 'char' => 97,   'level' => 2, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 3, 'char' => 114,  'level' => 2, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 15, 'char' => 8297, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                )
            ),
            array(
                // he said "<car MEANS CAR=." "<IT DOES=," she agreed.
                // 000000000022211111111110000001111111000000000000000
                array(
                    array('pos' => 0, 'char' => 104,  'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 1, 'char' => 101,  'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 2, 'char' => 32,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 3, 'char' => 115,  'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 4, 'char' => 97,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 5, 'char' => 105,  'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 6, 'char' => 100,  'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 7, 'char' => 32,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 8, 'char' => 34,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 9, 'char' => 8295, 'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 10, 'char' => 99,   'level' => 2, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 11, 'char' => 97,   'level' => 2, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 12, 'char' => 114,  'level' => 2, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 13, 'char' => 32,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 14, 'char' => 77,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 15, 'char' => 69,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 16, 'char' => 65,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 17, 'char' => 78,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 18, 'char' => 83,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 19, 'char' => 32,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 20, 'char' => 67,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 21, 'char' => 65,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 22, 'char' => 82,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 23, 'char' => 8297, 'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 24, 'char' => 46,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 25, 'char' => 34,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 26, 'char' => 32,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 27, 'char' => 34,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 28, 'char' => 8295, 'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 29, 'char' => 73,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 30, 'char' => 84,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 31, 'char' => 32,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 32, 'char' => 68,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 33, 'char' => 79,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 34, 'char' => 69,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 35, 'char' => 83,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 36, 'char' => 8297, 'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 37, 'char' => 44,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 38, 'char' => 34,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 39, 'char' => 32,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 40, 'char' => 115,  'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 41, 'char' => 104,  'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 42, 'char' => 101,  'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 43, 'char' => 32,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 44, 'char' => 97,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 45, 'char' => 103,  'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 46, 'char' => 114,  'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 47, 'char' => 101,  'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 48, 'char' => 101,  'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 49, 'char' => 100,  'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 50, 'char' => 46,   'level' => 0, 'type' => 'L',  'otype' => 'L'),
                ),
                0,
                2,
                // he said "<RAC SNAEM car=." "<SEOD TI=," she agreed.
                array(
                    array('pos' => 0, 'char' => 104, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 1, 'char' => 101, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 2, 'char' => 32, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 3, 'char' => 115, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 4, 'char' => 97, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 5, 'char' => 105, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 6, 'char' => 100, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 7, 'char' => 32, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 8, 'char' => 34, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 9, 'char' => 8295, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 22, 'char' => 82, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 21, 'char' => 65, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 20, 'char' => 67, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 19, 'char' => 32, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 18, 'char' => 83, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 17, 'char' => 78, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 16, 'char' => 65, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 15, 'char' => 69, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 14, 'char' => 77, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 13, 'char' => 32, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 10, 'char' => 99, 'level' => 2, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 11, 'char' => 97, 'level' => 2, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 12, 'char' => 114, 'level' => 2, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 23, 'char' => 8297, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 24, 'char' => 46, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 25, 'char' => 34, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 26, 'char' => 32, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 27, 'char' => 34, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 28, 'char' => 8295, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 35, 'char' => 83, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 34, 'char' => 69, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 33, 'char' => 79, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 32, 'char' => 68, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 31, 'char' => 32, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 30, 'char' => 84, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 29, 'char' => 73, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 36, 'char' => 8297, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 37, 'char' => 44, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 38, 'char' => 34, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 39, 'char' => 32, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 40, 'char' => 115, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 41, 'char' => 104, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 42, 'char' => 101, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 43, 'char' => 32, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 44, 'char' => 97, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 45, 'char' => 103, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 46, 'char' => 114, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 47, 'char' => 101, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 48, 'char' => 101, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 49, 'char' => 100, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 50, 'char' => 46, 'level' => 0, 'type' => 'L', 'otype' => 'L'),
                )
            ),
            array(
                // DID YOU SAY '>he said "<car MEANS CAR="='?
                // 111111111111112222222222444333333333322111
                array(
                    array('pos' => 0, 'char' => 68,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 1, 'char' => 73,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 2, 'char' => 68,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 3, 'char' => 32,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 4, 'char' => 89,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 5, 'char' => 79,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 6, 'char' => 85,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 7, 'char' => 32,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 8, 'char' => 83,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 9, 'char' => 65,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 10, 'char' => 89,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 11, 'char' => 32,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 12, 'char' => 39,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 13, 'char' => 8294, 'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 14, 'char' => 104,  'level' => 2, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 15, 'char' => 101,  'level' => 2, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 16, 'char' => 32,   'level' => 2, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 17, 'char' => 115,  'level' => 2, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 18, 'char' => 97,   'level' => 2, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 19, 'char' => 105,  'level' => 2, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 20, 'char' => 100,  'level' => 2, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 21, 'char' => 32,   'level' => 2, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 22, 'char' => 34,   'level' => 2, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 23, 'char' => 8295, 'level' => 2, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 24, 'char' => 99,   'level' => 4, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 25, 'char' => 97,   'level' => 4, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 26, 'char' => 114,  'level' => 4, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 27, 'char' => 32,   'level' => 3, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 28, 'char' => 77,   'level' => 3, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 29, 'char' => 69,   'level' => 3, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 30, 'char' => 65,   'level' => 3, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 31, 'char' => 78,   'level' => 3, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 32, 'char' => 83,   'level' => 3, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 33, 'char' => 32,   'level' => 3, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 34, 'char' => 67,   'level' => 3, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 35, 'char' => 65,   'level' => 3, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 36, 'char' => 82,   'level' => 3, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 37, 'char' => 8297, 'level' => 2, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 38, 'char' => 34,   'level' => 2, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 39, 'char' => 8297, 'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 40, 'char' => 39,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 41, 'char' => 63,   'level' => 1, 'type' => 'L',  'otype' => 'L'),
                ),
                1,
                4,
                // ?'=he said "<RAC SNAEM car=">' YAS UOY DID
                array(
                    array('pos' => 41, 'char' => 63, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 40, 'char' => 39, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 39, 'char' => 8297, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 14, 'char' => 104, 'level' => 2, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 15, 'char' => 101, 'level' => 2, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 16, 'char' => 32, 'level' => 2, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 17, 'char' => 115, 'level' => 2, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 18, 'char' => 97, 'level' => 2, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 19, 'char' => 105, 'level' => 2, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 20, 'char' => 100, 'level' => 2, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 21, 'char' => 32, 'level' => 2, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 22, 'char' => 34, 'level' => 2, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 23, 'char' => 8295, 'level' => 2, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 36, 'char' => 82, 'level' => 3, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 35, 'char' => 65, 'level' => 3, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 34, 'char' => 67, 'level' => 3, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 33, 'char' => 32, 'level' => 3, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 32, 'char' => 83, 'level' => 3, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 31, 'char' => 78, 'level' => 3, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 30, 'char' => 65, 'level' => 3, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 29, 'char' => 69, 'level' => 3, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 28, 'char' => 77, 'level' => 3, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 27, 'char' => 32, 'level' => 3, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 24, 'char' => 99, 'level' => 4, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 25, 'char' => 97, 'level' => 4, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 26, 'char' => 114, 'level' => 4, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 37, 'char' => 8297, 'level' => 2, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 38, 'char' => 34, 'level' => 2, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 13, 'char' => 8294, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 12, 'char' => 39, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 11, 'char' => 32, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 10, 'char' => 89, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 9, 'char' => 65, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 8, 'char' => 83, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 7, 'char' => 32, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 6, 'char' => 85, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 5, 'char' => 79, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 4, 'char' => 89, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 3, 'char' => 32, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 2, 'char' => 68, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 1, 'char' => 73, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                    array('pos' => 0, 'char' => 68, 'level' => 1, 'type' => 'L', 'otype' => 'L'),
                )
            ),
            array(
                array(
                    array('pos' => 0, 'char' => 11032, 'level' => 0, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 1, 'char' => 99,    'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 2, 'char' => 12,    'level' => 0, 'type' => 'WS', 'otype' => 'WS'),
                    array('pos' => 3, 'char' => 10,    'level' => 0, 'type' => 'B',  'otype' => 'B'),
                    array('pos' => 4, 'char' => 97,    'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 5, 'char' => 11032, 'level' => 0, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 6, 'char' => 12288, 'level' => 0, 'type' => 'WS', 'otype' => 'WS'),
                    array('pos' => 7, 'char' => 10,    'level' => 0, 'type' => 'B',  'otype' => 'B'),
                ),
                0,
                0,
                array(
                    array('pos' => 0, 'char' => 11032, 'level' => 0, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 1, 'char' => 99,    'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 2, 'char' => 12,    'level' => 0, 'type' => 'WS', 'otype' => 'WS'),
                    array('pos' => 3, 'char' => 10,    'level' => 0, 'type' => 'B',  'otype' => 'B'),
                    array('pos' => 4, 'char' => 97,    'level' => 0, 'type' => 'L',  'otype' => 'L'),
                    array('pos' => 5, 'char' => 11032, 'level' => 0, 'type' => 'ON', 'otype' => 'ON'),
                    array('pos' => 6, 'char' => 12288, 'level' => 0, 'type' => 'WS', 'otype' => 'WS'),
                    array('pos' => 7, 'char' => 10,    'level' => 0, 'type' => 'B',  'otype' => 'B'),
                )
            ),
        );
    }
}
