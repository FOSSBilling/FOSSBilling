<?php
/**
 * Raw.php
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 *
 * This file is part of tc-lib-barcode software library.
 */

namespace Com\Tecnick\Barcode\Type;

use \Com\Tecnick\Barcode\Exception as BarcodeException;

/**
 * Com\Tecnick\Barcode\Type\Raw
 *
 * Raw Barcode type class
 * RAW MODE (comma-separated rows)
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class Raw extends \Com\Tecnick\Barcode\Type
{
    /**
     * Get the pre-formatted code
     *
     * @return string
     */
    protected function getCodeRows()
    {
        if (is_array($this->code)) {
            return $this->code;
        }
        // remove spaces and newlines
        $code = preg_replace('/[\s]*/s', '', $this->code);
        // remove trailing brackets or commas
        $code = preg_replace('/^[\[,]+/', '', $code);
        $code = preg_replace('/[\],]+$/', '', $code);
        // convert bracket -separated to comma-separated
        $code = preg_replace('/[\]][\[]$/', ',', $code);
        return explode(',', $code);
    }

    /**
     * Get the bars array
     *
     * @throws BarcodeException in case of error
     */
    protected function setBars()
    {
        $rows = $this->getCodeRows();
        if (empty($rows)) {
            throw new BarcodeException('Empty input string');
        }
        $this->nrows = count($rows);
        if (is_array($rows[0])) {
            $this->ncols = count($rows[0]);
        } else {
            $this->ncols = strlen($rows[0]);
        }
        if (empty($this->ncols)) {
            throw new BarcodeException('Empty columns');
        }
        $this->bars = array();
        foreach ($rows as $posy => $row) {
            if (!is_array($row)) {
                $row = str_split($row, 1);
            }
            $prevcol = '';
            $bar_width = 0;
            $row[] = '0';
            for ($posx = 0; $posx <= $this->ncols; ++$posx) {
                if ($row[$posx] != $prevcol) {
                    if ($prevcol == '1') {
                        $this->bars[] = array(($posx - $bar_width), $posy, $bar_width, 1);
                    }
                    $bar_width = 0;
                }
                ++$bar_width;
                $prevcol = $row[$posx];
            }
        }
    }
}
