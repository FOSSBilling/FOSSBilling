<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace FOSSBilling\Events;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventDispatcher as SymfonyEventDispatcher;

/**
 * Registers listeners on active module services without requiring Symfony's DI container.
 */
final class EventDispatcher
{
    private SymfonyEventDispatcher $dispatcher;
    private bool $initialized = false;

    /**
     * @param \Closure(): array<string> $activeModules
     * @param \Closure(string): object  $moduleService
     */
    public function __construct(
        private readonly \Closure $activeModules,
        private readonly \Closure $moduleService,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->dispatcher = new SymfonyEventDispatcher();
    }

    public function dispatch(Event $event): Event
    {
        $this->initialize();
        $this->logger?->debug('Dispatching event {event}', ['event' => $event::class]);

        return $this->dispatcher->dispatch($event);
    }

    /**
     * Rebuild listener registrations after a module is activated or deactivated.
     */
    public function refresh(): void
    {
        $this->dispatcher = new SymfonyEventDispatcher();
        $this->initialized = false;
    }

    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        // Set this first so a listener's service setup cannot recursively scan modules.
        $this->initialized = true;

        try {
            $services = [];
            foreach (array_unique(($this->activeModules)()) as $moduleName) {
                $className = 'Box\\Mod\\' . ucfirst($moduleName) . '\\Service';
                if (!class_exists($className)) {
                    continue;
                }

                $reflection = new \ReflectionClass($className);
                foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                    foreach ($method->getAttributes(AsEventListener::class) as $attribute) {
                        $listener = $attribute->newInstance();
                        $parameterType = $this->eventType($method);
                        $eventName = $listener->event ?? $parameterType;
                        if ($eventName === null || !is_a($eventName, Event::class, true) || $parameterType === null || !is_a($eventName, $parameterType, true)) {
                            throw new \LogicException(sprintf('Listener %s::%s must accept its FOSSBilling event class.', $className, $method->getName()));
                        }

                        if ($listener->dispatcher !== null || ($listener->method !== null && $listener->method !== $method->getName())) {
                            throw new \LogicException(sprintf('Listener %s::%s uses unsupported AsEventListener options.', $className, $method->getName()));
                        }

                        $service = $services[$moduleName] ??= ($this->moduleService)($moduleName);
                        $this->dispatcher->addListener($eventName, [$service, $method->getName()], $listener->priority);
                    }
                }
            }
        } catch (\Throwable $error) {
            $this->refresh();

            throw $error;
        }
    }

    private function eventType(\ReflectionMethod $method): ?string
    {
        $type = $method->getParameters()[0]->getType() ?? null;

        return $type instanceof \ReflectionNamedType && !$type->isBuiltin() ? $type->getName() : null;
    }
}
