<?php

declare(strict_types=1);

namespace Box\Mod\Servicehosting\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \Box\Mod\Servicehosting\Repository\ServiceHostingServerRepository::class)]
#[ORM\Table(name: 'service_hosting_server')]
#[ORM\HasLifecycleCallbacks]
class ServiceHostingServer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $hostname = null;

    #[ORM\Column(name: 'assigned_ips', type: Types::TEXT, nullable: true)]
    private ?string $assignedIps = null;

    #[ORM\Column(name: 'status_url', type: Types::STRING, length: 255, nullable: true)]
    private ?string $statusUrl = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $active = null;

    #[ORM\Column(name: 'max_accounts', type: Types::BIGINT, nullable: true)]
    private ?int $maxAccounts = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $ns1 = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $ns2 = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $ns3 = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $ns4 = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $manager = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $password = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $accesshash = null;

    #[ORM\Column(name: 'password_length', type: Types::SMALLINT, nullable: true)]
    private ?int $passwordLength = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    private ?string $port = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $config = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $secure = null;

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

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): void
    {
        $this->ip = $ip;
    }

    public function getHostname(): ?string
    {
        return $this->hostname;
    }

    public function setHostname(?string $hostname): void
    {
        $this->hostname = $hostname;
    }

    public function getAssignedIps(): ?string
    {
        return $this->assignedIps;
    }

    public function setAssignedIps(?string $assignedIps): void
    {
        $this->assignedIps = $assignedIps;
    }

    public function getStatusUrl(): ?string
    {
        return $this->statusUrl;
    }

    public function setStatusUrl(?string $statusUrl): void
    {
        $this->statusUrl = $statusUrl;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): void
    {
        $this->active = $active;
    }

    public function getMaxAccounts(): ?int
    {
        return $this->maxAccounts;
    }

    public function setMaxAccounts(?int $maxAccounts): void
    {
        $this->maxAccounts = $maxAccounts;
    }

    public function getNs1(): ?string
    {
        return $this->ns1;
    }

    public function setNs1(?string $ns1): void
    {
        $this->ns1 = $ns1;
    }

    public function getNs2(): ?string
    {
        return $this->ns2;
    }

    public function setNs2(?string $ns2): void
    {
        $this->ns2 = $ns2;
    }

    public function getNs3(): ?string
    {
        return $this->ns3;
    }

    public function setNs3(?string $ns3): void
    {
        $this->ns3 = $ns3;
    }

    public function getNs4(): ?string
    {
        return $this->ns4;
    }

    public function setNs4(?string $ns4): void
    {
        $this->ns4 = $ns4;
    }

    public function getManager(): ?string
    {
        return $this->manager;
    }

    public function setManager(?string $manager): void
    {
        $this->manager = $manager;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): void
    {
        $this->username = $username;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): void
    {
        $this->password = $password;
    }

    public function getAccesshash(): ?string
    {
        return $this->accesshash;
    }

    public function setAccesshash(?string $accesshash): void
    {
        $this->accesshash = $accesshash;
    }

    public function getPasswordLength(): ?int
    {
        return $this->passwordLength;
    }

    public function setPasswordLength(?int $passwordLength): void
    {
        $this->passwordLength = $passwordLength;
    }

    public function getPort(): ?string
    {
        return $this->port;
    }

    public function setPort(?string $port): void
    {
        $this->port = $port;
    }

    public function getConfig(): ?string
    {
        return $this->config;
    }

    public function setConfig(?string $config): void
    {
        $this->config = $config;
    }

    public function isSecure(): ?bool
    {
        return $this->secure;
    }

    public function setSecure(?bool $secure): void
    {
        $this->secure = $secure;
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
