<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use FOSSBilling\PasswordManager;

test('set algo', function (): void {
    $boxPassword = new PasswordManager();
    $algo = PASSWORD_BCRYPT;
    $boxPassword->setAlgo($algo);
    $result = $boxPassword->getAlgo();
    expect($result)->toEqual($algo);
});

test('set options', function (): void {
    $boxPassword = new PasswordManager();
    $options = [
        'cost' => 12,
    ];
    $boxPassword->setOptions($options);
    $result = $boxPassword->getOptions();
    expect($result)->toEqual($options);
});

test('hashing', function (): void {
    $boxPassword = new PasswordManager();
    $password = '123456';
    $hash = $boxPassword->hashIt($password);
    expect($hash)->toBeString();
    expect($hash)->not->toBeEmpty();

    $veryfied = $boxPassword->verify($password, $hash);
    expect($veryfied)->toBeBool();
    expect($veryfied)->toBeTrue();

    $needRehashing = $boxPassword->needsRehash($hash);
    expect($needRehashing)->toBeBool();
    expect($needRehashing)->toBeFalse();
});

test('needs rehashing', function (): void {
    $boxPassword = new PasswordManager();
    $password = '123456';
    $hash = $boxPassword->hashIt($password);
    expect($hash)->toBeString();
    expect($hash)->not->toBeEmpty();

    $newOptions = ['cost' => 15];
    $boxPassword->setOptions($newOptions);

    $needRehashing = $boxPassword->needsRehash($hash);
    expect($needRehashing)->toBeBool();
    expect($needRehashing)->toBeTrue();
});
