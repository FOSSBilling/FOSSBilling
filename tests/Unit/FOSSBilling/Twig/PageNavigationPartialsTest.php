<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

function pageNavigationTwig(string $theme): Environment
{
    $twig = new Environment(new FilesystemLoader([PATH_THEMES . '/default/' . $theme . '/html', PATH_THEMES . '/default/shared/html']), ['strict_variables' => true, 'autoescape' => 'html']);
    $twig->addFilter(new TwigFilter('trans', static fn (string $text): string => $text));

    return $twig;
}

test('both themes share a named breadcrumb landmark and preserve captured extension lists', function (string $theme): void {
    $twig = pageNavigationTwig($theme);
    $html = $twig->createTemplate(<<<'TWIG'
        {% set breadcrumb_items %}<li class="breadcrumb-item"><a href="/parent?extension=1">Parent</a></li><li class="breadcrumb-item active" aria-current="page">{{ subject }}</li>{% endset %}
        {% include 'partial_breadcrumbs.html.twig' with {breadcrumb_class: 'extension-trail'} %}
        TWIG)->render(['subject' => '<long subject>']);
    expect($html)->toContain('aria-label="Breadcrumb"', 'extension-trail', '<ol class="breadcrumb mb-0">', 'aria-current="page"', '&lt;long subject&gt;', 'href="/parent?extension=1"')->not->toContain('<long subject>');
    $custom = $twig->createTemplate(<<<'TWIG'
        {% set breadcrumb_list %}<ul class="breadcrumb extension-list"><li id="extension-crumb">Custom</li></ul>{% endset %}
        {% include 'partial_breadcrumbs.html.twig' %}
        TWIG)->render();
    expect(substr_count($custom, 'id="extension-crumb"'))->toBe(1);
    expect($custom)->toContain('<ul class="breadcrumb extension-list">')->not->toContain('<ol');
})->with(['client', 'admin']);

test('page headings preserve a single H1 escaped copy and native extension actions', function (string $theme): void {
    $twig = pageNavigationTwig($theme);
    $html = $twig->createTemplate(<<<'TWIG'
        {% embed 'partial_page_heading.html.twig' with {heading_class: 'legacy-heading', heading_actions_class: 'extension-actions'} %}
            {% block card_heading_actions %}<button type="button" id="new-ticket" data-bs-toggle="modal" data-bs-target="#ticket-dialog">New Ticket</button>{% endblock %}
        {% endembed %}
        TWIG)->render(['title' => '<Page>', 'subtitle' => '<Help>']);
    expect(substr_count($html, '<h1 '))->toBe(1);
    expect(substr_count($html, 'id="new-ticket"'))->toBe(1);
    expect($html)->toContain('&lt;Page&gt;', '&lt;Help&gt;', 'legacy-heading', 'extension-actions', 'data-bs-target="#ticket-dialog"');
    expect($twig->render('partial_page_heading.html.twig', ['title' => 'Compact', 'page_title_class' => 'card-title mb-0']))->toContain('<h1 class="card-title mb-0">Compact</h1>')->not->toContain('fb-card-heading-actions');
})->with(['client', 'admin']);
