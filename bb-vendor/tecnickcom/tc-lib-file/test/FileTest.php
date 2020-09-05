<?php
/**
 * FileTest.php
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
 * File Color class test
 *
 * @since       2015-07-28
 * @category    Library
 * @package     File
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-file
 */
class FileTest extends TestCase
{
    protected $obj = null;

    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test
        $this->obj = new \Com\Tecnick\File\File();
    }

    public function testFopenLocal()
    {
        $handle = $this->obj->fopenLocal(__FILE__, 'r');
        $this->assertInternalType('resource', $handle);
        fclose($handle);
    }

    /**
     * @expectedException \Com\Tecnick\File\Exception
     */
    public function testFopenLocalNonLocal()
    {
        $this->obj->fopenLocal('http://www.example.com/test.txt', 'r');
    }

    /**
     * @expectedException \Com\Tecnick\File\Exception
     */
    public function testFopenLocalMissing()
    {
        $this->obj->fopenLocal('/missing_error.txt', 'r');
    }

    public function testfReadInt()
    {
        $handle = fopen(__FILE__, 'r');
        $res = $this->obj->fReadInt($handle);
        // '<?ph' = 60 63 112 104 = 00111100 00111111 01110000 01101000 = 1010790504
        $this->assertEquals(1010790504, $res);
        fclose($handle);
    }

    public function testRfRead()
    {
        $handle = fopen(dirname(__DIR__).'/src/File.php', 'rb');
        $res = $this->obj->rfRead($handle, 2);
        $this->assertEquals('<?', $res);
        $res = $this->obj->rfRead($handle, 3);
        $this->assertEquals('php', $res);
        fclose($handle);
    }

    /**
     * @expectedException \Com\Tecnick\File\Exception
     */
    public function testRfReadException()
    {
        $this->obj->rfRead(0, 2);
    }

    /**
     * @dataProvider getAltFilePathsDataProvider
     */
    public function testGetAltFilePaths($file, $expected)
    {
        $_SERVER['DOCUMENT_ROOT'] = '/var/www';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['SCRIPT_URI'] = 'https://localhost/path/example.php';
        $alt = $this->obj->getAltFilePaths($file);
        $this->assertEquals($expected, $alt);
    }

    public function getAltFilePathsDataProvider()
    {
        return array(
            array(
                'http://www.example.com/test.txt',
                array(
                    0 => 'http://www.example.com/test.txt'
                )
            ),
            array(
                'https://localhost/path/test.txt',
                array(
                    0 => 'https://localhost/path/test.txt',
                    3 => '/var/www/path/test.txt'
                )
            ),
            array(
                '//www.example.com/space test.txt',
                array(
                    0 => '//www.example.com/space test.txt',
                    2 => 'https://www.example.com/space%20test.txt'
                )
            ),
            array(
                '/path/test.txt',
                array(
                    0 => '/path/test.txt',
                    1 => '/var/www/path/test.txt',
                    4 => 'https://localhost/path/test.txt'
                )
            ),
            array(
                'https://localhost/path/test.php?a=0&b=1&amp;c=2;&amp;d="a+b%20c"',
                array(
                      0 => 'https://localhost/path/test.php?a=0&b=1&amp;c=2;&amp;d="a+b%20c"',
                      2 => 'https://localhost/path/test.php?a=0&b=1&c=2;&d="a+b%20c"',
                )
            ),
            array(
                'path/test.txt',
                array(
                    0 => 'path/test.txt',
                    4 => 'https://localhost/path/test.txt'
                )
            ),
        );
    }

    /**
     * @expectedException \Com\Tecnick\File\Exception
     */
    public function testFileGetContentsException()
    {
        $this->obj->fileGetContents('missing.txt');
    }

    public function testFileGetContents()
    {
        $res = $this->obj->fileGetContents(__FILE__);
        $this->assertEquals('<?php', substr($res, 0, 5));
    }

    /**
     * @expectedException \Com\Tecnick\File\Exception
     */
    public function testFileGetContentsCurl()
    {
        define('FORCE_CURL', true);
        $this->obj->fileGetContents('http://www.example.com/test.txt');
    }
}
