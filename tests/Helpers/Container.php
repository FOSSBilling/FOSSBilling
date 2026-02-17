<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
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
 * Access a private property on an object using reflection.
 *
 * @return mixed The property value when getting, null when setting
 */
function accessPrivate(object $instance, string $property, mixed $value = null): mixed
{
    $refl = new \ReflectionClass($instance);
    $prop = null;

    if ($refl->hasProperty($property)) {
        $prop = $refl->getProperty($property);
    } else {
        $parentClass = $refl->getParentClass();
        if ($parentClass && $parentClass->hasProperty($property)) {
            $prop = $parentClass->getProperty($property);
        }
    }

    if ($prop === null || $prop->isStatic()) {
        return null;
    }

    $prop->setAccessible(true);

    // If value is provided, set the property
    if (func_num_args() > 2) {
        $prop->setValue($instance, $value);

        return null;
    }

    // Otherwise, get the property value
    return $prop->getValue($instance);
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
