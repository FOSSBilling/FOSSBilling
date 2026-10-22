<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

function statusBadgeTwig(string $theme, ?Closure $translate = null): Environment
{
    $twig = new Environment(new FilesystemLoader([
        PATH_THEMES . '/default/' . $theme . '/html',
        PATH_THEMES . '/default/shared/html',
    ]), ['autoescape' => 'html', 'strict_variables' => true]);
    $twig->addFilter(new TwigFilter('trans', $translate ?? static fn (string $text): string => $text));

    // Other admin macros use these filters; they are not invoked by badge tests.
    $twig->addFilter(new TwigFilter('markdown_to_html', static fn (string $text): string => $text));
    $twig->addFilter(new TwigFilter('url', static fn (string $text, array $query = []): string => $text));

    return $twig;
}

test('both status badge entrypoints share semantics and keep their Tabler palette', function (string $type, mixed $status, string $color, string $label): void {
    foreach (['client', 'admin'] as $theme) {
        $html = statusBadgeTwig($theme)->createTemplate('{% import "macro_functions.html.twig" as mf %}{{ mf.status_badge(status, type) }}')
            ->render(compact('status', 'type'));
        $expectedClass = $theme === 'client'
            ? 'bg-' . $color . '-subtle text-' . $color . '-emphasis'
            : 'text-bg-' . $color;

        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $badges = (new DOMXPath($dom))->query('//span');
        expect($badges)->toHaveCount(1);
        expect($badges->item(0)->getAttribute('class'))->toBe('badge ' . $expectedClass);
        expect(trim($badges->item(0)->textContent))->toBe($label);
        expect($html)->not->toContain('<Custom>');
    }
})->with([
    ['order', 'active', 'success', 'Active'],
    ['order', 'pending_setup', 'warning', 'Pending Setup'],
    ['order', 'failed_setup', 'danger', 'Failed Setup'],
    ['order', 'failed_renew', 'danger', 'Failed Renewal'],
    ['order', 'suspended', 'danger', 'Suspended'],
    ['order', 'canceled', 'secondary', 'Canceled'],
    ['ticket', 'open', 'success', 'Open'],
    ['ticket', 'on_hold', 'warning', 'On Hold'],
    ['ticket', 'closed', 'secondary', 'Closed'],
    ['invoice', 'unpaid', 'warning', 'Unpaid'],
    ['invoice', 'PAID', 'success', 'Paid'],
    ['invoice', 'refunded', 'info', 'Refunded'],
    ['invoice', 'canceled', 'secondary', 'Canceled'],
    ['client', 'active', 'success', 'Active'],
    ['client', 'suspended', 'danger', 'Suspended'],
    ['client', 'canceled', 'secondary', 'Canceled'],
    ['admin', 'active', 'success', 'Active'],
    ['admin', 'inactive', 'secondary', 'Inactive'],
    ['subscription', 'pending_cancellation', 'warning', 'Pending Cancellation'],
    ['subscription', 'past_due', 'danger', 'Past Due'],
    ['post', 'published', 'success', 'Published'],
    ['post', 'active', 'success', 'Active'],
    ['post', 'draft', 'secondary', 'Draft'],
    ['product', 'enabled', 'success', 'Enabled'],
    ['product', 'disabled', 'secondary', 'Disabled'],
    ['massmailer', 'sent', 'success', 'Sent'],
    ['massmailer', 'draft', 'secondary', 'Draft'],
    ['transaction', 'approved', 'success', 'Approved'],
    ['transaction', 'processing', 'warning', 'Processing'],
    ['transaction', 'error', 'danger', 'Error'],
    ['gateway_tx', 'completed', 'success', 'Completed'],
    ['gateway_tx', 'requires_action', 'warning', 'Requires Action'],
    ['gateway_tx', 'requires_payment_method', 'danger', 'Requires Payment Method'],
    ['gateway_tx', 'canceled', 'danger', 'Canceled'],
    ['mail_queue', 'sent', 'success', 'Sent'],
    ['mail_queue', 'sending', 'warning', 'Sending'],
    ['mail_queue', 'unsent', 'danger', 'Unsent'],
    ['tx', 'refunded', 'info', 'Refunded'],
    ['tx', 'pending', 'warning', 'Pending'],
    // Extensions retain the distinction between known types and generic fallbacks.
    ['extension', 'closed', 'danger', 'Closed'],
    ['extension', 'active', 'success', 'Active'],
    ['extension', 'pending', 'warning', 'Pending'],
    ['invoice', 'open', 'primary', 'Open'],
    ['extension', '<custom>', 'primary', '<Custom>'],
    ['invoice', null, 'primary', '-'],
    ['invoice', '', 'primary', '-'],
    ['extension', '0', 'primary', '0'],
]);

test('shared badges still translate labels and preserve admin text and boolean macros', function (): void {
    foreach (['client', 'admin'] as $theme) {
        $twig = statusBadgeTwig($theme, static fn (string $text): string => 'Translated <' . $text . '>');
        $html = $twig->createTemplate('{% import "macro_functions.html.twig" as mf %}{{ mf.status_badge("failed_renew", "order") }}')->render();
        expect($html)->toContain('Translated &lt;Failed Renewal&gt;')->not->toContain('Translated <');
    }

    $html = statusBadgeTwig('admin')->createTemplate('{% import "macro_functions.html.twig" as mf %}{{ mf.text_badge("<custom>", "text-bg-info") }}{{ mf.boolean_badge(true, "Yes", "No") }}')->render();
    expect($html)->toContain('badge text-bg-info', '&lt;custom&gt;', 'badge text-bg-success', 'Yes');
});
