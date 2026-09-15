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

/**
 * Force the value of an entity's `id` property via reflection.
 *
 * Doctrine entities have private `id` properties; tests need to set them
 * directly to simulate a persisted row without going through the database.
 *
 * Entities may be subclassed by the `createEntity` proxy helper, so the `id`
 * property can live on a parent class and must be located by walking up the
 * inheritance chain.
 *
 * @template T of object
 *
 * @param T $entity
 *
 * @return T
 */
function setEntityId(object $entity, int $id): object
{
    $reflection = new \ReflectionClass($entity);
    while (!$reflection->hasProperty('id')) {
        $parent = $reflection->getParentClass();
        if ($parent === false) {
            throw new \ReflectionException(sprintf('Property %s::$id does not exist', $entity::class));
        }
        $reflection = $parent;
    }

    $reflection->getProperty('id')->setValue($entity, $id);

    return $entity;
}
