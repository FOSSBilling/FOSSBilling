<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Api;

final readonly class Identity
{
    private string $type;

    public function __construct(private object $identity)
    {
        $this->type = self::typeFromObject($identity);
    }

    public static function typeFromObject(object $identity): string
    {
        $class = $identity::class;

        // Legacy RedBeanPHP model classes (e.g. Model_Admin, Model_Client, Model_Guest)
        if (str_starts_with($class, 'Model_')) {
            return str_replace('model_', '', strtolower($class));
        }

        // Doctrine entity classes — extract the short name (e.g. Client, Admin, Guest)
        $ref = new \ReflectionClass($class);
        $shortName = $ref->getShortName();

        // Handle entity proxy classes (e.g. Tests\Helpers\EntityProxy_xxx) by
        // falling back to the parent class short name.
        if (str_starts_with($shortName, 'EntityProxy_') && $parent = $ref->getParentClass()) {
            $shortName = $parent->getShortName();
        }

        return strtolower($shortName);
    }

    public function getIdentity(): object
    {
        return $this->identity;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
