<?php
/**
 * Imb.php
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 *
 * This file is part of tc-lib-barcode software library.
 */

namespace Com\Tecnick\Barcode\Type\Linear;

use \Com\Tecnick\Barcode\Exception as BarcodeException;

/**
 * Com\Tecnick\Barcode\Type\Linear\Imb;
 *
 * Imb Barcode type class
 * IMB - Intelligent Mail Barcode - Onecode - USPS-B-3200
 *
 * Intelligent Mail barcode is a 65-bar code for use on mail in the United States.
 * The fields are described as follows:
 *  * The Barcode Identifier shall be assigned by USPS to encode the presort identification that is currently
 *    printed in human readable form on the optional endorsement line (OEL) as well as for future USPS use.
 *    This shall be two digits, with the second digit in the range of 0–4. The allowable encoding ranges shall be
 *    00–04, 10–14, 20–24, 30–34, 40–44, 50–54, 60–64, 70–74, 80–84, and 90–94.
 *  * The Service Type Identifier shall be assigned by USPS for any combination of services requested on the mailpiece.
 *    The allowable encoding range shall be 000http://it2.php.net/manual/en/function.dechex.php–999.
 *    Each 3-digit value shall correspond to a particular mail class with a particular combination of service(s).
 *    Each service program, such as OneCode Confirm and OneCode ACS, shall provide the list of Service Type Identifier
 *    values.
 *  * The Mailer or Customer Identifier shall be assigned by USPS as a unique, 6 or 9 digit number that identifies
 *    a business entity. The allowable encoding range for the 6 digit Mailer ID shall be 000000- 899999, while the
 *    allowable encoding range for the 9 digit Mailer ID shall be 900000000-999999999. The Serial or
 *    Sequence Number shall be assigned by the mailer for uniquely identifying and tracking mailpieces.
 *    The allowable encoding range shall be 000000000–999999999 when used with a 6 digit Mailer ID and 000000-999999
 *    when used with a 9 digit Mailer ID. e. The Delivery Point ZIP Code shall be assigned by the mailer for routing
 *    the mailpiece. This shall replace POSTNET for routing the mailpiece to its final delivery point.
 *    The length may be 0, 5, 9, or 11 digits. The allowable encoding ranges shall be no ZIP Code, 00000–99999,
 *    000000000–999999999, and 00000000000–99999999999. An hyphen '-' is required before the zip/delivery point.
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class Imb extends \Com\Tecnick\Barcode\Type\Linear
{
    /**
     * Barcode format
     *
     * @var string
     */
    protected $format = 'IMB';

    /**
     * ASC characters
     *
     * @var array
     */
    protected static $asc_chr = array(
        4,0,2,6,3,5,1,9,8,7,
        1,2,0,6,4,8,2,9,5,3,
        0,1,3,7,4,6,8,9,2,0,
        5,1,9,4,3,8,6,7,1,2,
        4,3,9,5,7,8,3,0,2,1,
        4,0,9,1,7,0,2,4,6,3,
        7,1,9,5,8
    );
    
    /**
     * DSC characters
     *
     * @var array
     */
    protected static $dsc_chr = array(
        7,1,9,5,8,0,2,4,6,3,
        5,8,9,7,3,0,6,1,7,4,
        6,8,9,2,5,1,7,5,4,3,
        8,7,6,0,2,5,4,9,3,0,
        1,6,8,2,0,4,5,9,6,7,
        5,2,6,3,8,5,1,9,8,7,
        4,0,2,6,3);
    
    /**
     * ASC positions
     *
     * @var array
     */
    protected static $asc_pos = array(
        3,0,8,11,1,12,8,11,10,6,4,12,2,7,9,6,7,9,2,8,4,0,12,7,10,9,0,7,10,5,7,9,
        6,8,2,12,1,4,2,0,1,5,4,6,12,1,0,9,4,7,5,10,2,6,9,11,2,12,6,7,5,11,0,3,2);
    
    /**
     * DSC positions
     *
     * @var array
     */
    protected static $dsc_pos = array(
        2,10,12,5,9,1,5,4,3,9,11,5,10,1,6,3,4,1,10,0,2,11,8,6,1,12,3,8,6,4,4,11,
        0,6,1,9,11,5,3,7,3,10,7,11,8,2,10,3,5,8,0,3,12,11,8,4,5,1,3,0,7,12,9,8,10);

    /**
     * Reverse unsigned short value
     *
     * @param int $num Value to reversr
     *
     * @return int reversed value
     */
    protected function getReversedUnsignedShort($num)
    {
        $rev = 0;
        for ($pos = 0; $pos < 16; ++$pos) {
            $rev <<= 1;
            $rev |= ($num & 1);
            $num >>= 1;
        }
        return $rev;
    }

    /**
     * Get the Frame Check Sequence
     *
     * @param array $code_arr Array of hexadecimal values (13 bytes holding 102 bits right justified).
     *
     * @return int 11 bit Frame Check Sequence as integer (decimal base)
     */
    protected function getFrameCheckSequence($code_arr)
    {
        $genpoly = 0x0F35; // generator polynomial
        $fcs = 0x07FF; // Frame Check Sequence
        // do most significant byte skipping the 2 most significant bits
        $data = hexdec($code_arr[0]) << 5;
        for ($bit = 2; $bit < 8; ++$bit) {
            if (($fcs ^ $data) & 0x400) {
                $fcs = ($fcs << 1) ^ $genpoly;
            } else {
                $fcs = ($fcs << 1);
            }
            $fcs &= 0x7FF;
            $data <<= 1;
        }
        // do rest of bytes
        for ($byte = 1; $byte < 13; ++$byte) {
            $data = hexdec($code_arr[$byte]) << 3;
            for ($bit = 0; $bit < 8; ++$bit) {
                if (($fcs ^ $data) & 0x400) {
                    $fcs = ($fcs << 1) ^ $genpoly;
                } else {
                    $fcs = ($fcs << 1);
                }
                $fcs &= 0x7FF;
                $data <<= 1;
            }
        }
        return $fcs;
    }

    /**
     * Get the Nof13 tables
     *
     * @param int $type  Table type: 2 for 2of13 table, 5 for 5of13table
     * @param int $size Table size (78 for n=2 and 1287 for n=5)
     *
     * @return array requested table
     */
    protected function getTables($type, $size)
    {
        $table = array();
        $lli = 0; // LUT lower index
        $lui = $size - 1; // LUT upper index
        for ($count = 0; $count < 8192; ++$count) {
            $bit_count = 0;
            for ($bit_index = 0; $bit_index < 13; ++$bit_index) {
                $bit_count += intval(($count & (1 << $bit_index)) != 0);
            }
            // if we don't have the right number of bits on, go on to the next value
            if ($bit_count == $type) {
                $reverse = ($this->getReversedUnsignedShort($count) >> 3);
                // if the reverse is less than count, we have already visited this pair before
                if ($reverse >= $count) {
                    // If count is symmetric, place it at the first free slot from the end of the list.
                    // Otherwise, place it at the first free slot from the beginning of the list AND place
                    // $reverse ath the next free slot from the beginning of the list
                    if ($reverse == $count) {
                        $table[$lui] = $count;
                        --$lui;
                    } else {
                        $table[$lli] = $count;
                        ++$lli;
                        $table[$lli] = $reverse;
                        ++$lli;
                    }
                }
            }
        }
        return $table;
    }

    /**
     * Get the routing code binary block
     *
     * @param string $routing_code the routing code
     *
     * @return string
     *
     * @throws BarcodeException in case of error
     */
    protected function getRoutingCode($routing_code)
    {
        // Conversion of Routing Code
        switch (strlen($routing_code)) {
            case 0:
                return 0;
            case 5:
                return bcadd($routing_code, '1');
            case 9:
                return bcadd($routing_code, '100001');
            case 11:
                return bcadd($routing_code, '1000100001');
        }
        throw new BarcodeException('Invalid routing code');
    }
    
    /**
     * Get the processed array of characters
     *
     * @return array
     *
     * @throws BarcodeException in case of error
     */
    protected function getCharsArray()
    {
        $this->ncols = 0;
        $this->nrows = 3;
        $this->bars = array();
        $code_arr = explode('-', $this->code);
        $tracking_number = $code_arr[0];
        $binary_code = 0;
        if (isset($code_arr[1])) {
            $binary_code = $this->getRoutingCode($code_arr[1]);
        }
        $binary_code = bcmul($binary_code, 10);
        $binary_code = bcadd($binary_code, $tracking_number[0]);
        $binary_code = bcmul($binary_code, 5);
        $binary_code = bcadd($binary_code, $tracking_number[1]);
        $binary_code .= substr($tracking_number, 2, 18);
        // convert to hexadecimal
        $binary_code = $this->convertDecToHex($binary_code);
        // pad to get 13 bytes
        $binary_code = str_pad($binary_code, 26, '0', STR_PAD_LEFT);
        // convert string to array of bytes
        $binary_code_arr = chunk_split($binary_code, 2, "\r");
        $binary_code_arr = substr($binary_code_arr, 0, -1);
        $binary_code_arr = explode("\r", $binary_code_arr);
        // calculate frame check sequence
        $fcs = $this->getFrameCheckSequence($binary_code_arr);
        // exclude first 2 bits from first byte
        $first_byte = sprintf('%2s', dechex((hexdec($binary_code_arr[0]) << 2) >> 2));
        $binary_code_102bit = $first_byte.substr($binary_code, 2);
        // convert binary data to codewords
        $codewords = array();
        $data = $this->convertHexToDec($binary_code_102bit);
        $codewords[0] = bcmod($data, 636) * 2;
        $data = bcdiv($data, 636);
        for ($pos = 1; $pos < 9; ++$pos) {
            $codewords[$pos] = bcmod($data, 1365);
            $data = bcdiv($data, 1365);
        }
        $codewords[9] = $data;
        if (($fcs >> 10) == 1) {
            $codewords[9] += 659;
        }
        // generate lookup tables
        $table2of13 = $this->getTables(2, 78);
        $table5of13 = $this->getTables(5, 1287);
        // convert codewords to characters
        $characters = array();
        $bitmask = 512;
        foreach ($codewords as $val) {
            if ($val <= 1286) {
                $chrcode = $table5of13[$val];
            } else {
                $chrcode = $table2of13[($val - 1287)];
            }
            if (($fcs & $bitmask) > 0) {
                // bitwise invert
                $chrcode = ((~$chrcode) & 8191);
            }
            $characters[] = $chrcode;
            $bitmask /= 2;
        }
        
        return array_reverse($characters);
    }

    /**
     * Get the bars array
     *
     * @throws BarcodeException in case of error
     */
    protected function setBars()
    {
        $chars = $this->getCharsArray();
        for ($pos = 0; $pos < 65; ++$pos) {
            $asc = (($chars[self::$asc_chr[$pos]] & pow(2, self::$asc_pos[$pos])) > 0);
            $dsc = (($chars[self::$dsc_chr[$pos]] & pow(2, self::$dsc_pos[$pos])) > 0);
            if ($asc and $dsc) {
                // full bar (F)
                $this->bars[] = array($this->ncols, 0, 1, 3);
            } elseif ($asc) {
                // ascender (A)
                $this->bars[] = array($this->ncols, 0, 1, 2);
            } elseif ($dsc) {
                // descender (D)
                $this->bars[] = array($this->ncols, 1, 1, 2);
            } else {
                // tracker (T)
                $this->bars[] = array($this->ncols, 1, 1, 1);
            }
            $this->ncols += 2;
        }
        --$this->ncols;
    }
}
