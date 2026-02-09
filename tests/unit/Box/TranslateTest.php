<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

test('set locale', function () {
    $locale = 'en_US';
    $translateObj = new Box_Translate();
    $translateObj->setLocale($locale);
    $result = $translateObj->getLocale();

    expect($result)->toEqual($locale);
});

test('domain setter and getter', function () {
    $translateObj = new Box_Translate();

    $default = 'messages';
    $result = $translateObj->getDomain();
    expect($result)->toEqual($default);

    $newDomain = 'admin';
    $result = $translateObj->setDomain($newDomain)->getDomain();
    expect($result)->toEqual($newDomain);
});

test('translate', function () {
    $text = 'Translate ME';
    $translateObj = new Box_Translate();
    $translateObj->setup();
    $result = $translateObj->__($text);

    expect($result)->toEqual($text);
});
