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
 * attribute. Listener discovery is performed during initialization.
 */
final class EventDispatcherFactory
{
    private EventDispatcher $dispatcher;
    private Container $di;

    private function __construct(Container $di)
    {
        $this->di = $di;
        $this->dispatcher = new EventDispatcher();
        $this->discoverAndRegisterListeners();
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
     */
    public function dispatch(Event $event, ?string $eventName = null): Event
    {
        $this->di['logger']->setChannel('event')->debug(
            'Dispatching event: ' . ($eventName ?? $event::class),
            ['event_class' => $event::class]
        );

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
        return $this->dispatcher->getListeners($eventName);
    }

    /**
     * Check if any listeners are registered for the given event.
     */
    public function hasListeners(?string $eventName = null): bool
    {
        return $this->dispatcher->hasListeners($eventName);
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
                $attributes = $method->getAttributes(AsEventListener::class);
                if ($attributes !== []) {
                    foreach ($attributes as $attribute) {
                        $this->registerListener($service, $method, $attribute);
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Failed to register listeners for module {$moduleName}: " . $e->getMessage());
        }
    }

    /**
     * Register a single listener from an #[AsEventListener] attribute.
     */
    private function registerListener(object $service, \ReflectionMethod $method, \ReflectionAttribute $attribute): void
    {
        $arguments = $attribute->getArguments();
        $eventName = $arguments['event'] ?? null;
        $priority = $arguments['priority'] ?? 0;

        $eventName ??= $this->extractEventFromMethod($method);

        if ($eventName === null) {
            return;
        }

        $this->dispatcher->addListener(
            $eventName,
            [$service, $method->getName()],
            $priority
        );
    }

    /**
     * Extract event name from the first parameter type of a method.
     */
    private function extractEventFromMethod(\ReflectionMethod $method): ?string
    {
        $parameters = $method->getParameters();
        if (isset($parameters[0])) {
            $type = $parameters[0]->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                return $type->getName();
            }
        }

        return null;
    }
}
