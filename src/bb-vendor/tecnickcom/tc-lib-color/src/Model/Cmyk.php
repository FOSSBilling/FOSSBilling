<?php
/**
 * Cmyk.php
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
 * Com\Tecnick\Color\Model\Cmyk
 *
 * CMYK Color Model class
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Color
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-color
 */
class Cmyk extends \Com\Tecnick\Color\Model implements \Com\Tecnick\Color\Model\Template
{
    /**
     * Color Model type
     *
     * @var string
     */
    protected $type = 'CMYK';

    /**
     * Value of the Cyan color component [0..1]
     *
     * @var float
     */
    protected $cmp_cyan = 0.0;

    /**
     * Value of the Magenta color component [0..1]
     *
     * @var float
     */
    protected $cmp_magenta = 0.0;

    /**
     * Value of the Yellow color component [0..1]
     *
     * @var float
     */
    protected $cmp_yellow = 0.0;

    /**
     * Value of the Key (Black) color component [0..1]
     *
     * @var float
     */
    protected $cmp_key = 0.0;

    /**
     * Get an array with all color components
     *
     * @return array with keys ('C', 'M', 'Y', 'K', 'A')
     */
    public function getArray()
    {
        return array(
            'C' => $this->cmp_cyan,
            'M' => $this->cmp_magenta,
            'Y' => $this->cmp_yellow,
            'K' => $this->cmp_key,
            'A' => $this->cmp_alpha
        );
    }

    /**
     * Get an array with color components values normalized between 0 and $max.
     * NOTE: the alpha and other fraction component values are kept in the [0..1] range.
     *
     * @param int $max Maximum value to return (reference value - it should be 100)
     *
     * @return array with keys ('C', 'M', 'Y', 'K', 'A')
     */
    public function getNormalizedArray($max)
    {
        return array(
            'C' => $this->getNormalizedValue($this->cmp_cyan, $max),
            'M' => $this->getNormalizedValue($this->cmp_magenta, $max),
            'Y' => $this->getNormalizedValue($this->cmp_yellow, $max),
            'K' => $this->getNormalizedValue($this->cmp_key, $max),
            'A' => $this->cmp_alpha,
        );
    }

    /**
     * Get the CSS representation of the color: rgba(R, G, B, A)
     * NOTE: Supported since CSS3 and above.
     *       Use getHexadecimalColor() for CSS1 and CSS2
     *
     * @return string
     */
    public function getCssColor()
    {
        $rgb = $this->toRgbArray();
        return 'rgba('
            .$this->getNormalizedValue($rgb['red'], 100).'%,'
            .$this->getNormalizedValue($rgb['green'], 100).'%,'
            .$this->getNormalizedValue($rgb['blue'], 100).'%,'
            .$rgb['alpha']
            .')';
    }

    /**
     * Get the color format used in Acrobat JavaScript
     * NOTE: the alpha channel is omitted from this representation unless is 0 = transparent
     *
     * @return string
     */
    public function getJsPdfColor()
    {
        if ($this->cmp_alpha == 0) {
            return '["T"]'; // transparent color
        }
        return sprintf('["CMYK",%F,%F,%F,%F]', $this->cmp_cyan, $this->cmp_magenta, $this->cmp_yellow, $this->cmp_key);
    }

    /**
     * Get a space separated string with color component values.
     *
     * @return string
     */
    public function getComponentsString()
    {
        return sprintf('%F %F %F %F', $this->cmp_cyan, $this->cmp_magenta, $this->cmp_yellow, $this->cmp_key);
    }

    /**
     * Get the color components format used in PDF documents (CMYK)
     * NOTE: the alpha channel is omitted
     *
     * @param bool $stroke True for stroking (lines, drawing) and false for non-stroking (text and area filling).
     *
     * @return string
     */
    public function getPdfColor($stroke = false)
    {
        $mode = 'k';
        if ($stroke) {
            $mode = strtoupper($mode);
        }
        return $this->getComponentsString().' '.$mode."\n";
    }

    /**
     * Get an array with Gray color components
     *
     * @return array with keys ('gray')
     */
    public function toGrayArray()
    {
        return array(
            'gray'  => $this->cmp_key,
            'alpha' => $this->cmp_alpha
        );
    }

    /**
     * Get an array with RGB color components
     *
     * @return array with keys ('red', 'green', 'blue', 'alpha')
     */
    public function toRgbArray()
    {
        return array(
            'red'   => max(0, min(1, (1 - (($this->cmp_cyan    * (1 - $this->cmp_key)) + $this->cmp_key)))),
            'green' => max(0, min(1, (1 - (($this->cmp_magenta * (1 - $this->cmp_key)) + $this->cmp_key)))),
            'blue'  => max(0, min(1, (1 - (($this->cmp_yellow  * (1 - $this->cmp_key)) + $this->cmp_key)))),
            'alpha' => $this->cmp_alpha
        );
    }

    /**
     * Get an array with HSL color components
     *
     * @return array with keys ('hue', 'saturation', 'lightness', 'alpha')
     */
    public function toHslArray()
    {
        $rgb = new \Com\Tecnick\Color\Model\Rgb($this->toRgbArray());
        return $rgb->toHslArray();
    }

    /**
     * Get an array with CMYK color components
     *
     * @return array with keys ('cyan', 'magenta', 'yellow', 'key', 'alpha')
     */
    public function toCmykArray()
    {
        return array(
            'cyan'    => $this->cmp_cyan,
            'magenta' => $this->cmp_magenta,
            'yellow'  => $this->cmp_yellow,
            'key'     => $this->cmp_key,
            'alpha'   => $this->cmp_alpha
        );
    }

    /**
     * Invert the color
     */
    public function invertColor()
    {
        $this->cmp_cyan    = (1 - $this->cmp_cyan);
        $this->cmp_magenta = (1 - $this->cmp_magenta);
        $this->cmp_yellow  = (1 - $this->cmp_yellow);
        $this->cmp_key     = (1 - $this->cmp_key);
        return $this;
    }
}
