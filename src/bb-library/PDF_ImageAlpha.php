<?php

/*******************************************************************************
 * Software: PDF_ImageAlpha
 * Version:  1.4
 * Date:     2009-12-28
 * Author:   Valentin Schmidt
 *
 * Requirements: FPDF 1.6
 *
 * This script allows to use images (PNGs or JPGs) with alpha-channels.
 * The alpha-channel can be either supplied as separate 8-bit PNG ("mask"),
 * or, for PNGs, also an internal alpha-channel can be used.
 * For the latter the GD 2.x extension is required.
 *******************************************************************************/
class PDF_ImageAlpha extends \tFPDF
{

//Private properties 
    private $tmpFiles = array();

    /*******************************************************************************
     *                                                                              *
     *                               Public methods                                 *
     *                                                                              *
     *******************************************************************************/
    function Image($file, $x = null, $y = null, $w = 0, $h = 0, $type = '', $link = '', $isMask = false, $maskImg = 0)
    {
        //Put an image on the page
        if (!isset($this->images[$file])) {
            //First use of image, get info
            if ($type == '') {
                $pos = strrpos($file, '.');
                if (!$pos)
                    $this->Error('Image file has no extension and no type was specified: ' . $file);
                $type = substr($file, $pos + 1);
            }
            $type = strtolower($type);
            if ($type == 'jpg' || $type == 'jpeg')
                $info = $this->_parsejpg($file);
            elseif ($type == 'png') {
                $info = $this->_parsepng($file);
                if ($info == 'alpha') return $this->ImagePngWithAlpha($file, $x, $y, $w, $h, $link);
            } else {
                //Allow for additional formats
                $mtd = '_parse' . $type;
                if (!method_exists($this, $mtd))
                    $this->Error('Unsupported image type: ' . $type);
                $info = $this->$mtd($file);
            }

            if ($isMask) {
                $info['cs'] = "DeviceGray"; // try to force grayscale (instead of indexed)
            }
            $info['i'] = count($this->images) + 1;
            if ($maskImg > 0) $info['masked'] = $maskImg;###
            $this->images[$file] = $info;
        } else
            $info = $this->images[$file];
        //Automatic width and height calculation if needed
        if ($w == 0 && $h == 0) {
            //Put image at 72 dpi
            $w = $info['w'] / $this->k;
            $h = $info['h'] / $this->k;
        }
        if ($w == 0)
            $w = $h * $info['w'] / $info['h'];
        if ($h == 0)
            $h = $w * $info['h'] / $info['w'];

        // embed hidden, ouside the canvas
        if ((float)$this->PDFVersion >= 1.7) {
            if ($isMask) $x = ($this->CurOrientation == 'P' ? $this->CurPageSize[0] : $this->CurPageSize[1]) + 10;
        } else {
            if ($isMask) $x = ($this->CurOrientation == 'P' ? $this->CurPageSize[0] : $this->CurPageSize[1]) + 10;
        }

        $this->_out(sprintf('q %.2f 0 0 %.2f %.2f %.2f cm /I%d Do Q', $w * $this->k, $h * $this->k, $x * $this->k, ($this->h - ($y + $h)) * $this->k, $info['i']));

        if ($link)
            $this->Link($x, $y, $w, $h, $link);

        return $info['i'];
    }

// needs GD 2.x extension 
// pixel-wise operation, not very fast 
    function ImagePngWithAlpha($file, $x, $y, $w = 0, $h = 0, $link = '')
    {
        $tmp_alpha        = tempnam('.', 'mska');
        $this->tmpFiles[] = $tmp_alpha;
        $tmp_plain        = tempnam('.', 'mskp');
        $this->tmpFiles[] = $tmp_plain;

        list($wpx, $hpx) = getimagesize($file);
        $img       = imagecreatefrompng($file);
        $alpha_img = imagecreate($wpx, $hpx);

        // generate gray scale pallete
        for ($c = 0; $c < 256; $c++) ImageColorAllocate($alpha_img, $c, $c, $c);

        // extract alpha channel
        $xpx = 0;
        while ($xpx < $wpx) {
            $ypx = 0;
            while ($ypx < $hpx) {
                $color_index = imagecolorat($img, $xpx, $ypx);
                $alpha       = 255 - ($color_index >> 24) * 255 / 127; // GD alpha component: 7 bit only, 0..127!
                imagesetpixel($alpha_img, $xpx, $ypx, $alpha);
                ++$ypx;
            }
            ++$xpx;
        }

        imagepng($alpha_img, $tmp_alpha);
        imagedestroy($alpha_img);

        // extract image without alpha channel
        $plain_img = imagecreatetruecolor($wpx, $hpx);
        imagecopy($plain_img, $img, 0, 0, 0, 0, $wpx, $hpx);
        imagepng($plain_img, $tmp_plain);
        imagedestroy($plain_img);

        //first embed mask image (w, h, x, will be ignored)
        $maskImg = $this->Image($tmp_alpha, 0, 0, 0, 0, 'PNG', '', true);

        //embed image, masked with previously embedded mask
        $this->Image($tmp_plain, $x, $y, $w, $h, 'PNG', $link, false, $maskImg);
    }

    function Close()
    {
        parent::Close();
        // clean up tmp files
        foreach ($this->tmpFiles as $tmp) @unlink($tmp);
    }

    /*******************************************************************************
     *                                                                              *
     *                               Private methods                                *
     *                                                                              *
     *******************************************************************************/
    function _putimages()
    {
        $filter = ($this->compress) ? '/Filter /FlateDecode ' : '';
        reset($this->images);
        while (list($file, $info) = each($this->images)) {
            $this->_newobj();
            $this->images[$file]['n'] = $this->n;
            $this->_out('<</Type /XObject');
            $this->_out('/Subtype /Image');
            $this->_out('/Width ' . $info['w']);
            $this->_out('/Height ' . $info['h']);

            if (isset($info["masked"])) $this->_out('/SMask ' . ($this->n - 1) . ' 0 R'); ###

            if ($info['cs'] == 'Indexed')
                $this->_out('/ColorSpace [/Indexed /DeviceRGB ' . (strlen($info['pal']) / 3 - 1) . ' ' . ($this->n + 1) . ' 0 R]');
            else {
                $this->_out('/ColorSpace /' . $info['cs']);
                if ($info['cs'] == 'DeviceCMYK')
                    $this->_out('/Decode [1 0 1 0 1 0 1 0]');
            }
            $this->_out('/BitsPerComponent ' . $info['bpc']);
            if (isset($info['f']))
                $this->_out('/Filter /' . $info['f']);
            if (isset($info['parms']))
                $this->_out($info['parms']);
            if (isset($info['trns']) && is_array($info['trns'])) {
                $trns = '';
                for ($i = 0; $i < count($info['trns']); $i++)
                    $trns .= $info['trns'][$i] . ' ' . $info['trns'][$i] . ' ';
                $this->_out('/Mask [' . $trns . ']');
            }
            $this->_out('/Length ' . strlen($info['data']) . '>>');
            $this->_putstream($info['data']);
            unset($this->images[$file]['data']);
            $this->_out('endobj');
            //Palette
            if ($info['cs'] == 'Indexed') {
                $this->_newobj();
                $pal = ($this->compress) ? gzcompress($info['pal']) : $info['pal'];
                $this->_out('<<' . $filter . '/Length ' . strlen($pal) . '>>');
                $this->_putstream($pal);
                $this->_out('endobj');
            }
        }
    }

// this method overwriing the original version is only needed to make the Image method support PNGs with alpha channels. 
// if you only use the ImagePngWithAlpha method for such PNGs, you can remove it from this script. 
    function _parsepng($file)
    {
        //Extract info from a PNG file
        $f = fopen($file, 'rb');
        if (!$f)
            $this->Error('Can\'t open image file: ' . $file);
        //Check signature
        if (fread($f, 8) != chr(137) . 'PNG' . chr(13) . chr(10) . chr(26) . chr(10))
            $this->Error('Not a PNG file: ' . $file);
        //Read header chunk
        fread($f, 4);
        if (fread($f, 4) != 'IHDR')
            $this->Error('Incorrect PNG file: ' . $file);
        $w   = $this->_readint($f);
        $h   = $this->_readint($f);
        $bpc = ord(fread($f, 1));
        if ($bpc > 8)
            $this->Error('16-bit depth not supported: ' . $file);
        $ct = ord(fread($f, 1));
        if ($ct == 0)
            $colspace = 'DeviceGray';
        elseif ($ct == 2)
            $colspace = 'DeviceRGB';
        elseif ($ct == 3)
            $colspace = 'Indexed';
        else {
            fclose($f);      // the only changes are
            return 'alpha';  // made in those 2 lines
        }
        if (ord(fread($f, 1)) != 0)
            $this->Error('Unknown compression method: ' . $file);
        if (ord(fread($f, 1)) != 0)
            $this->Error('Unknown filter method: ' . $file);
        if (ord(fread($f, 1)) != 0)
            $this->Error('Interlacing not supported: ' . $file);
        fread($f, 4);
        $parms = '/DecodeParms <</Predictor 15 /Colors ' . ($ct == 2 ? 3 : 1) . ' /BitsPerComponent ' . $bpc . ' /Columns ' . $w . '>>';
        //Scan chunks looking for palette, transparency and image data
        $pal  = '';
        $trns = '';
        $data = '';
        do {
            $n    = $this->_readint($f);
            $type = fread($f, 4);
            if ($type == 'PLTE') {
                //Read palette
                $pal = fread($f, $n);
                fread($f, 4);
            } elseif ($type == 'tRNS') {
                //Read transparency info
                $t = fread($f, $n);
                if ($ct == 0)
                    $trns = array(ord(substr($t, 1, 1)));
                elseif ($ct == 2)
                    $trns = array(ord(substr($t, 1, 1)), ord(substr($t, 3, 1)), ord(substr($t, 5, 1)));
                else {
                    $pos = strpos($t, chr(0));
                    if ($pos !== false)
                        $trns = array($pos);
                }
                fread($f, 4);
            } elseif ($type == 'IDAT') {
                //Read image data block
                $data .= fread($f, $n);
                fread($f, 4);
            } elseif ($type == 'IEND')
                break;
            else
                fread($f, $n + 4);
        } while ($n);
        if ($colspace == 'Indexed' && empty($pal))
            $this->Error('Missing palette in ' . $file);
        fclose($f);

        return array('w' => $w, 'h' => $h, 'cs' => $colspace, 'bpc' => $bpc, 'f' => 'FlateDecode', 'parms' => $parms, 'pal' => $pal, 'trns' => $trns, 'data' => $data);
    }

} 
