<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Doctrine;

use Doctrine\ORM\Mapping as ORM;

/**
 * Standard `createdAt` / `updatedAt` lifecycle and accessors for Doctrine entities.
 *
 * Classes that use this trait should:
 *  - Add the class-level `#[ORM\HasLifecycleCallbacks]` attribute
 *  - Implement {@see \FOSSBilling\Interfaces\TimestampInterface}
 *
 * The trait provides:
 *  - `createdAt` and `updatedAt` properties mapped to the `created_at` / `updated_at`
 *    `DATETIME_MUTABLE` columns
 *  - `onPrePersist()` lifecycle callback that initialises both timestamps
 *  - `onPreUpdate()` lifecycle callback that bumps `updatedAt`
 *  - Getter and setter methods satisfying {@see \FOSSBilling\Interfaces\TimestampInterface}
 *
 * Special-cases:
 *  - Entities that must not auto-bump `updatedAt` on every Doctrine update (e.g. when
 *    a non-state-changing field such as a view counter is updated) can override
 *    `onPreUpdate()` to be a no-op and manage `updatedAt` from explicit setters.
 *  - Entities that need a different column name (extremely rare) can redeclare the
 *    property with a new `#[ORM\Column(name: '...')]` attribute — the redeclaration
 *    takes precedence over the trait property.
 */
trait TimestampTrait
{
    #[ORM\Column(name: 'created_at', type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $updatedAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTime();
        $this->createdAt ??= $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
