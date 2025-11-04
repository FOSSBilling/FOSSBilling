<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Client\Entity;

use Doctrine\ORM\Mapping as ORM;
use FOSSBilling\Interfaces\TimestampInterface;

/**
 * Client Password Reset entity - tracks password reset requests.
 */
#[ORM\Entity(repositoryClass: \Box\Mod\Client\Repository\ClientPasswordResetRepository::class)]
#[ORM\Table(name: 'client_password_reset')]
#[ORM\HasLifecycleCallbacks]
class ClientPasswordReset implements TimestampInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    private int $id;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    private int $client_id;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 100, unique: true)]
    private string $hash;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 45)]
    private string $ip;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE)]
    private \DateTime $created_at;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE)]
    private \DateTime $updated_at;

    /**
     * PrePersist lifecycle callback.
     */
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->created_at = new \DateTime();
        $this->updated_at = new \DateTime();
    }

    /**
     * PreUpdate lifecycle callback.
     */
    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updated_at = new \DateTime();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getClientId(): int
    {
        return $this->client_id;
    }

    public function setClientId(int $client_id): self
    {
        $this->client_id = $client_id;

        return $this;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function setHash(string $hash): self
    {
        $this->hash = $hash;

        return $this;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function setIp(string $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updated_at;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->created_at = $createdAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updated_at = $updatedAt;
    }
}
