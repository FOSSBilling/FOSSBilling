<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

function clientComponentsTwig(): Environment
{
    $twig = new Environment(new FilesystemLoader([PATH_THEMES . '/default/client/html', PATH_THEMES . '/default/shared/html']), ['autoescape' => 'html', 'strict_variables' => true]);
    $twig->addFilter(new TwigFilter('trans', static fn (string $text): string => $text));

    return $twig;
}

test('client status badges pair semantic backgrounds with theme-aware text', function (string $status, string $type, string $color, string $label): void {
    $html = clientComponentsTwig()->createTemplate('{% import "macro_functions.html.twig" as mf %}{{ mf.status_badge(status, type) }}')->render(compact('status', 'type'));
    expect($html)->toContain('badge bg-' . $color . '-subtle text-' . $color . '-emphasis', $label);
})->with([
    ['unpaid', 'invoice', 'warning', 'Unpaid'],
    ['paid', 'invoice', 'success', 'Paid'],
    ['refunded', 'invoice', 'info', 'Refunded'],
    ['active', 'order', 'success', 'Active'],
    ['failed_renew', 'order', 'danger', 'Failed Renewal'],
    ['closed', 'ticket', 'secondary', 'Closed'],
    ['<custom>', 'order', 'primary', '&lt;Custom&gt;'],
]);

test('client empty states escape supplied content and only render complete actions', function (array $action, bool $hasAction): void {
    $html = clientComponentsTwig()->render('partial_client_empty.html.twig', $action + [
        'empty_title' => 'No <messages>', 'empty_description' => 'Try <other> keywords.',
    ]);
    expect($html)->toContain('empty-title', 'No &lt;messages&gt;', 'Try &lt;other&gt; keywords.')
        ->not->toContain('<messages>', '<other>', '<svg', '<img');
    if ($hasAction) {
        expect($html)->toContain('empty-action', 'href="/email?search=a&amp;page=1"', 'View &lt;messages&gt;');
    } else {
        expect($html)->not->toContain('empty-action', '<a');
    }
})->with([
    'no action' => [[], false],
    'missing URL' => [['empty_action_label' => 'View messages'], false],
    'missing label' => [['empty_action_url' => '/email'], false],
    'complete action' => [['empty_action_label' => 'View <messages>', 'empty_action_url' => '/email?search=a&page=1'], true],
]);

test('client empty states do not leave blank description or action elements', function (): void {
    $html = clientComponentsTwig()->render('partial_client_empty.html.twig', ['empty_title' => 'No invoices']);
    expect($html)->toContain('No invoices')->not->toContain('empty-subtitle', 'empty-action');
});

test('client select macro preserves options and exposes native labelled required controls', function (): void {
    $twig = clientComponentsTwig();
    $html = $twig->createTemplate('{% import "macro_functions.html.twig" as mf %}{{ mf.selectbox("support_helpdesk_id", options, 2, true, null, "support-helpdesk-id") }}')->render(['options' => [1 => 'General', 2 => '<Billing>']]);
    expect($html)->toContain('name="support_helpdesk_id"', 'id="support-helpdesk-id"', 'class="form-select is-required" required', '&lt;Billing&gt;')
        ->not->toContain('<Billing>');
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//option[@value="2" and @selected]'))->toHaveCount(1);
    $legacy = $twig->createTemplate('{% import "macro_functions.html.twig" as mf %}{{ mf.selectbox("region", {}, null, false) }}')->render();
    expect($legacy)->toContain('id="region"', 'class="form-select"')->not->toContain('required');
});
