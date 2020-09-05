<?php
/**
 * EanFive.php
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 *
 * This file is part of tc-lib-barcode software library.
 */

namespace Com\Tecnick\Barcode\Type\Linear;

use \Com\Tecnick\Barcode\Exception as BarcodeException;

/**
 * Com\Tecnick\Barcode\Type\Linear\EanFive;
 *
 * EanFive Barcode type class
 * EAN 5-Digits UPC-Based Extension
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class EanFive extends \Com\Tecnick\Barcode\Type\Linear\EanTwo
{
    /**
     * Barcode format
     *
     * @var string
     */
    protected $format = 'EAN5';

    /**
     * Fixed code length
     *
     * @var int
     */
    protected $code_length = 5;

    /**
     * Map parities
     *
     * @var array
     */
    protected $parities = array(
        '0' => array('B','B','A','A','A'),
        '1' => array('B','A','B','A','A'),
        '2' => array('B','A','A','B','A'),
        '3' => array('B','A','A','A','B'),
        '4' => array('A','B','B','A','A'),
        '5' => array('A','A','B','B','A'),
        '6' => array('A','A','A','B','B'),
        '7' => array('A','B','A','B','A'),
        '8' => array('A','B','A','A','B'),
        '9' => array('A','A','B','A','B')
    );

    /**
     * Calculate checksum
     *
     * @param $code (string) code to represent.
     *
     * @return char checksum.
     */
    protected function getChecksum($code)
    {
        return (((3 * (intval($code[0]) + intval($code[2]) + intval($code[4])))
            + (9 * (intval($code[1]) + intval($code[3])))) % 10);
    }
}
