<?php
/**
 * Pdf.php
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

namespace Com\Tecnick\Color;

use \Com\Tecnick\Color\Exception as ColorException;
use \Com\Tecnick\Color\Web;
use \Com\Tecnick\Color\Spot;

/**
 * Com\Tecnick\Color\Pdf
 *
 * PDF Color class
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Color
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-color
 */
class Pdf extends \Com\Tecnick\Color\Spot
{
    /**
     * Array of valid JavaScript color names to be used in PDF documents
     *
     * @var array
     */
    protected static $jscolor = array(
        'transparent',
        'black',
        'white',
        'red',
        'green',
        'blue',
        'cyan',
        'magenta',
        'yellow',
        'dkGray',
        'gray',
        'ltGray',
    );

    /**
     * Return the Js color array of names
     *
     * @return array
     */
    public function getJsMap()
    {
        return self::$jscolor;
    }

    /**
     * Convert color to javascript string
     *
     * @param string|object $color color name or color object
     *
     * @return string
     */
    public function getJsColorString($color)
    {
        if (in_array($color, self::$jscolor)) {
            return 'color.'.$color;
        }
        try {
            if (($colobj = $this->getColorObj($color)) !== null) {
                return $colobj->getJsPdfColor();
            }
        } catch (Exception $e) {
            if (!($e instanceof ColorException)) {
                throw $e;
            }
        }
        // default transparent color
        return 'color.'.self::$jscolor[0];
    }

    /**
     * Returns a color object from an HTML, CSS or Spot color representation.
     *
     * @param string $color HTML, CSS or Spot color to parse
     *
     * @return object or null in case of error or if the color is not found
     */
    public function getColorObject($color)
    {
        try {
            return $this->getSpotColorObj($color);
        } catch (Exception $e) {
            if (!($e instanceof ColorException)) {
                throw $e;
            }
        }
        try {
            return $this->getColorObj($color);
        } catch (Exception $e) {
            if (!($e instanceof ColorException)) {
                throw $e;
            }
        }
        return null;
    }

    /**
     * Get the color components format used in PDF documents
     * NOTE: the alpha channel is omitted
     *
     * @param string $color  HTML, CSS or Spot color to parse
     * @param bool   $stroke True for stroking (lines, drawing) and false for non-stroking (text and area filling).
     * @param float  $tint   Intensity of the color (from 0 to 1; 1 = full intensity).
     *
     * @return string
     */
    public function getPdfColor($color, $stroke = false, $tint = 1)
    {
        try {
            $col = $this->getSpotColor($color);
            $tint = sprintf('cs %F scn', (max(0, min(1, (float) $tint))));
            if ($stroke) {
                $tint = strtoupper($tint);
            }
            return sprintf('/CS%d %s'."\n", $col['i'], $tint);
        } catch (Exception $e) {
            if (!($e instanceof ColorException)) {
                throw $e;
            }
        }
        try {
            $col = $this->getColorObj($color);
            if ($col !== null) {
                return $col->getPdfColor($stroke);
            }
        } catch (Exception $e) {
            if (!($e instanceof ColorException)) {
                throw $e;
            }
        }
        return '';
    }

    /**
     * Get the RGB color components format used in PDF documents
     *
     * @param string $color  HTML, CSS or Spot color to parse
     *
     * @return string
     */
    public function getPdfRgbComponents($color)
    {
        $col = $this->getColorObject($color);
        if ($col === null) {
            return '';
        }
        $cmp = $col->toRgbArray();
        return sprintf('%F %F %F', $cmp['red'], $cmp['green'], $cmp['blue']);
    }
}
