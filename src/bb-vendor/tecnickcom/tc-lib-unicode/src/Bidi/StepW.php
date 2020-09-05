<?php
/**
 * StepW.php
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

use \Com\Tecnick\Unicode\Data\Constant as UniConstant;

/**
 * Com\Tecnick\Unicode\Bidi\StepW
 *
 * @since       2015-07-13
 * @category    Library
 * @package     Unicode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-unicode
 */
class StepW extends \Com\Tecnick\Unicode\Bidi\StepBase
{
    /**
     * Process W steps
     * Resolving Weak Types
     */
    protected function process()
    {
        $this->processStep('processW1');
        $this->processStep('processW2');
        $this->processStep('processW3');
        $this->processStep('processW4');
        $this->processStep('processW5');
        $this->processStep('processW6');
        $this->processStep('processW7');
    }

    /**
     * W1. Examine each nonspacing mark (NSM) in the isolating run sequence, and
     *     change the type of the NSM to Other Neutral if the previous character is an isolate initiator or PDI, and
     *     to the type of the previous character otherwise.
     *     If the NSM is at the start of the isolating run sequence, it will get the type of sos.
     *     (Note that in an isolating run sequence, an isolate initiator followed by an NSM or any type
     *     other than PDI must be an overflow isolate initiator.)
     *
     * @param int $idx Current character position
     */
    protected function processW1($idx)
    {
        if ($this->seq['item'][$idx]['type'] == 'NSM') {
            $jdx = ($idx - 1);
            if ($jdx < 0) {
                $this->seq['item'][$idx]['type'] = $this->seq['sos'];
            } elseif (($this->seq['item'][$jdx]['char'] >= UniConstant::LRI)
                && ($this->seq['item'][$jdx]['char'] <= UniConstant::PDI)
            ) {
                $this->seq['item'][$idx]['type'] = 'ON';
            } else {
                $this->seq['item'][$idx]['type'] = $this->seq['item'][$jdx]['type'];
            }
        }
    }

    /**
     * W2. Search backward from each instance of a European number until the first strong type (R, L, AL, or sos)
     *     is found. If an AL is found, change the type of the European number to Arabic number.
     *
     * @param int $idx Current character position
     */
    protected function processW2($idx)
    {
        if ($this->seq['item'][$idx]['type'] == 'EN') {
            $jdx = ($idx - 1);
            while ($jdx >= 0) {
                if ($this->seq['item'][$jdx]['type'] == 'AL') {
                    $this->seq['item'][$idx]['type'] = 'AN';
                    break;
                } elseif (in_array($this->seq['item'][$jdx]['type'], array('R','L'))) {
                    break;
                }
                --$jdx;
            }
        }
    }

    /**
     * W3. Change all ALs to R.
     *
     * @param int $idx Current character position
     */
    protected function processW3($idx)
    {
        if ($this->seq['item'][$idx]['type'] == 'AL') {
            $this->seq['item'][$idx]['type'] = 'R';
        }
    }

    /**
     * W4. A single European separator between two European numbers changes to a European number.
     *     A single common separator between two numbers of the same type changes to that type.
     *
     * @param int $idx Current character position
     */
    protected function processW4($idx)
    {
        if (in_array($this->seq['item'][$idx]['type'], array('ES','CS'))) {
            $bdx = ($idx - 1);
            $fdx = ($idx + 1);
            if (($bdx >= 0)
                && ($fdx < $this->seq['length'])
                && ($this->seq['item'][$bdx]['type'] == $this->seq['item'][$fdx]['type'])
            ) {
                if (in_array($this->seq['item'][$bdx]['type'], array('EN','AN'))) {
                    $this->seq['item'][$idx]['type'] = $this->seq['item'][$bdx]['type'];
                }
            }
        }
    }

    /**
     * W5. A sequence of European terminators adjacent to European numbers changes to all European numbers.
     *
     * @param int $idx Current character position
     */
    protected function processW5($idx)
    {
        if ($this->seq['item'][$idx]['type'] == 'ET') {
            $this->processW5a($idx);
            $this->processW5b($idx);
        }
    }

    /**
     * W5a
     *
     * @param int $idx Current character position
     */
    protected function processW5a($idx)
    {
        for ($jdx = ($idx - 1); $jdx >= 0; --$jdx) {
            if ($this->seq['item'][$jdx]['type'] == 'EN') {
                $this->seq['item'][$idx]['type'] = 'EN';
            } else {
                break;
            }
        }
    }

    /**
     * W5b
     *
     * @param int $idx Current character position
     */
    protected function processW5b($idx)
    {
        if ($this->seq['item'][$idx]['type'] == 'ET') {
            for ($jdx = ($idx + 1); $jdx < $this->seq['length']; ++$jdx) {
                if ($this->seq['item'][$jdx]['type'] == 'EN') {
                    $this->seq['item'][$idx]['type'] = 'EN';
                } elseif ($this->seq['item'][$jdx]['type'] != 'ET') {
                    break;
                }
            }
        }
    }

    /**
     * W6. Otherwise, separators and terminators change to Other Neutral.
     *
     * @param int $idx Current character position
     */
    protected function processW6($idx)
    {
        if (in_array($this->seq['item'][$idx]['type'], array('ET','ES','CS','ON'))) {
            $this->seq['item'][$idx]['type'] = 'ON';
        }
    }

    /**
     * W7. Search backward from each instance of a European number until the first strong type (R, L, or sos) is found.
     *     If an L is found, then change the type of the European number to L.
     *
     * @param int $idx Current character position
     */
    protected function processW7($idx)
    {
        if ($this->seq['item'][$idx]['type'] == 'EN') {
            for ($jdx = ($idx - 1); $jdx >= 0; --$jdx) {
                if ($this->seq['item'][$jdx]['type'] == 'L') {
                    $this->seq['item'][$idx]['type'] = 'L';
                    break;
                } elseif ($this->seq['item'][$jdx]['type'] == 'R') {
                    break;
                }
            }
            if (($this->seq['sos'] == 'L') && ($jdx < 0)) {
                $this->seq['item'][$idx]['type'] = 'L';
            }
        }
    }
}
