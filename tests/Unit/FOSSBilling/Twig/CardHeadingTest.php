<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

function cardHeadingTwig(string $theme): Environment
{
    return new Environment(new FilesystemLoader([
        PATH_THEMES . '/default/' . $theme . '/html', PATH_THEMES . '/default/shared/html',
    ]), ['strict_variables' => true, 'autoescape' => 'html']);
}

test('heading blocks preserve existing heading IDs levels and extension action attributes', function (string $theme): void {
    $html = cardHeadingTwig($theme)->createTemplate(<<<'TWIG'
        {% embed 'partial_card_heading.html.twig' with {heading_actions_class: 'extension-actions', heading_content_class: 'legacy-heading'} %}
            {% block card_heading_content %}<h1 id="service-title">Service Details</h1>{% endblock %}
            {% block card_heading_actions %}<button type="button" id="extension-action" data-extension-action="manage">Manage</button>{% endblock %}
        {% endembed %}
        TWIG)->render();
    expect($html)->toContain('<h1 id="service-title">', 'legacy-heading', 'extension-actions', 'ms-auto', 'data-extension-action="manage"');
    expect(substr_count($html, 'id="extension-action"'))->toBe(1);
})->with(['client', 'admin']);

test('admin form card keeps legacy blocks classes and footer actions', function (): void {
    $html = cardHeadingTwig('admin')->createTemplate(<<<'TWIG'
        {% embed 'partial_admin_form_card.html.twig' with {title: 'Edit <account>', subtitle: 'Details', card_class: 'custom-card', body_class: 'custom-body', footer_align: 'start'} %}
            {% block form_header_actions %}<button id="header-action">Header</button>{% endblock %}
            {% block form_content %}<input id="extension-field" name="extension[value]" value="kept">{% endblock %}
            {% block form_actions %}<button id="save-action">Save</button>{% endblock %}
        {% endembed %}
        TWIG)->render();
    expect($html)->toContain('card custom-card', 'card-body custom-body', 'justify-content-start', '<h5 class="card-title mb-0">Edit &lt;account&gt;</h5>');
    foreach (['header-action', 'extension-field', 'save-action'] as $id) {
        expect(substr_count($html, 'id="' . $id . '"'))->toBe(1);
    }
});

test('admin summary keeps its avatar content and footer blocks without duplicating them', function (): void {
    $calls = 0;
    $twig = cardHeadingTwig('admin');
    $twig->addFunction(new Twig\TwigFunction('extension_aside', static function () use (&$calls): string {
        ++$calls;

        return '';
    }));
    $html = $twig->createTemplate(<<<'TWIG'
        {% embed 'partial_admin_summary_card.html.twig' with {title: 'Client Summary', centered_actions: true} %}
            {% block summary_header_aside %}{{ extension_aside() }}<img id="client-avatar" alt="" src="/custom-avatar.svg">{% endblock %}
            {% block summary_content %}<div id="summary-details">Details</div>{% endblock %}
            {% block summary_actions %}<button id="summary-action">Manage</button>{% endblock %}
        {% endembed %}
        TWIG)->render();
    foreach (['client-avatar', 'summary-details', 'summary-action'] as $id) {
        expect(substr_count($html, 'id="' . $id . '"'))->toBe(1);
    }
    expect($html)->toContain('fb-card-heading-actions', 'btn-list justify-content-center');
    expect($calls)->toBe(1);
});

test('admin detail tabs retain tab content blocks and card customization', function (): void {
    $html = cardHeadingTwig('admin')->createTemplate(<<<'TWIG'
        {% embed 'partial_admin_detail_tabs.html.twig' with {title: 'Details', outer_card_class: 'outer-custom', inner_card_class: 'inner-custom', tab_content_class: 'content-custom'} %}
            {% block detail_tabs %}<li><a id="custom-tab" href="#custom-pane" data-bs-toggle="tab">Custom</a></li>{% endblock %}
            {% block detail_tab_content %}<div id="custom-pane" class="tab-pane">Contents</div>{% endblock %}
        {% endembed %}
        TWIG)->render();
    expect($html)->toContain('outer-custom', 'inner-custom', 'content-custom', '<h3 class="card-title">Details</h3>');
    expect(substr_count($html, 'id="custom-tab"'))->toBe(1);
    expect(substr_count($html, 'id="custom-pane"'))->toBe(1);
});
