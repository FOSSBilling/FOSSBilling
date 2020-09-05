<?php
/**
 * AESThirtytwo.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfEncrypt
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2017 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-encrypt
 *
 * This file is part of tc-lib-pdf-encrypt software library.
 */

namespace Com\Tecnick\Pdf\Encrypt\Type;

use \Com\Tecnick\Pdf\Encrypt\Exception as EncException;
use \Com\Tecnick\Pdf\Encrypt\Type\AES;

/**
 * Com\Tecnick\Pdf\Encrypt\Type\AESThirtytwo
 *
 * AESThirtytwo
 * 32 bytes = 256 bit
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfEncrypt
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2017 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-encrypt
 */
class AESThirtytwo
{
    /**
     * Encrypt the data using OpenSSL
     *
     * @param string $data  Data string to encrypt
     * @param string $key   Encryption key
     *
     * @return string Encrypted data string.
     */
    public function encrypt($data, $key)
    {
        $obj = new AES();
        return $obj->encrypt($data, $key, 'aes-256-cbc');
    }
}
