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
 */
function setEntityId(object $entity, int $id): void
{
    $reflection = new \ReflectionProperty($entity, 'id');
    $reflection->setValue($entity, $id);
}
