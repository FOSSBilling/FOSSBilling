<?php
/**
 * Raw.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfGraph
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-graph
 *
 * This file is part of tc-lib-pdf-graph software library.
 */

namespace Com\Tecnick\Pdf\Graph;

use \Com\Tecnick\Pdf\Graph\Exception as GraphException;

/**
 * Com\Tecnick\Pdf\Graph\Raw
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfGraph
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-graph
 */
abstract class Raw extends \Com\Tecnick\Pdf\Graph\Transform
{
    /**
     * Begin a new subpath by moving the current point to the specified coordinates,
     * omitting any connecting line segment.
     *
     * @param float $posx Abscissa of point.
     * @param float $posy Ordinate of point.
     *
     * @return string PDF command
     */
    public function getRawPoint($posx, $posy)
    {
        return sprintf(
            '%F %F m'."\n",
            ($posx * $this->kunit),
            (($this->pageh - $posy) * $this->kunit)
        );
    }

    /**
     * Append a straight line segment from the current point to the specified one.
     * The new current point shall be the one specified.
     *
     * @param float $posx Abscissa of end point.
     * @param float $posy Ordinate of end point.
     *
     * @return string PDF command
     */
    public function getRawLine($posx, $posy)
    {
        return sprintf(
            '%F %F l'."\n",
            ($posx * $this->kunit),
            (($this->pageh - $posy) * $this->kunit)
        );
    }

    /**
     * Append a rectangle to the current path as a complete subpath,
     * with lower-left corner in the specified point and dimensions width and height in user units.
     *
     * @param float $posx   Abscissa of upper-left corner.
     * @param float $posy   Ordinate of upper-left corner.
     * @param float $width  Width.
     * @param float $height Height.
     *
     * @return string PDF command
     */
    public function getRawRect($posx, $posy, $width, $height)
    {
        return sprintf(
            '%F %F %F %F re'."\n",
            ($posx * $this->kunit),
            (($this->pageh - $posy) * $this->kunit),
            ($width * $this->kunit),
            (-$height * $this->kunit)
        );
    }

    /**
     * Append a cubic Bezier curve to the current path.
     * The curve shall extend from the current point to the point (posx3, posy3),
     * using (posx1, posy1) and (posx2, posy2) as the Bezier control points.
     * The new current point shall be (posx3, posy3).
     *
     * @param float $posx1 Abscissa of control point 1.
     * @param float $posy1 Ordinate of control point 1.
     * @param float $posx2 Abscissa of control point 2.
     * @param float $posy2 Ordinate of control point 2.
     * @param float $posx3 Abscissa of end point.
     * @param float $posy3 Ordinate of end point.
     *
     * @return string PDF command
     */
    public function getRawCurve($posx1, $posy1, $posx2, $posy2, $posx3, $posy3)
    {
        return sprintf(
            '%F %F %F %F %F %F c'."\n",
            ($posx1 * $this->kunit),
            (($this->pageh - $posy1) * $this->kunit),
            ($posx2 * $this->kunit),
            (($this->pageh - $posy2) * $this->kunit),
            ($posx3 * $this->kunit),
            (($this->pageh - $posy3) * $this->kunit)
        );
    }

    /**
     * Append a cubic Bezier curve to the current path.
     * The curve shall extend from the current point to the point (posx3, posy3),
     * using the current point and (posx2, posy2) as the Bezier control points.
     * The new current point shall be (posx3, posy3).
     *
     * @param float $posx2 Abscissa of control point 2.
     * @param float $posy2 Ordinate of control point 2.
     * @param float $posx3 Abscissa of end point.
     * @param float $posy3 Ordinate of end point.
     *
     * @return string PDF command
     */
    public function getRawCurveV($posx2, $posy2, $posx3, $posy3)
    {
        return sprintf(
            '%F %F %F %F v'."\n",
            ($posx2 * $this->kunit),
            (($this->pageh - $posy2) * $this->kunit),
            ($posx3 * $this->kunit),
            (($this->pageh - $posy3) * $this->kunit)
        );
    }

    /**
     * Append a cubic Bezier curve to the current path.
     * The curve shall extend from the current point to the point (posx3, posy3),
     * using (posx1, posy1) and (posx3, posy3) as the Bezier control points.
     * The new current point shall be (posx3, posy3).
     *
     * @param float $posx1 Abscissa of control point 1.
     * @param float $posy1 Ordinate of control point 1.
     * @param float $posx3 Abscissa of end point.
     * @param float $posy3 Ordinate of end point.
     *
     * @return string PDF command
     */
    public function getRawCurveY($posx1, $posy1, $posx3, $posy3)
    {
        return sprintf(
            '%F %F %F %F y'."\n",
            ($posx1 * $this->kunit),
            (($this->pageh - $posy1) * $this->kunit),
            ($posx3 * $this->kunit),
            (($this->pageh - $posy3) * $this->kunit)
        );
    }

    /**
     * Initialize angles for the elliptical arc.
     *
     * @param float $ags Angle in degrees at which starting drawing.
     * @param float $agf Angle in degrees at which stop drawing.
     * @param float $rdh Horizontal radius.
     * @param float $rdv Vertical radius (if = 0 then it is a circle).
     * @param bool  $ccw If true draws in counter-clockwise direction.
     * @param bool  $svg If true the angles are in svg mode (already calculated).
     */
    protected function setRawEllipticalArcAngles(&$ags, &$agf, $rdv, $rdh, $ccw, $svg)
    {
        $ags = $this->degToRad((float) $ags);
        $agf = $this->degToRad((float) $agf);
        if (!$svg) {
            $ags = atan2((sin($ags) / $rdv), (cos($ags) / $rdh));
            $agf = atan2((sin($agf) / $rdv), (cos($agf) / $rdh));
        }
        if ($ags < 0) {
            $ags += (2 * self::MPI);
        }
        if ($agf < 0) {
            $agf += (2 * self::MPI);
        }
        if ($ccw && ($ags > $agf)) {
            // reverse rotation
            $ags -= (2 * self::MPI);
        } elseif (!$ccw && ($ags < $agf)) {
            // reverse rotation
            $agf -= (2 * self::MPI);
        }
    }

    /**
     * Append an elliptical arc to the current path.
     * An ellipse is formed from n Bezier curves.
     *
     * @param float $posxc      Abscissa of center point.
     * @param float $posyc      Ordinate of center point.
     * @param float $rdh        Horizontal radius.
     * @param float $rdv        Vertical radius (if = 0 then it is a circle).
     * @param float $posxang    Angle between the X-axis and the major axis of the ellipse.
     * @param float $angs       Angle in degrees at which starting drawing.
     * @param float $angf       Angle in degrees at which stop drawing.
     * @param bool  $pie        If true do not mark the border point (used to draw pie sectors).
     * @param int   $ncv        Number of curves used to draw a 90 degrees portion of ellipse.
     * @param bool  $startpoint If true output a starting point.
     * @param bool  $ccw        If true draws in counter-clockwise direction.
     * @param bool  $svg        If true the angles are in svg mode (already calculated).
     * @param array $bbox       If provided, it will be filled with the bounding box coordinates
     *                          (x min, y min, x max, y max).
     *
     * @return string PDF command
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function getRawEllipticalArc(
        $posxc,
        $posyc,
        $rdh,
        $rdv,
        $posxang = 0,
        $angs = 0,
        $angf = 360,
        $pie = false,
        $ncv = 2,
        $startpoint = true,
        $ccw = true,
        $svg = false,
        &$bbox = array()
    ) {
        $out = '';
        if (($rdh <= 0) || ($rdv < 0)) {
            return '';
        }
        $bbox = array(PHP_INT_MAX, PHP_INT_MAX, 0, 0);
        if ($pie) {
            $out .= $this->getRawPoint($posxc, $posyc); // center of the arc
        }
        $posxang = $this->degToRad((float) $posxang);
        $ags = $angs;
        $agf = $angf;
        $this->setRawEllipticalArcAngles($ags, $agf, $rdv, $rdh, $ccw, $svg);
        $total_angle = ($agf - $ags);
        $ncv = max(2, $ncv);
        $ncv *= (2 * abs($total_angle) / self::MPI); // total arcs to draw
        $ncv = round($ncv) + 1;
        $arcang = ($total_angle / $ncv); // angle of each arc
        $posx0 = $posxc; // X center point in PDF coordinates
        $posy0 = ($this->pageh - $posyc); // Y center point in PDF coordinates
        $ang = $ags; // starting angle
        $alpha = sin($arcang) * ((sqrt(4 + (3 * pow(tan(($arcang) / 2), 2))) - 1) / 3);
        $cos_xang = cos($posxang);
        $sin_xang = sin($posxang);
        $cos_ang = cos($ang);
        $sin_ang = sin($ang);
        // first arc point
        $px1 = $posx0 + ($rdh * $cos_xang * $cos_ang) - ($rdv * $sin_xang * $sin_ang);
        $py1 = $posy0 + ($rdh * $sin_xang * $cos_ang) + ($rdv * $cos_xang * $sin_ang);
        // first Bezier control point
        $qx1 = ($alpha * ((-$rdh * $cos_xang * $sin_ang) - ($rdv * $sin_xang * $cos_ang)));
        $qy1 = ($alpha * ((-$rdh * $sin_xang * $sin_ang) + ($rdv * $cos_xang * $cos_ang)));
        if ($pie) {
            $out .= $this->getRawLine($px1, ($this->pageh - $py1)); // line from center to arc starting point
        } elseif ($startpoint) {
            $out .= $this->getRawPoint($px1, ($this->pageh - $py1)); // arc starting point
        }
        // draw arcs
        for ($idx = 1; $idx <= $ncv; ++$idx) {
            $ang = $ags + ($idx * $arcang); // starting angle
            if ($idx == $ncv) {
                $ang = $agf;
            }
            $cos_ang = cos($ang);
            $sin_ang = sin($ang);
            // second arc point
            $px2 = $posx0 + ($rdh * $cos_xang * $cos_ang) - ($rdv * $sin_xang * $sin_ang);
            $py2 = $posy0 + ($rdh * $sin_xang * $cos_ang) + ($rdv * $cos_xang * $sin_ang);
            // second Bezier control point
            $qx2 = ($alpha * ((-$rdh * $cos_xang * $sin_ang) - ($rdv * $sin_xang * $cos_ang)));
            $qy2 = ($alpha * ((-$rdh * $sin_xang * $sin_ang) + ($rdv * $cos_xang * $cos_ang)));
            // draw arc
            $cx1 = ($px1 + $qx1);
            $cy1 = ($this->pageh - ($py1 + $qy1));
            $cx2 = ($px2 - $qx2);
            $cy2 = ($this->pageh - ($py2 - $qy2));
            $cx3 = $px2;
            $cy3 = ($this->pageh - $py2);
            $out .= $this->getRawCurve($cx1, $cy1, $cx2, $cy2, $cx3, $cy3);
            // get bounding box coordinates
            $bbox = array(
                min($bbox[0], $cx1, $cx2, $cx3),
                min($bbox[1], $cy1, $cy2, $cy3),
                max($bbox[2], $cx1, $cx2, $cx3),
                max($bbox[3], $cy1, $cy2, $cy3),
            );
            // move to next point
            $px1 = $px2;
            $py1 = $py2;
            $qx1 = $qx2;
            $qy1 = $qy2;
        }
        if ($pie) {
            $out .= $this->getRawLine($posxc, $posyc);
            // get bounding box coordinates
            $bbox = array(min($bbox[0], $posxc), min($bbox[1], $posyc), max($bbox[2], $posxc),  max($bbox[3], $posyc));
        }
        return $out;
    }

    /**
     * Returns the angle in radiants between two vectors with the same origin point.
     * Angles are counted counter-clock wise.
     *
     * @param int $posx1 X coordinate of first vector point.
     * @param int $posy1 Y coordinate of first vector point.
     * @param int $posx2 X coordinate of second vector point.
     * @param int $posy2 Y coordinate of second vector point.
     *
     * @return float Angle in radiants
     */
    public function getVectorsAngle($posx1, $posy1, $posx2, $posy2)
    {
        $dprod = (($posx1 * $posx2) + ($posy1 * $posy2));
        $dist1 = sqrt(($posx1 * $posx1) + ($posy1 * $posy1));
        $dist2 = sqrt(($posx2 * $posx2) + ($posy2 * $posy2));
        $distprod = ($dist1 * $dist2);
        if ($distprod == 0) {
            return 0;
        }
        $angle = acos(min(1, max(-1, ($dprod / $distprod))));
        if ((($posx1 * $posy2) - ($posx2 * $posy1)) < 0) {
            $angle *= -1;
        }
        return $angle;
    }

    /**
     * Converts the number in degrees to the radian equivalent.
     * We use this instead of $this->degToRad to avoid precision problems with hhvm.
     *
     * @param float $deg Angular value in degrees.
     *
     * @return float Angle in radiants
     */
    public function degToRad($deg)
    {
        return ($deg * self::MPI / 180);
    }
}
