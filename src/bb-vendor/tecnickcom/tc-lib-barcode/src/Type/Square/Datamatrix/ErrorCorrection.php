<?php
/**
 * ErrorCorrection.php
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

namespace Com\Tecnick\Barcode\Type\Square\Datamatrix;

/**
 * Com\Tecnick\Barcode\Type\Square\Datamatrix\ErrorCorrection
 *
 * Error correction methods and other utilities for Datamatrix Barcode type class
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class ErrorCorrection
{
    /**
     * Product of two numbers in a Power-of-Two Galois Field
     *
     * @param int   $numa First number to multiply.
     * @param int   $numb Second number to multiply.
     * @param array $log  Log table.
     * @param array $alog Anti-Log table.
     * @param array $ngf  Number of Factors of the Reed-Solomon polynomial.
     *
     * @return int product
     */
    protected function getGFProduct($numa, $numb, $log, $alog, $ngf)
    {
        if (($numa == 0) || ($numb == 0)) {
            return 0;
        }
        return ($alog[($log[$numa] + $log[$numb]) % ($ngf - 1)]);
    }

    /**
     * Add error correction codewords to data codewords array (ANNEX E).
     *
     * @param array $wdc Array of datacodewords.
     * @param int   $nbk Number of blocks.
     * @param int   $ncw Number of data codewords per block.
     * @param int   $ncc Number of correction codewords per block.
     * @param int   $ngf Number of fields on log/antilog table (power of 2).
     * @param int   $vpp The value of its prime modulus polynomial (301 for ECC200).
     *
     * @return array data codewords + error codewords
     */
    public function getErrorCorrection($wdc, $nbk, $ncw, $ncc, $ngf = 256, $vpp = 301)
    {
        // generate the log ($log) and antilog ($alog) tables
        $log = array(0);
        $alog = array(1);
        $this->genLogs($log, $alog, $ngf, $vpp);
        
        // generate the polynomial coefficients (c)
        $plc = array_fill(0, ($ncc + 1), 0);
        $plc[0] = 1;
        for ($i = 1; $i <= $ncc; ++$i) {
            $plc[$i] = $plc[($i-1)];
            for ($j = ($i - 1); $j >= 1; --$j) {
                $plc[$j] = $plc[($j - 1)] ^ $this->getGFProduct($plc[$j], $alog[$i], $log, $alog, $ngf);
            }
            $plc[0] = $this->getGFProduct($plc[0], $alog[$i], $log, $alog, $ngf);
        }
        ksort($plc);
        
        // total number of data codewords
        $num_wd = ($nbk * $ncw);
        // total number of error codewords
        $num_we = ($nbk * $ncc);
        // for each block
        for ($b = 0; $b < $nbk; ++$b) {
            // create interleaved data block
            $block = array();
            for ($n = $b; $n < $num_wd; $n += $nbk) {
                $block[] = $wdc[$n];
            }
            // initialize error codewords
            $wec = array_fill(0, ($ncc + 1), 0);
            // calculate error correction codewords for this block
            for ($i = 0; $i < $ncw; ++$i) {
                $ker = ($wec[0] ^ $block[$i]);
                for ($j = 0; $j < $ncc; ++$j) {
                    $wec[$j] = ($wec[($j + 1)] ^ $this->getGFProduct($ker, $plc[($ncc - $j - 1)], $log, $alog, $ngf));
                }
            }
            // add error codewords at the end of data codewords
            $j = 0;
            for ($i = $b; $i < $num_we; $i += $nbk) {
                $wdc[($num_wd + $i)] = $wec[$j];
                ++$j;
            }
        }
        // reorder codewords
        ksort($wdc);
        return $wdc;
    }

    /**
     * Generate the log ($log) and antilog ($alog) tables
     *
     * @param array $log  Log table
     * @param arrya $alog Anti-Log table
     * @param int   $ngf  Number of fields on log/antilog table (power of 2).
     * @param int   $vpp  The value of its prime modulus polynomial (301 for ECC200).
     */
    protected function genLogs(&$log, &$alog, $ngf, $vpp)
    {
        for ($i = 1; $i < $ngf; ++$i) {
            $alog[$i] = ($alog[($i - 1)] * 2);
            if ($alog[$i] >= $ngf) {
                $alog[$i] ^= $vpp;
            }
            $log[$alog[$i]] = $i;
        }
        ksort($log);
    }
}
