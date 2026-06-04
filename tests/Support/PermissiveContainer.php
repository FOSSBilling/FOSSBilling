<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 */

declare(strict_types=1);

namespace Tests\Support;

/**
 * Pimple-compatible container that returns a PermissiveStub for every key
 * access. Used by StrictTemplateRenderer to instantiate the FOSSBilling Twig
 * extensions in the test environment without dragging in a live DI container.
 *
 * The stub absorbs everything, so any code path that hits `$di['anything']`
 * during template rendering returns a benign value rather than throwing.
 */
final class PermissiveContainer extends \Pimple\Container
{
    private PermissiveStub $stub;

    public function __construct()
    {
        $this->stub = new PermissiveStub();
    }

    public function offsetExists(mixed $offset): bool
    {
        return true;
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->stub;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        // Tests don't write to the container; ignore.
    }
}
