<?php

declare(strict_types=1);

use Box\Mod\Client\Entity\Client;
use Box\Mod\Client\Entity\ClientBalance;
use Box\Mod\Client\Entity\ClientGroup;
use Box\Mod\Client\Entity\ClientPasswordReset;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

test('maps client tables without changing their columns', function (): void {
    $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 3) . '/Entity'], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');
    $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);

    $client = $entityManager->getClassMetadata(Client::class);
    $balance = $entityManager->getClassMetadata(ClientBalance::class);
    $group = $entityManager->getClassMetadata(ClientGroup::class);
    $passwordReset = $entityManager->getClassMetadata(ClientPasswordReset::class);

    expect($client->getTableName())->toBe('client')
        ->and($client->getColumnNames())->toBe([
            'id', 'aid', 'client_group_id', 'role', 'auth_type', 'email', 'pass', 'salt',
            'status', 'email_approved', 'tax_exempt', 'type', 'first_name', 'last_name',
            'gender', 'birthday', 'phone_cc', 'phone', 'company', 'company_vat',
            'company_number', 'address_1', 'address_2', 'city', 'state', 'postcode',
            'country', 'notes', 'currency', 'lang', 'timezone', 'ip', 'api_token',
            'referred_by', 'billing_email', 'custom_1', 'custom_2', 'custom_3', 'custom_4',
            'custom_5', 'custom_6', 'custom_7', 'custom_8', 'custom_9', 'custom_10',
            'custom_11', 'custom_12', 'custom_13', 'custom_14', 'custom_15', 'custom_16',
            'custom_17', 'custom_18', 'custom_19', 'custom_20', 'created_at', 'updated_at',
        ])
        ->and($client->getFieldMapping('email')['unique'])->toBeTrue()
        ->and($client->getFieldMapping('status')['nullable'])->toBeTrue()
        ->and($client->getFieldMapping('taxExempt')['nullable'])->toBeTrue()
        ->and($client->getFieldMapping('gender')['columnDefinition'])->toContain('ENUM')
        ->and($balance->getTableName())->toBe('client_balance')
        ->and($balance->getColumnNames())->toBe([
            'id', 'client_id', 'type', 'rel_id', 'amount', 'description', 'created_at', 'updated_at',
        ])
        ->and($balance->getFieldMapping('amount')['nullable'])->toBeTrue()
        ->and($group->getTableName())->toBe('client_group')
        ->and($group->getColumnNames())->toBe(['id', 'title', 'created_at', 'updated_at'])
        ->and($passwordReset->getTableName())->toBe('client_password_reset')
        ->and($passwordReset->getColumnNames())->toBe([
            'id', 'client_id', 'hash', 'ip', 'created_at', 'updated_at',
        ]);
});

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

    $admin = \Tests\Helpers\createEntity(Box\Mod\Staff\Entity\Admin::class);

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
    expect($result)->not->toHaveKey('notes');
    expect($result)->not->toHaveKey('status');
    expect($result)->not->toHaveKey('group_id');
    expect($result)->not->toHaveKey('billing_email');
});
