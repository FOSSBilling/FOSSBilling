<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\ProductType\License\Entity;

use Doctrine\ORM\Mapping as ORM;
use FOSSBilling\Interfaces\ApiArrayInterface;
use FOSSBilling\Interfaces\TimestampInterface;

#[ORM\Entity(repositoryClass: \FOSSBilling\ProductType\License\Repository\LicenseRepository::class)]
#[ORM\Table(name: 'ext_product_license')]
class License implements ApiArrayInterface, TimestampInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    /** @phpstan-readonly */
    private int $id;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    private int $clientId;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, unique: true)]
    private ?string $licenseKey = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN)]
    private bool $validateIp = true;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN)]
    private bool $validateHost = true;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN)]
    private bool $validatePath = false;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN)]
    private bool $validateVersion = false;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $ips = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $hosts = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $paths = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $versions = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $config = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $plugin = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $checkedAt = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $pingedAt = null;

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
            'license_key' => $this->licenseKey,
            'validate_ip' => $this->validateIp,
            'validate_host' => $this->validateHost,
            'validate_path' => $this->validatePath,
            'validate_version' => $this->validateVersion,
            'plugin' => $this->plugin,
            'checked_at' => $this->checkedAt?->format('Y-m-d H:i:s'),
            'pinged_at' => $this->pingedAt?->format('Y-m-d H:i:s'),
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

    public function getLicenseKey(): ?string
    {
        return $this->licenseKey;
    }

    public function setLicenseKey(?string $licenseKey): self
    {
        $this->licenseKey = $licenseKey;

        return $this;
    }

    public function isValidateIp(): bool
    {
        return $this->validateIp;
    }

    public function setValidateIp(bool $validateIp): self
    {
        $this->validateIp = $validateIp;

        return $this;
    }

    public function isValidateHost(): bool
    {
        return $this->validateHost;
    }

    public function setValidateHost(bool $validateHost): self
    {
        $this->validateHost = $validateHost;

        return $this;
    }

    public function isValidatePath(): bool
    {
        return $this->validatePath;
    }

    public function setValidatePath(bool $validatePath): self
    {
        $this->validatePath = $validatePath;

        return $this;
    }

    public function isValidateVersion(): bool
    {
        return $this->validateVersion;
    }

    public function setValidateVersion(bool $validateVersion): self
    {
        $this->validateVersion = $validateVersion;

        return $this;
    }

    public function getIps(): ?string
    {
        return $this->ips;
    }

    public function setIps(?string $ips): self
    {
        $this->ips = $ips;

        return $this;
    }

    public function getHosts(): ?string
    {
        return $this->hosts;
    }

    public function setHosts(?string $hosts): self
    {
        $this->hosts = $hosts;

        return $this;
    }

    public function getPaths(): ?string
    {
        return $this->paths;
    }

    public function setPaths(?string $paths): self
    {
        $this->paths = $paths;

        return $this;
    }

    public function getVersions(): ?string
    {
        return $this->versions;
    }

    public function setVersions(?string $versions): self
    {
        $this->versions = $versions;

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

    public function getPlugin(): ?string
    {
        return $this->plugin;
    }

    public function setPlugin(?string $plugin): self
    {
        $this->plugin = $plugin;

        return $this;
    }

    public function getCheckedAt(): ?\DateTime
    {
        return $this->checkedAt;
    }

    public function setCheckedAt(?\DateTime $checkedAt): self
    {
        $this->checkedAt = $checkedAt;

        return $this;
    }

    public function getPingedAt(): ?\DateTime
    {
        return $this->pingedAt;
    }

    public function setPingedAt(?\DateTime $pingedAt): self
    {
        $this->pingedAt = $pingedAt;

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

    public function getAllowedIps(): array
    {
        if (empty($this->ips)) {
            return [];
        }

        $decoded = json_decode($this->ips, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function getAllowedHosts(): array
    {
        if (empty($this->hosts)) {
            return [];
        }

        $decoded = json_decode($this->hosts, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function getAllowedPaths(): array
    {
        if (empty($this->paths)) {
            return [];
        }

        $decoded = json_decode($this->paths, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function getAllowedVersions(): array
    {
        if (empty($this->versions)) {
            return [];
        }

        $decoded = json_decode($this->versions, true);

        return is_array($decoded) ? $decoded : [];
    }
}
