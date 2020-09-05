<?php
/**
 * MaskNum.php
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
 * Com\Tecnick\Barcode\Type\Square\QrCode\MaskNum
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
abstract class MaskNum
{
    /**
     * Make Mask Number
     *
     * @param int  $maskNo Mask number
     * @param int  $width  Width
     * @param int  $frame  Frame
     * @param int  $mask   Mask
     *
     * @return int mask number
     */
    protected function makeMaskNo($maskNo, $width, $frame, &$mask)
    {
        $bnum = 0;
        $bitMask = $this->generateMaskNo($maskNo, $width, $frame);
        $mask = $frame;
        for ($ypos = 0; $ypos < $width; ++$ypos) {
            for ($xpos = 0; $xpos < $width; ++$xpos) {
                if ($bitMask[$ypos][$xpos] == 1) {
                    $mask[$ypos][$xpos] = chr(ord($frame[$ypos][$xpos]) ^ ((int)($bitMask[$ypos][$xpos])));
                }
                $bnum += (int)(ord($mask[$ypos][$xpos]) & 1);
            }
        }
        return $bnum;
    }

    /**
     * Return bit mask
     *
     * @param int   $maskNo Mask number
     * @param int   $width  Width
     * @param array $frame  Frame
     *
     * @return array bit mask
     */
    protected function generateMaskNo($maskNo, $width, $frame)
    {
        $bitMask = array_fill(0, $width, array_fill(0, $width, 0));
        for ($ypos = 0; $ypos < $width; ++$ypos) {
            for ($xpos = 0; $xpos < $width; ++$xpos) {
                if (ord($frame[$ypos][$xpos]) & 0x80) {
                    $bitMask[$ypos][$xpos] = 0;
                } else {
                    $maskFunc = call_user_func(array($this, 'mask'.$maskNo), $xpos, $ypos);
                    $bitMask[$ypos][$xpos] = (($maskFunc == 0) ? 1 : 0);
                }
            }
        }
        return $bitMask;
    }

    /**
     * Mask 0
     *
     * @param int $xpos X position
     * @param int $ypos Y position
     *
     * @return int mask
     */
    protected function mask0($xpos, $ypos)
    {
        return (($xpos + $ypos) & 1);
    }

    /**
     * Mask 1
     *
     * @param int $xpos X position
     * @param int $ypos Y position
     *
     * @return int mask
     */
    protected function mask1($xpos, $ypos)
    {
        $xpos = null;
        return ($ypos & 1);
    }

    /**
     * Mask 2
     *
     * @param int $xpos X position
     * @param int $ypos Y position
     *
     * @return int mask
     */
    protected function mask2($xpos, $ypos)
    {
        $ypos = null;
        return ($xpos % 3);
    }

    /**
     * Mask 3
     *
     * @param int $xpos X position
     * @param int $ypos Y position
     *
     * @return int mask
     */
    protected function mask3($xpos, $ypos)
    {
        return (($xpos + $ypos) % 3);
    }

    /**
     * Mask 4
     *
     * @param int $xpos X position
     * @param int $ypos Y position
     *
     * @return int mask
     */
    protected function mask4($xpos, $ypos)
    {
        return ((((int)($ypos / 2)) + ((int)($xpos / 3))) & 1);
    }

    /**
     * Mask 5
     *
     * @param int $xpos X position
     * @param int $ypos Y position
     *
     * @return int mask
     */
    protected function mask5($xpos, $ypos)
    {
        return ((($xpos * $ypos) & 1) + ($xpos * $ypos) % 3);
    }

    /**
     * Mask 6
     *
     * @param int $xpos X position
     * @param int $ypos Y position
     *
     * @return int mask
     */
    protected function mask6($xpos, $ypos)
    {
        return (((($xpos * $ypos) & 1) + ($xpos * $ypos) % 3) & 1);
    }

    /**
     * Mask 7
     *
     * @param int $xpos X position
     * @param int $ypos Y position
     *
     * @return int mask
     */
    protected function mask7($xpos, $ypos)
    {
        return (((($xpos * $ypos) % 3) + (($xpos + $ypos) & 1)) & 1);
    }
}
