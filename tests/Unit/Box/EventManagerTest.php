<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;

test('empty fire', function (): void {
    $manager = new FOSSBilling\Core\Event\Manager();
    expect($manager->fire([]))->toBeFalse();
});

test('fire', function (): void {
    $di = container();
    $di['logger'] = new FOSSBilling\Core\Logging\Logger();

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('fetchAllAssociative')->atLeast()->once()->andReturn([]);
    $di['em']->shouldReceive('getConnection')->andReturn($connection);

    $manager = new FOSSBilling\Core\Event\Manager();
    $manager->setDi($di);

    $manager->fire(['event' => 'onBeforeClientSignup']);
});
