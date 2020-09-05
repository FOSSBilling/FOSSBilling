<?php
/**
 * TrueType.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 *
 * This file is part of tc-lib-pdf-font software library.
 */

namespace Com\Tecnick\Pdf\Font\Import;

use \Com\Tecnick\File\File;
use \Com\Tecnick\Unicode\Data\Encoding;
use \Com\Tecnick\Pdf\Font\Exception as FontException;

/**
 * Com\Tecnick\Pdf\Font\Import\TrueType
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 */
class TrueType extends \Com\Tecnick\Pdf\Font\Import\TrueTypeFormat
{
    /**
     * Content of the input font file
     *
     * @var string
     */
    protected $font = '';

    /**
     * Extracted font metrics
     *
     * @var array
     */
    protected $fdt = array();
    
    /**
     * Object used to read font bytes
     *
     * @var \Com\Tecnick\File\Byte
     */
    protected $fbyte;
    
    /**
     * Pointer position on the original font data
     *
     * @var int
     */
    protected $offset = 0;

    /**
     * Process TrueType font
     *
     * @param string $font     Content of the input font file
     * @param array  $fdt      Extracted font metrics
     * @param Byte   $fbyte    Object used to read font bytes
     * @param array  $subchars Array containing subset chars
     *
     * @throws FontException in case of error
     */
    public function __construct($font, $fdt, $fbyte, $subchars = array())
    {
        $this->font = $font;
        $this->fdt = $fdt;
        $this->fbyte = $fbyte;
        ksort($subchars);
        $this->subchars = $subchars;
        $this->subglyphs = array(0 => true);
        $this->process();
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
     * Get glyphs in the subset
     *
     * @return array
     */
    public function getSubGlyphs()
    {
        return $this->subglyphs;
    }

    /**
     * Process TrueType font
     */
    protected function process()
    {
        $this->isValidType();
        $this->setFontFile();
        $this->getTables();
        $this->checkMagickNumber();
        $this->offset += 2; // skip flags
        $this->getBbox();
        $this->getIndexToLoc();
        $this->getEncodingTables();
        $this->getOS2Metrics();
        $this->getFontName();
        $this->getPostData();
        $this->getHheaData();
        $this->getMaxpData();
        $this->getCIDToGIDMap();
        $this->getHeights();
        $this->getWidths();
    }

    /**
     * Check if the font is a valid type
     *
     * @throws FontException if the font is invalid
     */
    protected function isValidType()
    {
        if ($this->fbyte->getULong($this->offset) != 0x10000) {
            throw new FontException('sfnt version must be 0x00010000 for TrueType version 1.0.');
        }
        $this->offset += 4;
    }

    /**
     * Copy or link the original font file
     */
    protected function setFontFile()
    {
        if (!empty($this->fdt['desc'])) {
            // subsetting mode
            $this->fdt['Flags'] = $this->fdt['desc']['Flags'];
            return;
        }
        if ($this->fdt['type'] != 'cidfont0') {
            if ($this->fdt['linked']) {
                // creates a symbolic link to the existing font
                symlink($this->fdt['input_file'], $this->fdt['dir'].$this->fdt['file_name']);
            } else {
                // store compressed font
                $this->fdt['file'] = $this->fdt['file_name'].'.z';
                $file = new File();
                $fpt = $file->fopenLocal($this->fdt['dir'].$this->fdt['file'], 'wb');
                fwrite($fpt, gzcompress($this->font));
                fclose($fpt);
            }
        }
    }

    /**
     * Get the font tables
     *
     */
    protected function getTables()
    {
        // get number of tables
        $numTables = $this->fbyte->getUShort($this->offset);
        $this->offset += 2;
        // skip searchRange, entrySelector and rangeShift
        $this->offset += 6;
        // tables array
        $this->fdt['table'] = array();
        // ---------- get tables ----------
        for ($idx = 0; $idx < $numTables; ++$idx) {
            // get table info
            $tag = substr($this->font, $this->offset, 4);
            $this->offset += 4;
            $this->fdt['table'][$tag] = array();
            $this->fdt['table'][$tag]['checkSum'] = $this->fbyte->getULong($this->offset);
            $this->offset += 4;
            $this->fdt['table'][$tag]['offset'] = $this->fbyte->getULong($this->offset);
            $this->offset += 4;
            $this->fdt['table'][$tag]['length'] = $this->fbyte->getULong($this->offset);
            $this->offset += 4;
        }
    }

    /**
     * Check if the font is a valid type
     *
     * @throws FontException if the font is invalid
     */
    protected function checkMagickNumber()
    {
        $this->offset = ($this->fdt['table']['head']['offset'] + 12);
        if ($this->fbyte->getULong($this->offset) != 0x5F0F3CF5) {
            // magicNumber must be 0x5F0F3CF5
            throw new FontException('magicNumber must be 0x5F0F3CF5');
        }
        $this->offset += 4;
    }

    /**
     * Get BBox, units and flags
     */
    protected function getBbox()
    {
        // get FUnits
        $this->fdt['unitsPerEm'] = $this->fbyte->getUShort($this->offset);
        $this->offset += 2;
        // units ratio constant
        $this->fdt['urk'] = (1000 / $this->fdt['unitsPerEm']);
        $this->offset += 16; // skip created, modified
        $xMin = round($this->fbyte->getFWord($this->offset) * $this->fdt['urk']);
        $this->offset += 2;
        $yMin = round($this->fbyte->getFWord($this->offset) * $this->fdt['urk']);
        $this->offset += 2;
        $xMax = round($this->fbyte->getFWord($this->offset) * $this->fdt['urk']);
        $this->offset += 2;
        $yMax = round($this->fbyte->getFWord($this->offset) * $this->fdt['urk']);
        $this->offset += 2;
        $this->fdt['bbox'] = $xMin.' '.$yMin.' '.$xMax.' '.$yMax;
        $macStyle = $this->fbyte->getUShort($this->offset);
        $this->offset += 2;
        // PDF font flags
        if (($macStyle & 2) == 2) {
            // italic flag
            $this->fdt['Flags'] |= 64;
        }
    }

    /**
     * Get index to loc map
     */
    protected function getIndexToLoc()
    {
        // get offset mode (indexToLocFormat : 0 = short, 1 = long)
        $this->offset = ($this->fdt['table']['head']['offset'] + 50);
        $this->fdt['short_offset'] = ($this->fbyte->getShort($this->offset) == 0);
        $this->offset += 2;
        // get the offsets to the locations of the glyphs in the font, relative to the beginning of the glyphData table
        $this->fdt['indexToLoc'] = array();
        $this->offset = $this->fdt['table']['loca']['offset'];
        if ($this->fdt['short_offset']) {
            // short version
            $this->fdt['tot_num_glyphs'] = floor($this->fdt['table']['loca']['length'] / 2); // numGlyphs + 1
            for ($idx = 0; $idx < $this->fdt['tot_num_glyphs']; ++$idx) {
                $this->fdt['indexToLoc'][$idx] = $this->fbyte->getUShort($this->offset) * 2;
                if (isset($this->fdt['indexToLoc'][($idx - 1)])
                    && ($this->fdt['indexToLoc'][$idx] == $this->fdt['indexToLoc'][($idx - 1)])
                ) {
                    // the last glyph didn't have an outline
                    unset($this->fdt['indexToLoc'][($idx - 1)]);
                }
                $this->offset += 2;
            }
        } else {
            // long version
            $this->fdt['tot_num_glyphs'] = floor($this->fdt['table']['loca']['length'] / 4); // numGlyphs + 1
            for ($idx = 0; $idx < $this->fdt['tot_num_glyphs']; ++$idx) {
                $this->fdt['indexToLoc'][$idx] = $this->fbyte->getULong($this->offset);
                if (isset($this->fdt['indexToLoc'][($idx - 1)])
                    && ($this->fdt['indexToLoc'][$idx] == $this->fdt['indexToLoc'][($idx - 1)])
                ) {
                    // the last glyph didn't have an outline
                    unset($this->fdt['indexToLoc'][($idx - 1)]);
                }
                $this->offset += 4;
            }
        }
    }

    /**
     * Get encoding tables
     */
    protected function getEncodingTables()
    {
        // get glyphs indexes of chars from cmap table
        $this->offset = $this->fdt['table']['cmap']['offset'] + 2;
        $numEncodingTables = $this->fbyte->getUShort($this->offset);
        $this->offset += 2;
        $this->fdt['encodingTables'] = array();
        for ($idx = 0; $idx < $numEncodingTables; ++$idx) {
            $this->fdt['encodingTables'][$idx]['platformID'] = $this->fbyte->getUShort($this->offset);
            $this->offset += 2;
            $this->fdt['encodingTables'][$idx]['encodingID'] = $this->fbyte->getUShort($this->offset);
            $this->offset += 2;
            $this->fdt['encodingTables'][$idx]['offset'] = $this->fbyte->getULong($this->offset);
            $this->offset += 4;
        }
    }

    /**
     * Get encoding tables
     */
    protected function getOS2Metrics()
    {
        $this->offset = $this->fdt['table']['OS/2']['offset'];
        $this->offset += 2; // skip version
        // xAvgCharWidth
        $this->fdt['AvgWidth'] = round($this->fbyte->getFWord($this->offset) * $this->fdt['urk']);
        $this->offset += 2;
        // usWeightClass
        $usWeightClass = round($this->fbyte->getUFWord($this->offset) * $this->fdt['urk']);
        // estimate StemV and StemH (400 = usWeightClass for Normal - Regular font)
        $this->fdt['StemV'] = round((70 * $usWeightClass) / 400);
        $this->fdt['StemH'] = round((30 * $usWeightClass) / 400);
        $this->offset += 2;
        $this->offset += 2; // usWidthClass
        $fsType = $this->fbyte->getShort($this->offset);
        $this->offset += 2;
        if ($fsType == 2) {
            throw new FontException(
                'This Font cannot be modified, embedded or exchanged in any manner'
                .' without first obtaining permission of the legal owner.'
            );
        }
    }

    /**
     * Get font name
     */
    protected function getFontName()
    {
        $this->fdt['name'] = '';
        $this->offset = $this->fdt['table']['name']['offset'];
        $this->offset += 2; // skip Format selector (=0).
        // Number of NameRecords that follow n.
        $numNameRecords = $this->fbyte->getUShort($this->offset);
        $this->offset += 2;
        // Offset to start of string storage (from start of table).
        $stringStorageOffset = $this->fbyte->getUShort($this->offset);
        $this->offset += 2;
        for ($idx = 0; $idx < $numNameRecords; ++$idx) {
            $this->offset += 6; // skip Platform ID, Platform-specific encoding ID, Language ID.
            // Name ID.
            $nameID = $this->fbyte->getUShort($this->offset);
            $this->offset += 2;
            if ($nameID == 6) {
                // String length (in bytes).
                $stringLength = $this->fbyte->getUShort($this->offset);
                $this->offset += 2;
                // String offset from start of storage area (in bytes).
                $stringOffset = $this->fbyte->getUShort($this->offset);
                $this->offset += 2;
                $this->offset = ($this->fdt['table']['name']['offset'] + $stringStorageOffset + $stringOffset);
                $this->fdt['name'] = substr($this->font, $this->offset, $stringLength);
                $this->fdt['name'] = preg_replace('/[^a-zA-Z0-9_\-]/', '', $this->fdt['name']);
                break;
            } else {
                $this->offset += 4; // skip String length, String offset
            }
        }
    }

    /**
     * Get post data
     */
    protected function getPostData()
    {
        $this->offset = $this->fdt['table']['post']['offset'];
        $this->offset += 4; // skip Format Type
        $this->fdt['italicAngle'] = $this->fbyte->getFixed($this->offset);
        $this->offset += 4;
        $this->fdt['underlinePosition'] = round($this->fbyte->getFWord($this->offset) * $this->fdt['urk']);
        $this->offset += 2;
        $this->fdt['underlineThickness'] = round($this->fbyte->getFWord($this->offset) * $this->fdt['urk']);
        $this->offset += 2;
        $isFixedPitch = (($this->fbyte->getULong($this->offset) == 0) ? false : true);
        $this->offset += 2;
        if ($isFixedPitch) {
            $this->fdt['Flags'] |= 1;
        }
    }

    /**
     * Get hhea data
     */
    protected function getHheaData()
    {
        // ---------- get hhea data ----------
        $this->offset = $this->fdt['table']['hhea']['offset'];
        $this->offset += 4; // skip Table version number
        // Ascender
        $this->fdt['Ascent'] = round($this->fbyte->getFWord($this->offset) * $this->fdt['urk']);
        $this->offset += 2;
        // Descender
        $this->fdt['Descent'] = round($this->fbyte->getFWord($this->offset) * $this->fdt['urk']);
        $this->offset += 2;
        // LineGap
        $this->fdt['Leading'] = round($this->fbyte->getFWord($this->offset) * $this->fdt['urk']);
        $this->offset += 2;
        // advanceWidthMax
        $this->fdt['MaxWidth'] = round($this->fbyte->getUFWord($this->offset) * $this->fdt['urk']);
        $this->offset += 2;
        $this->offset += 22; // skip some values
        // get the number of hMetric entries in hmtx table
        $this->fdt['numHMetrics'] = $this->fbyte->getUShort($this->offset);
    }

    /**
     * Get maxp data
     */
    protected function getMaxpData()
    {
        $this->offset = $this->fdt['table']['maxp']['offset'];
        $this->offset += 4; // skip Table version number
        // get the the number of glyphs in the font.
        $this->fdt['numGlyphs'] = $this->fbyte->getUShort($this->offset);
    }

    /**
     * Get font heights
     */
    protected function getHeights()
    {
        // get xHeight (height of x)
        $this->fdt['XHeight'] = ($this->fdt['Ascent'] + $this->fdt['Descent']);
        if (!empty($this->fdt['ctgdata'][120])) {
            $this->offset = ($this->fdt['table']['glyf']['offset']
                + $this->fdt['indexToLoc'][$this->fdt['ctgdata'][120]]
                + 4
            );
            $yMin = $this->fbyte->getFWord($this->offset);
            $this->offset += 4;
            $yMax = $this->fbyte->getFWord($this->offset);
            $this->offset += 2;
            $this->fdt['XHeight'] = round(($yMax - $yMin) * $this->fdt['urk']);
        }
    
        // get CapHeight (height of H)
        $this->fdt['CapHeight'] = $this->fdt['Ascent'];
        if (!empty($this->fdt['ctgdata'][72])) {
            $this->offset = ($this->fdt['table']['glyf']['offset']
                + $this->fdt['indexToLoc'][$this->fdt['ctgdata'][72]]
                + 4
            );
            $yMin = $this->fbyte->getFWord($this->offset);
            $this->offset += 4;
            $yMax = $this->fbyte->getFWord($this->offset);
            $this->offset += 2;
            $this->fdt['CapHeight'] = round(($yMax - $yMin) * $this->fdt['urk']);
        }
    }

    /**
     * Get font widths
     */
    protected function getWidths()
    {
        // create widths array
        $chw = array();
        $this->offset = $this->fdt['table']['hmtx']['offset'];
        for ($i = 0; $i < $this->fdt['numHMetrics']; ++$i) {
            $chw[$i] = round($this->fbyte->getUFWord($this->offset) * $this->fdt['urk']);
            $this->offset += 4; // skip lsb
        }
        if ($this->fdt['numHMetrics'] < $this->fdt['numGlyphs']) {
            // fill missing widths with the last value
            $chw = array_pad($chw, $this->fdt['numGlyphs'], $chw[($this->fdt['numHMetrics'] - 1)]);
        }
        $this->fdt['MissingWidth'] = $chw[0];
        $this->fdt['cw'] = '';
        $this->fdt['cbbox'] = '';
        for ($cid = 0; $cid <= 65535; ++$cid) {
            if (isset($this->fdt['ctgdata'][$cid])) {
                if (($cid >= 0) && isset($chw[$this->fdt['ctgdata'][$cid]])) {
                    $this->fdt['cw'] .= ',"'.$cid.'":'.$chw[$this->fdt['ctgdata'][$cid]];
                }
                if (isset($this->fdt['indexToLoc'][$this->fdt['ctgdata'][$cid]])) {
                    $this->offset = ($this->fdt['table']['glyf']['offset']
                        + $this->fdt['indexToLoc'][$this->fdt['ctgdata'][$cid]]
                    );
                    $xMin = round($this->fbyte->getFWord($this->offset + 2) * $this->fdt['urk']);
                    $yMin = round($this->fbyte->getFWord($this->offset + 4) * $this->fdt['urk']);
                    $xMax = round($this->fbyte->getFWord($this->offset + 6) * $this->fdt['urk']);
                    $yMax = round($this->fbyte->getFWord($this->offset + 8) * $this->fdt['urk']);
                    $this->fdt['cbbox'] .= ',"'.$cid.'":['.$xMin.','.$yMin.','.$xMax.','.$yMax.']';
                }
            }
        }
    }
}
