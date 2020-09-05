<?php
/**
 * Process.php
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

namespace Com\Tecnick\Barcode\Type\Square\PdfFourOneSeven;

use \Com\Tecnick\Barcode\Exception as BarcodeException;
use \Com\Tecnick\Barcode\Type\Square\PdfFourOneSeven\Data;

/**
 * Com\Tecnick\Barcode\Type\Square\PdfFourOneSeven\Compaction
 *
 * Process for PdfFourOneSeven Barcode type class
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
abstract class Compaction extends \Com\Tecnick\Barcode\Type\Square\PdfFourOneSeven\Sequence
{
    /**
     * Process Sub Text Compaction
     *
     * @param array   $txtarr
     * @param int     $submode
     * @param int     $sub
     * @param string  $code
     * @param int     $key
     * @param int     $idx
     * @param int     $codelen
     */
    protected function processTextCompactionSub(&$txtarr, &$submode, $sub, $code, $key, $idx, $codelen)
    {
        // $sub is the new submode
        if (((($idx + 1) == $codelen) || ((($idx + 1) < $codelen)
            && (array_search(ord($code[($idx + 1)]), Data::$textsubmodes[$submode]) !== false)))
            && (($sub == 3) || (($sub == 0) && ($submode == 1)))
        ) {
            // shift (temporary change only for this char)
            if ($sub == 3) {
                // shift to puntuaction
                $txtarr[] = 29;
            } else {
                // shift from lower to alpha
                $txtarr[] = 27;
            }
        } else {
            // latch
            $txtarr = array_merge($txtarr, Data::$textlatch[''.$submode.$sub]);
            // set new submode
            $submode = $sub;
        }
        // add characted code to array
        $txtarr[] = $key;
    }

    /**
     * Process Text Compaction
     *
     * @param string  $code      Data to compact
     * @param string  $codewords Codewords
     *
     * @return array of codewords
     */
    protected function processTextCompaction($code, &$codewords)
    {
        $submode = 0; // default Alpha sub-mode
        $txtarr = array(); // array of characters and sub-mode switching characters
        $codelen = strlen($code);
        for ($idx = 0; $idx < $codelen; ++$idx) {
            $chval = ord($code[$idx]);
            if (($key = array_search($chval, Data::$textsubmodes[$submode])) !== false) {
                // we are on the same sub-mode
                $txtarr[] = $key;
            } else {
                // the sub-mode is changed
                for ($sub = 0; $sub < 4; ++$sub) {
                    // search new sub-mode
                    if (($sub != $submode) && (($key = array_search($chval, Data::$textsubmodes[$sub])) !== false)) {
                        $this->processTextCompactionSub($txtarr, $submode, $sub, $code, $key, $idx, $codelen);
                        break;
                    }
                }
            }
        }
        $txtarrlen = count($txtarr);
        if (($txtarrlen % 2) != 0) {
            // add padding
            $txtarr[] = 29;
            ++$txtarrlen;
        }
        // calculate codewords
        for ($idx = 0; $idx < $txtarrlen; $idx += 2) {
            $codewords[] = (30 * $txtarr[$idx]) + $txtarr[($idx + 1)];
        }
    }

    /**
     * Process Byte Compaction
     *
     * @param string  $code      Data to compact
     * @param string  $codewords Codewords
     *
     * @return array of codewords
     */
    protected function processByteCompaction($code, &$codewords)
    {
        while (($codelen = strlen($code)) > 0) {
            if ($codelen > 6) {
                $rest = substr($code, 6);
                $code = substr($code, 0, 6);
                $sublen = 6;
            } else {
                $rest = '';
                $sublen = strlen($code);
            }
            if ($sublen == 6) {
                $tdg = bcmul(''.ord($code[0]), '1099511627776');
                $tdg = bcadd($tdg, bcmul(''.ord($code[1]), '4294967296'));
                $tdg = bcadd($tdg, bcmul(''.ord($code[2]), '16777216'));
                $tdg = bcadd($tdg, bcmul(''.ord($code[3]), '65536'));
                $tdg = bcadd($tdg, bcmul(''.ord($code[4]), '256'));
                $tdg = bcadd($tdg, ''.ord($code[5]));
                // tmp array for the 6 bytes block
                $cw6 = array();
                for ($idx = 0; $idx < 5; ++$idx) {
                    $ddg = bcmod($tdg, '900');
                    $tdg = bcdiv($tdg, '900');
                    // prepend the value to the beginning of the array
                    array_unshift($cw6, $ddg);
                }
                // append the result array at the end
                $codewords = array_merge($codewords, $cw6);
            } else {
                for ($idx = 0; $idx < $sublen; ++$idx) {
                    $codewords[] = ord($code[$idx]);
                }
            }
            $code = $rest;
        }
    }

    /**
     * Process Numeric Compaction
     *
     * @param string  $code      Data to compact
     * @param string  $codewords Codewords
     *
     * @return array of codewords
     */
    protected function processNumericCompaction($code, &$codewords)
    {
        while (($codelen = strlen($code)) > 0) {
            $rest = '';
            if ($codelen > 44) {
                $rest = substr($code, 44);
                $code = substr($code, 0, 44);
            }
            $tdg = '1'.$code;
            do {
                $ddg = bcmod($tdg, '900');
                $tdg = bcdiv($tdg, '900');
                array_unshift($codewords, $ddg);
            } while ($tdg != '0');
            $code = $rest;
        }
    }

    /**
     * Compact data by mode
     *
     * @param int     $mode    Compaction mode number
     * @param string  $code    Data to compact
     * @param boolean $addmode If true add the mode codeword in the first position
     *
     * @return array of codewords
     */
    protected function getCompaction($mode, $code, $addmode = true)
    {
        $codewords = array(); // array of codewords to return
        switch ($mode) {
            case 900:
                // Text Compaction mode latch
                $this->processTextCompaction($code, $codewords);
                break;
            case 901:
            case 924:
                // Byte Compaction mode latch
                $this->processByteCompaction($code, $codewords);
                break;
            case 902:
                // Numeric Compaction mode latch
                $this->processNumericCompaction($code, $codewords);
                break;
            case 913:
                // Byte Compaction mode shift
                $codewords[] = ord($code);
                break;
        }
        if ($addmode) {
            // add the compaction mode codeword at the beginning
            array_unshift($codewords, $mode);
        }
        return $codewords;
    }
}
