<?php
/**
 * Arabic.php
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

namespace Com\Tecnick\Unicode\Bidi\Shaping;

use \Com\Tecnick\Unicode\Data\Arabic as UniArabic;

/**
 * Com\Tecnick\Unicode\Bidi\Shaping\Arabic
 *
 * @since       2015-07-13
 * @category    Library
 * @package     Unicode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-unicode
 */
abstract class Arabic
{
    /**
     * Check if it is a LAA LETTER
     *
     * @return bool
     */
    protected function isLaaLetter($prevchar, $thischar)
    {
        if (($prevchar !== false)
            && ($prevchar['char'] == UniArabic::LAM)
            && (isset(UniArabic::$laa[$thischar['char']]))
        ) {
            return true;
        }
        return false;
    }

    /**
     * Check next char
     *
     * @param int       $thischar Current char
     * @param int|false $nextchar Next char
     *
     * @return bool
     */
    protected function hasNextChar($thischar, $nextchar)
    {
        return (($nextchar !== false)
            && (($nextchar['otype'] == 'AL') || ($nextchar['otype'] == 'NSM'))
            && ($nextchar['type'] == $thischar['type'])
            && ($nextchar['char'] != UniArabic::QUESTION_MARK)
        );
    }

    /**
     * Check previous char
     *
     * @param int|false $prevchar Previous char
     * @param int       $thischar Current char
     *
     * @return bool
     */
    protected function hasPrevChar($prevchar, $thischar)
    {
        return ((($prevchar !== false)
            && (($prevchar['otype'] == 'AL') || ($prevchar['otype'] == 'NSM'))
            && ($prevchar['type'] == $thischar['type']))
        );
    }

    /**
     * Check if it is a middle character
     *
     * @param int|false $prevchar Previous char
     * @param int       $thischar Current char
     * @param int|false $nextchar Next char
     *
     * @return bool
     */
    protected function isMiddleChar($prevchar, $thischar, $nextchar)
    {
        return ($this->hasPrevChar($prevchar, $thischar) && $this->hasNextChar($thischar, $nextchar));
    }

    /**
     * Check if it is a final character
     *
     * @param int|false $prevchar Previous char
     * @param int       $thischar Current char
     * @param int|false $nextchar Next char
     *
     * @return bool
     */
    protected function isFinalChar($prevchar, $thischar, $nextchar)
    {
        return ($this->hasPrevChar($prevchar, $thischar)
            || (($nextchar !== false) && ($nextchar['char'] == UniArabic::QUESTION_MARK))
        );
    }
    
    /**
     * Set initial or middle char
     *
     * @param int       $idx       Current index
     * @param int|false $prevchar Previous char
     * @param int       $thischar Current char
     * @param array     $arabicarr Substitution array
     */
    protected function setMiddleChar($idx, $prevchar, $thischar, $arabicarr)
    {
        if (in_array($prevchar['char'], UniArabic::$end)) {
            if (isset($arabicarr[$thischar['char']][2])) {
                // initial
                $this->newchardata[$idx]['char'] = $arabicarr[$thischar['char']][2];
            }
        } else {
            if (isset($arabicarr[$thischar['char']][3])) {
                // medial
                $this->newchardata[$idx]['char'] = $arabicarr[$thischar['char']][3];
            }
        }
    }
    
    /**
     * Set initial char
     *
     * @param int       $idx       Current index
     * @param int       $thischar Current char
     * @param array     $arabicarr Substitution array
     */
    protected function setInitialChar($idx, $thischar, $arabicarr)
    {
        if (isset($arabicarr[$this->seq['item'][$idx]['char']][2])) {
            $this->newchardata[$idx]['char'] = $arabicarr[$thischar['char']][2];
        }
    }
    
    /**
     * Set final char
     *
     * @param int       $idx       Current index
     * @param int|false $prevchar  Previous char
     * @param int       $thischar  Current char
     * @param array     $arabicarr Substitution array
     */
    protected function setFinalChar($idx, $prevchar, $thischar, $arabicarr)
    {
        if (($idx > 1)
            && ($thischar['char'] == UniArabic::HEH)
            && ($this->seq['item'][($idx - 1)]['char'] == UniArabic::LAM)
            && ($this->seq['item'][($idx - 2)]['char'] == UniArabic::LAM)
        ) {
            // Allah Word
            $this->newchardata[($idx - 2)]['char'] = false;
            $this->newchardata[($idx - 1)]['char'] = false;
            $this->newchardata[$idx]['char'] = UniArabic::LIGATURE_ALLAH_ISOLATED_FORM;
        } else {
            if (($prevchar !== false) && in_array($prevchar['char'], UniArabic::$end)) {
                if (isset($arabicarr[$thischar['char']][0])) {
                    // isolated
                    $this->newchardata[$idx]['char'] = $arabicarr[$thischar['char']][0];
                }
            } else {
                if (isset($arabicarr[$thischar['char']][1])) {
                    // final
                    $this->newchardata[$idx]['char'] = $arabicarr[$thischar['char']][1];
                }
            }
        }
    }

    /**
     * Process AL character
     *
     * @param int       $idx      Current index
     * @param int       $pos      Current char position
     * @param int|false $prevchar Previous char
     * @param int       $thischar Current char
     * @param int|false $nextchar Next char
     */
    protected function processAlChar($idx, $pos, $prevchar, $thischar, $nextchar)
    {
        $laaletter = $this->isLaaLetter($prevchar, $thischar);
        if ($laaletter) {
            $arabicarr = UniArabic::$laa;
            $prevchar = (($pos > 1) ? $this->alchars[($pos - 2)] : false);
        } else {
            $arabicarr = UniArabic::$substitute;
        }

        if ($this->isMiddleChar($prevchar, $thischar, $nextchar)) {
            $this->setMiddleChar($idx, $prevchar, $thischar, $arabicarr);
        } elseif ($this->hasNextChar($thischar, $nextchar)) {
            $this->setInitialChar($idx, $thischar, $arabicarr);
        } elseif ($this->isFinalChar($prevchar, $thischar, $nextchar)) {
            // final
            $this->setFinalChar($idx, $prevchar, $thischar, $arabicarr);
        } elseif (isset($arabicarr[$thischar['char']][0])) {
            // isolated
            $this->newchardata[$idx]['char'] = $arabicarr[$thischar['char']][0];
        }

        
        // if laa letter
        if ($laaletter) {
            // mark characters to delete with false
            $this->newchardata[($this->alchars[($pos - 1)]['i'])]['char'] = false;
        }
    }
}
