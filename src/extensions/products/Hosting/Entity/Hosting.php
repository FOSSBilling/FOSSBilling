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

#[ORM\Entity(repositoryClass: \FOSSBilling\ProductType\Hosting\Repository\HostingRepository::class)]
#[ORM\Table(name: 'ext_product_hosting')]
class Hosting implements ApiArrayInterface, TimestampInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    /** @phpstan-readonly */
    private int $id;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    private int $clientId;

    #[ORM\ManyToOne(targetEntity: HostingServer::class)]
    #[ORM\JoinColumn(name: 'ext_product_hosting_server_id', referencedColumnName: 'id')]
    private ?HostingServer $server = null;

    #[ORM\ManyToOne(targetEntity: HostingPlan::class)]
    #[ORM\JoinColumn(name: 'ext_product_hosting_plan_id', referencedColumnName: 'id')]
    private ?HostingPlan $plan = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $sld = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $tld = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 45, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $pass = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN, nullable: true)]
    private ?bool $reseller = null;

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
            'sld' => $this->sld,
            'tld' => $this->tld,
            'ip' => $this->ip,
            'username' => $this->username,
            'reseller' => $this->reseller,
            'server_id' => $this->server?->getId(),
            'plan_id' => $this->plan?->getId(),
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

    public function getServer(): ?HostingServer
    {
        return $this->server;
    }

    public function setServer(?HostingServer $server): self
    {
        $this->server = $server;

        return $this;
    }

    public function getPlan(): ?HostingPlan
    {
        return $this->plan;
    }

    public function setPlan(?HostingPlan $plan): self
    {
        $this->plan = $plan;

        return $this;
    }

    public function getSld(): ?string
    {
        return $this->sld;
    }

    public function setSld(?string $sld): self
    {
        $this->sld = $sld;

        return $this;
    }

    public function getTld(): ?string
    {
        return $this->tld;
    }

    public function setTld(?string $tld): self
    {
        $this->tld = $tld;

        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getPass(): ?string
    {
        return $this->pass;
    }

    public function setPass(?string $pass): self
    {
        $this->pass = $pass;

        return $this;
    }

    public function isReseller(): ?bool
    {
        return $this->reseller;
    }

    public function setReseller(?bool $reseller): self
    {
        $this->reseller = $reseller;

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
