<?php

use \FOSSBilling\Twig\TwigLoader;
use \FOSSBilling\Twig\Enum\AppArea;
use Symfony\Component\Filesystem\Path;

#[PHPUnit\Framework\Attributes\Group('Core')]
class Box_TwigLoaderTest extends PHPUnit\Framework\TestCase
{
    public function testTemplates(): void
    {
        $loader = new TwigLoader(AppArea::CLIENT, Path::join(PATH_THEMES, 'huraga'));
        $test = $loader->getSourceContext('mod_page_login.html.twig');
        $test2 = $loader->getSourceContext('error.html.twig');

        $this->assertIsObject($test);
        $this->assertIsObject($test2);
    }

    public function testException(): void
    {
        $loader = new TwigLoader(AppArea::CLIENT, Path::join(PATH_THEMES, 'huraga'));
        $this->expectException(Twig\Error\LoaderError::class);
        $test = $loader->getSourceContext('mod_non_existing_settings.html.twig');
        $test = $loader->getSourceContext('some_random_name.html.twig');
    }
}
