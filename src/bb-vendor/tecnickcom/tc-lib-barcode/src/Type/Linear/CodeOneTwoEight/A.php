<?php
/**
 * A.php
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
 * Com\Tecnick\Barcode\Type\Linear\CodeOneTwoEight\A;
 *
 * CodeOneTwoEightA Barcode type class
 * CODE 128 A
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class A extends \Com\Tecnick\Barcode\Type\Linear\CodeOneTwoEight
{
    /**
     * Barcode format
     *
     * @var string
     */
    protected $format = 'C128A';
    
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
        // length of the code
        $len = strlen($code);
        $startid = 103;
        $this->getCodeDataA($code_data, $code, $len);
        return $this->finalizeCodeData($code_data, $startid);
    }
}
