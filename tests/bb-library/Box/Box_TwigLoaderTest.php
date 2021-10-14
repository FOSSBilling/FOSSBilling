<?php
/**
 * @group Core
 */


class Box_TwigLoaderTest extends PHPUnit\Framework\TestCase
{

    public function testTemplates()
    {
        $loader = new Box_TwigLoader(array(
            "mods" => BB_PATH_MODS,
            "theme" => BB_PATH_THEMES.DIRECTORY_SEPARATOR."huraga",
            "type" => "client"
        ));
        $test =  $loader->getSourceContext("mod_example_index.phtml");
        $test2 =  $loader->getSourceContext("404.phtml");

        $this->assertIsObject($test);
        $this->assertIsObject($test2);
    }

    public function testException()
    {
        $loader = new Box_TwigLoader(array(
            "type" => 'client',
            "mods" => BB_PATH_MODS,
            "theme" => BB_PATH_THEMES.DIRECTORY_SEPARATOR."huraga",
        ));
        $this->expectException(Twig\Error\LoaderError::class);
        $test =  $loader->getSourceContext("mod_non_existing_settings.phtml");
        $test =  $loader->getSourceContext("some_random_name.phtml");
    }
}