<?php

declare(strict_types=1);

describe('Template System', function () {
    it('finds existing templates', function () {
        expect(api('guest/system/template_exists', ['file' => 'layout_default.html.twig']))
            ->toHaveResult()
            ->toBeTrue();
    });

    it('returns false for non-existent templates', function () {
        expect(api('guest/system/template_exists', ['file' => 'thisfiledoesnotexist.txt']))
            ->toHaveResult()
            ->toBeFalse();
    });
});

describe('Localization Data', function () {
    dataset('localization_endpoints', [
        'billing periods' => 'periods',
        'countries list' => 'countries',
        'EU countries' => 'countries_eunion',
        'states/provinces' => 'states',
        'phone codes' => 'phone_codes',
    ]);

    it('returns {data} as array', function (string $endpoint) {
        expect(api("guest/system/{$endpoint}"))
            ->toHaveResult()
            ->toBeArray()
            ->not->toBeEmpty();
    })->with('localization_endpoints');
});
