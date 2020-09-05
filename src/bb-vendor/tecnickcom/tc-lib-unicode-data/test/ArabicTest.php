<?php
/**
 * ArabicTest.php
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

namespace Test;

use PHPUnit\Framework\TestCase;

/**
 * Arabic Test
 *
 * @since       2011-05-23
 * @category    Library
 * @package     UnicodeData
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-unicode-data
 */
class ArabicTest extends TestCase
{
    public function testDiacritic()
    {
        $this->assertEquals(5, count(\Com\Tecnick\Unicode\Data\Arabic::$diacritic));
    }

    public function testlaa()
    {
        $this->assertEquals(4, count(\Com\Tecnick\Unicode\Data\Arabic::$laa));
    }

    public function testSubstitute()
    {
        $this->assertEquals(76, count(\Com\Tecnick\Unicode\Data\Arabic::$substitute));
    }
}
