<?php

declare(strict_types=1);

use APIHelper\Request;

const THEME_SEMANTIC_VERSION_PATTERN = '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?(?:\+[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$/';

function expectValidThemePayload(array $data): void
{
    foreach (['author', 'name', 'version', 'code', 'paths', 'hasSettings', 'url'] as $requiredKey) {
        expect($data)->toHaveKey($requiredKey);
    }

    expect($data['name'])->toBeString()->not->toBe('')
        ->and($data['version'])->toBeString()->not->toBe('')
        ->and($data['version'])->toMatch(THEME_SEMANTIC_VERSION_PATTERN)
        ->and($data['author'])->toBeString()->not->toBe('')
        ->and($data['code'])->toBeString()->not->toBe('')
        ->and($data['url'])->toBeString()->not->toBe('')
        ->and($data['hasSettings'])->toBeBool()
        ->and($data['paths'])->toBeArray()->not->toBeEmpty();

    foreach ($data['paths'] as $path) {
        expect($path)->toBeString()->not->toBe('');
    }

    if (array_key_exists('author_url', $data)) {
        expect($data['author_url'])->toBeString()->not->toBe('');
    }

    if (array_key_exists('description', $data)) {
        expect($data['description'])->toBeString();
    }

    if (array_key_exists('icon', $data)) {
        expect($data['icon'])->toBeString();
    }

    if (array_key_exists('markdown_attributes', $data)) {
        expect($data['markdown_attributes'])->toBeArray();
    }
}

test('gets current client theme', function (): void {
    $result = Request::makeRequest('admin/theme/get_current');
    expect($result->wasSuccessful())->toBeTrue();

    $data = $result->getResult();
    expect($data)->toBeArray();
    expectValidThemePayload($data);
});

test('gets current admin theme', function (): void {
    $result = Request::makeRequest('admin/theme/get_current', ['client' => false]);
    expect($result->wasSuccessful())->toBeTrue();

    $data = $result->getResult();
    expect($data)->toBeArray();
    expectValidThemePayload($data);
});

test('returns error when current theme client parameter is invalid', function (): void {
    $result = Request::makeRequest('admin/theme/get_current', ['client' => 'invalid']);

    expect($result->wasSuccessful())->toBeFalse();

    $errorMessage = $result->getErrorMessage();
    expect($errorMessage)->toBeString()
        ->and(trim($errorMessage))->not->toBe('');
});

test('returns not found for invalid admin theme action with client false', function (): void {
    $result = Request::makeRequest('admin/theme/non_existing_action', ['client' => false]);

    expect($result->getStatusCode())->toBe(404)
        ->and($result->wasSuccessful())->toBeFalse()
        ->and($result->getErrorCode())->toBe(740);

    $errorMessage = $result->getErrorMessage();
    expect($errorMessage)->toBeString()
        ->and(trim($errorMessage))->not->toBe('');
});

test('returns not found for invalid theme action', function (): void {
    $result = Request::makeRequest('admin/theme/non_existing_action');

    expect($result->getStatusCode())->toBe(404)
        ->and($result->wasSuccessful())->toBeFalse()
        ->and($result->getErrorCode())->toBe(740);

    $errorMessage = $result->getErrorMessage();
    expect($errorMessage)->toBeString()
        ->and(trim($errorMessage))->not->toBe('');
});
