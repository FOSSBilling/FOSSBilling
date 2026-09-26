<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Invoice\Controller\Admin;
use Symfony\Component\HttpFoundation\Request;

use function Tests\Helpers\container;

function journalDownloadEntries(): array
{
    return [
        ['id' => 1, 'type' => 'created', 'admin_id' => null, 'client_id' => 5, 'snapshot' => null, 'created_at' => '2026-01-01 00:00:00'],
        ['id' => 2, 'type' => 'issued', 'admin_id' => 1, 'client_id' => 5, 'snapshot' => ['serie_nr' => 'FOSS-0007'], 'created_at' => '2026-01-02 00:00:00'],
    ];
}

function journalDownloadApp(array $query = []): Box_App
{
    $app = Mockery::mock(Box_App::class);
    $app->shouldReceive('getRequest')
        ->once()
        ->andReturn(Request::create('/', 'GET', $query));

    return $app;
}

function journalDownloadController(array $journal): Admin
{
    $api = new class($journal) {
        public function __construct(private readonly array $journal)
        {
        }

        public function invoice_journal(array $data): array
        {
            return $this->journal;
        }
    };

    $di = container();
    $di['is_admin_logged'] = true;
    $di['api_admin'] = $api;

    $controller = new Admin();
    $controller->setDi($di);

    return $controller;
}

test('journal download returns the full journal as an attachment', function (): void {
    $journal = journalDownloadEntries();

    $response = journalDownloadController($journal)->get_journal_download(journalDownloadApp(), 7);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toBe('application/json')
        ->and($response->headers->get('Content-Disposition'))->toBe('attachment; filename="invoice-7-journal.json"')
        ->and(json_decode($response->getContent(), true))->toBe($journal);
});

test('journal download filters to a single event', function (): void {
    $journal = journalDownloadEntries();

    $response = journalDownloadController($journal)->get_journal_download(journalDownloadApp(['event' => 2]), 7);

    expect(json_decode($response->getContent(), true))->toBe([$journal[1]]);
});

test('journal download with an unknown event returns an empty list', function (): void {
    $response = journalDownloadController(journalDownloadEntries())->get_journal_download(journalDownloadApp(['event' => 999]), 7);

    expect(json_decode($response->getContent(), true))->toBe([]);
});
