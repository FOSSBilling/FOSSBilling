<?php

declare(strict_types=1);

dataset('major_currencies', [
    'US Dollar' => ['USD', 'US Dollar', '$', 2],
    'Euro' => ['EUR', 'Euro', '€', 2],
    'British Pound' => ['GBP', 'Pound Sterling', '£', 2],
    'Japanese Yen' => ['JPY', 'Yen', '¥', 0],
]);

it('returns correct defaults for {data}', function (string $code, string $name, string $symbol, int $minorUnits) {
    $defaults = api('guest/currency/get_currency_defaults', ['code' => $code])->getResult();

    expect($defaults)
        ->toHaveKeys(['code', 'name', 'symbol', 'minorUnits'])
        ->and($defaults['code'])->toBe($code)
        ->and($defaults['name'])->toBe($name)
        ->and($defaults['symbol'])->toBe($symbol)
        ->and($defaults['minorUnits'])->toBe($minorUnits);
})->with('major_currencies');
