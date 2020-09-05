<?php
/**
 * Base.php
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

use \Com\Tecnick\Color\Pdf as PdfColor;
use \Com\Tecnick\Pdf\Encrypt\Encrypt;
use \Com\Tecnick\Pdf\Graph\Exception as GraphException;

/**
 * Com\Tecnick\Pdf\Graph\Base
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfGraph
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-graph
 */
abstract class Base
{
    /**
     * Pi constant
     * We use this instead of M_PI because HHVM has a different value.
     *
     * @var float
     */
    const MPI = 3.14159265358979323846264338327950288419716939937510;

    /**
     * Current PDF object number
     *
     * @var int
     */
    protected $pon;

    /**
     * Current page height
     *
     * @var float
     */
    protected $pageh = 0;

    /**
     * Current page width
     *
     * @var float
     */
    protected $pagew = 0;

    /**
     * Unit of measure conversion ratio
     *
     * @var float
     */
    protected $kunit = 1.0;

    /**
     * Color object
     *
     * @var PdfColor
     */
    protected $col;

    /**
     * Encrypt object
     *
     * @var Encrypt
     */
    protected $enc;

    /**
     * True if we are in PDF/A mode.
     *
     * @var bool
     */
    protected $pdfa = false;

    /**
     * Initialize
     *
     * @param float    $kunit  Unit of measure conversion ratio.
     * @param float    $pagew  Page width.
     * @param float    $pageh  Page height.
     * @param PdfColor $color  Color object.
     * @param bool     $pdfa   True if we are in PDF/A mode.
     */
    public function __construct($kunit, $pagew, $pageh, PdfColor $color, Encrypt $enc, $pdfa = false)
    {
        $this->setKUnit($kunit);
        $this->setPageWidth($pagew);
        $this->setPageHeight($pageh);
        $this->col = $color;
        $this->enc = $enc;
        $this->pdfa = (bool) $pdfa;
        $this->init();
    }

    /**
     * Returns current PDF object number
     *
     * @return int
     */
    public function getObjectNumber()
    {
        return $this->pon;
    }

    /**
     * Initialize objects
     */
    abstract public function init();

    /**
     * Set page height
     *
     * @param float  $pageh  Page height
     */
    public function setPageHeight($pageh)
    {
        $this->pageh = (float) $pageh;
        return $this;
    }

    /**
     * Set page width
     *
     * @param float  $pagew  Page width
     */
    public function setPageWidth($pagew)
    {
        $this->pagew = (float) $pagew;
        return $this;
    }

    /**
     * Set unit of measure conversion ratio.
     *
     * @param float  $kunit  Unit of measure conversion ratio.
     */
    public function setKUnit($kunit)
    {
        $this->kunit = (float) $kunit;
        return $this;
    }
    
    /**
     * Get the PDF output string for ExtGState
     *
     * @param int $pon Current PDF Object Number
     *
     * @return string PDF command
     */
    public function getOutExtGState($pon)
    {
        $this->pon = (int) $pon;
        $out = '';
        foreach ($this->extgstates as $idx => $ext) {
            $this->extgstates[$idx]['n'] = ++$this->pon;
            $out .= $this->pon.' 0 obj'."\n"
                .'<< /Type /ExtGState';
            foreach ($ext['parms'] as $key => $val) {
                if (is_numeric($val)) {
                    $val = sprintf('%F', $val);
                } elseif ($val === true) {
                    $val = 'true';
                } elseif ($val === false) {
                    $val = 'false';
                }
                $out .= ' /'.$key.' '.$val;
            }
            $out .= ' >>'."\n"
            .'endobj'."\n";
        }
        return $out;
    }

    /**
     * Get the PDF output string for ExtGState Resource Dictionary
     *
     * @return string PDF command
     */
    public function getOutExtGStateResources()
    {
        if ($this->pdfa || empty($this->extgstates)) {
            return '';
        }
        $out = ' /ExtGState <<';
        foreach ($this->extgstates as $key => $ext) {
            if (isset($ext['name'])) {
                $out .= ' /'.$ext['name'];
            } else {
                $out .= ' /GS'.$key;
            }
            $out .= ' '.$ext['n'].' 0 R';
        }
        $out .= ' >>'."\n";
        return $out;
    }
    
    /**
     * Get the PDF output string for Gradients Resource Dictionary
     *
     * @return string PDF command
     */
    public function getOutGradientResources()
    {
        if ($this->pdfa || empty($this->gradients)) {
            return '';
        }
        $grp = '';
        $grs = '';
        foreach ($this->gradients as $idx => $grad) {
            // gradient patterns
            $grp .= ' /p'.$idx.' '.$grad['pattern'].' 0 R';
            // gradient shadings
            $grs .= ' /Sh'.$idx.' '.$grad['id'].' 0 R';
        }
        return ' /Pattern <<'.$grp.' >>'."\n"
            .' /Shading <<'.$grs.' >>'."\n";
    }

    /**
     * Get the PDF output string for gradient colors and transparency
     *
     * @param array  $grad Array of gradient colors
     * @param string $type Type of output: 'color' or 'opacity'
     *
     * @return string PDF command
     */
    protected function getOutGradientCols($grad, $type)
    {
        if (($type == 'opacity') && !$grad['transparency']) {
            return '';
        }

        $out = '';

        if (($grad['type'] == 2) || ($grad['type'] == 3)) {
            $num_cols = count($grad['colors']);
            $lastcols = ($num_cols - 1);
            $funct = array(); // color and transparency objects
            $bounds = array();
            $encode = array();

            for ($idx = 1; $idx < $num_cols; ++$idx) {
                $encode[] = '0 1';
                if ($idx < $lastcols) {
                    $bounds[] = sprintf('%F ', $grad['colors'][$idx]['offset']);
                }
                $out .= ++$this->pon.' 0 obj'."\n"
                .'<<'
                .' /FunctionType 2'
                .' /Domain [0 1]'
                .' /C0 ['.$grad['colors'][($idx - 1)][$type].']'
                .' /C1 ['.$grad['colors'][$idx][$type].']'
                .' /N '.$grad['colors'][$idx]['exponent']
                .' >>'."\n"
                .'endobj'."\n";
                $funct[] = $this->pon.' 0 R';
            }

            $out .= ++$this->pon.' 0 obj'."\n"
                .'<<'
                .' /FunctionType 3'
                .' /Domain [0 1]'
                .' /Functions ['.implode(' ', $funct).']'
                .' /Bounds ['.implode(' ', $bounds).']'
                .' /Encode ['.implode(' ', $encode).']'
                .' >>'."\n"
                .'endobj'."\n";
        }

        $out .= $this->getOutPatternObj($grad, $this->pon);
        return $out;
    }

    /**
     * Get the PDF output string for the pattern and shading object
     *
     * @param array  $grad    Array of gradient colors
     * @param int    $objref  Refrence object number
     *
     * @return string PDF command
     */
    protected function getOutPatternObj($grad, $objref)
    {
        // set shading object
        if ($grad['transparency']) {
            $grad['colspace'] = 'DeviceGray';
        }
        
        $objref = ++$this->pon;
        $out = $objref.' 0 obj'."\n"
            .'<<'
            .' /ShadingType '.$grad['type']
            .' /ColorSpace /'.$grad['colspace'];
        if (!empty($grad['background'])) {
            $out .= ' /Background ['.$grad['background']->getComponentsString().']';
        }
        if (!empty($grad['antialias'])) {
            $out .= ' /AntiAlias true';
        }
        if ($grad['type'] == 2) {
            $out .= ' '.sprintf(
                '/Coords [%F %F %F %F]',
                $grad['coords'][0],
                $grad['coords'][1],
                $grad['coords'][2],
                $grad['coords'][3]
            )
                .' /Domain [0 1]'
                .' /Function '.$objref.' 0 R'
                .' /Extend [true true]'
                .' >>'."\n";
        } elseif ($grad['type'] == 3) {
            // x0, y0, r0, x1, y1, r1
            // the  radius of the inner circle is 0
            $out .= ' '.sprintf(
                '/Coords [%F %F 0 %F %F %F]',
                $grad['coords'][0],
                $grad['coords'][1],
                $grad['coords'][2],
                $grad['coords'][3],
                $grad['coords'][4]
            )
                .' /Domain [0 1]'
                .' /Function '.$objref.' 0 R'
                .' /Extend [true true]'
                .' >>'."\n";
        } elseif ($grad['type'] == 6) {
            $stream = $this->enc->encryptString($grad['stream'], $this->pon);
            $out .= ' /BitsPerCoordinate 16'
                .' /BitsPerComponent 8'
                .' /Decode[0 1 0 1 0 1 0 1 0 1]'
                .' /BitsPerFlag 8'
                .' /Length '.strlen($stream)
                .' >>'."\n"
                .' stream'."\n"
                .$stream."\n"
                .'endstream'."\n";
        }
        $out .= 'endobj'."\n";

        // pattern object
        $out .= ++$this->pon.' 0 obj'."\n"
            .'<<'
            .' /Type /Pattern'
            .' /PatternType 2'
            .' /Shading '.$objref.' 0 R'
            .' >>'."\n"
            .'endobj'
            ."\n";

        return $out;
    }

    /**
     * Get the PDF output string for gradient shaders
     *
     * @param int $pon Current PDF Object Number
     *
     * @return string PDF command
     */
    public function getOutGradientShaders($pon)
    {
        $this->pon = (int) $pon;

        if ($this->pdfa || empty($this->gradients)) {
            return '';
        }

        $idt = count($this->gradients); // index for transparency gradients

        $out = '';
        foreach ($this->gradients as $idx => $grad) {
            $out .= $this->getOutGradientCols($grad, 'color');
            $this->gradients[$idx]['id'] = ($this->pon - 1);
            $this->gradients[$idx]['pattern'] = $this->pon;

            $out .= $this->getOutGradientCols($grad, 'opacity');
            $idgs = ($idx + $idt);
            $this->gradients[$idgs]['id'] = ($this->pon - 1);
            $this->gradients[$idgs]['pattern'] = $this->pon;

            if ($grad['transparency']) {
                $oid = ++$this->pon;
                $pwidth = ($this->pagew * $this->kunit);
                $pheight = ($this->pageh * $this->kunit);
                $stream = 'q /a0 gs /Pattern cs /p'.$idgs.' scn 0 0 '.$pwidth.' '.$pheight.' re f Q';
                $stream = gzcompress($stream);
                $stream = $this->enc->encryptString($stream, $oid);
                $rect = sprintf('%F %F', $pwidth, $pheight);

                $out .= $oid.' 0 obj'."\n"
                    .'<<'
                    .' /Type /XObject'
                    .' /Subtype /Form'
                    .' /FormType 1'
                    .' /Filter /FlateDecode'
                    .' /Length '.strlen($stream)
                    .' /BBox [0 0 '.$rect.']'
                    .' /Group << /Type /Group /S /Transparency /CS /DeviceGray >>'
                    .' /Resources <<'
                    .' /ExtGState << /a0 << /ca 1 /CA 1 >>  >>'
                    .' /Pattern << /p'.$idgs.' '.$this->gradients[$idgs]['pattern'].' 0 R >>'
                    .' >>'
                    .' >>'."\n"
                    .' stream'."\n"
                    .$stream."\n"
                    .'endstream'."\n"
                    .'endobj'."\n";

                // SMask
                $objsm = ++$this->pon;
                $out .= $objsm.' 0 obj'."\n"
                    .'<<'
                    .' /Type /Mask'
                    .' /S /Luminosity'
                    .' /G '.$oid.' 0 R'
                    .' >>'."\n"
                    .'endobj'."\n";

                // ExtGState
                $objext = ++$this->pon;
                $out .= ++$objext.' 0 obj'."\n"
                    .'<<'
                    .' /Type /ExtGState'
                    .' /SMask '.$objsm.' 0 R'
                    .' /AIS false'
                    .' >>'."\n"
                    .'endobj'."\n";
                $this->extgstates[] = array('n' => $objext, 'name' => 'TGS'.$idx);
            }
        }

        return $out;
    }
}
