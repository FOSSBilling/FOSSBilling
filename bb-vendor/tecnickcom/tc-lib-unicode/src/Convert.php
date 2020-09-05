<?php
/**
 * Convert.php
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

namespace Com\Tecnick\Unicode;

use \Com\Tecnick\Unicode\Data\Latin as Latin;

/**
 * Com\Tecnick\Unicode\Convert
 *
 * @since       2015-07-13
 * @category    Library
 * @package     Unicode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-unicode
 */
class Convert extends \Com\Tecnick\Unicode\Convert\Encoding
{
    /**
     * Returns the unicode string containing the character specified by value
     *
     * @param int $ord Unicode character value to convert
     *
     * @return string Returns the unicode string
     */
    public function chr($ord)
    {
        return mb_convert_encoding(pack('N', $ord), 'UTF-8', 'UCS-4BE');
    }

    /**
     * Returns the unicode value of the specified character
     *
     * @param string $chr Unicode character
     *
     * @return string Returns the unicode value
     */
    public function ord($chr)
    {
        list(, $uni) = unpack('N', mb_convert_encoding($chr, 'UCS-4BE', 'UTF-8'));
        return $uni;
    }

    /**
     * Converts an UTF-8 string to an array of UTF-8 codepoints (integer values)
     *
     * @param string $str String to convert
     *
     * @return array
     */
    public function strToChrArr($str)
    {
        return preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Converts an array of UTF-8 chars to an array of codepoints (integer values)
     *
     * @param array $chars Array of UTF-8 chars
     *
     * @return array
     */
    public function chrArrToOrdArr(array $chars)
    {
        return array_map(array($this, 'ord'), $chars);
    }

    /**
     * Converts an array of UTF-8 code points array of chars
     *
     * @param array $ords Array of UTF-8 code points
     *
     * @return array
     */
    public function ordArrToChrArr(array $ords)
    {
        return array_map(array($this, 'chr'), $ords);
    }

    /**
     * Converts an UTF-8 string to an array of UTF-8 codepoints (integer values)
     *
     * @param string $str Convert to convert
     *
     * @return array
     */
    public function strToOrdArr($str)
    {
        return $this->chrArrToOrdArr($this->strToChrArr($str));
    }

    /**
     * Extract a slice of the $uniarr array and return it as string
     *
     * @param array $uniarr  The input array of characters
     * @param int   $start   The position of the starting element
     * @param int   $end     The position of the first element that will not be returned.
     *
     * @return string
     */
    public function getSubUniArrStr(array $uniarr, $start = 0, $end = null)
    {
        if ($end === null) {
            $end = count($uniarr);
        }
        return implode(array_slice($uniarr, $start, ($end - $start)));
    }
}
