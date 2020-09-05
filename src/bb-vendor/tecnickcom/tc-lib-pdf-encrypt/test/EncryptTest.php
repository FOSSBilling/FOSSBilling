<?php
/**
 * EncryptTest.php
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
 * Encrypt Test
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfEncrypt
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-encrypt
 */
class EncryptTest extends TestCase
{
    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test
    }

    /**
     * @expectedException \Com\Tecnick\Pdf\Encrypt\Exception
     */
    public function testEncryptException()
    {
        $enc = new \Com\Tecnick\Pdf\Encrypt\Encrypt(true, md5('file_id'));
        $enc->encrypt('WRONG');
    }

    /**
     * @expectedException \Com\Tecnick\Pdf\Encrypt\Exception
     */
    public function testEncryptModeException()
    {
        new \Com\Tecnick\Pdf\Encrypt\Encrypt(true, md5('file_id'), 4);
    }

    public function testEncryptThree()
    {
        $enc = new \Com\Tecnick\Pdf\Encrypt\Encrypt(
            true,
            md5('file_id'),
            3,
            array('print'),
            'alpha',
            'beta'
        );
        $result = $enc->encrypt(3, 'alpha');
        $this->assertEquals(32, strlen($result));
    }

    public function testEncryptPubThree()
    {
        $pubkeys = array(array('c' => __DIR__.'/data/cert.pem', 'p' => array('print')));
        $enc = new \Com\Tecnick\Pdf\Encrypt\Encrypt(
            true,
            md5('file_id'),
            3,
            array('print'),
            'alpha',
            'beta',
            $pubkeys
        );
        $result = $enc->encrypt(3, 'alpha');
        $this->assertEquals(32, strlen($result));
    }

    public function testEncryptPubNoP()
    {
        $pubkeys = array(array('c' => __DIR__.'/data/cert.pem'));
        $enc = new \Com\Tecnick\Pdf\Encrypt\Encrypt(
            true,
            md5('file_id'),
            3,
            array('print'),
            'alpha',
            'beta',
            $pubkeys
        );
        $result = $enc->encrypt(3, 'alpha');
        $this->assertEquals(32, strlen($result));
    }


    /**
     * @expectedException \Com\Tecnick\Pdf\Encrypt\Exception
     */
    public function testEncryptPubException()
    {
        new \Com\Tecnick\Pdf\Encrypt\Encrypt(
            true,
            md5('file_id'),
            3,
            array('print'),
            'alpha',
            'beta',
            array(array('c' => __FILE__))
        );
    }

    public function testEncryptModZeroPub()
    {
        $pubkeys = array(array('c' => __DIR__.'/data/cert.pem', 'p' => array('print')));
        $enc = new \Com\Tecnick\Pdf\Encrypt\Encrypt(
            true,
            md5('file_id'),
            0,
            array('print'),
            'alpha',
            'beta',
            $pubkeys
        );
        $result = $enc->encrypt(1, 'alpha');
        $this->assertEquals(5, strlen($result));
    }

    public function testGetEncryptionData()
    {
        $permissions = array('print');
        $enc = new \Com\Tecnick\Pdf\Encrypt\Encrypt(true, md5('file_id'), 0, $permissions, 'alpha', 'beta');
        $result = $enc->getEncryptionData();
        $this->assertEquals('68bdb9944269edc95d1787eb6337de7a', md5(serialize($result)));
        $this->assertEquals(2147422008, $result['protection']);
        $this->assertEquals(1, $result['V']);
        $this->assertEquals(40, $result['Length']);
        $this->assertEquals('V2', $result['CF']['CFM']);
    }

    public function testGetObjectKey()
    {
        $permissions = array(
            'print',
            'modify',
            'copy',
            'annot-forms',
            'fill-forms',
            'extract',
            'assemble',
            'print-high'
        );
        
        $enc = new \Com\Tecnick\Pdf\Encrypt\Encrypt(true, md5('file_id'), 2, $permissions, 'alpha', 'beta');
        $result = $enc->getObjectKey(123);
        $this->assertEquals('93879594941619c98047c404192b977d', bin2hex($result));
    }

    public function testGetUserPermissionCode()
    {
        $permissions = array(
            'owner',
            'print',
            'modify',
            'copy',
            'annot-forms',
            'fill-forms',
            'extract',
            'assemble',
            'print-high'
        );
        
        $enc = new \Com\Tecnick\Pdf\Encrypt\Encrypt();
        $result = $enc->getUserPermissionCode($permissions, 0);
        $this->assertEquals(2147421954, $result);
    }

    public function testConvertHexStringToString()
    {
        $enc = new \Com\Tecnick\Pdf\Encrypt\Encrypt();
        
        $result = $enc->convertHexStringToString('');
        $this->assertEquals('', $result);
        
        $result = $enc->convertHexStringToString('68656c6c6f20776f726c64');
        $this->assertEquals('hello world', $result);
        
        $result = $enc->convertHexStringToString('68656c6c6f20776f726c642');
        $this->assertEquals('hello world ', $result);
    }

    public function testConvertStringToHexString()
    {
        $enc = new \Com\Tecnick\Pdf\Encrypt\Encrypt();
        
        $result = $enc->convertStringToHexString('');
        $this->assertEquals('', $result);
        
        $result = $enc->convertStringToHexString('hello world');
        $this->assertEquals('68656c6c6f20776f726c64', $result);
    }

    public function testEncodeNameObject()
    {
        $enc = new \Com\Tecnick\Pdf\Encrypt\Encrypt();
        
        $result = $enc->encodeNameObject('');
        $this->assertEquals('', $result);
        
        $result = $enc->encodeNameObject('059akzAKZ#_=-');
        $this->assertEquals('059akzAKZ#_=-', $result);
        
        $result = $enc->encodeNameObject('059[]{}+~*akzAKZ#_=-');
        $this->assertEquals('059#5B#5D#7B#7D#2B#7E#2AakzAKZ#_=-', $result);
    }

    public function testEscapeString()
    {
        $enc = new \Com\Tecnick\Pdf\Encrypt\Encrypt();
        
        $result = $enc->escapeString('');
        $this->assertEquals('', $result);
        
        $result = $enc->escapeString('hello world');
        $this->assertEquals('hello world', $result);

        $result = $enc->escapeString('(hello world) slash \\'.chr(13));
        $this->assertEquals('\\(hello world\\) slash \\\\\r', $result);
    }

    public function testEncryptStringDisabled()
    {
        $enc = new \Com\Tecnick\Pdf\Encrypt\Encrypt();
        
        $result = $enc->encryptString('');
        $this->assertEquals('', $result);
        
        $result = $enc->encryptString('hello world');
        $this->assertEquals('hello world', $result);

        $result = $enc->encryptString('(hello world) slash \\'.chr(13).chr(250));
        $this->assertEquals('(hello world) slash \\'.chr(13).chr(250), $result);
    }

    public function testEncryptStringEnabled()
    {
        $permissions = array(
            'print',
            'modify',
            'copy',
            'annot-forms',
            'fill-forms',
            'extract',
            'assemble',
            'print-high'
        );
        
        $enc = new \Com\Tecnick\Pdf\Encrypt\Encrypt(true, md5('file_id'), 0, $permissions, 'alpha');
        $result = $enc->encryptString('(hello world) slash \\'.chr(13));
        $this->assertEquals('728cc693be1e4c1fb6b7e7b2a34644ad', md5($result));

        $enc = new \Com\Tecnick\Pdf\Encrypt\Encrypt(true, md5('file_id'), 1, $permissions, 'alpha', 'beta');
        $result = $enc->encryptString('(hello world) slash \\'.chr(13));
        $this->assertEquals('258ad774ddeec21b3b439a720df18e0d', md5($result));
    }

    public function testEscapeDataStringDisabled()
    {
        $enc = new \Com\Tecnick\Pdf\Encrypt\Encrypt();
        
        $result = $enc->escapeDataString('');
        $this->assertEquals('()', $result);
        
        $result = $enc->escapeDataString('hello world');
        $this->assertEquals('(hello world)', $result);

        $result = $enc->escapeDataString('(hello world) slash \\'.chr(13));
        $this->assertEquals('(\\(hello world\\) slash \\\\\r)', $result);
    }

    public function testEscapeDataStringEnabled()
    {
        $permissions = array(
            'print',
            'modify',
            'copy',
            'annot-forms',
            'fill-forms',
            'extract',
            'assemble',
            'print-high'
        );
        
        $enc = new \Com\Tecnick\Pdf\Encrypt\Encrypt(true, md5('file_id'), 0, $permissions, 'alpha');
        $result = $enc->escapeDataString('(hello world) slash \\'.chr(13));
        $this->assertEquals('24f60765c1c07a44fc3c9b44d2f55dbc', md5($result));

        $enc = new \Com\Tecnick\Pdf\Encrypt\Encrypt(true, md5('file_id'), 1, $permissions, 'alpha', 'beta');
        $result = $enc->escapeDataString('(hello world) slash \\'.chr(13));
        $this->assertEquals('ebc28272f4aff661fa0b7764d791fb79', md5($result));
    }

    public function testGetFormattedDate()
    {
        $permissions = array(
            'print',
            'modify',
            'copy',
            'annot-forms',
            'fill-forms',
            'extract',
            'assemble',
            'print-high'
        );
        
        $enc = new \Com\Tecnick\Pdf\Encrypt\Encrypt(false);
        $result = $enc->getFormattedDate();
        $this->assertEquals('(D:', substr($result, 0, 3));
        $this->assertEquals('+00\'00\')', substr($result, -8));
        
        $enc = new \Com\Tecnick\Pdf\Encrypt\Encrypt(true, md5('file_id'), 0, $permissions, 'alpha');
        $result = $enc->getFormattedDate();
        $this->assertNotEmpty($result);
    }
}
