<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

function renderClientServiceDetails(array $orderOverrides = [], array $service = [], string $type = 'custom'): array
{
    $fs = new Filesystem();
    $templates = [
        'partial_card_heading.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/client/html/partial_card_heading.html.twig'),
        'partial_card_heading_shared.html.twig' => (new Filesystem())->readFile(PATH_THEMES . '/default/shared/html/partial_card_heading_shared.html.twig'),
        'manage' => $fs->readFile(PATH_MODS . '/Order/templates/client/mod_order_manage.html.twig'),
        'layout_default.html.twig' => '{% block content %}{% endblock %}{% block js %}{% endblock %}',
        'macro_functions.html.twig' => '{% macro status_badge(status, type) %}<span>{{ status }}</span>{% endmacro %}',
        'partial_order_attention.html.twig' => $fs->readFile(PATH_MODS . '/Order/templates/client/partial_order_attention.html.twig'),
        'mod_servicecustom_manage.html.twig' => '<h2>Custom service controls</h2>{{ service.marker }}',
    ];
    if ($type !== 'custom') {
        $templates['mod_service' . $type . '_manage.html.twig'] = $fs->readFile(PATH_MODS . '/Service' . $type . '/templates/client/mod_service' . $type . '_manage.html.twig');
    }
    $twig = new Environment(new ArrayLoader($templates), ['autoescape' => 'html', 'strict_variables' => true]);
    $twig->addFilter(new TwigFilter('trans', static fn (string $text, array $params = []): string => strtr($text, $params)));
    $twig->addFilter(new TwigFilter('url', static fn (string $text): string => '/' . $text));
    $twig->addFilter(new TwigFilter('api_url', static fn (string $text, array $query = []): string => '/api/client/' . $text . '?' . http_build_query($query)));
    foreach (['format_date', 'period_title', 'markdown_to_html'] as $filter) {
        $twig->addFilter(new TwigFilter($filter, static fn (mixed $value): string => (string) $value));
    }
    $twig->addFilter(new TwigFilter('format_currency', static fn (float $value, string $currency): string => $currency . ' ' . number_format($value, 2)));
    $twig->addFunction(new TwigFunction('render_widgets', static fn (string $slot): string => '<span data-widget="' . $slot . '"></span>', ['is_safe' => ['html']]));
    $twig->addFunction(new TwigFunction('wysiwyg', static fn (): string => ''));
    $twig->addFunction(new TwigFunction('fb_api_link', static fn (array $config): string => 'href="' . htmlspecialchars($config['href'] ?? '', ENT_QUOTES) . '"', ['is_safe' => ['html']]));
    $twig->addFunction(new TwigFunction('fb_api_form', static fn (array $config): string => 'method="post" data-fb-api="request"', ['is_safe' => ['html']]));
    $client = new class($service) {
        public int $serviceLookups = 0;

        public function __construct(private array $service)
        {
        }

        public function order_addons(array $data): array
        {
            return [];
        }

        public function order_upgradables(array $data): array
        {
            return [8 => 'Larger plan'];
        }

        public function support_helpdesk_get_pairs(): array
        {
            return [1 => 'Support'];
        }

        public function order_service(array $data): array
        {
            ++$this->serviceLookups;

            return $this->service;
        }
    };
    $html = $twig->render('manage', [
        'order' => array_replace([
            'id' => 5, 'title' => 'Example <service>', 'status' => 'active', 'service_type' => $type,
            'total' => 20, 'discount' => 5, 'currency' => 'USD', 'period' => '1M', 'quantity' => 2,
            'expires_at' => '2026-10-25', 'created_at' => '2026-10-01', 'activated_at' => '2026-10-02',
            'notes' => '', 'group_master' => true, 'meta' => [], 'active_tickets' => 0,
            'can_renew' => true, 'renewal_due' => true,
        ], $orderOverrides),
        'client' => $client,
        'guest' => new Tests\Support\PermissiveStub(['system_template_exists' => true]),
        'request' => ['ajax' => false, 'support_helpdesk_id' => null, 'subject' => null, 'content' => '', 'password' => 'NEVER-ECHO-THIS', 'password_confirm' => 'NEVER-ECHO-THIS'],
    ]);

    return [$html, $client];
}

test('service details prioritise the correct next action for each state', function (array $state, string $message, bool $pay, bool $renew, bool $cancel): void {
    [$html, $client] = renderClientServiceDetails($state, ['marker' => 'Service controls loaded']);
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    expect($html)->toContain($message, 'Example &lt;service&gt;', 'USD 15.00', 'Quantity', 'Price before tax.')
        ->and($xpath->query('//a[@href="/invoice/demo-invoice"]')->length > 0)->toBe($pay)
        ->and($xpath->query('//a[contains(@href,"renewal_invoice")]')->length > 0)->toBe($renew)
        ->and($dom->getElementById('request-cancel') !== null)->toBe($cancel)
        ->and($xpath->query('//h1')->length)->toBe(1)
        ->and($client->serviceLookups)->toBe(($state['status'] ?? 'active') === 'active' ? 1 : 0);
})->with([
    'renewable' => [[], 'Renew Now', false, true, true],
    'unpaid invoice wins over renewal' => [['unpaid_invoice_hash' => 'demo-invoice'], 'There is an unpaid invoice', true, false, true],
    'setup pending' => [['status' => 'pending_setup'], 'Your service is awaiting setup.', false, false, false],
    'setup failed' => [['status' => 'failed_setup'], 'Setup could not be completed.', false, false, false],
    'renewal failed' => [['status' => 'failed_renew'], 'Renewal could not be completed.', false, false, false],
    'suspended' => [['status' => 'suspended'], 'This service is suspended.', false, false, false],
    'canceled' => [['status' => 'canceled'], 'This service has been canceled.', false, false, false],
    'scheduled cancellation' => [['meta' => ['cancel_at_period_end' => true]], 'Scheduled End Date', false, false, false],
    'one time' => [['period' => null, 'can_renew' => false], 'There is no recurring renewal.', false, false, true],
    'renewal unavailable' => [['can_renew' => false], 'Online renewal is not currently available.', false, false, true],
]);

test('service details retain extension slots, service controls and request context', function (): void {
    [$html] = renderClientServiceDetails([], ['marker' => 'Service controls loaded']);
    foreach (['content.start', 'details.after', 'actions.end', 'service.before', 'service.after'] as $slot) {
        expect($html)->toContain('data-widget="client.order.manage.' . $slot . '"');
    }
    expect($html)->toContain('Service controls loaded', 'Send Upgrade Request', 'It does not cancel your service immediately.', 'name="rel_task" value="cancel"', 'name="rel_task" value="upgrade"', 'name="rel_id" value="5"');
    expect($html)->toContain('aria-describedby="cancel-description"', 'Keep Service');
    expect(strpos($html, 'Billing and Renewal'))->toBeLessThan(strpos($html, 'Service controls loaded'))
        ->and(strpos($html, 'Service controls loaded'))->toBeLessThan(strpos($html, 'Service Record'));
});

test('hosting controls label changes and keep password values out of the page', function (): void {
    [$html] = renderClientServiceDetails([], [
        'domain_order_id' => null, 'domain' => 'example.test', 'sld' => 'example', 'tld' => '.test', 'username' => 'demo',
        'server' => ['ip' => '192.0.2.1', 'hostname' => 'server.example.test'],
        'hosting_plan' => ['name' => 'Demo', 'bandwidth' => 'unlimited', 'quota' => 'unlimited'],
    ], 'hosting');
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    expect($html)->not->toContain('NEVER-ECHO-THIS')
        ->toContain('Login to Control Panel', 'data-auth-confirm-password', 'autocomplete="new-password"');
    foreach ($xpath->query('//input[not(@type="hidden")]') as $input) {
        expect($xpath->query('//label[@for="' . $input->getAttribute('id') . '"]')->length)->toBe(1);
    }
    expect($xpath->query('//h1')->length)->toBe(1);
});
