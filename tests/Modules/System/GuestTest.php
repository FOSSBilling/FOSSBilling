<?php

declare(strict_types=1);

use APIHelper\Request;

test('template exists', function (): void {
    $result = Request::makeRequest('guest/system/template_exists', ['file' => 'layout_default.html.twig']);

    expect($result->wasSuccessful())->toBeTrue()
        ->and($result->getResult())->toBeTrue();
});

test('template does not exist', function (): void {
    $result = Request::makeRequest('guest/system/template_exists', ['file' => 'thisfiledoesnotexist.txt']);

    expect($result->wasSuccessful())->toBeTrue()
        ->and($result->getResult())->toBeFalse();
});

test('gets periods', function (): void {
    $result = Request::makeRequest('guest/system/periods');

    expect($result->wasSuccessful())->toBeTrue()
        ->and($result->getResult())->toBeArray();
});

test('gets phone codes', function (): void {
    $result = Request::makeRequest('guest/system/phone_codes');

    expect($result->wasSuccessful())->toBeTrue()
        ->and($result->getResult())->toBeArray();
});
