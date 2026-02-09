<?php

declare(strict_types=1);

namespace Tests\Helpers;

use Pimple\Container;

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
