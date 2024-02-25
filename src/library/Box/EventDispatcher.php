<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Box_EventDispatcher
{
    protected $listeners = [];

    /**
     * Connects a listener to a given event name.
     *
     * @param string $name     An event name
     * @param mixed  $listener A PHP callable
     */
    public function connect($name, mixed $listener)
    {
        if (!isset($this->listeners[$name])) {
            $this->listeners[$name] = [];
        }

        $this->listeners[$name][] = $listener;
    }

    /**
     * TODO: Unsused?
     * Disconnects a listener for a given event name.
     *
     * @param string $name     An event name
     * @param mixed  $listener A PHP callable
     *
     * @return false|null false if listener does not exist, null otherwise
     */
    public function disconnect($name, mixed $listener)
    {
        if (!isset($this->listeners[$name])) {
            return false;
        }

        foreach ($this->listeners[$name] as $i => $callable) {
            if ($listener === $callable) {
                unset($this->listeners[$name][$i]);
            }
        }

        return null;
    }

    /**
     * TODO: Unsused?
     * Disconnects all listeners for a given event name.
     *
     * @param string $name An event name
     *
     * @return false|null false if listener does not exist, null otherwise
     */
    public function disconnectAll($name)
    {
        if (!isset($this->listeners[$name])) {
            return false;
        }

        foreach ($this->listeners[$name] as $i => $callable) {
            unset($this->listeners[$name][$i]);
        }

        return null;
    }

    /**
     * Notifies all listeners of a given event.
     *
     * @param Box_Event $event A Box_Event instance
     *
     * @return Box_Event The Box_Event instance
     */
    public function notify(Box_Event $event)
    {
        foreach ($this->getListeners($event->getName()) as $listener) {
            call_user_func($listener, $event);
        }

        return $event;
    }

    /**
     * Notifies all listeners of a given event until one returns a non null value.
     *
     * @param Box_Event $event A Box_Event instance
     *
     * @return Box_Event The Box_Event instance
     */
    public function notifyUntil(Box_Event $event)
    {
        foreach ($this->getListeners($event->getName()) as $listener) {
            if (call_user_func($listener, $event)) {
                $event->setProcessed(true);

                break;
            }
        }

        return $event;
    }

    /**
     * Filters a value by calling all listeners of a given event.
     *
     * @param Box_Event $event A Box_Event instance
     * @param mixed     $value The value to be filtered
     *
     * @return Box_Event The Box_Event instance
     */
    public function filter(Box_Event $event, mixed $value)
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
    public function hasListeners($name)
    {
        if (!isset($this->listeners[$name])) {
            $this->listeners[$name] = [];
        }

        return (bool) (is_countable($this->listeners[$name]) ? count($this->listeners[$name]) : 0);
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
