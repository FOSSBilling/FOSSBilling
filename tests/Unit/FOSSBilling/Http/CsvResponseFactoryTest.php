<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use FOSSBilling\Http\CsvResponseFactory;

test('CSV factory strips pass, salt, api_token, hash, and config from numeric-array headers', function (): void {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('fetchAllAssociative')
        ->with('SELECT * FROM `client`', [])
        ->andReturn([
            [
                'id' => 1,
                'email' => 'client@example.com',
                'pass' => 'leaked-hash',
                'salt' => 'leaked-salt',
                'api_token' => 'leaked-token',
                'hash' => 'leaked-invoice-hash',
                'config' => '{"password":"leaked-config"}',
                'status' => 'active',
            ],
        ]);

    $factory = new CsvResponseFactory($connection);
    $response = $factory->create('client', 'clients.csv', ['id', 'email', 'pass', 'salt', 'api_token', 'hash', 'config', 'status']);
    $content = $response->getContent();

    expect($content)->toContain('id')
        ->and($content)->toContain('email')
        ->and($content)->toContain('status')
        ->and($content)->not->toContain('pass')
        ->and($content)->not->toContain('salt')
        ->and($content)->not->toContain('api_token')
        ->and($content)->not->toContain('hash')
        ->and($content)->not->toContain('config')
        ->and($content)->not->toContain('leaked-hash')
        ->and($content)->not->toContain('leaked-salt')
        ->and($content)->not->toContain('leaked-token')
        ->and($content)->not->toContain('leaked-invoice-hash')
        ->and($content)->not->toContain('leaked-config');
});

test('CSV factory does not leak all columns when every requested header is sensitive', function (): void {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('fetchAllAssociative')
        ->with('SELECT * FROM `client`', [])
        ->andReturn([
            [
                'id' => 1,
                'email' => 'client@example.com',
                'pass' => 'leaked-hash',
                'salt' => 'leaked-salt',
                'api_token' => 'leaked-token',
            ],
        ]);

    $factory = new CsvResponseFactory($connection);
    $response = $factory->create('client', 'clients.csv', ['pass', 'salt', 'api_token']);
    $content = $response->getContent();

    expect($content)->not->toContain('leaked-hash')
        ->and($content)->not->toContain('leaked-salt')
        ->and($content)->not->toContain('leaked-token')
        ->and($content)->not->toContain('client@example.com');
});

test('CSV factory exports all non-sensitive columns when no headers are specified', function (): void {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('fetchAllAssociative')
        ->with('SELECT * FROM `client`', [])
        ->andReturn([
            [
                'id' => 1,
                'email' => 'client@example.com',
                'pass' => 'leaked-hash',
                'salt' => 'leaked-salt',
                'api_token' => 'leaked-token',
                'status' => 'active',
            ],
        ]);

    $factory = new CsvResponseFactory($connection);
    $response = $factory->create('client', 'clients.csv');
    $content = $response->getContent();

    expect($content)->toContain('id')
        ->and($content)->toContain('email')
        ->and($content)->toContain('status')
        ->and($content)->not->toContain('pass')
        ->and($content)->not->toContain('salt')
        ->and($content)->not->toContain('api_token')
        ->and($content)->not->toContain('leaked-hash')
        ->and($content)->not->toContain('leaked-salt')
        ->and($content)->not->toContain('leaked-token');
});
