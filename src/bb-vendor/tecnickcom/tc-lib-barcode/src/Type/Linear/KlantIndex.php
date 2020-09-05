<?php
/**
 * KlantIndex.php
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
 * Com\Tecnick\Barcode\Type\Linear\KlantIndex;
 *
 * KlantIndex Barcode type class
 * KIX (Klant index - Customer index)
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class KlantIndex extends \Com\Tecnick\Barcode\Type\Linear\RoyalMailFourCc
{
    /**
     * Barcode format
     *
     * @var string
     */
    protected $format = 'KIX';

    /**
     * Format code
     */
    protected function formatCode()
    {
        $this->extcode = strtoupper($this->code);
    }
    
    /**
     * Get the bars array
     *
     * @throws BarcodeException in case of error
     */
    protected function setBars()
    {
        $this->ncols = 0;
        $this->nrows = 3;
        $this->bars = array();
        $this->getCoreBars();
    }
}
