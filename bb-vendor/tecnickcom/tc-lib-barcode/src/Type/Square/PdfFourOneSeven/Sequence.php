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
 * Com\Tecnick\Barcode\Type\Square\PdfFourOneSeven\Sequence
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
abstract class Sequence extends \Com\Tecnick\Barcode\Type\Square
{
    /**
     * Get the error correction level (0-8) to be used
     *
     * @param int $ecl   Error correction level
     * @param int $numcw Number of data codewords
     *
     * @return int error correction level
     */
    protected function getErrorCorrectionLevel($ecl, $numcw)
    {
        $maxecl = 8; // maximum error level
        $maxerrsize = (928 - $numcw); // available codewords for error
        while (($maxecl > 0) && ($maxerrsize < (2 << $maxecl))) {
            --$maxecl;
        }
        if (($ecl < 0) || ($ecl > 8)) {
            if ($numcw < 41) {
                $ecl = 2;
            } elseif ($numcw < 161) {
                $ecl = 3;
            } elseif ($numcw < 321) {
                $ecl = 4;
            } elseif ($numcw < 864) {
                $ecl = 5;
            } else {
                $ecl = $maxecl;
            }
        }
        return min($maxecl, $ecl);
    }

    /**
     * Get the error correction codewords
     *
     * @param array $codewords  Array of codewords including Symbol Length Descriptor and pad
     * @param int   $ecl        Error correction level 0-8
     *
     * @return array of error correction codewords
     */
    protected function getErrorCorrection($codewords, $ecl)
    {
        // get error correction coefficients
        $ecc = Data::$rsfactors[$ecl];
        // number of error correction factors
        $eclsize = (2 << $ecl);
        // maximum index for $rsfactors[$ecl]
        $eclmaxid = ($eclsize - 1);
        // initialize array of error correction codewords
        $ecw = array_fill(0, $eclsize, 0);
        // for each data codeword
        foreach ($codewords as $cdw) {
            $tk1 = ($cdw + $ecw[$eclmaxid]) % 929;
            for ($idx = $eclmaxid; $idx > 0; --$idx) {
                $tk2 = ($tk1 * $ecc[$idx]) % 929;
                $tk3 = 929 - $tk2;
                $ecw[$idx] = ($ecw[($idx - 1)] + $tk3) % 929;
            }
            $tk2 = ($tk1 * $ecc[0]) % 929;
            $tk3 = 929 - $tk2;
            $ecw[0] = $tk3 % 929;
        }
        foreach ($ecw as $idx => $err) {
            if ($err != 0) {
                $ecw[$idx] = 929 - $err;
            }
        }
        return array_reverse($ecw);
    }

    /**
     * Process a single sequence
     *
     * @param array  $sequence_array  Sequence to process
     * @param string $code            Data to process
     * @param int    $seq             Current sequence
     * @param int    $offset          Current code offset
     */
    protected function processSequence(&$sequence_array, $code, $seq, $offset)
    {
        // extract text sequence before the number sequence
        $prevseq = substr($code, $offset, ($seq - $offset));
        $textseq = array();
        // get text sequences
        preg_match_all('/([\x09\x0a\x0d\x20-\x7e]{5,})/', $prevseq, $textseq, PREG_OFFSET_CAPTURE);
        $textseq[1][] = array('', strlen($prevseq));
        $txtoffset = 0;
        foreach ($textseq[1] as $txtseq) {
            $txtseqlen = strlen($txtseq[0]);
            if ($txtseq[1] > 0) {
                // extract byte sequence before the text sequence
                $prevtxtseq = substr($prevseq, $txtoffset, ($txtseq[1] - $txtoffset));
                if (strlen($prevtxtseq) > 0) {
                    // add BYTE sequence
                    if ((strlen($prevtxtseq) == 1)
                        && ((count($sequence_array) > 0)
                        && ($sequence_array[(count($sequence_array) - 1)][0] == 900))
                    ) {
                        $sequence_array[] = array(913, $prevtxtseq);
                    } elseif ((strlen($prevtxtseq) % 6) == 0) {
                        $sequence_array[] = array(924, $prevtxtseq);
                    } else {
                        $sequence_array[] = array(901, $prevtxtseq);
                    }
                }
            }
            if ($txtseqlen > 0) {
                // add numeric sequence
                $sequence_array[] = array(900, $txtseq[0]);
            }
            $txtoffset = ($txtseq[1] + $txtseqlen);
        }
    }

    /**
     * Get an array of sequences from input
     *
     * @param string $code Data to process
     *
     * @return bidimensional array containing characters and classification
     */
    protected function getInputSequences($code)
    {
        $sequence_array = array(); // array to be returned
        $numseq = array();
        // get numeric sequences
        preg_match_all('/([0-9]{13,})/', $code, $numseq, PREG_OFFSET_CAPTURE);
        $numseq[1][] = array('', strlen($code));
        $offset = 0;
        foreach ($numseq[1] as $seq) {
            $seqlen = strlen($seq[0]);
            if ($seq[1] > 0) {
                $this->processSequence($sequence_array, $code, $seq[1], $offset);
            }
            if ($seqlen > 0) {
                // add numeric sequence
                $sequence_array[] = array(902, $seq[0]);
            }
            $offset = ($seq[1] + $seqlen);
        }
        return $sequence_array;
    }
}
