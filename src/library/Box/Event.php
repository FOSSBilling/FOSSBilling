<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
class Box_Event implements ArrayAccess, FOSSBilling\InjectionAwareInterface
{
    protected ?Pimple\Container $di = null;
    protected $value;
    protected $processed = false;

    /**
     * Constructs a new sfEvent.
     *
     * @param mixed  $subject    The subject
     * @param string $name       The event name
     * @param array  $parameters An array of parameters
     */
    public function __construct(protected mixed $subject, protected $name, protected $parameters = [], protected $api_admin = null, protected $api_guest = null)
    {
    }

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Pimple\Container
    {
        return $this->di;
    }

    /**
     * Returns the subject.
     *
     * @return mixed The subject
     */
    public function getSubject()
    {
        return $this->subject;
    }

    public function getApiAdmin()
    {
        return $this->api_admin;
    }

    public function getApiGuest()
    {
        return $this->api_guest;
    }

    /**
     * Returns the event name.
     *
     * @return string The event name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the return value for this event.
     *
     * @param mixed $value The return value
     */
    public function setReturnValue(mixed $value)
    {
        $this->value = $value;
    }

    /**
     * Returns the return value.
     *
     * @return mixed The return value
     */
    public function getReturnValue()
    {
        return $this->value;
    }

    /**
     * Sets the processed flag.
     *
     * @param bool $processed The processed flag value
     */
    public function setProcessed($processed)
    {
        $this->processed = (bool) $processed;
    }

    /**
     * Returns whether the event has been processed by a listener or not.
     *
     * @return bool true if the event has been processed, false otherwise
     */
    public function isProcessed()
    {
        return $this->processed;
    }

    /**
     * Returns the event parameters.
     *
     * @return array The event parameters
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Returns true if the parameter exists (implements the ArrayAccess interface).
     *
     * @param string $name The parameter name
     *
     * @return bool true if the parameter exists, false otherwise
     */
    public function offsetExists(mixed $name): bool
    {
        return array_key_exists($name, $this->parameters);
    }

    /**
     * Returns a parameter value (implements the ArrayAccess interface).
     *
     * @param string $name The parameter name
     *
     * @return mixed The parameter value
     */
    public function offsetGet(mixed $name): mixed
    {
        if (!array_key_exists($name, $this->parameters)) {
            throw new InvalidArgumentException(sprintf('The event "%s" has no "%s" parameter.', $this->name, $name));
        }

        return $this->parameters[$name];
    }

    /**
     * Sets a parameter (implements the ArrayAccess interface).
     *
     * @param string $name  The parameter name
     * @param mixed  $value The parameter value
     */
    public function offsetSet(mixed $name, mixed $value): void
    {
        $this->parameters[$name] = $value;
    }

    /**
     * Removes a parameter (implements the ArrayAccess interface).
     *
     * @param string $name The parameter name
     */
    public function offsetUnset(mixed $name): void
    {
        unset($this->parameters[$name]);
    }
}
