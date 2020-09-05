<?php
/**
 * Gradient.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfGraph
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-graph
 *
 * This file is part of tc-lib-pdf-graph software library.
 */

namespace Com\Tecnick\Pdf\Graph;

use \Com\Tecnick\Pdf\Graph\Exception as GraphException;

/**
 * Com\Tecnick\Pdf\Graph\Gradient
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfGraph
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-graph
 */
abstract class Gradient extends \Com\Tecnick\Pdf\Graph\Raw
{
    /**
     * Array of gradients
     *
     * @var array
     */
    protected $gradients = array();

    /**
     * Returns the gradients array
     *
     * @return array
     */
    public function getGradientsArray()
    {
        return $this->gradients;
    }

    /**
     * Get a linear colour gradient command.
     *
     * @param float  $posx       Abscissa of the top left corner of the rectangle.
     * @param float  $posy       Ordinate of the top left corner of the rectangle.
     * @param float  $width      Width of the rectangle.
     * @param float  $height     Height of the rectangle.
     * @param string $colorstart Starting color.
     * @param string $colorend   Ending color.
     * @param array  $coords     Gradient vector (x1, y1, x2, y2).
     *
     * @return string PDF command
     */
    public function getLinearGradient(
        $posx,
        $posy,
        $width,
        $height,
        $colorstart,
        $colorend,
        $coords = array(0,0,1,0)
    ) {
        return $this->getStartTransform()
        .$this->getClippingRect($posx, $posy, $width, $height)
        .$this->getGradientTransform($posx, $posy, $width, $height)
        .$this->getGradient(
            2,
            $coords,
            array(
                array(
                    'color' => $colorstart,
                    'offset' => 0,
                    'exponent' => 1
                ),
                array(
                    'color' => $colorend,
                    'offset' => 1,
                    'exponent' => 1
                )
            ),
            '',
            false
        )
        .$this->getStopTransform();
    }

    /**
     * Get a radial colour gradient command.
     *
     * @param float  $posx       Abscissa of the top left corner of the rectangle.
     * @param float  $posy       Ordinate of the top left corner of the rectangle.
     * @param float  $width      Width of the rectangle.
     * @param float  $height     Height of the rectangle.
     * @param string $colorstart Starting color.
     * @param string $colorend   Ending color.
     * @param array  $coords     Array of the form (fx, fy, cx, cy, r) where
     *                           (fx, fy) is the starting point of the gradient with $colorstart (be inside the circle),
     *                           (cx, cy) is the center of the circle with $colorend,
     *                           and r is the radius of the circle.
     *
     * @return string PDF command
     */
    public function getRadialGradient(
        $posx,
        $posy,
        $width,
        $height,
        $colorstart,
        $colorend,
        $coords = array(0.5,0.5,0.5,0.5,1)
    ) {
        return $this->getStartTransform()
        .$this->getClippingRect($posx, $posy, $width, $height)
        .$this->getGradientTransform($posx, $posy, $width, $height)
        .$this->getGradient(
            3,
            $coords,
            array(
                array(
                    'color' => $colorstart,
                    'offset' => 0,
                    'exponent' => 1
                ),
                array(
                    'color' => $colorend,
                    'offset' => 1,
                    'exponent' => 1
                )
            ),
            '',
            false
        )
        .$this->getStopTransform();
    }

    /**
     * Rectangular clipping area.
     *
     * @param float $posx   Abscissa of the top left corner of the rectangle.
     * @param float $posy   Ordinate of the top left corner of the rectangle.
     * @param float $width  Width of the rectangle.
     * @param float $height Height of the rectangle.
     *
     * @return string
     */
    public function getClippingRect($posx, $posy, $width, $height)
    {
        return sprintf(
            '%F %F %F %F re W n'."\n",
            ($posx * $this->kunit),
            (($this->pageh - $posy) * $this->kunit),
            ($width * $this->kunit),
            (-$height * $this->kunit)
        );
    }

    /**
     * Rectangular clipping area.
     *
     * @param float $posx   Abscissa of the top left corner of the rectangle.
     * @param float $posy   Ordinate of the top left corner of the rectangle.
     * @param float $width  Width of the rectangle.
     * @param float $height Height of the rectangle.
     *
     * @return string
     */
    public function getGradientTransform($posx, $posy, $width, $height)
    {
        $ctm = array(
            ($width * $this->kunit),
            0,
            0,
            ($height * $this->kunit),
            ($posx * $this->kunit),
            (($this->pageh - ($posy + $height)) * $this->kunit)
        );
        return $this->getTransformation($ctm);
    }

    /**
     * Get a color gradient command.
     *
     * @param int   $type       Type of gradient (Not all types are currently supported):
     *                          1 = Function-based shading;
     *                          2 = Axial shading;
     *                          3 = Radial shading;
     *                          4 = Free-form Gouraud-shaded triangle mesh;
     *                          5 = Lattice-form Gouraud-shaded triangle mesh;
     *                          6 = Coons patch mesh; 7 Tensor-product patch mesh
     * @param array $coords     Array of coordinates.
     * @param array $stops      Array gradient color components:
     *                          color = color;
     *                          offset = (0 to 1) represents a location along the gradient vector;
     *                          exponent = exponent of the exponential interpolation function (default = 1).
     * @param string $bgcolor   Background color
     * @param bool   $antialias Flag indicating whether to filter the shading function to prevent aliasing artifacts.
     *
     * @return string PDF command
     */
    public function getGradient($type, $coords, $stops, $bgcolor, $antialias = false)
    {
        if ($this->pdfa) {
            return '';
        }

        $map_colspace = array('CMYK' => 'DeviceCMYK', 'RGB' => 'DeviceRGB', 'GRAY' => 'DeviceGray');
        $ngr = (1 + count($this->gradients));
        $this->gradients[$ngr] = $this->getGradientStops(
            array(
                'type' => $type,
                'coords' => $coords,
                'antialias' => $antialias,
                'colors' => array(),
                'transparency' => false,
                'background' => $this->col->getColorObject($bgcolor),
                'colspace' => $map_colspace[$this->col->getColorObject($stops[0]['color'])->getType()],
            ),
            $stops
        );
        
        $out = '';
        if ($this->gradients[$ngr]['transparency']) {
            // paint luminosity gradient
            $out .= '/TGS'.$ngr.' gs'."\n";
        }
        // paint the gradient
        $out .= '/Sh'.$ngr.' sh'."\n";

        return $out;
    }

    /**
     * Process the gradient stops.
     *
     * @param array $grad       Array containing gradient info
     * @param array $stops      Array gradient color components:
     *                          color = color;
     *                          offset = (0 to 1) represents a location along the gradient vector;
     *                          exponent = exponent of the exponential interpolation function (default = 1).
     *
     * @return array Gradient array.
     */
    protected function getGradientStops($grad, $stops)
    {
        $num_stops = count($stops);
        $last_stop_id = ($num_stops - 1);

        foreach ($stops as $key => $stop) {
            $grad['colors'][$key] = array();
            $grad['colors'][$key]['color'] = $stop['color'];
            $grad['colors'][$key]['exponent'] = 1;
            if (isset($stop['exponent'])) {
                // exponent for the interpolation function
                $grad['colors'][$key]['exponent'] = $stop['exponent'];
            }
            $grad['colors'][$key]['opacity'] = 1;
            if (isset($stop['opacity'])) {
                $grad['colors'][$key]['opacity'] = $stop['opacity'];
                $grad['transparency'] = ($grad['transparency'] || ($stop['opacity'] < 1));
            }
            // offset represents a location along the gradient vector
            if (isset($stop['offset'])) {
                $grad['colors'][$key]['offset'] = $stop['offset'];
            } else {
                if ($key == 0) {
                    $grad['colors'][$key]['offset'] = 0;
                } elseif ($key == $last_stop_id) {
                    $grad['colors'][$key]['offset'] = 1;
                } else {
                    $offsetstep = ((1 - $grad['colors'][($key - 1)]['offset']) / ($num_stops - $key));
                    $grad['colors'][$key]['offset'] = ($grad['colors'][($key - 1)]['offset'] + $offsetstep);
                }
            }
        }

        return $grad;
    }

    /**
     * Paints a coons patch mesh.
     *
     * @param float $posx   Abscissa of the top left corner of the rectangle.
     * @param float $posy   Ordinate of the top left corner of the rectangle.
     * @param float $width  Width of the rectangle.
     * @param float $height Height of the rectangle.
     * @param string $colll Lower-Left corner color.
     * @param string $collr Lower-Right corner color.
     * @param string $colur Upper-Right corner color.
     * @param string $colul Upper-Left corner color.
     * @param array $coords For one patch mesh:
     *                      array(float x1, float y1, .... float x12, float y12):
     *                      12 pairs of coordinates (normally from 0 to 1)
     *                      which specify the Bezier control points that define the patch.
     *                      First pair is the lower left edge point,
     *                      next is its right control point (control point 2).
     *                      Then the other points are defined in the order:
     *                      control point 1, edge point, control point 2 going counter-clockwise around the patch.
     *                      Last (x12, y12) is the first edge point's left control point (control point 1).
     *                      For two or more patch meshes:
     *                      array[number of patches] - arrays with the following keys for each patch:
     *                      f: where to put that patch (0 = first patch, 1, 2, 3 = right, top and left)
     *                      points: 12 pairs of coordinates of the Bezier control points as above for the first patch,
     *                      8 pairs of coordinates for the following patches,
     *                      ignoring the coordinates already defined by the precedent patch
     *                      colors: must be 4 colors for the first patch, 2 colors for the following patches
     * @param array $coords_min Minimum value used by the coordinates.
     *                          If a coordinate's value is smaller than this it will be cut to coords_min.
     * @param array $coords_max Maximum value used by the coordinates.
     *                          If a coordinate's value is greater than this it will be cut to coords_max.
     * @param boolean $antialias Flag indicating whether to filter the shading function to prevent aliasing artifacts.
     *
     * @return string PDF command
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function getCoonsPatchMesh(
        $posx,
        $posy,
        $width,
        $height,
        $colll = 'yellow',
        $collr = 'blue',
        $colur = 'green',
        $colul = 'red',
        $coords = array(
            0.00,0.00, 0.33,0.00, 0.67,0.00, 1.00,0.00, 1.00,0.33, 1.00,0.67,
            1.00,1.00, 0.67,1.00, 0.33,1.00, 0.00,1.00, 0.00,0.67, 0.00,0.33
        ),
        $coords_min = 0,
        $coords_max = 1,
        $antialias = false
    ) {
        if ($this->pdfa) {
            return '';
        }
        
        $ngr = (1 + count($this->gradients));
        $this->gradients[$ngr] = array(
            'type' => 6, //coons patch mesh
            'coords' => array(),
            'antialias' => $antialias,
            'colors' => array(),
            'transparency' => false,
            'background' => '',
            'colspace' => 'DeviceRGB',
        );

        // check the coords array if it is the simple array or the multi patch array
        if (!isset($coords[0]['f'])) {
            // simple array -> convert to multi patch array
            $patch_array[0]['f'] = 0;
            $patch_array[0]['points'] = $coords;
            $patch_array[0]['colors'][0] = $this->col->getColorObject($colll)->toRgbArray();
            $patch_array[0]['colors'][1] = $this->col->getColorObject($collr)->toRgbArray();
            $patch_array[0]['colors'][2] = $this->col->getColorObject($colur)->toRgbArray();
            $patch_array[0]['colors'][3] = $this->col->getColorObject($colul)->toRgbArray();
        } else {
            // multi patch array
            $patch_array = $coords;
        }

        $bpcd = 65535; //16 bits per coordinate
        //build the data stream
        $this->gradients[$ngr]['stream'] = '';
 
        foreach ($patch_array as $par) {
            $this->gradients[$ngr]['stream'] .= chr($par['f']); // start with the edge flag as 8 bit
            foreach ($par['points'] as $point) {
                //each point as 16 bit
                $point = max(0, min(
                    $bpcd,
                    ((($point - $coords_min) / ($coords_max - $coords_min)) * $bpcd)
                ));
                $this->gradients[$ngr]['stream'] .= chr(floor($point / 256)).chr(floor($point % 256));
            }
            foreach ($par['colors'] as $color) {
                //each color component as 8 bit
                $this->gradients[$ngr]['stream'] .= chr($color['red']).chr($color['green']).chr($color['blue']);
            }
        }

        return $this->getStartTransform()
            .$this->getClippingRect($posx, $posy, $width, $height)
            .$this->getGradientTransform($posx, $posy, $width, $height)
            .'/Sh'.$ngr.' sh'."\n"
            .$this->getStopTransform();
    }

    /**
     * Paints registration bars with color transtions
     *
     * @param float   $posx       Abscissa of the top left corner of the rectangle.
     * @param float   $posy       Ordinate of the top left corner of the rectangle.
     * @param float   $width      Width of the rectangle.
     * @param float   $height     Height of the rectangle.
     * @param boolean $vertical   If true prints bar vertically, otherwise horizontally.
     * @param array   $colors     Array of colors to print,
     *                            each entry is a color string or an array of two transition colors;
     *
     * @return string PDF command
     */
    public function getColorRegistrationBar(
        $posx,
        $posy,
        $width,
        $height,
        $vertical = false,
        $colors = array(
            array('g(0%)', 'g(100%)'),                       // GRAY : black   to white
            array('rgb(100%,0%,0%)', 'rgb(100%,100%,100%)'), // RGB  : red     to white
            array('rgb(0%,100%,0%)', 'rgb(100%,100%,100%)'), // RGB  : green   to white
            array('rgb(0%,0%,100%)', 'rgb(100%,100%,100%)'), // RGB  : blue    to white
            array('cmyk(100%,0%,0,0%)', 'cmyk(0%,0%,0,0%)'), // CMYK : cyan    to white
            array('cmyk(0%,100%,0,0%)', 'cmyk(0%,0%,0,0%)'), // CMYK : magenta to white
            array('cmyk(0%,0%,100,0%)', 'cmyk(0%,0%,0,0%)'), // CMYK : yellow  to white
            array('cmyk(0%,0%,0,100%)', 'cmyk(0%,0%,0,0%)'), // CMYK : black   to white
        )
    ) {
        $numbars = count($colors);
        if ($numbars <= 0) {
            return '';
        }

        // set bar measures
        if ($vertical) {
            $coords = array(0, 0, 0, 1); // coordinates for gradient transition
            $wbr = ($width / $numbars);  // bar width
            $hbr = $height;              // bar height
            $xdt = $wbr;                 // delta x
            $ydt = 0;                    // delta y
        } else {
            $coords = array(1, 0, 0, 0);
            $wbr = $width;
            $hbr = ($height / $numbars);
            $xdt = 0;
            $ydt = $hbr;
        }
        $xbr = $posx;
        $ybr = $posy;
        
        $out = '';
        foreach ($colors as $col) {
            if (!empty($col)) {
                if (!is_array($col)) {
                    $col = array($col, $col);
                }
                if (!isset($col[1])) {
                    $col[1] = $col[0];
                }
                if (($col[0] != $col[1]) && (!$this->pdfa)) {
                    // color gradient
                    $out .= $this->getLinearGradient($xbr, $ybr, $wbr, $hbr, $col[0], $col[1], $coords);
                } else {
                    // colored rectangle
                    $out .= $this->getStartTransform()
                        .$this->col->getColorObject($col[0])->getPdfColor()
                        .$this->getRect($xbr, $ybr, $wbr, $hbr, 'F')
                        .$this->getStopTransform();
                }
            }
            $xbr += $xdt;
            $ybr += $ydt;
        }

        return $out;
    }

    /**
     * Get a crop-mark.
     *
     * @param float   $posx       Abscissa of the crop-mark center.
     * @param float   $posy       Ordinate of the crop-mark center.
     * @param float   $width      Width of the crop-mark.
     * @param float   $height     Height of the crop-mark.
     * @param string  $type       Type of crop mark - one symbol per type:
     *                            T = TOP, B = BOTTOM, L = LEFT, R = RIGHT
     * @param array   $style      Line style to apply.
     *
     * @return string PDF command
     */
    public function getCropMark(
        $posx,
        $posy,
        $width,
        $height,
        $type = 'TBLR',
        array $style = array()
    ) {
        $crops = array_unique(str_split(strtoupper($type), 1));
        $space_ratio = 4;
        $dhw = ($width / $space_ratio);  // horizontal space to leave before the intersection point
        $dvh = ($height / $space_ratio); // vertical space to leave before the intersection point

        $out = '';
        foreach ($crops as $crop) {
            switch ($crop) {
                case 'T':
                    $posx1 = $posx;
                    $posy1 = ($posy - $height);
                    $posx2 = $posx;
                    $posy2 = ($posy - $dvh);
                    break;
                case 'B':
                    $posx1 = $posx;
                    $posy1 = ($posy + $dvh);
                    $posx2 = $posx;
                    $posy2 = ($posy + $height);
                    break;
                case 'L':
                    $posx1 = ($posx - $width);
                    $posy1 = $posy;
                    $posx2 = ($posx - $dhw);
                    $posy2 = $posy;
                    break;
                case 'R':
                    $posx1 = ($posx + $dhw);
                    $posy1 = $posy;
                    $posx2 = ($posx + $width);
                    $posy2 = $posy;
                    break;
                default:
                    continue 2;
            }
            $out .= $this->getRawPoint($posx1, $posy1)
                .$this->getRawLine($posx2, $posy2)
                .$this->getPathPaintOp('S');
        }
        
        if (empty($out)) {
            return '';
        }

        return $this->getStartTransform()
            .$this->getStyleCmd($style)
            .$out
            .$this->getStopTransform();
    }

    /**
     * Get overprint mode for stroking (OP) and non-stroking (op) painting operations.
     * (Check the "Entries in a Graphics State Parameter Dictionary" on PDF 32000-1:2008).
     *
     * @param boolean $stroking    If true apply overprint for stroking operations.
     * @param boolean $nonstroking If true apply overprint for painting operations other than stroking.
     * @param integer $mode        Overprint mode:
     *                             0 = each source colour component value replaces the value previously
     *                                 painted for the corresponding device colorant;
     *                             1 = a tint value of 0.0 for a source colour component shall leave the
     *                                 corresponding component of the previously painted colour unchanged.
     *
     * @return string PDF command
     */
    public function getOverprint($stroking = true, $nonstroking = '', $mode = 0)
    {
        if ($nonstroking == '') {
            $nonstroking = $stroking;
        }
        return $this->getExtGState(
            array(
                'OP' => ($stroking && true),
                'op' => ($nonstroking && true),
                'OPM' => max(0, min(1, (int) $mode)),
            )
        );
    }

    /**
     * Set alpha for stroking (CA) and non-stroking (ca) operations.
     *
     * @param float  $stroking    Alpha value for stroking operations: real value from 0 (transparent) to 1 (opaque).
     * @param string $bmv         Blend mode, one of the following:
     *                            Normal, Multiply, Screen, Overlay, Darken, Lighten, ColorDodge, ColorBurn,
     *                            HardLight, SoftLight, Difference, Exclusion, Hue, Saturation, Color, Luminosity.
     * @param float  $nonstroking Alpha value for non-stroking operations:
     *                            real value from 0 (transparent) to 1 (opaque).
     * @param boolean $ais
     *
     * @return string PDF command
     */
    public function getAlpha($stroking = 1, $bmv = 'Normal', $nonstroking = '', $ais = false)
    {
        if ($nonstroking == '') {
            $nonstroking = $stroking;
        }

        if ($bmv[0] == '/') {
            // remove trailing slash
            $bmv = substr($bmv, 1);
        }
        $map = array(
            'Normal',
            'Multiply',
            'Screen',
            'Overlay',
            'Darken',
            'Lighten',
            'ColorDodge',
            'ColorBurn',
            'HardLight',
            'SoftLight',
            'Difference',
            'Exclusion',
            'Hue',
            'Saturation',
            'Color',
            'Luminosity',
        );
        if (!in_array($bmv, $map)) {
            $bmv = $map[0];
        }
        return $this->getExtGState(
            array(
                'CA' => floatval($stroking),
                'ca' => floatval($nonstroking),
                'BM' => '/'.$bmv,
                'AIS' => ($ais && true),
            )
        );
    }
}
