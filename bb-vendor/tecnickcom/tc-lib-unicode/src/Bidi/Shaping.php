<?php
/**
 * Shaping.php
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
use \Com\Tecnick\Unicode\Data\Arabic as UniArabic;

/**
 * Com\Tecnick\Unicode\Bidi\Shaping
 *
 * @since       2015-07-13
 * @category    Library
 * @package     Unicode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-unicode
 */
class Shaping extends \Com\Tecnick\Unicode\Bidi\Shaping\Arabic
{
    /**
     * Sequence to process and return
     *
     * @var array
     */
    protected $seq = array();

    /**
     * Array of processed chars
     *
     * @var array
     */
    protected $newchardata = array();

    /**
     * Array of AL characters
     *
     * @var array
     */
    protected $alchars = array();

    /**
     * Number of AL characters
     *
     * @var int
     */
    protected $numalchars = 0;

    /**
     * Shaping
     * Cursively connected scripts, such as Arabic or Syriac,
     * require the selection of positional character shapes that depend on adjacent characters.
     * Shaping is logically applied after the Bidirectional Algorithm is used and is limited to
     * characters within the same directional run.
     *
     * @param array $seq isolated Sequence array
     */
    public function __construct($seq)
    {
        $this->seq = $seq;
        $this->newchardata = $seq['item'];
        $this->process();
    }

    /**
     * Returns the processed sequence
     *
     * @return array
     */
    public function getSequence()
    {
        return $this->seq;
    }

    /**
     * Process
     */
    protected function process()
    {
        $this->setAlChars();
        for ($idx = 0; $idx < $this->seq['length']; ++$idx) {
            if ($this->seq['item'][$idx]['otype'] == 'AL') {
                $thischar = $this->seq['item'][$idx];
                $pos = $thischar['x'];
                $prevchar = (($pos > 0) ? $this->alchars[($pos - 1)] : false);
                $nextchar = ((($pos + 1) < $this->numalchars) ? $this->alchars[($pos + 1)] : false);
                $this->processAlChar($idx, $pos, $prevchar, $thischar, $nextchar);
            }
        }
        $this->combineShadda();
        $this->removeDeletedChars();
        $this->seq['item'] = array_values($this->newchardata);
        unset($this->newchardata);
    }

    /**
     * Set AL chars array
     */
    protected function setAlChars()
    {
        $this->numalchars = 0;
        for ($idx = 0; $idx < $this->seq['length']; ++$idx) {
            if (($this->seq['item'][$idx]['otype'] == 'AL')
                || ($this->seq['item'][$idx]['char'] == UniConstant::SPACE)
                || ($this->seq['item'][$idx]['char'] == UniConstant::ZERO_WIDTH_NON_JOINER)
            ) {
                $this->alchars[$this->numalchars] = $this->seq['item'][$idx];
                $this->alchars[$this->numalchars]['i'] = $idx;
                $this->seq['item'][$idx]['x'] = $this->numalchars;
                ++$this->numalchars;
            }
        }
    }

    /**
     * Combine characters that can occur with Arabic Shadda (0651 HEX, 1617 DEC).
     * Putting the combining mark and shadda in the same glyph allows
     * to avoid the two marks overlapping each other in an illegible manner.
     */
    protected function combineShadda()
    {
        $last = ($this->seq['length'] - 1);
        for ($idx = 0; $idx < $last; ++$idx) {
            if (($this->newchardata[$idx]['char'] == UniArabic::SHADDA)
                && (isset(UniArabic::$diacritic[($this->newchardata[($idx + 1)]['char'])]))
            ) {
                $this->newchardata[$idx]['char'] = false;
                $this->newchardata[($idx + 1)]['char'] = UniArabic::$diacritic[
                    ($this->newchardata[($idx + 1)]['char'])
                ];
            }
        }
    }

    /**
     * Remove marked characters
     */
    protected function removeDeletedChars()
    {
        foreach ($this->newchardata as $key => $value) {
            if ($value['char'] === false) {
                unset($this->newchardata[$key]);
            }
        }
    }
}
