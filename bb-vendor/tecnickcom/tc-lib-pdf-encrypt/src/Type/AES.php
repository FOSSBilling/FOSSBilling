<?php
/**
 * AES.php
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

namespace Com\Tecnick\Pdf\Encrypt\Type;

use \Com\Tecnick\Pdf\Encrypt\Exception as EncException;
use \Com\Tecnick\Pdf\Encrypt\Type\AESnopad;

/**
 * Com\Tecnick\Pdf\Encrypt\Type\AES
 *
 * AES
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfEncrypt
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-encrypt
 */
class AES
{
    /**
     * Encrypt the data using OpenSSL
     *
     * @param string $data  Data string to encrypt
     * @param string $key   Encryption key
     * @param string $mode  Cipher
     *
     * @return string Encrypted data string.
     */
    public function encrypt($data, $key, $mode = '')
    {
        if (empty($mode)) {
            if (strlen($key) > 16) {
                $mode = 'aes-256-cbc';
            } else {
                $mode = 'aes-128-cbc';
            }
        } elseif (!in_array($mode, array('aes-128-cbc', 'aes-256-cbc'))) {
            throw new EncException('unknown chipher: '.$mode);
        }

        $ivect = openssl_random_pseudo_bytes(openssl_cipher_iv_length($mode));
        $obj = new AESnopad();
        return $ivect.$obj->encrypt($data, $key, $ivect, $mode);
    }
}
