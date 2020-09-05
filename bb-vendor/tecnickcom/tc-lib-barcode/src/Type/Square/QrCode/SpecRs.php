<?php
/**
 * SpecRs.php
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
 * Com\Tecnick\Barcode\Type\Square\QrCode\SpecRs
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
abstract class SpecRs
{
    /**
     * Return block number 0
     *
     * @param array $spec
     *
     * @return int value
     */
    public function rsBlockNum($spec)
    {
        return ($spec[0] + $spec[3]);
    }

    /**
     * Return block number 1
     *
     * @param array $spec
     *
     * @return int value
     */
    public function rsBlockNum1($spec)
    {
        return $spec[0];
    }

    /**
     * Return data codes 1
     *
     * @param array $spec
     *
     * @return int value
     */
    public function rsDataCodes1($spec)
    {
        return $spec[1];
    }

    /**
     * Return ecc codes 1
     *
     * @param array $spec
     *
     * @return int value
     */
    public function rsEccCodes1($spec)
    {
        return $spec[2];
    }

    /**
     * Return block number 2
     *
     * @param array $spec
     *
     * @return int value
     */
    public function rsBlockNum2($spec)
    {
        return $spec[3];
    }

    /**
     * Return data codes 2
     *
     * @param array $spec
     *
     * @return int value
     */
    public function rsDataCodes2($spec)
    {
        return $spec[4];
    }

    /**
     * Return ecc codes 2
     *
     * @param array $spec
     *
     * @return int value
     */
    public function rsEccCodes2($spec)
    {
        return $spec[2];
    }

    /**
     * Return data length
     *
     * @param array $spec
     *
     * @return int value
     */
    public function rsDataLength($spec)
    {
        return ($spec[0] * $spec[1]) + ($spec[3] * $spec[4]);
    }

    /**
     * Return ecc length
     *
     * @param array $spec
     *
     * @return int value
     */
    public function rsEccLength($spec)
    {
        return ($spec[0] + $spec[3]) * $spec[2];
    }

    /**
     * Return a copy of initialized frame.
     *
     * @param int $version Version
     *
     * @return Array of unsigned char.
     */
    public function createFrame($version)
    {
        $width = Data::$capacity[$version][Data::QRCAP_WIDTH];
        $frameLine = str_repeat("\0", $width);
        $frame = array_fill(0, $width, $frameLine);
        // Finder pattern
        $frame = $this->putFinderPattern($frame, 0, 0);
        $frame = $this->putFinderPattern($frame, $width - 7, 0);
        $frame = $this->putFinderPattern($frame, 0, $width - 7);
        // Separator
        $yOffset = $width - 7;
        for ($ypos = 0; $ypos < 7; ++$ypos) {
            $frame[$ypos][7] = "\xc0";
            $frame[$ypos][$width - 8] = "\xc0";
            $frame[$yOffset][7] = "\xc0";
            ++$yOffset;
        }
        $setPattern = str_repeat("\xc0", 8);
        $frame = $this->qrstrset($frame, 0, 7, $setPattern);
        $frame = $this->qrstrset($frame, $width-8, 7, $setPattern);
        $frame = $this->qrstrset($frame, 0, $width - 8, $setPattern);
        // Format info
        $setPattern = str_repeat("\x84", 9);
        $frame = $this->qrstrset($frame, 0, 8, $setPattern);
        $frame = $this->qrstrset($frame, $width - 8, 8, $setPattern, 8);
        $yOffset = $width - 8;
        for ($ypos = 0; $ypos < 8; ++$ypos, ++$yOffset) {
            $frame[$ypos][8] = "\x84";
            $frame[$yOffset][8] = "\x84";
        }
        // Timing pattern
        $wdo = $width - 15;
        for ($idx = 1; $idx < $wdo; ++$idx) {
            $frame[6][(7 + $idx)] = chr(0x90 | ($idx & 1));
            $frame[(7 + $idx)][6] = chr(0x90 | ($idx & 1));
        }
        // Alignment pattern
        $frame = $this->putAlignmentPattern($version, $frame, $width);
        // Version information
        if ($version >= 7) {
            $vinf = $this->getVersionPattern($version);
            $val = $vinf;
            for ($xpos = 0; $xpos < 6; ++$xpos) {
                for ($ypos = 0; $ypos < 3; ++$ypos) {
                    $frame[(($width - 11) + $ypos)][$xpos] = chr(0x88 | ($val & 1));
                    $val = $val >> 1;
                }
            }
            $val = $vinf;
            for ($ypos = 0; $ypos < 6; ++$ypos) {
                for ($xpos = 0; $xpos < 3; ++$xpos) {
                    $frame[$ypos][($xpos + ($width - 11))] = chr(0x88 | ($val & 1));
                    $val = $val >> 1;
                }
            }
        }
        // and a little bit...
        $frame[$width - 8][8] = "\x81";
        return $frame;
    }
}
