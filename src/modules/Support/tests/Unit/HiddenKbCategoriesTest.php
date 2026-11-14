<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Support\HiddenKbCategories;

use function Tests\Helpers\container;

function hiddenKbCategoriesConnection(): array
{
    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE support_kb_category_hidden (category_id BIGINT NOT NULL, hidden_at DATETIME NOT NULL, PRIMARY KEY (category_id))');

    $di = container();
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($connection);
    $di['em'] = $em;

    return [$di, $connection];
}

test('hidden kb categories start visible and round-trip', function (): void {
    [$di] = hiddenKbCategoriesConnection();

    expect(HiddenKbCategories::ids($di))->toBe([]);
    expect(HiddenKbCategories::isHidden($di, null))->toBeFalse();
    expect(HiddenKbCategories::isHidden($di, 3))->toBeFalse();

    HiddenKbCategories::save($di, 3, true);
    expect(HiddenKbCategories::ids($di))->toBe([3]);
    expect(HiddenKbCategories::isHidden($di, 3))->toBeTrue();

    HiddenKbCategories::save($di, 2, false);
    expect(HiddenKbCategories::ids($di))->toBe([3]);

    HiddenKbCategories::forget($di, 3);
    expect(HiddenKbCategories::ids($di))->toBe([]);
});

test('hidden kb category reads degrade to visible without the table', function (): void {
    $di = container();
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andThrow(new RuntimeException('no such table'));
    $di['em'] = $em;

    expect(HiddenKbCategories::ids($di))->toBe([]);
    expect(HiddenKbCategories::isHidden($di, 3))->toBeFalse();
});
