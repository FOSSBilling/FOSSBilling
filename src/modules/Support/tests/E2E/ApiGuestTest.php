<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Tests\Helpers\ApiClient;

if (!getenv('APP_URL') || !getenv('TEST_API_KEY')) {
    return;
}

const SUPPORT_MIN_TICKET_ID_LENGTH = 30;
const SUPPORT_MAX_TICKET_ID_LENGTH = 60;

$initialSupportConfig = null;

beforeEach(function () use (&$initialSupportConfig): void {
    $configGetResult = ApiClient::request('admin/extension/config_get', ['ext' => 'mod_support']);
    expect($configGetResult->wasSuccessful())->toBeTrue();

    $configData = $configGetResult->getResult();
    expect($configData)->toBeArray();
    $initialSupportConfig = $configData;
});

afterEach(function () use (&$initialSupportConfig): void {
    if ($initialSupportConfig === null) {
        return;
    }

    $configResetResult = ApiClient::request(
        'admin/extension/config_save',
        array_merge(['ext' => 'mod_support'], $initialSupportConfig)
    );
    expect($configResetResult->wasSuccessful())->toBeTrue();
    $initialSupportConfig = null;
});

test('creates ticket for guest', function (): void {
    $expectedName = 'Name';
    $expectedEmail = 'email@example.com';
    $expectedSubject = 'Subject';
    $expectedMessage = 'message';

    $result = ApiClient::request('guest/support/ticket_create', [
        'name' => $expectedName,
        'email' => $expectedEmail,
        'subject' => $expectedSubject,
        'message' => $expectedMessage,
    ]);

    expect($result->wasSuccessful())->toBeTrue();
    $ticketId = $result->getResult();
    expect($ticketId)->toBeString()
        ->and(strlen((string) $ticketId))->toBeGreaterThanOrEqual(SUPPORT_MIN_TICKET_ID_LENGTH)
        ->and(strlen((string) $ticketId))->toBeLessThanOrEqual(SUPPORT_MAX_TICKET_ID_LENGTH)
        ->and($ticketId)->toMatch('/^[A-Za-z0-9_-]+$/');

    $ticketGetResult = ApiClient::request('guest/support/ticket_get', ['hash' => $ticketId]);
    expect($ticketGetResult->wasSuccessful())->toBeTrue();

    $ticketData = $ticketGetResult->getResult();
    expect($ticketData)->toBeArray()
        ->toHaveKey('author')
        ->toHaveKey('subject')
        ->toHaveKey('messages')
        ->and($ticketData['subject'])->toBe($expectedSubject)
        ->and($ticketData['messages'])->toBeArray()->not->toBeEmpty()
        ->and($ticketData['messages'][0]['content'])->toBe($expectedMessage);

    expect($ticketData['author'])->toMatchArray([
        'name' => $expectedName,
        'email' => $expectedEmail,
        'role' => 'guest',
    ]);
});

test('rejects guest ticket creation when public tickets are disabled', function (): void {
    $configResult = ApiClient::request('admin/extension/config_save', ['ext' => 'mod_support', 'disable_public_tickets' => true]);
    expect($configResult->wasSuccessful())->toBeTrue();

    $configGetResult = ApiClient::request('admin/extension/config_get', ['ext' => 'mod_support']);
    expect($configGetResult->wasSuccessful())->toBeTrue();

    $configData = $configGetResult->getResult();
    expect($configData)->toBeArray()
        ->toHaveKey('disable_public_tickets')
        ->and((bool) $configData['disable_public_tickets'])->toBeTrue();

    $result = ApiClient::request('guest/support/ticket_create', [
        'name' => 'Name',
        'email' => 'email2@example.com',
        'subject' => 'Subject',
        'message' => 'message',
    ]);

    expect($result->wasSuccessful())->toBeFalse();
    $errorMessage = $result->getErrorMessage();
    expect($errorMessage)->toBeString()
        ->toContain("aren't accepting support tickets")
        ->toContain('unregistered users');
});

test('public tickets enabled reflects configuration', function (): void {
    $enabledResult = ApiClient::request('guest/support/public_tickets_enabled');
    expect($enabledResult->wasSuccessful())->toBeTrue()
        ->and($enabledResult->getResult())->toBeTrue();

    $configResult = ApiClient::request('admin/extension/config_save', ['ext' => 'mod_support', 'disable_public_tickets' => true]);
    expect($configResult->wasSuccessful())->toBeTrue();

    $disabledResult = ApiClient::request('guest/support/public_tickets_enabled');
    expect($disabledResult->wasSuccessful())->toBeTrue()
        ->and($disabledResult->getResult())->toBeFalse();
});

test('rejects guest ticket creation without name', function (): void {
    $result = ApiClient::request('guest/support/ticket_create', [
        'email' => 'email@example.com',
        'subject' => 'Subject',
        'message' => 'message',
    ]);

    expect($result->wasSuccessful())->toBeFalse()
        ->and($result->getErrorMessage())->toBe('Please enter your name');
});

test('rejects guest ticket creation without email', function (): void {
    $result = ApiClient::request('guest/support/ticket_create', [
        'name' => 'Name',
        'subject' => 'Subject',
        'message' => 'message',
    ]);

    expect($result->wasSuccessful())->toBeFalse()
        ->and($result->getErrorMessage())->toBe('Please enter your email address');
});

test('rejects guest ticket creation with invalid email', function (): void {
    $result = ApiClient::request('guest/support/ticket_create', [
        'name' => 'Name',
        'email' => 'not-an-email',
        'subject' => 'Subject',
        'message' => 'message',
    ]);

    expect($result->wasSuccessful())->toBeFalse()
        ->and($result->getErrorMessage())->toBe('Email address is invalid');
});

test('rejects guest ticket creation with empty subject', function (): void {
    $result = ApiClient::request('guest/support/ticket_create', [
        'name' => 'Name',
        'email' => 'email@example.com',
        'subject' => '',
        'message' => 'message',
    ]);

    expect($result->wasSuccessful())->toBeFalse()
        ->and($result->getErrorMessage())->toBe('Please enter the subject');
});

test('rejects guest ticket creation with empty message', function (): void {
    $result = ApiClient::request('guest/support/ticket_create', [
        'name' => 'Name',
        'email' => 'email@example.com',
        'subject' => 'Subject',
        'message' => '',
    ]);

    expect($result->wasSuccessful())->toBeFalse()
        ->and($result->getErrorMessage())->toBe('Please enter your message');
});
