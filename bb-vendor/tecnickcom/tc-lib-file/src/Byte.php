<?php
/**
 * Byte.php
 *
 * @since       2015-07-28
 * @category    Library
 * @package     File
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-file
 *
 * This file is part of tc-lib-file software library.
 */

namespace Com\Tecnick\File;

/**
 * Com\Tecnick\File\Byte
 *
 * Function to read byte-level data
 *
 * @since       2015-07-28
 * @category    Library
 * @package     File
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-file
 */
class Byte
{
    /**
     * String to process
     *
     * @var string
     */
    protected $str = '';

    /**
     * Initialize a new string to be processed
     *
     * @param string $str String from where to extract values
     */
    public function __construct($str)
    {
        $this->str = $str;
    }

    /**
     * Get BYTE from string (8-bit unsigned integer).
     *
     * @param int $offset Point from where to read the data.
     *
     * @return int 8 bit value
     *
     */
    public function getByte($offset)
    {
        $val = unpack('Ci', substr($this->str, $offset, 1));
        return $val['i'];
    }

    /**
     * Get ULONG from string (Big Endian 32-bit unsigned integer).
     *
     * @param int $offset Point from where to read the data
     *
     * @return int 32 bit value
     */
    public function getULong($offset)
    {
        $val = unpack('Ni', substr($this->str, $offset, 4));
        return $val['i'];
    }

    /**
     * Get USHORT from string (Big Endian 16-bit unsigned integer).
     *
     * @param int $offset Point from where to read the data
     *
     * @return int 16 bit value
     */
    public function getUShort($offset)
    {
        $val = unpack('ni', substr($this->str, $offset, 2));
        return $val['i'];
    }

    /**
     * Get SHORT from string (Big Endian 16-bit signed integer).
     *
     * @param int $offset Point from where to read the data.
     *
     * @return int 16 bit value
     */
    public function getShort($offset)
    {
        $val = unpack('si', substr($this->str, $offset, 2));
        return $val['i'];
    }

    /**
     * Get UFWORD from string (Big Endian 16-bit unsigned integer).
     *
     * @param int $offset Point from where to read the data.
     *
     * @return int 16 bit value
     */
    public function getUFWord($offset)
    {
        return $this->getUShort($offset);
    }

    /**
     * Get FWORD from string (Big Endian 16-bit signed integer).
     *
     * @param int $offset Point from where to read the data.
     *
     * @return int 16 bit value
     */
    public function getFWord($offset)
    {
        $val = $this->getUShort($offset);
        if ($val > 0x7fff) {
            $val -= 0x10000;
        }
        return $val;
    }

    /**
     * Get FIXED from string (32-bit signed fixed-point number (16.16).
     *
     * @param int $offset Point from where to read the data.
     *
     * @return int 16 bit value
     *
     */
    public function getFixed($offset)
    {
        // mantissa.fraction
        return (float)($this->getFWord($offset).'.'.$this->getUShort($offset + 2));
    }
}
