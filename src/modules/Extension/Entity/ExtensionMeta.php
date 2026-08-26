<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Extension\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use FOSSBilling\Doctrine\TimestampTrait;
use FOSSBilling\Api\ArrayInterface;
use FOSSBilling\Doctrine\TimestampInterface;

#[ORM\Entity(repositoryClass: \Box\Mod\Extension\Repository\ExtensionMetaRepository::class)]
#[ORM\Table(name: 'extension_meta')]
// Named per-table (unlike structure.sql's bare `client_id_idx`) because index names must be
// unique database-wide on SQLite/PostgreSQL, not just per-table like MySQL.
#[ORM\Index(name: 'extension_meta_client_id_idx', columns: ['client_id'])]
#[ORM\HasLifecycleCallbacks]
class ExtensionMeta implements ArrayInterface, TimestampInterface
{
    use TimestampTrait;

    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(type: Types::BIGINT)]
        private ?int $id = null,
    ) {
    }

    #[ORM\Column(name: 'client_id', type: Types::BIGINT, nullable: true)]
    private ?int $clientId = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $extension = null;

    #[ORM\Column(name: 'rel_type', type: Types::STRING, length: 255, nullable: true)]
    private ?string $relType = null;

    #[ORM\Column(name: 'rel_id', type: Types::STRING, length: 255, nullable: true)]
    private ?string $relId = null;

    #[ORM\Column(name: 'meta_key', type: Types::STRING, length: 255, nullable: true)]
    private ?string $metaKey = null;

    #[ORM\Column(name: 'meta_value', type: Types::TEXT, nullable: true)]
    private ?string $metaValue = null;

    public function toApiArray(): array
    {
        return [
            'id' => $this->getId(),
            'client_id' => $this->getClientId(),
            'extension' => $this->getExtension(),
            'rel_type' => $this->getRelType(),
            'rel_id' => $this->getRelId(),
            'meta_key' => $this->getMetaKey(),
            'meta_value' => $this->getMetaValue(),
            'created_at' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $this->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClientId(): ?int
    {
        return $this->clientId;
    }

    public function setClientId(?int $clientId): self
    {
        $this->clientId = $clientId;

        return $this;
    }

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function setExtension(?string $extension): self
    {
        $this->extension = $extension;

        return $this;
    }

    public function getRelType(): ?string
    {
        return $this->relType;
    }

    public function setRelType(?string $relType): self
    {
        $this->relType = $relType;

        return $this;
    }

    public function getRelId(): ?string
    {
        return $this->relId;
    }

    public function setRelId(string|int|null $relId): self
    {
        $this->relId = $relId === null ? null : (string) $relId;

        return $this;
    }

    public function getMetaKey(): ?string
    {
        return $this->metaKey;
    }

    public function setMetaKey(?string $metaKey): self
    {
        $this->metaKey = $metaKey;

        return $this;
    }

    public function getMetaValue(): ?string
    {
        return $this->metaValue;
    }

    public function setMetaValue(?string $metaValue): self
    {
        $this->metaValue = $metaValue;

        return $this;
    }
}
