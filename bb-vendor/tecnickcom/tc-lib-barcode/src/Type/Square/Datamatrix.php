<?php
/**
 * Datamatrix.php
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2020 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 *
 * This file is part of tc-lib-barcode software library.
 */

namespace Com\Tecnick\Barcode\Type\Square;

use \Com\Tecnick\Barcode\Exception as BarcodeException;
use \Com\Tecnick\Barcode\Type\Square\Datamatrix\Data;
use \Com\Tecnick\Barcode\Type\Square\Datamatrix\Encode;

/**
 * Com\Tecnick\Barcode\Type\Square\Datamatrix
 *
 * Datamatrix Barcode type class
 * DATAMATRIX (ISO/IEC 16022)
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2016 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class Datamatrix extends \Com\Tecnick\Barcode\Type\Square
{
    /**
     * Barcode format
     *
     * @var string
     */
    protected $format = 'DATAMATRIX';

    /**
     * Array of codewords.
     *
     * @var array
     */
    protected $cdw = array();

    /**
     * Binary grid
     *
     * @var array
     */
    protected $grid = array();

    /**
     * Datamatrix Encoding object
     *
     * @var \Com\Tecnick\Barcode\Type\Square\Datamatrix\Encode
     */
    protected $dmx;

    /**
     * Datamatrix shape key (S=square, R=rectangular)
     *
     * @var string
     */
    protected $shape = 'S';

    /**
     * Datamatrix variant (N=default, GS1=FNC1 codeword in first place)
     *
     * @var string
     */
    protected $gsonemode = false;

    /**
     * Set extra (optional) parameters:
     *     1: SHAPE - S=square (default), R=rectangular
     *     2: MODE - N=default, GS1 = the FNC1 codeword is added in the first position of Data Matrix ECC 200 version
     */
    protected function setParameters()
    {
        parent::setParameters();
        // shape
        if (isset($this->params[0]) && ($this->params[0] == 'R')) {
            $this->shape = 'R';
        }
        // mode
        if (isset($this->params[1]) && ($this->params[1] == 'GS1')) {
            $this->gsonemode = true;
        }
    }

    /**
     * Add padding codewords
     *
     * @param int $size Max barcode size in codewords
     * @param int $ncw  Number of codewords
     *
     * @throws BarcodeException in case of error
     */
    protected function addPadding($size, $ncw)
    {
        if (($this->dmx->last_enc != Data::ENC_ASCII) && ($this->dmx->last_enc != Data::ENC_BASE256)) {
            // return to ASCII encodation before padding
            if ($this->dmx->last_enc == Data::ENC_EDF) {
                $this->cdw[] = 124;
            } else {
                $this->cdw[] = 254;
            }
            ++$ncw;
        }
        if ($size > $ncw) {
            // add first pad
            $this->cdw[] = 129;
            ++$ncw;
            // add remaining pads
            for ($i = $ncw; $i < $size; ++$i) {
                $this->cdw[] = $this->dmx->get253StateCodeword(129, $i);
            }
        }
    }

    /**
     * Get the codewords
     *
     * @return array params
     *
     * @throws BarcodeException in case of error
     */
    protected function getCodewords()
    {
        if (strlen((string)$this->code) == 0) {
            throw new BarcodeException('Empty input');
        }

        // get data codewords
        $this->cdw = $this->getHighLevelEncoding($this->code);

        // number of data codewords
        $ncw = count($this->cdw);
        
        // check size
        if ($ncw > 1560) {
            throw new BarcodeException('the input is too large to fit the barcode');
        }
        
        // get minimum required matrix size.
        foreach (Data::$symbattr[$this->shape] as $params) {
            if ($params[11] >= $ncw) {
                break;
            }
        }
        if ($params[11] > $ncw) {
            $this->addPadding($params[11], $ncw);
        }

        $errorCorrection = new \Com\Tecnick\Barcode\Type\Square\Datamatrix\ErrorCorrection;
        $this->cdw = $errorCorrection->getErrorCorrection($this->cdw, $params[13], $params[14], $params[15]);

        return $params;
    }

    /**
     * Set the grid
     *
     * @param int $idx
     * @param array $places
     * @param int $row
     * @param int $col
     * @param int $rdx
     * @param int $cdx
     * @param int $rdri
     * @param int $rdci
     */
    protected function setGrid(&$idx, &$places, &$row, &$col, &$rdx, &$cdx, &$rdri, &$rdci)
    {
        // braw bits by case
        if ($rdx == 0) {
            // top finder pattern
            $this->grid[$row][$col] = intval(($cdx % 2) == 0);
        } elseif ($rdx == $rdri) {
            // bottom finder pattern
            $this->grid[$row][$col] = 1;
        } elseif ($cdx == 0) {
            // left finder pattern
            $this->grid[$row][$col] = 1;
        } elseif ($cdx == $rdci) {
            // right finder pattern
            $this->grid[$row][$col] = intval(($rdx % 2) > 0);
        } else {
            // data bit
            if ($places[$idx] < 2) {
                $this->grid[$row][$col] = $places[$idx];
            } else {
                // codeword ID
                $cdw_id = (floor($places[$idx] / 10) - 1);
                // codeword BIT mask
                $cdw_bit = pow(2, (8 - ($places[$idx] % 10)));
                $this->grid[$row][$col] = (($this->cdw[$cdw_id] & $cdw_bit) == 0) ? 0 : 1;
            }
            ++$idx;
        }
    }

    /**
     * Get high level encoding using the minimum symbol data characters for ECC 200
     *
     * @param $data (string) data to encode
     *
     * @return array of codewords
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function getHighLevelEncoding($data)
    {
        // STEP A. Start in ASCII encodation.
        $enc = Data::ENC_ASCII; // current encoding mode
        $this->dmx->last_enc = $enc; // last used encoding
        $pos = 0; // current position
        $cdw = array(); // array of codewords to be returned
        $cdw_num = 0; // number of data codewords
        $data_length = strlen($data); // number of chars
        while ($pos < $data_length) {
            // Determine if current char is FNC1 (don't encode it, just pass it through)
            if ($this->gsonemode && ($data[$pos] == chr(232))) {
                $cdw[] = 232;
                ++$pos;
                ++$cdw_num;
                continue;
            }
            switch ($enc) {
                case Data::ENC_ASCII:
                    // STEP B. While in ASCII encodation
                    $this->dmx->encodeASCII($cdw, $cdw_num, $pos, $data_length, $data, $enc);
                    break;
                case Data::ENC_C40:
                    // Upper-case alphanumeric
                case Data::ENC_TXT:
                    // Lower-case alphanumeric
                case Data::ENC_X12:
                    // ANSI X12
                    $this->dmx->encodeTXT($cdw, $cdw_num, $pos, $data_length, $data, $enc);
                    break;
                case Data::ENC_EDF:
                    // F. While in EDIFACT (EDF) encodation
                    $this->dmx->encodeEDF($cdw, $cdw_num, $pos, $data_length, $field_length, $data, $enc);
                    break;
                case Data::ENC_BASE256:
                    // G. While in Base 256 (B256) encodation
                    $this->dmx->encodeBase256($cdw, $cdw_num, $pos, $data_length, $field_length, $data, $enc);
                    break;
            }
            $this->dmx->last_enc = $enc;
        }
        return $cdw;
    }

    /**
     * Get the bars array
     *
     * @throws BarcodeException in case of error
     */
    protected function setBars()
    {
        $this->dmx = new Encode($this->shape);
        $params = $this->getCodewords();
        // initialize empty arrays
        $this->grid = array_fill(0, ($params[2] * $params[3]), 0);
        // get placement map
        $places = $this->dmx->getPlacementMap($params[2], $params[3]);
        // fill the grid with data
        $this->grid = array();
        $idx = 0;
        // region data row max index
        $rdri = ($params[4] - 1);
        // region data column max index
        $rdci = ($params[5] - 1);
        // for each horizontal region
        for ($hr = 0; $hr < $params[8]; ++$hr) {
            // for each row on region
            for ($rdx = 0; $rdx < $params[4]; ++$rdx) {
                // get row
                $row = (($hr * $params[4]) + $rdx);
                // for each vertical region
                for ($vr = 0; $vr < $params[9]; ++$vr) {
                    // for each column on region
                    for ($cdx = 0; $cdx < $params[5]; ++$cdx) {
                        // get column
                        $col = (($vr * $params[5]) + $cdx);
                        $this->setGrid($idx, $places, $row, $col, $rdx, $cdx, $rdri, $rdci);
                    }
                }
            }
        }
        $this->processBinarySequence($this->grid);
    }
}
