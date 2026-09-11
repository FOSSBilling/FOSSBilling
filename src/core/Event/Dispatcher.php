<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core\Event;

use FOSSBilling\Core\Utils\Arr;

class Dispatcher
{
    protected $listeners = [];

    /**
     * Connects a listener to a given event name.
     *
     * @param string $name     An event name
     * @param mixed  $listener A PHP callable
     */
    public function connect($name, mixed $listener): void
    {
        $this->listeners[$name] ??= [];

        $this->listeners[$name][] = $listener;
    }

    /**
     * Notifies all listeners of a given event.
     *
     * @param Event $event A Event instance
     *
     * @return Event The Event instance
     */
    public function notify(Event $event)
    {
        foreach ($this->getListeners($event->getName()) as $listener) {
            call_user_func($listener, $event);
        }

        return $event;
    }

    /**
     * Filters a value by calling all listeners of a given event.
     *
     * @param Event $event A Event instance
     * @param mixed $value The value to be filtered
     *
     * @return Event The Event instance
     */
    public function filter(Event $event, mixed $value)
    {
        foreach ($this->getListeners($event->getName()) as $listener) {
            $value = call_user_func_array($listener, [$event, $value]);
        }

        $event->setReturnValue($value);

        return $event;
    }

    /**
     * Returns true if the given event name has some listeners.
     *
     * @param string $name The event name
     *
     * @return bool true if some listeners are connected, false otherwise
     */
    public function hasListeners($name): bool
    {
        $this->listeners[$name] ??= [];

        return (bool) Arr::safeCount($this->listeners[$name]);
    }

    /**
     * Returns all listeners associated with a given event name.
     *
     * @param string $name The event name
     *
     * @return array An array of listeners
     */
    public function getListeners($name)
    {
        if (!isset($this->listeners[$name])) {
            return [];
        }

        return $this->listeners[$name];
    }
}
