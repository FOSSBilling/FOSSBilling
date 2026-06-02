<?php

declare(strict_types=1);

use Tests\Helpers\ApiClient;

test('gets extension settings', function (): void {
    $result = ApiClient::request('guest/extension/settings', ['ext' => 'index']);

    expect($result->wasSuccessful())->toBeTrue()
        ->and($result->getResult())->toBeArray();
});

test('returns error when extension settings ext is missing', function (): void {
    $result = ApiClient::request('guest/extension/settings', ['ext' => '']);

    expect($result->wasSuccessful())->toBeFalse()
        ->and($result->getErrorMessage())->toBe('Parameter ext is missing');
});

test('reports active extension as enabled', function (): void {
    $result = ApiClient::request('guest/extension/is_on', ['mod' => 'index']);

    expect($result->wasSuccessful())->toBeTrue()
        ->and($result->getResult())->toBeTrue();
});

test('reports inactive extension as disabled', function (): void {
    $result = ApiClient::request('guest/extension/is_on', ['mod' => 'serviceapikey']);

    expect($result->wasSuccessful())->toBeTrue()
        ->and($result->getResult())->toBeFalse();
});
