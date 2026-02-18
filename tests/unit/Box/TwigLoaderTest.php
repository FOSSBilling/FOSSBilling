<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Twig\Error\LoaderError;

test('templates', function (): void {
    $loader = new Box_TwigLoader([
        'mods' => PATH_MODS,
        'theme' => PATH_THEMES . DIRECTORY_SEPARATOR . 'huraga',
        'type' => 'client',
    ]);
    $test = $loader->getSourceContext('mod_page_login.html.twig');
    $test2 = $loader->getSourceContext('error.html.twig');

    expect($test)->toBeObject();
    expect($test2)->toBeObject();
});

test('exception', function (): void {
    $loader = new Box_TwigLoader([
        'type' => 'client',
        'mods' => PATH_MODS,
        'theme' => PATH_THEMES . DIRECTORY_SEPARATOR . 'huraga',
    ]);

    expect(fn () => $loader->getSourceContext('mod_non_existing_settings.html.twig'))
        ->toThrow(LoaderError::class);
});
