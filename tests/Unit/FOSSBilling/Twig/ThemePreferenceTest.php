<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

function themePreferenceTemplates(): Environment
{
    $twig = new Environment(new FilesystemLoader([
        PATH_THEMES . '/default/shared/html',
        PATH_THEMES . '/default/client/html',
        PATH_THEMES . '/default/admin/html',
    ]), ['autoescape' => 'html', 'strict_variables' => true]);
    $twig->addFilter(new TwigFilter('trans', static fn (string $text): string => $text));

    return $twig;
}

test('theme choices share accessible labels and keep client picker controls in one group', function (): void {
    $html = themePreferenceTemplates()->render('partial_client_theme_picker.html.twig');
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//button[@data-theme-preference]'))->toHaveCount(3)
        ->and($xpath->query('//button[@aria-controls="client-theme-menu" and @aria-expanded="false"]'))->toHaveCount(1);
    foreach (['system' => 'System', 'light' => 'Light', 'dark' => 'Dark'] as $value => $label) {
        $button = $xpath->query('//button[@data-theme-preference="' . $value . '"]')->item(0);
        expect($button->getAttribute('aria-label'))->toBe($label)
            ->and($button->getAttribute('aria-pressed'))->toBe('false')
            ->and(trim($button->textContent))->toBe($label);
    }
});

test('pre-paint templates pass their configured defaults and admin sidebar option', function (): void {
    $twig = themePreferenceTemplates();
    $client = $twig->render('partials/theme_init.html.twig', ['settings' => ['theme' => 'dark']]);
    $admin = $twig->render('partial_admin_theme_init.html.twig');
    expect($client)->toContain('const configured = "dark";')
        ->not->toContain("getItem('tabler-sidebar')");
    expect($admin)->toContain('const configured = "system";', "getItem('tabler-sidebar')", "setAttribute('data-bs-sidebar', sidebar)");
});
