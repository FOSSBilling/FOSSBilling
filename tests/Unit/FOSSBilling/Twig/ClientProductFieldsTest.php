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

test('checkout custom fields retain names and defaults with labelled controls and configured requirements', function (string $style): void {
    $fields = [];
    foreach (['text', 'url', 'select', 'checkbox', 'radio', 'textarea'] as $type) {
        $fields[] = ['name' => 'custom_' . $type, 'type' => $type, 'label' => ucfirst($type), 'hide_label' => $type === 'url' ? 1 : 0,
            'description' => 'Helpful instructions', 'required' => $type === 'select' ? 0 : 1, 'readonly' => 0,
            'prefix' => $type === 'text' ? '$' : '', 'suffix' => '', 'default_value' => $type === 'checkbox' ? ['one'] : 'one',
            'options' => in_array($type, ['select', 'checkbox', 'radio'], true) ? ['First' => 'one', 'Second' => 'two'] : []];
    }
    $twig = new Environment(new FilesystemLoader(PATH_MODS . '/Formbuilder/templates/client'), ['strict_variables' => true]);
    $twig->addFilter(new TwigFilter('markdown_to_html', static fn (string $text): string => $text));
    $twig->addFilter(new TwigFilter('trans', static fn (string $text): string => $text));
    $html = $twig->render('mod_formbuilder_build.html.twig', ['product' => ['id' => 5, 'form_id' => 7],
        'guest' => new PermissiveStub(['extension_is_on' => true, 'formbuilder_get' => ['id' => 7, 'name' => 'Custom fields', 'style' => ['type' => $style, 'show_title' => '1'], 'fields' => $fields]])]);
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    foreach ($xpath->query('//input[not(@type="hidden")]|//select|//textarea') as $control) {
        $id = $control->getAttribute('id');
        expect($id)->not->toBe('');
        expect($xpath->query('//*[@id="' . $id . '"]'))->toHaveCount(1);
        expect($control->hasAttribute('aria-label') || $xpath->query('//label[@for="' . $id . '"]')->length > 0)->toBeTrue();
    }
    expect($dom->getElementById('custom_select')->hasAttribute('required'))->toBeFalse()
        ->and($dom->getElementById('custom_text')->hasAttribute('required'))->toBeTrue()
        ->and($dom->getElementById('custom_text')->getAttribute('name'))->toBe('custom_text')
        ->and($dom->getElementById('custom_text')->getAttribute('value'))->toBe('one')
        ->and($dom->getElementById('custom_text')->getAttribute('aria-describedby'))->toBe('custom_text-help')
        ->and($dom->getElementById('custom_checkbox-1')->hasAttribute('checked'))->toBeTrue();
    expect($xpath->query('//*[@data-required-checkbox-group]'))->toHaveCount(1);
})->with(['horizontal', 'vertical']);
