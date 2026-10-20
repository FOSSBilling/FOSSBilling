<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Tests\Support\PermissiveStub;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

test('form editor renders missing and saved answers without pricing metadata', function (array $answers): void {
    $fields = [];
    foreach (['text', 'url', 'select', 'checkbox', 'radio', 'textarea'] as $type) {
        $fields[] = [
            'name' => $type,
            'type' => $type,
            'label' => ucfirst($type),
            'hide_label' => false,
            'required' => false,
            'readonly' => false,
            'prefix' => '',
            'suffix' => '',
            'options' => in_array($type, ['select', 'checkbox', 'radio'], true) ? ['one' => 'one', 'two' => 'two'] : [],
        ];
    }

    $twig = new Environment(new FilesystemLoader(PATH_MODS . '/Formbuilder/templates/admin'), ['strict_variables' => true]);
    $twig->addFilter(new TwigFilter('trans', static fn (string $value): string => $value));
    $twig->addFilter(new TwigFilter('api_url', static fn (string $value): string => '/' . $value));
    $twig->addFunction(new TwigFunction('fb_api_form', static fn (array $options): string => ''));

    $html = $twig->render('mod_formbuilder_manage.html.twig', [
        'guest' => new PermissiveStub(['formbuilder_get' => ['name' => 'Example form', 'fields' => $fields]]),
        'order' => ['id' => 1, 'form_id' => 2, 'config' => ['period' => '1M'] + $answers],
    ]);

    $document = new DOMDocument();
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);

    foreach (['text', 'url'] as $type) {
        expect($xpath->evaluate('string(//input[@name="config[' . $type . ']"]/@value)'))->toBe($answers[$type] ?? '');
    }
    expect($xpath->evaluate('string(//textarea[@name="config[textarea]"])'))->toBe($answers['textarea'] ?? '');
    foreach (['checkbox', 'radio'] as $type) {
        expect($xpath->evaluate('string(//input[@type="' . $type . '"][@checked]/@value)'))->toBe($answers === [] ? '' : 'two');
    }
    expect($xpath->evaluate('string(//option[@selected]/@value)'))->toBe($answers['select'] ?? '')
        ->and($xpath->evaluate('count(//input[@type="hidden"])'))->toBe(2.0)
        ->and($xpath->evaluate('string(//input[@name="config[form_id]"]/@value)'))->toBe('2')
        ->and($xpath->evaluate('string(//input[@name="id"]/@value)'))->toBe('1');
})->with([
    'missing answers' => [[]],
    'saved answers' => [[
        'text' => 'Saved answer',
        'url' => 'https://example.test',
        'select' => 'two',
        'checkbox' => ['two'],
        'radio' => 'two',
        'textarea' => 'Saved notes',
    ]],
]);
