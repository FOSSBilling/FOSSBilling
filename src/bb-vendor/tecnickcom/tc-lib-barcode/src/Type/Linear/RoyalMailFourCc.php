<?php
/**
 * RoyalMailFourCC.php
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
 * Com\Tecnick\Barcode\Type\Linear\RoyalMailFourCc;
 *
 * RoyalMailFourCC Barcode type class
 * RMS4CC (Royal Mail 4-state Customer Bar Code)
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class RoyalMailFourCc extends \Com\Tecnick\Barcode\Type\Linear
{
    /**
     * Barcode format
     *
     * @var string
     */
    protected $format = 'RMS4CC';

    /**
     * Map characters to barcodes
     *
     * @var array
     */
    protected $chbar = array(
        '0' => '3322',
        '1' => '3412',
        '2' => '3421',
        '3' => '4312',
        '4' => '4321',
        '5' => '4411',
        '6' => '3142',
        '7' => '3232',
        '8' => '3241',
        '9' => '4132',
        'A' => '4141',
        'B' => '4231',
        'C' => '3124',
        'D' => '3214',
        'E' => '3223',
        'F' => '4114',
        'G' => '4123',
        'H' => '4213',
        'I' => '1342',
        'J' => '1432',
        'K' => '1441',
        'L' => '2332',
        'M' => '2341',
        'N' => '2431',
        'O' => '1324',
        'P' => '1414',
        'Q' => '1423',
        'R' => '2314',
        'S' => '2323',
        'T' => '2413',
        'U' => '1144',
        'V' => '1234',
        'W' => '1243',
        'X' => '2134',
        'Y' => '2143',
        'Z' => '2233'
    );

    /**
     * Characters used for checksum
     *
     * @var array
     */
    protected $chksum = array(
        '0' => '11',
        '1' => '12',
        '2' => '13',
        '3' => '14',
        '4' => '15',
        '5' => '10',
        '6' => '21',
        '7' => '22',
        '8' => '23',
        '9' => '24',
        'A' => '25',
        'B' => '20',
        'C' => '31',
        'D' => '32',
        'E' => '33',
        'F' => '34',
        'G' => '35',
        'H' => '30',
        'I' => '41',
        'J' => '42',
        'K' => '43',
        'L' => '44',
        'M' => '45',
        'N' => '40',
        'O' => '51',
        'P' => '52',
        'Q' => '53',
        'R' => '54',
        'S' => '55',
        'T' => '50',
        'U' => '01',
        'V' => '02',
        'W' => '03',
        'X' => '04',
        'Y' => '05',
        'Z' => '00'
    );

    /**
     * Calculate the checksum.
     *
     * @param $code (string) code to represent.
     *
     * @return char checksum.
     *
     * @throws BarcodeException in case of error
     */
    protected function getChecksum($code)
    {
        $row = 0;
        $col = 0;
        $len = strlen($code);
        for ($pos = 0; $pos < $len; ++$pos) {
            $char = $code[$pos];
            if (!isset($this->chksum[$char])) {
                throw new BarcodeException('Invalid character: chr('.ord($char).')');
            }
            $row += intval($this->chksum[$char][0]);
            $col += intval($this->chksum[$char][1]);
        }
        $row %= 6;
        $col %= 6;
        $check = array_keys($this->chksum, $row.$col);
        return $check[0];
    }

    /**
     * Format code
     */
    protected function formatCode()
    {
        $code = strtoupper($this->code);
        $this->extcode = $code.$this->getChecksum($code);
    }
    
    /**
     * Get the central bars
     *
     * @throws BarcodeException in case of error
     */
    protected function getCoreBars()
    {
        $this->formatCode();
        $clen = strlen($this->extcode);
        for ($chr = 0; $chr < $clen; ++$chr) {
            $char = $this->extcode[$chr];
            for ($pos = 0; $pos < 4; ++$pos) {
                switch ($this->chbar[$char][$pos]) {
                    case '1':
                        $this->bars[] = array($this->ncols, 0, 1, 2);
                        break;
                    case '2':
                        $this->bars[] = array($this->ncols, 0, 1, 3);
                        break;
                    case '3':
                        $this->bars[] = array($this->ncols, 1, 1, 1);
                        break;
                    case '4':
                        $this->bars[] = array($this->ncols, 1, 1, 2);
                        break;
                }
                $this->ncols +=2;
            }
        }
    }
    
    /**
     * Get the bars array
     *
     * @throws BarcodeException in case of error
     */
    protected function setBars()
    {
        $this->ncols = 0;
        $this->nrows = 3;
        $this->bars = array();
        
        // start bar
        $this->bars[] = array($this->ncols, 0, 1, 2);
        $this->ncols += 2;

        $this->getCoreBars();
  
        // stop bar
        $this->bars[] = array($this->ncols, 0, 1, 3);
        ++$this->ncols;
    }
}
