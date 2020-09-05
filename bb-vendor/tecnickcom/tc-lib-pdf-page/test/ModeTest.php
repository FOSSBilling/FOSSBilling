<?php
/**
 * ModeTest.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfPage
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-page
 *
 * This file is part of tc-lib-pdf-page software library.
 */

namespace Test;

use PHPUnit\Framework\TestCase;

/**
 * Mode Test
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfPage
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-page
 */
class ModeTest extends TestCase
{
    protected $obj = null;

    public function setUp()
    {
        //$this->markTestSkipped(); // skip this test

        $col = new \Com\Tecnick\Color\Pdf;
        $enc = new \Com\Tecnick\Pdf\Encrypt\Encrypt(false);
        $this->obj = new \Com\Tecnick\Pdf\Page\Page('mm', $col, $enc, false, false);
    }

    public function testGetLayout()
    {
        $this->assertEquals('TwoColumnLeft', $this->obj->getLayout('two'));
        $this->assertEquals('SinglePage', $this->obj->getLayout(''));
        $this->assertEquals('SinglePage', $this->obj->getLayout());
    }

    public function testGetDisplay()
    {
        $this->assertEquals('UseThumbs', $this->obj->getDisplay('usethumbs'));
        $this->assertEquals('UseAttachments', $this->obj->getDisplay(''));
        $this->assertEquals('UseNone', $this->obj->getDisplay('something'));
    }
}
