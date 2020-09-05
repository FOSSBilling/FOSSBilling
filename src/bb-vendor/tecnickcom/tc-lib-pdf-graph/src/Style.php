<?php
/**
 * Style.php
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
 * Com\Tecnick\Pdf\Graph\Style
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfGraph
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-graph
 */
abstract class Style extends \Com\Tecnick\Pdf\Graph\Base
{
    /**
     * Stack containing style data
     *
     * @var array
     */
    protected $style = array();

    /**
     * Stack index
     *
     * @var int
     */
    protected $styleid = -1;

    /**
     * Array of restore points (style ID)
     *
     * @var array
     */
    protected $stylemark = array(0);

    /**
     * Unit of measure conversion ratio
     *
     * @var float
     */
    protected $kunit = 1.0;

    /**
     * Map values for lineCap
     *
     * @var array
     */
    protected static $linecapmap = array(0 => 0, 1 => 1, 2 => 2, 'butt' => 0, 'round'=> 1, 'square' => 2);

    /**
     * Map values for lineJoin
     *
     * @var array
     */
    protected static $linejoinmap = array(0 => 0, 1 => 1, 2 => 2, 'miter' => 0, 'round' => 1, 'bevel' => 2);

    /**
     * Map path paint operators
     *
     * @var array
     */
    protected static $ppopmap = array(
        'S'    => 'S',
        'D'    => 'S',
        's'    => 's',
        'h S'  => 's',
        'd'    => 's',
        'f'    => 'f',
        'F'    => 'f',
        'h f'  => 'h f',
        'f*'   => 'f*',
        'F*'   => 'f*',
        'h f*' => 'h f*',
        'B'    => 'B',
        'FD'   => 'B',
        'DF'   => 'B',
        'B*'   => 'B*',
        'F*D'  => 'B*',
        'DF*'  => 'B*',
        'b'    => 'b',
        'h B'  => 'b',
        'fd'   => 'b',
        'df'   => 'b',
        'b*'   => 'b*',
        'h B*' => 'b*',
        'f*d'  => 'b*',
        'df*'  => 'b*',
        'W n'  => 'W n',
        'CNZ'  => 'W n',
        'W* n' => 'W* n',
        'CEO'  => 'W* n',
        'h'    => 'h',
        'n'    => 'n'
    );

    /**
     * Array of transparency objects and parameters.
     *
     * @var array
     */
    protected $extgstates = array();

    /**
     * Initialize default style
     */
    public function init()
    {
        $this->style[++$this->styleid] = array(
            'lineWidth'  => (1.0 / $this->kunit),  // line thickness in user units
            'lineCap'    => 'butt',                // shape of the endpoints for any open path that is stroked
            'lineJoin'   => 'miter',               // shape of joints between connected segments of a stroked path
            'miterLimit' => (10.0 / $this->kunit), // maximum length of mitered line joins for stroked paths
            'dashArray'  => array(),               // lengths of alternating dashes and gaps
            'dashPhase'  => 0,                     // distance  at which to start the dash
            'lineColor'  => 'black',               // line (drawing) color
            'fillColor'  => 'black',               // background (filling) color
        );
        return $this;
    }

    /**
     * Add a new style
     *
     * @param array $style       Style to add.
     * @param bool  $inheritlast If true inherit missing values from the last style.
     *
     * @return string PDF style string
     */
    public function add(array $style = array(), $inheritlast = false)
    {
        if ($inheritlast) {
            $style = array_merge($this->style[$this->styleid], $style);
        }
        $this->style[++$this->styleid] = $style;
        return $this->getStyle();
    }

    /**
     * Remove and return last style
     *
     * @return string PDF style string
     */
    public function pop()
    {
        if ($this->styleid <= 0) {
            throw new GraphException('The style stack is empty');
        }
        $style = $this->getStyle();
        unset($this->style[$this->styleid]);
        --$this->styleid;
        return $style;
    }

    /**
     * Save the current style ID to be restored later
     */
    public function saveStyleStaus()
    {
        $this->stylemark[] = $this->styleid;
    }

    /**
     * Restore the saved style status
     */
    public function restoreStyleStaus()
    {
        $this->styleid = array_pop($this->stylemark);
        $this->style = array_slice($this->style, 0, ($this->styleid + 1), true);
    }

    /**
     * Returns the last style array
     *
     * @return array
     */
    public function getCurrentStyleArray()
    {
        return $this->style[$this->styleid];
    }

    /**
     * Returns the last set value of the specified property
     *
     * @param string $property Property to search.
     * @param mixed  default   Default value to return in case the property is not found.
     *
     * @return mixed Property value or $default in case the property is not found
     */
    public function getLastStyleProperty($property, $default = null)
    {
        for ($idx = $this->styleid; $idx >= 0; --$idx) {
            if (isset($this->style[$idx][$property])) {
                return $this->style[$idx][$property];
            }
        }
        return $default;
    }

    /**
     * Returns the value of th especified item from the last inserted style
     *
     * @return mixed
     */
    public function getCurrentStyleItem($item)
    {
        if (!isset($this->style[$this->styleid][$item])) {
            throw new GraphException('The '.$item.' value is not set in the current style');
        }
        return $this->style[$this->styleid][$item];
    }
    /**
     * Returns the PDF string of the last style added.
     *
     * @return string
     */
    public function getStyle()
    {
        return $this->getStyleCmd($this->style[$this->styleid]);
    }

    /**
     * Returns the PDF string of the specified style.
     *
     * @param array $style Style to represent.
     *
     * @return string
     */
    public function getStyleCmd(array $style)
    {
        $out = '';
        if (isset($style['lineWidth'])) {
            $out .= sprintf('%F w'."\n", ((float) $style['lineWidth'] * $this->kunit));
        }
        $out .= $this->getLineModeCmd($style);
        if (isset($style['lineColor'])) {
            $out .= $this->col->getPdfColor($style['lineColor'], true);
        }
        if (isset($style['fillColor'])) {
            $out .= $this->col->getPdfColor($style['fillColor'], false);
        }
        return $out;
    }

    /**
     * Returns the PDF string of the specified line style
     *
     * @param array $style Style to represent.
     *
     * @return string
     */
    protected function getLineModeCmd(array $style)
    {
        $out = '';
        if (isset($style['lineCap']) && isset(self::$linecapmap[$style['lineCap']])) {
            $out .= self::$linecapmap[$style['lineCap']].' J'."\n";
        }

        if (isset($style['lineJoin']) && isset(self::$linejoinmap[$style['lineJoin']])) {
            $out .= self::$linejoinmap[$style['lineJoin']].' j'."\n";
        }

        if (isset($style['miterLimit'])) {
            $out .= sprintf('%F M'."\n", ((float) $style['miterLimit'] * $this->kunit));
        }

        if (!empty($style['dashArray'])) {
            $dash = array();
            foreach ($style['dashArray'] as $val) {
                $dash[] = sprintf('%F', ((float) $val * $this->kunit));
            }
            $out .= sprintf('[%s] %F d'."\n", implode(' ', $dash), ((float) $style['dashPhase'] * $this->kunit));
        }
        return $out;
    }

    /**
     * Get the Path-Painting Operators.
     *
     * @param string $mode Mode of rendering. Possible values are:
     *   - S or D: Stroke the path.
     *   - s or d: Close and stroke the path.
     *   - f or F: Fill the path, using the nonzero winding number rule to determine the region to fill.
     *   - f* or F*: Fill the path, using the even-odd rule to determine the region to fill.
     *   - B or FD or DF: Fill and then stroke the path,
     *         using the nonzero winding number rule to determine the region to fill.
     *   - B* or F*D or DF*: Fill and then stroke the path,
     *         using the even-odd rule to determine the region to fill.
     *   - b or fd or df: Close, fill, and then stroke the path,
     *         using the nonzero winding number rule to determine the region to fill.
     *   - b or f*d or df*: Close, fill, and then stroke the path,
     *         using the even-odd rule to determine the region to fill.
     *   - CNZ: Clipping mode using the even-odd rule to determine which regions lie inside the clipping path.
     *   - CEO: Clipping mode using the nonzero winding number rule to determine
     *          which regions lie inside the clipping path
     *   - n: End the path object without filling or stroking it.
     * @param string $default Default style
     *
     * @return string
     */
    public function getPathPaintOp($mode, $default = 'S')
    {
        if (!empty(self::$ppopmap[$mode])) {
            return self::$ppopmap[$mode]."\n";
        }
        if (!empty(self::$ppopmap[$default])) {
            return self::$ppopmap[$default]."\n";
        }
        return '';
    }

    /**
     * Returns true if the specified path paint operator includes the filling option
     *
     * @param string $mode Path paint operator (mode of rendering).
     *
     * @return bool
     */
    public function isFillingMode($mode)
    {
        return (!empty(self::$ppopmap[$mode])
            && in_array(self::$ppopmap[$mode], array('f', 'f*', 'B', 'B*', 'b', 'b*'))
        );
    }

    /**
     * Returns true if the specified mode includes the stroking option
     *
     * @param string $mode Path paint operator (mode of rendering).
     *
     * @return bool
     */
    public function isStrokingMode($mode)
    {
        return (!empty(self::$ppopmap[$mode])
            && in_array(self::$ppopmap[$mode], array('S', 's', 'B', 'B*', 'b', 'b*'))
        );
    }

    /**
     * Returns true if the specified mode includes "closing the path" option
     *
     * @param string $mode Path paint operator (mode of rendering).
     *
     * @return bool
     */
    public function isClosingMode($mode)
    {
        return (!empty(self::$ppopmap[$mode])
            && in_array(self::$ppopmap[$mode], array('s', 'b', 'b*'))
        );
    }

    /**
     * Remove the Close option from the specified Path paint operator.
     *
     * @param string $mode Path paint operator (mode of rendering).
     *
     * @return string
     */
    public function getModeWithoutClose($mode)
    {
        $map = array('s' => 'S', 'b' => 'B', 'b*' => 'B*');
        if (!empty(self::$ppopmap[$mode]) && isset($map[self::$ppopmap[$mode]])) {
            return $map[self::$ppopmap[$mode]];
        }
        return $mode;
    }

    /**
     * Remove the Fill option from the specified Path paint operator.
     *
     * @param string $mode Path paint operator (mode of rendering).
     *
     * @return string
     */
    public function getModeWithoutFill($mode)
    {
        $map = array('f' => '', 'f*' => '', 'B' => 'S', 'B*' => 'S', 'b' => 's', 'b*' => 's');
        if (!empty(self::$ppopmap[$mode]) && isset($map[self::$ppopmap[$mode]])) {
            return $map[self::$ppopmap[$mode]];
        }
        return $mode;
    }

    /**
     * Remove the Stroke option from the specified Path paint operator.
     *
     * @param string $mode Path paint operator (mode of rendering).
     *
     * @return string
     */
    public function getModeWithoutStroke($mode)
    {
        $map = array('S' => '', 's' => 'h', 'B' => 'f', 'B*' => 'f*', 'b' => 'h f', 'b*' => 'h f*');
        if (!empty(self::$ppopmap[$mode]) && isset($map[self::$ppopmap[$mode]])) {
            return $map[self::$ppopmap[$mode]];
        }
        return $mode;
    }

    /**
     * Add transparency parameters to the current extgstate
     *
     * @param array $parms parameters
     *
     * @return string PDF command
     */
    public function getExtGState($parms)
    {
        if ($this->pdfa) {
            return '';
        }

        $gsx = (count($this->extgstates) + 1);
        // check if this ExtGState already exist
        foreach ($this->extgstates as $idx => $ext) {
            if ($ext['parms'] == $parms) {
                $gsx = $idx;
                break;
            }
        }
        if (empty($this->extgstates[$gsx])) {
            $this->extgstates[$gsx] = array('parms' => $parms);
        }
        return '/GS'.$gsx.' gs'."\n";
    }
}
