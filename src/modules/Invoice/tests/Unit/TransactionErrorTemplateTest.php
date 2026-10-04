<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

test('transaction error links preserve diagnostic text without executable attributes', function (string $error): void {
    $template = (new Filesystem())->readFile(Path::join(PATH_MODS, 'Invoice/templates/admin/mod_invoice_transactions.html.twig'));
    $start = strpos($template, '{% if tx.error %}');
    $end = strpos($template, '{% else %}', $start);
    expect($start)->not->toBeFalse();
    expect($end)->not->toBeFalse();
    $fragment = substr($template, $start, $end - $start) . '{% endif %}';
    $twig = new Environment(new ArrayLoader(['error_link' => $fragment]), ['autoescape' => 'html', 'strict_variables' => true]);
    $html = $twig->render('error_link', ['tx' => ['error' => $error, 'error_code' => 42]]);
    $document = new DOMDocument();
    $document->loadHTML('<?xml encoding="UTF-8">' . $html);
    $links = $document->getElementsByTagName('a');
    expect($links->length)->toBe(1);
    $link = $links->item(0);
    expect($link->getAttribute('data-transaction-error'))->toBe($error)
        ->and($link->getAttribute('data-transaction-error-code'))->toBe('42')
        ->and($link->textContent)->toBe('42');
    foreach ($link->attributes as $attribute) {
        expect(str_starts_with(strtolower($attribute->name), 'on'))->toBeFalse();
    }
    expect($document->getElementsByTagName('script')->length)->toBe(0)
        ->and($document->getElementsByTagName('img')->length)->toBe(0);
})->with([
    'quote breakout' => ["');globalThis.transactionErrorExecuted = true;//"],
    'markup breakout' => ['"><img src=x onerror="alert(1)"><script>alert(1)</script>'],
    'entity text' => ['&quot; &#39; &amp;'],
    'ordinary diagnostic' => ["Gateway's \"declined\" response \\ retry\nPayment failed — £10"],
]);
