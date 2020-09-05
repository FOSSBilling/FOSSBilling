<?php
/**
 * Pattern.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     UnicodeData
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-unicode-data
 *
 * This file is part of tc-lib-unicode-data software library.
 */

namespace Com\Tecnick\Unicode\Data;

/**
 * Com\Tecnick\Unicode\Data\Pattern
 *
 * @since       2011-05-23
 * @category    Library
 * @package     UnicodeData
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-unicode-data
 */
class Pattern
{
    /**
     * Pattern to test RTL (Righ-To-Left) strings using regular expressions.
     * (excluding Arabic)
     */
    const RTL = "/(
          \xD6\xBE                                             # R
        | \xD7[\x80\x83\x86\x90-\xAA\xB0-\xB4]                 # R
        | \xDF[\x80-\xAA\xB4\xB5\xBA]                          # R
        | \xE2\x80\x8F                                         # R
        | \xEF\xAC[\x9D\x9F\xA0-\xA8\xAA-\xB6\xB8-\xBC\xBE]    # R
        | \xEF\xAD[\x80\x81\x83\x84\x86-\x8F]                  # R
        | \xF0\x90\xA0[\x80-\x85\x88\x8A-\xB5\xB7\xB8\xBC\xBF] # R
        | \xF0\x90\xA4[\x80-\x99]                              # R
        | \xF0\x90\xA8[\x80\x90-\x93\x95-\x97\x99-\xB3]        # R
        | \xF0\x90\xA9[\x80-\x87\x90-\x98]                     # R
        | \xE2\x80[\xAB\xAE]                                   # RLE & RLO
        | \xE2\x81\xA7                                         # RLI
        )/x";

    /**
     * Pattern to test Arabic strings using regular expressions.
     * Ref: http://www.w3.org/International/questions/qa-forms-utf-8
     */
    const ARABIC = "/(
          \xD8[\x80-\x83\x8B\x8D\x9B\x9E\x9F\xA1-\xBA]  # AL
        | \xD9[\x80-\x8A\xAD-\xAF\xB1-\xBF]             # AL
        | \xDA[\x80-\xBF]                               # AL
        | \xDB[\x80-\x95\x9D\xA5\xA6\xAE\xAF\xBA-\xBF]  # AL
        | \xDC[\x80-\x8D\x90\x92-\xAF]                  # AL
        | \xDD[\x8D-\xAD]                               # AL
        | \xDE[\x80-\xA5\xB1]                           # AL
        | \xEF\xAD[\x90-\xBF]                           # AL
        | \xEF\xAE[\x80-\xB1]                           # AL
        | \xEF\xAF[\x93-\xBF]                           # AL
        | \xEF[\xB0-\xB3][\x80-\xBF]                    # AL
        | \xEF\xB4[\x80-\xBD]                           # AL
        | \xEF\xB5[\x90-\xBF]                           # AL
        | \xEF\xB6[\x80-\x8F\x92-\xBF]                  # AL
        | \xEF\xB7[\x80-\x87\xB0-\xBC]                  # AL
        | \xEF\xB9[\xB0-\xB4\xB6-\xBF]                  # AL
        | \xEF\xBA[\x80-\xBF]                           # AL
        | \xEF\xBB[\x80-\xBC]                           # AL
        | \xD9[\xA0-\xA9\xAB\xAC]                       # AN
        )/x";
}
