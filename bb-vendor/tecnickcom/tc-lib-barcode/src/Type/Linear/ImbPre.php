<?php
/**
 * ImbPre.php
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
 * Com\Tecnick\Barcode\Type\Linear\ImbPre;
 *
 * ImbPre Barcode type class
 * IMB - Intelligent Mail Barcode pre-processed (USPS-B-3200)
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class ImbPre extends \Com\Tecnick\Barcode\Type\Linear
{
    /**
     * Barcode format
     *
     * @var string
     */
    protected $format = 'IMBPRE';
    
    /**
     * Get the bars array
     *
     * @throws BarcodeException in case of error
     */
    protected function setBars()
    {
        $code = strtolower($this->code);
        if (preg_match('/^[fadt]{65}$/', $code) != 1) {
            throw new BarcodeException('Invalid character sequence');
        }
        $this->ncols = 0;
        $this->nrows = 3;
        $this->bars = array();
        for ($pos = 0; $pos < 65; ++$pos) {
            switch ($code[$pos]) {
                case 'f':
                    // full bar
                    $this->bars[] = array($this->ncols, 0, 1, 3);
                    break;
                case 'a':
                    // ascender
                    $this->bars[] = array($this->ncols, 0, 1, 2);
                    break;
                case 'd':
                    // descender
                    $this->bars[] = array($this->ncols, 1, 1, 2);
                    break;
                case 't':
                    // tracker (short)
                    $this->bars[] = array($this->ncols, 1, 1, 1);
                    break;
            }
            $this->ncols += 2;
        }
        --$this->ncols;
    }
}
