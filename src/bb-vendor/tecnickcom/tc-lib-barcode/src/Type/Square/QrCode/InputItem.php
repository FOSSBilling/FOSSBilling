<?php
/**
 * InputItem.php
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
 * Com\Tecnick\Barcode\Type\Square\QrCode\InputItem
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
abstract class InputItem extends \Com\Tecnick\Barcode\Type\Square\QrCode\Estimate
{
    /**
     * Append data to an input object.
     * The data is copied and appended to the input object.
     *
     * @param array $items Input items
     * @param int   $mode  Encoding mode.
     * @param int   $size  Size of data (byte).
     * @param array $data  Array of input data.
     *
     * @return array items
     */
    public function appendNewInputItem($items, $mode, $size, $data)
    {
        $newitem = $this->newInputItem($mode, $size, $data);
        if (!empty($newitem)) {
            $items[] = $newitem;
        }
        return $items;
    }

    /**
     * newInputItem
     *
     * @param int   $mode    Encoding mode.
     * @param int   $size    Size of data (byte).
     * @param array $data    Array of input data.
     * @param array $bstream Binary stream
     *
     * @return array input item
     */
    protected function newInputItem($mode, $size, $data, $bstream = null)
    {
        $setData = array_slice($data, 0, $size);
        if (count($setData) < $size) {
            $setData = array_merge($setData, array_fill(0, ($size - count($setData)), 0));
        }
        if (!$this->check($mode, $size, $setData)) {
            throw new BarcodeException('Invalid input item');
        }
        return array(
            'mode'    => $mode,
            'size'    => $size,
            'data'    => $setData,
            'bstream' => $bstream,
        );
    }

    /**
     * Validate the input data.
     *
     * @param int   $mode Encoding mode.
     * @param int   $size Size of data (byte).
     * @param array $data Data to validate
     *
     * @return boolean true in case of valid data, false otherwise
     */
    protected function check($mode, $size, $data)
    {
        if ($size <= 0) {
            return false;
        }
        switch ($mode) {
            case Data::$encodingModes['NM']:
                return $this->checkModeNum($size, $data);
            case Data::$encodingModes['AN']:
                return $this->checkModeAn($size, $data);
            case Data::$encodingModes['KJ']:
                return $this->checkModeKanji($size, $data);
            case Data::$encodingModes['8B']:
                return true;
            case Data::$encodingModes['ST']:
                return true;
        }
        return false;
    }

    /**
     * checkModeNum
     *
     * @param int $size
     * @param int $data
     *
     * @return boolean true or false
     */
    protected function checkModeNum($size, $data)
    {
        for ($idx = 0; $idx < $size; ++$idx) {
            if ((ord($data[$idx]) < ord('0')) || (ord($data[$idx]) > ord('9'))) {
                return false;
            }
        }
        return true;
    }

    /**
     * checkModeAn
     *
     * @param int $size
     * @param int $data
     *
     * @return boolean true or false
     */
    protected function checkModeAn($size, $data)
    {
        for ($idx = 0; $idx < $size; ++$idx) {
            if ($this->lookAnTable(ord($data[$idx])) == -1) {
                return false;
            }
        }
        return true;
    }

    /**
     * checkModeKanji
     *
     * @param int $size
     * @param int $data
     *
     * @return boolean true or false
     */
    protected function checkModeKanji($size, $data)
    {
        if ($size & 1) {
            return false;
        }
        for ($idx = 0; $idx < $size; $idx += 2) {
            $val = (ord($data[$idx]) << 8) | ord($data[($idx + 1)]);
            if (($val < 0x8140) || (($val > 0x9ffc) && ($val < 0xe040)) || ($val > 0xebbf)) {
                return false;
            }
        }
        return true;
    }
}
