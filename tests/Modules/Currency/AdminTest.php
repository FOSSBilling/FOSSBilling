<?php

declare(strict_types=1);

use APIHelper\Request;

test('gets currency pairs', function (): void {
    $result = Request::makeRequest('admin/currency/get_pairs');

    expect($result->wasSuccessful())->toBeTrue();

    $list = $result->getResult();

    foreach (['USD', 'EUR', 'GBP', 'JPY', 'CHF', 'AUD', 'CAD', 'NZD', 'INR', 'HKD'] as $currencyCode) {
        expect($list)->toHaveKey($currencyCode);
    }

    foreach (['XXX', 'XTS'] as $currencyCode) {
        expect($list)->not->toHaveKey($currencyCode);
    }

    expect($list['USD'])->toMatch('/^USD \(.*\)$/');
});
