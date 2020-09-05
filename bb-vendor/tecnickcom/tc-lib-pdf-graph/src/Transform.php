<?php
/**
 * Transform.php
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
 * Com\Tecnick\Pdf\Graph\Transform
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfGraph
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-graph
 */
abstract class Transform extends \Com\Tecnick\Pdf\Graph\Style
{
    /**
     * Array (stack) of Current Transformation Matrix (CTM),
     * which maps user space coordinates used within a PDF content stream into output device coordinates.
     *
     * @var array
     */
    protected $ctm = array();

    /**
     * Current ID for transformation matrix.
     *
     * @var int
     */
    protected $ctmid = -1;

    /**
     * Returns the transformation stack.
     *
     * @return array
     */
    public function getTransformStack()
    {
        return $this->ctm;
    }

    /**
     * Returns the transformation stack index.
     *
     * @return int
     */
    public function getTransformIndex()
    {
        return $this->ctmid;
    }

    /**
     * Starts a 2D transformation saving current graphic state.
     * This function must be called before calling transformation methods
     *
     * @return string
     */
    public function getStartTransform()
    {
        $this->saveStyleStaus();
        $this->ctm[++$this->ctmid] = array();
        return 'q'."\n";
    }

    /**
     * Stops a 2D tranformation restoring previous graphic state.
     * This function must be called after calling transformation methods.
     *
     * @return string
     */
    public function getStopTransform()
    {
        if (!isset($this->ctm[$this->ctmid])) {
            return '';
        }
        unset($this->ctm[$this->ctmid]);
        --$this->ctmid;
        $this->restoreStyleStaus();
        return 'Q'."\n";
    }

    /**
     * Get the tranformation matrix (CTM) PDF string
     *
     * @param array $ctm Transformation matrix array.
     *
     * @return string
     */
    public function getTransformation($ctm)
    {
        $this->ctm[$this->ctmid][] = $ctm;
        return sprintf('%F %F %F %F %F %F cm'."\n", $ctm[0], $ctm[1], $ctm[2], $ctm[3], $ctm[4], $ctm[5]);
    }

    /**
     * Vertical and horizontal non-proportional Scaling.
     *
     * @param float $skx  Horizontal scaling factor.
     * @param float $sky  vertical scaling factor.
     * @param float $posx Abscissa of the scaling center.
     * @param float $posy Ordinate of the scaling center.
     *
     * @return string Transformation string
     */
    public function getScaling($skx, $sky, $posx, $posy)
    {
        if (($skx == 0) || ($sky == 0)) {
            throw new GraphException('Scaling factors must be different than zero');
        }
        $posy = (($this->pageh - $posy) * $this->kunit);
        $posx = ($posx * $this->kunit);
        $ctm = array($skx, 0, 0, $sky, ($posx * (1 - $skx)), ($posy * (1 - $sky)));
        return $this->getTransformation($ctm);
    }

    /**
     * Horizontal Scaling.
     *
     * @param float $skx  Horizontal scaling factor.
     * @param float $posx Abscissa of the scaling center.
     * @param float $posy Ordinate of the scaling center.
     *
     * @return string Transformation string
     */
    public function getHorizScaling($skx, $posx, $posy)
    {
        return $this->getScaling($skx, 1, $posx, $posy);
    }

    /**
     * Vertical Scaling.
     *
     * @param float $sky  vertical scaling factor.
     * @param float $posx Abscissa of the scaling center.
     * @param float $posy Ordinate of the scaling center.
     *
     * @return string Transformation string
     */
    public function getVertScaling($sky, $posx, $posy)
    {
        return $this->getScaling(1, $sky, $posx, $posy);
    }

    /**
     * Vertical and horizontal proportional Scaling.
     *
     * @param float $skf  Scaling factor.
     * @param float $posx Abscissa of the scaling center.
     * @param float $posy Ordinate of the scaling center.
     *
     * @return string Transformation string
     */
    public function getPropScaling($skf, $posx, $posy)
    {
        return $this->getScaling($skf, $skf, $posx, $posy);
    }

    /**
     * Rotation.
     *
     * @param float $angle Angle in degrees for counter-clockwise rotation.
     * @param float $posx Abscissa of the rotation center.
     * @param float $posy Ordinate of the rotation center.
     *
     * @return string Transformation string
     */
    public function getRotation($angle, $posx, $posy)
    {
        $posy = (($this->pageh - $posy) * $this->kunit);
        $posx = ($posx * $this->kunit);
        $ctm = array();
        $ctm[0] = cos($this->degToRad($angle));
        $ctm[1] = sin($this->degToRad($angle));
        $ctm[2] = -$ctm[1];
        $ctm[3] = $ctm[0];
        $ctm[4] = ($posx + ($ctm[1] * $posy) - ($ctm[0] * $posx));
        $ctm[5] = ($posy - ($ctm[0] * $posy) - ($ctm[1] * $posx));
        return $this->getTransformation($ctm);
    }

    /**
     * Horizontal Mirroring.
     *
     * @param float $posx Abscissa of the mirroring line.
     *
     * @return string Transformation string
     */
    public function getHorizMirroring($posx)
    {
        return $this->getScaling(-1, 1, $posx, 0);
    }

    /**
     * Verical Mirroring.
     *
     * @param float $posy Ordinate of the mirroring line.
     *
     * @return string Transformation string
     */
    public function getVertMirroring($posy)
    {
        return $this->getScaling(1, -1, 0, $posy);
    }

    /**
     * Point reflection mirroring.
     *
     * @param float $posx Abscissa of the mirroring point.
     * @param float $posy Ordinate of the mirroring point.
     *
     * @return string Transformation string
     */
    public function getPointMirroring($posx, $posy)
    {
        return $this->getScaling(-1, -1, $posx, $posy);
    }

    /**
     * Reflection against a straight line through point (x, y) with the gradient angle (angle).
     *
     * @param float $angle Gradient angle in degrees of the straight line.
     * @param float $posx  Abscissa of the mirroring point.
     * @param float $posy  Ordinate of the mirroring point.
     *
     * @return string Transformation string
     */
    public function getReflection($ang, $posx, $posy)
    {
        return $this->getScaling(-1, 1, $posx, $posy).$this->getRotation((-2 * ($ang - 90)), $posx, $posy);
    }

    /**
     * Translate graphic object horizontally and vertically.
     *
     * @param float $trx Movement to the right.
     * @param float $try Movement to the bottom.
     *
     * @return string Transformation string
     */
    public function getTranslation($trx, $try)
    {
        //calculate elements of transformation matrix
        $ctm = array(1, 0, 0, 1, ($trx * $this->kunit), (-$try * $this->kunit));
        return $this->getTransformation($ctm);
    }

    /**
     * Translate graphic object horizontally.
     *
     * @param float $trx Movement to the right.
     *
     * @return string Transformation string
     */
    public function getHorizTranslation($trx)
    {
        return $this->getTranslation($trx, 0);
    }

    /**
     * Translate graphic object vertically.
     *
     * @param float $try Movement to the bottom.
     *
     * @return string Transformation string
     */
    public function getVertTranslation($try)
    {
        return $this->getTranslation(0, $try);
    }

    /**
     * Skew.
     *
     * @param float $angx Angle in degrees between -90 (skew to the left) and 90 (skew to the right)
     * @param float $angy Angle in degrees between -90 (skew to the bottom) and 90 (skew to the top)
     * @param float $posx Abscissa of the skewing center.
     * @param float $posy Ordinate of the skewing center.
     *
     * @return string Transformation string
     */
    public function getSkewing($angx, $angy, $posx, $posy)
    {
        if (($angx <= -90) || ($angx >= 90) || ($angy <= -90) || ($angy >= 90)) {
            throw new GraphException('Angle values must be beweeen -90 and +90 degrees.');
        }
        $posy = (($this->pageh - $posy) * $this->kunit);
        $posx = ($posx * $this->kunit);
        $ctm = array();
        $ctm[0] = 1;
        $ctm[1] = tan($this->degToRad($angy));
        $ctm[2] = tan($this->degToRad($angx));
        $ctm[3] = 1;
        $ctm[4] = (-$ctm[2] * $posy);
        $ctm[5] = (-$ctm[1] * $posx);
        return $this->getTransformation($ctm);
    }

    /**
     * Skew horizontally.
     *
     * @param float $angx Angle in degrees between -90 (skew to the left) and 90 (skew to the right)
     * @param float $posx Abscissa of the skewing center.
     * @param float $posy Ordinate of the skewing center.
     *
     * @return string Transformation string
     */
    public function getHorizSkewing($angx, $posx, $posy)
    {
        return $this->getSkewing($angx, 0, $posx, $posy);
    }

    /**
     * Skew vertically.
     *
     * @param float $angy Angle in degrees between -90 (skew to the bottom) and 90 (skew to the top)
     * @param float $posx Abscissa of the skewing center.
     * @param float $posy Ordinate of the skewing center.
     *
     * @return string Transformation string
     */
    public function getVertSkewing($angy, $posx, $posy)
    {
        return $this->getSkewing(0, $angy, $posx, $posy);
    }

    /**
     * Get the product of two Tranformation Matrix.
     *
     * @param array $tma First  Tranformation Matrix.
     * @param array $tmb Second Tranformation Matrix.
     *
     * @return array CTM Transformation Matrix.
     */
    public function getCtmProduct($tma, $tmb)
    {
        return array(
            (((float) $tma[0] * (float) $tmb[0]) + ((float) $tma[2] * (float) $tmb[1])),
            (((float) $tma[1] * (float) $tmb[0]) + ((float) $tma[3] * (float) $tmb[1])),
            (((float) $tma[0] * (float) $tmb[2]) + ((float) $tma[2] * (float) $tmb[3])),
            (((float) $tma[1] * (float) $tmb[2]) + ((float) $tma[3] * (float) $tmb[3])),
            (((float) $tma[0] * (float) $tmb[4]) + ((float) $tma[2] * (float) $tmb[5]) + (float) $tma[4]),
            (((float) $tma[1] * (float) $tmb[4]) + ((float) $tma[3] * (float) $tmb[5]) + (float) $tma[5])
        );
    }
}
