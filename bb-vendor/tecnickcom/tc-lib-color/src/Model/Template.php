<?php
/**
 * Template.php
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Color
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-color
 *
 * This file is part of tc-lib-color software library.
 */

namespace Com\Tecnick\Color\Model;

/**
 * Com\Tecnick\Color\Model\Template
 *
 * Color Model Interface
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Color
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-color
 */
interface Template
{
    /**
     * Get an array with all color components
     *
     * @return array
     */
    public function getArray();

    /**
     * Get an array with color components values normalized between 0 and $max.
     * NOTE: the alpha and other fraction component values are kept in the [0..1] range.
     *
     * @param int $max Maximum value to return (reference value)
     *
     * @return array
     */
    public function getNormalizedArray($max);

    /**
     * Get the CSS representation of the color
     *
     * @return string
     */
    public function getCssColor();

    /**
     * Get the color format used in Acrobat JavaScript
     * NOTE: the alpha channel is omitted from this representation unless is 0 = transparent
     *
     * @return string
     */
    public function getJsPdfColor();

    /**
     * Get a space separated string with color component values.
     *
     * @return string
     */
    public function getComponentsString();

    /**
     * Get the color components format used in PDF documents
     * NOTE: the alpha channel is omitted
     *
     * @return string
     */
    public function getPdfColor();
    
    /**
     * Get an array with Gray color components
     *
     * @return array with keys ('gray')
     */
    public function toGrayArray();
    
    /**
     * Get an array with RGB color components
     *
     * @return array with keys ('red', 'green', 'blue', 'alpha')
     */
    public function toRgbArray();

    /**
     * Get an array with HSL color components
     *
     * @return array with keys ('hue', 'saturation', 'lightness', 'alpha')
     */
    public function toHslArray();

    /**
     * Get an array with CMYK color components
     *
     * @return array with keys ('cyan', 'magenta', 'yellow', 'key', 'alpha')
     */
    public function toCmykArray();

    /**
     * Invert the color
     */
    public function invertColor();
}
