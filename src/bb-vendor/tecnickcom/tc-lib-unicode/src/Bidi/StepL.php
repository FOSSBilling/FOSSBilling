<?php
/**
 * StepL.php
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

namespace Com\Tecnick\Unicode\Bidi;

use \Com\Tecnick\Unicode\Data\Mirror as UniMirror;
use \Com\Tecnick\Unicode\Data\Constant as UniConstant;

/**
 * Com\Tecnick\Unicode\Bidi\StepL
 *
 * @since       2015-07-13
 * @category    Library
 * @package     Unicode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-unicode
 */
class StepL
{
    /**
     * Array of characters data to return
     *
     * @var array
     */
    protected $chardata = array();

    /**
     * Number of characters in $this->chardata
     *
     * @var int
     */
    protected $numchars = 0;

    /**
     * Paragraph embedding level
     *
     * @var int
     */
    protected $pel = 0;

    /**
     * Maximul level
     *
     * @var int
     */
    protected $maxlevel = 0;

    /**
     * L steps
     *
     * @param array $chardata Array of characters data
     * @param int   $pel      Paragraph embedding level
     */
    public function __construct($chardata, $pel, $maxlevel)
    {
        // reorder chars by their original position
        usort($chardata, function ($apos, $bpos) {
            return ($apos['pos'] - $bpos['pos']);
        });
        $this->chardata = $chardata;
        $this->numchars = count($this->chardata);
        $this->pel = $pel;
        $this->maxlevel = $maxlevel;
        $this->processL1();
        $this->processL2();
    }

    /**
     * Returns the processed array
     *
     * @return array
     */
    public function getChrData()
    {
        return $this->chardata;
    }

    /**
     * L1. On each line, reset the embedding level of the following characters to the paragraph embedding level:
     *     1. Segment separators,
     *     2. Paragraph separators,
     *     3. Any sequence of whitespace characters and/or isolate formatting characters (FSI, LRI, RLI, and PDI)
     *        preceding a segment separator or paragraph separator, and
     *     4. Any sequence of whitespace characters and/or isolate formatting characters (FSI, LRI, RLI, and PDI)
     *        at the end of the line.
     */
    protected function processL1()
    {
        for ($idx = 0; $idx < $this->numchars; ++$idx) {
            $this->processL1b($idx, $idx);
        }
    }

    /**
     * Internal L1 step
     *
     * @param int $idx Main character index
     * @param int $jdx Current index
     */
    protected function processL1b($idx, $jdx)
    {
        if ($jdx >= $this->numchars) {
            return;
        }
        if ((($this->chardata[$jdx]['otype'] == 'S') || ($this->chardata[$jdx]['otype'] == 'B'))
            || (($jdx == ($this->numchars - 1)) && ($this->chardata[$jdx]['otype'] == 'WS'))
        ) {
            $this->chardata[$idx]['level'] = $this->pel;
            return;
        } elseif (($this->chardata[$jdx]['otype'] != 'WS')
            && (($this->chardata[$idx]['char'] < UniConstant::LRI)
            || ($this->chardata[$idx]['char'] > UniConstant::PDI))
        ) {
            return $this->processL1b($idx, ($jdx + 1));
        }
    }

    /**
     * L2. From the highest level found in the text to the lowest odd level on each line,
     *     including intermediate levels not actually present in the text,
     *     reverse any contiguous sequence of characters that are at that level or higher.
     *     This rule reverses a progressively larger series of substrings.
     */
    protected function processL2()
    {
        for ($level = $this->maxlevel; $level > 0; --$level) {
            $ordered = array();
            $reversed = array();
            foreach ($this->chardata as $char) {
                if ($char['level'] >= $level) {
                    if (($char['type'] == 'R') && (isset(UniMirror::$uni[$char['char']]))) {
                        // L4. A character is depicted by a mirrored glyph if and only if
                        //     (a) the resolved directionality of that character is R, and
                        //     (b) the Bidi_Mirrored property value of that character is true.
                        $char['char'] = UniMirror::$uni[$char['char']];
                    }
                    $reversed[] = $char;
                } else {
                    if (!empty($reversed)) {
                        $ordered = array_merge($ordered, array_reverse($reversed));
                        $reversed = array();
                    }
                    $ordered[] = $char;
                }
            }
            if (!empty($reversed)) {
                $ordered = array_merge($ordered, array_reverse($reversed));
                $reversed = array();
            }
            $this->chardata = $ordered;
        }
    }
}
