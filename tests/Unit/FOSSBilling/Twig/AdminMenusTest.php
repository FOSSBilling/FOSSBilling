<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Tests\Support\PermissiveStub;
use Tests\Support\StrictTemplateRenderer;

test('admin menus retain extension destinations active state and named trigger relationships', function (): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(PATH_THEMES . '/default/admin/html/partial_admin_sidebar.html.twig', [
        'current_active_menu' => 'extension',
        'admin' => new PermissiveStub(['extension_get_navigation' => [
            'extension' => ['active' => true, 'label' => 'Custom <Tools>', 'class' => 'settings', 'subpages' => [
                ['uri' => '/extension/custom?type=demo', 'active' => true, 'label' => 'Custom <Page>'],
            ]],
        ]]),
    ]);
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    foreach (['admin-account-menu', 'admin-nav-1', 'admin-nav-help'] as $id) {
        $menu = $xpath->query('//*[@id="' . $id . '"]')->item(0);
        expect($menu)->not->toBeNull();
        $trigger = $dom->getElementById($menu->getAttribute('aria-labelledby'));
        expect($trigger)->not->toBeNull();
        expect($trigger->getAttribute('aria-controls'))->toBe($id);
    }
    expect($html)->toContain('Custom &lt;Tools&gt;', 'Custom &lt;Page&gt;', 'href="/extension/custom?type=demo"', 'aria-current="page"', 'data-bs-auto-close="outside"');
});
