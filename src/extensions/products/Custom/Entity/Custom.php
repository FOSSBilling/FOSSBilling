<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\ProductType\Custom\Entity;

use Doctrine\ORM\Mapping as ORM;
use FOSSBilling\Interfaces\ApiArrayInterface;
use FOSSBilling\Interfaces\TimestampInterface;

#[ORM\Entity(repositoryClass: \FOSSBilling\ProductType\Custom\Repository\CustomRepository::class)]
#[ORM\Table(name: 'ext_product_custom')]
class Custom implements ApiArrayInterface, TimestampInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    /** @phpstan-readonly */
    private int $id;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    private int $clientId;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $plugin = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $pluginConfig = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $f1 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $f2 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $f3 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $f4 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $f5 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $f6 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $f7 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $f8 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $f9 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $f10 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $config = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $updatedAt = null;

    public function __construct(int $clientId)
    {
        $this->clientId = $clientId;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new \DateTime();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->clientId,
            'plugin' => $this->plugin,
            'f1' => $this->f1,
            'f2' => $this->f2,
            'f3' => $this->f3,
            'f4' => $this->f4,
            'f5' => $this->f5,
            'f6' => $this->f6,
            'f7' => $this->f7,
            'f8' => $this->f8,
            'f9' => $this->f9,
            'f10' => $this->f10,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getClientId(): int
    {
        return $this->clientId;
    }

    public function setClientId(int $clientId): self
    {
        $this->clientId = $clientId;

        return $this;
    }

    public function getPlugin(): ?string
    {
        return $this->plugin;
    }

    public function setPlugin(?string $plugin): self
    {
        $this->plugin = $plugin;

        return $this;
    }

    public function getPluginConfig(): ?string
    {
        return $this->pluginConfig;
    }

    public function setPluginConfig(?string $pluginConfig): self
    {
        $this->pluginConfig = $pluginConfig;

        return $this;
    }

    public function getF1(): ?string
    {
        return $this->f1;
    }

    public function setF1(?string $f1): self
    {
        $this->f1 = $f1;

        return $this;
    }

    public function getF2(): ?string
    {
        return $this->f2;
    }

    public function setF2(?string $f2): self
    {
        $this->f2 = $f2;

        return $this;
    }

    public function getF3(): ?string
    {
        return $this->f3;
    }

    public function setF3(?string $f3): self
    {
        $this->f3 = $f3;

        return $this;
    }

    public function getF4(): ?string
    {
        return $this->f4;
    }

    public function setF4(?string $f4): self
    {
        $this->f4 = $f4;

        return $this;
    }

    public function getF5(): ?string
    {
        return $this->f5;
    }

    public function setF5(?string $f5): self
    {
        $this->f5 = $f5;

        return $this;
    }

    public function getF6(): ?string
    {
        return $this->f6;
    }

    public function setF6(?string $f6): self
    {
        $this->f6 = $f6;

        return $this;
    }

    public function getF7(): ?string
    {
        return $this->f7;
    }

    public function setF7(?string $f7): self
    {
        $this->f7 = $f7;

        return $this;
    }

    public function getF8(): ?string
    {
        return $this->f8;
    }

    public function setF8(?string $f8): self
    {
        $this->f8 = $f8;

        return $this;
    }

    public function getF9(): ?string
    {
        return $this->f9;
    }

    public function setF9(?string $f9): self
    {
        $this->f9 = $f9;

        return $this;
    }

    public function getF10(): ?string
    {
        return $this->f10;
    }

    public function setF10(?string $f10): self
    {
        $this->f10 = $f10;

        return $this;
    }

    public function getConfig(): ?string
    {
        return $this->config;
    }

    public function setConfig(?string $config): self
    {
        $this->config = $config;

        return $this;
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
