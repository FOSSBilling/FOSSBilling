<?php
/**
 * Spot.php
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
use \Com\Tecnick\Color\Model\Cmyk;

/**
 * Com\Tecnick\Color\Spot
 *
 * Spot Color class
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Color
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-color
 */
class Spot extends \Com\Tecnick\Color\Web
{
    /**
     * Array of default Spot colors
     * Color keys must be in lowercase and without spaces.
     *
     * @var array
     */
    protected static $default_spot_colors = array (
        'none' => array('name' => 'None',
            'color' => array('cyan' => 0, 'magenta' => 0, 'yellow' => 0, 'key' => 0, 'alpha' => 1)),
        'all' => array('name' => 'All',
            'color' => array('cyan' => 1, 'magenta' => 1, 'yellow' => 1, 'key' => 1, 'alpha' => 1)),
        'cyan' => array('name' => 'Cyan',
            'color' => array('cyan' => 1, 'magenta' => 0, 'yellow' => 0, 'key' => 0, 'alpha' => 1)),
        'magenta' => array('name' => 'Magenta',
            'color' => array('cyan' => 0, 'magenta' => 1, 'yellow' => 0, 'key' => 0, 'alpha' => 1)),
        'yellow' => array('name' => 'Yellow',
            'color' => array('cyan' => 0, 'magenta' => 0, 'yellow' => 1, 'key' => 0, 'alpha' => 1)),
        'key' => array('name' => 'Key',
            'color' => array('cyan' => 0, 'magenta' => 0, 'yellow' => 0, 'key' => 1, 'alpha' => 1)),
        'white' => array('name' => 'White',
            'color' => array('cyan' => 0, 'magenta' => 0, 'yellow' => 0, 'key' => 0, 'alpha' => 1)),
        'black' => array('name' => 'Black',
            'color' => array('cyan' => 0, 'magenta' => 0, 'yellow' => 0, 'key' => 1, 'alpha' => 1)),
        'red' => array('name' => 'Red',
            'color' => array('cyan' => 0, 'magenta' => 1, 'yellow' => 1, 'key' => 0, 'alpha' => 1)),
        'green' => array('name' => 'Green',
            'color' => array('cyan' => 1, 'magenta' => 0, 'yellow' => 1, 'key' => 0, 'alpha' => 1)),
        'blue' => array('name' => 'Blue',
            'color' => array('cyan' => 1, 'magenta' => 1, 'yellow' => 0, 'key' => 0, 'alpha' => 1)),
    );
    
    /**
     * Array of Spot colors
     *
     * @var array
     */
    protected $spot_colors = array();

    /**
     * Returns the array of spot colors.
     *
     * @return array Spot colors array.
     */
    public function getSpotColors()
    {
        return $this->spot_colors;
    }

    /**
     * Return the normalized version of the spot color name
     *
     * @param string $name Full name of the spot color.
     *
     * @return string
     */
    public function normalizeSpotColorName($name)
    {
        return preg_replace('/[^a-z0-9]*/', '', strtolower($name));
    }

    /**
     * Return the requested spot color data array
     *
     * @param string $name Full name of the spot color.
     *
     * @return array
     *
     * @throws ColorException if the color is not found
     */
    public function getSpotColor($name)
    {
        $key = $this->normalizeSpotColorName($name);
        if (empty($this->spot_colors[$key])) {
            // search on default spot colors
            if (empty(self::$default_spot_colors[$key])) {
                throw new ColorException('unable to find the spot color: '.$key);
            }
            $this->addSpotColor($key, new Cmyk(self::$default_spot_colors[$key]['color']));
        }
        return $this->spot_colors[$key];
    }

    /**
     * Return the requested spot color CMYK object
     *
     * @param string $name Full name of the spot color.
     *
     * @return \Com\Tecnick\Color\Web\Model\Cmyk
     *
     * @throws ColorException if the color is not found
     */
    public function getSpotColorObj($name)
    {
        $spot = $this->getSpotColor($name);
        return $spot['color'];
    }

    /**
     * Add a new spot color or overwrite an existing one with the same name.
     *
     * @param string $name  Full name of the spot color.
     * @param Cmyk   $color CMYK color object
     */
    public function addSpotColor($name, Cmyk $color)
    {
        $key = $this->normalizeSpotColorName($name);
        if (isset($this->spot_colors[$key])) {
            $num = $this->spot_colors[$key]['i'];
        } else {
            $num = (count($this->spot_colors) + 1);
        }
        $this->spot_colors[$key] = array(
            'i'     => $num,   // color index
            'n'     => 0,      // PDF object number
            'name'  => $name,  // color name (key)
            'color' => $color, // CMYK color object
        );
    }

    /**
     * Returns the PDF command to output Spot color objects.
     *
     * @param int $pon Current PDF object number
     *
     * @return string PDF command
     */
    public function getPdfSpotObjects(&$pon)
    {
        $out = '';
        foreach ($this->spot_colors as $name => $color) {
            $out .= (++$pon).' 0 obj'."\n";
            $this->spot_colors[$name]['n'] = $pon;
            $out .= '[/Separation /'.str_replace(' ', '#20', $name)
                .' /DeviceCMYK <<'
                .'/Range [0 1 0 1 0 1 0 1]'
                .' /C0 [0 0 0 0]'
                .' /C1 ['.$color['color']->getComponentsString().']'
                .' /FunctionType 2'
                .' /Domain [0 1]'
                .' /N 1'
                .'>>]'."\n"
                .'endobj'."\n";
        }
        return $out;
    }

    /**
     * Returns the PDF command to output Spot color resources.
     *
     * @return string PDF command
     */
    public function getPdfSpotResources()
    {
        if (empty($this->spot_colors)) {
            return '';
        }
        $out = '/ColorSpace << ';
        foreach ($this->spot_colors as $color) {
            $out .= '/CS'.$color['i'].' '.$color['n'].' 0 R ';
        }
        $out .= '>>'."\n";
        return $out;
    }
}
