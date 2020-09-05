<?php
/**
 * Web.php
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
 * Com\Tecnick\Color\Web
 *
 * Web Color class
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Color
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-color
 */
class Web extends \Com\Tecnick\Color\Css
{
    /**
     * Array of WEB safe colors #RRGGBBAA
     *
     * @var array
     */
    protected static $webhex = array(
        'aliceblue'            => 'f0f8ffff',
        'antiquewhite'         => 'faebd7ff',
        'aqua'                 => '00ffffff',
        'aquamarine'           => '7fffd4ff',
        'azure'                => 'f0ffffff',
        'beige'                => 'f5f5dcff',
        'bisque'               => 'ffe4c4ff',
        'black'                => '000000ff',
        'blanchedalmond'       => 'ffebcdff',
        'blue'                 => '0000ffff',
        'blueviolet'           => '8a2be2ff',
        'brown'                => 'a52a2aff',
        'burlywood'            => 'deb887ff',
        'cadetblue'            => '5f9ea0ff',
        'chartreuse'           => '7fff00ff',
        'chocolate'            => 'd2691eff',
        'coral'                => 'ff7f50ff',
        'cornflowerblue'       => '6495edff',
        'cornsilk'             => 'fff8dcff',
        'crimson'              => 'dc143cff',
        'cyan'                 => '00ffffff',
        'darkblue'             => '00008bff',
        'darkcyan'             => '008b8bff',
        'darkgoldenrod'        => 'b8860bff',
        'dkgray'               => 'a9a9a9ff',
        'darkgray'             => 'a9a9a9ff',
        'darkgrey'             => 'a9a9a9ff',
        'darkgreen'            => '006400ff',
        'darkkhaki'            => 'bdb76bff',
        'darkmagenta'          => '8b008bff',
        'darkolivegreen'       => '556b2fff',
        'darkorange'           => 'ff8c00ff',
        'darkorchid'           => '9932ccff',
        'darkred'              => '8b0000ff',
        'darksalmon'           => 'e9967aff',
        'darkseagreen'         => '8fbc8fff',
        'darkslateblue'        => '483d8bff',
        'darkslategray'        => '2f4f4fff',
        'darkslategrey'        => '2f4f4fff',
        'darkturquoise'        => '00ced1ff',
        'darkviolet'           => '9400d3ff',
        'deeppink'             => 'ff1493ff',
        'deepskyblue'          => '00bfffff',
        'dimgray'              => '696969ff',
        'dimgrey'              => '696969ff',
        'dodgerblue'           => '1e90ffff',
        'firebrick'            => 'b22222ff',
        'floralwhite'          => 'fffaf0ff',
        'forestgreen'          => '228b22ff',
        'fuchsia'              => 'ff00ffff',
        'gainsboro'            => 'dcdcdcff',
        'ghostwhite'           => 'f8f8ffff',
        'gold'                 => 'ffd700ff',
        'goldenrod'            => 'daa520ff',
        'gray'                 => '808080ff',
        'grey'                 => '808080ff',
        'green'                => '008000ff',
        'greenyellow'          => 'adff2fff',
        'honeydew'             => 'f0fff0ff',
        'hotpink'              => 'ff69b4ff',
        'indianred'            => 'cd5c5cff',
        'indigo'               => '4b0082ff',
        'ivory'                => 'fffff0ff',
        'khaki'                => 'f0e68cff',
        'lavender'             => 'e6e6faff',
        'lavenderblush'        => 'fff0f5ff',
        'lawngreen'            => '7cfc00ff',
        'lemonchiffon'         => 'fffacdff',
        'lightblue'            => 'add8e6ff',
        'lightcoral'           => 'f08080ff',
        'lightcyan'            => 'e0ffffff',
        'lightgoldenrodyellow' => 'fafad2ff',
        'ltgray'               => 'd3d3d3ff',
        'lightgray'            => 'd3d3d3ff',
        'lightgrey'            => 'd3d3d3ff',
        'lightgreen'           => '90ee90ff',
        'lightpink'            => 'ffb6c1ff',
        'lightsalmon'          => 'ffa07aff',
        'lightseagreen'        => '20b2aaff',
        'lightskyblue'         => '87cefaff',
        'lightslategray'       => '778899ff',
        'lightslategrey'       => '778899ff',
        'lightsteelblue'       => 'b0c4deff',
        'lightyellow'          => 'ffffe0ff',
        'lime'                 => '00ff00ff',
        'limegreen'            => '32cd32ff',
        'linen'                => 'faf0e6ff',
        'magenta'              => 'ff00ffff',
        'maroon'               => '800000ff',
        'mediumaquamarine'     => '66cdaaff',
        'mediumblue'           => '0000cdff',
        'mediumorchid'         => 'ba55d3ff',
        'mediumpurple'         => '9370d8ff',
        'mediumseagreen'       => '3cb371ff',
        'mediumslateblue'      => '7b68eeff',
        'mediumspringgreen'    => '00fa9aff',
        'mediumturquoise'      => '48d1ccff',
        'mediumvioletred'      => 'c71585ff',
        'midnightblue'         => '191970ff',
        'mintcream'            => 'f5fffaff',
        'mistyrose'            => 'ffe4e1ff',
        'moccasin'             => 'ffe4b5ff',
        'navajowhite'          => 'ffdeadff',
        'navy'                 => '000080ff',
        'oldlace'              => 'fdf5e6ff',
        'olive'                => '808000ff',
        'olivedrab'            => '6b8e23ff',
        'orange'               => 'ffa500ff',
        'orangered'            => 'ff4500ff',
        'orchid'               => 'da70d6ff',
        'palegoldenrod'        => 'eee8aaff',
        'palegreen'            => '98fb98ff',
        'paleturquoise'        => 'afeeeeff',
        'palevioletred'        => 'd87093ff',
        'papayawhip'           => 'ffefd5ff',
        'peachpuff'            => 'ffdab9ff',
        'peru'                 => 'cd853fff',
        'pink'                 => 'ffc0cbff',
        'plum'                 => 'dda0ddff',
        'powderblue'           => 'b0e0e6ff',
        'purple'               => '800080ff',
        'red'                  => 'ff0000ff',
        'rosybrown'            => 'bc8f8fff',
        'royalblue'            => '4169e1ff',
        'saddlebrown'          => '8b4513ff',
        'salmon'               => 'fa8072ff',
        'sandybrown'           => 'f4a460ff',
        'seagreen'             => '2e8b57ff',
        'seashell'             => 'fff5eeff',
        'sienna'               => 'a0522dff',
        'silver'               => 'c0c0c0ff',
        'skyblue'              => '87ceebff',
        'slateblue'            => '6a5acdff',
        'slategray'            => '708090ff',
        'slategrey'            => '708090ff',
        'snow'                 => 'fffafaff',
        'springgreen'          => '00ff7fff',
        'steelblue'            => '4682b4ff',
        'tan'                  => 'd2b48cff',
        'teal'                 => '008080ff',
        'thistle'              => 'd8bfd8ff',
        'tomato'               => 'ff6347ff',
        'turquoise'            => '40e0d0ff',
        'violet'               => 'ee82eeff',
        'wheat'                => 'f5deb3ff',
        'white'                => 'ffffffff',
        'whitesmoke'           => 'f5f5f5ff',
        'yellow'               => 'ffff00ff',
        'yellowgreen'          => '9acd32ff'
    );

    /**
     * Get the color map (name => hexhash)
     *
     * @return array
     */
    public function getMap()
    {
        return self::$webhex;
    }

    /**
     * Get the color hexadecimal hash code from name
     *
     * @param string $name Name of the color to search (e.g.: 'turquoise')
     *
     * @return string color hexadecimal code (e.g.: '40e0d0ff')
     *
     * @throws ColorException if the color is not found
     */
    public function getHexFromName($name)
    {
        $name = strtolower($name);
        if (($dotpos = strpos($name, '.')) !== false) {
            // remove parent name (i.e.: color.green)
            $name = substr($name, ($dotpos + 1));
        }
        if (empty(self::$webhex[$name])) {
            throw new ColorException('unable to find the color hex for the name: '.$name);
        }
        return self::$webhex[$name];
    }

    /**
     * Get the color name code from hexadecimal hash
     *
     * @param string $hex hexadecimal color hash (i.e. #RRGGBBAA)
     *
     * @return string color name
     *
     * @throws ColorException if the color is not found
     */
    public function getNameFromHex($hex)
    {
        $name = array_search($this->extractHexCode($hex), self::$webhex, true);
        if ($name === false) {
            throw new ColorException('unable to find the color name for the hex code: '.$hex);
        }
        return $name;
    }

    /**
     * Extract the hexadecimal code from the input string and add the alpha channel if missing
     *
     * @param string $hex string containing the hexadecimal color hash (i.e. #RGB, #RGBA, #RRGGBB, #RRGGBBAA)
     *
     * @return string the hash code (e.g.: '40e0d0')
     *
     * @throws ColorException if the hash is not found or has an invalid format
     */
    public function extractHexCode($hex)
    {
        if (preg_match('/^[#]?([0-9a-f]{3,8})$/', strtolower($hex), $match) !== 1) {
            throw new ColorException('unable to extract the color hash: '.$hex);
        }
        $hex = $match[1];
        switch (strlen($hex)) {
            case 3:
                return $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2].'ff';
            case 4:
                return $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2].$hex[3].$hex[3];
            case 6:
                return $hex.'ff';
        }
        return $hex;
    }

    /**
     * Get the RGB color object from hexadecimal hash
     *
     * @param string $hex hexadecimal color hash (i.e. #RGB, #RGBA, #RRGGBB, #RRGGBBAA)
     *
     * @return array with keys ('red', 'green', 'blue', 'alpha')
     *
     * @throws ColorException if the color is not found
     */
    public function getRgbObjFromHex($hex)
    {
        return new \Com\Tecnick\Color\Model\Rgb(
            $this->getHexArray(
                $this->extractHexCode($hex)
            )
        );
    }

    /**
     * Get the RGB color object from color name
     *
     * @param string $name Color name
     *
     * @return \Com\Tecnick\Color\Model\Rgb object
     *
     * @throws ColorException if the color is not found
     */
    public function getRgbObjFromName($name)
    {
        return new \Com\Tecnick\Color\Model\Rgb(
            $this->getHexArray(
                $this->getHexFromName($name)
            )
        );
    }

    /**
     * Get the RGB array from hexadecimal hash
     *
     * @param string $hex hexadecimal color hash (i.e. RRGGBBAA)
     *
     * @return array with keys ('red', 'green', 'blue', 'alpha')
     */
    private function getHexArray($hex)
    {
        return array(
            'red'   => (hexdec(substr($hex, 0, 2)) / 255),
            'green' => (hexdec(substr($hex, 2, 2)) / 255),
            'blue'  => (hexdec(substr($hex, 4, 2)) / 255),
            'alpha' => (hexdec(substr($hex, 6, 2)) / 255),
        );
    }

    /**
     * Get the normalized integer value from [0..$max] to [0..1]
     *
     * @param float $value Value to convert
     * @param int   $max   Max input value
     *
     * @return float value [0..1]
     */
    public function normalizeValue($value, $max)
    {
        if (strpos($value, '%') !== false) {
            return max(0, min(1, (floatval($value) / 100)));
        }
        return max(0, min(1, (floatval($value) / $max)));
    }

    /**
     * Parse the input color string and return the correspondent color Object
     *
     * @param string $color String containing web color definition
     *
     * @return \Com\Tecnick\Color\Model or null in case of transparent color
     *
     * @throws ColorException in case of error
     */
    public function getColorObj($color)
    {
        $color = preg_replace('/[\s]*/', '', strtolower($color));
        if (empty($color) || (strpos($color, 'transparent') !== false)) {
            return null;
        }
        if ($color[0] === '#') {
            return $this->getRgbObjFromHex($color);
        }
        if ($color[0] === '[') {
            return $this->getColorObjFromJs($color);
        }
        $rex = '/^(t|g|rgba|rgb|hsla|hsl|cmyka|cmyk)[\(]/';
        if (preg_match($rex, $color, $col) === 1) {
            return $this->getColorObjFromCss($col[1], $color);
        }
        return $this->getRgbObjFromName($color);
    }

    /**
     * Get the square of the distance between 2 RGB points
     *
     * @param array $cola First color as RGB array
     * @param array $colb Second color as RGB array
     *
     * @return float
     */
    public function getRgbSquareDistance($cola, $colb)
    {
        return (pow(($cola['red'] - $colb['red']), 2)
            + pow(($cola['green'] - $colb['green']), 2)
            + pow(($cola['blue'] - $colb['blue']), 2));
    }

    /**
     * Get the name of the closest web color
     *
     * @param array $col Color as RGB array (keys: 'red', 'green', 'blue')
     *
     * @return string
     */
    public function getClosestWebColor($col)
    {
        $color = '';
        $mindist = 3; // = 1^2 + 1^2 + 1^2
        foreach (self::$webhex as $name => $hex) {
            $dist = $this->getRgbSquareDistance($col, $this->getHexArray($hex));
            if ($dist <= $mindist) {
                $mindist = $dist;
                $color = $name;
            }
        }
        return $color;
    }
}
