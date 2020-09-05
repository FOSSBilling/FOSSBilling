<?php
/**
 * Import.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfImage
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-image
 *
 * This file is part of tc-lib-pdf-image software library.
 */

namespace Com\Tecnick\Pdf\Image;

use \Com\Tecnick\File\File;
use \Com\Tecnick\Pdf\Image\Exception as ImageException;
use Com\Tecnick\Pdf\Image\Import\ImageImportInterface;

/**
 * Com\Tecnick\Pdf\Image\Import
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfImage
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-image
 */
class Import extends \Com\Tecnick\Pdf\Image\Output
{
    /**
     * Image index.
     * Count the number of added images.
     *
     * @var int
     */
    protected $iid = 0;

    /**
     * Stack of added images.
     *
     * @var array
     */
    protected $image = array();

    /**
     * Cache used to store imported image data.
     * The same image data can be reused multiple times.
     *
     * @var array
     */
    protected $cache = array();

    /**
     * Native image types and associated importing class
     * (image types for which we have an import method)
     *
     * @var array
     */
    private static $native = array(
        IMAGETYPE_PNG  => 'Png',
        IMAGETYPE_JPEG => 'Jpeg',
    );

    /**
     * Lossless image types
     *
     * @var array
     */
    protected static $lossless = array(
        IMAGETYPE_GIF,
        IMAGETYPE_PNG,
        IMAGETYPE_PSD,
        IMAGETYPE_BMP,
        IMAGETYPE_WBMP,
        IMAGETYPE_XBM,
        IMAGETYPE_TIFF_II,
        IMAGETYPE_TIFF_MM,
        IMAGETYPE_IFF,
        IMAGETYPE_SWC,
        IMAGETYPE_ICO,
    );

    /**
     * Map number of channels with color space name
     *
     * @var array
     */
    protected static $colspacemap = array(
        1 => 'DeviceGray',
        3 => 'DeviceRGB',
        4 => 'DeviceCMYK',
    );

    /**
     * Add a new image
     *
     * @param string $image    Image file name, URL or a '@' character followed by the image data string.
     *                         To link an image without embedding it on the document, set an asterisk character
     *                         before the URL (i.e.: '*http://www.example.com/image.jpg').
     * @param int    $width    New width in pixels or null to keep the original value
     * @param int    $height   New height in pixels or null to keep the original value
     * @param bool   $ismask   True if the image is a transparency mask
     * @param int    $quality  Quality for JPEG files (0 = max compression; 100 = best quality, bigger file).
     * @param bool   $defprint Indicate if the image is the default for printing when used as alternative image.
     * @param array  $altimgs  Arrays of alternate image keys.
     *
     * @return int Image ID
     */
    public function add(
        $image,
        $width = null,
        $height = null,
        $ismask = false,
        $quality = 100,
        $defprint = false,
        $altimgs = array()
    ) {
        $data = $this->import($image, $width, $height, $ismask, $quality, $defprint);
        ++$this->iid;
        $this->image[$this->iid] = array(
            'iid'      => $this->iid,
            'key'      => $data['key'],
            'width'    => $data['width'],
            'height'   => $data['height'],
            'defprint' => $defprint,
            'altimgs'  => $altimgs,
        );
        return $this->iid;
    }

    /**
     * Get the Image key used for caching
     *
     * @param string $image   Image file name or content
     * @param int    $width   Width in pixels
     * @param int    $height  Height in pixels
     * @param int    $quality Quality for JPEG files
     *
     * @return string
     */
    public function getKey($image, $width = 0, $height = 0, $quality = 100)
    {
        return strtr(
            rtrim(
                base64_encode(
                    pack('H*', md5($image.$width.$height.$quality))
                ),
                '='
            ),
            '+/',
            '-_'
        );
    }

    /**
     * Get an imported image by key
     *
     * @param string $key Image key
     *
     * @return array Image raw data array
     */
    public function getImageDataByKey($key)
    {
        if (empty($this->cache[$key])) {
            throw new ImageException('Unknown key');
        }
        return $this->cache[$key];
    }

    /**
     * Import the original image raw data
     *
     * @param string $image    Image file name, URL or a '@' character followed by the image data string.
     *                         To link an image without embedding it on the document, set an asterisk character
     *                         before the URL (i.e.: '*http://www.example.com/image.jpg').
     * @param int    $width    New width in pixels or null to keep the original value
     * @param int    $height   New height in pixels or null to keep the original value
     * @param bool   $ismask   True if the image is a transparency mask
     * @param int    $quality  Quality for JPEG files (0 = max compression; 100 = best quality, bigger file).
     *
     * @return array Image raw data array
     */
    protected function import($image, $width = null, $height = null, $ismask = false, $quality = 100)
    {
        $quality = max(0, min(100, $quality));
        $imgkey = $this->getKey($image, intval($width), intval($height), $quality);

        if (isset($this->cache[$imgkey])) {
            return $this->cache[$imgkey];
        }

        $data = $this->getRawData($image);
        $data['key'] = $imgkey;

        if ($width === null) {
            $width = $data['width'];
        }
        $width = max(0, intval($width));
        if ($height === null) {
            $height = $data['height'];
        }
        $height = max(0, intval($height));

        if ((!$data['native']) || ($width != $data['width']) || ($height != $data['height'])) {
            $data = $this->getResizedRawData($data, $width, $height, true, $quality);
        }

        $data = $this->getData($data, $width, $height, $quality);

        if ($ismask) {
            $data['mask'] = $data;
        } elseif (!empty($data['splitalpha'])) {
            // create 2 separate images: plain + mask
            $data['plain'] = $this->getResizedRawData($data, $width, $height, false, $quality);
            $data['plain'] = $this->getData($data['plain'], $width, $height, $quality);
            $data['mask'] = $this->getAlphaChannelRawData($data);
            $data['mask'] = $this->getData($data['mask'], $width, $height, $quality);
        }

        // store data in cache
        $this->cache[$imgkey] = $data;

        return $data;
    }

    /**
     * Extract the relevant data from the image
     *
     * @param array  $data    Image raw data
     * @param int    $width   Width in pixels
     * @param int    $height  Height in pixels
     * @param int    $quality Quality for JPEG files
     *
     * @return array
     */
    protected function getData($data, $width, $height, $quality)
    {
        if (!$data['native']) {
            throw new ImageException('Unable to import image');
        }
        $imp = $this->createImportImage($data);
        $data = $imp->getData($data);

        if (!empty($data['recode'])) {
            // re-encode the image as it was not possible to decode it
            $data = $this->getResizedRawData($data, $width, $height, true, $quality);
            $data = $imp->getData($data);
        }
        return $data;
    }

    /**
     * @param array $data Image raw data
     *
     * @return ImageImportInterface
     */
    private function createImportImage($data)
    {
        $class = '\\Com\\Tecnick\\Pdf\\Image\\Import\\'.self::$native[$data['type']];
        return new $class();
    }

    /**
     * Get the original image raw data
     *
     * @param string $image Image file name, URL or a '@' character followed by the image data string.
     *                      To link an image without embedding it on the document, set an asterisk character
     *                      before the URL (i.e.: '*http://www.example.com/image.jpg').
     *
     * @return array Image data array
     */
    protected function getRawData($image)
    {
        // default data to return
        $data = array(
            'key'      => '',            // image key
            'defprint' => false,         // default printing image when used as alternate
            'raw'      => '',            // raw image data
            'file'     => '',            // source file name or URL
            'exturl'   => false,         // true if the image is an exernal URL that should not be embedded
            'width'    => 0,             // image width in pixels
            'height'   => 0,             // image height in pixels
            'type'     => 0,             // image type constant: IMAGETYPE_XXX
            'native'   => false,         // true if the image is PNG or JPEG
            'mapto'    => IMAGETYPE_PNG, // type to convert to
            'bits'     => 8,             // number of bits per channel
            'channels' => 3,             // number of channels
            'colspace' => 'DeviceRGB',   // color space
            'icc'      => '',            // ICC profile
            'filter'   => 'FlateDecode', // decoding filter
            'parms'    => '',            // additional PDF decoding parameters
            'pal'      => '',            // colour palette
            'trns'     => array(),       // colour key masking
            'data'     => '',            // PDF image data
            'ismask'   => false,         // true if the image is a transparency mask
        );

        if (empty($image)) {
            throw new ImageException('Empty image');
        }

        if ($image[0] === '@') { // image from string
            $data['raw'] = substr($image, 1);
        } else {
            if ($image[0] === '*') { // not-embedded external URL
                $data['exturl'] = true;
                $image = substr($image, 1);
            }
            $data['file'] = $image;
            $fobj = new File();
            $data['raw'] = $fobj->getFileData($image);
        }

        return $this->getMetaData($data);
    }

    /**
     * Get the image meta data
     *
     * @param array $data Image raw data
     *
     * @return array Image raw data array
     */
    protected function getMetaData($data)
    {
        try {
            $meta = getimagesizefromstring($data['raw']);
        } catch (\Exception $exc) {
            throw new ImageException('Invalid image format: '.$exc);
        }
        $data['width'] = $meta[0];
        $data['height'] = $meta[1];
        $data['type'] = $meta[2];
        $data['native'] = isset(self::$native[$data['type']]);
        $data['mapto'] = (in_array($data['type'], self::$lossless) ? IMAGETYPE_PNG : IMAGETYPE_JPEG);
        if (isset($meta['bits'])) {
            $data['bits'] = intval($meta['bits']);
        }
        if (isset($meta['channels'])) {
            $data['channels'] = intval($meta['channels']);
        }
        if (isset(self::$colspacemap[$data['channels']])) {
            $data['colspace'] = self::$colspacemap[$data['channels']];
        }
        return $data;
    }

    /**
     * Get the resized image raw data
     * (always convert the image type to a native format: PNG or JPEG)
     *
     * @param array  $data    Image raw data as returned by getImageRawData
     * @param int    $width   New width in pixels
     * @param int    $height  New height in pixels
     * @param bool   $alpha   If true save the alpha channel information, if false merge the alpha channel (PNG mode)
     * @param int    $quality Quality for JPEG files (0 = max compression; 100 = best quality, bigger file).
     *
     * @return array Image raw data array
     */
    protected function getResizedRawData($data, $width, $height, $alpha = true, $quality = 100)
    {
        $img = imagecreatefromstring($data['raw']);
        $newimg = imagecreatetruecolor($width, $height);
        imageinterlace($newimg, 0);
        imagealphablending($newimg, !$alpha);
        imagesavealpha($newimg, $alpha);
        imagecopyresampled($newimg, $img, 0, 0, 0, 0, $width, $height, $data['width'], $data['height']);
        ob_start();
        if ($data['mapto'] == IMAGETYPE_PNG) {
            if ((($tid = imagecolortransparent($img)) >= 0)
                && (($palsize = imagecolorstotal($img)) > 0)
                && ($tid < $palsize)
            ) {
                // set transparency for Indexed image
                $tcol = imagecolorsforindex($img, $tid);
                $tid = imagecolorallocate($newimg, $tcol['red'], $tcol['green'], $tcol['blue']);
                imagefill($newimg, 0, 0, $tid);
                imagecolortransparent($newimg, $tid);
            }
            imagepng($newimg, null, 9, PNG_ALL_FILTERS);
        } else {
            imagejpeg($newimg, null, $quality);
        }
        $data['raw'] = ob_get_clean();
        $data['exturl'] = false;
        $data['recoded'] = true;
        return $this->getMetaData($data);
    }

    /**
     * Extract the alpha channel as separate image to be used as a mask
     *
     * @param array $data Image raw data as returned by getImageRawData
     *
     * @return array Image raw data array
     */
    protected function getAlphaChannelRawData($data)
    {
        $img = imagecreatefromstring($data['raw']);
        $newimg = imagecreate($data['width'], $data['height']);
        imageinterlace($newimg, 0);
        // generate gray scale palette (0 -> 255)
        for ($col = 0; $col < 256; ++$col) {
            ImageColorAllocate($newimg, $col, $col, $col);
        }
        // extract alpha channel
        for ($xpx = 0; $xpx < $data['width']; ++$xpx) {
            for ($ypx = 0; $ypx < $data['height']; ++$ypx) {
                $colindex = imagecolorat($img, $xpx, $ypx);
                // get and correct gamma color
                $color = imagecolorsforindex($img, $colindex);
                // GD alpha is only 7 bit (0 -> 127); 2.2 is the gamma value
                $alpha = (pow(((127 - $color['alpha']) / 127), 2.2) * 255);
                imagesetpixel($newimg, $xpx, $ypx, $alpha);
            }
        }
        ob_start();
        imagepng($newimg, null, 9, PNG_ALL_FILTERS);
        $data['raw'] = ob_get_clean();
        $data['colspace'] = 'DeviceGray';
        $data['exturl'] = false;
        $data['recoded'] = true;
        return $this->getMetaData($data);
    }
}
