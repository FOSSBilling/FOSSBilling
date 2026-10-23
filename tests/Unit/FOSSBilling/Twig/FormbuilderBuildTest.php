<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

test('forms render standalone and within a product', function (bool $embedded): void {
    $guest = new class {
        public function extension_is_on(array $data): bool
        {
            return true;
        }

        public function formbuilder_get(array $data): array
        {
            expect($data)->toBe(['id' => 3]);

            return ['id' => 3, 'name' => 'Example form', 'style' => ['show_title' => '1', 'type' => 'horizontal'], 'fields' => []];
        }
    };
    $twig = new Environment(new FilesystemLoader(PATH_MODS . '/Formbuilder/templates/client'), ['strict_variables' => true]);
    $twig->addFilter(new TwigFilter('markdown_to_html', static fn (string $value): string => $value));
    $context = ['guest' => $guest];
    if ($embedded) {
        $context['product'] = ['id' => 7, 'form_id' => 3];
    } else {
        $context['id'] = 3;
    }

    $html = $twig->render('mod_formbuilder_build.html.twig', $context);

    expect($html)->toContain('Example form')->toContain('name="form_id" value="3"');
    if ($embedded) {
        expect($html)->toContain('name="id" value="7"');
    } else {
        expect($html)->not->toContain('name="id"');
    }
})->with([false, true]);
