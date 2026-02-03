<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\ProductType\Hosting\Entity;

use Doctrine\ORM\Mapping as ORM;
use FOSSBilling\Interfaces\ApiArrayInterface;
use FOSSBilling\Interfaces\TimestampInterface;

#[ORM\Entity(repositoryClass: \FOSSBilling\ProductType\Hosting\Repository\HostingPlanRepository::class)]
#[ORM\Table(name: 'ext_product_hosting_plan')]
class HostingPlan implements ApiArrayInterface, TimestampInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    /** @phpstan-readonly */
    private int $id;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 50, nullable: true)]
    private ?string $quota = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 50, nullable: true)]
    private ?string $bandwidth = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 50, nullable: true)]
    private ?string $maxFtp = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 50, nullable: true)]
    private ?string $maxSql = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 50, nullable: true)]
    private ?string $maxPop = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 50, nullable: true)]
    private ?string $maxSub = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 50, nullable: true)]
    private ?string $maxPark = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 50, nullable: true)]
    private ?string $maxAddon = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $config = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $updatedAt = null;

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
            'name' => $this->name,
            'quota' => $this->quota,
            'bandwidth' => $this->bandwidth,
            'max_ftp' => $this->maxFtp,
            'max_sql' => $this->maxSql,
            'max_pop' => $this->maxPop,
            'max_sub' => $this->maxSub,
            'max_park' => $this->maxPark,
            'max_addon' => $this->maxAddon,
        ];
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getQuota(): ?string
    {
        return $this->quota;
    }

    public function setQuota(?string $quota): self
    {
        $this->quota = $quota;

        return $this;
    }

    public function getBandwidth(): ?string
    {
        return $this->bandwidth;
    }

    public function setBandwidth(?string $bandwidth): self
    {
        $this->bandwidth = $bandwidth;

        return $this;
    }

    public function getMaxFtp(): ?string
    {
        return $this->maxFtp;
    }

    public function setMaxFtp(?string $maxFtp): self
    {
        $this->maxFtp = $maxFtp;

        return $this;
    }

    public function getMaxSql(): ?string
    {
        return $this->maxSql;
    }

    public function setMaxSql(?string $maxSql): self
    {
        $this->maxSql = $maxSql;

        return $this;
    }

    public function getMaxPop(): ?string
    {
        return $this->maxPop;
    }

    public function setMaxPop(?string $maxPop): self
    {
        $this->maxPop = $maxPop;

        return $this;
    }

    public function getMaxSub(): ?string
    {
        return $this->maxSub;
    }

    public function setMaxSub(?string $maxSub): self
    {
        $this->maxSub = $maxSub;

        return $this;
    }

    public function getMaxPark(): ?string
    {
        return $this->maxPark;
    }

    public function setMaxPark(?string $maxPark): self
    {
        $this->maxPark = $maxPark;

        return $this;
    }

    public function getMaxAddon(): ?string
    {
        return $this->maxAddon;
    }

    public function setMaxAddon(?string $maxAddon): self
    {
        $this->maxAddon = $maxAddon;

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
