<?php
/**
 * ImageImportInterface.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfImage
 * @author      jmleroux <jmleroux.pro@gmail.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-image
 *
 * This file is part of tc-lib-pdf-image software library.
 */

namespace Com\Tecnick\Pdf\Image\Import;

/**
 * Com\Tecnick\Pdf\Image\Import\Jpeg
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfImage
 * @author      jmleroux <jmleroux.pro@gmail.com>
 * @copyright   2011-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-image
 */
interface ImageImportInterface
{
    /**
     * Extract data from an image
     *
     * @param array $data Image raw data
     *
     * @return array Image raw data array
     */
    public function getData($data);
}
