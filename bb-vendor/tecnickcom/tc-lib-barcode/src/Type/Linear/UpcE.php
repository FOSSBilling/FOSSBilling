<?php
/**
 * UpcE.php
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2019 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 *
 * This file is part of tc-lib-barcode software library.
 */

namespace Com\Tecnick\Barcode\Type\Linear;

use \Com\Tecnick\Barcode\Exception as BarcodeException;

/**
 * Com\Tecnick\Barcode\Type\Linear\UpcE;
 *
 * UpcE Barcode type class
 * UPC-E
 *
 * UPC-E is a variation of UPC-A which allows for a more compact barcode by eliminating "extra" zeros.
 * Since the resulting UPC-E barcode is about half the size as an UPC-A barcode, UPC-E is generally used on products
 * with very small packaging where a full UPC-A barcode couldn't reasonably fit.
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2019 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class UpcE extends \Com\Tecnick\Barcode\Type\Linear\UpcA
{
    /**
     * Barcode format
     *
     * @var string
     */
    protected $format = 'UPCE';

    /**
     * Fixed code length
     *
     * @var int
     */
    protected $code_length = 12;

    /**
     * Map parities
     *
     * @var array
     */
    protected $parities = array(
        0 => array(
            '0' => array('B','B','B','A','A','A'),
            '1' => array('B','B','A','B','A','A'),
            '2' => array('B','B','A','A','B','A'),
            '3' => array('B','B','A','A','A','B'),
            '4' => array('B','A','B','B','A','A'),
            '5' => array('B','A','A','B','B','A'),
            '6' => array('B','A','A','A','B','B'),
            '7' => array('B','A','B','A','B','A'),
            '8' => array('B','A','B','A','A','B'),
            '9' => array('B','A','A','B','A','B')
        ),
        1 => array(
            '0' => array('A','A','A','B','B','B'),
            '1' => array('A','A','B','A','B','B'),
            '2' => array('A','A','B','B','A','B'),
            '3' => array('A','A','B','B','B','A'),
            '4' => array('A','B','A','A','B','B'),
            '5' => array('A','B','B','A','A','B'),
            '6' => array('A','B','B','B','A','A'),
            '7' => array('A','B','A','B','A','B'),
            '8' => array('A','B','A','B','B','A'),
            '9' => array('A','B','B','A','B','A')
        )
    );

    /**
     * Convert UPC-E code to UPC-A
     *
     * @param string $code Code to convert.
     *
     * @return string
     */
    protected function convertUpceToUpca($code)
    {
        if ($code[5] < 3) {
            return '0'.$code[0].$code[1].$code[5].'0000'.$code[2].$code[3].$code[4];
        }
        if ($code[5] == 3) {
            return '0'.$code[0].$code[1].$code[2].'00000'.$code[3].$code[4];
        }
        if ($code[5] == 4) {
            return '0'.$code[0].$code[1].$code[2].$code[3].'00000'.$code[4];
        }
        return '0'.$code[0].$code[1].$code[2].$code[3].$code[4].'0000'.$code[5];
    }

    /**
     * Convert UPC-A code to UPC-E
     *
     * @param string $code Code to convert.
     *
     * @return string
     */
    protected function convertUpcaToUpce($code)
    {
        $tmp = substr($code, 4, 3);
        if (($tmp == '000') || ($tmp == '100') || ($tmp == '200')) {
            // manufacturer code ends in 000, 100, or 200
            return substr($code, 2, 2).substr($code, 9, 3).substr($code, 4, 1);
        }
        $tmp = substr($code, 5, 2);
        if ($tmp == '00') {
            // manufacturer code ends in 00
            return substr($code, 2, 3).substr($code, 10, 2).'3';
        }
        $tmp = substr($code, 6, 1);
        if ($tmp == '0') {
            // manufacturer code ends in 0
            return substr($code, 2, 4).substr($code, 11, 1).'4';
        }
        // manufacturer code does not end in zero
        return substr($code, 2, 5).substr($code, 11, 1);
    }

    /**
     * Format the code
     */
    protected function formatCode()
    {
        $code = $this->code;
        if (strlen($code) == 6) {
            $code = $this->convertUpceToUpca($code);
        }
        $code = str_pad($code, ($this->code_length - 1), '0', STR_PAD_LEFT);
        $code .= $this->getChecksum($code);
        ++$this->code_length;
        $this->extcode = '0'.$code;
    }

    /**
     * Get the bars array
     *
     * @throws BarcodeException in case of error
     */
    protected function setBars()
    {
        $this->formatCode();
        $upce_code = $this->convertUpcaToUpce($this->extcode);
        $seq = '101'; // left guard bar
        $parity = $this->parities[$this->extcode[1]][$this->check];
        for ($pos = 0; $pos < 6; ++$pos) {
            $seq .= $this->chbar[$parity[$pos]][$upce_code[$pos]];
        }
        $seq .= '010101'; // right guard bar
        $this->processBinarySequence($seq);
    }
}
