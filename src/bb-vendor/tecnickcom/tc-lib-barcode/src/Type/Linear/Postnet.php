<?php
/**
 * Postnet.php
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
 * Com\Tecnick\Barcode\Type\Linear\Postnet;
 *
 * Postnet Barcode type class
 * POSTNET
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class Postnet extends \Com\Tecnick\Barcode\Type\Linear
{
    /**
     * Barcode format
     *
     * @var string
     */
    protected $format = 'POSTNET';

    /**
     * Map characters to barcodes
     *
     * @var array
     */
    protected $chbar = array(
        '0' => '22111',
        '1' => '11122',
        '2' => '11212',
        '3' => '11221',
        '4' => '12112',
        '5' => '12121',
        '6' => '12211',
        '7' => '21112',
        '8' => '21121',
        '9' => '21211'
    );

    /**
     * Calculate the checksum.
     *
     * @param $code (string) code to represent.
     *
     * @return char checksum.
     */
    protected function getChecksum($code)
    {
        $sum = 0;
        $len = strlen($code);
        for ($pos = 0; $pos < $len; ++$pos) {
            $sum += intval($code[$pos]);
        }
        $check = ($sum % 10);
        if ($check > 0) {
            $check = (10 - $check);
        }
        return $check;
    }

    /**
     * Format code
     */
    protected function formatCode()
    {
        $code = preg_replace('/[-\s]+/', '', $this->code);
        $this->extcode = $code.$this->getChecksum($code);
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
        $this->ncols = 0;
        $this->nrows = 2;
        $this->bars = array();
        $this->formatCode();
        $clen = strlen($this->extcode);
        // start bar
        $this->bars[] = array($this->ncols, 0, 1, 2);
        $this->ncols += 2;
        for ($chr = 0; $chr < $clen; ++$chr) {
            $char = $this->extcode[$chr];
            if (!isset($this->chbar[$char])) {
                throw new BarcodeException('Invalid character: chr('.ord($char).')');
            }
            for ($pos = 0; $pos < 5; ++$pos) {
                $bar_height = intval($this->chbar[$char][$pos]);
                $this->bars[] = array($this->ncols, floor(1 / $bar_height), 1, $bar_height);
                $this->ncols += 2;
            }
        }
        // end bar
        $this->bars[] = array($this->ncols, 0, 1, 2);
        ++$this->ncols;
    }
}
