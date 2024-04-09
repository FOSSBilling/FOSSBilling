<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Api_Guest_ExtensionTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'extensions.xml';

    public function testLists(): void
    {
        $array = $this->api_guest->extension_languages();
        $this->assertIsArray($array);

        $array = $this->api_guest->extension_theme();
        $this->assertIsArray($array);

        $bool = $this->api_guest->extension_is_on(['mod' => 'system']);
        $this->assertTrue($bool);

        $arr = $this->api_guest->extension_settings(['ext' => 'mod_email']);
        $this->assertIsArray($arr);
    }
}
