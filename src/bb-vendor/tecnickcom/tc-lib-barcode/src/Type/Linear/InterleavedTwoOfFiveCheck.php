<?php
/**
 * InterleavedTwoOfFiveCheck.php
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
 * Com\Tecnick\Barcode\Type\Linear\InterleavedTwoOfFiveCheck;
 *
 * InterleavedTwoOfFiveCheck Barcode type class
 * Interleaved 2 of 5 + CHECKSUM
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class InterleavedTwoOfFiveCheck extends \Com\Tecnick\Barcode\Type\Linear\StandardTwoOfFiveCheck
{
    /**
     * Barcode format
     *
     * @var string
     */
    protected $format = 'I25+';

    /**
     * Map characters to barcodes
     *
     * @var array
     */
    protected $chbar = array(
        '0' => '11221',
        '1' => '21112',
        '2' => '12112',
        '3' => '22111',
        '4' => '11212',
        '5' => '21211',
        '6' => '12211',
        '7' => '11122',
        '8' => '21121',
        '9' => '12121',
        'A' => '11',
        'Z' => '21'
    );

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
        if ((strlen($this->extcode) % 2) != 0) {
            // add leading zero if code-length is odd
            $this->extcode = '0'.$this->extcode;
        }
        // add start and stop codes
        $this->extcode = 'AA'.strtolower($this->extcode).'ZA';
        $this->ncols = 0;
        $this->nrows = 1;
        $this->bars = array();
        $clen = strlen($this->extcode);
        for ($idx = 0; $idx < $clen; $idx = ($idx + 2)) {
            $char_bar = $this->extcode[$idx];
            $char_space = $this->extcode[($idx + 1)];
            if ((!isset($this->chbar[$char_bar])) || (!isset($this->chbar[$char_space]))) {
                throw new BarcodeException('Invalid character sequence: '.$char_bar.$char_space);
            }
            // create a bar-space sequence
            $seq = '';
            $chrlen = strlen($this->chbar[$char_bar]);
            for ($pos = 0; $pos < $chrlen; ++$pos) {
                $seq .= $this->chbar[$char_bar][$pos].$this->chbar[$char_space][$pos];
            }
            $seqlen = strlen($seq);
            for ($pos = 0; $pos < $seqlen; ++$pos) {
                $bar_width = intval($seq[$pos]);
                if ((($pos % 2) == 0) && ($bar_width > 0)) {
                    $this->bars[] = array($this->ncols, 0, $bar_width, 1);
                }
                $this->ncols += $bar_width;
            }
        }
        --$this->ncols;
    }
}
