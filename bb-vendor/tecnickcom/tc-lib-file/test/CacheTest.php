<?php
/**
 * CacheTest.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     File
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-filecache
 *
 * This file is part of tc-lib-pdf-filecache software library.
 */

namespace Test;

use PHPUnit\Framework\TestCase;

/**
 * Unit Test
 *
 * @since       2011-05-23
 * @category    Library
 * @package     File
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-filecache
 */
class CacheTest extends TestCase
{
    protected $obj = null;

    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test

        $this->obj = new \Com\Tecnick\File\Cache('1_2-a+B/c');
    }
    
    public function testAutoPrefix()
    {
        $obj = new \Com\Tecnick\File\Cache();
        $this->assertNotEmpty($obj->getFilePrefix());
    }
    
    public function testGetCachePath()
    {
        $val = $this->obj->getCachePath();
        $this->assertEquals('/', $val[0]);
        $this->assertEquals('/', substr($val, -1));

        $this->obj->setCachePath();
        $this->assertEquals($val, $this->obj->getCachePath());

        $path = '/tmp';
        $this->obj->setCachePath($path);
        $this->assertEquals('/tmp/', $this->obj->getCachePath());
    }
    
    public function testGetFilePrefix()
    {
        $val = $this->obj->getFilePrefix();
        $this->assertEquals('_1_2-a-B_c_', $val);
    }
    
    public function testGetNewFileName()
    {
        $val = $this->obj->getNewFileName('tst', '0123');
        $this->assertRegexp('/_1_2-a-B_c_tst_0123_/', $val);
    }
    
    public function testDelete()
    {
        $idk = 0;
        for ($idx = 1; $idx <=2; ++$idx) {
            for ($idy = 1; $idy <=2; ++$idy) {
                $file[$idk] = $this->obj->getNewFileName($idx, $idy);
                file_put_contents($file[$idk], '');
                $this->assertTrue(file_exists($file[$idk]));
                ++$idk;
            }
        }

        $this->obj->delete('2', '1');
        $this->assertFalse(file_exists($file[2]));

        $this->obj->delete('1');
        $this->assertFalse(file_exists($file[0]));
        $this->assertFalse(file_exists($file[1]));
        $this->assertTrue(file_exists($file[3]));

        $this->obj->delete();
        $this->assertFalse(file_exists($file[3]));
    }
}
