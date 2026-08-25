<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\System\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Maps the `session` table purely so Doctrine's schema tooling knows about it - actual reads and
 * writes go through {@see \FOSSBilling\Session}'s own hand-written, already-portable parameterized
 * SQL, not this entity. Without a mapping here, generating schema from entity metadata (what
 * every fresh install does, on every platform - see {@see \FOSSBilling\Doctrine\SchemaInstaller})
 * would silently omit this table, since it's the one table with no other Doctrine entity.
 */
#[ORM\Entity]
#[ORM\Table(name: 'session')]
#[ORM\Index(name: 'session_lifetime_idx', columns: ['lifetime'])]
class Session
{
    #[ORM\Id]
    #[ORM\Column(type: Types::BINARY, length: 128)]
    private string $id;

    #[ORM\Column(name: 'modified_at', type: Types::INTEGER)]
    private int $modifiedAt;

    #[ORM\Column(name: 'created_at', type: Types::INTEGER, nullable: true)]
    private ?int $createdAt = null;

    #[ORM\Column(type: Types::BLOB)]
    private mixed $content;

    #[ORM\Column(type: Types::INTEGER)]
    private int $lifetime;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $fingerprint = null;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getModifiedAt(): int
    {
        return $this->modifiedAt;
    }

    public function setModifiedAt(int $modifiedAt): self
    {
        $this->modifiedAt = $modifiedAt;

        return $this;
    }

    public function getCreatedAt(): ?int
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?int $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getContent(): mixed
    {
        return $this->content;
    }

    public function setContent(mixed $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getLifetime(): int
    {
        return $this->lifetime;
    }

    public function setLifetime(int $lifetime): self
    {
        $this->lifetime = $lifetime;

        return $this;
    }

    public function getFingerprint(): ?string
    {
        return $this->fingerprint;
    }

    public function setFingerprint(?string $fingerprint): self
    {
        $this->fingerprint = $fingerprint;

        return $this;
    }
}
