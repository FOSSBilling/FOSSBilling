<?php
/**
 * Css.php
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

/**
 * Com\Tecnick\Color\Css
 *
 * Css Color class
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Color
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-color
 */
abstract class Css
{
    /**
     * Get the color object from acrobat Javascript syntax
     *
     * @param string $color color specification (e.g.: ["RGB",0.1,0.3,1])
     *
     * @return \Com\Tecnick\Color\Model or null for transparent color
     *
     * @throws ColorException if the color is not found
     */
    protected function getColorObjFromJs($color)
    {
        if (!isset($color[2]) || (strpos('tgrc', $color[2]) === false)) {
             throw new ColorException('invalid javascript color: '.$color);
        }
        switch ($color[2]) {
            case 'g':
                $rex = '/[\[][\"\']g[\"\'][\,]([0-9\.]+)[\]]/';
                if (preg_match($rex, $color, $col) !== 1) {
                    throw new ColorException('invalid javascript color: '.$color);
                }
                return new \Com\Tecnick\Color\Model\Gray(array('gray' => $col[1], 'alpha' => 1));
            case 'r':
                $rex = '/[\[][\"\']rgb[\"\'][\,]([0-9\.]+)[\,]([0-9\.]+)[\,]([0-9\.]+)[\]]/';
                if (preg_match($rex, $color, $col) !== 1) {
                    throw new ColorException('invalid javascript color: '.$color);
                }
                return new \Com\Tecnick\Color\Model\Rgb(
                    array(
                        'red'   => $col[1],
                        'green' => $col[2],
                        'blue'  => $col[3],
                        'alpha' => 1
                    )
                );
            case 'c':
                $rex = '/[\[][\"\']cmyk[\"\'][\,]([0-9\.]+)[\,]([0-9\.]+)[\,]([0-9\.]+)[\,]([0-9\.]+)[\]]/';
                if (preg_match($rex, $color, $col) !== 1) {
                    throw new ColorException('invalid javascript color: '.$color);
                }
                return new \Com\Tecnick\Color\Model\Cmyk(
                    array(
                        'cyan'    => $col[1],
                        'magenta' => $col[2],
                        'yellow'  => $col[3],
                        'key'     => $col[4],
                        'alpha'   => 1
                    )
                );
        }
        // case 't'
        return null;
    }

    /**
     * Get the color object from a CSS color string
     *
     * @param string $type  color type: t, g, rgb, rgba, hsl, hsla, cmyk
     * @param string $color color specification (e.g.: rgb(255,128,64))
     *
     * @return \Com\Tecnick\Color\Model or null for transparency (default)
     *
     * @throws ColorException if the color is not found
     */
    protected function getColorObjFromCss($type, $color)
    {
        switch ($type) {
            case 'g':
                return $this->getColorObjFromCssGray($color);
            case 'rgb':
            case 'rgba':
                return $this->getColorObjFromCssRgb($color);
            case 'hsl':
            case 'hsla':
                return $this->getColorObjFromCssHsl($color);
            case 'cmyk':
            case 'cmyka':
                return $this->getColorObjFromCssCmyk($color);
        }
        // case 't'
        return null;
    }

    /**
     * Get the color object from a CSS CMYK color string
     *
     * @param string $color color specification (e.g.: rgb(255,128,64))
     *
     * @return \Com\Tecnick\Color\Model
     *
     * @throws ColorException if the color is not found
     */
    private function getColorObjFromCssGray($color)
    {
        $rex = '/[\(]([0-9\%]+)[\)]/';
        if (preg_match($rex, $color, $col) !== 1) {
            throw new ColorException('invalid css color: '.$color);
        }
        return new \Com\Tecnick\Color\Model\Gray(
            array(
                'gray' => $this->normalizeValue($col[1], 255),
                'alpha' => 1
            )
        );
    }

    /**
     * Get the color object from a CSS CMYK color string
     *
     * @param string $color color specification (e.g.: rgb(255,128,64))
     *
     * @return \Com\Tecnick\Color\Model
     *
     * @throws ColorException if the color is not found
     */
    private function getColorObjFromCssRgb($color)
    {
        $rex = '/[\(]([0-9\%]+)[\,]([0-9\%]+)[\,]([0-9\%]+)[\,]?([0-9\.]*)[\)]/';
        if (preg_match($rex, $color, $col) !== 1) {
            throw new ColorException('invalid css color: '.$color);
        }
        return new \Com\Tecnick\Color\Model\Rgb(
            array(
                'red'   => $this->normalizeValue($col[1], 255),
                'green' => $this->normalizeValue($col[2], 255),
                'blue'  => $this->normalizeValue($col[3], 255),
                'alpha' => (isset($col[4][0]) ? $col[4] : 1)
            )
        );
    }

    /**
     * Get the color object from a CSS CMYK color string
     *
     * @param string $color color specification (e.g.: rgb(255,128,64))
     *
     * @return \Com\Tecnick\Color\Model
     *
     * @throws ColorException if the color is not found
     */
    private function getColorObjFromCssHsl($color)
    {
        $rex = '/[\(]([0-9\%]+)[\,]([0-9\%]+)[\,]([0-9\%]+)[\,]?([0-9\.]*)[\)]/';
        if (preg_match($rex, $color, $col) !== 1) {
            throw new ColorException('invalid css color: '.$color);
        }
        return new \Com\Tecnick\Color\Model\Hsl(
            array(
                'hue'        => $this->normalizeValue($col[1], 360),
                'saturation' => $this->normalizeValue($col[2], 1),
                'lightness'  => $this->normalizeValue($col[3], 1),
                'alpha'      => (isset($col[4][0]) ? $col[4] : 1)
            )
        );
    }

    /**
     * Get the color object from a CSS CMYK color string
     *
     * @param string $color color specification (e.g.: rgb(255,128,64))
     *
     * @return \Com\Tecnick\Color\Model
     *
     * @throws ColorException if the color is not found
     */
    private function getColorObjFromCssCmyk($color)
    {
        $rex = '/[\(]([0-9\%]+)[\,]([0-9\%]+)[\,]([0-9\%]+)[\,]([0-9\%]+)[\,]?([0-9\.]*)[\)]/';
        if (preg_match($rex, $color, $col) !== 1) {
            throw new ColorException('invalid css color: '.$color);
        }
        return new \Com\Tecnick\Color\Model\Cmyk(
            array(
                'cyan'    => $this->normalizeValue($col[1], 100),
                'magenta' => $this->normalizeValue($col[2], 100),
                'yellow'  => $this->normalizeValue($col[3], 100),
                'key'     => $this->normalizeValue($col[4], 100),
                'alpha'   => (isset($col[5][0]) ? $col[5] : 1)
            )
        );
    }
}
