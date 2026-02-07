<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace FOSSBilling\Tests;

use PHPUnit\Framework\TestCase;

abstract class UnitTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $refl = new \ReflectionObject($this);
        foreach ($refl->getProperties() as $prop) {
            if (!$prop->isStatic() && !str_starts_with($prop->getDeclaringClass()->getName(), 'PHPUnit_')) {
                try {
                    $prop->setValue($this, null);
                } catch (\Throwable $e) {
                    // Ignore errors when clearing properties
                }
            }
        }
    }

    protected function getMinimalDi(): \Pimple\Container
    {
        $di = new \Pimple\Container();
        $di['config'] = [
            'salt' => 'test_salt_' . uniqid(),
            'url' => 'http://localhost/',
        ];
        $di['validator'] = fn(): \FOSSBilling\Validate => new \FOSSBilling\Validate();
        $di['tools'] = fn(): \FOSSBilling\Tools => new \FOSSBilling\Tools();

        return $di;
    }

    protected function setPrivateProperty(object $instance, string $property, mixed $value): void
    {
        $refl = new \ReflectionClass($instance);
        $prop = $refl->hasProperty($property) ? $refl->getProperty($property) : null;

        if ($prop && !$prop->isStatic()) {
            $prop->setAccessible(true);
            $prop->setValue($instance, $value);
        } else {
            $prop = $refl->getParentClass()?->getProperty($property);
            if ($prop && !$prop->isStatic()) {
                $prop->setAccessible(true);
                $prop->setValue($instance, $value);
            }
        }
    }

    protected function getPrivateProperty(object $instance, string $property): mixed
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
}
