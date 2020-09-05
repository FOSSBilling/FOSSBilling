<?php
/**
 * Jpeg.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfImage
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-image
 *
 * This file is part of tc-lib-pdf-image software library.
 */

namespace Com\Tecnick\Pdf\Image\Import;

use \Com\Tecnick\File\Byte;

/**
 * Com\Tecnick\Pdf\Image\Import\Jpeg
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfImage
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-image
 */
class Jpeg implements ImageImportInterface
{
    /**
     * Extract data from a JPEG image
     *
     * @param array $data Image raw data
     *
     * @return array Image raw data array
     */
    public function getData($data)
    {
        $data['filter'] = 'DCTDecode';
        $data['data'] = $data['raw'];
        $byte = new Byte($data['raw']);
        // extract embedded ICC profile (if any)
        $icc = array();
        $offset = 0;
        while (($pos = strpos($data['raw'], 'ICC_PROFILE'."\0", $offset)) !== false) {
            // get ICC sequence length
            $length = ($byte->getUShort($pos - 2) - 16);
            // marker sequence number
            $msn = max(1, ord($data['raw'][($pos + 12)]));
            // number of markers (total of APP2 used)
            //$nom = max(1, ord($data['raw'][($pos + 13)]));
            // get sequence segment
            $icc[($msn - 1)] = substr($data['raw'], ($pos + 14), $length);
            // move forward to next sequence
            $offset = ($pos + 14 + $length);
        }
        // order and compact ICC segments
        if (count($icc) > 0) {
            ksort($icc);
            $icc = implode('', $icc);
            if (substr($icc, 36, 4) == 'acsp') {
                // valid ICC profile
                $data['icc'] = $icc;
            }
        }
        return $data;
    }
}
