<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Tests\Support\PermissiveStub;
use Tests\Support\StrictTemplateRenderer;

test('admin record actions expose names and decorative icons while retaining API confirmations', function (string $module, string $template, string $endpoint, string $editLabel, string $deleteLabel): void {
    $record = new PermissiveStub(['id' => 7, 'status' => 'unpaid', 'cancellable' => true, 'issued' => false, 'period' => null, 'currency' => 'USD', 'total' => 10, 'client' => new PermissiveStub()]);
    $html = (new StrictTemplateRenderer())->renderTemplate(PATH_MODS . '/' . $module . '/templates/admin/' . $template . '.html.twig', [
        'admin' => new PermissiveStub([
            $endpoint => ['list' => [$record], 'total' => 1, 'pages' => 1, 'per_page' => 25],
            'client_get_statuses' => ['active' => 1, 'suspended' => 0, 'canceled' => 0],
        ]),
    ]);
    $document = new DOMDocument();
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    foreach ([$editLabel, $deleteLabel] as $label) {
        $actions = $xpath->query('//a[@aria-label="' . $label . '"]');
        expect($actions)->toHaveCount(1);
        expect($actions->item(0)->hasAttribute('href'))->toBeTrue();
        expect($xpath->query('.//svg[@aria-hidden="true"]', $actions->item(0)))->toHaveCount(1);
    }
    expect($xpath->query('//a[@aria-label="' . $deleteLabel . '"][@data-fb-api]'))->toHaveCount(1);
})->with([
    ['Invoice', 'mod_invoice_index', 'invoice_get_list', 'Manage Invoice', 'Delete Invoice'],
    ['Support', 'mod_support_tickets', 'support_ticket_get_list', 'Reply to Ticket', 'Delete Ticket'],
    ['News', 'mod_news_index', 'news_get_list', 'Edit News', 'Delete News'],
]);
