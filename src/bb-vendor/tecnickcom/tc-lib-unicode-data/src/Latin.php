<?php
/**
 * Latin.php
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
 * Com\Tecnick\Unicode\Data\Latin
 *
 * @since       2011-05-23
 * @category    Library
 * @package     UnicodeData
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-unicode-data
 */
class Latin
{
    /**
     * Array of character substitutions from UTF-8 Unicode to Latin1.
     *
     * @var array
     */
    public static $substitute = array(
        8364=>128, # Euro1
        338=>140,  # OE
        352=>138,  # Scaron
        376=>159,  # Ydieresis
        381=>142,  # Zcaron2
        8226=>149, # bullet3
        710=>136,  # circumflex
        8224=>134, # dagger
        8225=>135, # daggerdbl
        8230=>133, # ellipsis
        8212=>151, # emdash
        8211=>150, # endash
        402=>131,  # florin
        8249=>139, # guilsinglleft
        8250=>155, # guilsinglright
        339=>156,  # oe
        8240=>137, # perthousand
        8222=>132, # quotedblbase
        8220=>147, # quotedblleft
        8221=>148, # quotedblright
        8216=>145, # quoteleft
        8217=>146, # quoteright
        8218=>130, # quotesinglbase
        353=>154,  # scaron
        732=>152,  # tilde
        8482=>153, # trademark
        382=>158   # zcaron2
    );
}
