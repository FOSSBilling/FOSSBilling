<?php
/**
 * Type.php
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 *
 * This file is part of tc-lib-barcode software library.
 */

namespace Com\Tecnick\Barcode;

use \Com\Tecnick\Barcode\Exception as BarcodeException;
use \Com\Tecnick\Color\Exception as ColorException;

/**
 * Com\Tecnick\Barcode\Type
 *
 * Barcode Type class
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
abstract class Type extends \Com\Tecnick\Barcode\Type\Convert
{
    /**
     * Barcode type (linear or square)
     *
     * @var string
     */
    protected $type = '';

    /**
     * Barcode format
     *
     * @var string
     */
    protected $format = '';
    
    /**
     * Array containing extra parameters for the specified barcode type
     *
     * @var array
     */
    protected $params;

    /**
     * Code to convert (barcode content)
     *
     * @var string
     */
    protected $code = '';

    /**
     * Resulting code after applying checksum etc.
     *
     * @var string
     */
    protected $extcode = '';

    /**
     * Total number of columns
     *
     * @var int
     */
    protected $ncols = 0;

    /**
     * Total number of rows
     *
     * @var int
     */
    protected $nrows = 1;

    /**
     * Array containing the position and dimensions of each barcode bar
     * (x, y, width, height)
     *
     * @var array
     */
    protected $bars = array();

    /**
     * Barcode width
     *
     * @var float
     */
    protected $width;
    
    /**
     * Barcode height
     *
     * @var float
     */
    protected $height;

    /**
     * Additional padding to add around the barcode (top, right, bottom, left) in user units.
     * A negative value indicates the multiplication factor for each row or column.
     *
     * @var array
     */
    protected $padding = array('T' => 0, 'R' => 0, 'B' => 0, 'L' => 0);

    /**
     * Ratio between the barcode width and the number of rows
     *
     * @var float
     */
    protected $width_ratio;

    /**
     * Ratio between the barcode height and the number of columns
     *
     * @var float
     */
    protected $height_ratio;

    /**
     * Foreground Color object
     *
     * @var Color object
     */
    protected $color_obj;

    /**
     * Backgorund Color object
     *
     * @var Color object
     */
    protected $bg_color_obj;

    /**
     * Initialize a new barcode object
     *
     * @param string $code    Barcode content
     * @param int    $width   Barcode width in user units (excluding padding).
     *                        A negative value indicates the multiplication factor for each column.
     * @param int    $height  Barcode height in user units (excluding padding).
     *                        A negative value indicates the multiplication factor for each row.
     * @param string $color   Foreground color in Web notation (color name, or hexadecimal code, or CSS syntax)
     * @param array  $params  Array containing extra parameters for the specified barcode type
     * @param array  $padding Additional padding to add around the barcode (top, right, bottom, left) in user units.
     *                        A negative value indicates the number or rows or columns.
     *
     * @throws BarcodeException in case of error
     * @throws ColorException in case of color error
     */
    public function __construct(
        $code,
        $width = -1,
        $height = -1,
        $color = 'black',
        $params = array(),
        $padding = array(0, 0, 0, 0)
    ) {
        $this->code = $code;
        $this->extcode = $code;
        $this->params = $params;
        $this->setParameters();
        $this->setBars();
        $this->setSize($width, $height, $padding);
        $this->setColor($color);
    }

    /**
     * Set extra (optional) parameters
     */
    protected function setParameters()
    {
    }

    /**
     * Set the bars array
     *
     * @throws BarcodeException in case of error
     */
    abstract protected function setBars();

    /**
     * Set the size of the barcode to be exported
     *
     * @param int    $width   Barcode width in user units (excluding padding).
     *                        A negative value indicates the multiplication factor for each column.
     * @param int    $height  Barcode height in user units (excluding padding).
     *                        A negative value indicates the multiplication factor for each row.
     * @param array  $padding Additional padding to add around the barcode (top, right, bottom, left) in user units.
     *                        A negative value indicates the number or rows or columns.
     */
    public function setSize($width, $height, $padding = array(0, 0, 0, 0))
    {
        $this->width = intval($width);
        if ($this->width <= 0) {
            $this->width = (abs(min(-1, $this->width)) * $this->ncols);
        }

        $this->height = intval($height);
        if ($this->height <= 0) {
            $this->height = (abs(min(-1, $this->height)) * $this->nrows);
        }

        $this->width_ratio = ($this->width / $this->ncols);
        $this->height_ratio = ($this->height / $this->nrows);

        $this->setPadding($padding);

        return $this;
    }

    /**
     * Set the barcode padding
     *
     * @param array  $padding Additional padding to add around the barcode (top, right, bottom, left) in user units.
     *                        A negative value indicates the number or rows or columns.
     *
     * @throws BarcodeException in case of error
     */
    protected function setPadding($padding)
    {
        if (!is_array($padding) || (count($padding) != 4)) {
            throw new BarcodeException('Invalid padding, expecting an array of 4 numbers (top, right, bottom, left)');
        }
        $map = array(
            array('T', $this->height_ratio),
            array('R', $this->width_ratio),
            array('B', $this->height_ratio),
            array('L', $this->width_ratio)
        );
        foreach ($padding as $key => $val) {
            $val = intval($val);
            if ($val < 0) {
                $val = (abs(min(-1, $val)) * $map[$key][1]);
            }
            $this->padding[$map[$key][0]] = $val;
        }

        return $this;
    }

    /**
     * Set the color of the bars.
     * If the color is transparent or empty it will be set to the default black color.
     *
     * @param string $color Foreground color in Web notation (color name, or hexadecimal code, or CSS syntax)
     *
     * @throws ColorException in case of color error
     * @throws BarcodeException in case of empty or transparent color
     */
    public function setColor($color)
    {
        $this->color_obj = $this->getRgbColorObject($color);
        if ($this->color_obj === null) {
            throw new BarcodeException('The foreground color cannot be empty or transparent');
        }
        return $this;
    }

    /**
     * Set the background color
     *
     * @param string $color Background color in Web notation (color name, or hexadecimal code, or CSS syntax)
     *
     * @throws ColorException in case of color error
     */
    public function setBackgroundColor($color)
    {
        $this->bg_color_obj = $this->getRgbColorObject($color);
        return $this;
    }

    /**
     * Get the RGB Color object for the given color representation
     *
     * @param string $color Color in Web notation (color name, or hexadecimal code, or CSS syntax)
     *
     * @return Color object or null
     *
     * @throws ColorException in case of color error
     */
    protected function getRgbColorObject($color)
    {
        $conv = new \Com\Tecnick\Color\Pdf();
        $cobj = $conv->getColorObject($color);
        if ($cobj !== null) {
            return new \Com\Tecnick\Color\Model\Rgb($cobj->toRgbArray());
        }
        return null;
    }

    /**
     * Get the barcode raw array
     *
     * @return array
     */
    public function getArray()
    {
        return array(
            'type'         => $this->type,
            'format'       => $this->format,
            'params'       => $this->params,
            'code'         => $this->code,
            'extcode'      => $this->extcode,
            'ncols'        => $this->ncols,
            'nrows'        => $this->nrows,
            'width'        => $this->width,
            'height'       => $this->height,
            'width_ratio'  => $this->width_ratio,
            'height_ratio' => $this->height_ratio,
            'padding'      => $this->padding,
            'full_width'   => ($this->width + $this->padding['L'] + $this->padding['R']),
            'full_height'  => ($this->height + $this->padding['T'] + $this->padding['B']),
            'color_obj'    => $this->color_obj,
            'bg_color_obj' => $this->bg_color_obj,
            'bars'         => $this->bars
        );
    }

    /**
     * Get the extended code (code + checksum)
     *
     * @return string
     */
    public function getExtendedCode()
    {
        return $this->extcode;
    }

    /**
     * Get the barcode as SVG image object
     */
    public function getSvg()
    {
        $data = $this->getSvgCode();
        header('Content-Type: application/svg+xml');
        header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0, max-age=1');
        header('Pragma: public');
        header('Expires: Thu, 04 jan 1973 00:00:00 GMT'); // Date in the past
        header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
        header('Content-Disposition: inline; filename="'.md5($data).'.svg";');
        if (empty($_SERVER['HTTP_ACCEPT_ENCODING'])) {
            // the content length may vary if the server is using compression
            header('Content-Length: '.strlen($data));
        }
        echo $data;
    }

    /**
     * Get the barcode as SVG code
     *
     * @return string SVG code
     */
    public function getSvgCode()
    {
        // flags for htmlspecialchars
        $hflag = ENT_NOQUOTES;
        if (defined('ENT_XML1') && defined('ENT_DISALLOWED')) {
            $hflag = ENT_XML1 | ENT_DISALLOWED;
        }
        $width = sprintf('%F', ($this->width + $this->padding['L'] + $this->padding['R']));
        $height = sprintf('%F', ($this->height + $this->padding['T'] + $this->padding['B']));
        $svg = '<?xml version="1.0" standalone="no" ?>'."\n"
            .'<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">'."\n"
            .'<svg'
            .' width="'.$width.'"'
            .' height="'.$height.'"'
            .' viewBox="0 0 '.$width.' '.$height.'"'
            .' version="1.1"'
            .' xmlns="http://www.w3.org/2000/svg"'
            .'>'."\n"
            ."\t".'<desc>'.htmlspecialchars($this->code, $hflag, 'UTF-8').'</desc>'."\n";
        if ($this->bg_color_obj !== null) {
            $svg .= "\t".'<rect'
                .' x="0"'
                .' y="0"'
                .' width="'.$width.'"'
                .' height="'.$height.'"'
                .' fill="'.$this->bg_color_obj->getRgbHexColor().'"'
                .' stroke="none"'
                .' stroke-width="0"'
                .' stroke-linecap="square"'
                .' />'."\n";
        }
        $svg .= "\t".'<g'
            .' id="bars"'
            .' fill="'.$this->color_obj->getRgbHexColor().'"'
            .' stroke="none"'
            .' stroke-width="0"'
            .' stroke-linecap="square"'
            .'>'."\n";
        $bars = $this->getBarsArray('XYWH');
        foreach ($bars as $rect) {
            $svg .= "\t\t".'<rect'
                .' x="'.sprintf('%F', $rect[0]).'"'
                .' y="'.sprintf('%F', $rect[1]).'"'
                .' width="'.sprintf('%F', $rect[2]).'"'
                .' height="'.sprintf('%F', $rect[3]).'"'
                .' />'."\n";
        }
        $svg .= "\t".'</g>'."\n"
            .'</svg>'."\n";
        return $svg;
    }

    /**
     * Get an HTML representation of the barcode.
     *
     * @return string HTML code (DIV block)
     */
    public function getHtmlDiv()
    {
        $html = '<div style="'
            .'width:'.sprintf('%F', ($this->width + $this->padding['L'] + $this->padding['R'])).'px;'
            .'height:'.sprintf('%F', ($this->height + $this->padding['T'] + $this->padding['B'])).'px;'
            .'position:relative;'
            .'font-size:0;'
            .'border:none;'
            .'padding:0;'
            .'margin:0;';
        if ($this->bg_color_obj !== null) {
            $html .= 'background-color:'.$this->bg_color_obj->getCssColor().';';
        }
        $html .= '">'."\n";
        $bars = $this->getBarsArray('XYWH');
        foreach ($bars as $rect) {
            $html .= "\t".'<div style="background-color:'.$this->color_obj->getCssColor().';'
                .'left:'.sprintf('%F', $rect[0]).'px;'
                .'top:'.sprintf('%F', $rect[1]).'px;'
                .'width:'.sprintf('%F', $rect[2]).'px;'
                .'height:'.sprintf('%F', $rect[3]).'px;'
                .'position:absolute;'
                .'border:none;'
                .'padding:0;'
                .'margin:0;'
                .'">&nbsp;</div>'."\n";
        }
        $html .= '</div>'."\n";
        return $html;
    }

    /**
     * Get Barcode as PNG Image (requires GD or Imagick library)
     */
    public function getPng()
    {
        $data = $this->getPngData();
        header('Content-Type: image/png');
        header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0, max-age=1');
        header('Pragma: public');
        header('Expires: Thu, 04 jan 1973 00:00:00 GMT'); // Date in the past
        header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
        header('Content-Disposition: inline; filename="'.md5($data).'.png";');
        if (empty($_SERVER['HTTP_ACCEPT_ENCODING'])) {
            // the content length may vary if the server is using compression
            header('Content-Length: '.strlen($data));
        }
        echo $data;
    }

    /**
     * Get the barcode as PNG image (requires GD or Imagick library)
     *
     * @param bool $imagick If true try to use the Imagick extension
     *
     * @return string PNG image data
     */
    public function getPngData($imagick = true)
    {
        if ($imagick && extension_loaded('imagick')) {
            return $this->getPngDataImagick();
        }
        $img = $this->getGd();
        ob_start();
        imagepng($img);
        return ob_get_clean();
    }

    /**
     * Get the barcode as PNG image (requires Imagick library)
     *
     * @return object
     *
     * @throws BarcodeException if the Imagick library is not installed
     */
    public function getPngDataImagick()
    {
        $img = new \Imagick();
        $width = ceil($this->width + $this->padding['L'] + $this->padding['R']);
        $height = ceil($this->height + $this->padding['T'] + $this->padding['B']);
        $img->newImage($width, $height, 'none', 'png');
        $barcode = new \imagickdraw();
        if ($this->bg_color_obj !== null) {
            $rgbcolor = $this->bg_color_obj->getNormalizedArray(255);
            $bg_color = new \imagickpixel('rgb('.$rgbcolor['R'].','.$rgbcolor['G'].','.$rgbcolor['B'].')');
            $barcode->setfillcolor($bg_color);
            $barcode->rectangle(0, 0, $width, $height);
        }
        $rgbcolor = $this->color_obj->getNormalizedArray(255);
        $bar_color = new \imagickpixel('rgb('.$rgbcolor['R'].','.$rgbcolor['G'].','.$rgbcolor['B'].')');
        $barcode->setfillcolor($bar_color);
        $bars = $this->getBarsArray('XYXY');
        foreach ($bars as $rect) {
            $barcode->rectangle($rect[0], $rect[1], $rect[2], $rect[3]);
        }
        $img->drawimage($barcode);
        return $img->getImageBlob();
    }

    /**
     * Get the barcode as GD image object (requires GD library)
     *
     * @return object
     *
     * @throws BarcodeException if the GD library is not installed
     */
    public function getGd()
    {
        $width = ceil($this->width + $this->padding['L'] + $this->padding['R']);
        $height = ceil($this->height + $this->padding['T'] + $this->padding['B']);
        $img = imagecreate($width, $height);
        if ($this->bg_color_obj === null) {
            $bgobj = clone $this->color_obj;
            $rgbcolor = $bgobj->invertColor()->getNormalizedArray(255);
            $background_color = imagecolorallocate($img, $rgbcolor['R'], $rgbcolor['G'], $rgbcolor['B']);
            imagecolortransparent($img, $background_color);
        } else {
            $rgbcolor = $this->bg_color_obj->getNormalizedArray(255);
            $bg_color = imagecolorallocate($img, $rgbcolor['R'], $rgbcolor['G'], $rgbcolor['B']);
            imagefilledrectangle($img, 0, 0, $width, $height, $bg_color);
        }
        $rgbcolor = $this->color_obj->getNormalizedArray(255);
        $bar_color = imagecolorallocate($img, $rgbcolor['R'], $rgbcolor['G'], $rgbcolor['B']);
        $bars = $this->getBarsArray('XYXY');
        foreach ($bars as $rect) {
            imagefilledrectangle($img, $rect[0], $rect[1], $rect[2], $rect[3], $bar_color);
        }
        return $img;
    }

    /**
     * Get a raw barcode string representation using characters
     *
     * @param string $space_char Character or string to use for filling empty spaces
     * @param string $bar_char   Character or string to use for filling bars
     *
     * @return string
     */
    public function getGrid($space_char = '0', $bar_char = '1')
    {
        $raw = $this->getGridArray($space_char, $bar_char);
        $grid = '';
        foreach ($raw as $row) {
            $grid .= implode($row)."\n";
        }
        return $grid;
    }

    /**
     * Get the array containing all the formatted bars coordinates
     *
     * @param string $type Type of coordinates to return: 'XYXY' or 'XYWH'
     *
     * @return array
     */
    public function getBarsArray($type = 'XYXY')
    {
        $mtd = 'getBarRect'.$type;
        $rect = array();
        foreach ($this->bars as $bar) {
            if (($bar[2] > 0) && ($bar[3] > 0)) {
                $rect[] = $this->$mtd($bar);
            }
        }
        if ($this->nrows > 1) {
            // reprint rotated to cancel row gaps
            $rot = $this->getRotatedBarArray();
            foreach ($rot as $bar) {
                if (($bar[2] > 0) && ($bar[3] > 0)) {
                    $rect[] = $this->$mtd($bar);
                }
            }
        }
        return $rect;
    }
}
