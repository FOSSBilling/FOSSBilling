<?php
/**
 * Modes.php
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

use \Com\Tecnick\Barcode\Exception as BarcodeException;

/**
 * Com\Tecnick\Barcode\Type\Square\Datamatrix\Modes
 *
 * Modes for Datamatrix Barcode type class
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
abstract class Modes extends \Com\Tecnick\Barcode\Type\Square\Datamatrix\Placement
{
    /**
     * Return the 253-state codeword
     *
     * @param int $cdwpad Pad codeword.
     * @param int $cdwpos Number of data codewords from the beginning of encoded data.
     *
     * @return pad codeword
     */
    public function get253StateCodeword($cdwpad, $cdwpos)
    {
        $pad = ($cdwpad + (((149 * $cdwpos) % 253) + 1));
        if ($pad > 254) {
            $pad -= 254;
        }
        return $pad;
    }

    /**
     * Return the 255-state codeword
     *
     * @param int $cdwpad Pad codeword.
     * @param int $cdwpos Number of data codewords from the beginning of encoded data.
     *
     * @return int pad codeword
     */
    protected function get255StateCodeword($cdwpad, $cdwpos)
    {
        $pad = ($cdwpad + (((149 * $cdwpos) % 255) + 1));
        if ($pad > 255) {
            $pad -= 256;
        }
        return $pad;
    }

    /**
     * Returns true if the char belongs to the selected mode
     *
     * @param int $chr  Character (byte) to check.
     * @param int $mode Current encoding mode.
     *
     * @return boolean true if the char is of the selected mode.
     */
    protected function isCharMode($chr, $mode)
    {
        $map = array(
            //Data::ENC_ASCII     => 'isASCIIMode',
            Data::ENC_C40       => 'isC40Mode',
            Data::ENC_TXT       => 'isTXTMode',
            Data::ENC_X12       => 'isX12Mode',
            Data::ENC_EDF       => 'isEDFMode',
            Data::ENC_BASE256   => 'isBASE256Mode',
            Data::ENC_ASCII_EXT => 'isASCIIEXTMode',
            Data::ENC_ASCII_NUM => 'isASCIINUMMode'
        );
        $method = $map[$mode];
        return $this->$method($chr);
    }

    ///**
    // * Tell if char is ASCII character 0 to 127
    // *
    // * @param int $chr  Character (byte) to check.
    // *
    // * @return boolean
    // */
    //protected function isASCIIMode($chr)
    //{
    //    return (($chr >= 0) && ($chr <= 127));
    //}

    /**
     * Tell if char is Upper-case alphanumeric
     *
     * @param int $chr  Character (byte) to check.
     *
     * @return boolean
     */
    protected function isC40Mode($chr)
    {
        return (($chr == 32) || (($chr >= 48) && ($chr <= 57)) || (($chr >= 65) && ($chr <= 90)));
    }

    /**
     * Tell if char is Lower-case alphanumeric
     *
     * @param int $chr  Character (byte) to check.
     *
     * @return boolean
     */
    protected function isTXTMode($chr)
    {
        return (($chr == 32) || (($chr >= 48) && ($chr <= 57)) || (($chr >= 97) && ($chr <= 122)));
    }

    /**
     * Tell if char is ANSI X12
     *
     * @param int $chr  Character (byte) to check.
     *
     * @return boolean
     */
    protected function isX12Mode($chr)
    {
        return (($chr == 13) || ($chr == 42) || ($chr == 62));
    }

    /**
     * Tell if char is ASCII character 32 to 94
     *
     * @param int $chr  Character (byte) to check.
     *
     * @return boolean
     */
    protected function isEDFMode($chr)
    {
        return (($chr >= 32) && ($chr <= 94));
    }

    /**
     * Tell if char is Function character (FNC1, Structured Append, Reader Program, or Code Page)
     *
     * @param int $chr  Character (byte) to check.
     *
     * @return boolean
     */
    protected function isBASE256Mode($chr)
    {
        return (($chr == 232) || ($chr == 233) || ($chr == 234) || ($chr == 241));
    }

    /**
     * Tell if char is ASCII character 128 to 255
     *
     * @param int $chr  Character (byte) to check.
     *
     * @return boolean
     */
    protected function isASCIIEXTMode($chr)
    {
        return (($chr >= 128) && ($chr <= 255));
    }

    /**
     * Tell if char is ASCII digits
     *
     * @param int $chr  Character (byte) to check.
     *
     * @return boolean
     */
    protected function isASCIINUMMode($chr)
    {
        return (($chr >= 48) && ($chr <= 57));
    }

    /**
     * Choose the minimum matrix size and return the max number of data codewords.
     *
     * @param int $numcw Number of current codewords.
     *
     * @return number of data codewords in matrix
     */
    protected function getMaxDataCodewords($numcw)
    {
        $mdc = 0;
        foreach (Data::$symbattr[$this->shape] as $matrix) {
            if ($matrix[11] >= $numcw) {
                $mdc = $matrix[11];
                break;
            }
        }
        return $mdc;
    }

    /**
     * Get the switching codeword to a new encoding mode (latch codeword)
     * @param $mode (int) New encoding mode.
     * @return (int) Switch codeword.
     * @protected
     */
    protected function getSwitchEncodingCodeword($mode)
    {
        $map = array(
            Data::ENC_ASCII   => 254,
            Data::ENC_C40     => 230,
            Data::ENC_TXT     => 239,
            Data::ENC_X12     => 238,
            Data::ENC_EDF     => 240,
            Data::ENC_BASE256 => 231
        );
        $cdw = $map[$mode];
        if (($cdw == 254) && ($this->last_enc == Data::ENC_EDF)) {
            $cdw = 124;
        }
        return $cdw;
    }
}
