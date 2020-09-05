<?php
/**
 * Import.php
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

use \Com\Tecnick\Pdf\Font\ImportUtil;
use \Com\Tecnick\File\Byte;
use \Com\Tecnick\File\Dir;
use \Com\Tecnick\File\File;
use \Com\Tecnick\Unicode\Data\Encoding;
use \Com\Tecnick\Pdf\Font\UniToCid;
use \Com\Tecnick\Pdf\Font\Exception as FontException;

/**
 * Com\Tecnick\Pdf\Font\Import
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 */
class Import extends ImportUtil
{
    /**
     * Import the specified font and create output files.
     *
     * @param string $file           Font file to process
     * @param string $output_path    Output path for generated font files (must be writeable by the web server).
     *                               Leave null for default font folder.
     * @param string $type           Font type. Leave empty for autodetect mode. Valid values are:
     *                                   Core (AFM - Adobe Font Metrics)
     *                                   TrueTypeUnicode
     *                                   TrueType
     *                                   Type1
     *                                   CID0JP (CID-0 Japanese)
     *                                   CID0KR (CID-0 Korean)
     *                                   CID0CS (CID-0 Chinese Simplified)
     *                                   CID0CT (CID-0 Chinese Traditional)
     * @param string $encoding       Name of the encoding table to use. Leave empty for default mode.
     *                               Omit this parameter for TrueType Unicode and symbolic fonts
     *                               like Symbol or ZapfDingBats.
     * @param int    $flags          Unsigned 32-bit integer containing flags specifying various characteristics
     *                               of the font as described in "PDF32000:2008 - 9.8.2 Font Descriptor Flags":
     *                                   +1 for fixed width font
     *                                   +4 for symbol or +32 for non-symbol
     *                                   +64 for italic
     *                               Note: Fixed and Italic mode are generally autodetected, so you have to set it to
     *                                     32 = non-symbolic font (default) or 4 = symbolic font.
     * @param int    $platform_id    Platform ID for CMAP table to extract.
     *                               For a Unicode font for Windows this value should be 3, for Macintosh should be 1.
     * @param int    $encoding_id    Encoding ID for CMAP table to extract.
     *                               For a Unicode font for Windows this value should be 1, for Macintosh should be 0.
     *                               When Platform ID is 3, legal values for Encoding ID are:
     *                                0 = Symbol,
     *                                1 = Unicode,
     *                                2 = ShiftJIS,
     *                                3 = PRC,
     *                                4 = Big5,
     *                                5 = Wansung,
     *                                6 = Johab,
     *                                7 = Reserved,
     *                                8 = Reserved,
     *                                9 = Reserved,
     *                               10 = UCS-4.
     * @param bool   $linked         If true, links the font file to system font instead of copying the font data
     *                               (not transportable).
     *                               Note: this option do not work with Type1 fonts.
     *
     * @throws FontException in case of error
     */
    public function __construct(
        $file,
        $output_path = null,
        $type = null,
        $encoding = null,
        $flags = 32,
        $platform_id = 3,
        $encoding_id = 1,
        $linked = false
    ) {
        $this->fdt['input_file'] = $file;
        $this->fdt['file_name'] = $this->makeFontName($file);
        if (empty($this->fdt['file_name'])) {
            throw new FontException('the font name is empty');
        }
        $this->fdt['dir'] = $this->findOutputPath($output_path);
        $this->fdt['datafile'] = $this->fdt['dir'].$this->fdt['file_name'].'.json';
        if (@file_exists($this->fdt['datafile'])) {
            throw new FontException('this font has been already imported: '.$this->fdt['datafile']);
        }

        // get font data
        if (!is_file($file) || ($this->font = @file_get_contents($file)) === false) {
            throw new FontException('unable to read the input font file: '.$file);
        }
        $this->fbyte = new Byte($this->font);
        
        $this->fdt['settype'] = $type;
        $this->fdt['type'] = $this->getFontType($type);
        $this->fdt['isUnicode'] = (($this->fdt['type'] == 'TrueTypeUnicode') || ($this->fdt['type'] == 'cidfont0'));
        $this->fdt['Flags'] = intval($flags);
        $this->initFlags();
        $this->fdt['enc'] = $this->getEncodingTable($encoding);
        $this->fdt['diff'] = $this->getEncodingDiff();
        $this->fdt['originalsize'] = strlen($this->font);
        $this->fdt['ctg'] = $this->fdt['file_name'].'.ctg.z';
        $this->fdt['platform_id'] = intval($platform_id);
        $this->fdt['encoding_id'] = intval($encoding_id);
        $this->fdt['linked'] = (bool)$linked;

        if ($this->fdt['type'] == 'Core') {
            $processor = new \Com\Tecnick\Pdf\Font\Import\Core($this->font, $this->fdt);
        } elseif ($this->fdt['type'] == 'Type1') {
            $processor = new \Com\Tecnick\Pdf\Font\Import\TypeOne($this->font, $this->fdt);
        } else {
            $processor = new \Com\Tecnick\Pdf\Font\Import\TrueType($this->font, $this->fdt, $this->fbyte);
        }
        $this->fdt = $processor->getFontMetrics();
        $this->saveFontData();
    }

    /**
     * Get all the extracted font metrics
     *
     * @return string
     */
    public function getFontMetrics()
    {
        return $this->fdt;
    }

    /**
     * Get the output font name
     *
     * @return string
     */
    public function getFontName()
    {
        return $this->fdt['file_name'];
    }

    /**
     * Initialize font flags from font name
     *
     * @param int $flags
     *
     * return int
     */
    protected function initFlags()
    {
        $filename = strtolower(basename($this->fdt['input_file']));

        if ((strpos($filename, 'mono') !== false)
            || (strpos($filename, 'courier') !== false)
            || (strpos($filename, 'fixed') !== false)
        ) {
            $this->fdt['Flags'] |= 1;
        }

        if ((strpos($filename, 'symbol') !== false)
            || (strpos($filename, 'zapfdingbats') !== false)
        ) {
            $this->fdt['Flags'] |= 4;
        }

        if ((strpos($filename, 'italic') !== false)
            || (strpos($filename, 'oblique') !== false)
        ) {
            $this->fdt['Flags'] |= 64;
        }
    }

    /**
     * Save the eported metadata font file
     */
    protected function saveFontData()
    {
        $pfile = '{'
            .'"type":"'.$this->fdt['type'].'"'
            .',"name":"'.$this->fdt['name'].'"'
            .',"up":'.$this->fdt['underlinePosition']
            .',"ut":'.$this->fdt['underlineThickness']
            .',"dw":'.(($this->fdt['MissingWidth'] > 0) ? $this->fdt['MissingWidth'] : $this->fdt['AvgWidth'])
            .',"diff":"'.$this->fdt['diff'].'"'
            .',"platform_id":'.$this->fdt['platform_id']
            .',"encoding_id":'.$this->fdt['encoding_id'];

        if ($this->fdt['type'] == 'Core') {
            // Core
            $pfile .= ',"enc":""';
        } elseif ($this->fdt['type'] == 'Type1') {
            // Type 1
            $pfile .= ',"enc":"'.$this->fdt['enc'].'"'
                .',"file":"'.$this->fdt['file'].'"'
                .',"size1":'.$this->fdt['size1']
                .',"size2":'.$this->fdt['size2'];
        } else {
            $pfile .= ',"originalsize":'.$this->fdt['originalsize'];
            if ($this->fdt['type'] == 'cidfont0') {
                $pfile .= ','.UniToCid::$type[$this->fdt['settype']];
            } else {
                // TrueType
                $pfile .= ',"enc":"'.$this->fdt['enc'].'"'
                    .',"file":"'.$this->fdt['file'].'"'
                    .',"ctg":"'.$this->fdt['ctg'].'"';
                // create CIDToGIDMap
                $cidtogidmap = str_pad('', 131072, "\x00"); // (256 * 256 * 2) = 131072
                foreach ($this->fdt['ctgdata'] as $cid => $gid) {
                    $cidtogidmap = $this->updateCIDtoGIDmap($cidtogidmap, $cid, $gid);
                }
                // store compressed CIDToGIDMap
                $file = new File();
                $fpt = $file->fopenLocal($this->fdt['dir'].$this->fdt['ctg'], 'wb');
                fwrite($fpt, gzcompress($cidtogidmap));
                fclose($fpt);
            }
        }
        if ($this->fdt['isUnicode']) {
            $pfile .=',"isUnicode":true';
        } else {
            $pfile .=',"isUnicode":false';
        }

        $pfile .= ',"desc":{'
            .'"Flags":'.$this->fdt['Flags']
            .',"FontBBox":"['.$this->fdt['bbox'].']"'
            .',"ItalicAngle":'.$this->fdt['italicAngle']
            .',"Ascent":'.$this->fdt['Ascent']
            .',"Descent":'.$this->fdt['Descent']
            .',"Leading":'.$this->fdt['Leading']
            .',"CapHeight":'.$this->fdt['CapHeight']
            .',"XHeight":'.$this->fdt['XHeight']
            .',"StemV":'.$this->fdt['StemV']
            .',"StemH":'.$this->fdt['StemH']
            .',"AvgWidth":'.$this->fdt['AvgWidth']
            .',"MaxWidth":'.$this->fdt['MaxWidth']
            .',"MissingWidth":'.$this->fdt['MissingWidth']
            .'}';
        if (!empty($this->fdt['cbbox'])) {
            $pfile .= ',"cbbox":{'.substr($this->fdt['cbbox'], 1).'}';
        }
        $pfile .= ',"cw":{'.substr($this->fdt['cw'], 1).'}';
        $pfile .= '}'."\n";

        // store file
        $file = new File();
        $fpt = $file->fopenLocal($this->fdt['datafile'], 'wb');
        fwrite($fpt, $pfile);
        fclose($fpt);
    }
}
