<?php
/**
 * RCFour.php
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

/**
 * Com\Tecnick\Pdf\Encrypt\Type\RCFour
 *
 * RC4 is the standard encryption algorithm used in PDF format
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfEncrypt
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2017 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-encrypt
 */
class RCFour
{
    /**
     * Encrypt the data
     *
     * @param string $data Data string to encrypt
     * @param string $key  Encryption key
     * @param string $mode Cipher
     *
     * @return string Encrypted data string.
     */
    public function encrypt($data, $key, $mode = '')
    {
        if (empty($mode)) {
            if (strlen($key) > 5) {
                $mode = 'RC4';
            } else {
                $mode = 'RC4-40';
            }
        } elseif (!in_array($mode, array('RC4', 'RC4-40'))) {
            throw new EncException('unknown chipher: '.$mode);
        }
        return openssl_encrypt($data, $mode, $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
    }
}
