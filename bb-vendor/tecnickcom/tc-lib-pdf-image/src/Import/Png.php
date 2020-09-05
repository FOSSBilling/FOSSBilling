<?php
/**
 * Png.php
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

namespace Com\Tecnick\Pdf\Image\Import;

use \Com\Tecnick\File\Byte;
use \Com\Tecnick\Pdf\Image\Exception as ImageException;

/**
 * Com\Tecnick\Pdf\Image\Import\Png
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfImage
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-image
 */
class Png implements ImageImportInterface
{
    /**
     * Extract data from a PNG image
     *
     * @param array $data Image raw data
     *
     * @return array Image raw data array
     */
    public function getData($data)
    {
        $data['filter'] = 'FlateDecode';
        $byte = new Byte($data['raw']);

        $offset = 0;
        // check signature
        if (substr($data['raw'], $offset, 8) != chr(137).'PNG'.chr(13).chr(10).chr(26).chr(10)) {
            // @codeCoverageIgnoreStart
            throw new ImageException('Not a PNG image');
            // @codeCoverageIgnoreEnd
        }
        $offset += 8;
        $offset += 4;

        $data = $this->getIhdrChunk($data, $offset);

        // check compression, filter and interlacing settings
        if (($byte->getByte($offset++) != 0)
            || ($byte->getByte($offset++) != 0)
            || ($byte->getByte($offset++) != 0)
        ) {
            if (!empty($data['recoded'])) {
                // this image has been already re-encoded
                // @codeCoverageIgnoreStart
                throw new ImageException('Unsupported feature');
                // @codeCoverageIgnoreEnd
            }
            // re-encode PNG
            $data['recode'] = true;
            return $data;
        }

        if (strpos($data['colspace'], '+Alpha') !== false) {
            // alpha channel: split images (plain + alpha)
            $data['splitalpha'] = true;
            return $data;
        }

        $data['parms'] = '/DecodeParms <<'
            .' /Predictor 15'
            .' /Colors '.$data['channels']
            .' /BitsPerComponent '.$data['bits']
            .' /Columns '.$data['width']
            .' >>';

        $offset += 4;
        return $this->getChunks($data, $offset);
    }

    /**
     * Extract the IHDR chunk data
     *
     * The header chunk (IHDR) contains basic information about the image data and must appear as the first chunk,
     * and there must only be one header chunk in a PNG data stream.
     * @param array  $data   Image raw data
     * @param int    $offset Current byte offset
     *
     * @return array Image raw data array
     */
    protected function getIhdrChunk($data, &$offset)
    {
        $byte = new Byte($data['raw']);
        if (substr($data['raw'], $offset, 4) != 'IHDR') {
            // @codeCoverageIgnoreStart
            throw new ImageException('Invalid PNG image');
            // @codeCoverageIgnoreEnd
        }
        $offset += 4;
        $data['width'] = $byte->getULong($offset);
        $offset += 4;
        $data['height'] = $byte->getULong($offset);
        $offset += 4;
        $data['bits'] = $byte->getByte($offset);
        $offset += 1;
        $chc = $byte->getByte($offset); // channels code
        $offset += 1;
        $data['channels'] = (($chc == 2) ? 3 : 1);
        $chcmap = array(
            0 => 'DeviceGray',
            2 => 'DeviceRGB',
            3 => 'Indexed',
            4 => 'DeviceGray+Alpha',
            6 => 'DeviceRGB+Alpha',
        );
        if (isset($chcmap[$chc])) {
            $data['colspace'] = $chcmap[$chc];
        } else {
            // @codeCoverageIgnoreStart
            throw new ImageException('Unknown color mode');
            // @codeCoverageIgnoreEnd
        }
        return $data;
    }

    /**
     * Extract chunks data from a PNG image
     *
     * @param array  $data   Image raw data
     * @param int    $offset Current byte offset
     *
     * @return array Image raw data array
     */
    protected function getChunks($data, $offset)
    {
        $byte = new Byte($data['raw']);
        while (($len = $byte->getULong($offset)) >= 0) {
            $offset += 4;
            $type = substr($data['raw'], $offset, 4);
            $offset += 4;
            if ($type == 'PLTE') {
                $data = $this->getPlteChunk($data, $offset, $len);
            } elseif ($type == 'tRNS') {
                $data = $this->getTrnsChunk($data, $offset, $len);
            } elseif ($type == 'IDAT') {
                $data = $this->getIdatChunk($data, $offset, $len);
            } elseif ($type == 'iCCP') {
                $data = $this->getIccpChunk($byte, $data, $offset, $len);
            } elseif ($type == 'IEND') {
                // The image trailer chunk (IEND) must be the final chunk
                // and marks the end of the PNG file or data stream.
                break;
            } else {
                $offset += $len;
                $offset += 4;
            }
        }
        if (($data['colspace'] == 'Indexed') && (empty($data['pal']))) {
            // @codeCoverageIgnoreStart
            throw new ImageException('The color palette is missing');
            // @codeCoverageIgnoreEnd
        }
        return $data;
    }

    /**
     * Extract the PLTE chunk data
     *
     * The palette chunk (PLTE) stores the colormap data associated with the image data.
     * This chunk is presentonly if the image data uses a color palette and must appear before the image data chunk.
     *
     * @param array  $data   Image raw data
     * @param int    $offset Current byte offset
     * @param int    $len    NUmber of bytes in this chunk
     *
     * @return array Image raw data array
     */
    protected function getPlteChunk($data, &$offset, $len)
    {
        $data['pal'] = substr($data['raw'], $offset, $len);
        $offset += $len;
        $offset += 4;
        return $data;
    }

    /**
     * Extract the tRNS chunk data
     *
     * @param array  $data   Image raw data
     * @param int    $offset Current byte offset
     * @param int    $len    NUmber of bytes in this chunk
     *
     * @return array Image raw data array
     */
    protected function getTrnsChunk($data, &$offset, $len)
    {
        // read transparency info
        $trns = substr($data['raw'], $offset, $len);
        $offset += $len;
        if ($data['colspace'] == 'DeviceGray') {
            // DeviceGray
            $data['trns'][] = ord($trns[1]);
        } elseif ($data['colspace'] == 'DeviceRGB') {
            // DeviceRGB
            $data['trns'][] = ord($trns[1]);
            $data['trns'][] = ord($trns[3]);
            $data['trns'][] = ord($trns[5]);
        } else {
            // Indexed
            $data['trns'] = array_map('ord', str_split($trns));
        }
        $offset += 4;
        return $data;
    }

    /**
     * Extract the IDAT chunk data
     *
     * The image data chunk (IDAT) stores the actual image data,
     * and multiple image data chunks may occur in a data stream and must be stored in contiguous order.
     *
     * @param array  $data   Image raw data
     * @param int    $offset Current byte offset
     * @param int    $len    NUmber of bytes in this chunk
     *
     * @return array Image raw data array
     */
    protected function getIdatChunk($data, &$offset, $len)
    {
        $data['data'] .= substr($data['raw'], $offset, $len);
        $offset += $len;
        $offset += 4;
        return $data;
    }

    /**
     * Extract the iCCP chunk data
     *
     * @param Byte   $byte   Byte class object
     * @param array  $data   Image raw data
     * @param int    $offset Current byte offset
     * @param int    $len    NUmber of bytes in this chunk
     *
     * @return array Image raw data array
     */
    protected function getIccpChunk($byte, $data, &$offset, $len)
    {
        // skip profile name
        $pos = 0;
        while (($byte->getByte($offset++) != 0) && ($pos < 80)) {
            ++$pos;
        }
        // get compression method
        if ($byte->getByte($offset++) != 0) {
            // @codeCoverageIgnoreStart
            throw new ImageException('Unknown filter method');
            // @codeCoverageIgnoreEnd
        }
        // read ICC Color Profile
        $len -= ($pos + 2);
        $data['icc'] = gzuncompress(substr($data['raw'], $offset, $len));
        $offset += $len;
        $offset += 4;
        return $data;
    }
}
