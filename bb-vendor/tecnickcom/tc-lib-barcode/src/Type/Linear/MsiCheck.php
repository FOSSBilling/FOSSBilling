<?php
/**
 * MsiCheck.php
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2020 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 *
 * This file is part of tc-lib-barcode software library.
 */

namespace Com\Tecnick\Barcode\Type\Linear;

use \Com\Tecnick\Barcode\Exception as BarcodeException;

/**
 * Com\Tecnick\Barcode\Type\Linear\MsiCheck;
 *
 * MsiCheck Barcode type class
 * MSI + CHECKSUM (modulo 11)
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2020 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class MsiCheck extends \Com\Tecnick\Barcode\Type\Linear
{
    /**
     * Barcode format
     *
     * @var string
     */
    protected $format = 'MSI+';

    /**
     * Map characters to barcodes
     *
     * @var array
     */
    protected $chbar = array(
        '0' => '100100100100',
        '1' => '100100100110',
        '2' => '100100110100',
        '3' => '100100110110',
        '4' => '100110100100',
        '5' => '100110100110',
        '6' => '100110110100',
        '7' => '100110110110',
        '8' => '110100100100',
        '9' => '110100100110',
        'A' => '110100110100',
        'B' => '110100110110',
        'C' => '110110100100',
        'D' => '110110100110',
        'E' => '110110110100',
        'F' => '110110110110'
    );

    /**
     * Calculate the checksum
     *
     * @param $code (string) code to represent.
     *
     * @return char checksum.
     */
    protected function getChecksum($code)
    {
        $clen = strlen($code);
        $pix = 2;
        $check = 0;
        for ($pos = ($clen - 1); $pos >= 0; --$pos) {
            $hex = $code[$pos];
            if (!ctype_xdigit($hex)) {
                continue;
            }
            $check += (hexdec($hex) * $pix);
            ++$pix;
            if ($pix > 7) {
                $pix = 2;
            }
        }
        $check %= 11;
        if ($check > 0) {
            $check = (11 - $check);
        }
        return $check;
    }

    /**
     * Format code
     */
    protected function formatCode()
    {
        $this->extcode = $this->code.$this->getChecksum($this->code);
    }
    
    /**
     * Get the bars array
     *
     * @return array
     *
     * @throws BarcodeException in case of error
     */
    protected function setBars()
    {
        $this->formatCode();
        $seq = '110'; // left guard
        $clen = strlen($this->extcode);
        for ($pos = 0; $pos < $clen; ++$pos) {
            $digit = $this->extcode[$pos];
            if (!isset($this->chbar[$digit])) {
                throw new BarcodeException('Invalid character: chr('.ord($digit).')');
            }
            $seq .= $this->chbar[$digit];
        }
        $seq .= '1001'; // right guard
        $this->processBinarySequence($seq);
    }
}
