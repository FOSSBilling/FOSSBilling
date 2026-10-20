<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Tests\Support\PermissiveStub;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

test('product settings only load forms when staff can view them', function (bool $enabled, bool $allowed): void {
    $admin = new class($allowed) {
        public int $formLookups = 0;

        public function __construct(private readonly bool $allowed)
        {
        }

        public function __call(string $name, array $arguments): mixed
        {
            if ($name === 'system_template_exists') {
                return false;
            }

            if ($name === 'formbuilder_get_pairs') {
                ++$this->formLookups;
                if (!$this->allowed) {
                    throw new FOSSBilling\Exception('You do not have access to the formbuilder module');
                }

                return [3 => 'Example form'];
            }

            return new PermissiveStub();
        }
    };
    $twig = new Environment(new ChainLoader([
        new ArrayLoader([
            'layout_default.html.twig' => '{% block content %}{% endblock %}',
            'partial_admin_detail_tabs.html.twig' => '{% block detail_tab_content %}{% endblock %}',
            'partial_admin_form_card.html.twig' => '{% block form_content %}{% endblock %}',
            'partial_admin_related_section.html.twig' => '',
            'partial_pricing.html.twig' => '',
            'macro_functions.html.twig' => '{% macro selectbox(name, pairs, selected, empty, label) %}<select name="{{ name }}">{% for id, title in pairs %}<option value="{{ id }}">{{ title }}</option>{% endfor %}</select>{% endmacro %}',
        ]),
        new FilesystemLoader(PATH_MODS . '/Product/templates/admin'),
    ]), ['strict_variables' => true]);
    foreach (['trans', 'api_url', 'markdown_to_html'] as $filter) {
        $twig->addFilter(new TwigFilter($filter, static fn (mixed $value, mixed ...$arguments): string => (string) $value));
    }
    $twig->addFilter(new TwigFilter('url', static fn (string $value, ?array $query = null, ?string $area = null): string => '/' . $value));
    foreach (['fb_api_form', 'wysiwyg'] as $function) {
        $twig->addFunction(new TwigFunction($function, static fn (mixed ...$arguments): string => ''));
    }
    $twig->addFunction(new TwigFunction('has_permission', function (string $module, ?string $permission = null) use ($allowed): bool {
        expect($module)->toBe('formbuilder')->and($permission)->toBe('view');

        return $allowed;
    }));

    $html = $twig->render('mod_product_manage.html.twig', [
        'product' => new PermissiveStub(['type' => 'custom', 'form_id' => 3]),
        'guest' => new PermissiveStub(['extension_is_on' => $enabled, 'system_template_exists' => false]),
        'admin' => $admin,
    ]);

    expect($html)->toContain('name="title"')
        ->and($admin->formLookups)->toBe($enabled && $allowed ? 1 : 0);
    if ($enabled && $allowed) {
        expect($html)->toContain('name="form_id"')->toContain('Example form');
    } else {
        expect($html)->not->toContain('name="form_id"');
    }
})->with([[true, false], [true, true], [false, false], [false, true]]);
