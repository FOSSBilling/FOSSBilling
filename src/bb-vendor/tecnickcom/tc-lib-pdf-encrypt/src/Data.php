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

/**
 * Com\Tecnick\Pdf\Encrypt\Data
 *
 * Ecrypt common data
 *
 * @since       2008-01-02
 * @category    Library
 * @package     PdfEncrypt
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-encrypt
 */
abstract class Data extends \Com\Tecnick\Pdf\Encrypt\Output
{
    //@codingStandardsIgnoreStart
    /**
     * Encryption padding string.
     *
     * @var string
     */
    protected static $encpad = "\x28\xBF\x4E\x5E\x4E\x75\x8A\x41\x64\x00\x4E\x56\xFF\xFA\x01\x08\x2E\x2E\x00\xB6\xD0\x68\x3E\x80\x2F\x0C\xA9\xFE\x64\x53\x69\x7A";
    //@codingStandardsIgnoreEnd
    
    /**
     * Map permission modes and bits
     *
     * @var array
     */
    protected static $permbits = array(
        'owner'       =>    2,  // bit 2 -- inverted logic: cleared by default
                                // When set permits change of encryption and enables all other permissions.
        'print'       =>    4,  // bit 3
                                // Print the document.
        'modify'      =>    8,  // bit 4
                                // Modify the contents of the document by operations other than those controlled
                                // by 'fill-forms', 'extract' and 'assemble'.
        'copy'        =>   16,  // bit 5
                                // Copy or otherwise extract text and graphics from the document.
        'annot-forms' =>   32,  // bit 6
                                // Add or modify text annotations, fill in interactive form fields, and,
                                // if 'modify' is also set, create or modify interactive form fields
                                // (including signature fields).
        'fill-forms'  =>  256,  // bit 9
                                // Fill in existing interactive form fields (including signature fields),
                                // even if 'annot-forms' is not specified.
        'extract'     =>  512,  // bit 10
                                // Extract text and graphics (in support of accessibility to users with
                                // disabilities or for other purposes).
        'assemble'    => 1024,  // bit 11
                                // Assemble the document (insert, rotate, or delete pages and create bookmarks
                                // or thumbnail images), even if 'modify' is not set.
        'print-high'  => 2048,  // bit 12
                                // Print the document to a representation from which a faithful digital copy of the
                                // PDF content could be generated. When this is not set, printing is limited to a
                                // low-level representation of the appearance, possibly of degraded quality.
    );
    
    /**
     * Encryption settings
     *
     * @var array
     */
    protected static $encrypt_settings = array(
        0 => array(      // RC4 40 bit
            'V'          => 1,
            'Length'     => 40,
            'CF'         => array(
                'CFM'       => 'V2',
                'AuthEvent' => 'DocOpen',
            ),
        ),
        1 => array(     // RC4 128 bit
            'V'          => 2,
            'Length'     => 128,
            'CF'         => array(
                'CFM'       => 'V2',
                'AuthEvent' => 'DocOpen',
            ),
            'SubFilter'  => 'adbe.pkcs7.s4',
            'Recipients' => array(),
        ),
        2 => array(     // AES 128 bit
            'V'          => 4,
            'Length'     => 128,
            'CF'         => array(
                'CFM'       => 'AESV2',
                'Length'    => 128,
                'AuthEvent' => 'DocOpen',
            ),
            'SubFilter'  => 'adbe.pkcs7.s5',
            'Recipients' => array(),
        ),
        3 => array(      // AES 256 bit
            'V'          => 5,
            'Length'     => 256,
            'CF'         => array(
                'CFM'       => 'AESV3',
                'Length'    => 256,
                'AuthEvent' => 'DocOpen',
            ),
            'SubFilter'  => 'adbe.pkcs7.s5',
            'Recipients' => array(),
        ),
    );

    /**
     * Define a list of available encrypt encoders.
     *
     * @var array
     */
    protected static $encmap = array(
        0          => 'RCFourFive',    // RC4-40
        1          => 'RCFourSixteen', // RC4-128
        2          => 'AESSixteen',    // AES-128
        3          => 'AESThirtytwo',  // AES-256
        'RC4'      => 'RCFour',        // RC4-40
        'RC4-40'   => 'RCFourFive',    // RC4-40
        'RC4-128'  => 'RCFourSixteen', // RC4-128
        'AES'      => 'AES',           // AES-256
        'AES-128'  => 'AESSixteen',    // AES-128
        'AES-256'  => 'AESThirtytwo',  // AES-256
        'AESnopad' => 'AESnopad',      // AES - no padding
        'MD5-16'   => 'MDFiveSixteen', // MD5-16
        'seed'     => 'Seed',          // Random seed string
    );
}
