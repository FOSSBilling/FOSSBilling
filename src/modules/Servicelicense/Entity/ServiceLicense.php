<?php

declare(strict_types=1);

namespace Box\Mod\Servicelicense\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \Box\Mod\Servicelicense\Repository\ServiceLicenseRepository::class)]
#[ORM\Table(name: 'service_license')]
#[ORM\Index(name: 'client_id_idx', columns: ['client_id'])]
#[ORM\HasLifecycleCallbacks]
class ServiceLicense
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'client_id', type: Types::BIGINT, nullable: true)]
    private ?int $clientId = null;

    #[ORM\Column(name: 'license_key', type: Types::STRING, length: 255, unique: true, nullable: true)]
    private ?string $licenseKey = null;

    #[ORM\Column(name: 'validate_ip', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $validateIp = true;

    #[ORM\Column(name: 'validate_host', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $validateHost = true;

    #[ORM\Column(name: 'validate_path', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $validatePath = false;

    #[ORM\Column(name: 'validate_version', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $validateVersion = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $ips = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $hosts = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $paths = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $versions = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $config = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $plugin = null;

    #[ORM\Column(name: 'checked_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $checkedAt = null;

    #[ORM\Column(name: 'pinged_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $pingedAt = null;

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

    public function getLicenseKey(): ?string
    {
        return $this->licenseKey;
    }

    public function setLicenseKey(?string $licenseKey): void
    {
        $this->licenseKey = $licenseKey;
    }

    public function getValidateIp(): bool
    {
        return $this->validateIp;
    }

    public function setValidateIp(bool $validateIp): void
    {
        $this->validateIp = $validateIp;
    }

    public function getValidateHost(): bool
    {
        return $this->validateHost;
    }

    public function setValidateHost(bool $validateHost): void
    {
        $this->validateHost = $validateHost;
    }

    public function getValidatePath(): bool
    {
        return $this->validatePath;
    }

    public function setValidatePath(bool $validatePath): void
    {
        $this->validatePath = $validatePath;
    }

    public function getValidateVersion(): bool
    {
        return $this->validateVersion;
    }

    public function setValidateVersion(bool $validateVersion): void
    {
        $this->validateVersion = $validateVersion;
    }

    public function getIps(): ?string
    {
        return $this->ips;
    }

    public function setIps(?string $ips): void
    {
        $this->ips = $ips;
    }

    public function getHosts(): ?string
    {
        return $this->hosts;
    }

    public function setHosts(?string $hosts): void
    {
        $this->hosts = $hosts;
    }

    public function getPaths(): ?string
    {
        return $this->paths;
    }

    public function setPaths(?string $paths): void
    {
        $this->paths = $paths;
    }

    public function getVersions(): ?string
    {
        return $this->versions;
    }

    public function setVersions(?string $versions): void
    {
        $this->versions = $versions;
    }

    public function getConfig(): ?string
    {
        return $this->config;
    }

    public function setConfig(?string $config): void
    {
        $this->config = $config;
    }

    public function getPlugin(): ?string
    {
        return $this->plugin;
    }

    public function setPlugin(?string $plugin): void
    {
        $this->plugin = $plugin;
    }

    public function getCheckedAt(): ?\DateTime
    {
        return $this->checkedAt;
    }

    public function setCheckedAt(?\DateTime $checkedAt): void
    {
        $this->checkedAt = $checkedAt;
    }

    public function getPingedAt(): ?\DateTime
    {
        return $this->pingedAt;
    }

    public function setPingedAt(?\DateTime $pingedAt): void
    {
        $this->pingedAt = $pingedAt;
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

    public function getAllowedIps(): array
    {
        return json_decode($this->ips ?? '', true) ?? [];
    }

    public function getAllowedVersions(): array
    {
        return json_decode($this->versions ?? '', true) ?? [];
    }

    public function getAllowedHosts(): array
    {
        return json_decode($this->hosts ?? '', true) ?? [];
    }

    public function getAllowedPaths(): array
    {
        return json_decode($this->paths ?? '', true) ?? [];
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
