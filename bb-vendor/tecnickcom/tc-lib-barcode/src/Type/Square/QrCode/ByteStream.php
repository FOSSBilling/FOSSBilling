<?php
/**
 * ByteStream.php
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
use \Com\Tecnick\Barcode\Type\Square\QrCode\Estimate;
use \Com\Tecnick\Barcode\Type\Square\QrCode\Spec;

/**
 * Com\Tecnick\Barcode\Type\Square\QrCode\ByteStream
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class ByteStream extends \Com\Tecnick\Barcode\Type\Square\QrCode\Encode
{
    /**
     * Encoding mode
     *
     * @var int
     */
    protected $hint = 2;

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
     * Initialize
     *
     * @param int $hint    Encoding mode
     * @param int $version Code version
     * @param int $level   Error Correction Level
     */
    public function __construct($hint, $version, $level)
    {
        $this->hint = $hint;
        $this->version = $version;
        $this->level = $level;
    }

    /**
     * Pack all bit streams padding bits into a byte array
     *
     * @param array $items items
     *
     * @return array padded merged byte stream
     */
    public function getByteStream($items)
    {
        return $this->bitstreamToByte(
            $this->appendPaddingBit(
                $this->mergeBitStream($items)
            )
        );
    }

    /**
     * Convert bitstream to bytes
     *
     * @param array $bstream Original bitstream
     *
     * @return array of bytes
     */
    protected function bitstreamToByte($bstream)
    {
        $size = count($bstream);
        if ($size == 0) {
            return array();
        }
        $data = array_fill(0, (int)(($size + 7) / 8), 0);
        $bytes = (int)($size / 8);
        $pos = 0;
        for ($idx = 0; $idx < $bytes; ++$idx) {
            $val = 0;
            for ($jdx = 0; $jdx < 8; ++$jdx) {
                $val = $val << 1;
                $val |= $bstream[$pos];
                $pos++;
            }
            $data[$idx] = $val;
        }
        if ($size & 7) {
            $val = 0;
            for ($jdx = 0; $jdx < ($size & 7); ++$jdx) {
                $val = $val << 1;
                $val |= $bstream[$pos];
                $pos++;
            }
            $data[$bytes] = $val;
        }
        return $data;
    }

    /**
     * merge the bit stream
     *
     * @param array $items Items
     *
     * @return array bitstream
     */
    protected function mergeBitStream($items)
    {
        $items = $this->convertData($items);
        $bstream = array();
        foreach ($items as $item) {
            $bstream = $this->appendBitstream($bstream, $item['bstream']);
        }
        return $bstream;
    }

    /**
     * convertData
     *
     * @param array $items Items
     *
     * @return array items
     */
    protected function convertData($items)
    {
        $ver = $this->estimateVersion($items, $this->level);
        if ($ver > $this->version) {
            $this->version = $ver;
        }
        while (true) {
            $cbs = $this->createBitStream($items);
            $items = $cbs[0];
            $bits = $cbs[1];
            if ($bits < 0) {
                throw new BarcodeException('Negative Bits value');
            }
            $ver = $this->getMinimumVersion((int)(($bits + 7) / 8), $this->level);
            if ($ver > $this->version) {
                $this->version = $ver;
            } else {
                break;
            }
        }
        return $items;
    }

    /**
     * Create BitStream
     *
     * @param $items
     *
     * @return array of items and total bits
     */
    protected function createBitStream($items)
    {
        $total = 0;
        foreach ($items as $key => $item) {
            $items[$key] = $this->encodeBitStream($item, $this->version);
            $bits = count($items[$key]['bstream']);
            $total += $bits;
        }
        return array($items, $total);
    }

    /**
     * Encode BitStream
     *
     * @param array $inputitem
     * @param int   $version
     *
     * @return array input item
     */
    public function encodeBitStream($inputitem, $version)
    {
        $inputitem['bstream'] = array();
        $specObj = new Spec;
        $words = $specObj->maximumWords($inputitem['mode'], $version);
        if ($inputitem['size'] > $words) {
            $st1 = $this->newInputItem($inputitem['mode'], $words, $inputitem['data']);
            $st2 = $this->newInputItem(
                $inputitem['mode'],
                ($inputitem['size'] - $words),
                array_slice($inputitem['data'], $words)
            );
            $st1 = $this->encodeBitStream($st1, $version);
            $st2 = $this->encodeBitStream($st2, $version);
            $inputitem['bstream'] = array();
            $inputitem['bstream'] = $this->appendBitstream($inputitem['bstream'], $st1['bstream']);
            $inputitem['bstream'] = $this->appendBitstream($inputitem['bstream'], $st2['bstream']);
        } else {
            switch ($inputitem['mode']) {
                case Data::$encodingModes['NM']:
                    $inputitem = $this->encodeModeNum($inputitem, $version);
                    break;
                case Data::$encodingModes['AN']:
                    $inputitem = $this->encodeModeAn($inputitem, $version);
                    break;
                case Data::$encodingModes['8B']:
                    $inputitem = $this->encodeMode8($inputitem, $version);
                    break;
                case Data::$encodingModes['KJ']:
                    $inputitem = $this->encodeModeKanji($inputitem, $version);
                    break;
                case Data::$encodingModes['ST']:
                    $inputitem = $this->encodeModeStructure($inputitem);
                    break;
            }
        }
        return $inputitem;
    }

    /**
     * Append Padding Bit to bitstream
     *
     * @param array $bstream Bit stream
     *
     * @return array bitstream
     */
    protected function appendPaddingBit($bstream)
    {
        if (is_null($bstream)) {
            return null;
        }
        $bits = count($bstream);
        $specObj = new Spec;
        $maxwords = $specObj->getDataLength($this->version, $this->level);
        $maxbits = $maxwords * 8;
        if ($maxbits == $bits) {
            return $bstream;
        }
        if ($maxbits - $bits < 5) {
            return $this->appendNum($bstream, $maxbits - $bits, 0);
        }
        $bits += 4;
        $words = (int)(($bits + 7) / 8);
        $padding = array();
        $padding = $this->appendNum($padding, $words * 8 - $bits + 4, 0);
        $padlen = $maxwords - $words;
        if ($padlen > 0) {
            $padbuf = array();
            for ($idx = 0; $idx < $padlen; ++$idx) {
                $padbuf[$idx] = (($idx & 1) ? 0x11 : 0xec);
            }
            $padding = $this->appendBytes($padding, $padlen, $padbuf);
        }
        return $this->appendBitstream($bstream, $padding);
    }
}
