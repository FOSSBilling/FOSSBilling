<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use FOSSBilling\Twig\Enum\AppArea;
use FOSSBilling\Twig\TwigLoader;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Twig\Error\LoaderError;

test('templates', function (): void {
    $loader = new TwigLoader(AppArea::CLIENT, PATH_THEMES . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'client');
    $test = $loader->getSourceContext('mod_page_login.html.twig');
    $test2 = $loader->getSourceContext('error.html.twig');

    expect($test)->toBeObject();
    expect($test2)->toBeObject();
});

test('exception', function (): void {
    $loader = new TwigLoader(AppArea::CLIENT, PATH_THEMES . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . 'client');

    expect(fn (): Twig\Source => $loader->getSourceContext('mod_non_existing_settings.html.twig'))
        ->toThrow(LoaderError::class);
});

function makeFixturePackage(string $areaName, bool $withShared): array
{
    $root = Path::join(sys_get_temp_dir(), 'fossbilling-twig-loader-test-' . bin2hex(random_bytes(8)));
    $themePath = Path::join($root, $areaName);
    $sharedHtmlPath = Path::join($root, 'shared', 'html');

    $filesystem = new Filesystem();
    $filesystem->mkdir(Path::join($themePath, 'html'));
    if ($withShared) {
        $filesystem->mkdir($sharedHtmlPath);
    }

    return [$themePath, $sharedHtmlPath];
}

test('shared fallback resolves when a shared path is given', function (): void {
    [$themePath, $sharedHtmlPath] = makeFixturePackage('admin', withShared: true);
    file_put_contents(Path::join($sharedHtmlPath, 'partial_only_in_shared.html.twig'), 'shared content');

    $loader = new TwigLoader(AppArea::ADMIN, $themePath, $sharedHtmlPath);

    expect($loader->getSourceContext('partial_only_in_shared.html.twig')->getCode())->toBe('shared content');

    (new Filesystem())->remove(Path::getDirectory($themePath));
});

test("theme's own template wins over the shared one", function (): void {
    [$themePath, $sharedHtmlPath] = makeFixturePackage('admin', withShared: true);
    file_put_contents(Path::join($themePath, 'html', 'partial_test_priority_check.html.twig'), 'own content');
    file_put_contents(Path::join($sharedHtmlPath, 'partial_test_priority_check.html.twig'), 'shared content');

    $loader = new TwigLoader(AppArea::ADMIN, $themePath, $sharedHtmlPath);

    expect($loader->getSourceContext('partial_test_priority_check.html.twig')->getCode())->toBe('own content');

    (new Filesystem())->remove(Path::getDirectory($themePath));
});

test('no shared fallback when no shared path is given', function (): void {
    [$themePath, $sharedHtmlPath] = makeFixturePackage('admin', withShared: true);
    file_put_contents(Path::join($sharedHtmlPath, 'partial_only_in_shared.html.twig'), 'shared content');

    $loader = new TwigLoader(AppArea::ADMIN, $themePath);

    expect(fn (): Twig\Source => $loader->getSourceContext('partial_only_in_shared.html.twig'))
        ->toThrow(LoaderError::class);

    (new Filesystem())->remove(Path::getDirectory($themePath));
});

test('a non-existent shared path is silently ignored', function (): void {
    [$themePath, $sharedHtmlPath] = makeFixturePackage('admin', withShared: false);

    $loader = new TwigLoader(AppArea::ADMIN, $themePath, $sharedHtmlPath);

    expect(fn (): Twig\Source => $loader->getSourceContext('anything.html.twig'))
        ->toThrow(LoaderError::class);

    (new Filesystem())->remove(Path::getDirectory($themePath));
});
