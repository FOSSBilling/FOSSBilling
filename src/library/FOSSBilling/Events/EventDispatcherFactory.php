<?php

declare(strict_types=1);

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Events;

use Pimple\Container;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Factory for creating and configuring the Symfony EventDispatcher.
 *
 * This factory creates an EventDispatcher that automatically discovers
 * event listeners from module Service classes using the #[AsEventListener]
 * attribute. Listener discovery is performed lazily on the first dispatch
 * call and cached for subsequent dispatches.
 */
final class EventDispatcherFactory
{
    private EventDispatcher $dispatcher;
    private Container $di;
    private bool $listenersRegistered = false;

    private function __construct(Container $di)
    {
        $this->di = $di;
        $this->dispatcher = new EventDispatcher();
    }

    /**
     * Create a new event dispatcher wrapper.
     */
    public static function create(Container $di): self
    {
        return new self($di);
    }

    /**
     * Dispatch an event to all registered listeners.
     *
     * On the first call, this will scan all active modules for event
     * listeners and register them. Subsequent calls use the cached listeners.
     */
    public function dispatch(Event $event, ?string $eventName = null): Event
    {
        $this->ensureListenersRegistered();

        // Inject DI container into the event if not already set
        if ($event->getDi() === null) {
            $event->setDi($this->di);
        }

        $this->di['logger']->setChannel('event')->debug(
            'Dispatching event: ' . ($eventName ?? $event::class),
            ['event_class' => $event::class]
        );

        /* @var Event */
        return $this->dispatcher->dispatch($event, $eventName);
    }

    /**
     * Add a listener for a specific event.
     */
    public function addListener(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->dispatcher->addListener($eventName, $listener, $priority);
    }

    /**
     * Get all listeners for a specific event or all events.
     *
     * @return array<string, array<callable>>|array<callable>
     */
    public function getListeners(?string $eventName = null): array
    {
        $this->ensureListenersRegistered();

        return $this->dispatcher->getListeners($eventName);
    }

    /**
     * Check if any listeners are registered for the given event.
     */
    public function hasListeners(?string $eventName = null): bool
    {
        $this->ensureListenersRegistered();

        return $this->dispatcher->hasListeners($eventName);
    }

    /**
     * Ensure listeners are registered (lazy initialization).
     */
    private function ensureListenersRegistered(): void
    {
        if ($this->listenersRegistered) {
            return;
        }

        $this->discoverAndRegisterListeners();
        $this->listenersRegistered = true;
    }

    /**
     * Discover and register all event listeners from module Service classes.
     */
    private function discoverAndRegisterListeners(): void
    {
        $extensionService = $this->di['mod_service']('extension');
        $modules = $extensionService->getCoreAndActiveModules();

        foreach ($modules as $moduleName) {
            $this->registerModuleListeners($moduleName);
        }
    }

    /**
     * Register listeners from a specific module's Service class.
     */
    private function registerModuleListeners(string $moduleName): void
    {
        try {
            $mod = $this->di['mod']($moduleName);

            if (!$mod->hasService()) {
                return;
            }

            $service = $mod->getService();
            $reflectionClass = new \ReflectionClass($service);

            foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $this->processMethodAttributes($service, $method);
            }
        } catch (\Exception $e) {
            // Log error but continue with other modules
            error_log("Failed to register listeners for module {$moduleName}: " . $e->getMessage());
        }
    }

    /**
     * Process #[AsEventListener] attributes on a method.
     */
    private function processMethodAttributes(object $service, \ReflectionMethod $method): void
    {
        $attributes = $method->getAttributes(AsEventListener::class);

        foreach ($attributes as $attribute) {
            $listener = $attribute->newInstance();
            $eventName = $listener->event;
            $priority = $listener->priority;

            if ($eventName === null) {
                // Try to determine event from first parameter type
                $parameters = $method->getParameters();
                if (isset($parameters[0])) {
                    $type = $parameters[0]->getType();
                    if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                        $eventName = $type->getName();
                    }
                }
            }

            if ($eventName === null) {
                continue;
            }

            // Register the listener
            $this->dispatcher->addListener(
                $eventName,
                [$service, $method->getName()],
                $priority
            );
        }
    }
}
