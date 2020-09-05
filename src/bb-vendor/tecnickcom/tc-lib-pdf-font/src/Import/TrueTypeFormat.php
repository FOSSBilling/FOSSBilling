<?php
/**
 * TrueType.php
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

namespace Com\Tecnick\Pdf\Font\Import;

use \Com\Tecnick\File\File;
use \Com\Tecnick\Unicode\Data\Encoding;
use \Com\Tecnick\Pdf\Font\Exception as FontException;

/**
 * Com\Tecnick\Pdf\Font\Import\TrueTypeFormat
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 */
abstract class TrueTypeFormat
{
    /**
     * Array containing subset chars
     *
     * @var array
     */
    protected $subchars = array();

    /**
     * Array containing subset glyphs indexes of chars from cmap table
     *
     * @var array
     */
    protected $subglyphs = array();

    /**
     * Add CTG entry
     *
     * @param int $cid
     * @param int $gid
     */
    protected function addCtgItem($cid, $gid)
    {
        $this->fdt['ctgdata'][$cid] = $gid;
        if (isset($this->subchars[$cid])) {
            $this->subglyphs[$gid] = true;
        }
    }

    /**
     * Get CIDToGIDMap
     */
    protected function getCIDToGIDMap()
    {
        $valid_format = array(0,2,4,6,8,10,12,13,14);
        $this->fdt['ctgdata'] = array();
        foreach ($this->fdt['encodingTables'] as $enctable) {
            // get only specified Platform ID and Encoding ID
            if (($enctable['platformID'] == $this->fdt['platform_id'])
                && ($enctable['encodingID'] == $this->fdt['encoding_id'])
            ) {
                $this->offset = ($this->fdt['table']['cmap']['offset'] + $enctable['offset']);
                $format = $this->fbyte->getUShort($this->offset);
                $this->offset += 2;
                if (in_array($format, $valid_format)) {
                    $method = 'processFormat'.$format;
                    $this->$method();
                }
            }
        }
        if (!isset($this->fdt['ctgdata'][0])) {
            $this->fdt['ctgdata'][0] = 0;
        } elseif (($this->fdt['type'] == 'TrueTypeUnicode') && (count($this->fdt['ctgdata']) == 256)) {
            $this->fdt['type'] = 'TrueType';
        }
    }

    /**
     * Process Format 0: Byte encoding table
     */
    protected function processFormat0()
    {
        $this->offset += 4; // skip length and version/language
        for ($chr = 0; $chr < 256; ++$chr) {
            $gid = $this->fbyte->getByte($this->offset);
            $this->addCtgItem($chr, $gid);
            ++$this->offset;
        }
    }

    /**
     * Process Format 2: High-byte mapping through table
     */
    protected function processFormat2()
    {
        $this->offset += 4; // skip length and version/language
        $numSubHeaders = 0;
        for ($chr = 0; $chr < 256; ++$chr) {
            // Array that maps high bytes to subHeaders: value is subHeader index * 8.
            $subHeaderKeys[$chr] = ($this->fbyte->getUShort($this->offset) / 8);
            $this->offset += 2;
            if ($numSubHeaders < $subHeaderKeys[$chr]) {
                $numSubHeaders = $subHeaderKeys[$chr];
            }
        }
        // the number of subHeaders is equal to the max of subHeaderKeys + 1
        ++$numSubHeaders;
        // read subHeader structures
        $subHeaders = array();
        $numGlyphIndexArray = 0;
        for ($ish = 0; $ish < $numSubHeaders; ++$ish) {
            $subHeaders[$ish]['firstCode'] = $this->fbyte->getUShort($this->offset);
            $this->offset += 2;
            $subHeaders[$ish]['entryCount'] = $this->fbyte->getUShort($this->offset);
            $this->offset += 2;
            $subHeaders[$ish]['idDelta'] = $this->fbyte->getUShort($this->offset);
            $this->offset += 2;
            $subHeaders[$ish]['idRangeOffset'] = $this->fbyte->getUShort($this->offset);
            $this->offset += 2;
            $subHeaders[$ish]['idRangeOffset'] -= (2 + (($numSubHeaders - $ish - 1) * 8));
            $subHeaders[$ish]['idRangeOffset'] /= 2;
            $numGlyphIndexArray += $subHeaders[$ish]['entryCount'];
        }
        for ($gid = 0; $gid < $numGlyphIndexArray; ++$gid) {
            $glyphIndexArray[$gid] = $this->fbyte->getUShort($this->offset);
            $this->offset += 2;
        }
        for ($chr = 0; $chr < 256; ++$chr) {
            $shk = $subHeaderKeys[$chr];
            if ($shk == 0) {
                // one byte code
                $cdx = $chr;
                $gid = $glyphIndexArray[0];
                $this->addCtgItem($cdx, $gid);
            } else {
                // two bytes code
                $start_byte = $subHeaders[$shk]['firstCode'];
                $end_byte = $start_byte + $subHeaders[$shk]['entryCount'];
                for ($jdx = $start_byte; $jdx < $end_byte; ++$jdx) {
                    // combine high and low bytes
                    $cdx = (($chr << 8) + $jdx);
                    $idRangeOffset = ($subHeaders[$shk]['idRangeOffset'] + $jdx - $subHeaders[$shk]['firstCode']);
                    $gid = max(0, (($glyphIndexArray[$idRangeOffset] + $subHeaders[$shk]['idDelta']) % 65536));
                    $this->addCtgItem($cdx, $gid);
                }
            }
        }
    }

    /**
     * Process Format 4: Segment mapping to delta values
     */
    protected function processFormat4()
    {
        $length = $this->fbyte->getUShort($this->offset);
        $this->offset += 2;
        $this->offset += 2; // skip version/language
        $segCount = floor($this->fbyte->getUShort($this->offset) / 2);
        $this->offset += 2;
        $this->offset += 6; // skip searchRange, entrySelector, rangeShift
        $endCount = array(); // array of end character codes for each segment
        for ($kdx = 0; $kdx < $segCount; ++$kdx) {
            $endCount[$kdx] = $this->fbyte->getUShort($this->offset);
            $this->offset += 2;
        }
        $this->offset += 2; // skip reservedPad
        $startCount = array(); // array of start character codes for each segment
        for ($kdx = 0; $kdx < $segCount; ++$kdx) {
            $startCount[$kdx] = $this->fbyte->getUShort($this->offset);
            $this->offset += 2;
        }
        $idDelta = array(); // delta for all character codes in segment
        for ($kdx = 0; $kdx < $segCount; ++$kdx) {
            $idDelta[$kdx] = $this->fbyte->getUShort($this->offset);
            $this->offset += 2;
        }
        $idRangeOffset = array(); // Offsets into glyphIdArray or 0
        for ($kdx = 0; $kdx < $segCount; ++$kdx) {
            $idRangeOffset[$kdx] = $this->fbyte->getUShort($this->offset);
            $this->offset += 2;
        }
        $gidlen = (floor($length / 2) - 8 - (4 * $segCount));
        $glyphIdArray = array(); // glyph index array
        for ($kdx = 0; $kdx < $gidlen; ++$kdx) {
            $glyphIdArray[$kdx] = $this->fbyte->getUShort($this->offset);
            $this->offset += 2;
        }
        for ($kdx = 0; $kdx < $segCount; ++$kdx) {
            for ($chr = $startCount[$kdx]; $chr <= $endCount[$kdx]; ++$chr) {
                if ($idRangeOffset[$kdx] == 0) {
                    $gid = max(0, (($idDelta[$kdx] + $chr) % 65536));
                } else {
                    $gid = (floor($idRangeOffset[$kdx] / 2) + ($chr - $startCount[$kdx]) - ($segCount - $kdx));
                    $gid = max(0, (($glyphIdArray[$gid] + $idDelta[$kdx]) % 65536));
                }
                $this->addCtgItem($chr, $gid);
            }
        }
    }

    /**
     * Process Format 6: Trimmed table mapping
     */
    protected function processFormat6()
    {
        $this->offset += 4; // skip length and version/language
        $firstCode = $this->fbyte->getUShort($this->offset);
        $this->offset += 2;
        $entryCount = $this->fbyte->getUShort($this->offset);
        $this->offset += 2;
        for ($kdx = 0; $kdx < $entryCount; ++$kdx) {
            $chr = ($kdx + $firstCode);
            $gid = $this->fbyte->getUShort($this->offset);
            $this->offset += 2;
            $this->addCtgItem($chr, $gid);
        }
    }

    /**
     * Process Format 8: Mixed 16-bit and 32-bit coverage
     */
    protected function processFormat8()
    {
        $this->offset += 10; // skip reserved, length and version/language
        for ($kdx = 0; $kdx < 8192; ++$kdx) {
            $is32[$kdx] = $this->fbyte->getByte($this->offset);
            ++$this->offset;
        }
        $nGroups = $this->fbyte->getULong($this->offset);
        $this->offset += 4;
        for ($idx = 0; $idx < $nGroups; ++$idx) {
            $startCharCode = $this->fbyte->getULong($this->offset);
            $this->offset += 4;
            $endCharCode = $this->fbyte->getULong($this->offset);
            $this->offset += 4;
            $startGlyphID = $this->fbyte->getULong($this->offset);
            $this->offset += 4;
            for ($cpw = $startCharCode; $cpw <= $endCharCode; ++$cpw) {
                $is32idx = floor($cpw / 8);
                if ((isset($is32[$is32idx])) && (($is32[$is32idx] & (1 << (7 - ($cpw % 8)))) == 0)) {
                    $chr = $cpw;
                } else {
                    // 32 bit format
                    // convert to decimal (http://www.unicode.org/faq//utf_bom.html#utf16-4)
                    //LEAD_OFFSET = (0xD800 - (0x10000 >> 10)) = 55232
                    //SURROGATE_OFFSET = (0x10000 - (0xD800 << 10) - 0xDC00) = -56613888
                    $chr = (((55232 + ($cpw >> 10)) << 10) + (0xDC00 + ($cpw & 0x3FF)) - 56613888);
                }
                $this->addCtgItem($chr, $startGlyphID);
                $this->fdt['ctgdata'][$chr] = 0; // overwrite
                ++$startGlyphID;
            }
        }
    }

    /**
     * Process Format 10: Trimmed array
     */
    protected function processFormat10()
    {
        $this->offset += 10; // skip reserved, length and version/language
        $startCharCode = $this->fbyte->getULong($this->offset);
        $this->offset += 4;
        $numChars = $this->fbyte->getULong($this->offset);
        $this->offset += 4;
        for ($kdx = 0; $kdx < $numChars; ++$kdx) {
            $chr = ($kdx + $startCharCode);
            $gid = $this->fbyte->getUShort($this->offset);
            $this->addCtgItem($chr, $gid);
            $this->offset += 2;
        }
    }

    /**
     * Process Format 12: Segmented coverage
     */
    protected function processFormat12()
    {
        $this->offset += 10; // skip length and version/language
        $nGroups = $this->fbyte->getULong($this->offset);
        $this->offset += 4;
        for ($kdx = 0; $kdx < $nGroups; ++$kdx) {
            $startCharCode = $this->fbyte->getULong($this->offset);
            $this->offset += 4;
            $endCharCode = $this->fbyte->getULong($this->offset);
            $this->offset += 4;
            $startGlyphCode = $this->fbyte->getULong($this->offset);
            $this->offset += 4;
            for ($chr = $startCharCode; $chr <= $endCharCode; ++$chr) {
                $this->addCtgItem($chr, $startGlyphCode);
                ++$startGlyphCode;
            }
        }
    }

    /**
     * Process Format 13: Many-to-one range mappings
     * @TODO: TO BE IMPLEMENTED
     */
    protected function processFormat13()
    {
        return;
    }

    /**
     * Process Format 14: Unicode Variation Sequences
     * @TODO: TO BE IMPLEMENTED
     */
    protected function processFormat14()
    {
        return;
    }
}
