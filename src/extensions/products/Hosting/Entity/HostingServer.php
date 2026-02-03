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

#[ORM\Entity(repositoryClass: \FOSSBilling\ProductType\Hosting\Repository\HostingServerRepository::class)]
#[ORM\Table(name: 'ext_product_hosting_server')]
class HostingServer implements ApiArrayInterface, TimestampInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    /** @phpstan-readonly */
    private int $id;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 100, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 45, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 100, nullable: true)]
    private ?string $hostname = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $assignedIps = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $statusUrl = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN, nullable: true)]
    private ?bool $active = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BIGINT, nullable: true)]
    private ?int $maxAccounts = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 100, nullable: true)]
    private ?string $ns1 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 100, nullable: true)]
    private ?string $ns2 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 100, nullable: true)]
    private ?string $ns3 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 100, nullable: true)]
    private ?string $ns4 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 100, nullable: true)]
    private ?string $manager = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $password = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $accessHash = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: true)]
    private ?int $passwordLength = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 20, nullable: true)]
    private ?string $port = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $config = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN, nullable: true)]
    private ?bool $secure = null;

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
            'ip' => $this->ip,
            'hostname' => $this->hostname,
            'assigned_ips' => $this->assignedIps,
            'status_url' => $this->statusUrl,
            'active' => $this->active,
            'max_accounts' => $this->maxAccounts,
            'ns1' => $this->ns1,
            'ns2' => $this->ns2,
            'ns3' => $this->ns3,
            'ns4' => $this->ns4,
            'manager' => $this->manager,
            'username' => $this->username,
            'port' => $this->port,
            'secure' => $this->secure,
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

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    public function getHostname(): ?string
    {
        return $this->hostname;
    }

    public function setHostname(?string $hostname): self
    {
        $this->hostname = $hostname;

        return $this;
    }

    public function getAssignedIps(): ?string
    {
        return $this->assignedIps;
    }

    public function setAssignedIps(?string $assignedIps): self
    {
        $this->assignedIps = $assignedIps;

        return $this;
    }

    public function getStatusUrl(): ?string
    {
        return $this->statusUrl;
    }

    public function setStatusUrl(?string $statusUrl): self
    {
        $this->statusUrl = $statusUrl;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getMaxAccounts(): ?int
    {
        return $this->maxAccounts;
    }

    public function setMaxAccounts(?int $maxAccounts): self
    {
        $this->maxAccounts = $maxAccounts;

        return $this;
    }

    public function getNs1(): ?string
    {
        return $this->ns1;
    }

    public function setNs1(?string $ns1): self
    {
        $this->ns1 = $ns1;

        return $this;
    }

    public function getNs2(): ?string
    {
        return $this->ns2;
    }

    public function setNs2(?string $ns2): self
    {
        $this->ns2 = $ns2;

        return $this;
    }

    public function getNs3(): ?string
    {
        return $this->ns3;
    }

    public function setNs3(?string $ns3): self
    {
        $this->ns3 = $ns3;

        return $this;
    }

    public function getNs4(): ?string
    {
        return $this->ns4;
    }

    public function setNs4(?string $ns4): self
    {
        $this->ns4 = $ns4;

        return $this;
    }

    public function getManager(): ?string
    {
        return $this->manager;
    }

    public function setManager(?string $manager): self
    {
        $this->manager = $manager;

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

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getAccessHash(): ?string
    {
        return $this->accessHash;
    }

    public function setAccessHash(?string $accessHash): self
    {
        $this->accessHash = $accessHash;

        return $this;
    }

    public function getPasswordLength(): ?int
    {
        return $this->passwordLength;
    }

    public function setPasswordLength(?int $passwordLength): self
    {
        $this->passwordLength = $passwordLength;

        return $this;
    }

    public function getPort(): ?string
    {
        return $this->port;
    }

    public function setPort(?string $port): self
    {
        $this->port = $port;

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

    public function isSecure(): ?bool
    {
        return $this->secure;
    }

    public function setSecure(?bool $secure): self
    {
        $this->secure = $secure;

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
