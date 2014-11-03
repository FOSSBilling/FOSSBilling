<?php
/**
 * @group Core
 */


class Box_TwigLoaderTest extends PHPUnit_Framework_TestCase
{

    public function testTemplates()
    {
        $loader = new Box_TwigLoader(array(
            "mods" => BB_PATH_MODS,
            "theme" => BB_PATH_THEMES.DIRECTORY_SEPARATOR."huraga",
            "type" => "client"
        ));
        $test =  $loader->getSource("mod_cron_check.phtml");
        $test2 =  $loader->getSource("404.phtml");

        $this->assertInternalType('string', $test);
        $this->assertInternalType('string', $test2);
    }

    /**
     * @expectedException Twig_Error_Loader
     */
    public function testException()
    {
        $loader = new Box_TwigLoader(array(
            "type" => 'client',
            "mods" => BB_PATH_MODS,
            "theme" => BB_PATH_THEMES.DIRECTORY_SEPARATOR."huraga",
        ));
        $test =  $loader->getSource("mod_non_existing_settings.phtml");
        $test =  $loader->getSource("some_random_name.phtml");
    }
}