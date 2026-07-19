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
    $manager = new Box_EventManager();
    expect($manager->fire([]))->toBeFalse();
});

test('fire', function (): void {
    $di = container();
    $connectionMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connectionMock->shouldReceive('fetchAllAssociative')->andReturn([]);
    $di['em']->shouldReceive('getConnection')->andReturn($connectionMock);
    $di['logger'] = new Box_Log();

    $manager = new Box_EventManager();
    $manager->setDi($di);

    $result = $manager->fire(['event' => 'onBeforeClientSignup']);
    expect($result)->toBeNull();
});
