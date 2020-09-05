<?php
/**
 * Constant.php
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
 * Com\Tecnick\Unicode\Data\Constant
 *
 * @since       2011-05-23
 * @category    Library
 * @package     UnicodeData
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-unicode-data
 */
class Constant
{
    /*
     * Explicit Directional Embeddings
     * -------------------------------
     * The following characters signal that a piece of text is to be treated as embedded.
     * For example, an English quotation in the middle of an Arabic sentence could be marked
     * as being embedded left-to-right text. If there were a Hebrew phrase in the middle of
     * the English quotation, that phrase could be marked as being embedded right-to-left text.
     * Embeddings can be nested one inside another, and in isolates and overrides.
     */

    /**
     * (U+202A) LEFT-TO-RIGHT EMBEDDING
     * Treat the following text as embedded left-to-right
     */
    const LRE = 8234;

    /**
     * (U+202B) RIGHT-TO-LEFT EMBEDDING
     * Treat the following text as embedded right-to-left
     */
    const RLE = 8235;

    /*
     * Explicit Directional Overrides
     * ------------------------------
     * The following characters allow the bidirectional character types to be overridden when
     * required for special cases, such as for part numbers. They are to be avoided wherever possible,
     * because of security concerns. For more information, see [UTR36].
     * Directional overrides can be nested one inside another, and in embeddings and isolates.
     */

    /**
     * (U+202D) for LEFT-TO-RIGHT OVERRIDE
     * Force following characters to be treated as strong left-to-right characters
     */
    const LRO = 8237;

    /**
     * (U+202E) RIGHT-TO-LEFT OVERRIDE
     * Force following characters to be treated as strong right-to-left characters
     */
    const RLO = 8238;

    /*
     * Terminating Explicit Directional Embeddings and Overrides
     * ---------------------------------------------------------
     */

    /**
     * (U+202C) POP DIRECTIONAL FORMATTING
     * End the scope of the last LRE, RLE, RLO, or LRO whose scope has not yet been terminated
     */
    const PDF = 8236;

    /*
     * Explicit Directional Isolates
     * -----------------------------
     * The following characters signal that a piece of text is to be treated as directionally isolated
     * from its surroundings. They are very similar to the explicit embedding formatting characters.
     * However, while an embedding roughly has the effect of a strong character on the ordering of the
     * surrounding text, an isolate has the effect of a neutral like U+FFFC OBJECT REPLACEMENT CHARACTER,
     * and is assigned the corresponding display position in the surrounding text.
     * Furthermore, the text inside the isolate has no effect on the ordering of the text outside it, and vice versa.
     *
     * In addition to allowing the embedding of strongly directional text without unduly affecting the bidirectional
     * order of its surroundings, one of the isolate formatting characters also offers an extra feature:
     * embedding text while inferring its direction heuristically from its constituent characters.
     *
     * Isolates can be nested one inside another, and in embeddings and overrides.
     */

    /**
     * (U+2066) LEFT-TO-RIGHT ISOLATE
     * Treat the following text as isolated and left-to-right
     */
    const LRI = 8294;

    /**
     * (U+2067) RIGHT-TO-LEFT ISOLATE
     * Treat the following text as isolated and right-to-left
     */
    const RLI = 8295;

    /**
     * (U+2068) FIRST STRONG ISOLATE
     * Treat the following text as isolated and in the direction of its first
     * strong directional character that is not inside a nested isolate
     */
    const FSI = 8296;

    /*
     * Terminating Explicit Directional Isolates
     * -----------------------------------------
     * The following character terminates the scope of the last LRI, RLI, or FSI whose scope
     * has not yet been terminated, as well as the scopes of any subsequent LREs, RLEs, LROs, or RLOs
     * whose scopes have not yet been terminated.
     */

    /**
     * (U+2069) POP DIRECTIONAL ISOLATE
     * End the scope of the last LRI, RLI, or FSI
     */
    const PDI = 8297;
    
    /*
     * Implicit Directional Marks
     * --------------------------
     * These characters are very light-weight formatting.
     * They act exactly like right-to-left or left-to-right characters,
     * except that they do not display or have any other semantic effect.
     * Their use is more convenient than using explicit embeddings or overrides because their scope is much more local.

    /**
     * (U+200E) LEFT-TO-RIGHT MARK
     * Left-to-right zero-width character
     */
    const LRM = 8206;

    /**
     * (U+200F) RIGHT-TO-LEFT MARK
     * Right-to-left zero-width non-Arabic character
     */
    const RLM = 8207;

    /**
     * (U+061C) ARABIC LETTER MARK
     * Right-to-left zero-width Arabic character
     */
    const ALM = 1564;

    /*
     * Other useful characters
     * -----------------------
     */

    /**
     * (U+0020) SPACE
     */
    const SPACE = 32;

    /**
     * (U+200C) ZERO WIDTH NON-JOINER
     */
    const ZERO_WIDTH_NON_JOINER = 8204;
}
