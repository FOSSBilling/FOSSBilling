<?php
/**
 * C.php
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

namespace Com\Tecnick\Barcode\Type\Linear\CodeOneTwoEight;

use \Com\Tecnick\Barcode\Exception as BarcodeException;

/**
 * Com\Tecnick\Barcode\Type\Linear\CodeOneTwoEight\C;
 *
 * CodeOneTwoEightC Barcode type class
 * CODE 128 C
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class C extends \Com\Tecnick\Barcode\Type\Linear\CodeOneTwoEight
{
    /**
     * Barcode format
     *
     * @var string
     */
    protected $format = 'C128C';

    /**
     * Get the code point array
     *
     * @throws BarcodeException in case of error
     */
    protected function getCodeData()
    {
        $code = $this->code;
        // array of symbols
        $code_data = array();

        $startid = 105;
        if (ord($code[0]) == 241) {
            $code_data[] = 102;
            $code = substr($code, 1);
        }
        $this->getCodeDataC($code_data, $code);
        return $this->finalizeCodeData($code_data, $startid);
    }
}
