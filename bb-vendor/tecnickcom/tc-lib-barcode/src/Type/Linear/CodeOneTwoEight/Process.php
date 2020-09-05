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

namespace Com\Tecnick\Barcode\Type\Linear\CodeOneTwoEight;

use \Com\Tecnick\Barcode\Exception as BarcodeException;

/**
 * Com\Tecnick\Barcode\Type\Linear\CodeOneTwoEight\Process;
 *
 * Process methods for CodeOneTwoEight Barcode type class
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
abstract class Process extends \Com\Tecnick\Barcode\Type\Linear
{
    /**
     * Get the numeric sequence (if any)
     *
     * @param string $code Code to parse
     *
     * @return array
     *
     * @throws BarcodeException in case of error
     */
    protected function getNumericSequence($code)
    {
        $sequence = array();
        $len = strlen($code);
        // get numeric sequences (if any)
        $numseq = array();
        preg_match_all('/([0-9]{4,})/', $code, $numseq, PREG_OFFSET_CAPTURE);
        if (!empty($numseq[1])) {
            $end_offset = 0;
            foreach ($numseq[1] as $val) {
                // offset to the start of numeric substr
                $offset = $val[1];
                
                // numeric sequence
                $slen = strlen($val[0]);
                if (($slen % 2) != 0) {
                    // the length must be even
                    --$slen;
                    // add 1 to start of offset so numbers are c type encoded "from the end"
                    ++$offset;
                }
                
                if ($offset > $end_offset) {
                    // non numeric sequence
                    $sequence = array_merge(
                        $sequence,
                        $this->get128ABsequence(substr($code, $end_offset, ($offset - $end_offset)))
                    );
                }
                $sequence[] = array('C', substr($code, $offset, $slen), $slen);
                $end_offset = $offset + $slen;
            }
            if ($end_offset < $len) {
                $sequence = array_merge($sequence, $this->get128ABsequence(substr($code, $end_offset)));
            }
        } else {
            // text code (non C mode)
            $sequence = array_merge($sequence, $this->get128ABsequence($code));
        }
        return $sequence;
    }

    /**
     * Split text code in A/B sequence for 128 code
     *
     * @param string $code Code to split
     *
     * @return array sequence
     */
    protected function get128ABsequence($code)
    {
        $len = strlen($code);
        $sequence = array();
        // get A sequences (if any)
        $aseq = array();
        preg_match_all('/([\x00-\x1f])/', $code, $aseq, PREG_OFFSET_CAPTURE);
        if (!empty($aseq[1])) {
            // get the entire A sequence (excluding FNC1-FNC4)
            preg_match_all('/([\x00-\x5f]+)/', $code, $aseq, PREG_OFFSET_CAPTURE);
            $end_offset = 0;
            foreach ($aseq[1] as $val) {
                $offset = $val[1];
                if ($offset > $end_offset) {
                    // B sequence
                    $sequence[] = array(
                        'B',
                        substr($code, $end_offset, ($offset - $end_offset)),
                        ($offset - $end_offset)
                    );
                }
                // A sequence
                $slen = strlen($val[0]);
                $sequence[] = array('A', substr($code, $offset, $slen), $slen);
                $end_offset = $offset + $slen;
            }
            if ($end_offset < $len) {
                $sequence[] = array('B', substr($code, $end_offset), ($len - $end_offset));
            }
        } else {
            // only B sequence
            $sequence[] = array('B', $code, $len);
        }
        return $sequence;
    }


    /**
     * Get the A code point array
     *
     * @param array  $code_data  Array of codepoints to alter
     * @param string $code       Code to process
     * @param int    $len        Number of characters to process
     *
     * @retun array
     *
     * @throws BarcodeException in case of error
     */
    protected function getCodeDataA(&$code_data, $code, $len)
    {
        for ($pos = 0; $pos < $len; ++$pos) {
            $char = $code[$pos];
            $char_id = ord($char);
            if (($char_id >= 241) && ($char_id <= 244)) {
                $code_data[] = $this->fnc_a[$char_id];
            } elseif (($char_id >= 0) && ($char_id <= 95)) {
                $code_data[] = strpos($this->keys_a, $char);
            } else {
                throw new BarcodeException('Invalid character sequence');
            }
        }
    }

    /**
     * Get the B code point array
     *
     * @param array  $code_data  Array of codepoints to alter
     * @param string $code       Code to process
     * @param int    $len        Number of characters to process
     *
     * @retun array
     *
     * @throws BarcodeException in case of error
     */
    protected function getCodeDataB(&$code_data, $code, $len)
    {
        for ($pos = 0; $pos < $len; ++$pos) {
            $char = $code[$pos];
            $char_id = ord($char);
            if (($char_id >= 241) && ($char_id <= 244)) {
                $code_data[] = $this->fnc_b[$char_id];
            } elseif (($char_id >= 32) && ($char_id <= 127)) {
                $code_data[] = strpos($this->keys_b, $char);
            } else {
                throw new BarcodeException('Invalid character sequence: '.$char_id);
            }
        }
    }

    /**
     * Get the C code point array
     *
     * @param array  $code_data  Array of codepoints to alter
     * @param string $code       Code to process
     *
     * @retun array
     *
     * @throws BarcodeException in case of error
     */
    protected function getCodeDataC(&$code_data, $code)
    {
        // code blocks separated by FNC1 (chr 241)
        $blocks = explode(chr(241), $code);

        foreach ($blocks as $blk) {
            $len = strlen($blk);
  
            if (($len % 2) != 0) {
                throw new BarcodeException('The length of each FNC1-separated code block must be even');
            }

            for ($pos = 0; $pos < $len; $pos += 2) {
                $chrnum = $blk[$pos].$blk[($pos + 1)];
                if (preg_match('/([0-9]{2})/', $chrnum) > 0) {
                    $code_data[] = intval($chrnum);
                } else {
                    throw new BarcodeException('Invalid character sequence');
                }
            }

            $code_data[] = 102;
        }

        // remove last 102 code
        array_pop($code_data);
    }

    /**
     * Finalize code data
     *
     * @param array  $code_data  Array of codepoints to alter
     * @param int    $startid    Start ID code
     *
     * @return array
     */
    protected function finalizeCodeData($code_data, $startid)
    {
        // calculate check character
        $sum = $startid;
        foreach ($code_data as $key => $val) {
            $sum += ($val * ($key + 1));
        }
        // add check character
        $code_data[] = ($sum % 103);

        // add stop sequence
        $code_data[] = 106;
        $code_data[] = 107;
        // add start code at the beginning
        array_unshift($code_data, $startid);

        return $code_data;
    }
}
