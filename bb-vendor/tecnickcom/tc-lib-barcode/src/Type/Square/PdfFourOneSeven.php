<?php
/**
 * PdfFourOneSeven.php
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 *
 * This file is part of tc-lib-barcode software library.
 */

namespace Com\Tecnick\Barcode\Type\Square;

use \Com\Tecnick\Barcode\Exception as BarcodeException;
use \Com\Tecnick\Barcode\Type\Square\PdfFourOneSeven\Data;

/**
 * Com\Tecnick\Barcode\Type\Square\PdfFourOneSeven
 *
 * PdfFourOneSeven Barcode type class
 * PDF417 (ISO/IEC 15438:2006)
 *
 * PDF417 (ISO/IEC 15438:2006) is a 2-dimensional stacked bar code created by Symbol Technologies in 1991.
 * It is one of the most popular 2D codes because of its ability to be read with slightly modified handheld
 * laser or linear CCD scanners.
 * TECHNICAL DATA / FEATURES OF PDF417:
 *     Encodable Character Set:     All 128 ASCII Characters (including extended)
 *     Code Type:                   Continuous, Multi-Row
 *     Symbol Height:               3 - 90 Rows
 *     Symbol Width:                90X - 583X
 *     Bidirectional Decoding:      Yes
 *     Error Correction Characters: 2 - 512
 *     Maximum Data Characters:     1850 text, 2710 digits, 1108 bytes
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class PdfFourOneSeven extends \Com\Tecnick\Barcode\Type\Square\PdfFourOneSeven\Compaction
{
    /**
     * Barcode format
     *
     * @var string
     */
    protected $format = 'PDF417';

    /**
     * Row height respect X dimension of single module
     *
     * @var int
     */
    protected $row_height = 2;

    /**
     * Horizontal quiet zone in modules
     *
     * @var int
     */
    protected $quiet_vertical = 2;

    /**
     * Vertical quiet zone in modules
     *
     * @var int
     */
    protected $quiet_horizontal = 2;

    /**
     * Aspect ratio (width / height)
     *
     * @var int
     */
    protected $aspectratio = 2;

    /**
     * Error correction level (0-8);
     * Default -1 = automatic correction level
     *
     * @var int
     */
    protected $ecl = -1;
    
    /**
     * Information for macro block
     *
     * @var int
     */
    protected $macro = array();

    /**
     * Set extra (optional) parameters
     */
    protected function setParameters()
    {
        parent::setParameters();
        // aspect ratio
        if (!empty($this->params[0]) && (($aspectratio = floatval($this->params[0])) >= 1)) {
            $this->aspectratio = $aspectratio;
        }
        // error correction level (auto)
        if (isset($this->params[1]) && (($ecl = intval($this->params[1])) >= 0) && ($ecl <= 8)) {
            $this->ecl = $ecl;
        }
        // macro block
        $this->setMacroBlockParam();
    }

    /**
     * Set macro block parameter
     */
    protected function setMacroBlockParam()
    {
        if (isset($this->params[4])
            && ($this->params[2] !== '')
            && ($this->params[3] !== '')
            && ($this->params[4] !== '')
        ) {
            $this->macro['segment_total'] = intval($this->params[2]);
            $this->macro['segment_index'] = intval($this->params[3]);
            $this->macro['file_id'] = strtr($this->params[4], "\xff", ',');
            for ($idx = 0; $idx < 7; ++$idx) {
                $opt = $idx + 5;
                if (isset($this->params[$opt]) && ($this->params[$opt] !== '')) {
                    $this->macro['option_'.$idx] = strtr($this->params[$opt], "\xff", ',');
                }
            }
        }
    }

    /**
     * Get the bars array
     *
     * @throws BarcodeException in case of error
     */
    protected function setBars()
    {
        if (strlen((string)$this->code) == 0) {
            throw new BarcodeException('Empty input');
        }
        $barcode = $this->getBinSequence();
        $this->processBinarySequence($barcode);
    }

    /**
     * Get macro control block codewords
     *
     * @param int $numcw Number of codewords
     *
     * @return array
     */
    protected function getMacroBlock(&$numcw)
    {
        if (empty($this->macro)) {
            return array();
        }
        $macrocw = array();
        // beginning of macro control block
        $macrocw[] = 928;
        // segment index
        $cdw = $this->getCompaction(902, sprintf('%05d', $this->macro['segment_index']), false);
        $macrocw = array_merge($macrocw, $cdw);
        // file ID
        $cdw = $this->getCompaction(900, $this->macro['file_id'], false);
        $macrocw = array_merge($macrocw, $cdw);
        // optional fields
        $optmodes = array(900,902,902,900,900,902,902);
        $optsize = array(-1,2,4,-1,-1,-1,2);
        foreach ($optmodes as $key => $omode) {
            if (isset($this->macro['option_'.$key])) {
                $macrocw[] = 923;
                $macrocw[] = $key;
                if ($optsize[$key] == 2) {
                    $this->macro['option_'.$key] = sprintf('%05d', $this->macro['option_'.$key]);
                } elseif ($optsize[$key] == 4) {
                    $this->macro['option_'.$key] = sprintf('%010d', $this->macro['option_'.$key]);
                }
                $cdw = $this->getCompaction($omode, $this->macro['option_'.$key], false);
                $macrocw = array_merge($macrocw, $cdw);
            }
        }
        if ($this->macro['segment_index'] == ($this->macro['segment_total'] - 1)) {
            // end of control block
            $macrocw[] = 922;
        }
        // update total codewords
        $numcw += count($macrocw);
        return $macrocw;
    }

    /**
     * Get codewords
     *
     * @param int $rows number of rows
     * @param int $cols number of columns
     * @param int $ecl eroor correction level
     *
     * @return array
     *
     * @throws BarcodeException in case of error
     */
    public function getCodewords(&$rows, &$cols, &$ecl)
    {
        $codewords = array(); // array of code-words
        // get the input sequence array
        $sequence = $this->getInputSequences($this->code);
        foreach ($sequence as $seq) {
            $cw = $this->getCompaction($seq[0], $seq[1], true);
            $codewords = array_merge($codewords, $cw);
        }
        if ($codewords[0] == 900) {
            // Text Alpha is the default mode, so remove the first code
            array_shift($codewords);
        }
        // count number of codewords
        $numcw = count($codewords);
        if ($numcw > 925) {
            throw new BarcodeException('The maximum codeword capaciy has been reached: '.$numcw.' > 925');
        }
        $macrocw = $this->getMacroBlock($numcw);
        // set error correction level
        $ecl = $this->getErrorCorrectionLevel($this->ecl, $numcw);
        // number of codewords for error correction
        $errsize = (2 << $ecl);
        // calculate number of columns (number of codewords per row) and rows
        $nce = ($numcw + $errsize + 1);
        $cols = min(30, max(1, round((sqrt(4761 + (68 * $this->aspectratio * $this->row_height * $nce)) - 69) / 34)));
        $rows = min(90, max(3, ceil($nce / $cols)));
        $size = ($cols * $rows);
        if ($size > 928) {
            // set dimensions to get maximum capacity
            if (abs($this->aspectratio - (17 * 29 / 32)) < abs($this->aspectratio - (17 * 16 / 58))) {
                $cols = 29;
                $rows = 32;
            } else {
                $cols = 16;
                $rows = 58;
            }
            $size = 928;
        }
        // calculate padding
        $pad = ($size - $nce);
        if ($pad > 0) {
            // add padding
            $codewords = array_merge($codewords, array_fill(0, $pad, 900));
        }
        if (!empty($macrocw)) {
            // add macro section
            $codewords = array_merge($codewords, $macrocw);
        }
        // Symbol Length Descriptor (number of data codewords including Symbol Length Descriptor and pad codewords)
        $sld = ($size - $errsize);
        // add symbol length description
        array_unshift($codewords, $sld);
        // calculate error correction
        $ecw = $this->getErrorCorrection($codewords, $ecl);
        // add error correction codewords
        return array_merge($codewords, $ecw);
    }

    /**
     * Creates a PDF417 object as binary string
     *
     * @return array barcode as binary string
     *
     * @throws BarcodeException in case of error
     */
    public function getBinSequence()
    {
        $rows = 0;
        $cols = 0;
        $ecl = 0;
        $codewords = $this->getCodewords($rows, $cols, $ecl);
        $barcode = '';
        // add horizontal quiet zones to start and stop patterns
        $pstart = str_repeat('0', $this->quiet_horizontal).Data::$start_pattern;
        $this->nrows = ($rows * $this->row_height) + (2 * $this->quiet_vertical);
        $this->ncols = (($cols + 2) * 17) + 35 + (2 * $this->quiet_horizontal);
        // build rows for vertical quiet zone
        $empty_row = ','.str_repeat('0', $this->ncols);
        $empty_rows = str_repeat($empty_row, $this->quiet_vertical);
        $barcode .= $empty_rows;
        $kcw = 0; // codeword index
        $cid = 0; // initial cluster
        // for each row
        for ($rix = 0; $rix < $rows; ++$rix) {
            // row start code
            $row = $pstart;
            switch ($cid) {
                case 0:
                    $rval = ((30 * intval($rix / 3)) + intval(($rows - 1) / 3));
                    $cval = ((30 * intval($rix / 3)) + ($cols - 1));
                    break;
                case 1:
                    $rval = ((30 * intval($rix / 3)) + ($ecl * 3) + (($rows - 1) % 3));
                    $cval = ((30 * intval($rix / 3)) + intval(($rows - 1) / 3));
                    break;
                case 2:
                    $rval = ((30 * intval($rix / 3)) + ($cols - 1));
                    $cval = ((30 * intval($rix / 3)) + ($ecl * 3) + (($rows - 1) % 3));
                    break;
            }
            // left row indicator
            $row .= sprintf('%17b', Data::$clusters[$cid][$rval]);
            // for each column
            for ($cix = 0; $cix < $cols; ++$cix) {
                $row .= sprintf('%17b', Data::$clusters[$cid][$codewords[$kcw]]);
                ++$kcw;
            }
            // right row indicator
            $row .= sprintf('%17b', Data::$clusters[$cid][$cval]);
            // row stop code
            $row .= Data::$stop_pattern.str_repeat('0', $this->quiet_horizontal);
            $brow = ','.str_repeat($row, $this->row_height);
            $barcode .= $brow;
            ++$cid;
            if ($cid > 2) {
                $cid = 0;
            }
        }
        $barcode .= $empty_rows;
        return $barcode;
    }
}
