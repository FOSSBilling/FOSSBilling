<?php
/**
 * TypeTest.php
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
 * Type Test
 *
 * @since       2011-05-23
 * @category    Library
 * @package     UnicodeData
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-unicode-data
 */
class TypeTest extends TestCase
{
    public function testStrong()
    {
        $this->assertEquals(3, count(\Com\Tecnick\Unicode\Data\Type::$strong));
    }
    
    public function testWeak()
    {
        $this->assertEquals(7, count(\Com\Tecnick\Unicode\Data\Type::$weak));
    }
    
    public function testNeutral()
    {
        $this->assertEquals(4, count(\Com\Tecnick\Unicode\Data\Type::$neutral));
    }
    
    public function testExplicitFormatting()
    {
        $this->assertEquals(9, count(\Com\Tecnick\Unicode\Data\Type::$explicit_formatting));
    }
    
    public function testUni()
    {
        $this->assertEquals(17720, count(\Com\Tecnick\Unicode\Data\Type::$uni));
    }
}
