<?php
/**
 * Encrypt.php
 *
 * @since       2008-01-02
 * @category    Library
 * @package     PdfEncrypt
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-encrypt
 *
 * This file is part of tc-lib-pdf-encrypt software library.
 */

namespace Com\Tecnick\Pdf\Encrypt;

use \Com\Tecnick\Pdf\Encrypt\Exception as EncException;

/**
 * Com\Tecnick\Pdf\Encrypt\Encrypt
 *
 * PHP class for encrypting data for PDF documents
 *
 * @since       2008-01-02
 * @category    Library
 * @package     PdfEncrypt
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-encrypt
 */
class Encrypt extends \Com\Tecnick\Pdf\Encrypt\Compute
{
    /**
     * Encryption data
     *
     * @var array
     */
    protected $encryptdata = array('encrypted' => false, 'mode' => false);

    /**
     * Set PDF document protection (permission settings)
     *
     * NOTES: The protection against modification is for people who have the full Acrobat product.
     *        If you don't set any password, the document will open as usual.
     *        If you set a user password, the PDF viewer will ask for it before displaying the document.
     *        The master password, if different from the user one, can be used to get full access.
     *        Protecting a document requires to encrypt it, which requires long processign time and may cause timeouts.
     *
     * @param bool   $enabled     False if the encryption is disabled (i.e. the document is in PDF/A mode)
     * @param string $file_id     File ID
     * @param int    $mode        Encryption strength: 0 = RC4 40; 1 = RC4 128; 2 = AES 128; 3 = AES 256
     * @param array  $permissions The set of permissions (specify the ones you want to block):
     *                      'owner' // When set permits change of encryption and enables all other permissions.
     *                              // (inverted logic: cleared by default).
     *                      'print' // Print the document.
     *                     'modify' // Modify the contents of the document by operations other than those controlled
     *                              // by 'fill-forms', 'extract' and 'assemble'.
     *                       'copy' // Copy or otherwise extract text and graphics from the document.
     *                'annot-forms' // Add or modify text annotations, fill in interactive form fields, and,
     *                              // if 'modify' is also set, create or modify interactive form fields
     *                              // (including signature fields).
     *                 'fill-forms' // Fill in existing interactive form fields (including signature fields),
     *                              // even if 'annot-forms' is not specified.
     *                    'extract' // Extract text and graphics (in support of accessibility to users with
     *                              // disabilities or for other purposes).
     *                   'assemble' // Assemble the document (insert, rotate, or delete pages and create bookmarks
     *                              // or thumbnail images), even if 'modify' is not set.
     *                 'print-high' // Print the document to a representation from which a faithful digital copy of the
     *                              // PDF content could be generated. When this is not set, printing is limited to a
     *                              // low-level representation of the appearance, possibly of degraded quality.
     *
     * @param string $user_pass   User password. Empty by default.
     * @param string $owner_pass  Owner password. If not specified, a random value is used.
     * @param string $pubkeys     Array of recipients containing public-key certificates ('c') and permissions ('p').
     *                            For example:
     *                            array(array('c' => 'file://../examples/data/cert/test.crt', 'p' => array('print')))
     *                            To create self-signed certificate:
     *                            openssl req -x509 -nodes -days 365000 -newkey rsa:1024 -keyout key.pem -out cert.pem
     *                            To export crt to p12: openssl pkcs12 -export -in cert.pem -out cert.p12
     *                            To convert pfx certificate to pem: openssl pkcs12 -in cert.pfx -out cert.pem -nodes
     */
    public function __construct(
        $enabled = false,
        $file_id = '',
        $mode = 0,
        $permissions = array(
            'print',
            'modify',
            'copy',
            'annot-forms',
            'fill-forms',
            'extract',
            'assemble',
            'print-high'
        ),
        $user_pass = '',
        $owner_pass = null,
        $pubkeys = null
    ) {
        if (!$enabled) {
            return;
        }
        $this->encryptdata['protection'] = $this->getUserPermissionCode($permissions, $mode);

        if (is_array($pubkeys)) {
            // public-key mode
            $this->encryptdata['pubkeys'] = $pubkeys;
            if ($mode == 0) {
                // public-Key Security requires at least 128 bit
                $mode = 1;
            }
            // Set Public-Key filter (available are: Entrust.PPKEF, Adobe.PPKLite, Adobe.PubSec)
            $this->encryptdata['pubkey'] = true;
            $this->encryptdata['Filter'] = 'Adobe.PubSec';
            $this->encryptdata['StmF']   = 'DefaultCryptFilter';
            $this->encryptdata['StrF']   = 'DefaultCryptFilter';
        } else {
            // standard mode (password mode)
            $this->encryptdata['pubkey'] = false;
            $this->encryptdata['Filter'] = 'Standard';
            $this->encryptdata['StmF']   = 'StdCF';
            $this->encryptdata['StrF']   = 'StdCF';
        }

        if ($owner_pass === null) {
            $owner_pass = md5($this->encrypt('seed'));
        }

        $this->encryptdata['user_password']  = $user_pass;
        $this->encryptdata['owner_password'] = $owner_pass;

        if (($mode < 0) || ($mode > 3)) {
            throw new EncException('unknown encryption mode: '.$this->encryptdata['mode']);
        }
        $this->encryptdata['mode'] = $mode;

        $this->encryptdata = array_merge($this->encryptdata, self::$encrypt_settings[$mode]);
        if (!$this->encryptdata['pubkey']) {
            unset($this->encryptdata['SubFilter'], $this->encryptdata['Recipients']);
        }
        $this->encryptdata['encrypted'] = true;
        $this->encryptdata['fileid'] = $this->convertHexStringToString($file_id);
        $this->generateEncryptionKey();
    }

    /**
     * Get the encryption data array.
     *
     * @return array
     */
    public function getEncryptionData()
    {
        return $this->encryptdata;
    }

    /**
     * Encrypt data using the specified encrypt type.
     *
     * @param string $type   Encrypt type.
     * @param string $data   Data string to encrypt.
     * @param string $key    Encryption key.
     * @param int    $objnum Object number.
     *
     * @return string Encrypted data string.
     */
    public function encrypt($type, $data = '', $key = null, $objnum = null)
    {
        if (empty($this->encryptdata['encrypted']) || ($type === false)) {
            return $data;
        }

        if (!isset(self::$encmap[$type])) {
            throw new EncException('unknown encryption type: '.$type);
        }

        if (($key === null) && ($type == $this->encryptdata['mode'])) {
            $key = '';
            if ($this->encryptdata['mode'] < 3) {
                $key = $this->getObjectKey($objnum);
            } elseif ($this->encryptdata['mode'] == 3) {
                $key = $this->encryptdata['key'];
            }
        }

        $class = '\\Com\\Tecnick\\Pdf\\Encrypt\\Type\\'.self::$encmap[$type];
        $obj = new $class;
        return $obj->encrypt($data, $key);
    }

    /**
     * Compute encryption key depending on object number where the encrypted data is stored.
     * This is used for all strings and streams without crypt filter specifier.
     *
     * @param int $objnum Object number.
     *
     * @return int
     */
    public function getObjectKey($objnum)
    {
        $objkey = $this->encryptdata['key'].pack('VXxx', $objnum);
        if ($this->encryptdata['mode'] == 2) {
            // AES-128 padding
            $objkey .= "\x73\x41\x6C\x54"; // sAlT
        }
        $objkey = substr($this->encrypt('MD5-16', $objkey, 'H*'), 0, (($this->encryptdata['Length'] / 8) + 5));
        $objkey = substr($objkey, 0, 16);
        return $objkey;
    }

    /**
     * Convert encryption P value to a string of bytes, low-order byte first.
     *
     * @param string $protection 32bit encryption permission value (P value).
     *
     * @return string
     */
    public function getEncPermissionsString($protection)
    {
        $binprot = sprintf('%032b', $protection);
        return chr(bindec(substr($binprot, 24, 8)))
            .chr(bindec(substr($binprot, 16, 8)))
            .chr(bindec(substr($binprot, 8, 8)))
            .chr(bindec(substr($binprot, 0, 8)));
    }

    /**
     * Return the permission code used on encryption (P value).
     *
     * @param array $permissions The set of permissions (specify the ones you want to block).
     * @param $mode (int) encryption strength: 0 = RC4 40 bit; 1 = RC4 128 bit; 2 = AES 128 bit; 3 = AES 256 bit.
     *
     * @return int
     */
    public function getUserPermissionCode($permissions, $mode = 0)
    {
        $protection = 2147422012; // 32 bit: (01111111 11111111 00001111 00111100)
        foreach ($permissions as $permission) {
            if (isset(self::$permbits[$permission])) {
                if (($mode > 0) || (self::$permbits[$permission] <= 32)) {
                    // set only valid permissions
                    if (self::$permbits[$permission] == 2) {
                        // the logic for bit 2 is inverted (cleared by default)
                        $protection += self::$permbits[$permission];
                    } else {
                        $protection -= self::$permbits[$permission];
                    }
                }
            }
        }
        return $protection;
    }

    /**
     * Convert hexadecimal string to string.
     *
     * @param string $bstr Byte-string to convert.
     *
     * @return String
     */
    public function convertHexStringToString($bstr)
    {
        $str = ''; // string to be returned
        $bslength = strlen($bstr);
        if (($bslength % 2) != 0) {
            // padding
            $bstr .= '0';
            ++$bslength;
        }
        for ($idx = 0; $idx < $bslength; $idx += 2) {
            $str .= chr(hexdec($bstr[$idx].$bstr[($idx + 1)]));
        }
        return $str;
    }

    /**
     * Convert string to hexadecimal string (byte string).
     *
     * @param string $str String to convert.
     *
     * @return string
     */
    public function convertStringToHexString($str)
    {
        $bstr = '';
        $chars = preg_split('//', $str, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($chars as $chr) {
            $bstr .= sprintf('%02s', dechex(ord($chr)));
        }
        return $bstr;
    }

    /**
     * Encode a name object.
     *
     * @param string $name Name object to encode.
     *
     * @return string Encoded name object.
     */
    public function encodeNameObject($name)
    {
        $escname = '';
        $length = strlen($name);
        for ($idx = 0; $idx < $length; ++$idx) {
            $chr = $name[$idx];
            if (preg_match('/[0-9a-zA-Z#_=-]/', $chr) == 1) {
                $escname .= $chr;
            } else {
                $escname .= sprintf('#%02X', ord($chr));
            }
        }
        return $escname;
    }

    /**
     * Escape a string: add "\" before "\", "(" and ")".
     *
     * @param string $str String to escape.
     *
     * @return string
     */
    public function escapeString($str)
    {
        return strtr($str, array(')' => '\\)', '(' => '\\(', '\\' => '\\\\', chr(13) => '\r'));
    }

    /**
     * Encrypt a string.
     *
     * @param string $str    String to encrypt.
     * @param int    $objnum Object ID.
     *
     * @return string
     */
    public function encryptString($str, $objnum = null)
    {
        return $this->encrypt($this->encryptdata['mode'], $str, null, $objnum);
    }

    /**
     * Format a data string for meta information.
     *
     * @param string $str    Data string to escape.
     * @param int    $objnum Object ID.
     *
     * @return string
     */
    public function escapeDataString($str, $objnum = null)
    {
        return '('.$this->escapeString($this->encryptString($str, $objnum)).')';
    }

    /**
     * Returns a formatted date-time.
     *
     * @param int $time   UTC time measured in the number of seconds since the Unix Epoch (January 1 1970 00:00:00 GMT).
     * @param int $objnum Object ID.
     *
     * @return string escaped date string.
     */
    public function getFormattedDate($time = null, $objnum = null)
    {
        if ($time === null) {
            $time = time(); // get current UTC time
        }
        return $this->escapeDataString('D:'.substr_replace(date('YmdHisO', intval($time)), '\'', -2, 0).'\'', $objnum);
    }
}
