<?php
/**
 * EanOneThree.php
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
 * Com\Tecnick\Barcode\Type\Linear\EanOneThree;
 *
 * EanOneThree Barcode type class
 * EAN 13
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class EanOneThree extends \Com\Tecnick\Barcode\Type\Linear
{
    /**
     * Barcode format
     *
     * @var string
     */
    protected $format = 'EAN13';

    /**
     * Fixed code length
     *
     * @var int
     */
    protected $code_length = 13;

    /**
     * Check digit
     *
     * @var int
     */
    protected $check = '';
    
    /**
     * Map characters to barcodes
     *
     * @var array
     */
    protected $chbar = array(
        'A' => array( // left odd parity
            '0' => '0001101',
            '1' => '0011001',
            '2' => '0010011',
            '3' => '0111101',
            '4' => '0100011',
            '5' => '0110001',
            '6' => '0101111',
            '7' => '0111011',
            '8' => '0110111',
            '9' => '0001011'
        ),
        'B' => array( // left even parity
            '0' => '0100111',
            '1' => '0110011',
            '2' => '0011011',
            '3' => '0100001',
            '4' => '0011101',
            '5' => '0111001',
            '6' => '0000101',
            '7' => '0010001',
            '8' => '0001001',
            '9' => '0010111'
        ),
        'C' => array( // right
            '0' => '1110010',
            '1' => '1100110',
            '2' => '1101100',
            '3' => '1000010',
            '4' => '1011100',
            '5' => '1001110',
            '6' => '1010000',
            '7' => '1000100',
            '8' => '1001000',
            '9' => '1110100'
        )
    );

    /**
     * Map parities
     *
     * @var array
     */
    protected $parities = array(
        '0' => 'AAAAAA',
        '1' => 'AABABB',
        '2' => 'AABBAB',
        '3' => 'AABBBA',
        '4' => 'ABAABB',
        '5' => 'ABBAAB',
        '6' => 'ABBBAA',
        '7' => 'ABABAB',
        '8' => 'ABABBA',
        '9' => 'ABBABA'
    );

    /**
     * Calculate checksum
     *
     * @param $code (string) code to represent.
     *
     * @return char checksum.
     *
     * @throws BarcodeException in case of error
     */
    protected function getChecksum($code)
    {
        $data_len = ($this->code_length - 1);
        $code_len = strlen($code);
        $sum_a = 0;
        for ($pos = 1; $pos < $data_len; $pos += 2) {
            $sum_a += $code[$pos];
        }
        if ($this->code_length > 12) {
            $sum_a *= 3;
        }
        $sum_b = 0;
        for ($pos = 0; $pos < $data_len; $pos += 2) {
            $sum_b += ($code[$pos]);
        }
        if ($this->code_length < 13) {
            $sum_b *= 3;
        }
        $this->check = ($sum_a + $sum_b) % 10;
        if ($this->check > 0) {
            $this->check = (10 - $this->check);
        }
        if ($code_len == $data_len) {
            // add check digit
            return $this->check;
        } elseif ($this->check !== intval($code[$data_len])) {
            // wrong check digit
            throw new BarcodeException('Invalid check digit: '.$this->check);
        }
        return '';
    }

    /**
     * Format code
     */
    protected function formatCode()
    {
        $code = str_pad($this->code, ($this->code_length - 1), '0', STR_PAD_LEFT);
        $this->extcode = $code.$this->getChecksum($code);
    }
    
    /**
     * Get the bars array
     *
     * @throws BarcodeException in case of error
     */
    protected function setBars()
    {
        if (!is_numeric($this->code)) {
            throw new BarcodeException('Input code must be a number');
        }
        $this->formatCode();
        $seq = '101'; // left guard bar
        $half_len = intval(ceil($this->code_length / 2));
        $parity = $this->parities[$this->extcode[0]];
        for ($pos = 1; $pos < $half_len; ++$pos) {
            $seq .= $this->chbar[$parity[($pos - 1)]][$this->extcode[$pos]];
        }
        $seq .= '01010'; // center guard bar
        for ($pos = $half_len; $pos < $this->code_length; ++$pos) {
            $seq .= $this->chbar['C'][$this->extcode[$pos]];
        }
        $seq .= '101'; // right guard bar
        $this->processBinarySequence($seq);
    }
}
