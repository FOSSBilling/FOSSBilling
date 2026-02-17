<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
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
    $dbMock = Mockery::mock('Box_Database');
    /** @var \Mockery\Expectation $expectation */
    $expectation = $dbMock->shouldReceive('getAll');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $di = container();
    $di['logger'] = new Box_Log();
    $di['db'] = $dbMock;

    $manager = new Box_EventManager();
    $manager->setDi($di);

    $manager->fire(['event' => 'onBeforeClientSignup']);
});
