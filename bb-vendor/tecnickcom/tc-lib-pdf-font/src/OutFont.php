<?php
/**
 * OutFont.php
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

use \Com\Tecnick\Unicode\Data\Identity;
use \Com\Tecnick\Pdf\Encrypt\Encrypt;
use \Com\Tecnick\Pdf\Font\Exception as FontException;

/**
 * Com\Tecnick\Pdf\Font\OutFont
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 */
abstract class OutFont extends \Com\Tecnick\Pdf\Font\OutUtil
{
    /**
     * Current PDF object number
     *
     * @var int
     */
    protected $pon;

    /**
     * Encrypt object
     *
     * @var Encrypt
     */
    protected $enc;

    /**
     * Get the PDF output string for a CID-0 font.
     * A Type 0 CIDFont contains glyph descriptions based on the Adobe Type 1 font format
     *
     * @param array $font Font to process
     *
     * return string
     */
    protected function getCid0(array $font)
    {
        $cidoffset = 0;
        if (!isset($font['cw'][1])) {
            $cidoffset = 31;
        }
        $this->uniToCid($font, $cidoffset);
        $name = $font['name'];
        $longname = $name;
        if (!empty($font['enc'])) {
            $longname .= '-'.$font['enc'];
        }
        
        // obj 1
        $out = $font['n'].' 0 obj'."\n"
            .'<</Type /Font'
            .' /Subtype /Type0'
            .' /BaseFont /'.$longname
            .' /Name /F'.$font['i'];
        if (!empty($font['enc'])) {
            $out .= ' /Encoding /'.$font['enc'];
        }
        $out .= ' /DescendantFonts ['.($this->pon + 1).' 0 R]'
            .' >>'."\n"
            .'endobj'."\n";

        // obj 2
        $out .= (++$this->pon).' 0 obj'."\n"
            .'<</Type /Font'
            .' /Subtype /CIDFontType0'
            .' /BaseFont /'.$name;
        $cidinfo = '/Registry '.$this->enc->escapeDataString($font['cidinfo']['Registry'], $this->pon)
            .' /Ordering '.$this->enc->escapeDataString($font['cidinfo']['Ordering'], $this->pon)
            .' /Supplement '.$font['cidinfo']['Supplement'];
        $out .= ' /CIDSystemInfo <<'.$cidinfo.'>>'
            .' /FontDescriptor '.($this->pon + 1).' 0 R'
            .' /DW '.$font['dw']."\n"
            .$this->getCharWidths($font, $cidoffset)
            .' >>'."\n"
            .'endobj'."\n";

        // obj 3
        $out .= (++$this->pon).' 0 obj'."\n"
            .'<</Type /FontDescriptor /FontName /'.$name;
        foreach ($font['desc'] as $key => $val) {
            if ($key != 'Style') {
                $out .= $this->getKeyValOut($key, $val);
            }
        }
        $out .= '>>'."\n"
            .'endobj'."\n";

        return $out;
    }

    /**
     * Convert Unicode to CID
     *
     * @param array $font      Font to process
     * @param int   $cidoffset Offset for CID values
     *
     * @return array Processed font
     */
    protected function uniToCid(array &$font, $cidoffset)
    {
        if (isset($font['cidinfo']['uni2cid'])) {
            // convert unicode to cid.
            $uni2cid = $font['cidinfo']['uni2cid'];
            $chw = array();
            foreach ($font['cw'] as $uni => $width) {
                if (isset($uni2cid[$uni])) {
                    $chw[($uni2cid[$uni] + $cidoffset)] = $width;
                } elseif ($uni < 256) {
                    $chw[$uni] = $width;
                } // else unknown character
            }
            $font['cw'] = array_merge($font['cw'], $chw);
        }
    }

    /**
     * Get the PDF output string for a TrueTypeUnicode font.
     * Based on PDF Reference 1.3 (section 5)
     *
     * @param array $font Font to process
     *
     * return string
     */
    protected function getTrueTypeUnicode(array $font)
    {
        $fontname = '';
        if ($font['subset']) {
            // change name for font subsetting
            $subtag = sprintf('%06u', $font['i']);
            $subtag = strtr($subtag, '0123456789', 'ABCDEFGHIJ');
            $fontname .= $subtag.'+';
        }
        $fontname .= $font['name'];

        // Type0 Font
        // A composite font composed of other fonts, organized hierarchically

        // obj 1
        $out = $font['n'].' 0 obj'."\n"
            .'<< /Type /Font'
            .' /Subtype /Type0'
            .' /BaseFont /'.$fontname
            .' /Name /F'.$font['i']
            .' /Encoding /'.$font['enc']
            .' /ToUnicode '.($this->pon + 1).' 0 R'
            .' /DescendantFonts ['.($this->pon + 2).' 0 R]'
            .' >>'."\n"
            .'endobj'."\n";

        // ToUnicode Object
        $out .= (++$this->pon).' 0 obj'."\n";
        $stream = $this->enc->encryptString(gzcompress(Identity::CIDHMAP), $this->pon); // ToUnicode map for Identity-H
        $out .= '<</Filter /FlateDecode /Length '.strlen($stream).'>> stream'."\n"
            .$stream."\n"
            .'endstream'."\n"
            .'endobj'."\n";

        // CIDFontType2
        // A CIDFont whose glyph descriptions are based on TrueType font technology
        $out .= (++$this->pon).' 0 obj'."\n"
            .'<< /Type /Font'
            .' /Subtype /CIDFontType2'
            .' /BaseFont /'.$fontname;
        // A dictionary containing entries that define the character collection of the CIDFont.
        $cidinfo = '/Registry '.$this->enc->escapeDataString($font['cidinfo']['Registry'], $this->pon)
            .' /Ordering '.$this->enc->escapeDataString($font['cidinfo']['Ordering'], $this->pon)
            .' /Supplement '.$font['cidinfo']['Supplement'];
        $out .= ' /CIDSystemInfo << '.$cidinfo.' >>'
            .' /FontDescriptor '.($this->pon + 1).' 0 R'
            .' /DW '.$font['dw']."\n"
            .$this->getCharWidths($font, 0);
        if (!empty($font['ctg'])) {
            $out .= "\n".'/CIDToGIDMap '.($this->pon + 2).' 0 R';
        }
        $out .= ' >>'."\n"
            .'endobj'."\n";

        // Font descriptor
        // A font descriptor describing the CIDFont default metrics other than its glyph widths
        $out .= (++$this->pon).' 0 obj'."\n"
            .'<< /Type /FontDescriptor'
            .' /FontName /'.$fontname;
        foreach ($font['desc'] as $key => $val) {
            $out .= $this->getKeyValOut($key, $val);
        }

        if (!empty($font['file_n'])) {
            // A stream containing a TrueType font
            $out .= ' /FontFile2 '.$font['file_n'].' 0 R';
        }
        $out .= ' >>'."\n"
            .'endobj'."\n";

        if (!empty($font['ctg'])) {
            $out .= (++$this->pon).' 0 obj'."\n";
            // Embed CIDToGIDMap
            // A specification of the mapping from CIDs to glyph indices
            // search and get CTG font file to embedd
            $ctgfile = strtolower($font['ctg']);
            // search and get ctg font file to embedd
            $fontfile = $this->getFontFullPath($font['dir'], $ctgfile);
            $stream = $this->enc->encryptString(file_get_contents($fontfile), $this->pon);
            $out .= '<< /Length '.strlen($stream).'';
            if (substr($fontfile, -2) == '.z') { // check file extension
                // Decompresses data encoded using the public-domain
                // zlib/deflate compression method, reproducing the
                // original text or binary data
                $out .= ' /Filter /FlateDecode';
            }
            $out .= ' >>'
                .' stream'."\n"
                .$stream."\n"
                .'endstream'."\n"
                .'endobj'."\n";
        }

        return $out;
    }

    /**
     * Get the PDF output string for a Core font.
     *
     * @param array $font Font to process
     *
     * return string
     */
    protected function getCore(array $font)
    {
        $out = $font['n'].' 0 obj'."\n"
            .'<</Type /Font'
            .' /Subtype /Type1'
            .' /BaseFont /'.$font['name']
            .' /Name /F'.$font['i'];
        if (($font['family'] != 'symbol') && ($font['family'] != 'zapfdingbats')) {
            $out .= ' /Encoding /WinAnsiEncoding';
        }
        $out .= ' >>'."\n"
            .'endobj'."\n";
        return $out;
    }

    /**
     * Get the PDF output string for a Core font.
     *
     * @param array $font Font to process
     *
     * return string
     */
    protected function getTrueType(array $font)
    {
        // obj 1
        $out = $font['n'].' 0 obj'."\n"
            .'<</Type /Font'
            .' /Subtype /'.$font['type']
            .' /BaseFont /'.$font['name']
            .' /Name /F'.$font['i']
            .' /FirstChar 32 /LastChar 255'
            .' /Widths '.($this->pon + 1).' 0 R'
            .' /FontDescriptor '.($this->pon + 2).' 0 R';
        if (!empty($font['enc'])) {
            if (isset($font['diff_n'])) {
                $out .= ' /Encoding '.$font['diff_n'].' 0 R';
            } else {
                $out .= ' /Encoding /WinAnsiEncoding';
            }
        }
        $out .= ' >>'."\n"
            .'endobj'."\n";

        // obj 2 - Widths
        $out .= (++$this->pon).' 0 obj'."\n"
            .'[';
        for ($idx = 32; $idx < 256; ++$idx) {
            if (isset($font['cw'][$idx])) {
                $out .= $font['cw'][$idx].' ';
            } else {
                $out .= $font['dw'].' ';
            }
        }
        $out .= ']'."\n"
            .'endobj'."\n";

        // obj 3 - Descriptor
        $out .= (++$this->pon).' 0 obj'."\n"
            .'<</Type /FontDescriptor /FontName /'.$font['name'];
        foreach ($font['desc'] as $fdk => $fdv) {
            $out .= $this->getKeyValOut($fdk, $fdv);
        }
        if (!empty($font['file'])) {
            $out .= ' /FontFile'.($font['type'] == 'Type1' ? '' : '2').' '.$font['file_n'].' 0 R';
        }
        $out .= '>>'."\n"
            .'endobj'."\n";

        return $out;
    }

    /**
     * Returns the formatted key/value PDF string
     *
     * @param string $key   Key name
     * @param mixed  $value Value
     *
     * @return string
     */
    protected function getKeyValOut($key, $val)
    {
        if (is_float($val)) {
            $val = sprintf('%F', $val);
        }
        return ' /'.$key.' '.$val.'';
    }
}
