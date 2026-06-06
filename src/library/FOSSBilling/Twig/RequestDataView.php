<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Twig;

/**
 * A permissive view over the HTTP request data exposed to Twig as the `request` global.
 *
 * Acts as an array of GET query parameters, but never throws when a key is missing
 * and never reports a key as undefined. This mirrors how HTTP query strings naturally
 * work: accessing a parameter that wasn't sent returns null, not an error.
 *
 * This is intentional and orthogonal to `strict_variables`. The goal of strict_variables
 * is to catch real template logic errors (undefined business variables, missing attributes
 * on objects, etc.). The HTTP request is not business state - it's input data that is
 * inherently sparse - so treating it permissively is the correct design.
 *
 * `{% if request.foo %}` works the same as before: false for missing/empty values.
 * `{{ request.foo|default('x') }}` works as expected.
 * `{{ request.foo }}` renders as empty string when missing, which is the existing
 * form pre-fill behavior in dozens of templates.
 */
final class RequestDataView implements \ArrayAccess, \Countable, \IteratorAggregate
{
    public function __construct(
        private readonly array $data,
    ) {
    }

    public function offsetExists(mixed $offset): bool
    {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    #[\ReturnTypeWillChange]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        // Read-only view.
    }

    public function offsetUnset(mixed $offset): void
    {
        // Read-only view.
    }

    public function count(): int
    {
        return count($this->data);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->data);
    }

    public function all(): array
    {
        return $this->data;
    }
}
