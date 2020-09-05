<?php
/**
 * Subset.php
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
use \Com\Tecnick\Pdf\Font\Import\TrueType;
use \Com\Tecnick\Pdf\Font\Exception as FontException;

/**
 * Com\Tecnick\Pdf\Font\Subset
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 */
class Subset
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
     * Array containing subset glyphs indexes of chars from cmap table
     *
     * @var array
     */
    protected $subglyphs = array();

    /**
     * Subset font
     *
     * @var string
     */
    protected $subfont = '';

    /**
     * Process TrueType font
     *
     * @param string $font     Content of the input font file
     * @param array  $fdt      Extracted font metrics
     * @param array  $subchars Array containing subset chars
     *
     * @throws FontException in case of error
     */
    public function __construct($font, $fdt, $subchars = array())
    {
        $this->fbyte = new Byte($font);
        $processor = new TrueType($font, $fdt, $this->fbyte, $subchars);
        $this->fdt = $processor->getFontMetrics();
        $this->subglyphs = $processor->getSubGlyphs();
        $this->addCompositeGlyphs();
        $this->addProcessedTables();
        $this->removeUnusedTables();
        $this->buildSubsetFont();
    }

    /**
     * Get all the extracted font metrics
     *
     * @return string
     */
    public function getSubsetFont()
    {
        return $this->subfont;
    }

    /**
     * Returs the checksum of a TTF table.
     *
     * @param string $table  Table to check
     * @param int    $length Length of table in bytes
     *
     * @return int checksum
     */
    protected function getTableChecksum($table, $length)
    {
        $sum = 0;
        $tlen = ($length / 4);
        $offset = 0;
        for ($idx = 0; $idx < $tlen; ++$idx) {
            $val = unpack('Ni', substr($table, $offset, 4));
            $sum += $val['i'];
            $offset += 4;
        }
        $sum = unpack('Ni', pack('N', $sum));
        return $sum['i'];
    }

    /**
     * Add composite glyphs
     */
    protected function addCompositeGlyphs()
    {
        $new_sga = $this->subglyphs;
        while (!empty($new_sga)) {
            $sga = array_keys($new_sga);
            $new_sga = array();
            foreach ($sga as $key) {
                $new_sga = $this->findCompositeGlyphs($new_sga, $key);
            }
            $this->subglyphs = array_merge($this->subglyphs, $new_sga);
        }
        // sort glyphs by key (and remove duplicates)
        ksort($this->subglyphs);
    }

    /**
     * Add composite glyphs
     *
     * @param array $new_sga
     * @param int   $key
     *
     * @return array
     */
    protected function findCompositeGlyphs($new_sga, $key)
    {
        if (isset($this->fdt['indexToLoc'][$key])) {
            $this->offset = ($this->fdt['table']['glyf']['offset'] + $this->fdt['indexToLoc'][$key]);
            $numberOfContours = $this->fbyte->getShort($this->offset);
            $this->offset += 2;
            if ($numberOfContours < 0) { // composite glyph
                $this->offset += 8; // skip xMin, yMin, xMax, yMax
                do {
                    $flags = $this->fbyte->getUShort($this->offset);
                    $this->offset += 2;
                    $glyphIndex = $this->fbyte->getUShort($this->offset);
                    $this->offset += 2;
                    if (!isset($this->subglyphs[$glyphIndex])) {
                        // add missing glyphs
                        $new_sga[$glyphIndex] = true;
                    }
                    // skip some bytes by case
                    if ($flags & 1) {
                        $this->offset += 4;
                    } else {
                        $this->offset += 2;
                    }
                    if ($flags & 8) {
                        $this->offset += 2;
                    } elseif ($flags & 64) {
                        $this->offset += 4;
                    } elseif ($flags & 128) {
                        $this->offset += 8;
                    }
                } while ($flags & 32);
            }
        }
        return $new_sga;
    }

    /**
     * Remove unused tables
     */
    protected function removeUnusedTables()
    {
        // array of table names to preserve (loca and glyf tables will be added later)
        // the cmap table is not needed and shall not be present,
        // since the mapping from character codes to glyph descriptions is provided separately
        $table_names = array ('head', 'hhea', 'hmtx', 'maxp', 'cvt ', 'fpgm', 'prep', 'glyf', 'loca');
        // get the tables to preserve
        $this->offset = 12;
        $tabname = array_keys($this->fdt['table']);
        foreach ($tabname as $tag) {
            if (in_array($tag, $table_names)) {
                $this->fdt['table'][$tag]['data'] = substr(
                    $this->font,
                    $this->fdt['table'][$tag]['offset'],
                    $this->fdt['table'][$tag]['length']
                );
                if ($tag == 'head') {
                    // set the checkSumAdjustment to 0
                    $this->fdt['table'][$tag]['data'] = substr($this->fdt['table'][$tag]['data'], 0, 8)
                        ."\x0\x0\x0\x0".substr($this->fdt['table'][$tag]['data'], 12);
                }
                $pad = 4 - ($this->fdt['table'][$tag]['length'] % 4);
                if ($pad != 4) {
                    // the length of a table must be a multiple of four bytes
                    $this->fdt['table'][$tag]['length'] += $pad;
                    $this->fdt['table'][$tag]['data'] .= str_repeat("\x0", $pad);
                }
                $this->fdt['table'][$tag]['offset'] = $this->offset;
                $this->offset += $this->fdt['table'][$tag]['length'];
                // check sum is not changed
            } else {
                // remove the table
                unset($this->fdt['table'][$tag]);
            }
        }
    }

    /**
     * Add glyf and loca tables
     */
    protected function addProcessedTables()
    {
        // build new glyf and loca tables
        $glyf = '';
        $loca = '';
        $this->offset = 0;
        $glyf_offset = $this->fdt['table']['glyf']['offset'];
        for ($i = 0; $i < $this->fdt['tot_num_glyphs']; ++$i) {
            if (isset($this->subglyphs[$i])
                && isset($this->fdt['indexToLoc'][$i])
                && isset($this->fdt['indexToLoc'][($i + 1)])
            ) {
                $length = ($this->fdt['indexToLoc'][($i + 1)] - $this->fdt['indexToLoc'][$i]);
                $glyf .= substr($this->font, ($glyf_offset + $this->fdt['indexToLoc'][$i]), $length);
            } else {
                $length = 0;
            }
            if ($this->fdt['short_offset']) {
                $loca .= pack('n', floor($this->offset / 2));
            } else {
                $loca .= pack('N', $this->offset);
            }
            $this->offset += $length;
        }
        // add loca
        $this->fdt['table']['loca']['data'] = $loca;
        $this->fdt['table']['loca']['length'] = strlen($loca);
        $pad = 4 - ($this->fdt['table']['loca']['length'] % 4);
        if ($pad != 4) {
            // the length of a table must be a multiple of four bytes
            $this->fdt['table']['loca']['length'] += $pad;
            $this->fdt['table']['loca']['data'] .= str_repeat("\x0", $pad);
        }
        $this->fdt['table']['loca']['offset'] = $this->offset;
        $this->fdt['table']['loca']['checkSum'] = $this->getTableChecksum(
            $this->fdt['table']['loca']['data'],
            $this->fdt['table']['loca']['length']
        );
        $this->offset += $this->fdt['table']['loca']['length'];
        // add glyf
        $this->fdt['table']['glyf']['data'] = $glyf;
        $this->fdt['table']['glyf']['length'] = strlen($glyf);
        $pad = 4 - ($this->fdt['table']['glyf']['length'] % 4);
        if ($pad != 4) {
            // the length of a table must be a multiple of four bytes
            $this->fdt['table']['glyf']['length'] += $pad;
            $this->fdt['table']['glyf']['data'] .= str_repeat("\x0", $pad);
        }
        $this->fdt['table']['glyf']['offset'] = $this->offset;
        $this->fdt['table']['glyf']['checkSum'] = $this->getTableChecksum(
            $this->fdt['table']['glyf']['data'],
            $this->fdt['table']['glyf']['length']
        );
    }

    /**
     * build new subset font
     */
    protected function buildSubsetFont()
    {
        $this->subfont = '';
        $this->subfont .= pack('N', 0x10000); // sfnt version
        $numTables = count($this->fdt['table']);
        $this->subfont .= pack('n', $numTables); // numTables
        $entrySelector = floor(log($numTables, 2));
        $searchRange = pow(2, $entrySelector) * 16;
        $rangeShift = ($numTables * 16) - $searchRange;
        $this->subfont .= pack('n', $searchRange); // searchRange
        $this->subfont .= pack('n', $entrySelector); // entrySelector
        $this->subfont .= pack('n', $rangeShift); // rangeShift
        $this->offset = ($numTables * 16);
        foreach ($this->fdt['table'] as $tag => $data) {
            $this->subfont .= $tag; // tag
            $this->subfont .= pack('N', $data['checkSum']); // checkSum
            $this->subfont .= pack('N', ($data['offset'] + $this->offset)); // offset
            $this->subfont .= pack('N', $data['length']); // length
        }
        foreach ($this->fdt['table'] as $data) {
            $this->subfont .= $data['data'];
        }
        // set checkSumAdjustment on head table
        $checkSumAdjustment = (0xB1B0AFBA - $this->getTableChecksum($this->subfont, strlen($this->subfont)));
        $this->subfont = substr($this->subfont, 0, $this->fdt['table']['head']['offset'] + 8)
            .pack('N', $checkSumAdjustment)
            .substr($this->subfont, $this->fdt['table']['head']['offset'] + 12);
    }
}
