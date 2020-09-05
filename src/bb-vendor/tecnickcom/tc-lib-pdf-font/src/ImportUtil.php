<?php
/**
 * ImportUtil.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 *
 * This file is part of tc-lib-pdf-font software library.
 */

namespace Com\Tecnick\Pdf\Font;

use \Com\Tecnick\File\Byte;
use \Com\Tecnick\File\Dir;
use \Com\Tecnick\File\File;
use \Com\Tecnick\Unicode\Data\Encoding;
use \Com\Tecnick\Pdf\Font\Import\TypeOne;
use \Com\Tecnick\Pdf\Font\Import\TrueType;
use \Com\Tecnick\Pdf\Font\UniToCid;
use \Com\Tecnick\Pdf\Font\Exception as FontException;

/**
 * Com\Tecnick\Pdf\Font\ImportUtil
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 */
abstract class ImportUtil
{
    /**
     * Content of the input font file
     *
     * @var string
     */
    protected $font = '';

    /**
     * Object used to read font bytes
     *
     * @var \Com\Tecnick\File\Byte
     */
    protected $fbyte;

    /**
     * Extracted font metrics
     *
     * @var array
     */
    protected $fdt = array();

    /**
     * Make the output font name
     *
     * @param string $font_file Input font file
     *
     * @return string
     */
    protected function makeFontName($font_file)
    {
        $font_path_parts = pathinfo($font_file);
        return str_replace(
            array('bold', 'oblique', 'italic', 'regular'),
            array('b', 'i', 'i', ''),
            preg_replace('/[^a-z0-9_]/', '', strtolower($font_path_parts['filename']))
        );
    }

    /**
     * Find the path where to store the processed font.
     *
     * @param string $output_path    Output path for generated font files (must be writeable by the web server).
     *                               Leave null for default font folder (K_PATH_FONTS).
     *
     * @return string
     */
    protected function findOutputPath($output_path = null)
    {
        if (!empty($output_path) && is_writable($output_path)) {
            return $output_path;
        }
        if (defined('K_PATH_FONTS') && is_writable(K_PATH_FONTS)) {
            return K_PATH_FONTS;
        }
        $dirobj = new Dir();
        $dir = $dirobj->findParentDir('fonts', __DIR__);
        if ($dir == '/') {
            $dir = sys_get_temp_dir();
        }
        if (substr($dir, -1) !== '/') {
            $dir .= '/';
        }
        return $dir;
    }

    /**
     * Get the font type
     *
     * @param string $font_type      Font type. Leave empty for autodetect mode.
     *
     * @return string
     */
    protected function getFontType($font_type)
    {
        // autodetect font type
        if (empty($font_type)) {
            if (substr($this->font, 0, 16) == 'StartFontMetrics') {
                // AFM type - we use this type only for the 14 Core fonts
                return 'Core';
            }
            if (substr($this->font, 0, 4) == 'OTTO') {
                throw new FontException('Unsupported font format: OpenType with CFF data');
            }
            if ($this->fbyte->getULong(0) == 0x10000) {
                return 'TrueTypeUnicode';
            }
            return 'Type1';
        }
        if (strpos($font_type, 'CID0') === 0) {
            return 'cidfont0';
        }
        if (in_array($font_type, array('Core', 'Type1', 'TrueType', 'TrueTypeUnicode'))) {
            return $font_type;
        }
        throw new FontException('unknown or unsupported font type: '.$font_type);
    }

    /**
     * Get the encoding table
     *
     * @param string $encoding  Name of the encoding table to use. Leave empty for default mode.
     *                          Omit this parameter for TrueType Unicode and symbolic fonts
     *                          like Symbol or ZapfDingBats.
     */
    protected function getEncodingTable($encoding)
    {
        if (empty($encoding) && ($this->fdt['type'] == 'Type1') && (($this->fdt['Flags'] & 4) == 0)) {
            return 'cp1252';
        }
        return preg_replace('/[^A-Za-z0-9_\-]/', '', $encoding);
    }

    /**
     * If required, get differences between the reference encoding (cp1252) and the current encoding
     *
     * @return string
     */
    protected function getEncodingDiff()
    {
        $diff = '';
        if ((($this->fdt['type'] == 'TrueType') || ($this->fdt['type'] == 'Type1'))
            && (!empty($this->fdt['enc'])
            && ($this->fdt['enc'] != 'cp1252')
            && isset(Encoding::$map[$this->fdt['enc']]))
        ) {
            // build differences from reference encoding
            $enc_ref = Encoding::$map['cp1252'];
            $enc_target = Encoding::$map[$this->fdt['enc']];
            $last = 0;
            for ($idx = 32; $idx <= 255; ++$idx) {
                if ($enc_target[$idx] != $enc_ref[$idx]) {
                    if ($idx != ($last + 1)) {
                        $diff .= $idx.' ';
                    }
                    $last = $idx;
                    $diff .= '/'.$enc_target[$idx].' ';
                }
            }
        }
        return $diff;
    }

    /**
     * Update the CIDToGIDMap string with a new value
     *
     * @param string $map CIDToGIDMap.
     * @param int    $cid CID value.
     * @param int    $gid GID value.
     *
     * @return string
     */
    protected function updateCIDtoGIDmap($map, $cid, $gid)
    {
        if (($cid >= 0) && ($cid <= 0xFFFF) && ($gid >= 0)) {
            if ($gid > 0xFFFF) {
                $gid -= 0x10000;
            }
            $map[($cid * 2)] = chr($gid >> 8);
            $map[(($cid * 2) + 1)] = chr($gid & 0xFF);
        }
        return $map;
    }
}
