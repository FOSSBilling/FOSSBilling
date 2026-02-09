<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace Tests\Helpers;

use Pimple\Container;
use Psr\Log\NullLogger;

/**
 * Create a minimal DI container for testing.
 */
function container(): Container
{
    $di = new Container();
    $di['config'] = [
        'salt' => 'test_salt_' . uniqid(),
        'url' => 'http://localhost/',
    ];
    $di['validator'] = fn (): \FOSSBilling\Validate => new \FOSSBilling\Validate();
    $di['tools'] = fn (): \FOSSBilling\Tools => new \FOSSBilling\Tools();
    $di['logger'] = fn (): \Psr\Log\LoggerInterface => new NullLogger();

    return $di;
}

/**
 * Set a private property value on an object using reflection.
 */
function setPrivateProperty(object $instance, string $property, mixed $value): void
{
    $refl = new \ReflectionClass($instance);
    $prop = $refl->hasProperty($property) ? $refl->getProperty($property) : null;

    if ($prop && !$prop->isStatic()) {
        $prop->setAccessible(true);
        $prop->setValue($instance, $value);

        return;
    }

    $prop = $refl->getParentClass()?->getProperty($property);
    if ($prop && !$prop->isStatic()) {
        $prop->setAccessible(true);
        $prop->setValue($instance, $value);
    }
}

/**
 * Get a private property value from an object using reflection.
 */
function getPrivateProperty(object $instance, string $property): mixed
{
    $refl = new \ReflectionClass($instance);
    $prop = $refl->hasProperty($property) ? $refl->getProperty($property) : null;

    if ($prop && !$prop->isStatic()) {
        $prop->setAccessible(true);

        return $prop->getValue($instance);
    }

    $prop = $refl->getParentClass()?->getProperty($property);
    if ($prop && !$prop->isStatic()) {
        $prop->setAccessible(true);

        return $prop->getValue($instance);
    }

    return null;
}

/**
 * Inject a mock filesystem into a service that has a private filesystem property.
 */
function injectMockFilesystem(object $service, \Mockery\MockInterface $filesystemMock): void
{
    $refl = new \ReflectionClass($service);
    $prop = $refl->getProperty('filesystem');
    $prop->setAccessible(true);
    $prop->setValue($service, $filesystemMock);
}
