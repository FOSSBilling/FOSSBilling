<?php
/**
 * Encode.php
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
 * Com\Tecnick\Barcode\Type\Square\QrCode\Encode
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
abstract class Encode extends \Com\Tecnick\Barcode\Type\Square\QrCode\EncodingMode
{
    /**
     * encode Mode Num
     *
     * @param array $inputitem
     * @param int   $version
     *
     * @return array input item
     */
    protected function encodeModeNum($inputitem, $version)
    {
        $words = (int)($inputitem['size'] / 3);
        $inputitem['bstream'] = array();
        $val = 0x1;
        $inputitem['bstream'] = $this->appendNum($inputitem['bstream'], 4, $val);
        $inputitem['bstream'] = $this->appendNum(
            $inputitem['bstream'],
            $this->getLengthIndicator(Data::$encodingModes['NM'], $version),
            $inputitem['size']
        );
        for ($i=0; $i < $words; ++$i) {
            $val  = (ord($inputitem['data'][$i*3  ]) - ord('0')) * 100;
            $val += (ord($inputitem['data'][$i*3+1]) - ord('0')) * 10;
            $val += (ord($inputitem['data'][$i*3+2]) - ord('0'));
            $inputitem['bstream'] = $this->appendNum($inputitem['bstream'], 10, $val);
        }
        if ($inputitem['size'] - $words * 3 == 1) {
            $val = ord($inputitem['data'][$words*3]) - ord('0');
            $inputitem['bstream'] = $this->appendNum($inputitem['bstream'], 4, $val);
        } elseif (($inputitem['size'] - ($words * 3)) == 2) {
            $val  = (ord($inputitem['data'][$words*3  ]) - ord('0')) * 10;
            $val += (ord($inputitem['data'][$words*3+1]) - ord('0'));
            $inputitem['bstream'] = $this->appendNum($inputitem['bstream'], 7, $val);
        }
        return $inputitem;
    }

    /**
     * encode Mode An
     *
     * @param array $inputitem
     * @param int   $version
     *
     * @return array input item
     */
    protected function encodeModeAn($inputitem, $version)
    {
        $words = (int)($inputitem['size'] / 2);
        $inputitem['bstream'] = array();
        $inputitem['bstream'] = $this->appendNum($inputitem['bstream'], 4, 0x02);
        $inputitem['bstream'] = $this->appendNum(
            $inputitem['bstream'],
            $this->getLengthIndicator(Data::$encodingModes['AN'], $version),
            $inputitem['size']
        );
        for ($idx = 0; $idx < $words; ++$idx) {
            $val  = (int)($this->lookAnTable(ord($inputitem['data'][($idx * 2)])) * 45);
            $val += (int)($this->lookAnTable(ord($inputitem['data'][($idx * 2)+1])));
            $inputitem['bstream'] = $this->appendNum($inputitem['bstream'], 11, $val);
        }
        if ($inputitem['size'] & 1) {
            $val = $this->lookAnTable(ord($inputitem['data'][($words * 2)]));
            $inputitem['bstream'] = $this->appendNum($inputitem['bstream'], 6, $val);
        }
        return $inputitem;
    }

    /**
     * encode Mode 8
     *
     * @param array $inputitem
     * @param int   $version
     *
     * @return array input item
     */
    protected function encodeMode8($inputitem, $version)
    {
        $inputitem['bstream'] = array();
        $inputitem['bstream'] = $this->appendNum($inputitem['bstream'], 4, 0x4);
        $inputitem['bstream'] = $this->appendNum(
            $inputitem['bstream'],
            $this->getLengthIndicator(Data::$encodingModes['8B'], $version),
            $inputitem['size']
        );
        for ($idx = 0; $idx < $inputitem['size']; ++$idx) {
            $inputitem['bstream'] = $this->appendNum($inputitem['bstream'], 8, ord($inputitem['data'][$idx]));
        }
        return $inputitem;
    }

    /**
     * encode Mode Kanji
     *
     * @param array $inputitem
     * @param int   $version
     *
     * @return array input item
     */
    protected function encodeModeKanji($inputitem, $version)
    {
        $inputitem['bstream'] = array();
        $inputitem['bstream'] = $this->appendNum($inputitem['bstream'], 4, 0x8);
        $inputitem['bstream'] = $this->appendNum(
            $inputitem['bstream'],
            $this->getLengthIndicator(Data::$encodingModes['KJ'], $version),
            (int)($inputitem['size'] / 2)
        );
        for ($idx = 0; $idx < $inputitem['size']; $idx += 2) {
            $val = (ord($inputitem['data'][$idx]) << 8) | ord($inputitem['data'][($idx + 1)]);
            if ($val <= 0x9ffc) {
                $val -= 0x8140;
            } else {
                $val -= 0xc140;
            }
            $val = ($val & 0xff) + (($val >> 8) * 0xc0);
            $inputitem['bstream'] = $this->appendNum($inputitem['bstream'], 13, $val);
        }
        return $inputitem;
    }

    /**
     * encode Mode Structure
     *
     * @param array $inputitem
     *
     * @return array input item
     */
    protected function encodeModeStructure($inputitem)
    {
        $inputitem['bstream'] = array();
        $inputitem['bstream'] = $this->appendNum($inputitem['bstream'], 4, 0x03);
        $inputitem['bstream'] = $this->appendNum($inputitem['bstream'], 4, ord($inputitem['data'][1]) - 1);
        $inputitem['bstream'] = $this->appendNum($inputitem['bstream'], 4, ord($inputitem['data'][0]) - 1);
        $inputitem['bstream'] = $this->appendNum($inputitem['bstream'], 8, ord($inputitem['data'][2]));
        return $inputitem;
    }
}
