<?php
/**
 * CodeNineThree.php
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
 * Com\Tecnick\Barcode\Type\Linear\CodeNineThree;
 *
 * CodeNineThree Barcode type class
 * CODE 93 - USS-93
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class CodeNineThree extends \Com\Tecnick\Barcode\Type\Linear\CodeThreeNineExtCheck
{
    /**
     * Barcode format
     *
     * @var string
     */
    protected $format = 'C93';

    /**
     * Map characters to barcodes
     *
     * @var array
     */
    protected $chbar = array(
        32  => '311211', // space
        36  => '321111', // $
        37  => '211131', // %
        42  => '111141', // start-stop
        43  => '113121', // +
        45  => '121131', // -
        46  => '311112', // .
        47  => '112131', // /
        48  => '131112', // 0
        49  => '111213', // 1
        50  => '111312', // 2
        51  => '111411', // 3
        52  => '121113', // 4
        53  => '121212', // 5
        54  => '121311', // 6
        55  => '111114', // 7
        56  => '131211', // 8
        57  => '141111', // 9
        65  => '211113', // A
        66  => '211212', // B
        67  => '211311', // C
        68  => '221112', // D
        69  => '221211', // E
        70  => '231111', // F
        71  => '112113', // G
        72  => '112212', // H
        73  => '112311', // I
        74  => '122112', // J
        75  => '132111', // K
        76  => '111123', // L
        77  => '111222', // M
        78  => '111321', // N
        79  => '121122', // O
        80  => '131121', // P
        81  => '212112', // Q
        82  => '212211', // R
        83  => '211122', // S
        84  => '211221', // T
        85  => '221121', // U
        86  => '222111', // V
        87  => '112122', // W
        88  => '112221', // X
        89  => '122121', // Y
        90  => '123111', // Z
        128 => '121221', // ($)
        129 => '311121', // (/)
        130 => '122211', // (+)
        131 => '312111'  // (%)
    );

    /**
     * Map for extended characters
     *
     * @var array
     */
    protected $extcodes = array();

    /**
     * Characters used for checksum
     *
     * @var array
     */
    protected $chksum = array(
        '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K',
        'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V',
        'W', 'X', 'Y', 'Z', '-', '.', ' ', '$', '/', '+', '%',
        '<', '=', '>', '?'
    );

    /**
     * Calculate CODE 93 checksum (modulo 47).
     *
     * @param $code (string) code to represent.
     *
     * @return char checksum.
     */
    protected function getChecksum($code)
    {
        // translate special characters
        $code = strtr($code, chr(128).chr(131).chr(129).chr(130), '<=>?');
        $clen = strlen($code);
        // calculate check digit C
        $pck = 1;
        $check = 0;
        for ($idx = ($clen - 1); $idx >= 0; --$idx) {
            $key = array_keys($this->chksum, $code[$idx]);
            $check += ($key[0] * $pck);
            ++$pck;
            if ($pck > 20) {
                $pck = 1;
            }
        }
        $check %= 47;
        $chk = $this->chksum[$check];
        $code .= $chk;
        // calculate check digit K
        $pck = 1;
        $check = 0;
        for ($idx = $clen; $idx >= 0; --$idx) {
            $key = array_keys($this->chksum, $code[$idx]);
            $check += ($key[0] * $pck);
            ++$pck;
            if ($pck > 15) {
                $pck = 1;
            }
        }
        $check %= 47;
        $key = $this->chksum[$check];
        $checksum = $chk.$key;
        // restore special characters
        $checksum = strtr($checksum, '<=>?', chr(128).chr(131).chr(129).chr(130));
        return $checksum;
    }

    /**
     * Get the bars array
     *
     * @throws BarcodeException in case of error
     */
    protected function setBars()
    {
        $this->extcodes = array(
            chr(131).'U', chr(128).'A', chr(128).'B', chr(128).'C', chr(128).'D', chr(128).'E', chr(128).'F',
            chr(128).'G', chr(128).'H', chr(128).'I', chr(128).'J', chr(128).'K', chr(128).'L', chr(128).'M',
            chr(128).'N', chr(128).'O', chr(128).'P', chr(128).'Q', chr(128).'R', chr(128).'S', chr(128).'T',
            chr(128).'U', chr(128).'V', chr(128).'W', chr(128).'X', chr(128).'Y', chr(128).'Z', chr(131).'A',
            chr(131).'B', chr(131).'C', chr(131).'D', chr(131).'E', ' ', chr(129).'A', chr(129).'B',
            chr(129).'C', chr(129).'D', chr(129).'E', chr(129).'F', chr(129).'G', chr(129).'H', chr(129).'I',
            chr(129).'J', chr(129).'K', chr(129).'L', '-', '.', chr(129).'O', '0', '1', '2', '3', '4', '5',
            '6', '7', '8', '9', chr(129).'Z', chr(131).'F', chr(131).'G', chr(131).'H', chr(131).'I',
            chr(131).'J', chr(131).'V', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N',
            'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', chr(131).'K', chr(131).'L',
            chr(131).'M', chr(131).'N', chr(131).'O', chr(131).'W', chr(130).'A',
            chr(130).'B', chr(130).'C', chr(130).'D', chr(130).'E', chr(130).'F', chr(130).'G', chr(130).'H',
            chr(130).'I', chr(130).'J', chr(130).'K', chr(130).'L', chr(130).'M', chr(130).'N', chr(130).'O',
            chr(130).'P', chr(130).'Q', chr(130).'R', chr(130).'S', chr(130).'T', chr(130).'U', chr(130).'V',
            chr(130).'W', chr(130).'X', chr(130).'Y', chr(130).'Z', chr(131).'P', chr(131).'Q', chr(131).'R',
            chr(131).'S', chr(131).'T'
        );
        $this->ncols = 0;
        $this->nrows = 1;
        $this->bars = array();
        $this->formatCode();
        $clen = strlen($this->extcode);
        for ($chr = 0; $chr < $clen; ++$chr) {
            $char = ord($this->extcode[$chr]);
            for ($pos = 0; $pos < 6; ++$pos) {
                $bar_width = intval($this->chbar[$char][$pos]);
                if (($pos % 2) == 0) {
                    $this->bars[] = array($this->ncols, 0, $bar_width, 1);
                }
                $this->ncols += $bar_width;
            }
        }
        $this->bars[] = array($this->ncols, 0, 1, 1);
        $this->ncols += 1;
    }
}
