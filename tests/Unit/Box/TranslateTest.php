<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

test('set locale', function (): void {
    $locale = 'en_US';
    $translateObj = new Box_Translate();
    $translateObj->setLocale($locale);
    $result = $translateObj->getLocale();

    expect($result)->toEqual($locale);
});

test('domain setter and getter', function (): void {
    $translateObj = new Box_Translate();

    $default = 'messages';
    $result = $translateObj->getDomain();
    expect($result)->toEqual($default);

    $newDomain = 'admin';
    $result = $translateObj->setDomain($newDomain)->getDomain();
    expect($result)->toEqual($newDomain);
});

test('translate', function (): void {
    $text = 'Translate ME';
    $translateObj = new Box_Translate();
    $translateObj->setup();
    $result = __trans($text);

    expect($result)->toEqual($text);
});

test('setup configures the default locale used by Twig Intl filters', function (): void {
    $defaultLocale = Locale::getDefault();
    Locale::setDefault('en_US_POSIX');

    try {
        $translateObj = new Box_Translate();
        $translateObj->setLocale('en_US');
        $translateObj->setup();

        $intlExtension = new Twig\Extra\Intl\IntlExtension();

        expect(Locale::getDefault())->toBe('en_US')
            ->and($intlExtension->formatCurrency(5, 'USD'))->toBe('$5.00');
    } finally {
        Locale::setDefault($defaultLocale);
    }
});
