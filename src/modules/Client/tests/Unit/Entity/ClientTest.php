<?php

declare(strict_types=1);

use Box\Mod\Client\Entity\Client;

test('converts an admin client list entity to the legacy API shape', function (): void {
    $client = \Tests\Helpers\createEntity(Client::class);
    $values = [
        'id' => 42,
        'email' => 'ada@example.com',
        'email_approved' => 1,
        'company' => 'Analytical Engines Ltd',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'billing_email' => 'billing@example.com',
        'client_group_id' => 3,
        'status' => 'active',
        'tax_exempt' => 0,
        'custom_15' => 'VIP',
        'createdAt' => new DateTime('2026-07-19 10:00:00'),
        'updatedAt' => new DateTime('2026-07-19 10:00:00'),
    ];

    foreach ($values as $property => $value) {
        $client->$property = $value;
    }

    $admin = \Tests\Helpers\createEntity(\Box\Mod\Staff\Entity\Admin::class);

    expect($client->toApiArray($admin))->toMatchArray([
        'id' => 42,
        'email' => 'ada@example.com',
        'email_approved' => 1,
        'group_id' => 3,
        'status' => 'active',
        'tax_exempt' => 0,
        'custom_15' => 'VIP',
        'created_at' => '2026-07-19 10:00:00',
        'updated_at' => '2026-07-19 10:00:00',
    ]);
});

test('does not expose admin-only client fields without an admin identity', function (): void {
    $client = \Tests\Helpers\createEntity(Client::class);

    $result = $client->toApiArray();
    expect($result)->toHaveKey('id');
    expect($result)->toHaveKey('email');
    expect($result)->toHaveKey('notes');
    expect($result)->toHaveKey('status');
    expect($result)->toHaveKey('group_id');
    expect($result)->not->toHaveKey('billing_email');
});
