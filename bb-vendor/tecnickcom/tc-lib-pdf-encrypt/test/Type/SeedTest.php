<?php
/**
 * SeedTest.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfEncrypt
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-encrypt
 *
 * This file is part of tc-lib-pdf-encrypt software library.
 */

namespace Test;

use PHPUnit\Framework\TestCase;

/**
 * Seed Test
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfEncrypt
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-encrypt
 */
class SeedTest extends TestCase
{
    protected $obj;
    
    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test
        $this->obj = new \Com\Tecnick\Pdf\Encrypt\Type\Seed();
    }

    public function testEncrypt()
    {
        $result = $this->obj->encrypt('hello', 'world');
        $this->assertNotEmpty($result);
    }

    public function testEncryptRaw()
    {
        $result = $this->obj->encrypt('hello', 'world', 'raw');
        $this->assertNotEmpty($result);
    }
}
