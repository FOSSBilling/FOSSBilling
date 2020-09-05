<?php
/**
 * CodeOneTwoEight.php
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
 * Com\Tecnick\Barcode\Type\Linear\CodeOneTwoEight;
 *
 * CodeOneTwoEight Barcode type class
 * CODE 128
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class CodeOneTwoEight extends \Com\Tecnick\Barcode\Type\Linear\CodeOneTwoEight\Process
{
    /**
     * Barcode format
     *
     * @var string
     */
    protected $format = 'C128';

    /**
     * Map characters to barcodes
     *
     * @var array
     */
    protected $chbar = array(
        '212222', // 00
        '222122', // 01
        '222221', // 02
        '121223', // 03
        '121322', // 04
        '131222', // 05
        '122213', // 06
        '122312', // 07
        '132212', // 08
        '221213', // 09
        '221312', // 10
        '231212', // 11
        '112232', // 12
        '122132', // 13
        '122231', // 14
        '113222', // 15
        '123122', // 16
        '123221', // 17
        '223211', // 18
        '221132', // 19
        '221231', // 20
        '213212', // 21
        '223112', // 22
        '312131', // 23
        '311222', // 24
        '321122', // 25
        '321221', // 26
        '312212', // 27
        '322112', // 28
        '322211', // 29
        '212123', // 30
        '212321', // 31
        '232121', // 32
        '111323', // 33
        '131123', // 34
        '131321', // 35
        '112313', // 36
        '132113', // 37
        '132311', // 38
        '211313', // 39
        '231113', // 40
        '231311', // 41
        '112133', // 42
        '112331', // 43
        '132131', // 44
        '113123', // 45
        '113321', // 46
        '133121', // 47
        '313121', // 48
        '211331', // 49
        '231131', // 50
        '213113', // 51
        '213311', // 52
        '213131', // 53
        '311123', // 54
        '311321', // 55
        '331121', // 56
        '312113', // 57
        '312311', // 58
        '332111', // 59
        '314111', // 60
        '221411', // 61
        '431111', // 62
        '111224', // 63
        '111422', // 64
        '121124', // 65
        '121421', // 66
        '141122', // 67
        '141221', // 68
        '112214', // 69
        '112412', // 70
        '122114', // 71
        '122411', // 72
        '142112', // 73
        '142211', // 74
        '241211', // 75
        '221114', // 76
        '413111', // 77
        '241112', // 78
        '134111', // 79
        '111242', // 80
        '121142', // 81
        '121241', // 82
        '114212', // 83
        '124112', // 84
        '124211', // 85
        '411212', // 86
        '421112', // 87
        '421211', // 88
        '212141', // 89
        '214121', // 90
        '412121', // 91
        '111143', // 92
        '111341', // 93
        '131141', // 94
        '114113', // 95
        '114311', // 96
        '411113', // 97
        '411311', // 98
        '113141', // 99
        '114131', // 100
        '311141', // 101
        '411131', // 102
        '211412', // 103 START A
        '211214', // 104 START B
        '211232', // 105 START C
        '233111', // STOP
        '200000'  // END
    );

    /**
     * Map ASCII characters for code A (ASCII 00 - 95)
     *
     * @var array
     */
    protected $keys_a = '';

    /**
     * Map ASCII characters for code B (ASCII 32 - 127)
     *
     * @var array
     */
    protected $keys_b = '';

    /**
     * Map special FNC codes for Code Set A (FNC 1-4)
     *
     * @var array
     */
    protected $fnc_a = array(241 => 102, 242 => 97, 243 => 96, 244 => 101);

    /**
     * Map special FNC codes for Code Set B (FNC 1-4)
     *
     * @var array
     */
    protected $fnc_b = array(241 => 102, 242 => 97, 243 => 96, 244 => 100);

    /**
     * Set the ASCII maps values
     */
    protected function setAsciiMaps()
    {
        // 128A (Code Set A) - ASCII characters 00 to 95 (0-9, A-Z and control codes), special characters
        $this->keys_a = ' !"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_'
            .chr(0).chr(1).chr(2).chr(3).chr(4).chr(5).chr(6).chr(7).chr(8).chr(9)
            .chr(10).chr(11).chr(12).chr(13).chr(14).chr(15).chr(16).chr(17).chr(18).chr(19)
            .chr(20).chr(21).chr(22).chr(23).chr(24).chr(25).chr(26).chr(27).chr(28).chr(29)
            .chr(30).chr(31);

        // 128B (Code Set B) - ASCII characters 32 to 127 (0-9, A-Z, a-z), special characters
        $this->keys_b = ' !"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]'
            .'^_`abcdefghijklmnopqrstuvwxyz{|}~'.chr(127);
    }

    /**
     * Get the coe point array
     *
     * @throws BarcodeException in case of error
     */
    protected function getCodeData()
    {
        $code = $this->code;
        // array of symbols
        $code_data = array();
        // split code into sequences
        $sequence = $this->getNumericSequence($code);
        // process the sequence
        $startid = 0;
        foreach ($sequence as $key => $seq) {
            $processMethod = 'processSequence'.$seq[0];
            $this->$processMethod($sequence, $code_data, $startid, $key, $seq);
        }
        return $this->finalizeCodeData($code_data, $startid);
    }

    /**
     * Process the A sequence
     *
     * @param array  $sequence   Sequence to process
     * @param array  $code_data  Array of codepoints to alter
     * @param string $code       Code to process
     * @param int    $startid    Start ID
     * @param int    $key        Sequence current key
     * @param string $seq        Sequence current value
     *
     * @throws BarcodeException in case of error
     */
    protected function processSequenceA(&$sequence, &$code_data, &$startid, $key, $seq)
    {
        if ($key == 0) {
            $startid = 103;
        } elseif ($sequence[($key - 1)][0] != 'A') {
            if (($seq[2] == 1)
                && ($key > 0)
                && ($sequence[($key - 1)][0] == 'B')
                && (!isset($sequence[($key - 1)][3]))
            ) {
                // single character shift
                $code_data[] = 98;
                // mark shift
                $sequence[$key][3] = true;
            } elseif (!isset($sequence[($key - 1)][3])) {
                $code_data[] = 101;
            }
        }
        $this->getCodeDataA($code_data, $seq[1], $seq[2]);
    }

    /**
     * Process the B sequence
     *
     * @param array  $sequence   Sequence to process
     * @param array  $code_data  Array of codepoints to alter
     * @param string $code       Code to process
     * @param int    $startid    Start ID
     * @param int    $key        Sequence current key
     * @param string $seq        Sequence current value
     *
     * @throws BarcodeException in case of error
     */
    protected function processSequenceB(&$sequence, &$code_data, &$startid, $key, $seq)
    {
        if ($key == 0) {
            $this->processSequenceBA($sequence, $code_data, $startid, $key, $seq);
        } elseif ($sequence[($key - 1)][0] != 'B') {
            $this->processSequenceBB($sequence, $code_data, $key, $seq);
        }
        $this->getCodeDataB($code_data, $seq[1], $seq[2]);
    }

    /**
     * Process the B-A sequence
     *
     * @param array  $sequence   Sequence to process
     * @param array  $code_data  Array of codepoints to alter
     * @param string $code       Code to process
     * @param int    $startid    Start ID
     * @param int    $key        Sequence current key
     * @param string $seq        Sequence current value
     *
     * @throws BarcodeException in case of error
     */
    protected function processSequenceBA(&$sequence, &$code_data, &$startid, $key, $seq)
    {
        $tmpchr = ord($seq[1][0]);
        if (($seq[2] == 1)
            && ($tmpchr >= 241)
            && ($tmpchr <= 244)
            && isset($sequence[($key + 1)])
            && ($sequence[($key + 1)][0] != 'B')
        ) {
            switch ($sequence[($key + 1)][0]) {
                case 'A':
                    $startid = 103;
                    $sequence[$key][0] = 'A';
                    $code_data[] = $this->fnc_a[$tmpchr];
                    break;
                case 'C':
                    $startid = 105;
                    $sequence[$key][0] = 'C';
                    $code_data[] = $this->fnc_a[$tmpchr];
                    break;
            }
        } else {
            $startid = 104;
        }
    }

    /**
     * Process the B-B sequence
     *
     * @param array  $sequence   Sequence to process
     * @param array  $code_data  Array of codepoints to alter
     * @param string $code       Code to process
     * @param int    $key        Sequence current key
     * @param string $seq        Sequence current value
     *
     * @throws BarcodeException in case of error
     */
    protected function processSequenceBB(&$sequence, &$code_data, $key, $seq)
    {
        if (($seq[2] == 1)
            && ($key > 0)
            && ($sequence[($key - 1)][0] == 'A')
            && (!isset($sequence[($key - 1)][3]))
        ) {
            // single character shift
            $code_data[] = 98;
            // mark shift
            $sequence[$key][3] = true;
        } elseif (!isset($sequence[($key - 1)][3])) {
            $code_data[] = 100;
        }
    }

    /**
     * Process the C sequence
     *
     * @param array  $sequence   Sequence to process
     * @param array  $code_data  Array of codepoints to alter
     * @param string $code       Code to process
     * @param int    $startid    Start ID
     * @param int    $key        Sequence current key
     * @param string $seq        Sequence current value
     *
     * @throws BarcodeException in case of error
     */
    protected function processSequenceC(&$sequence, &$code_data, &$startid, $key, $seq)
    {
        if ($key == 0) {
            $startid = 105;
        } elseif ($sequence[($key - 1)][0] != 'C') {
            $code_data[] = 99;
        }
        $this->getCodeDataC($code_data, $seq[1]);
    }

    /**
     * Get the bars array
     *
     * @throws BarcodeException in case of error
     */
    protected function setBars()
    {
        $this->setAsciiMaps();
        $code_data = $this->getCodeData();
        $this->ncols = 0;
        $this->nrows = 1;
        $this->bars = array();
        foreach ($code_data as $val) {
            $seq = $this->chbar[$val];
            for ($pos = 0; $pos < 6; ++$pos) {
                $bar_width = intval($seq[$pos]);
                if ((($pos % 2) == 0) && ($bar_width > 0)) {
                    $this->bars[] = array($this->ncols, 0, $bar_width, 1);
                }
                $this->ncols += $bar_width;
            }
        }
    }
}
