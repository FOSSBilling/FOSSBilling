<?php
/**
 * BidiTest.php
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

namespace Test;

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
class BidiTest extends TestCase
{
    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test
    }
    
    /**
     * @expectedException \Com\Tecnick\Unicode\Exception
     */
    public function testException()
    {
        new \Com\Tecnick\Unicode\Bidi(null, null, null, false);
    }

    /**
     * @dataProvider inputDataProvider
     */
    public function testStr($str, $charr, $ordarr, $forcertl)
    {
        $bidi = new \Com\Tecnick\Unicode\Bidi($str, $charr, $ordarr, $forcertl);
        $this->assertEquals('test', $bidi->getString());
        $this->assertEquals(array('t', 'e', 's', 't'), $bidi->getChrArray());
        $this->assertEquals(array(116, 101, 115, 116), $bidi->getOrdArray());
        $this->assertEquals(array(116 => true, 101 => true, 115 => true), $bidi->getCharKeys());
        $this->assertEquals(4, $bidi->getNumChars());
    }

    public function inputDataProvider()
    {
        return array(
            array('test', null, null, false),
            array(null, array('t', 'e', 's', 't'), null, false),
            array(null, null, array(116, 101, 115, 116), false),
            array('test', array('t', 'e', 's', 't'), null, false),
            array('test', null, array(116, 101, 115, 116), false),
            array(null, array('t', 'e', 's', 't'), array(116, 101, 115, 116), false),
            array('test', array('t', 'e', 's', 't'), array(116, 101, 115, 116), false),
            array('test', null, null, 'L'),
            array('test', null, null, 'R'),
        );
    }

    /**
     * @dataProvider bidiStrDataProvider
     */
    public function testBidiStr($str, $expected, $forcertl = false)
    {
        $bidi = new \Com\Tecnick\Unicode\Bidi($str, null, null, $forcertl);
        $this->assertEquals($expected, $bidi->getString());
    }

    public function bidiStrDataProvider()
    {
        return array(
            array(
                "\n\nABC\nEFG\n\nHIJ\n\n",
                "\n\nABC\nEFG\n\nHIJ\n\n",
                'L'
            ),
            array(
                json_decode('"\u202EABC\u202C"'),
                'CBA'
            ),
            array(
                json_decode('"smith (fabrikam \u0600\u0601\u0602) \u05de\u05d6\u05dc"'),
                json_decode('"\u05dc\u05d6\u05de (\u0602\u0601\u0600 fabrikam) smith"'),
                'R'
            ),
            array(
                json_decode('"\u0600\u0601\u0602 book(s)"'),
                json_decode('"book(s) \u0602\u0601\u0600"'),
                'R'
            ),
            array(
                json_decode('"\u0600\u0601(\u0602\u0603[&ef]!)gh"'),
                json_decode('"gh(![ef&]\u0603\u0602)\u0601\u0600"'),
                'R'
            ),
            array(
                'تشكيل اختبار',
                'ﺭﺎﺒﺘﺧﺍ ﻞﻴﻜﺸﺗ'
            ),
            array(
                json_decode('"\u05de\u05d6\u05dc \u05d8\u05d5\u05d1"'),
                json_decode('"\u05d1\u05d5\u05d8 \u05dc\u05d6\u05de"'),
            ),
            array(
                json_decode(
                    '"\u0644\u0644\u0647 \u0600\u0601\u0602 \uFB50'
                    .' \u0651\u064c\u0651\u064d\u0651\u064e\u0651\u064f\u0651\u0650'
                    .' \u0644\u0622"'
                ),
                json_decode('"\ufef5 \ufc62\ufc61\ufc60\ufc5f\ufc5e \ufb50 \u0602\u0601\u0600 \ufdf2"'),
            ),
            array(
                json_decode('"A\u2067\u05d8\u2069B"'),
                json_decode('"A\u2067\u05d8\u2069B"'),
            ),
            array( // RLI + PDI
                json_decode(
                    '"The words '
                    .'\"\u2067\u05de\u05d6\u05dc [mazel] \u05d8\u05d5\u05d1 [tov]\u2069\"'
                    .' mean \"Congratulations!\""'
                ),
                'The words "⁧[tov] בוט [mazel] לזמ⁩" mean "Congratulations!"',
            ),
            array( // RLE + PDF
                json_decode('"it is called \"\u202bAN INTRODUCTION TO java\u202c\" - $19.95 in hardcover."'),
                'it is called "java TO INTRODUCTION AN" - $19.95 in hardcover.',
            ),
            array( // RLI + PDI
                json_decode('"it is called \"\u2067AN INTRODUCTION TO java\u2069\" - $19.95 in hardcover."'),
                'it is called "⁧java TO INTRODUCTION AN⁩" - $19.95 in hardcover.',
            ),
        );
    }
}
