<?php
/**
 * ByteTest.php
 *
 * @since       2015-07-28
 * @category    Library
 * @package     File
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-file
 *
 * This file is part of tc-lib-file software library.
 */

namespace Test;

use PHPUnit\Framework\TestCase;

/**
 * Byte Color class test
 *
 * @since       2015-07-28
 * @category    Library
 * @package     File
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-file
 */
class ByteTest extends TestCase
{
    protected $obj = null;

    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test
        $str = chr(0).chr(0).chr(0).chr(0)
            .chr(1).chr(3).chr(7).chr(15)
            .chr(31).chr(63).chr(127).chr(255)
            .chr(254).chr(252).chr(248).chr(240)
            .chr(224).chr(192).chr(128).chr(0)
            .chr(255).chr(255).chr(255).chr(255);
        $this->obj = new \Com\Tecnick\File\Byte($str);
    }

    /**
     * @dataProvider getByteDataProvider
     */
    public function testGetByte($offset, $expected)
    {
        $res = $this->obj->getByte($offset);
        $this->assertEquals($expected, $res);
    }

    public function getByteDataProvider()
    {
        return array(
            array(0, 0),
            array(1, 0),
            array(2, 0),
            array(3, 0),
            array(4, 1),
            array(5, 3),
            array(6, 7),
            array(7, 15),
            array(8, 31),
            array(9, 63),
            array(10, 127),
            array(11, 255),
            array(12, 254),
            array(13, 252),
            array(14, 248),
            array(15, 240),
            array(16, 224),
            array(17, 192),
            array(18, 128),
            array(19, 0),
            array(20, 255),
            array(21, 255),
            array(22, 255),
            array(23, 255)
        );
    }

    /**
     * @dataProvider getULongDataProvider
     */
    public function testGetULong($offset, $expected)
    {
        $res = $this->obj->getULong($offset);
        $this->assertEquals($expected, $res);
    }

    public function getULongDataProvider()
    {
        return array(
            array(0, 0),
            array(1, 1),
            array(2, 259),
            array(3, 66311),
            array(4, 16975631),
            array(5, 50794271),
            array(6, 118431551),
            array(7, 253706111),
            array(8, 524255231),
            array(9, 1065353214),
            array(10, 2147483388),
            array(11, 4294900984),
            array(12, 4277991664),
            array(13, 4244173024),
            array(14, 4176535744),
            array(15, 4041261184),
            array(16, 3770712064),
            array(17, 3229614335),
            array(18, 2147549183),
            array(19, 16777215),
            array(20, 4294967295)
        );
    }

    /**
     * @dataProvider getUShortDataProvider
     */
    public function testGetUShort($offset, $expected)
    {
        $res = $this->obj->getUShort($offset);
        $this->assertEquals($expected, $res);
    }

    /**
     * @dataProvider getUShortDataProvider
     */
    public function testGetUFWord($offset, $expected)
    {
        $res = $this->obj->getUFWord($offset);
        $this->assertEquals($expected, $res);
    }

    public function getUShortDataProvider()
    {
        return array(
            array(0, 0),
            array(1, 0),
            array(2, 0),
            array(3, 1),
            array(4, 259),
            array(5, 775),
            array(6, 1807),
            array(7, 3871),
            array(8, 7999),
            array(9, 16255),
            array(10, 32767),
            array(11, 65534),
            array(12, 65276),
            array(13, 64760),
            array(14, 63728),
            array(15, 61664),
            array(16, 57536),
            array(17, 49280),
            array(18, 32768),
            array(19, 255),
            array(20, 65535),
            array(21, 65535),
            array(22, 65535)
        );
    }

    /**
     * @dataProvider getShortDataProvider
     */
    public function testGetShort($offset, $expected)
    {
        $res = $this->obj->getShort($offset);
        $this->assertEquals($expected, $res);
    }

    public function getShortDataProvider()
    {
        return array(
            array(0, 0),
            array(1, 0),
            array(2, 0),
            array(3, 256),
            array(4, 769),
            array(5, 1795),
            array(6, 3847),
            array(7, 7951),
            array(8, 16159),
            array(9, 32575),
            array(10, -129),
            array(11, -257),
            array(12, -770),
            array(13, -1796),
            array(14, -3848),
            array(15, -7952),
            array(16, -16160),
            array(17, -32576),
            array(18, 128),
            array(19, -256),
            array(20, -1),
            array(21, -1),
            array(22, -1)
        );
    }

    /**
     * @dataProvider getFWordDataProvider
     */
    public function testGetFWord($offset, $expected)
    {
        $res = $this->obj->getFWord($offset);
        $this->assertEquals($expected, $res);
    }

    public function getFWordDataProvider()
    {
        return array(
            array(0, 0),
            array(1, 0),
            array(2, 0),
            array(3, 1),
            array(4, 259),
            array(5, 775),
            array(6, 1807),
            array(7, 3871),
            array(8, 7999),
            array(9, 16255),
            array(10, 32767),
            array(11, -2),
            array(12, -260),
            array(13, -776),
            array(14, -1808),
            array(15, -3872),
            array(16, -8000),
            array(17, -16256),
            array(18, -32768),
            array(19, 255),
            array(20, -1),
            array(21, -1),
            array(22, -1)
        );
    }

    /**
     * @dataProvider getFixedDataProvider
     */
    public function testGetFixed($offset, $expected)
    {
        $res = $this->obj->getFixed($offset);
        $this->assertEquals($expected, $res);
    }

    public function getFixedDataProvider()
    {
        return array(
            array(0, 0),
            array(1, 0.1),
            array(2, 0.259),
            array(3, 1.775),
            array(4, 259.1807),
            array(5, 775.3871),
            array(6, 1807.7999),
            array(7, 3871.16255),
            array(8, 7999.32767),
            array(9, 16255.65534),
            array(10, 32767.65276),
            array(11, -2.64760),
            array(12, -260.63728),
            array(13, -776.61664),
            array(14, -1808.57536),
            array(15, -3872.49280),
            array(16, -8000.32768),
            array(17, -16256.255),
            array(18, -32768.65535),
            array(19, 255.65535),
            array(20, -1.65535)
        );
    }
}
