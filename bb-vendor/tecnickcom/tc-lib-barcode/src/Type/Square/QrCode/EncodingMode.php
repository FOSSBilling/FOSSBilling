<?php
/**
 * EncodingMode.php
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

namespace Com\Tecnick\Barcode\Type\Square\QrCode;

use \Com\Tecnick\Barcode\Exception as BarcodeException;
use \Com\Tecnick\Barcode\Type\Square\QrCode\Data;

/**
 * Com\Tecnick\Barcode\Type\Square\QrCode\EncodingMode
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
abstract class EncodingMode extends \Com\Tecnick\Barcode\Type\Square\QrCode\InputItem
{
    /**
     * Get the encoding mode to use
     *
     * @param string $data Data
     * @param int    $pos  Position
     *
     * @return int mode
     */
    public function getEncodingMode($data, $pos)
    {
        if (!isset($data[$pos])) {
            return Data::$encodingModes['NL'];
        }
        if ($this->isDigitAt($data, $pos)) {
            return Data::$encodingModes['NM'];
        }
        if ($this->isAlphanumericAt($data, $pos)) {
            return Data::$encodingModes['AN'];
        }
        return $this->getEncodingModeKj($data, $pos);
    }

    /**
     * Get the encoding mode for KJ or 8B
     *
     * @param string $data Data
     * @param int    $pos  Position
     *
     * @return int mode
     */
    protected function getEncodingModeKj($data, $pos)
    {
        if (($this->hint == Data::$encodingModes['KJ']) && isset($data[($pos + 1)])) {
            $word = ((ord($data[$pos]) << 8) | ord($data[($pos + 1)]));
            if ((($word >= 0x8140) && ($word <= 0x9ffc)) || (($word >= 0xe040) && ($word <= 0xebbf))) {
                return Data::$encodingModes['KJ'];
            }
        }
        return Data::$encodingModes['8B'];
    }

    /**
     * Return true if the character at specified position is a number
     *
     * @param string $str Data
     * @param int    $pos Character position
     *
     * @return boolean
     */
    public function isDigitAt($str, $pos)
    {
        if (!isset($str[$pos])) {
            return false;
        }
        return ((ord($str[$pos]) >= ord('0')) && (ord($str[$pos]) <= ord('9')));
    }

    /**
     * Return true if the character at specified position is an alphanumeric character
     *
     * @param string $str Data
     * @param int    $pos Character position
     *
     * @return boolean
     */
    public function isAlphanumericAt($str, $pos)
    {
        if (!isset($str[$pos])) {
            return false;
        }
        return ($this->lookAnTable(ord($str[$pos])) >= 0);
    }

    /**
     * Look up the alphabet-numeric conversion table (see JIS X0510:2004, pp.19)
     *
     * @param int $chr Character value
     *
     * @return value
     */
    public function lookAnTable($chr)
    {
        return (($chr > 127) ? -1 : Data::$anTable[$chr]);
    }

    /**
     * Return the size of length indicator for the mode and version
     *
     * @param int $mode Encoding mode
     * @param int $version Version
     *
     * @return int the size of the appropriate length indicator (bits).
     */
    public function getLengthIndicator($mode)
    {
        if ($mode == Data::$encodingModes['ST']) {
            return 0;
        }
        if ($this->version <= 9) {
            $len = 0;
        } elseif ($this->version <= 26) {
            $len = 1;
        } else {
            $len = 2;
        }
        return Data::$lengthTableBits[$mode][$len];
    }

    /**
     * Append one bitstream to another
     *
     * @param array $bitstream Original bitstream
     * @param array $append    Bitstream to append
     *
     * @return array bitstream
     */
    protected function appendBitstream($bitstream, $append)
    {
        if ((!is_array($append)) || (count($append) == 0)) {
            return $bitstream;
        }
        if (count($bitstream) == 0) {
            return $append;
        }
        return array_values(array_merge($bitstream, $append));
    }

    /**
     * Append one bitstream created from number to another
     *
     * @param array $bitstream Original bitstream
     * @param int   $bits      Number of bits
     * @param int   $num       Number
     *
     * @return array bitstream
     */
    protected function appendNum($bitstream, $bits, $num)
    {
        if ($bits == 0) {
            return 0;
        }
        return $this->appendBitstream($bitstream, $this->newFromNum($bits, $num));
    }

    /**
     * Append one bitstream created from bytes to another
     *
     * @param array $bitstream Original bitstream
     * @param int   $size      Size
     * @param array $data      Bytes
     *
     * @return array bitstream
     */
    protected function appendBytes($bitstream, $size, $data)
    {
        if ($size == 0) {
            return 0;
        }
        return $this->appendBitstream($bitstream, $this->newFromBytes($size, $data));
    }

    /**
     * Return new bitstream from number
     *
     * @param int $bits Number of bits
     * @param int $num  Number
     *
     * @return array bitstream
     */
    protected function newFromNum($bits, $num)
    {
        $bstream = $this->allocate($bits);
        $mask = 1 << ($bits - 1);
        for ($idx = 0; $idx < $bits; ++$idx) {
            if ($num & $mask) {
                $bstream[$idx] = 1;
            } else {
                $bstream[$idx] = 0;
            }
            $mask = $mask >> 1;
        }
        return $bstream;
    }

    /**
     * Return new bitstream from bytes
     *
     * @param int   $size Size
     * @param array $data Bytes
     *
     * @return array bitstream
     */
    protected function newFromBytes($size, $data)
    {
        $bstream = $this->allocate($size * 8);
        $pval = 0;
        for ($idx = 0; $idx < $size; ++$idx) {
            $mask = 0x80;
            for ($jdx = 0; $jdx < 8; ++$jdx) {
                if ($data[$idx] & $mask) {
                    $bstream[$pval] = 1;
                } else {
                    $bstream[$pval] = 0;
                }
                $pval++;
                $mask = $mask >> 1;
            }
        }
        return $bstream;
    }

    /**
     * Return an array with zeros
     *
     * @param int $setLength Array size
     *
     * @return array
     */
    protected function allocate($setLength)
    {
        return array_fill(0, $setLength, 0);
    }
}
