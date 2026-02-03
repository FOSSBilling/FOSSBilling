<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\ProductType\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use FOSSBilling\Interfaces\ApiArrayInterface;
use FOSSBilling\Interfaces\TimestampInterface;

#[ORM\Entity(repositoryClass: \FOSSBilling\ProductType\Domain\Repository\TldRepository::class)]
#[ORM\Table(name: 'tld')]
class Tld implements ApiArrayInterface, TimestampInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    private int $id;

    #[ORM\ManyToOne(targetEntity: TldRegistrar::class, inversedBy: 'tlds')]
    #[ORM\JoinColumn(name: 'tld_registrar_id', referencedColumnName: 'id')]
    private ?TldRegistrar $registrar = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 15, unique: true)]
    private string $tld;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DECIMAL, precision: 18, scale: 2, options: ['default' => '0.00'])]
    private string $priceRegistration = '0.00';

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DECIMAL, precision: 18, scale: 2, options: ['default' => '0.00'])]
    private string $priceRenew = '0.00';

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DECIMAL, precision: 18, scale: 2, options: ['default' => '0.00'])]
    private string $priceTransfer = '0.00';

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN, nullable: true)]
    private ?bool $allowRegister = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN, nullable: true)]
    private ?bool $allowTransfer = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN)]
    private bool $active = true;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: true)]
    private ?int $minYears = null;

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
            'tld' => $this->tld,
            'price_registration' => $this->getPriceRegistration(),
            'price_renew' => $this->getPriceRenew(),
            'price_transfer' => $this->getPriceTransfer(),
            'allow_register' => $this->allowRegister,
            'allow_transfer' => $this->allowTransfer,
            'active' => $this->active,
            'min_years' => $this->minYears,
            'registrar_id' => $this->registrar?->getId(),
        ];
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getRegistrar(): ?TldRegistrar
    {
        return $this->registrar;
    }

    public function setRegistrar(?TldRegistrar $registrar): self
    {
        $this->registrar = $registrar;

        return $this;
    }

    public function getTld(): string
    {
        return $this->tld;
    }

    public function setTld(string $tld): self
    {
        $this->tld = $tld;

        return $this;
    }

    public function getPriceRegistration(): float
    {
        return (float) $this->priceRegistration;
    }

    public function setPriceRegistration(string|float $priceRegistration): self
    {
        $this->priceRegistration = is_float($priceRegistration) ? sprintf('%.2f', $priceRegistration) : $priceRegistration;

        return $this;
    }

    public function getPriceRenew(): float
    {
        return (float) $this->priceRenew;
    }

    public function setPriceRenew(string|float $priceRenew): self
    {
        $this->priceRenew = is_float($priceRenew) ? sprintf('%.2f', $priceRenew) : $priceRenew;

        return $this;
    }

    public function getPriceTransfer(): float
    {
        return (float) $this->priceTransfer;
    }

    public function setPriceTransfer(string|float $priceTransfer): self
    {
        $this->priceTransfer = is_float($priceTransfer) ? sprintf('%.2f', $priceTransfer) : $priceTransfer;

        return $this;
    }

    public function getAllowRegister(): ?bool
    {
        return $this->allowRegister;
    }

    public function setAllowRegister(?bool $allowRegister): self
    {
        $this->allowRegister = $allowRegister;

        return $this;
    }

    public function getAllowTransfer(): ?bool
    {
        return $this->allowTransfer;
    }

    public function setAllowTransfer(?bool $allowTransfer): self
    {
        $this->allowTransfer = $allowTransfer;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getMinYears(): ?int
    {
        return $this->minYears;
    }

    public function setMinYears(?int $minYears): self
    {
        $this->minYears = $minYears;

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
