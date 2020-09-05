<?php
/**
 * StepI.php
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

/**
 * Com\Tecnick\Unicode\Bidi\StepI
 *
 * @since       2015-07-13
 * @category    Library
 * @package     Unicode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-unicode
 */
class StepI extends \Com\Tecnick\Unicode\Bidi\StepBase
{
    /**
     * Process I steps
     */
    protected function process()
    {
        $this->seq['maxlevel'] = 0;
        $this->processStep('processI');
    }

    /**
     * I1. For all characters with an even (left-to-right) embedding level, those of type R go up one level and those
     *     of type AN or EN go up two levels.
     * I2. For all characters with an odd (right-to-left) embedding level, those of type L, EN or AN go up one level.
     *
     * @param int $idx Current character position
     */
    protected function processI($idx)
    {
        $odd = ($this->seq['item'][$idx]['level'] % 2);
        if ($odd) {
            if (($this->seq['item'][$idx]['type'] == 'L')
                || ($this->seq['item'][$idx]['type'] == 'EN')
                || ($this->seq['item'][$idx]['type'] == 'AN')
            ) {
                $this->seq['item'][$idx]['level'] += 1;
            }
        } else {
            if ($this->seq['item'][$idx]['type'] == 'R') {
                $this->seq['item'][$idx]['level'] += 1;
            } elseif (($this->seq['item'][$idx]['type'] == 'AN')
                || ($this->seq['item'][$idx]['type'] == 'EN')
            ) {
                $this->seq['item'][$idx]['level'] += 2;
            }
        }
        // update the maximum level
        $this->seq['maxlevel'] = max($this->seq['maxlevel'], $this->seq['item'][$idx]['level']);
    }
}
