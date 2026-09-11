<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core\Doctrine;

use Doctrine\ORM\Mapping as ORM;

/**
 * Standard `createdAt` lifecycle and accessors for Doctrine entities that only
 * track a creation timestamp (immutable log/association records without an
 * `updated_at` column).
 *
 * Classes that use this trait should add the class-level
 * `#[ORM\HasLifecycleCallbacks]` attribute.
 */
trait CreatedAtTrait
{
    #[ORM\Column(name: 'created_at', type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $createdAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt ??= new \DateTime();
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
