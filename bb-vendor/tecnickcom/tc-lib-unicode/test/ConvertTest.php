<?php
/**
 * ConvertTest.php
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
 * Convert Test
 *
 * @since       2011-05-23
 * @category    Library
 * @package     Unicode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-unicode
 */
class ConvertTest extends TestCase
{
    protected $obj = null;

    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test
        $this->obj = new \Com\Tecnick\Unicode\Convert();
    }

    /**
     * @dataProvider chrDataProvider
     */
    public function testChr($ord, $expected)
    {
        $chr = $this->obj->chr($ord);
        $this->assertEquals($expected, $chr);
    }

    /**
     * @dataProvider chrDataProvider
     */
    public function testOrd($expected, $chr)
    {
        $ord = $this->obj->ord($chr);
        $this->assertEquals($expected, $ord);
    }

    public function chrDataProvider()
    {
        return array(
            array(32, ' '),
            array(48, '0'),
            array(65, 'A'),
            array(182, '¶'),
            array(255, 'ÿ'),
            array(256, 'Ā'),
            array(544, 'Ƞ'),
            array(916, 'Δ'),
            array(1488, 'א'),
            array(21488, '台'),
            array(49436, '서'),
            array(70039, '𑆗'),
            array(195101, '𪘀')
        );
    }

    public function testStrToChrArr()
    {
        $res = $this->obj->strToChrArr('0A¶ÿĀȠΔא台서');
        $this->assertEquals(array('0', 'A', '¶', 'ÿ', 'Ā', 'Ƞ', 'Δ', 'א', '台', '서'), $res);
    }

    public function testChrArrToOrdArr()
    {
        $res = $this->obj->chrArrToOrdArr(array('0', 'A', '¶', 'ÿ', 'Ā', 'Ƞ', 'Δ', 'א', '台', '서'));
        $this->assertEquals(array(48, 65, 182, 255, 256, 544, 916, 1488, 21488, 49436), $res);
    }

    public function testOrdArrToChrArr()
    {
        $res = $this->obj->ordArrToChrArr(array(48, 65, 182, 255, 256, 544, 916, 1488, 21488, 49436));
        $this->assertEquals(array('0', 'A', '¶', 'ÿ', 'Ā', 'Ƞ', 'Δ', 'א', '台', '서'), $res);
    }

    public function testStrToOrdArr()
    {
        $res = $this->obj->strToOrdArr('0A¶ÿĀȠΔא台서');
        $this->assertEquals(array(48, 65, 182, 255, 256, 544, 916, 1488, 21488, 49436), $res);
    }

    public function testGetSubUniArrStr()
    {
        $res = $this->obj->getSubUniArrStr(array('0', 'A', '¶', 'ÿ', 'Ā', 'Ƞ', 'Δ', 'א', '台', '서'));
        $this->assertEquals('0A¶ÿĀȠΔא台서', $res);

        $res = $this->obj->getSubUniArrStr(array('0', 'A', '¶', 'ÿ', 'Ā', 'Ƞ', 'Δ', 'א', '台', '서'), 2, 8);
        $this->assertEquals('¶ÿĀȠΔא', $res);
    }

    public function testUniArrToLatinArr()
    {
        $uniarr = array_keys(\Com\Tecnick\Unicode\Data\Latin::$substitute);
        $uniarr[] = 65533;  // 0xFFFD - character to ignore
        $uniarr[] = 123456; // undefined char
        $uniarr[] = 65;     // ASCII char
        $latarr = array_values(\Com\Tecnick\Unicode\Data\Latin::$substitute);
        $latarr[] = 63;
        $latarr[] = 65;
        $res = $this->obj->uniArrToLatinArr($uniarr);
        $this->assertEquals($latarr, $res);
    }

    public function testLatinArrToStr()
    {
        $res = $this->obj->latinArrToStr(array(48, 57, 65, 90, 97, 122));
        $this->assertEquals('09AZaz', $res);
    }

    /**
     * @dataProvider strToHexDataProvider
     */
    public function testStrToHex($str, $hex)
    {
        $res = $this->obj->strToHex($str);
        $this->assertEquals($hex, $res);
    }

    /**
     * @dataProvider strToHexDataProvider
     */
    public function testHexToStr($str, $hex)
    {
        $res = $this->obj->hexToStr($hex);
        $this->assertEquals($str, $res);
    }

    public function strToHexDataProvider()
    {
        return array(
            array('', ''),
            array('A', '41'),
            array('AB', '4142'),
            array('ABC', '414243'),
            array("\n", '0a'),
        );
    }

    /**
     * @dataProvider toUTF16BEDataProvider
     */
    public function testToUTF16BE($str, $exp)
    {
        $res = $this->obj->toUTF16BE($str);
        $this->assertEquals($exp, $this->obj->strToHex($res));
    }

    public function toUTF16BEDataProvider()
    {
        return array(
            array('', ''),
            array('ABC', '004100420043'),
            array(json_decode('"\u0010\uffff\u00ff\uff00"'), '0010ffff00ffff00'),
        );
    }

    /**
     * @dataProvider toUTF8DataProvider
     */
    public function testToUTF8($str, $exp, $enc = null)
    {
        $res = $this->obj->toUTF8($str, $enc);
        $this->assertEquals($exp, $res);
    }

    public function toUTF8DataProvider()
    {
        return array(
            array('', ''),
            array('òèìòù', 'òèìòù'),
            array('òèìòù', 'Ã²Ã¨Ã¬Ã²Ã¹', 'ISO-8859-1'),
        );
    }
}
