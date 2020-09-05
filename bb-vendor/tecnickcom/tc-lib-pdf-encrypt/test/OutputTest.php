<?php
/**
 * OutputTest.php
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
 * Output Test
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfEncrypt
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-encrypt
 */
class OutputTest extends TestCase
{
    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test
    }

    public function testGetPdfEncryptionObjZero()
    {
        $enc = new \Com\Tecnick\Pdf\Encrypt\Encrypt(true, md5('file_id'), 0, array('print'), 'alpha', 'beta');
        $pon = 122;
        $result = $enc->getPdfEncryptionObj($pon);
        $expected = '3132332030206f626a0a3c3c0a2f46696c746572202f5374616e646172640a2f5620310a2f4c656e6774682034300a2'
        .'f5220320a2f4f20280542fa0e15496869a825cd08c633ac10675c5c02167661241f5369895d768278b1290a2f552028550539dc185'
        .'e79d4c676f803babbdc50acf8a4427d2de5303d59e7c315b30eba290a2f5020323134373432323030380a2f456e63727970744d657'
        .'4616461746120747275650a3e3e0a656e646f626a0a';
        $this->assertEquals($expected, bin2hex($result));
    }

    public function testGetPdfEncryptionObjOne()
    {
        $enc = new \Com\Tecnick\Pdf\Encrypt\Encrypt(true, md5('file_id'), 1, array('print'), 'alpha', 'beta');
        $pon = 122;
        $result = $enc->getPdfEncryptionObj($pon);
        $this->assertTrue(strlen($result) > 150);
    }

    public function testGetPdfEncryptionObjTwo()
    {
        $enc = new \Com\Tecnick\Pdf\Encrypt\Encrypt(true, md5('file_id'), 2, array('print'), 'alpha', 'beta');
        $pon = 122;
        $result = $enc->getPdfEncryptionObj($pon);
        $this->assertTrue(strlen($result) > 200);
    }

    public function testGetPdfEncryptionObjThree()
    {
        $enc = new \Com\Tecnick\Pdf\Encrypt\Encrypt(true, md5('file_id'), 3, array('print'), 'alpha', 'beta');
        $pon = 122;
        $result = $enc->getPdfEncryptionObj($pon);
        $this->assertTrue(strlen($result) > 300);
    }

    public function testGetPdfEncryptionObjThreePub()
    {
        $pubkeys = array(array('c' => __DIR__.'/data/cert.pem', 'p' => array('print')));
        $enc = new \Com\Tecnick\Pdf\Encrypt\Encrypt(true, md5('file_id'), 3, array('print'), 'alpha', 'beta', $pubkeys);
        $pon = 122;
        $result = $enc->getPdfEncryptionObj($pon);
        $this->assertTrue(strlen($result) > 200);
    }

    public function testGetPdfEncryptionObjOnePub()
    {
        $pubkeys = array(array('c' => __DIR__.'/data/cert.pem', 'p' => array('print')));
        $enc = new \Com\Tecnick\Pdf\Encrypt\Encrypt(true, md5('file_id'), 1, array('print'), 'alpha', 'beta', $pubkeys);
        $pon = 122;
        $result = $enc->getPdfEncryptionObj($pon);
        $this->assertTrue(strlen($result) > 100);
    }
}
