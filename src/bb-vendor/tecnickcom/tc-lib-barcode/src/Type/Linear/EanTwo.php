<?php
/**
 * EanTwo.php
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
 * Com\Tecnick\Barcode\Type\Linear\EanTwo;
 *
 * EanTwo Barcode type class
 * EAN 2-Digits UPC-Based Extension
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class EanTwo extends \Com\Tecnick\Barcode\Type\Linear
{
    /**
     * Barcode format
     *
     * @var string
     */
    protected $format = 'EAN2';

    /**
     * Fixed code length
     *
     * @var int
     */
    protected $code_length = 2;
    
    /**
     * Map characters to barcodes
     *
     * @var array
     */
    protected $chbar = array(
        'A' => array( // left odd parity
            '0'=>'0001101',
            '1'=>'0011001',
            '2'=>'0010011',
            '3'=>'0111101',
            '4'=>'0100011',
            '5'=>'0110001',
            '6'=>'0101111',
            '7'=>'0111011',
            '8'=>'0110111',
            '9'=>'0001011'
        ),
        'B' => array( // left even parity
            '0'=>'0100111',
            '1'=>'0110011',
            '2'=>'0011011',
            '3'=>'0100001',
            '4'=>'0011101',
            '5'=>'0111001',
            '6'=>'0000101',
            '7'=>'0010001',
            '8'=>'0001001',
            '9'=>'0010111'
        )
    );

    /**
     * Map parities
     *
     * @var array
     */
    protected $parities = array(
        '0' => array('A','A'),
        '1' => array('A','B'),
        '2' => array('B','A'),
        '3' => array('B','B')
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
        return (intval($code) % 4);
    }

    /**
     * Format code
     */
    protected function formatCode()
    {
        $this->extcode = str_pad($this->code, $this->code_length, '0', STR_PAD_LEFT);
    }
    
    /**
     * Get the bars array
     *
     * @throws BarcodeException in case of error
     */
    protected function setBars()
    {
        $this->formatCode();
        $chk = $this->getChecksum($this->extcode);
        $parity = $this->parities[$chk];
        $seq = '1011'; // left guard bar
        $seq .= $this->chbar[$parity[0]][$this->extcode[0]];
        $len = strlen($this->extcode);
        for ($pos = 1; $pos < $len; ++$pos) {
            $seq .= '01'; // separator
            $seq .= $this->chbar[$parity[$pos]][$this->extcode[$pos]];
        }
        $this->processBinarySequence($seq);
    }
}
