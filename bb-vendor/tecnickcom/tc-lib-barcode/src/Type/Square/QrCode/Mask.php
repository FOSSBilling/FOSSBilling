<?php
/**
 * Mask.php
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
use \Com\Tecnick\Barcode\Type\Square\QrCode\Spec;

/**
 * Com\Tecnick\Barcode\Type\Square\QrCode\Mask
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
abstract class Mask extends \Com\Tecnick\Barcode\Type\Square\QrCode\MaskNum
{
    /**
     * If false, checks all masks available,
     * otherwise the value indicates the number of masks to be checked, mask id are random
     *
     * @var int
     */
    protected $qr_find_from_random = false;

    /**
     * If true, estimates best mask (spec. default, but extremally slow;
     * set to false to significant performance boost but (propably) worst quality code
     *
     * @var bool
     */
    protected $qr_find_best_mask = true;

    /**
     * Default mask used when $this->qr_find_best_mask === false
     *
     * @var int
     */
    protected $qr_default_mask = 2;

    /**
     * Run length
     *
     * @var array
     */
    protected $runLength = array();

    /**
     * QR code version.
     * The Size of QRcode is defined as version. Version is an integer value from 1 to 40.
     * Version 1 is 21*21 matrix. And 4 modules increases whenever 1 version increases.
     * So version 40 is 177*177 matrix.
     *
     * @var int
     */
    public $version = 0;

    /**
     * Error correction level
     *
     * @var int
     */
    protected $level = 0;

    /**
     * Spec class object
     *
     * @var \Com\Tecnick\Barcode\Type\Square\QrCode\Spec
     */
    protected $spc;

    /**
     * Initialize
     *
     * @param int  $version       Code version
     * @param int  $level         Error Correction Level
     * @param bool $random_mask   If false, checks all masks available,
     *                            otherwise the value indicates the number of masks to be checked, mask id are random
     * @param bool $best_mask     If true, estimates best mask (slow)
     * @param int  $default_mask  Default mask used when $fbm is false
     */
    public function __construct($version, $level, $random_mask = false, $best_mask = true, $default_mask = 2)
    {
        $this->version = $version;
        $this->level = $level;
        $this->qr_find_from_random = (bool)$random_mask;
        $this->qr_find_best_mask = (bool)$best_mask;
        $this->qr_default_mask = intval($default_mask);
        $this->spc = new Spec;
    }

    /**
     * Get the best mask
     *
     * @param int   $width Width
     * @param array $frame Frame
     * @param int   $level Error Correction lLevel
     *
     * @return array best mask
     */
    protected function mask($width, $frame, $level)
    {
        $minDemerit = PHP_INT_MAX;
        $bestMaskNum = 0;
        $bestMask = array();
        $checked_masks = array(0, 1, 2, 3, 4, 5, 6, 7);
        if ($this->qr_find_from_random !== false) {
            $howManuOut = (8 - ($this->qr_find_from_random % 9));
            for ($idx = 0; $idx <  $howManuOut; ++$idx) {
                $remPos = rand(0, (count($checked_masks) - 1));
                unset($checked_masks[$remPos]);
                $checked_masks = array_values($checked_masks);
            }
        }
        $bestMask = $frame;
        foreach ($checked_masks as $idx) {
            $mask = array_fill(0, $width, str_repeat("\0", $width));
            $demerit = 0;
            $blacks = 0;
            $blacks  = $this->makeMaskNo($idx, $width, $frame, $mask);
            $blacks += $this->writeFormatInformation($width, $mask, $idx, $level);
            $blacks  = (int)(100 * $blacks / ($width * $width));
            $demerit = (int)((int)(abs($blacks - 50) / 5) * Data::N4);
            $demerit += $this->evaluateSymbol($width, $mask);
            if ($demerit < $minDemerit) {
                $minDemerit = $demerit;
                $bestMask = $mask;
                $bestMaskNum = $idx;
            }
        }
        return $bestMask;
    }

    /**
     * Make a mask
     *
     * @param int   $width  Mask width
     * @param array $frame  Frame
     * @param int   $maskNo Mask number
     * @param int   $level  Error Correction level
     *
     * @return array mask
     */
    protected function makeMask($width, $frame, $maskNo, $level)
    {
        $this->makeMaskNo($maskNo, $width, $frame, $mask);
        $this->writeFormatInformation($width, $mask, $maskNo, $level);
        return $mask;
    }

    /**
     * Write Format Information on the frame and returns the number of black bits
     *
     * @param int   $width  Mask width
     * @param array $frame  Frame
     * @param int   $maskNo Mask number
     * @param int   $level  Error Correction level
     *
     * @return int blacks
     */
    protected function writeFormatInformation($width, &$frame, $maskNo, $level)
    {
        $blacks = 0;
        $spec = new Spec;
        $format =  $spec->getFormatInfo($maskNo, $level);
        for ($idx = 0; $idx < 8; ++$idx) {
            if ($format & 1) {
                $blacks += 2;
                $val = 0x85;
            } else {
                $val = 0x84;
            }
            $frame[8][($width - 1 - $idx)] = chr($val);
            if ($idx < 6) {
                $frame[$idx][8] = chr($val);
            } else {
                $frame[($idx + 1)][8] = chr($val);
            }
            $format = $format >> 1;
        }
        for ($idx = 0; $idx < 7; ++$idx) {
            if ($format & 1) {
                $blacks += 2;
                $val = 0x85;
            } else {
                $val = 0x84;
            }
            $frame[($width - 7 + $idx)][8] = chr($val);
            if ($idx == 0) {
                $frame[8][7] = chr($val);
            } else {
                $frame[8][(6 - $idx)] = chr($val);
            }
            $format = $format >> 1;
        }
        return $blacks;
    }

    /**
     * Evaluate Symbol
     *
     * @param int   $width Width
     * @param array $frame Frame
     *
     * @return int demerit
     */
    protected function evaluateSymbol($width, $frame)
    {
        for ($ypos = 0; $ypos < $width; ++$ypos) {
            $frameY = $frame[$ypos];
            if ($ypos > 0) {
                $frameYM = $frame[($ypos - 1)];
            } else {
                $frameYM = $frameY;
            }
            $demerit = $this->evaluateSymbolB($ypos, $width, $frameY, $frameYM);
        }
        for ($xpos = 0; $xpos < $width; ++$xpos) {
            $head = 0;
            $this->runLength[0] = 1;
            for ($ypos = 0; $ypos < $width; ++$ypos) {
                if (($ypos == 0) && (ord($frame[$ypos][$xpos]) & 1)) {
                    $this->runLength[0] = -1;
                    $head = 1;
                    $this->runLength[$head] = 1;
                } elseif ($ypos > 0) {
                    if ((ord($frame[$ypos][$xpos]) ^ ord($frame[($ypos - 1)][$xpos])) & 1) {
                        $head++;
                        $this->runLength[$head] = 1;
                    } else {
                        $this->runLength[$head]++;
                    }
                }
            }
            $demerit += $this->calcN1N3($head + 1);
        }
        return $demerit;
    }

    /**
     * Evaluate Symbol
     *
     * @param int   $ypos   Y position
     * @param int   $width  Width
     * @param array $frameY
     * @param array $frameYM
     *
     * @return int demerit
     */
    protected function evaluateSymbolB($ypos, $width, $frameY, $frameYM)
    {
        $head = 0;
        $demerit = 0;
        $this->runLength[0] = 1;
        for ($xpos = 0; $xpos < $width; ++$xpos) {
            if (($xpos > 0) && ($ypos > 0)) {
                $b22 = ord($frameY[$xpos])
                    & ord($frameY[($xpos - 1)])
                    & ord($frameYM[$xpos])
                    & ord($frameYM[($xpos - 1)]);
                $w22 = ord($frameY[$xpos])
                    | ord($frameY[($xpos - 1)])
                    | ord($frameYM[$xpos])
                    | ord($frameYM[($xpos - 1)]);
                if (($b22 | ($w22 ^ 1)) & 1) {
                    $demerit += Data::N2;
                }
            }
            if (($xpos == 0) && (ord($frameY[$xpos]) & 1)) {
                $this->runLength[0] = -1;
                $head = 1;
                $this->runLength[$head] = 1;
            } elseif ($xpos > 0) {
                if ((ord($frameY[$xpos]) ^ ord($frameY[($xpos - 1)])) & 1) {
                    $head++;
                    $this->runLength[$head] = 1;
                } else {
                    ++$this->runLength[$head];
                }
            }
        }
        return ($demerit + $this->calcN1N3($head + 1));
    }

    /**
     * Calc N1 N3
     *
     * @param int $length
     *
     * @return int demerit
     */
    protected function calcN1N3($length)
    {
        $demerit = 0;
        for ($idx = 0; $idx < $length; ++$idx) {
            if ($this->runLength[$idx] >= 5) {
                $demerit += (Data::N1 + ($this->runLength[$idx] - 5));
            }
            if (($idx & 1) && ($idx >= 3) && ($idx < ($length - 2)) && ($this->runLength[$idx] % 3 == 0)) {
                $demerit += $this->calcN1N3delta($length, $idx);
            }
        }
        return $demerit;
    }

    /**
     * Calc N1 N3 delta
     *
     * @param int $length
     * @param int $idx
     *
     * @return int demerit delta
     */
    protected function calcN1N3delta($length, $idx)
    {
        $fact = (int)($this->runLength[$idx] / 3);
        if (($this->runLength[($idx - 2)] == $fact)
            && ($this->runLength[($idx - 1)] == $fact)
            && ($this->runLength[($idx + 1)] == $fact)
            && ($this->runLength[($idx + 2)] == $fact)) {
            if (($this->runLength[($idx - 3)] < 0) || ($this->runLength[($idx - 3)] >= (4 * $fact))) {
                return Data::N3;
            } elseif ((($idx + 3) >= $length) || ($this->runLength[($idx + 3)] >= (4 * $fact))) {
                return Data::N3;
            }
        }
        return 0;
    }
}
