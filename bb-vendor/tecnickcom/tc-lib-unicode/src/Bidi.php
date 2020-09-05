<?php
/**
 * Bidi.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     Unicode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-unicode
 *
 * This file is part of tc-lib-unicode software library.
 */

namespace Com\Tecnick\Unicode;

use \Com\Tecnick\Unicode\Exception as UnicodeException;

use \Com\Tecnick\Unicode\Convert;
use \Com\Tecnick\Unicode\Bidi\StepP;
use \Com\Tecnick\Unicode\Bidi\StepX;
use \Com\Tecnick\Unicode\Bidi\StepXten;
use \Com\Tecnick\Unicode\Bidi\StepW;
use \Com\Tecnick\Unicode\Bidi\StepN;
use \Com\Tecnick\Unicode\Bidi\StepI;
use \Com\Tecnick\Unicode\Bidi\Shaping;
use \Com\Tecnick\Unicode\Bidi\StepL;
use \Com\Tecnick\Unicode\Data\Pattern as UniPattern;
use \Com\Tecnick\Unicode\Data\Type as UniType;
use \Com\Tecnick\Unicode\Data\Constant as UniConstant;

/**
 * Com\Tecnick\Unicode\Bidi
 *
 * @since       2015-07-13
 * @category    Library
 * @package     Unicode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-unicode
 */
class Bidi
{
    /**
     * String to process
     *
     * @var string
     */
    protected $str = '';

    /**
     * Array of UTF-8 chars
     *
     * @var array
     */
    protected $chrarr = array();

    /**
     * Array of UTF-8 codepoints
     *
     * @var array
     */
    protected $ordarr = array();

    /**
     * Processed string
     *
     * @var string
     */
    protected $bidistr = '';

    /**
     * Array of processed UTF-8 chars
     *
     * @var array
     */
    protected $bidichrarr = array();

    /**
     * Array of processed UTF-8 codepoints
     *
     * @var array
     */
    protected $bidiordarr = array();

    /**
     * If true force processign the string in RTL mode
     *
     * @var bool
     */
    protected $forcertl = false;

    /**
     * If true enable shaping
     *
     * @var bool
     */
    protected $shaping = true;

    /**
     * True if the string contains arabic characters
     *
     * @var bool
     */
    protected $arabic = false;

    /**
     * Array of character data
     *
     * @var array
     */
    protected $chardata = array();

    /**
     * Convert object
     *
     * @var Convert
     */
    protected $conv;

    /**
     * Reverse the RLT substrings using the Bidirectional Algorithm
     * http://unicode.org/reports/tr9/
     *
     * @param string $str      String to convert (if null it will be generated from $chrarr or $ordarr)
     * @param array  $chrarr   Array of UTF-8 chars (if empty it will be generated from $str or $ordarr)
     * @param array  $ordarr   Array of UTF-8 codepoints (if empty it will be generated from $str or $chrarr)
     * @param mixed  $forcertl If 'R' forces RTL, if 'L' forces LTR
     * @param bool   $shaping  If true enable the shaping algorithm
     */
    public function __construct($str = null, $chrarr = null, $ordarr = null, $forcertl = false, $shaping = true)
    {
        if (($str === null) && empty($chrarr) && empty($ordarr)) {
            throw new UnicodeException('empty input');
        }
        $this->conv = new Convert();
        $this->setInput($str, $chrarr, $ordarr, $forcertl);

        if (!$this->isRtlMode()) {
            $this->bidistr = $this->str;
            $this->bidichrarr = $this->chrarr;
            $this->bidiordarr = $this->ordarr;
            return;
        }

        $this->shaping = ($shaping && $this->arabic);

        $this->process();
    }
    

    /**
     * Set Input data
     *
     * @param string $str      String to convert (if null it will be generated from $chrarr or $ordarr)
     * @param array  $chrarr   Array of UTF-8 chars (if empty it will be generated from $str or $ordarr)
     * @param array  $ordarr   Array of UTF-8 codepoints (if empty it will be generated from $str or $chrarr)
     * @param mixed  $forcertl If 'R' forces RTL, if 'L' forces LTR
     */
    protected function setInput($str = null, $chrarr = null, $ordarr = null, $forcertl = false)
    {
        if ($str === null) {
            if (empty($chrarr)) {
                $chrarr = $this->conv->ordArrToChrArr($ordarr);
            }
            $str = implode($chrarr);
        }
        if (empty($chrarr)) {
            $chrarr = $this->conv->strToChrArr($str);
        }
        if (empty($ordarr)) {
            $ordarr = $this->conv->chrArrToOrdArr($chrarr);
        }
        $this->str = $str;
        $this->chrarr = $chrarr;
        $this->ordarr = $ordarr;
        $this->forcertl = (is_string($forcertl) ? strtoupper($forcertl[0]) : false);
    }

    /**
     * Returns the processed array of UTF-8 codepoints
     *
     * @return array
     */
    public function getOrdArray()
    {
        return $this->bidiordarr;
    }

    /**
     * Returns the processed array of UTF-8 chars
     *
     * @return array
     */
    public function getChrArray()
    {
        if (empty($this->bidichrarr)) {
            $this->bidichrarr = $this->conv->ordArrToChrArr($this->bidiordarr);
        }
        return $this->bidichrarr;
    }

    /**
     * Returns the number of characters in the processed string
     *
     * @return int
     */
    public function getNumChars()
    {
        return count($this->getChrArray());
    }

    /**
     * Returns the processed string
     *
     * @return string
     */
    public function getString()
    {
        if (empty($this->bidistr)) {
            $this->bidistr = implode($this->getChrArray());
        }
        return $this->bidistr;
    }

    /**
     * Returns an array with processed chars as keys
     *
     * @return array
     */
    public function getCharKeys()
    {
        return array_fill_keys(array_values($this->bidiordarr), true);
    }

    /**
     * P1. Split the text into separate paragraphs.
     *     A paragraph separator is kept with the previous paragraph.
     *
     * @return array
     */
    protected function getParagraphs()
    {
        
        $paragraph = array(0 => array());
        $pdx = 0; // paragraphs index
        foreach ($this->ordarr as $ord) {
            $paragraph[$pdx][] = $ord;
            if (isset(UniType::$uni[$ord]) && (UniType::$uni[$ord] == 'B')) {
                ++$pdx;
                $paragraph[$pdx] = array();
            }
        }
        return $paragraph;
    }

    /**
     * Process the string
     */
    protected function process()
    {
        // split the text into separate paragraphs.
        $paragraph = $this->getParagraphs();

        // Within each paragraph, apply all the other rules of this algorithm.
        foreach ($paragraph as $par) {
            $pel = $this->getPel($par);
            $stepx = new StepX($par, $pel);
            $stepx10 = new StepXten($stepx->getChrData(), $pel);
            $ilrs = $stepx10->getIsolatedLevelRunSequences();
            $chardata = array();
            foreach ($ilrs as $seq) {
                $stepw = new StepW($seq);
                $stepn = new StepN($stepw->getSequence());
                $stepi = new StepI($stepn->getSequence());
                $seq = $stepi->getSequence();
                if ($this->shaping) {
                    $shaping = new Shaping($seq);
                    $seq = $shaping->getSequence();
                }
                $chardata = array_merge($chardata, $seq['item']);
            }
            $stepl = new StepL($chardata, $pel, (isset($seq['maxlevel']) ? $seq['maxlevel'] : 0));
            $chardata = $stepl->getChrData();
            foreach ($chardata as $chd) {
                $this->bidiordarr[] = $chd['char'];
            }
            // add back the paragraph separators
            $lastchar = end($par);
            if (isset(UniType::$uni[$lastchar]) && (UniType::$uni[$lastchar] == 'B')) {
                $this->bidiordarr[] = $lastchar;
            }
        }
    }

    /**
     * Get the paragraph embedding level
     *
     * @param array $par Paragraph
     *
     * @return int
     */
    protected function getPel($par)
    {
        if ($this->forcertl === 'R') {
            return 1;
        }
        if ($this->forcertl === 'L') {
            return 0;
        }
        $stepp = new StepP($par);
        return $stepp->getPel();
    }

    /**
     * Check if the input string contains RTL characters to process
     *
     * @return boolean
     */
    protected function isRtlMode()
    {
        $this->arabic = preg_match(UniPattern::ARABIC, $this->str);
        return (($this->forcertl !== false) || $this->arabic || preg_match(UniPattern::RTL, $this->str));
    }
}
