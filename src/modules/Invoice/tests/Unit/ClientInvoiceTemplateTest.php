<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

// cspell:ignore demohash

use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;

function clientInvoiceListTemplate(): Environment
{
    $twig = new Environment(new ArrayLoader([
        'partial_page_heading.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/client/html/partial_page_heading.html.twig'),
        'partial_page_heading_shared.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/shared/html/partial_page_heading_shared.html.twig'),
        'partial_client_empty.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/client/html/partial_client_empty.html.twig'),
        'partial_empty_state.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/shared/html/partial_empty_state.html.twig'),
        'partial_card_heading.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/client/html/partial_card_heading.html.twig'),
        'partial_card_heading_shared.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/shared/html/partial_card_heading_shared.html.twig'),
        'invoices' => (new Filesystem())->readFile(PATH_MODS . '/Invoice/templates/client/mod_invoice_index.html.twig'),
        'layout_default.html.twig' => '{% block content %}{% endblock %}',
        'partial_pagination.html.twig' => '{{ page_param }}{{ hash }}',
    ]), ['autoescape' => 'html']);
    $twig->addFilter(new TwigFilter('trans', static fn (string $text, array $params = []): string => strtr($text, $params)));
    $twig->addFilter(new TwigFilter('url', static fn (string $path): string => '/' . $path));
    $twig->addFilter(new TwigFilter('format_date', static fn (?string $date): string => $date ?? ''));
    $twig->addFilter(new TwigFilter('format_currency', static fn (mixed $amount, string $currency): string => $amount . ' ' . $currency));

    return $twig;
}

test('client invoice list prioritizes due payments and preserves separate history pagination', function (): void {
    $client = new class {
        public array $queries = [];

        public function invoice_get_list(array $data): array
        {
            $this->queries[$data['status']] = $data;
            $list = [];
            foreach (['-3 days', 'today', '+7 days', null] as $index => $due) {
                $list[] = [
                    'hash' => 'invoice' . $index,
                    'serie_nr' => 'DEMO' . $index,
                    'due_at' => $due === null ? null : (new DateTimeImmutable($due))->format('Y-m-d H:i:s'),
                    'created_at' => '2026-01-01', 'paid_at' => '2026-01-02',
                    'total' => 25, 'currency' => 'USD',
                    'lines' => [['title' => '<Hosting>']],
                ];
            }

            return ['total' => count($list), 'list' => $list];
        }
    };
    $html = clientInvoiceListTemplate()->render('invoices', [
        'client' => $client,
        'request' => ['page' => 2, 'paid_page' => 3],
    ]);

    expect($client->queries['unpaid'])->toMatchArray(['sort' => 'due_at', 'direction' => 'ASC', 'page' => 2])
        ->and($client->queries['paid'])->toMatchArray(['sort' => 'id', 'direction' => 'DESC', 'page' => 3])
        ->and($client->queries['refunded']['page'])->toBe(1)
        ->and($html)->toContain('Overdue', 'Due Today', 'No Due Date', 'Amount Due', '25 USD', 'Pay Invoice', '&lt;Hosting&gt;')
        ->and($html)->toContain('href="/invoice/invoice0"', 'aria-label="Pay Invoice #DEMO0"', 'aria-labelledby="unpaid-tab"')
        ->and($html)->toContain('paid_page#nav-paid', 'refunded_page#nav-refunded', 'canceled_page#nav-canceled');
    $document = new DOMDocument();
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//*[@id="nav-paid"]//a[contains(@class,"btn-primary")]')->length)->toBe(0);
});

test('client invoice list provides a clear all paid state without payment links', function (): void {
    $client = new class {
        public function invoice_get_list(array $data): array
        {
            return ['total' => 0, 'list' => []];
        }
    };
    $html = clientInvoiceListTemplate()->render('invoices', ['client' => $client, 'request' => []]);
    expect($html)->toContain('No Unpaid Invoices', 'There is nothing to pay right now.', 'No Paid Invoices Yet')
        ->and($html)->not->toContain('Pay Invoice', 'The list is empty');
});

test('invoice payment choices respect currency and subscription compatibility', function (string $status, bool $subscribable, array $currencies, bool $single, bool $recurrent, bool $visible): void {
    $twig = clientInvoiceListTemplate();
    $twig->getLoader()->setTemplate('payment', (new Filesystem())->readFile(PATH_MODS . '/Invoice/templates/client/mod_invoice_invoice.html.twig'));
    $twig->getLoader()->setTemplate('macro_functions.html.twig', '{% macro status_badge(status, type) %}{{ status }}{% endmacro %}');
    foreach (['country_name', 'markdown_to_html', 'period_title'] as $filter) {
        $twig->addFilter(new TwigFilter($filter, static fn (?string $text): string => $text ?? ''));
    }
    $html = $twig->render('payment', [
        'invoice' => [
            'status' => $status, 'issued' => true, 'serie_nr' => 'DEMO1', 'hash' => 'demohash',
            'currency' => 'USD', 'subscribable' => $subscribable, 'due_at' => null,
            'total' => 25, 'seller' => [], 'buyer' => [], 'lines' => [],
        ],
        'guest' => ['invoice_gateways' => [[
            'id' => 7, 'title' => 'Demo "Payment" <Method>', 'accepted_currencies' => $currencies,
            'allow_single' => $single, 'allow_recurrent' => $recurrent, 'logo' => ['logo' => '/gateway.svg'],
        ]]],
        'client' => true, 'settings' => [], 'request' => [],
    ]);
    if ($visible) {
        expect($html)->toContain('Choose a Payment Method', 'data-gateway-id="7"', '25 USD')
            ->and($html)->toContain('data-gateway-title="Demo &quot;Payment&quot; &lt;Method&gt;"')
            ->and($html)->not->toContain('onclick=');
    } else {
        expect($html)->not->toContain('data-gateway-id="7"');
        if ($status === 'unpaid') {
            expect($html)->toContain('No payment methods are currently available', 'Contact Support');
        } else {
            expect($html)->not->toContain('invoice-payment-heading');
        }
    }
})->with([
    'single payment' => ['unpaid', false, ['USD'], true, false, true],
    'subscription payment' => ['unpaid', true, ['USD'], false, true, true],
    'incompatible currency' => ['unpaid', false, ['EUR'], true, false, false],
    'subscription unavailable for invoice' => ['unpaid', false, ['USD'], false, true, false],
    'paid invoice' => ['paid', true, ['USD'], true, true, false],
    'canceled invoice' => ['canceled', true, ['USD'], true, true, false],
]);

function renderClientInvoiceDetails(array $invoiceOverrides = [], array $contextOverrides = []): string
{
    $twig = clientInvoiceListTemplate();
    $twig->getLoader()->setTemplate('layout_default.html.twig', '{% block content %}{% endblock %}{% block js %}{% endblock %}');
    $twig->getLoader()->setTemplate('details', (new Filesystem())->readFile(PATH_MODS . '/Invoice/templates/client/mod_invoice_invoice.html.twig'));
    $twig->getLoader()->setTemplate('macro_functions.html.twig', '{% macro status_badge(status, type) %}<span>{{ status }}</span>{% endmacro %}');
    foreach (['country_name', 'markdown_to_html', 'period_title'] as $filter) {
        $twig->addFilter(new TwigFilter($filter, static fn (?string $text): string => $text ?? ''));
    }

    return $twig->render('details', array_replace([
        'client' => true, 'profile' => ['id' => 2, 'balance' => 6, 'currency' => 'USD'],
        'request' => [], 'settings' => [], 'guest' => ['invoice_gateways' => []],
    ], $contextOverrides, ['invoice' => array_replace([
        'id' => 24, 'client_id' => 2, 'issued' => true, 'status' => 'unpaid', 'serie_nr' => 'DEMO24', 'hash' => 'demo-hash',
        'currency' => 'USD', 'subscribable' => false, 'credit_note_for_invoice_id' => null, 'debit_note_for_invoice_id' => null,
        'created_at' => '2026-10-01', 'paid_at' => null, 'due_at' => '2026-10-25',
        'subtotal' => 18, 'tax' => 3.6, 'taxrate' => 20, 'taxname' => 'VAT', 'total' => 21.6,
        'seller' => ['company' => 'Snapshot seller', 'company_vat' => 'VAT-123'],
        'buyer' => ['first_name' => 'Example', 'last_name' => 'Client'],
        'text_1' => 'Before the charges', 'text_2' => 'After the totals',
        'lines' => [
            ['title' => 'Hosting <plan>', 'quantity' => 2, 'unit' => 'service', 'period' => '1M', 'price' => 10, 'total' => 20, 'taxed' => true, 'order_id' => 5, 'type' => 'order'],
            ['title' => 'Discount', 'quantity' => 1, 'unit' => 'discount', 'price' => -2, 'total' => -2, 'taxed' => true, 'order_id' => null, 'type' => 'custom'],
        ],
    ], $invoiceOverrides)]));
}

test('invoice charges retain quantities, negative adjustments, tax and document details', function (): void {
    $html = renderClientInvoiceDetails();
    expect($html)->toContain('Hosting &lt;plan&gt;', '20 USD', '-2 USD', '18 USD', '3.6 USD', '21.6 USD', 'VAT (20%)', 'Taxable', 'Unit Price', 'Quantity', 'Snapshot seller', 'VAT-123', 'Before the charges', 'After the totals', '/invoice/pdf/demo-hash', '/invoice/print/demo-hash', '/order/service/manage/5')
        ->and(strpos($html, 'id="invoice-charges-heading"'))->toBeLessThan(strpos($html, 'id="invoice-details-heading"'));
});

test('invoice states only offer payment for an issued positive unpaid invoice', function (array $invoice, bool $payable, string $message): void {
    $html = renderClientInvoiceDetails($invoice);
    expect(str_contains($html, 'id="invoice-payment-heading"'))->toBe($payable)
        ->and(str_contains($html, 'id="paymentPrompt"'))->toBe($payable)
        ->and($html)->toContain($message);
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    expect((new DOMXPath($dom))->query('//h1')->length)->toBe(1);
})->with([
    'unpaid' => [[], true, 'Amount Due'],
    'paid' => [['status' => 'paid', 'paid_at' => '2026-10-05'], false, 'This invoice has been paid.'],
    'draft' => [['issued' => false], false, 'This is a draft.'],
    'canceled' => [['status' => 'canceled'], false, 'can no longer be paid'],
    'replaced' => [['replaced_by_invoice_id' => 30], false, 'A replacement invoice has been issued.'],
    'refunded' => [['status' => 'refunded', 'refunded_total' => 21.6], false, 'Refunded Amount'],
    'credit note' => [['credit_note_for_invoice_id' => 20, 'total' => -21.6, 'tax' => -3.6, 'subtotal' => -18], false, 'No payment is required.'],
    'debit note' => [['debit_note_for_invoice_id' => 20], true, 'Debit Note'],
    'zero total' => [['total' => 0, 'subtotal' => 0, 'tax' => 0], false, 'No payment is due'],
]);

test('payment date stays separate from the invoice creation date', function (): void {
    $html = renderClientInvoiceDetails(['status' => 'paid', 'paid_at' => '2026-10-05']);
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    expect(trim($xpath->query('//div[dt="Invoice Date"]/dd')->item(0)->textContent))->toBe('2026-10-01')
        ->and(trim($xpath->query('//div[dt="Payment Date"]/dd')->item(0)->textContent))->toBe('2026-10-05');
});

test('account balance payment is limited to the owner, currency and non-deposit invoices', function (array $profile, bool $signedIn, bool $deposit, bool $visible, bool $disabled): void {
    $invoice = $deposit ? ['lines' => [['title' => 'Add Funds', 'quantity' => 1, 'unit' => 'deposit', 'price' => 21.6, 'total' => 21.6, 'order_id' => null, 'type' => 'deposit']]] : [];
    $html = renderClientInvoiceDetails($invoice, [
        'client' => $signedIn, 'profile' => $profile,
        'guest' => ['invoice_gateways' => [['id' => 7, 'code' => 'ClientBalance', 'title' => 'ClientBalance', 'allow_single' => true, 'allow_recurrent' => false, 'accepted_currencies' => ['USD'], 'logo' => []]]],
    ]);
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $button = (new DOMXPath($dom))->query('//button[@data-gateway-id="7"]')->item(0);
    expect($button !== null)->toBe($visible);
    if ($visible) {
        expect($button->hasAttribute('disabled'))->toBe($disabled)
            ->and($html)->toContain('Account Balance', 'Available Balance');
    }
})->with([
    'insufficient credit' => [['id' => 2, 'balance' => 6, 'currency' => 'USD'], true, false, true, true],
    'sufficient credit' => [['id' => 2, 'balance' => 30, 'currency' => 'USD'], true, false, true, false],
    'different client' => [['id' => 3, 'balance' => 30, 'currency' => 'USD'], true, false, false, false],
    'different currency' => [['id' => 2, 'balance' => 30, 'currency' => 'EUR'], true, false, false, false],
    'guest' => [[], false, false, false, false],
    'deposit' => [['id' => 2, 'balance' => 30, 'currency' => 'USD'], true, true, false, false],
]);

test('automatic subscription mode still explains recurring payment before the client continues', function (): void {
    $html = renderClientInvoiceDetails(['subscribable' => true], ['settings' => ['prompt_subscription' => false]]);
    expect($html)->toContain('This payment sets up recurring payments for future invoices as well as paying this invoice.', 'Pay and Set Up Recurring Payments', "singleLink.searchParams.set('allow_subscription', '0')");
});

test('credit notes show their document state without an unpaid payment label', function (): void {
    $html = renderClientInvoiceDetails(['credit_note_for_invoice_id' => 20]);
    expect($html)->toContain('Credit Note Issued', 'No payment is required.')
        ->not->toContain('<span>unpaid</span>', 'invoice-payment-method');
});

test('gateway return hints never override the recorded invoice payment status', function (string $status): void {
    $pending = renderClientInvoiceDetails([], ['request' => ['status' => $status]]);
    $paid = renderClientInvoiceDetails(['status' => 'paid'], ['request' => ['status' => $status]]);
    expect($pending)->toContain('Payment is not confirmed', 'Check Payment Status')
        ->and($paid)->toContain('This invoice has been paid.')
        ->not->toContain('Payment is not confirmed');
})->with(['ok', 'cancel']);

test('payment dialog description includes invoice amount and payment terms without hiding charge headers', function (): void {
    $html = renderClientInvoiceDetails();
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $dialog = $dom->getElementById('paymentPrompt');
    $description = preg_split('/\s+/', $dialog->getAttribute('aria-describedby'));
    expect($description)->toBe(['paymentPromptAmount', 'bodyText'])
        ->and($dom->getElementById($description[0])->textContent)->toContain('21.6 USD', '(USD)')
        ->and($dom->getElementById($description[1]))->not->toBeNull();
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//table[@aria-labelledby="invoice-charges-heading"]//th[@scope="col"]'))->toHaveCount(4)
        ->and($xpath->query('//span[contains(@class,"invoice-cell-label") and @aria-hidden="true"]'))->toHaveCount(6);
});
