<?php
/**
 * Core.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 *
 * This file is part of tc-lib-pdf-font software library.
 */

namespace Com\Tecnick\Pdf\Font;

/**
 * Com\Tecnick\Pdf\Font\Core
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 */
class Core
{
    /**
     * Core fonts
     *
     * @var array
     */
    public static $font = array(
        'courier'        => 'Courier',
        'courierB'       => 'Courier-Bold',
        'courierI'       => 'Courier-Oblique',
        'courierBI'      => 'Courier-BoldOblique',
        'helvetica'      => 'Helvetica',
        'helveticaB'     => 'Helvetica-Bold',
        'helveticaI'     => 'Helvetica-Oblique',
        'helveticaBI'    => 'Helvetica-BoldOblique',
        'timesroman'     => 'Times-Roman',
        'times'          => 'Times-Roman',
        'timesB'         => 'Times-Bold',
        'timesI'         => 'Times-Italic',
        'timesBI'        => 'Times-BoldItalic',
        'symbol'         => 'Symbol',
        'symbolB'        => 'Symbol',
        'symbolI'        => 'Symbol',
        'symbolBI'       => 'Symbol',
        'zapfdingbats'   => 'ZapfDingbats',
        'zapfdingbatsB'  => 'ZapfDingbats',
        'zapfdingbatsI'  => 'ZapfDingbats',
        'zapfdingbatsBI' => 'ZapfDingbats'
    );
}
