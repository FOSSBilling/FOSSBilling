<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Invoice\Service;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

test('invoice PDF footer keeps address keys when company address lines are missing', function (): void {
    $service = new Service();

    $company = [
        'name' => 'Acme',
        'bank_name' => null,
        'account_number' => null,
        'bic' => null,
        'display_bank_info' => null,
        'vat_number' => null,
        'number' => null,
        'www' => 'https://example.com',
        'email' => 'billing@example.com',
        'tel' => null,
        'signature' => null,
        'address_1' => null,
        'address_2' => null,
        'address_3' => null,
    ];

    $method = new ReflectionMethod(Service::class, 'getFooterInfo');
    $method->setAccessible(true);
    $footer = $method->invoke($service, $company);

    expect($footer)->toHaveKeys(['address_1', 'address_2', 'address_3', 'company_name', 'email', 'www', 'signature']);
});

test('default invoice PDF footer renders with strict variables when addresses are missing', function (): void {
    $filesystem = new Filesystem();
    $templatePath = Path::join(PATH_ROOT, 'modules', 'Invoice', 'templates', 'pdf');
    $template = $filesystem->readFile(Path::join($templatePath, 'default-invoice.twig'));

    // Extract only the address-lines block and the footer block so the test
    // does not depend on currency/date extensions registered by TwigFactory.
    $addressStart = strpos($template, '{% set address_lines = [] %}');
    $addressEnd = strpos($template, "{% set address = address_lines|join(', ') %}");
    $footerStart = strpos($template, "<div class='InvoiceFooter'>");
    $footerEnd = strpos($template, '</div>', $footerStart);
    expect($addressStart)->not->toBeFalse();
    expect($addressEnd)->not->toBeFalse();
    expect($footerStart)->not->toBeFalse();
    expect($footerEnd)->not->toBeFalse();

    $fragment = substr($template, $addressStart, $addressEnd - $addressStart + strlen("{% set address = address_lines|join(', ') %}"))
        . "\n"
        . substr($template, $footerStart, $footerEnd - $footerStart + strlen('</div>'));

    $twig = new Environment(new FilesystemLoader($templatePath), [
        'strict_variables' => true,
        'autoescape' => 'html',
    ]);
    $twig->addFilter(new TwigFilter('trans', fn (string $value): string => $value));

    $footer = [
        'company_name' => null,
        'bank_name' => null,
        'account_number' => null,
        'bic' => null,
        'display_bank_info' => null,
        'company_vat' => null,
        'company_number' => null,
        'www' => null,
        'email' => null,
        'phone' => null,
        'signature' => null,
        'address_1' => null,
        'address_2' => null,
        'address_3' => null,
    ];

    $html = $twig->createTemplate($fragment)->render(['footer' => $footer]);

    expect($html)->toContain('InvoiceFooter')
        ->and($html)->not->toContain('address_1');
});
