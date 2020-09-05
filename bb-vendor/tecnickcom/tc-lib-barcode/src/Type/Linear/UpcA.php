<?php
/**
 * UpcA.php
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

namespace Com\Tecnick\Barcode\Type\Linear;

use \Com\Tecnick\Barcode\Exception as BarcodeException;

/**
 * Com\Tecnick\Barcode\Type\Linear\UpcA;
 *
 * UpcA Barcode type class
 * UPC-A
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class UpcA extends \Com\Tecnick\Barcode\Type\Linear\EanOneThree
{
    /**
     * Barcode format
     *
     * @var string
     */
    protected $format = 'UPCA';

    /**
     * Fixed code length
     *
     * @var int
     */
    protected $code_length = 12;

    /**
     * Format the code
     */
    protected function formatCode()
    {
        $code = str_pad($this->code, ($this->code_length - 1), '0', STR_PAD_LEFT);
        $code .= $this->getChecksum($code);
        ++$this->code_length;
        $this->extcode = '0'.$code;
    }
}
