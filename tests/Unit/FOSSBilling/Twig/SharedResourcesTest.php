<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

test('feedback labels share translated values while retaining client dataset aliases', function (string $area): void {
    $twig = new Environment(new FilesystemLoader(PATH_THEMES . '/default/shared/html'), ['strict_variables' => true, 'autoescape' => 'html']);
    $twig->addFilter(new TwigFilter('trans', static fn (string $text): string => '<Translated> ' . $text));
    $html = $twig->render('partial_ui_feedback.html.twig', ['feedback_area' => $area]);
    $document = new DOMDocument();
    @$document->loadHTML($html);
    $nodes = (new DOMXPath($document))->query('//div[contains(@class,"toast-container")]');
    expect($nodes)->toHaveCount(1);
    $container = $nodes->item(0);
    foreach (['loading', 'message', 'form-error', 'required', 'confirm', 'cancel', 'close'] as $key) {
        expect($container->getAttribute('data-' . $key))->toStartWith('<Translated>');
        if ($area === 'client') {
            expect($container->getAttribute('data-client-' . $key))->toBe($container->getAttribute('data-' . $key));
        }
    }
    expect($container->getAttribute('data-request-loading'))->toContain('Loading. Please wait.');
    expect($container->getAttribute('data-form-updated'))->toContain('Changes Saved');
    expect($html)->not->toContain('<Translated>');
})->with(['admin', 'client']);

test('shared status palette still accepts extension color overrides', function (): void {
    $twig = new Environment(new FilesystemLoader(PATH_THEMES . '/default/shared/html'), ['strict_variables' => true, 'autoescape' => 'html']);
    $twig->addFilter(new TwigFilter('trans', static fn (string $text): string => $text));
    $html = $twig->render('partial_status_badge_shared.html.twig', ['status' => 'active', 'type' => 'order', 'badge_variant' => 'subtle', 'badge_palette' => ['success' => 'extension-active']]);
    expect($html)->toContain('badge extension-active', 'Active');
});
