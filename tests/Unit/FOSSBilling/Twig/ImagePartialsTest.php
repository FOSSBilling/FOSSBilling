<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

function imagePartialTwig(string $theme): Environment
{
    $loader = new FilesystemLoader([PATH_THEMES . '/default/' . $theme . '/html', PATH_THEMES . '/default/shared/html']);
    $loader->addPath(PATH_MODS . '/Page/templates/client', 'Page');
    $twig = new Environment($loader, ['strict_variables' => true, 'autoescape' => 'html']);
    $twig->addFilter(new TwigFilter('url', static fn (string $url): string => $url));

    return $twig;
}

test('logo entrypoints preserve branding options and fall back to the available variant', function (string $theme, string $template): void {
    $twig = imagePartialTwig($theme);
    $html = $twig->render($template, [
        'company' => ['name' => 'A & B <Hosting>', 'logo_url' => '/must-not-override.png'],
        'logo_url' => null, 'dark_logo_url' => '/custom-dark.svg',
        'link_url' => '/brand', 'link_class' => 'extension-logo', 'link_target' => '_blank', 'link_rel' => 'noopener',
        'image_class' => 'extension-image', 'image_width' => 120, 'image_height' => 40, 'image_style' => 'opacity: .9;',
    ]);
    $document = new DOMDocument();
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//a[@href="/brand"][@class="extension-logo"][@target="_blank"][@rel="noopener"]'))->toHaveCount(1);
    expect($xpath->query('//img[@src="/custom-dark.svg"][@width="120"][@height="40"][@style="opacity: .9;"]'))->toHaveCount(2);
    expect($html)->toContain('hide-theme-light', 'hide-theme-dark', 'extension-image', 'A &amp; B &lt;Hosting&gt;')
        ->not->toContain('/must-not-override.png');
    $hidden = $twig->render($template, ['company' => ['name' => 'Text Brand', 'logo_url' => '/disabled.svg'], 'logo_url' => null, 'dark_logo_url' => null, 'fallback_class' => 'extension-fallback']);
    expect($hidden)->toContain('<span class="extension-fallback">Text Brand</span>')->not->toContain('<img', '/disabled.svg');
})->with([
    ['admin', 'partial_company_logo.html.twig'],
    ['client', 'partial_company_logo.html.twig'],
    ['client', '@Page/partial_company_logo.html.twig'],
]);
