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
    private readonly PermissiveStub $stub;

    public function __construct()
    {
        parent::__construct();
        $this->stub = new PermissiveStub();

        // FOSSBillingExtension tracks loaded assets via $di['loaded_assets'].
        $this['loaded_assets'] = [];
        $this['filesystem'] = new \Symfony\Component\Filesystem\Filesystem();
    }

    #[\Override]
    public function offsetExists(mixed $offset): bool
    {
        return true;
    }

    #[\Override]
    public function offsetGet(mixed $offset): mixed
    {
        if (parent::offsetExists($offset)) {
            return parent::offsetGet($offset);
        }

        // The LegacyExtension's `ip_country_name`/`ip_country_code` filters call
        // `$this->di['geoip']->country($ip)` and then read properties on the
        // returned record. The signatures are `: string`, so a permissive
        // stub would fail with a TypeError. Provide a stub object whose
        // `country()` call throws — the surrounding try/catch then returns
        // the empty-string fallback the real code emits when GeoIP is
        // unavailable.
        if ($offset === 'geoip') {
            return new class {
                public function __call(string $name, array $args): mixed
                {
                    throw new \RuntimeException('geoip service not available in test environment');
                }

                public function __get(string $name): mixed
                {
                    throw new \RuntimeException('geoip service not available in test environment');
                }
            };
        }

        return $this->stub;
    }

    #[\Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        parent::offsetSet($offset, $value);
    }
}
