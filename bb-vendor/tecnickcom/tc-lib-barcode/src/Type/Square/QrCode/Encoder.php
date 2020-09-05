<?php
/**
 * Encoder.php
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
 * Com\Tecnick\Barcode\Type\Square\QrCode\Encoder
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class Encoder extends \Com\Tecnick\Barcode\Type\Square\QrCode\Init
{
    /**
     * Data code
     *
     * @var array
     */
    protected $datacode = array();

    /**
     * Error correction code
     *
     * @var array
     */
    protected $ecccode = array();

    /**
     * Blocks
     *
     * @var array
     */
    protected $blocks;

    /**
     * Reed-Solomon blocks
     *
     * @var array
     */
    protected $rsblocks = array(); //of RSblock

    /**
     * Counter
     *
     * @var int
     */
    protected $count;

    /**
     * Data length
     *
     * @var int
     */
    protected $dataLength;

    /**
     * Error correction length
     *
     * @var int
     */
    protected $eccLength;

    /**
     * Value bv1
     *
     * @var int
     */
    protected $bv1;

    /**
     * Width.
     *
     * @var int
     */
    protected $width;

    /**
     * Frame
     *
     * @var array
     */
    protected $frame;

    /**
     * Horizontal bit position
     *
     * @var int
     */
    protected $xpos;

    /**
     * Vertical bit position
     *
     * @var int
     */
    protected $ypos;

    /**
     * Direction
     *
     * @var int
     */
    protected $dir;

    /**
     * Single bit value
     *
     * @var int
     */
    protected $bit;

    /**
     * Reed-Solomon items
     *
     * @va array
     */
    protected $rsitems = array();

    /**
     * Encode mask
     *
     * @param int   $maskNo   Mask number (masking mode)
     * @param array $datacode Data code to encode
     *
     * @return array Encoded Mask
     */
    public function encodeMask($maskNo, $datacode)
    {
        // initialize values
        $this->datacode = $datacode;
        $spec = $this->spc->getEccSpec($this->version, $this->level, array(0, 0, 0, 0, 0));
        $this->bv1 = $this->spc->rsBlockNum1($spec);
        $this->dataLength = $this->spc->rsDataLength($spec);
        $this->eccLength = $this->spc->rsEccLength($spec);
        $this->ecccode = array_fill(0, $this->eccLength, 0);
        $this->blocks = $this->spc->rsBlockNum($spec);
        $this->init($spec);
        $this->count = 0;
        $this->width = $this->spc->getWidth($this->version);
        $this->frame = $this->spc->createFrame($this->version);
        $this->xpos = ($this->width - 1);
        $this->ypos = ($this->width - 1);
        $this->dir = -1;
        $this->bit = -1;
        
        // interleaved data and ecc codes
        for ($idx = 0; $idx < ($this->dataLength + $this->eccLength); $idx++) {
            $code = $this->getCode();
            $bit = 0x80;
            for ($jdx = 0; $jdx < 8; $jdx++) {
                $addr = $this->getNextPosition();
                $this->setFrameAt($addr, 0x02 | (($bit & $code) != 0));
                $bit = $bit >> 1;
            }
        }

        // remainder bits
        $rbits = $this->spc->getRemainder($this->version);
        for ($idx = 0; $idx < $rbits; $idx++) {
            $addr = $this->getNextPosition();
            $this->setFrameAt($addr, 0x02);
        }

        // masking
        $this->runLength = array_fill(0, (Data::QRSPEC_WIDTH_MAX + 1), 0);
        if ($maskNo < 0) {
            if ($this->qr_find_best_mask) {
                $mask = $this->mask($this->width, $this->frame, $this->level);
            } else {
                $mask = $this->makeMask($this->width, $this->frame, (intval($this->qr_default_mask) % 8), $this->level);
            }
        } else {
            $mask = $this->makeMask($this->width, $this->frame, $maskNo, $this->level);
        }
        if ($mask == null) {
            throw new BarcodeException('Null Mask');
        }
        return $mask;
    }

    /**
     * Return Reed-Solomon block code
     *
     * @return array rsblocks
     */
    protected function getCode()
    {
        if ($this->count < $this->dataLength) {
            $row = ($this->count % $this->blocks);
            $col = ($this->count / $this->blocks);
            if ($col >= $this->rsblocks[0]['dataLength']) {
                $row += $this->bv1;
            }
            $ret = $this->rsblocks[$row]['data'][$col];
        } elseif ($this->count < ($this->dataLength + $this->eccLength)) {
            $row = (($this->count - $this->dataLength) % $this->blocks);
            $col = (($this->count - $this->dataLength) / $this->blocks);
            $ret = $this->rsblocks[$row]['ecc'][$col];
        } else {
            return 0;
        }
        ++$this->count;
        return $ret;
    }

    /**
     * Set frame value at specified position
     *
     * @param array $pos X,Y position
     * @param int   $val Value of the character to set
     */
    protected function setFrameAt($pos, $val)
    {
        $this->frame[$pos['y']][$pos['x']] = chr($val);
    }

    /**
     * Return the next frame position
     *
     * @return array of x,y coordinates
     */
    protected function getNextPosition()
    {
        do {
            if ($this->bit == -1) {
                $this->bit = 0;
                return array('x' => $this->xpos, 'y' => $this->ypos);
            }
            $xpos = $this->xpos;
            $ypos = $this->ypos;
            $wdt = $this->width;
            $this->getNextPositionB($xpos, $ypos, $wdt);
            if (($xpos < 0) || ($ypos < 0)) {
                throw new BarcodeException('Error getting next position');
            }
            $this->xpos = $xpos;
            $this->ypos = $ypos;
        } while (ord($this->frame[$ypos][$xpos]) & 0x80);

        return array('x' => $xpos, 'y' => $ypos);
    }

    /**
     * Internal cycle for getNextPosition
     *
     * @param int $xpos
     * @param int $ypos
     * @param int $wdt
     */
    protected function getNextPositionB(&$xpos, &$ypos, $wdt)
    {
        if ($this->bit == 0) {
            --$xpos;
            ++$this->bit;
        } else {
            ++$xpos;
            $ypos += $this->dir;
            --$this->bit;
        }
        if ($this->dir < 0) {
            if ($ypos < 0) {
                $ypos = 0;
                $xpos -= 2;
                $this->dir = 1;
                if ($xpos == 6) {
                    --$xpos;
                    $ypos = 9;
                }
            }
        } else {
            if ($ypos == $wdt) {
                $ypos = $wdt - 1;
                $xpos -= 2;
                $this->dir = -1;
                if ($xpos == 6) {
                    --$xpos;
                    $ypos -= 8;
                }
            }
        }
    }
}
