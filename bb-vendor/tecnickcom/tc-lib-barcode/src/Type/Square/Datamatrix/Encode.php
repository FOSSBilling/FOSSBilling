<?php
/**
 * Encode.php
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2020 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 *
 * This file is part of tc-lib-barcode software library.
 */

namespace Com\Tecnick\Barcode\Type\Square\Datamatrix;

use \Com\Tecnick\Barcode\Exception as BarcodeException;
use \Com\Tecnick\Barcode\Type\Square\Datamatrix\Data;

/**
 * Com\Tecnick\Barcode\Type\Square\Datamatrix\Encode
 *
 * Datamatrix Barcode type class
 * DATAMATRIX (ISO/IEC 16022)
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class Encode extends \Com\Tecnick\Barcode\Type\Square\Datamatrix\EncodeTxt
{
    /**
     * Store last used encoding for data codewords.
     *
     * @var int
     */
    public $last_enc;

    /**
     * Datamatrix shape key (S=square, R=rectangular)
     *
     * @var string
     */
    public $shape;

    /**
     * Initialize a new encode object
     *
     * @param string $shape Datamatrix shape key (S=square, R=rectangular)
     */
    public function __construct($shape = 'S')
    {
        $this->shape = $shape;
    }

    /**
     * Encode ASCII
     *
     * @param int    $cdw
     * @param int    $cdw_num
     * @param int    $pos
     * @param int    $data_length
     * @param string $data
     * @param int    $enc
     */
    public function encodeASCII(&$cdw, &$cdw_num, &$pos, &$data_length, &$data, &$enc)
    {
        if (($data_length > 1)
            && ($pos < ($data_length - 1))
            && ($this->isCharMode(ord($data[$pos]), Data::ENC_ASCII_NUM)
                && $this->isCharMode(ord($data[$pos + 1]), Data::ENC_ASCII_NUM)
            )
        ) {
            // 1. If the next data sequence is at least 2 consecutive digits,
            // encode the next two digits as a double digit in ASCII mode.
            $cdw[] = (intval(substr($data, $pos, 2)) + 130);
            ++$cdw_num;
            $pos += 2;
        } else {
            // 2. If the look-ahead test (starting at step J) indicates another mode, switch to that mode.
            $newenc = $this->lookAheadTest($data, $pos, $enc);
            if ($newenc != $enc) {
                // switch to new encoding
                $enc = $newenc;
                $cdw[] = $this->getSwitchEncodingCodeword($enc);
                ++$cdw_num;
            } else {
                // get new byte
                $chr = ord($data[$pos]);
                ++$pos;
                if ($this->isCharMode($chr, Data::ENC_ASCII_EXT)) {
                    // 3. If the next data character is extended ASCII (greater than 127)
                    // encode it in ASCII mode first using the Upper Shift (value 235) character.
                    $cdw[] = 235;
                    $cdw[] = ($chr - 127);
                    $cdw_num += 2;
                } else {
                    // 4. Otherwise process the next data character in ASCII encodation.
                    $cdw[] = ($chr + 1);
                    ++$cdw_num;
                }
            }
        }
    }

    /**
     * Encode EDF4
     *
     * @param int    $epos
     * @param int    $cdw
     * @param int    $cdw_num
     * @param int    $pos
     * @param int    $data_length
     * @param int    $field_length
     * @param int    $enc
     * @param array  $temp_cw
     *
     * @return boolean true to break the loop
     */
    public function encodeEDFfour($epos, &$cdw, &$cdw_num, &$pos, &$data_length, &$field_length, &$enc, &$temp_cw)
    {
        if (($epos == $data_length) && ($field_length < 3)) {
            $enc = Data::ENC_ASCII;
            $cdw[] = $this->getSwitchEncodingCodeword($enc);
            ++$cdw_num;
            return true;
        }
        if ($field_length < 4) {
            // set unlatch character
            $temp_cw[] = 0x1f;
            ++$field_length;
            // fill empty characters
            for ($i = $field_length; $i < 4; ++$i) {
                $temp_cw[] = 0;
            }
            $enc = Data::ENC_ASCII;
            $this->last_enc = $enc;
        }
        // encodes four data characters in three codewords
        $cdw[] = (($temp_cw[0] & 0x3F) << 2) + (($temp_cw[1] & 0x30) >> 4);
        $cdw[] = (($temp_cw[1] & 0x0F) << 4) + (($temp_cw[2] & 0x3C) >> 2);
        $cdw[] = (($temp_cw[2] & 0x03) << 6) + ($temp_cw[3] & 0x3F);
        $cdw_num += 3;
        $temp_cw = array();
        $pos = $epos;
        $field_length = 0;
        if ($enc == Data::ENC_ASCII) {
            return true; // exit from EDIFACT mode
        }
        return false;
    }

    /**
     * Encode EDF
     *
     * @param int    $cdw
     * @param int    $cdw_num
     * @param int    $pos
     * @param int    $data_length
     * @param int    $field_length
     * @param string $data
     * @param int    $enc
     */
    public function encodeEDF(&$cdw, &$cdw_num, &$pos, &$data_length, &$field_length, &$data, &$enc)
    {
        // initialize temporary array with 0 length
        $temp_cw = array();
        $epos = $pos;
        $field_length = 0;
        do {
            // 2. process the next character in EDIFACT encodation.
            $chr = ord($data[$epos]);
            if ($this->isCharMode($chr, Data::ENC_EDF)) {
                ++$epos;
                $temp_cw[] = $chr;
                ++$field_length;
            }
            if (($field_length == 4)
                || ($epos == $data_length)
                || !$this->isCharMode($chr, Data::ENC_EDF)
            ) {
                if ($this->encodeEDFfour($epos, $cdw, $cdw_num, $pos, $data_length, $field_length, $enc, $temp_cw)) {
                    break;
                }
            }
        } while ($epos < $data_length);
    }

    /**
     * Encode Base256
     *
     * @param int    $cdw
     * @param int    $cdw_num
     * @param int    $pos
     * @param int    $data_length
     * @param int    $field_length
     * @param string $data
     * @param int    $enc
     */
    public function encodeBase256(&$cdw, &$cdw_num, &$pos, &$data_length, &$field_length, &$data, &$enc)
    {
        // initialize temporary array with 0 length
        $temp_cw = array();
        $field_length = 0;
        while (($pos < $data_length) && ($field_length <= 1555)) {
            $newenc = $this->lookAheadTest($data, $pos, $enc);
            if ($newenc != $enc) {
                // 1. If the look-ahead test (starting at step J)
                // indicates another mode, switch to that mode.
                $enc = $newenc;
                break; // exit from B256 mode
            } else {
                // 2. Otherwise, process the next character in Base 256 encodation.
                $chr = ord($data[$pos]);
                ++$pos;
                $temp_cw[] = $chr;
                ++$field_length;
            }
        }
        // set field length
        if ($field_length <= 249) {
            $cdw[] = $this->get255StateCodeword($field_length, ($cdw_num + 1));
            ++$cdw_num;
        } else {
            $cdw[] = $this->get255StateCodeword((floor($field_length / 250) + 249), ($cdw_num + 1));
            $cdw[] = $this->get255StateCodeword(($field_length % 250), ($cdw_num + 2));
            $cdw_num += 2;
        }
        if (!empty($temp_cw)) {
            // add B256 field
            foreach ($temp_cw as $cht) {
                $cdw[] = $this->get255StateCodeword($cht, ($cdw_num + 1));
                ++$cdw_num;
            }
        }
    }
}
