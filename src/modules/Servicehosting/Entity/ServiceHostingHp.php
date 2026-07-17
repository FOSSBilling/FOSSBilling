<?php

declare(strict_types=1);

namespace Box\Mod\Servicehosting\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \Box\Mod\Servicehosting\Repository\ServiceHostingHpRepository::class)]
#[ORM\Table(name: 'service_hosting_hp')]
#[ORM\HasLifecycleCallbacks]
class ServiceHostingHp
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $quota = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $bandwidth = null;

    #[ORM\Column(name: 'max_ftp', type: Types::STRING, length: 50, nullable: true)]
    private ?string $maxFtp = null;

    #[ORM\Column(name: 'max_sql', type: Types::STRING, length: 50, nullable: true)]
    private ?string $maxSql = null;

    #[ORM\Column(name: 'max_pop', type: Types::STRING, length: 50, nullable: true)]
    private ?string $maxPop = null;

    #[ORM\Column(name: 'max_sub', type: Types::STRING, length: 50, nullable: true)]
    private ?string $maxSub = null;

    #[ORM\Column(name: 'max_park', type: Types::STRING, length: 50, nullable: true)]
    private ?string $maxPark = null;

    #[ORM\Column(name: 'max_addon', type: Types::STRING, length: 50, nullable: true)]
    private ?string $maxAddon = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $config = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getQuota(): ?string
    {
        return $this->quota;
    }

    public function setQuota(?string $quota): void
    {
        $this->quota = $quota;
    }

    public function getBandwidth(): ?string
    {
        return $this->bandwidth;
    }

    public function setBandwidth(?string $bandwidth): void
    {
        $this->bandwidth = $bandwidth;
    }

    public function getMaxFtp(): ?string
    {
        return $this->maxFtp;
    }

    public function setMaxFtp(?string $maxFtp): void
    {
        $this->maxFtp = $maxFtp;
    }

    public function getMaxSql(): ?string
    {
        return $this->maxSql;
    }

    public function setMaxSql(?string $maxSql): void
    {
        $this->maxSql = $maxSql;
    }

    public function getMaxPop(): ?string
    {
        return $this->maxPop;
    }

    public function setMaxPop(?string $maxPop): void
    {
        $this->maxPop = $maxPop;
    }

    public function getMaxSub(): ?string
    {
        return $this->maxSub;
    }

    public function setMaxSub(?string $maxSub): void
    {
        $this->maxSub = $maxSub;
    }

    public function getMaxPark(): ?string
    {
        return $this->maxPark;
    }

    public function setMaxPark(?string $maxPark): void
    {
        $this->maxPark = $maxPark;
    }

    public function getMaxAddon(): ?string
    {
        return $this->maxAddon;
    }

    public function setMaxAddon(?string $maxAddon): void
    {
        $this->maxAddon = $maxAddon;
    }

    public function getConfig(): ?string
    {
        return $this->config;
    }

    public function setConfig(?string $config): void
    {
        $this->config = $config;
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

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
