<?php
/**
 * Encoding.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     Unicode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-unicode
 *
 * This file is part of tc-lib-unicode software library.
 */

namespace Com\Tecnick\Unicode\Convert;

use \Com\Tecnick\Unicode\Data\Latin as Latin;

/**
 * Com\Tecnick\Unicode\Convert\Encoding
 *
 * @since       2015-07-13
 * @category    Library
 * @package     Unicode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-unicode
 */
class Encoding
{
    /**
     * Converts UTF-8 code array to Latin1 codes
     *
     * @param array $ordarr Array containing UTF-8 code points
     *
     * @return array
     */
    public function uniArrToLatinArr(array $ordarr)
    {
        $latarr = array();
        foreach ($ordarr as $chr) {
            if ($chr < 256) {
                $latarr[] = $chr;
            } elseif (array_key_exists($chr, Latin::$substitute)) {
                $latarr[] = Latin::$substitute[$chr];
            } elseif ($chr !== 0xFFFD) {
                $latarr[] = 63; // '?' character
            }
        }
        return $latarr;
    }

    /**
     * Converts an array of Latin1 code points to a string
     *
     * @param array $latarr Array of Latin1 code points
     *
     * @return string
     */
    public function latinArrToStr(array $latarr)
    {
        return implode(array_map('chr', $latarr));
    }

    /**
     * Convert a string to an hexadecimal string (byte string) representation (as in the PDF standard)
     *
     * @param string $str  String to convert
     *
     * @return string
     */
    public function strToHex($str)
    {
        $hexstr = '';
        $len = strlen($str);
        for ($idx = 0; $idx < $len; ++$idx) {
            $hexstr .= sprintf('%02s', dechex(ord($str[$idx])));
        }
        return $hexstr;
    }

    /**
     * Convert an hexadecimal string (byte string - as in the PDF standard) to string
     *
     * @param $bs (string) byte string to convert
     *
     * @return string
     */
    public function hexToStr($hex)
    {
        if (strlen($hex) == 0) {
            return '';
        }
        $str = '';
        $bytes = str_split($hex, 2);
        foreach ($bytes as $chr) {
            $str .= chr(hexdec($chr));
        }
        return $str;
    }

    /**
     * Converts a string with an unknown encoding to UTF-8
     *
     * @param string $str String to convert
     * @param mixed  $enc Array or comma separated list string of encodings
     *
     * @return string UTF-8 encoded string
     */
    public function toUTF8($str, $enc = null)
    {
        if ($enc === null) {
            $enc = mb_detect_order();
        }
        return mb_convert_encoding($str, 'UTF-8', mb_detect_encoding($str, $enc));
    }

    /**
     * Converts an UTF-8 string to UTF-16BE
     *
     * @param string $str UTF-8 String to convert
     *
     * @return string UTF-16BE encoded string
     */
    public function toUTF16BE($str)
    {
        return mb_convert_encoding($str, 'UTF-16BE', 'UTF-8');
    }
}
