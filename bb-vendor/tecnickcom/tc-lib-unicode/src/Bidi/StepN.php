<?php
/**
 * StepN.php
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

use \Com\Tecnick\Unicode\Data\Bracket as UniBracket;

/**
 * Com\Tecnick\Unicode\Bidi\StepN
 *
 * @since       2015-07-13
 * @category    Library
 * @package     Unicode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-unicode
 */
class StepN extends \Com\Tecnick\Unicode\Bidi\StepBase
{
    /**
     * List or bracket pairs positions
     *
     * @var array
     */
    protected $brackets= array();

    /**
     * Stack used to store bracket positions
     *
     * @var array
     */
    protected $bstack= array();

    /**
     * Process N steps
     * Resolving Neutral and Isolate Formatting Types
     *
     * Neutral and isolate formatting (i.e. NI) characters are resolved one isolating run sequence at a time.
     * Its results are that all NIs become either R or L. Generally, NIs take on the direction of the surrounding text.
     * In case of a conflict, they take on the embedding direction.
     * At isolating run sequence boundaries where the type of the character on the other side of the boundary
     * is required, the type assigned to sos or eos is used.
     *
     * Bracket pairs within an isolating run sequence are processed as units so that both the opening and the closing
     * paired bracket in a pair resolve to the same direction. Note that this rule is applied based on the current
     * bidirectional character type of each paired bracket and not the original type, as this could have changed under
     * X6. The current bidirectional character type may also have changed under a previous iteration of the for loop in
     * N0 in the case of nested bracket pairs.
     */
    protected function process()
    {
        $this->processStep('getBracketPairs');
        $this->processN0();
        $this->processStep('processN1');
        $this->processStep('processN2');
    }

    /**
     * BD16. Find all bracket pairs
     */
    protected function getBracketPairs($idx)
    {
        $char = $this->seq['item'][$idx]['char'];
        if (isset(UniBracket::$open[$char])) {
            // process open bracket
            if ($char == 0x3008) {
                $char = 0x2329;
            }
            $this->bstack[] = array($idx, $char);
        } elseif (isset(UniBracket::$close[$char])) {
            // process closign bracket
            if ($char == 0x3009) {
                $char = 0x232A;
            }
            // find matching opening bracket
            $tmpstack = $this->bstack;
            while (!empty($tmpstack)) {
                $item = array_pop($tmpstack);
                if ($char == UniBracket::$open[$item[1]]) {
                    $this->brackets[$item[0]] = $idx;
                    $this->bstack = $tmpstack;
                }
            }
        }
        // Sort the list of pairs of text positions in ascending order
        // based on the text position of the opening paired bracket.
        ksort($this->brackets);
    }

    /**
     * Return the normalized chat type for the N0 step
     * Within this scope, bidirectional types EN and AN are treated as R.
     *
     * @param string $type Char type
     *
     * @return string
     */
    protected function getN0Type($type)
    {
        return ((($type == 'AN') || ($type == 'EN')) ? 'R' : $type);
    }

    /**
     * N0. Process bracket pairs in an isolating run sequence sequentially in the logical order of the text positions
     *     of the opening paired brackets.
     */
    protected function processN0()
    {
        $odir = (($this->seq['edir'] == 'L') ? 'R' : 'L');
        // For each bracket-pair element in the list of pairs of text positions
        foreach ($this->brackets as $open => $close) {
            if ($this->processInsideBrackets($open, $close, $odir)) {
                for ($jdx = ($open - 1); $jdx >= 0; --$jdx) {
                    $btype = $this->getN0Type($this->seq['item'][$jdx]['type']);
                    if ($btype == $odir) {
                        // 1. If the preceding strong type is also opposite the embedding direction,
                        //    context is established, so set the type for both brackets in the pair to that direction.
                        $this->setBracketsType($open, $close, $odir);
                        break;
                    } elseif ($btype == $this->seq['edir']) {
                        // 2. Otherwise set the type for both brackets in the pair to the embedding direction.
                        $this->setBracketsType($open, $close, $this->seq['edir']);
                        break;
                    }
                }
                if ($jdx < 0) {
                    $this->setBracketsType($open, $close, $this->seq['sos']);
                }
            }
            // d. Otherwise, there are no strong types within the bracket pair. Therefore, do not set the type for that
            //    bracket pair. Note that if the enclosed text contains no strong types the bracket pairs will both
            //    resolve to the same level when resolved individually using rules N1 and N2.
        }
    }

    /**
     * Inspect the bidirectional types of the characters enclosed within the bracket pair.
     *
     * @param int    $open  Open bracket entry
     * @param int    $close Close bracket entry
     * @param string $odir  Opposite direction (L or R)
     *
     * @return bool True if type has not been found
     */
    protected function processInsideBrackets($open, $close, $odir)
    {
        $opposite = false;
        // a. Inspect the bidirectional types of the characters enclosed within the bracket pair.
        for ($jdx = ($open + 1); $jdx < $close; ++$jdx) {
            $btype = $this->getN0Type($this->seq['item'][$jdx]['type']);
            // b. If any strong type (either L or R) matching the embedding direction is found,
            // set the type for both brackets in the pair to match the embedding direction.
            if ($btype == $this->seq['edir']) {
                $this->setBracketsType($open, $close, $this->seq['edir']);
                break;
            } elseif ($btype == $odir) {
                // c. Otherwise, if there is a strong type it must be opposite the embedding direction.
                $opposite = true;
            }
        }
        // Therefore, test for an established context with a preceding strong type by checking backwards before
        // the opening paired bracket until the first strong type (L, R, or sos) is found.
        return (($jdx == $close) && $opposite);
    }

    /**
     * Set the brackets type
     *
     * @param int    $open  Open bracket entry
     * @param int    $close Close bracket entry
     * @param string $type  Type
     *
     * @return bool True if type has not been found
     */
    protected function setBracketsType($open, $close, $type)
    {
        $this->seq['item'][$open]['type'] = $type;
        $this->seq['item'][$close]['type'] = $type;

        // Any number of characters that had original bidirectional character type NSM
        // prior to the application of W1 that immediately follow a paired bracket which
        // changed to L or R under N0 should change to match the type of their preceding bracket.
        $next = ($close + 1);
        while (isset($this->seq['item'][$next]['otype']) && ($this->seq['item'][$next]['otype'] == 'NSM')) {
            $this->seq['item'][$next]['type'] = $type;
            ++$next;
        }
    }

    /**
     * N1. A sequence of NIs takes the direction of the surrounding strong text if the text on both sides has the same
     *     direction. European and Arabic numbers act as if they were R in terms of their influence on NIs.
     *     The start-of-sequence (sos) and end-of-sequence (eos) types are used at isolating run sequence boundaries.
     *
     * @param int $idx Current character position
     */
    protected function processN1($idx)
    {
        if ($this->seq['item'][$idx]['type'] == 'NI') {
            $bdx = ($idx - 1);
            $prev = $this->processN1prev($bdx);
            if (empty($prev)) {
                return;
            }
            $jdx = $this->getNextN1Char($idx);
            $next = $this->processN1next($jdx);
            if (empty($next)) {
                return;
            }
            if ($next == $prev) {
                for ($bdx = $idx; (($bdx < $jdx) && ($bdx < $this->seq['length'])); ++$bdx) {
                    $this->seq['item'][$bdx]['type'] = $next;
                }
            }
        }
    }

    /**
     * Get the next direction
     *
     * @param int $bdx Position of the preceding character
     *
     * @return string Previous position
     */
    protected function processN1prev(&$bdx)
    {
        if ($bdx < 0) {
            $bdx = 0;
            return $this->seq['sos'];
        }
        if (in_array($this->seq['item'][$bdx]['type'], array('R','AN','EN'))) {
            return 'R';
        }
        if ($this->seq['item'][$bdx]['type'] == 'L') {
            return 'L';
        }
        return '';
    }

    /**
     * Get the next direction
     *
     * @param int $jdx Position of the next character
     *
     * @return string Previous position
     */
    protected function processN1next(&$jdx)
    {
        if ($jdx >= $this->seq['length']) {
            $jdx = $this->seq['length'];
            return $this->seq['eos'];
        }
        if (in_array($this->seq['item'][$jdx]['type'], array('R','AN','EN'))) {
            return 'R';
        }
        if ($this->seq['item'][$jdx]['type'] == 'L') {
            return 'L';
        }
        return '';
    }

    /**
     * Return the index of the next valid char for N1
     *
     * @param int $idx Start index
     *
     * @return int
     */
    protected function getNextN1Char($idx)
    {
        $jdx = ($idx + 1);
        while (($jdx < $this->seq['length']) && ($this->seq['item'][$jdx]['type'] == 'NI')) {
            ++$jdx;
        }
        return $jdx;
    }

    /**
     * N2. Any remaining NIs take the embedding direction.
     *
     * @param int $idx Current character position
     */
    protected function processN2($idx)
    {
        if ($this->seq['item'][$idx]['type'] == 'NI') {
            $this->seq['item'][$idx]['type'] = $this->seq['edir'];
        }
    }
}
