<?php
/**
 * Hsl.php
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
 * Com\Tecnick\Color\Model\Hsl
 *
 * HSL Color Model class
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Color
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-color
 */
class Hsl extends \Com\Tecnick\Color\Model implements \Com\Tecnick\Color\Model\Template
{
    /**
     * Color Model type
     *
     * @var string
     */
    protected $type = 'HSL';

    /**
     * Value of the Hue color component [0..1]
     *
     * @var float
     */
    protected $cmp_hue = 0.0;

    /**
     * Value of the Saturation color component [0..1]
     *
     * @var float
     */
    protected $cmp_saturation = 0.0;

    /**
     * Value of the Lightness color component [0..1]
     *
     * @var float
     */
    protected $cmp_lightness = 0.0;

    /**
     * Get an array with all color components
     *
     * @return array with keys ('H', 'S', 'L', 'A')
     */
    public function getArray()
    {
        return array(
            'H' => $this->cmp_hue,
            'S' => $this->cmp_saturation,
            'L' => $this->cmp_lightness,
            'A' => $this->cmp_alpha
        );
    }

    /**
     * Get an array with color components values normalized between 0 and $max.
     * NOTE: the alpha and other fraction component values are kept in the [0..1] range.
     *
     * @param int $max Maximum value to return (it is always set to 360)
     *
     * @return array with keys ('H', 'S', 'L', 'A')
     */
    public function getNormalizedArray($max)
    {
        $max = 360;
        return array(
            'H' => $this->getNormalizedValue($this->cmp_hue, $max),
            'S' => $this->cmp_saturation,
            'L' => $this->cmp_lightness,
            'A' => $this->cmp_alpha
        );
    }

    /**
     * Get the CSS representation of the color: hsla(H, S, L, A)
     * NOTE: Supported since CSS3 and above.
     *       Use getHexadecimalColor() for CSS1 and CSS2
     *
     * @return string
     */
    public function getCssColor()
    {
        return 'hsla('
            .$this->getNormalizedValue($this->cmp_hue, 360).','
            .$this->getNormalizedValue($this->cmp_saturation, 100).'%,'
            .$this->getNormalizedValue($this->cmp_lightness, 100).'%,'
            .$this->cmp_alpha
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
        $rgb = $this->toRgbArray();
        if ($this->cmp_alpha == 0) {
            return '["T"]'; // transparent color
        }
        return sprintf('["RGB",%F,%F,%F]', $rgb['red'], $rgb['green'], $rgb['blue']);
    }

    /**
     * Get a space separated string with color component values.
     *
     * @return string
     */
    public function getComponentsString()
    {
        $rgb = $this->toRgbArray();
        return sprintf('%F %F %F', $rgb['red'], $rgb['green'], $rgb['blue']);
    }

    /**
     * Get the color components format used in PDF documents (RGB)
     * NOTE: the alpha channel is omitted
     *
     * @param bool $stroke True for stroking (lines, drawing) and false for non-stroking (text and area filling).
     *
     * @return string
     */
    public function getPdfColor($stroke = false)
    {
        $mode = 'rg';
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
            'gray'  => $this->cmp_lightness,
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
        if ($this->cmp_saturation == 0) {
            return array(
                'red'   => $this->cmp_lightness,
                'green' => $this->cmp_lightness,
                'blue'  => $this->cmp_lightness,
                'alpha' => $this->cmp_alpha
            );
        }
        if ($this->cmp_lightness < 0.5) {
            $valb = ($this->cmp_lightness * (1 + $this->cmp_saturation));
        } else {
            $valb = (($this->cmp_lightness + $this->cmp_saturation) - ($this->cmp_lightness * $this->cmp_saturation));
        }
        $vala = ((2 * $this->cmp_lightness) - $valb);
        return array(
            'red'   => $this->convertHuetoRgb($vala, $valb, ($this->cmp_hue + (1 / 3))),
            'green' => $this->convertHuetoRgb($vala, $valb, $this->cmp_hue),
            'blue'  => $this->convertHuetoRgb($vala, $valb, ($this->cmp_hue - (1 / 3))),
            'alpha' => $this->cmp_alpha
        );
    }

    /**
     * Convet Hue to RGB
     *
     * @param float $vala Temporary value A
     * @param float $valb Temporary value B
     * @param float $hue  Hue value
     *
     * @return float
     */
    private function convertHuetoRgb($vala, $valb, $hue)
    {
        if ($hue < 0) {
            $hue += 1;
        }
        if ($hue > 1) {
            $hue -= 1;
        }
        if ((6 * $hue) < 1) {
            return max(0, min(1, ($vala + (($valb - $vala) * 6 * $hue))));
        }
        if ((2 * $hue) < 1) {
            return max(0, min(1, $valb));
        }
        if ((3 * $hue) < 2) {
            return max(0, min(1, ($vala + (($valb - $vala) * ((2 / 3) - $hue) * 6))));
        }
        return max(0, min(1, $vala));
    }

    /**
     * Get an array with HSL color components
     *
     * @return array with keys ('hue', 'saturation', 'lightness', 'alpha')
     */
    public function toHslArray()
    {
        return array(
            'hue'        => $this->cmp_hue,
            'saturation' => $this->cmp_saturation,
            'lightness'  => $this->cmp_lightness,
            'alpha'      => $this->cmp_alpha
        );
    }

    /**
     * Get an array with CMYK color components
     *
     * @return array with keys ('cyan', 'magenta', 'yellow', 'key', 'alpha')
     */
    public function toCmykArray()
    {
        $rgb = new \Com\Tecnick\Color\Model\Rgb($this->toRgbArray());
        return $rgb->toCmykArray();
    }

    /**
     * Invert the color
     */
    public function invertColor()
    {
        $this->cmp_hue = ($this->cmp_hue >= 0.5) ? ($this->cmp_hue - 0.5) : ($this->cmp_hue + 0.5);
        return $this;
    }
}
