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

final class EventManagerTestListener
{
    public static array $calls = [];

    public static function onExample(Box_Event $event): void
    {
        self::$calls[] = 'specific:' . $event->getName();
    }

    public static function onEveryEvent(Box_Event $event): void
    {
        self::$calls[] = 'global:' . $event->getName();
    }
}

test('empty fire', function (): void {
    $manager = new Box_EventManager();
    expect($manager->fire([]))->toBeFalse();
});

test('fire reuses listener lookup and reloads it after invalidation', function (): void {
    $di = container();
    $di['logger'] = new FOSSBilling\Logger();
    $di['mod_service'] = $di->protect(fn (): EventManagerTestListener => new EventManagerTestListener());

    EventManagerTestListener::$calls = [];

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('fetchAllAssociative')
        ->twice()
        ->andReturn(
            [
                ['rel_id' => 'example', 'meta_value' => 'onExample'],
                ['rel_id' => 'example', 'meta_value' => 'onEveryEvent'],
            ],
            [
                ['rel_id' => 'example', 'meta_value' => 'onExample'],
            ],
        );
    $di['em']->shouldReceive('getConnection')->andReturn($connection);

    $manager = new Box_EventManager();
    $manager->setDi($di);

    $manager->fire(['event' => 'onExample']);
    $manager->fire(['event' => 'onExample']);
    $manager->clearListenerCache();
    $manager->fire(['event' => 'onExample']);

    expect(EventManagerTestListener::$calls)->toBe([
        'specific:onExample',
        'global:onExample',
        'specific:onExample',
        'global:onExample',
        'specific:onExample',
    ]);
});
