<?php
/**
 * Output.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfImage
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-image
 *
 * This file is part of tc-lib-pdf-image software library.
 */

namespace Com\Tecnick\Pdf\Image;

use \Com\Tecnick\Pdf\Encrypt\Encrypt;
use \Com\Tecnick\Pdf\Image\Exception as ImageException;

/**
 * Com\Tecnick\Pdf\Image\Output
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfImage
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-image
 */
abstract class Output
{
    /**
     * Current PDF object number
     *
     * @var int
     */
    protected $pon;

    /**
     * Unit of measure conversion ratio
     *
     * @var float
     */
    protected $kunit = 1.0;

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
     * Store image object IDs for the XObject Dctionary.
     *
     * @var array
     */
    protected $xobjdict = array();

    /**
     * Image structure
     *
     * @var array
     */
    protected $image = [];

    /**
     * Images cache
     *
     * @var array
     */
    protected $cache = [];

    /**
     * Initialize images data
     *
     * @param float   $kunit  Unit of measure conversion ratio.
     * @param Encrypt $enc    Encrypt object.
     * @param bool    $pdfa   True if we are in PDF/A mode.
     */
    public function __construct($kunit, Encrypt $enc, $pdfa = false)
    {
        $this->kunit = (float) $kunit;
        $this->enc = $enc;
        $this->pdfa = (bool) $pdfa;
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
     * Get the PDF output string to print the specified image ID
     *
     * @param int $iid        Image ID
     * @param int $xpos       Abscissa (X coordinate) of the upper-left Image corner
     * @param int $ypos       Ordinate (Y coordinate) of the upper-left Image corner
     * @param int $width      Image width in user units
     * @param int $height     Image height in user units
     * @param int $pageheight Page height in user units
     *
     * @return string
     */
    public function getSetImage($iid, $xpos, $ypos, $width, $height, $pageheight)
    {
        if (empty($this->image[$iid])) {
            throw new ImageException('Unknown image ID: '.$iid);
        }
        $out = 'q';
        $out .= sprintf(
            ' %F 0 0 %F %F %F cm',
            ($width * $this->kunit),
            ($height * $this->kunit),
            ($xpos * $this->kunit),
            (($pageheight - $ypos - $height) * $this->kunit) // reverse coordinate
        );
        if (!empty($this->cache[$this->image[$iid]['key']]['mask'])) {
            $out .= ' /IMGmask'.$iid.' Do';
            if (!empty($this->cache[$this->image[$iid]['key']]['plain'])) {
                $out .= ' /IMGplain'.$iid.' Do';
            }
        } else {
            $out .= ' /IMG'.$iid.' Do';
        }
        $out .= ' Q';
        return $out;
    }

    /**
     * Get the PDF output string for Images
     *
     * @param int $pon Current PDF Object Number
     *
     * @return string
     */
    public function getOutImagesBlock($pon)
    {
        $this->pon = (int) $pon;
        $out = '';
        foreach ($this->image as $iid => $img) {
            if (empty($this->cache[$img['key']]['out'])) {
                if (!empty($this->cache[$img['key']]['mask'])) {
                    $out .= $this->getOutImage($img, $this->cache[$img['key']]['mask'], 'mask');
                    if (!empty($this->cache[$img['key']]['plain'])) {
                        $out .= $this->getOutImage($img, $this->cache[$img['key']]['plain'], 'plain');
                    }
                } else {
                    $out .= $this->getOutImage($img, $this->cache[$img['key']]);
                }
                $this->image[$iid] = $img;
            }

            if (!empty($this->cache[$img['key']]['mask']['obj'])) {
                $this->xobjdict['IMG'.$img['iid']] = $this->cache[$img['key']]['mask']['obj'];
                if (!empty($this->cache[$img['key']]['plain']['obj'])) {
                    $this->xobjdict['IMG'.$img['iid']] = $this->cache[$img['key']]['plain']['obj'];
                }
            } else {
                $this->xobjdict['IMG'.$img['iid']] = $this->cache[$img['key']]['obj'];
            }
        }
        return $out;
    }

    /**
     * Get the PDF output string for Image object
     *
     * @param array  $img  Image reference
     * @param array  $data Image raw data
     * @param string $sub  Sub image ('mask', 'plain' or empty string)
     *
     * @return string
     */
    protected function getOutImage(&$img, &$data, $sub = '')
    {
        $out = $this->getOutIcc($data)
                .$this->getOutPalette($data)
                .$this->getOutAltImages($img, $data, $sub);

        $data['obj'] = ++$this->pon;

        $out .= $this->pon.' 0 obj'."\n"
            .'<</Type /XObject'
            .' /Subtype /Image'
            .' /Width '.$data['width']
            .' /Height '.$data['height']
            .$this->getOutColorInfo($data);

        if (!empty($data['exturl'])) {
            // external stream
            $out .= ' /Length 0'
                .' /F << /FS /URL /F '.$this->enc->escapeDataString($data['exturl'], $this->pon).' >>';
            if (!empty($data['filter'])) {
                $out .= ' /FFilter /'.$data['filter'];
            }
            $out .= ' >> stream'."\n"
                .'endstream'."\n";
        } else {
            if (!empty($data['filter'])) {
                $out .= ' /Filter /'.$data['filter'];
            }
            if (!empty($data['parms'])) {
                $out .= ' '.$data['parms'];
            }

            // Colour Key Masking
            if (!empty($data['trns'])) {
                $trns = $this->getOutTransparency($data);
                if (!empty($trns)) {
                    $out .= ' /Mask [ '.$trns.']';
                }
            }

            $stream = $this->enc->encryptString($data['data'], $this->pon);
            $out .=' /Length '.strlen($stream)
                .'>> stream'."\n"
                .$stream."\n"
                .'endstream'."\n";
        }

        $out .= 'endobj'."\n";

        $this->cache[$img['key']]['out'] = true; // mark this as done

        return $out;
    }

    /**
    * Return XObjects Dictionary portion for the images
    *
    * @return string
    */
    public function getXobjectDict()
    {
        $out = '';
        foreach ($this->xobjdict as $iid => $objid) {
            $out .= ' /'.$iid.' '.$objid.' 0 R';
        }
        return $out;
    }

    /**
     * Get the PDF output string for ICC object
     *
     * @param array  $data Image raw data
     *
     * @return string
     */
    protected function getOutIcc(&$data)
    {
        if (empty($data['icc'])) {
            return '';
        }

        $data['obj_icc'] = ++$this->pon;
        $stream = $this->enc->encryptString(gzcompress($data['icc']), $this->pon);

        return $this->pon.' 0 obj'."\n"
            .'<</N '.$data['channels']
            .' /Alternate /'.$data['colspace']
            .' /Filter /FlateDecode'
            .' /Length '.strlen($stream)
            .'>> stream'."\n"
            .$stream."\n"
            .'endstream'."\n"
            .'endobj'."\n";
    }

    /**
     * Get the PDF output string for Indexed palette object
     *
     * @param array  $data Image raw data
     *
     * @return string
     */
    protected function getOutPalette(&$data)
    {
        if ($data['colspace'] != 'Indexed') {
            return '';
        }

        $data['obj_pal'] = ++$this->pon;
        $stream = $this->enc->encryptString(gzcompress($data['pal']), $this->pon);

        return $this->pon.' 0 obj'."\n"
            .'<</Filter /FlateDecode'
            .' /Length '.strlen($stream)
            .'>> stream'."\n"
            .$stream."\n"
            .'endstream'."\n"
            .'endobj'."\n";
    }

    /**
     * Get the PDF output string for color and mask information
     *
     * @param array  $data Image raw data
     *
     * @return string
     */
    protected function getOutColorInfo($data)
    {
        $out = '';
        // set color space
        if (!empty($data['obj_icc'])) {
            // ICC Colour Space
            $out .= ' /ColorSpace [/ICCBased '.$data['obj_icc'].' 0 R]';
        } elseif (!empty($data['obj_pal'])) {
            // Indexed Colour Space
            $out .= ' /ColorSpace [/Indexed /DeviceRGB '
                .((strlen($data['pal']) / 3) - 1)
                .' '.$data['obj_pal'].' 0 R]';
        } else {
            // Device Colour Space
            $out .= ' /ColorSpace /'.$data['colspace'];
        }
        if ($data['colspace'] == 'DeviceCMYK') {
            $out .= ' /Decode [1 0 1 0 1 0 1 0]';
        }
        $out .= ' /BitsPerComponent '.$data['bits'];

        if (!empty($this->cache[$data['key']]['mask']['obj'])) {
            $out .= ' /SMask '.$this->cache[$data['key']]['mask']['obj'].' 0 R';
        }

        if (!empty($data['obj_alt'])) {
            // reference to alternate images dictionary
            $out .= ' /Alternates '.$data['obj_alt'].' 0 R';
        }
        return $out;
    }

    /**
     * Get the PDF output string for Alternate images object
     *
     * @param array  $img  Image reference
     * @param array  $data Image raw data
     * @param string $sub  Sub image ('mask', 'plain' or empty string)
     *
     * @return string
     */
    protected function getOutAltImages($img, &$data, $sub = '')
    {
        if ($this->pdfa || empty($img['altimgs']) || ($sub == 'mask')) {
            return '';
        }

        $data['obj_alt'] = ++$this->pon;

        $out = $this->pon.' 0 obj'."\n"
            .'[';
        foreach ($img['altimgs'] as $iid) {
            if (!empty($this->cache[$this->image[$iid]['key']]['obj'])) {
                $out .= ' <<'
                    .' /Image '.$this->cache[$this->image[$iid]['key']]['obj'].' 0 R'
                    .' /DefaultForPrinting '.(empty($this->image[$iid]['defprint']) ? 'false' : 'true')
                    .' >>';
            }
        }
        $out .= ' ]'."\n"
            .'endobj'."\n";

        return $out;
    }

    /**
     * Get the PDF output string for color and mask information
     *
     * @param array  $data Image raw data
     *
     * @return string
     */
    protected function getOutTransparency($data)
    {
        $trns = '';
        foreach ($data['trns'] as $idx => $val) {
            if ($val == 0) {
                $trns .= $idx.' '.$idx.' ';
            }
        }
        return $trns;
    }
}
