<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

use Box\Mod\Support\Event\BeforeGuestTicketCreateEvent;
use FOSSBilling\Events\EventDispatcher;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class TypedEventTestService
{
    public int $calls = 0;

    #[AsEventListener(priority: 10)]
    public function changeTicket(BeforeGuestTicketCreateEvent $event): void
    {
        ++$this->calls;
        $event->setSubject('Updated by listener');
    }
}

class_alias(TypedEventTestService::class, 'Box\\Mod\\TypedEventTest\\Service');

final class TypedEventPriorityTestService
{
    /** @var list<string> */
    public array $calls = [];

    #[AsEventListener(priority: -10)]
    public function later(BeforeGuestTicketCreateEvent $event): void
    {
        $this->calls[] = 'later';
    }

    #[AsEventListener(event: BeforeGuestTicketCreateEvent::class, priority: 10)]
    public function earlier(BeforeGuestTicketCreateEvent $event): void
    {
        $this->calls[] = 'earlier';
        $event->stopPropagation();
    }
}

class_alias(TypedEventPriorityTestService::class, 'Box\\Mod\\TypedEventPriorityTest\\Service');

test('typed listeners can mutate declared fields and refresh after activation', function (): void {
    $modules = [];
    $service = new TypedEventTestService();
    $dispatcher = new EventDispatcher(
        static function () use (&$modules): array {
            return $modules;
        },
        static fn (string $module): object => $service,
    );

    $newEvent = static fn (): BeforeGuestTicketCreateEvent => new BeforeGuestTicketCreateEvent([], 'open', 'Original', 'Message');

    expect($dispatcher->dispatch($newEvent())->getSubject())->toBe('Original');

    $modules = ['typedEventTest', 'typedEventTest'];
    $dispatcher->refresh();
    expect($dispatcher->dispatch($newEvent())->getSubject())->toBe('Updated by listener');
    expect($service->calls)->toBe(1);

    $modules = [];
    $dispatcher->refresh();
    expect($dispatcher->dispatch($newEvent())->getSubject())->toBe('Original');
    expect($service->calls)->toBe(1);
});

test('typed listeners honor Symfony priorities and stop propagation', function (): void {
    $service = new TypedEventPriorityTestService();
    $dispatcher = new EventDispatcher(
        static fn (): array => ['typedEventPriorityTest'],
        static fn (string $module): object => $service,
    );

    $event = new BeforeGuestTicketCreateEvent([], 'open', 'Subject', 'Message');
    expect($dispatcher->dispatch($event))->toBe($event)
        ->and($service->calls)->toBe(['earlier'])
        ->and($event->isPropagationStopped())->toBeTrue();
});
