<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

test('both themes expose named determinate progress with bounded values and escaped labels', function (string $theme, int $input, int $expected): void {
    $twig = new Environment(new FilesystemLoader([PATH_THEMES . '/default/' . $theme . '/html', PATH_THEMES . '/default/shared/html']), ['strict_variables' => true, 'autoescape' => 'html']);
    $html = $twig->render('partial_progress.html.twig', ['progress_value' => $input, 'progress_label' => 'Translation <test>', 'progress_tone' => 'warning']);
    $document = new DOMDocument();
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@role="progressbar"][@aria-label="Translation <test>"][@aria-valuenow="' . $expected . '"][@aria-valuemin="0"][@aria-valuemax="100"]'))->toHaveCount(1);
    expect($html)->toContain('width: ' . $expected . '%', 'bg-warning', $expected . '%</span>', '&lt;test&gt;')->not->toContain('<test>');
    expect($xpath->query('//*[@role="progressbar"]//span'))->toHaveCount(0);
})->with(['client', 'admin'])->with([[-12, 0], [3, 3], [54, 54], [140, 100]]);
