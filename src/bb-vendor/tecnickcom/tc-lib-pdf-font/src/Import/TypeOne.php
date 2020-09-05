<?php
/**
 * TypeOne.php
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
 * Com\Tecnick\Pdf\Font\Import\TypeOne
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFont
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-font
 */
class TypeOne extends \Com\Tecnick\Pdf\Font\Import\Core
{
    /**
     * Store font data
     */
    protected function storeFontData()
    {
        // read first segment
        $dat = unpack('Cmarker/Ctype/Vsize', substr($this->font, 0, 6));
        if ($dat['marker'] != 128) {
            throw new FontException('Font file is not a valid binary Type1');
        }
        $this->fdt['size1'] = $dat['size'];
        $data = substr($this->font, 6, $this->fdt['size1']);
        // read second segment
        $dat = unpack('Cmarker/Ctype/Vsize', substr($this->font, (6 + $this->fdt['size1']), 6));
        if ($dat['marker'] != 128) {
            throw new FontException('Font file is not a valid binary Type1');
        }
        $this->fdt['size2'] = $dat['size'];
        $this->fdt['encrypted'] = substr($this->font, (12 + $this->fdt['size1']), $this->fdt['size2']);
        $data .= $this->fdt['encrypted'];
        // store compressed font
        $this->fdt['file'] = $this->fdt['file_name'].'.z';
        $file = new File();
        $fpt = $file->fopenLocal($this->fdt['dir'].$this->fdt['file'], 'wb');
        fwrite($fpt, gzcompress($data));
        fclose($fpt);
    }

    /**
     * Extract Font information
     */
    protected function extractFontInfo()
    {
        if (preg_match('#/FontName[\s]*\/([^\s]*)#', $this->font, $matches) !== 1) {
            preg_match('#/FullName[\s]*\(([^\)]*)#', $this->font, $matches);
        }
        $this->fdt['name'] = preg_replace('/[^a-zA-Z0-9_\-]/', '', $matches[1]);
        preg_match('#/FontBBox[\s]*{([^}]*)#', $this->font, $matches);
        $this->fdt['bbox'] = trim($matches[1]);
        $bvl = explode(' ', $this->fdt['bbox']);
        $this->fdt['Ascent'] = intval($bvl[3]);
        $this->fdt['Descent'] = intval($bvl[1]);
        preg_match('#/ItalicAngle[\s]*([0-9\+\-]*)#', $this->font, $matches);
        $this->fdt['italicAngle'] = intval($matches[1]);
        if ($this->fdt['italicAngle'] != 0) {
            $this->fdt['Flags'] |= 64;
        }
        preg_match('#/UnderlinePosition[\s]*([0-9\+\-]*)#', $this->font, $matches);
        $this->fdt['underlinePosition'] = intval($matches[1]);
        preg_match('#/UnderlineThickness[\s]*([0-9\+\-]*)#', $this->font, $matches);
        $this->fdt['underlineThickness'] = intval($matches[1]);
        preg_match('#/isFixedPitch[\s]*([^\s]*)#', $this->font, $matches);
        if ($matches[1] == 'true') {
            $this->fdt['Flags'] |= 1;
        }
        preg_match('#/Weight[\s]*\(([^\)]*)#', $this->font, $matches);
        if (!empty($matches[1])) {
            $this->fdt['weight'] = strtolower($matches[1]);
        }
        $this->fdt['weight'] = 'Book';
        $this->fdt['Leading'] = 0;
    }

    /**
     * Extract Font information
     *
     * @return array
     */
    protected function getInternalMap()
    {
        $imap = array();
        if (preg_match_all('#dup[\s]([0-9]+)[\s]*/([^\s]*)[\s]put#sU', $this->font, $fmap, PREG_SET_ORDER) > 0) {
            foreach ($fmap as $val) {
                $imap[$val[2]] = $val[1];
            }
        }
        return $imap;
    }

    /**
     * Decrypt eexec encrypted part
     *
     * @return string
     */
    protected function getEplain()
    {
        $csr = 55665; // eexec encryption constant
        $cc1 = 52845;
        $cc2 = 22719;
        $elen = strlen($this->fdt['encrypted']);
        $eplain = '';
        for ($idx = 0; $idx < $elen; ++$idx) {
            $chr = ord($this->fdt['encrypted'][$idx]);
            $eplain .= chr($chr ^ ($csr >> 8));
            $csr = ((($chr + $csr) * $cc1 + $cc2) % 65536);
        }
        return $eplain;
    }

    /**
     * Extract eexec info
     *
     * @return array
     */
    protected function extractEplainInfo()
    {
        $eplain = $this->getEplain();
        if (preg_match('#/ForceBold[\s]*([^\s]*)#', $eplain, $matches) > 0) {
            if ($matches[1] == 'true') {
                $this->fdt['Flags'] |= 0x40000;
            }
        }
        $this->extractStem($eplain);
        if (preg_match('#/BlueValues[\s]*\[([^\]]*)#', $eplain, $matches) > 0) {
            $bvl = explode(' ', $matches[1]);
            if (count($bvl) >= 6) {
                $vl1 = intval($bvl[2]);
                $vl2 = intval($bvl[4]);
                $this->fdt['XHeight'] = min($vl1, $vl2);
                $this->fdt['CapHeight'] = max($vl1, $vl2);
            }
        }
        $this->getRandomBytes($eplain);
        return $this->getCharstringData($eplain);
    }

    /**
     * Extract eexec info
     *
     * @param string $eplain Decoded eexec encrypted part
     *
     * @return array
     */
    protected function extractStem($eplain)
    {
        if (preg_match('#/StdVW[\s]*\[([^\]]*)#', $eplain, $matches) > 0) {
            $this->fdt['StemV'] = intval($matches[1]);
        } elseif (($this->fdt['weight'] == 'bold') || ($this->fdt['weight'] == 'black')) {
            $this->fdt['StemV'] = 123;
        } else {
            $this->fdt['StemV'] = 70;
        }
        if (preg_match('#/StdHW[\s]*\[([^\]]*)#', $eplain, $matches) > 0) {
            $this->fdt['StemH'] = intval($matches[1]);
        } else {
            $this->fdt['StemH'] = 30;
        }
        if (preg_match('#/Cap[X]?Height[\s]*\[([^\]]*)#', $eplain, $matches) > 0) {
            $this->fdt['CapHeight'] = intval($matches[1]);
        } else {
            $this->fdt['CapHeight'] = $this->fdt['Ascent'];
        }
        $this->fdt['XHeight'] = ($this->fdt['Ascent'] + $this->fdt['Descent']);
    }

    /**
     * Get the number of random bytes at the beginning of charstrings
     */
    protected function getRandomBytes($eplain)
    {
        $this->fdt['lenIV'] = 4;
        if (preg_match('#/lenIV[\s]*([0-9]*)#', $eplain, $matches) > 0) {
            $this->fdt['lenIV'] = intval($matches[1]);
        }
    }

    /**
     * Get charstring data
     */
    protected function getCharstringData($eplain)
    {
        $this->fdt['enc_map'] = false;
        $eplain = substr($eplain, (strpos($eplain, '/CharStrings') + 1));
        preg_match_all('#/([A-Za-z0-9\.]*)[\s][0-9]+[\s]RD[\s](.*)[\s]ND#sU', $eplain, $matches, PREG_SET_ORDER);
        if (!empty($this->fdt['enc']) && isset(Encoding::$map[$this->fdt['enc']])) {
            $this->fdt['enc_map'] = Encoding::$map[$this->fdt['enc']];
        }
        return $matches;
    }

    /**
     * get CID
     *
     * @param array $imap
     * @param array $val
     *
     * @return int
     */
    protected function getCid($imap, $val)
    {
        if (isset($imap[$val[1]])) {
            return $imap[$val[1]];
        }
        if ($this->fdt['enc_map'] === false) {
            return 0;
        }
        $cid = array_search($val[1], $this->fdt['enc_map']);
        if ($cid === false) {
            return 0;
        }
        if ($cid > 1000) {
            return 1000;
        }
        return $cid;
    }

    /**
     * Decode number
     *
     * @param int   $idx
     * @param int   $cck
     * @param int   $cid
     * @param array $ccom
     * @param array $cdec
     * @param array $cwidths
     *
     * @return $int
     */
    protected function decodeNumber($idx, &$cck, &$cid, &$ccom, &$cdec, &$cwidths)
    {
        if ($ccom[$idx] == 255) {
            $sval = chr($ccom[($idx + 1)]).chr($ccom[($idx + 2)]).chr($ccom[($idx + 3)]).chr($ccom[($idx + 4)]);
            $vsval = unpack('li', $sval);
            $cdec[$cck] = $vsval['i'];
            return ($idx + 5);
        }
        if ($ccom[$idx] >= 251) {
            $cdec[$cck] = ((-($ccom[$idx] - 251) * 256) - $ccom[($idx + 1)] - 108);
            return ($idx + 2);
        }
        if ($ccom[$idx] >= 247) {
            $cdec[$cck] = ((($ccom[$idx] - 247) * 256) + $ccom[($idx + 1)] + 108);
            return ($idx + 2);
        }
        if ($ccom[$idx] >= 32) {
            $cdec[$cck] = ($ccom[$idx] - 139);
            return ++$idx;
        }
        $cdec[$cck] = $ccom[$idx];
        if (($cck > 0) && ($cdec[$cck] == 13)) {
            // hsbw command: update width
            $cwidths[$cid] = $cdec[($cck - 1)];
        }
        return ++$idx;
    }

    /**
     * Process Type1 font
     */
    protected function process()
    {
        $this->storeFontData();
        $this->extractFontInfo();
        $imap = $this->getInternalMap();
        $matches = $this->extractEplainInfo();
        $cwidths = array();
        $cc1 = 52845;
        $cc2 = 22719;
        foreach ($matches as $val) {
            $cid = $this->getCid($imap, $val);
            // decrypt charstring encrypted part
            $csr = 4330; // charstring encryption constant
            $ccd = $val[2];
            $clen = strlen($ccd);
            $ccom = array();
            for ($idx = 0; $idx < $clen; ++$idx) {
                $chr = ord($ccd[$idx]);
                $ccom[] = ($chr ^ ($csr >> 8));
                $csr = ((($chr + $csr) * $cc1 + $cc2) % 65536);
            }
            // decode numbers
            $cdec = array();
            $cck = 0;
            $idx = $this->fdt['lenIV'];
            while ($idx < $clen) {
                $idx = $this->decodeNumber($idx, $cck, $cid, $ccom, $cdec, $cwidths);
                ++$cck;
            }
        }
        $this->setCharWidths($cwidths);
    }
}
