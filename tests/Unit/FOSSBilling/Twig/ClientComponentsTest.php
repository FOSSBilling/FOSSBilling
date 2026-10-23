<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

function clientComponentsTwig(): Environment
{
    $twig = new Environment(new FilesystemLoader([PATH_THEMES . '/default/client/html', PATH_THEMES . '/default/shared/html']), ['autoescape' => 'html', 'strict_variables' => true]);
    $twig->addFilter(new TwigFilter('trans', static fn (string $text): string => $text));

    return $twig;
}

test('client select macro preserves options and exposes native labelled required controls', function (): void {
    $twig = clientComponentsTwig();
    $html = $twig->createTemplate('{% import "macro_functions.html.twig" as mf %}{{ mf.selectbox("support_helpdesk_id", options, 2, true, null, "support-helpdesk-id") }}')->render(['options' => [1 => 'General', 2 => '<Billing>']]);
    expect($html)->toContain('name="support_helpdesk_id"', 'id="support-helpdesk-id"', 'class="form-select is-required" required', '&lt;Billing&gt;')
        ->not->toContain('<Billing>');
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//option[@value="2" and @selected]'))->toHaveCount(1);
    $legacy = $twig->createTemplate('{% import "macro_functions.html.twig" as mf %}{{ mf.selectbox("region", {}, null, false) }}')->render();
    expect($legacy)->toContain('id="region"', 'class="form-select"')->not->toContain('required');
});
