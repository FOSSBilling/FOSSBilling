<?php

declare(strict_types=1);

describe('Currency Pairs', function () {
    it('includes all major world currencies', function () {
        $currencies = api('admin/currency/get_pairs')->getResult();

        expect($currencies)->toHaveKeys([
            'USD', // US Dollar
            'EUR', // Euro
            'GBP', // British Pound
            'JPY', // Japanese Yen
            'CHF', // Swiss Franc
            'AUD', // Australian Dollar
            'CAD', // Canadian Dollar
            'NZD', // New Zealand Dollar
            'INR', // Indian Rupee
            'HKD', // Hong Kong Dollar
        ]);
    });

    it('excludes invalid or deprecated currency codes', function () {
        $currencies = api('admin/currency/get_pairs')->getResult();

        expect($currencies)->not->toHaveKeys([
            'XXX', // Test currency
            'XTS', // Test currency
            'VES', // Deprecated
            'BZR', // Deprecated
        ]);
    });
});
