<?php
/**
 * Spec.php
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
 * Com\Tecnick\Barcode\Type\Square\QrCode\Spec
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class Spec extends \Com\Tecnick\Barcode\Type\Square\QrCode\SpecRs
{
    /**
     * Replace a value on the array at the specified position
     *
     * @param array $srctab
     * @param int    $xpos       X position
     * @param int    $ypos       Y position
     * @param string $repl    Value to replace
     * @param int    $replLen Length of the repl string
     *
     * @return array srctab
     */
    public function qrstrset($srctab, $xpos, $ypos, $repl, $replLen = false)
    {
        $srctab[$ypos] = substr_replace(
            $srctab[$ypos],
            ($replLen !== false) ? substr($repl, 0, $replLen) : $repl,
            $xpos,
            ($replLen !== false) ? $replLen : strlen($repl)
        );
        return $srctab;
    }

    /**
     * Return maximum data code length (bytes) for the version.
     *
     * @param int $version Version
     * @param int $level   Error correction level
     *
     * @return int maximum size (bytes)
     */
    public function getDataLength($version, $level)
    {
        return (Data::$capacity[$version][Data::QRCAP_WORDS] - Data::$capacity[$version][Data::QRCAP_EC][$level]);
    }

    /**
     * Return maximum error correction code length (bytes) for the version.
     *
     * @param int $version Version
     * @param int $level   Error correction level
     *
     * @return int ECC size (bytes)
     */
    public function getECCLength($version, $level)
    {
        return Data::$capacity[$version][Data::QRCAP_EC][$level];
    }

    /**
     * Return the width of the symbol for the version.
     *
     * @param int $version Version
     *
     * @return int width
     */
    public function getWidth($version)
    {
        return Data::$capacity[$version][Data::QRCAP_WIDTH];
    }

    /**
     * Return the numer of remainder bits.
     *
     * @param int $version Version
     *
     * @return int number of remainder bits
     */
    public function getRemainder($version)
    {
        return Data::$capacity[$version][Data::QRCAP_REMINDER];
    }

    /**
     * Return the maximum length for the mode and version.
     *
     * @param int $mode    Encoding mode
     * @param int $version Version
     *
     * @return int the maximum length (bytes)
     */
    public function maximumWords($mode, $version)
    {
        if ($mode == Data::$encodingModes['ST']) {
            return 3;
        }
        if ($version <= 9) {
            $lval = 0;
        } elseif ($version <= 26) {
            $lval = 1;
        } else {
            $lval = 2;
        }
        $bits = Data::$lengthTableBits[$mode][$lval];
        $words = (1 << $bits) - 1;
        if ($mode == Data::$encodingModes['KJ']) {
            $words *= 2; // the number of bytes is required
        }
        return $words;
    }

    /**
     * Return an array of ECC specification.
     *
     * @param int   $version Version
     * @param int   $level   Error correction level
     * @param array $spec    Array of ECC specification contains as following:
     *                       {# of type1 blocks, # of data code, # of ecc code, # of type2 blocks, # of data code}
     *
     * @return array spec
     */
    public function getEccSpec($version, $level, $spec)
    {
        if (count($spec) < 5) {
            $spec = array(0, 0, 0, 0, 0);
        }
        $bv1 = Data::$eccTable[$version][$level][0];
        $bv2 = Data::$eccTable[$version][$level][1];
        $data = $this->getDataLength($version, $level);
        $ecc = $this->getECCLength($version, $level);
        if ($bv2 == 0) {
            $spec[0] = $bv1;
            $spec[1] = (int)($data / $bv1);
            $spec[2] = (int)($ecc / $bv1);
            $spec[3] = 0;
            $spec[4] = 0;
        } else {
            $spec[0] = $bv1;
            $spec[1] = (int)($data / ($bv1 + $bv2));
            $spec[2] = (int)($ecc  / ($bv1 + $bv2));
            $spec[3] = $bv2;
            $spec[4] = $spec[1] + 1;
        }
        return $spec;
    }

    /**
     * Put an alignment marker.
     *
     * @param array $frame Frame
     * @param int   $pox   X center coordinate of the pattern
     * @param int   $poy   Y center coordinate of the pattern
     *
     * @return array frame
     */
    public function putAlignmentMarker($frame, $pox, $poy)
    {
        $finder = array(
            "\xa1\xa1\xa1\xa1\xa1",
            "\xa1\xa0\xa0\xa0\xa1",
            "\xa1\xa0\xa1\xa0\xa1",
            "\xa1\xa0\xa0\xa0\xa1",
            "\xa1\xa1\xa1\xa1\xa1"
        );
        $yStart = $poy - 2;
        $xStart = $pox - 2;
        for ($ydx = 0; $ydx < 5; ++$ydx) {
            $frame = $this->qrstrset($frame, $xStart, ($yStart + $ydx), $finder[$ydx]);
        }
        return $frame;
    }

    /**
     * Put an alignment pattern.
     *
     * @param int   $version Version
     * @param array $frame   Frame
     * @param int   $width   Width
     *
     * @return array frame
     */
    public function putAlignmentPattern($version, $frame, $width)
    {
        if ($version < 2) {
            return $frame;
        }
        $dval = Data::$alignmentPattern[$version][1] - Data::$alignmentPattern[$version][0];
        if ($dval < 0) {
            $wdt = 2;
        } else {
            $wdt = (int)(($width - Data::$alignmentPattern[$version][0]) / $dval + 2);
        }
        if ($wdt * $wdt - 3 == 1) {
            $psx = Data::$alignmentPattern[$version][0];
            $psy = Data::$alignmentPattern[$version][0];
            $frame = $this->putAlignmentMarker($frame, $psx, $psy);
            return $frame;
        }
        $cpx = Data::$alignmentPattern[$version][0];
        $wdo = $wdt - 1;
        for ($xpos = 1; $xpos < $wdo; ++$xpos) {
            $frame = $this->putAlignmentMarker($frame, 6, $cpx);
            $frame = $this->putAlignmentMarker($frame, $cpx, 6);
            $cpx += $dval;
        }
        $cpy = Data::$alignmentPattern[$version][0];
        for ($y=0; $y < $wdo; ++$y) {
            $cpx = Data::$alignmentPattern[$version][0];
            for ($xpos = 0; $xpos < $wdo; ++$xpos) {
                $frame = $this->putAlignmentMarker($frame, $cpx, $cpy);
                $cpx += $dval;
            }
            $cpy += $dval;
        }
        return $frame;
    }

    /**
     * Return BCH encoded version information pattern that is used for the symbol of version 7 or greater.
     * Use lower 18 bits.
     *
     * @param int $version Version
     *
     * @return BCH encoded version information pattern
     */
    public function getVersionPattern($version)
    {
        if (($version < 7) || ($version > Data::QRSPEC_VERSION_MAX)) {
            return 0;
        }
        return Data::$versionPattern[($version - 7)];
    }

    /**
     * Return BCH encoded format information pattern.
     *
     * @param array $maskNo Mask number
     * @param int   $level  Error correction level
     *
     * @return BCH encoded format information pattern
     */
    public function getFormatInfo($maskNo, $level)
    {
        if (($maskNo < 0)
            || ($maskNo > 7)
            || ($level < 0)
            || ($level > 3)
        ) {
            return 0;
        }
        return Data::$formatInfo[$level][$maskNo];
    }

    /**
     * Put a finder pattern.
     *
     * @param array $frame Frame
     * @param int   $pox   X center coordinate of the pattern
     * @param int   $poy   Y center coordinate of the pattern
     *
     * @return array frame
     */
    public function putFinderPattern($frame, $pox, $poy)
    {
        $finder = array(
            "\xc1\xc1\xc1\xc1\xc1\xc1\xc1",
            "\xc1\xc0\xc0\xc0\xc0\xc0\xc1",
            "\xc1\xc0\xc1\xc1\xc1\xc0\xc1",
            "\xc1\xc0\xc1\xc1\xc1\xc0\xc1",
            "\xc1\xc0\xc1\xc1\xc1\xc0\xc1",
            "\xc1\xc0\xc0\xc0\xc0\xc0\xc1",
            "\xc1\xc1\xc1\xc1\xc1\xc1\xc1"
        );
        for ($ypos = 0; $ypos < 7; ++$ypos) {
            $frame = $this->qrstrset($frame, $pox, ($poy + $ypos), $finder[$ypos]);
        }
        return $frame;
    }
}
