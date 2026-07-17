<?php

declare(strict_types=1);

namespace Box\Mod\Servicehosting\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \Box\Mod\Servicehosting\Repository\ServiceHostingRepository::class)]
#[ORM\Table(name: 'service_hosting')]
#[ORM\Index(name: 'client_id_idx', columns: ['client_id'])]
#[ORM\Index(name: 'service_hosting_server_id_idx', columns: ['service_hosting_server_id'])]
#[ORM\Index(name: 'service_hosting_hp_id_idx', columns: ['service_hosting_hp_id'])]
#[ORM\HasLifecycleCallbacks]
class ServiceHosting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'client_id', type: Types::BIGINT, nullable: true)]
    private ?int $clientId = null;

    #[ORM\Column(name: 'service_hosting_server_id', type: Types::BIGINT, nullable: true)]
    private ?int $serviceHostingServerId = null;

    #[ORM\Column(name: 'service_hosting_hp_id', type: Types::BIGINT, nullable: true)]
    private ?int $serviceHostingHpId = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $sld = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $tld = null;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $pass = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $reseller = null;

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

    public function getServiceHostingServerId(): ?int
    {
        return $this->serviceHostingServerId;
    }

    public function setServiceHostingServerId(?int $serviceHostingServerId): void
    {
        $this->serviceHostingServerId = $serviceHostingServerId;
    }

    public function getServiceHostingHpId(): ?int
    {
        return $this->serviceHostingHpId;
    }

    public function setServiceHostingHpId(?int $serviceHostingHpId): void
    {
        $this->serviceHostingHpId = $serviceHostingHpId;
    }

    public function getSld(): ?string
    {
        return $this->sld;
    }

    public function setSld(?string $sld): void
    {
        $this->sld = $sld;
    }

    public function getTld(): ?string
    {
        return $this->tld;
    }

    public function setTld(?string $tld): void
    {
        $this->tld = $tld;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): void
    {
        $this->ip = $ip;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): void
    {
        $this->username = $username;
    }

    public function getPass(): ?string
    {
        return $this->pass;
    }

    public function setPass(?string $pass): void
    {
        $this->pass = $pass;
    }

    public function isReseller(): ?bool
    {
        return $this->reseller;
    }

    public function setReseller(?bool $reseller): void
    {
        $this->reseller = $reseller;
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
