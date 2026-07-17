<?php

declare(strict_types=1);

namespace Box\Mod\Servicecustom\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \Box\Mod\Servicecustom\Repository\ServiceCustomRepository::class)]
#[ORM\Table(name: 'service_custom')]
#[ORM\Index(name: 'client_id_idx', columns: ['client_id'])]
#[ORM\HasLifecycleCallbacks]
class ServiceCustom
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'client_id', type: Types::BIGINT, nullable: true)]
    private ?int $clientId = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $plugin = null;

    #[ORM\Column(name: 'plugin_config', type: Types::TEXT, nullable: true)]
    private ?string $pluginConfig = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $f1 = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $f2 = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $f3 = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $f4 = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $f5 = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $f6 = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $f7 = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $f8 = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $f9 = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $f10 = null;

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

    public function getClientId(): ?int
    {
        return $this->clientId;
    }

    public function setClientId(?int $clientId): void
    {
        $this->clientId = $clientId;
    }

    public function getPlugin(): ?string
    {
        return $this->plugin;
    }

    public function setPlugin(?string $plugin): void
    {
        $this->plugin = $plugin;
    }

    public function getPluginConfig(): ?string
    {
        return $this->pluginConfig;
    }

    public function setPluginConfig(?string $pluginConfig): void
    {
        $this->pluginConfig = $pluginConfig;
    }

    public function getF1(): ?string
    {
        return $this->f1;
    }

    public function setF1(?string $f1): void
    {
        $this->f1 = $f1;
    }

    public function getF2(): ?string
    {
        return $this->f2;
    }

    public function setF2(?string $f2): void
    {
        $this->f2 = $f2;
    }

    public function getF3(): ?string
    {
        return $this->f3;
    }

    public function setF3(?string $f3): void
    {
        $this->f3 = $f3;
    }

    public function getF4(): ?string
    {
        return $this->f4;
    }

    public function setF4(?string $f4): void
    {
        $this->f4 = $f4;
    }

    public function getF5(): ?string
    {
        return $this->f5;
    }

    public function setF5(?string $f5): void
    {
        $this->f5 = $f5;
    }

    public function getF6(): ?string
    {
        return $this->f6;
    }

    public function setF6(?string $f6): void
    {
        $this->f6 = $f6;
    }

    public function getF7(): ?string
    {
        return $this->f7;
    }

    public function setF7(?string $f7): void
    {
        $this->f7 = $f7;
    }

    public function getF8(): ?string
    {
        return $this->f8;
    }

    public function setF8(?string $f8): void
    {
        $this->f8 = $f8;
    }

    public function getF9(): ?string
    {
        return $this->f9;
    }

    public function setF9(?string $f9): void
    {
        $this->f9 = $f9;
    }

    public function getF10(): ?string
    {
        return $this->f10;
    }

    public function setF10(?string $f10): void
    {
        $this->f10 = $f10;
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
