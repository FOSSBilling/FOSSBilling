<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use FOSSBilling\Core\Twig\Extension\FOSSBillingExtension;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use Twig\Extra\Markdown\MarkdownExtension;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

test('admin status badges keep their meaning in solid and subtle variants', function (?string $status, string $type, string $label, string $color): void {
    $twig = new Environment(new FilesystemLoader([
        PATH_THEMES . '/default/admin/html',
        PATH_THEMES . '/default/shared/html',
    ]), ['strict_variables' => true]);
    $twig->addExtension(new AttributeExtension(FOSSBillingExtension::class));
    $twig->addExtension(new MarkdownExtension());
    $twig->addRuntimeLoader(new FactoryRuntimeLoader([
        FOSSBillingExtension::class => static fn () => new FOSSBillingExtension(null),
    ]));
    $template = $twig->createTemplate(<<<'TWIG'
        {% import 'macro_functions.html.twig' as mf %}
        {{ mf.status_badge(status, type, subtle) }}
        TWIG);

    foreach ([false, true] as $subtle) {
        $html = $template->render(compact('status', 'type', 'subtle'));
        $badgeClass = $subtle ? "bg-$color-subtle text-$color-emphasis" : "text-bg-$color";

        expect($html)->toContain('class="badge ' . $badgeClass . '"')
            ->and(trim(strip_tags($html)))->toBe($label);
    }
})->with([
    'ticket awaiting staff' => ['open', 'ticket', 'Awaiting Staff', 'warning'],
    'ticket awaiting client' => ['on_hold', 'ticket', 'Awaiting Client', 'info'],
    'closed ticket' => ['closed', 'ticket', 'Closed', 'secondary'],
    'case-insensitive ticket' => ['OPEN', 'ticket', 'Awaiting Staff', 'warning'],
    'active client' => ['active', 'client', 'Active', 'success'],
    'suspended client' => ['suspended', 'client', 'Suspended', 'danger'],
    'inactive admin' => ['inactive', 'admin', 'Inactive', 'secondary'],
    'unpaid invoice' => ['unpaid', 'invoice', 'Unpaid', 'warning'],
    'refunded invoice' => ['refunded', 'invoice', 'Refunded', 'info'],
    'canceled order' => ['canceled', 'order', 'Canceled', 'secondary'],
    'failed gateway transaction' => ['failed', 'gateway_tx', 'Failed', 'danger'],
    'unknown status' => ['custom_status', 'ticket', 'Custom Status', 'primary'],
    'unknown type uses fallback' => ['open', 'custom', 'Open', 'success'],
    'empty status' => [null, 'ticket', '-', 'primary'],
]);
