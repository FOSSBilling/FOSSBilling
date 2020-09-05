<?php
/**
 * AESnopad.php
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

/**
 * Com\Tecnick\Pdf\Encrypt\Type\AESnopad
 *
 * AES no-padding
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfEncrypt
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-encrypt
 */
class AESnopad
{
    /**
     * Block size (IV length):
     * openssl_cipher_iv_length('aes-256-cbc')
     */
    const BLOCKSIZE = 16;

    /**
     * Initialization Vector (16 bytes)
     */
    const IVECT = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";

    /**
     * Encrypt the data
     *
     * @param string $data  Data string to encrypt
     * @param string $key   Encryption key
     * @param string $ivect Initialization vector
     * @param string $mode  Cipher
     *
     * @return string Encrypted data string.
     */
    public function encrypt($data, $key, $ivect = self::IVECT, $mode = 'aes-256-cbc')
    {
        return substr(
            openssl_encrypt(
                $this->pad($data, self::BLOCKSIZE),
                $mode,
                $this->pad($key, (2 * self::BLOCKSIZE)),
                OPENSSL_RAW_DATA,
                $ivect
            ),
            0,
            -16
        );
    }

    /**
     * Pad the input string to the specified length
     * (RFC 2898, PKCS #5: Password-Based Cryptography Specification Version 2.0)
     *
     * @param string $data   Data to pad
     * @param int    $length Padding length
     * @param string $ivect Initialization vector
     *
     * @return string
     */
    protected function pad($data, $length)
    {
        $padding = ($length - (strlen($data) % $length));
        return substr($data.str_repeat("\x00", $padding), 0, $length);
    }
}
