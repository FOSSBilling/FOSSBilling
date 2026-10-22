<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Tests\Support\ClientThemeRenderer;
use Tests\Support\PermissiveStub;

require_once PATH_TESTS . '/Support/ClientThemeRenderer.php';

test('client shell renders one account menu and a shared active destination on both navigation surfaces', function (): void {
    $html = ClientThemeRenderer::render();
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    expect($xpath->query('//div[contains(@class, "client-dropdown")]//button[@data-bs-toggle="dropdown"]'))->toHaveCount(1)
        ->and($xpath->query('//ul[contains(@class, "client-menu")]//a[@aria-current="page"]'))->toHaveCount(2)
        ->and($xpath->query('//main[@id="main-content" and @tabindex="-1"]'))->toHaveCount(1)
        ->and($xpath->query('//a[@href="#main-content"]'))->toHaveCount(1)
        ->and($xpath->query('//ul[contains(@class, "client-menu")]//a[normalize-space(.)="Dashboard"]'))->toHaveCount(2)
        ->and($xpath->query('//ul[contains(@class, "client-header-menu")]/li/a[normalize-space(.)="Dashboard" or normalize-space(.)="Order"]'))->toHaveCount(0);
});

test('client shell respects hidden guest navigation and logo settings while using the full content width', function (): void {
    $html = ClientThemeRenderer::render([
        'client' => null,
        'settings' => ['hide_menu' => true, 'show_company_logo' => false],
        'guest' => new PermissiveStub(['system_company' => new PermissiveStub([
            'name' => 'Acme Hosting', 'logo_url' => '/light.svg', 'logo_url_dark' => '/dark.svg', 'bank_info_pagebottom' => false,
        ])]),
    ]);

    expect($html)->toContain('class="col-12 client-main-column"')
        ->toContain('Acme Hosting')
        ->not->toContain('client-sidebar')
        ->not->toContain('client-menu"')
        ->not->toContain('src="/light.svg"')
        ->not->toContain('src="/dark.svg"');
});

test('client navigation keeps enabled links on mobile with legacy header settings', function (): void {
    $html = ClientThemeRenderer::render([
        'client' => null,
        'current_active_menu' => 'dashboard',
        'settings' => ['hide_menu' => false, 'side_menu_dashboard' => true, 'side_menu_order' => true, 'top_menu_dashboard' => true, 'top_menu_order' => true],
    ], 'mobile_menu.html.twig');

    expect($html)->toContain('Dashboard')->toContain('Order')->toContain('aria-current="page"');
});

test('client shell keeps extension hooks around the header content and footer', function (): void {
    $html = ClientThemeRenderer::render();

    foreach (['body.start', 'header.start', 'header.end', 'content.before', 'content.after', 'footer.start', 'footer.end', 'body.end'] as $slot) {
        expect(substr_count($html, '<!-- widget:client.theme.' . $slot . ' -->'))->toBe(1);
    }

    expect($html)->toContain('src="/logo.svg"')->toContain('src="/logo-dark.svg"');
});

test('client navigation respects module availability and disabled destinations on both surfaces', function (): void {
    $html = ClientThemeRenderer::render([
        'settings' => ['side_menu_services' => false, 'side_menu_invoices' => false],
        'guest' => new PermissiveStub([
            'system_company' => new PermissiveStub(['bank_info_pagebottom' => false]),
            'extension_is_on' => false,
            'support_kb_enabled' => false,
            'invoice_funds_enabled' => false,
        ]),
    ]);
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    foreach (['Services', 'Invoices', 'News', 'Knowledge Base'] as $label) {
        expect($xpath->query('//ul[contains(@class, "client-menu")]//a[normalize-space(.)="' . $label . '"]'))->toHaveCount(0);
    }
});

test('notification menu renders unread state and escapes ticket subjects', function (): void {
    $html = ClientThemeRenderer::render(['client' => new PermissiveStub([
        'support_notification_get_list' => [
            'unread_count' => 1, 'latest_id' => 3,
            'list' => [
                ['id' => 3, 'ticket_id' => 12, 'message_id' => 45, 'subject' => '<script>unsafe</script>', 'created_at' => '2026-10-20 12:00:00', 'read_at' => null],
            ],
        ],
    ])]);

    expect($html)->toContain('data-notification-id="3"')
        ->toContain('href="/support/ticket/12#message-45"')
        ->toContain('&lt;script&gt;unsafe&lt;/script&gt;')
        ->not->toContain('<script>unsafe</script>');
});

test('guest header does not render a client notification menu', function (): void {
    expect(ClientThemeRenderer::render(['client' => null]))->not->toContain('data-client-notifications');
});

test('wallet history remains reachable when adding funds is disabled', function (): void {
    $html = ClientThemeRenderer::render([
        'guest' => new PermissiveStub(['system_company' => new PermissiveStub(['bank_info_pagebottom' => false]), 'invoice_funds_enabled' => false]),
    ]);
    expect(substr_count($html, 'href="/client/balance"'))->toBe(2);
    $hidden = ClientThemeRenderer::render(['settings' => ['side_menu_payments' => false]]);
    expect($hidden)->not->toContain('href="/client/balance"');
});

test('shared navigation labels match the email page and highlight profile in the account menu', function (): void {
    $html = ClientThemeRenderer::render(['active_menu' => 'profile', 'current_active_menu' => 'profile']);
    expect($html)->toContain('href="/client/profile" aria-current="page"', 'Emails');
});

test('back to top stays in document flow and respects its setting', function (): void {
    $html = ClientThemeRenderer::render(['settings' => ['footer_to_top_enabled' => true]]);
    expect($html)->toContain('href="#top" class="btn btn-outline-secondary"', 'Back to Top')
        ->and($html)->not->toContain('href="#top" class="position-fixed');
    expect(ClientThemeRenderer::render(['settings' => ['footer_to_top_enabled' => false]]))->not->toContain('href="#top"');
});

test('client layouts establish separate message regions and keep sidebar amounts out of heading navigation', function (): void {
    foreach (['layout_default.html.twig', 'layout_public.html.twig'] as $template) {
        $html = ClientThemeRenderer::render(['settings' => ['sidebar_balance_enabled' => true]], $template);
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        expect($xpath->query('//*[@data-client-announcement="status" and @role="status" and @aria-atomic="true"]'))->toHaveCount(1)
            ->and($xpath->query('//*[@data-client-announcement="error" and @role="alert" and not(@aria-live)]'))->toHaveCount(1)
            ->and($xpath->query('//ul[contains(@class,"client-menu")]//*[self::h5 or self::h6]'))->toHaveCount(0);
    }
});

test('client header keeps configured logos on mobile instead of substituting the company name', function (): void {
    $dom = new DOMDocument();
    @$dom->loadHTML(ClientThemeRenderer::render());
    $xpath = new DOMXPath($dom);
    $images = $xpath->query('//a[contains(@class,"navbar-brand")]/img');

    expect($images)->toHaveCount(2)
        ->and($xpath->query('//a[contains(@class,"navbar-brand")]/span'))->toHaveCount(0);
    foreach ($images as $image) {
        expect($image->getAttribute('class'))->not->toContain('d-none', 'd-sm-block');
    }
});

test('client notification panel keeps a named trigger relationship', function (): void {
    $html = ClientThemeRenderer::render();
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//button[@id="client-notification-toggle" and @aria-controls="client-notification-menu"]'))->toHaveCount(1)
        ->and($xpath->query('//div[@id="client-notification-menu" and @aria-labelledby="client-notification-toggle"]'))->toHaveCount(1);
});
