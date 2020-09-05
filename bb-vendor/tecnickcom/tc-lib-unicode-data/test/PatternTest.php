<?php
/**
 * PatternTest.php
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
 * Pattern Test
 *
 * @since       2011-05-23
 * @category    Library
 * @package     UnicodeData
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-unicode-data
 */
class PatternTest extends TestCase
{
    public function testPatterns()
    {
        $str = 'hello world';
        $this->assertEquals(0, preg_match(\Com\Tecnick\Unicode\Data\Pattern::ARABIC, $str));
        $this->assertEquals(0, preg_match(\Com\Tecnick\Unicode\Data\Pattern::RTL, $str));

        $str = 'مرحبا بالعالم';
        $this->assertEquals(1, preg_match(\Com\Tecnick\Unicode\Data\Pattern::ARABIC, $str));

        $str = 'שלום עולם';
        $this->assertEquals(0, preg_match(\Com\Tecnick\Unicode\Data\Pattern::ARABIC, $str));
        $this->assertEquals(1, preg_match(\Com\Tecnick\Unicode\Data\Pattern::RTL, $str));

        $str = json_decode('"\u2067"'); // RLI
        $this->assertEquals(1, preg_match(\Com\Tecnick\Unicode\Data\Pattern::RTL, $str));

        $str = json_decode('"\u202B"'); // RLE
        $this->assertEquals(1, preg_match(\Com\Tecnick\Unicode\Data\Pattern::RTL, $str));

        $str = json_decode('"\u202E"'); // RLO
        $this->assertEquals(1, preg_match(\Com\Tecnick\Unicode\Data\Pattern::RTL, $str));
    }
}
