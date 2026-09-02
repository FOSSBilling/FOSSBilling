<?php

declare(strict_types=1);

test('getPasswordLength returns the default when not configured', function (): void {
    $manager = new Server_Manager_Custom([]);

    expect($manager->getPasswordLength())->toBe(10);
});

test('getPasswordLength returns an int when configured with an int', function (): void {
    $manager = new Server_Manager_Custom(['passwordLength' => 16]);

    expect($manager->getPasswordLength())->toBe(16);
});

test('getPasswordLength coerces a numeric string to int', function (): void {
    // Guards against FOSSBILLING-MZA: a numeric string stored in the server
    // configuration must not violate the `int` return type.
    $manager = new Server_Manager_Custom(['passwordLength' => '12']);

    expect($manager->getPasswordLength())->toBe(12);
});
