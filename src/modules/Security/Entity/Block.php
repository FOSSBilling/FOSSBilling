<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Security\Entity;

use Doctrine\ORM\Mapping as ORM;
use Exception;
use FOSSBilling\Interfaces\ApiArrayInterface;
use FOSSBilling\Interfaces\TimestampInterface;

#[ORM\Entity(repositoryClass: \Box\Mod\Security\Repository\BlockRepository::class)]
#[ORM\Table(name: "post")]
#[ORM\HasLifecycleCallbacks]
class Block implements ApiArrayInterface, TimestampInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: "integer")]
    private int $id;

    #[ORM\Column(type: "string", length: 255, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: "binary")]
    private ?string $address;

    #[ORM\Column(type: "bool", options: ["default" => 0])]
    private bool $isV6 = false;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTime $updatedAt = null;

    public function __construct(string $address)
    {
        $this->setIp($address);
    }

    public function toApiArray(): array
    {
        return [];
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

    // --- Getters ---
    public function getId(): int
    {
        return $this->id;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function getIp(): ?string
    {
        if ($this->address === null) {
            return null;
        }

        // Detect IPv4‑mapped ::ffff:a.b.c.d
        if (substr($this->address, 0, 12) === str_repeat("\x00", 10) . "\xFF\xFF") {
            return inet_ntop(substr($this->address, 12)); // last 4 bytes
        }

        return inet_ntop($this->address); // real IPv6
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    // --- Setters ---
    public function setNote(?string $note): self
    {
        $this->note = $note;
        return $this;
    }

    public function setIp(string $address): self
    {
        // Basic validation – throws InvalidArgumentException on failure
        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            throw new Exception(sprintf('Invalid IP address: %s', $address));
        }

        $bin = inet_pton($address);          // 4 or 16 bytes
        if (strlen($bin) === 4) {          // IPv4 → map to ::ffff:a.b.c.d
            $bin = str_repeat("\x00", 10) . "\xFF\xFF" . $bin;
        }
        $this->address = $bin; // 16‑byte binary
        return $this;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
