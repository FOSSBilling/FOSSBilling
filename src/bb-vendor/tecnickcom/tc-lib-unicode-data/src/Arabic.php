<?php
/**
 * Arabic.php
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
 * Com\Tecnick\Unicode\Data\Arabic
 *
 * @since       2011-05-23
 * @category    Library
 * @package     UnicodeData
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-unicode-data
 */
class Arabic
{
    /**
     * Unicode code for ARABIC QUESTION MARK (U+061F)
     */
    const QUESTION_MARK = 1567;
    
    /**
     * Unicode code for ARABIC LETTER LAM (U+0644)
     */
    const LAM = 1604;
    
    /**
     * Unicode code for ARABIC LETTER HEH (U+0647)
     */
    const HEH = 1607;
    
    /**
     * Unicode code for ARABIC SHADDA (U+0651)
     */
    const SHADDA = 1617;
    
    /**
     * Unicode code for ARABIC LIGATURE ALLAH ISOLATED FORM (U+FDF2)
     */
    const LIGATURE_ALLAH_ISOLATED_FORM = 65010;
    
    /**
     * Arabic shape substitutions: char code => ([isolated, final, initial, medial]).
     *
     * @var array
     */
    public static $substitute = array(
        1569=>array(65152),
        1570=>array(65153, 65154, 65153, 65154),
        1571=>array(65155, 65156, 65155, 65156),
        1572=>array(65157, 65158),
        1573=>array(65159, 65160, 65159, 65160),
        1574=>array(65161, 65162, 65163, 65164),
        1575=>array(65165, 65166, 65165, 65166),
        1576=>array(65167, 65168, 65169, 65170),
        1577=>array(65171, 65172),
        1578=>array(65173, 65174, 65175, 65176),
        1579=>array(65177, 65178, 65179, 65180),
        1580=>array(65181, 65182, 65183, 65184),
        1581=>array(65185, 65186, 65187, 65188),
        1582=>array(65189, 65190, 65191, 65192),
        1583=>array(65193, 65194, 65193, 65194),
        1584=>array(65195, 65196, 65195, 65196),
        1585=>array(65197, 65198, 65197, 65198),
        1586=>array(65199, 65200, 65199, 65200),
        1587=>array(65201, 65202, 65203, 65204),
        1588=>array(65205, 65206, 65207, 65208),
        1589=>array(65209, 65210, 65211, 65212),
        1590=>array(65213, 65214, 65215, 65216),
        1591=>array(65217, 65218, 65219, 65220),
        1592=>array(65221, 65222, 65223, 65224),
        1593=>array(65225, 65226, 65227, 65228),
        1594=>array(65229, 65230, 65231, 65232),
        1601=>array(65233, 65234, 65235, 65236),
        1602=>array(65237, 65238, 65239, 65240),
        1603=>array(65241, 65242, 65243, 65244),
        1604=>array(65245, 65246, 65247, 65248),
        1605=>array(65249, 65250, 65251, 65252),
        1606=>array(65253, 65254, 65255, 65256),
        1607=>array(65257, 65258, 65259, 65260),
        1608=>array(65261, 65262, 65261, 65262),
        1609=>array(65263, 65264, 64488, 64489),
        1610=>array(65265, 65266, 65267, 65268),
        1649=>array(64336, 64337),
        1655=>array(64477),
        1657=>array(64358, 64359, 64360, 64361),
        1658=>array(64350, 64351, 64352, 64353),
        1659=>array(64338, 64339, 64340, 64341),
        1662=>array(64342, 64343, 64344, 64345),
        1663=>array(64354, 64355, 64356, 64357),
        1664=>array(64346, 64347, 64348, 64349),
        1667=>array(64374, 64375, 64376, 64377),
        1668=>array(64370, 64371, 64372, 64373),
        1670=>array(64378, 64379, 64380, 64381),
        1671=>array(64382, 64383, 64384, 64385),
        1672=>array(64392, 64393),
        1676=>array(64388, 64389),
        1677=>array(64386, 64387),
        1678=>array(64390, 64391),
        1681=>array(64396, 64397),
        1688=>array(64394, 64395, 64394, 64395),
        1700=>array(64362, 64363, 64364, 64365),
        1702=>array(64366, 64367, 64368, 64369),
        1705=>array(64398, 64399, 64400, 64401),
        1709=>array(64467, 64468, 64469, 64470),
        1711=>array(64402, 64403, 64404, 64405),
        1713=>array(64410, 64411, 64412, 64413),
        1715=>array(64406, 64407, 64408, 64409),
        1722=>array(64414, 64415),
        1723=>array(64416, 64417, 64418, 64419),
        1726=>array(64426, 64427, 64428, 64429),
        1728=>array(64420, 64421),
        1729=>array(64422, 64423, 64424, 64425),
        1733=>array(64480, 64481),
        1734=>array(64473, 64474),
        1735=>array(64471, 64472),
        1736=>array(64475, 64476),
        1737=>array(64482, 64483),
        1739=>array(64478, 64479),
        1740=>array(64508, 64509, 64510, 64511),
        1744=>array(64484, 64485, 64486, 64487),
        1746=>array(64430, 64431),
        1747=>array(64432, 64433)
    );

    /**
     * Arabic laa letter: (char code => [isolated, final, initial, medial]).
     *
     * @var array
     */
    public static $laa = array(
        1570=>array(65269, 65270, 65269, 65270), // ALEF (U+0627) with MADDAH ABOVE (U+0653)
        1571=>array(65271, 65272, 65271, 65272), // ALEF (U+0627) with HAMZA ABOVE (U+0654)
        1573=>array(65273, 65274, 65273, 65274), // ALEF (U+0627) with HAMZA BELOW (U+0655)
        1575=>array(65275, 65276, 65275, 65276)  // ALEF (U+0627)
    );

    /**
     * Array of character substitutions for sequences of two diacritics symbols.
     * Putting the combining mark and character in the same glyph allows us
     * to avoid the two marks overlapping each other in an illegible manner.
     * second NSM char code => substitution char
     *
     * @var array
     */
    public static $diacritic = array(
        1612=>64606, # Shadda + Dammatan
        1613=>64607, # Shadda + Kasratan
        1614=>64608, # Shadda + Fatha
        1615=>64609, # Shadda + Damma
        1616=>64610  # Shadda + Kasra
    );

    
    /**
     * Array of Arabic end letters
     *
     * @var array
     */
    public static $end = array(
        1569, // HAMZAH (U+621)
        1570, // ALEF (U+0627) with MADDAH ABOVE (U+0653)
        1571, // ALEF (U+0627) with HAMZA ABOVE (U+0654)
        1572, // WAW (U+0648) with HAMZA ABOVE (U+0654)
        1573, // ALEF (U+0627) with HAMZA BELOW (U+0655)
        1575, // ALEF (U+0627)
        1577, // TEH MARBUTA (U+0629)
        1583, // DAL (U+062F)
        1584, // THAL (U+0630)
        1585, // REH (U+0631)
        1586, // ZAIN (U+0632)
        1608, // WAW (U+0648)
        1688  // JEH (U+0698)
    );
}
