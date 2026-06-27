<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace Tests\Support;

/**
 * Permissive stub for the strict-variables render test.
 *
 * Behaves as a generic "always-defined" value: any property access, method call, or array key
 * returns another instance of this stub, so chained attribute access does not throw under
 * strict_variables.
 *
 * Implements \Countable, \IteratorAggregate, and casts to array so it
 * interoperates with Twig filters like |merge, |length, |first, |last, |join,
 * |keys, |sort, |reverse, |slice, etc.
 */
final class PermissiveStub implements \ArrayAccess, \Countable, \IteratorAggregate, \Stringable
{
    public function __construct(
        /** @var array<string, mixed> */
        private array $data = [],
    ) {
    }

    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? new self();
    }

    public function __set(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return true;
    }

    public function __unset(string $name): void
    {
        unset($this->data[$name]);
    }

    /**
     * @param array<int, mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->data[$name] ?? new self();
    }

    public function __toString(): string
    {
        return '';
    }

    public function offsetExists(mixed $offset): bool
    {
        return true;
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? new self();
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->data[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }

    public function count(): int
    {
        return count($this->data);
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->data);
    }
}
