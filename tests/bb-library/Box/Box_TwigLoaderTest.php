<?php

/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * @group Core
 */
class Box_TwigLoaderTest extends PHPUnit\Framework\TestCase
{
    public function testTemplates()
    {
        $loader = new Box_TwigLoader([
            'mods' => BB_PATH_MODS,
            'theme' => BB_PATH_THEMES . DIRECTORY_SEPARATOR . 'huraga',
            'type' => 'client',
        ]);
        $test = $loader->getSourceContext('mod_example_index.html.twig');
        $test2 = $loader->getSourceContext('404.html.twig');

        $this->assertIsObject($test);
        $this->assertIsObject($test2);
    }

    public function testException()
    {
        $loader = new Box_TwigLoader([
            'type' => 'client',
            'mods' => BB_PATH_MODS,
            'theme' => BB_PATH_THEMES . DIRECTORY_SEPARATOR . 'huraga',
        ]);
        $this->expectException(Twig\Error\LoaderError::class);
        $test = $loader->getSourceContext('mod_non_existing_settings.html.twig');
        $test = $loader->getSourceContext('some_random_name.html.twig');
    }
}
