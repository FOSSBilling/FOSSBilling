<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

use Box\Mod\Extension\Event\AfterExtensionActivatedEvent;
use Box\Mod\Extension\Event\AfterExtensionDeactivatedEvent;
use Box\Mod\Widgets\Service;
use FOSSBilling\Events\EventDispatcher;

use function Tests\Helpers\container;

test('module lifecycle events invalidate the widget registry', function (): void {
    $cache = new class {
        public array $deleted = [];

        public function delete(string $key): bool
        {
            $this->deleted[] = $key;

            return true;
        }
    };

    $di = container();
    $di['cache'] = $cache;

    $service = new Service();
    $service->setDi($di);
    $dispatcher = new EventDispatcher(
        static fn (): array => ['widgets'],
        static fn (string $module): object => $service,
    );

    $dispatcher->dispatch(new AfterExtensionActivatedEvent(1, 'mod', 'example'));
    $dispatcher->dispatch(new AfterExtensionDeactivatedEvent(1, 'mod', 'example'));
    $dispatcher->dispatch(new AfterExtensionActivatedEvent(2, 'theme', 'example'));

    expect($cache->deleted)->toBe([Service::CACHE_KEY, Service::CACHE_KEY]);
});
