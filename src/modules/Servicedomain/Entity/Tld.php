<?php

declare(strict_types=1);

namespace Box\Mod\Servicedomain\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \Box\Mod\Servicedomain\Repository\TldRepository::class)]
#[ORM\Table(name: 'tld')]
#[ORM\Index(name: 'tld_registrar_id_idx', columns: ['tld_registrar_id'])]
#[ORM\HasLifecycleCallbacks]
class Tld
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'tld_registrar_id', type: Types::BIGINT, nullable: true)]
    private ?int $tldRegistrarId = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true, unique: true)]
    private ?string $tld = null;

    #[ORM\Column(name: 'price_registration', type: Types::DECIMAL, precision: 18, scale: 2, nullable: true, options: ['default' => '0.00'])]
    private ?string $priceRegistration = null;

    #[ORM\Column(name: 'price_renew', type: Types::DECIMAL, precision: 18, scale: 2, nullable: true, options: ['default' => '0.00'])]
    private ?string $priceRenew = null;

    #[ORM\Column(name: 'price_transfer', type: Types::DECIMAL, precision: 18, scale: 2, nullable: true, options: ['default' => '0.00'])]
    private ?string $priceTransfer = null;

    #[ORM\Column(name: 'allow_register', type: Types::BOOLEAN, nullable: true)]
    private ?bool $allowRegister = null;

    #[ORM\Column(name: 'allow_transfer', type: Types::BOOLEAN, nullable: true)]
    private ?bool $allowTransfer = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => true])]
    private ?bool $active = true;

    #[ORM\Column(name: 'min_years', type: Types::SMALLINT, nullable: true)]
    private ?int $minYears = null;

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

    public function getTldRegistrarId(): ?int
    {
        return $this->tldRegistrarId;
    }

    public function setTldRegistrarId(?int $tldRegistrarId): void
    {
        $this->tldRegistrarId = $tldRegistrarId;
    }

    public function getTld(): ?string
    {
        return $this->tld;
    }

    public function setTld(?string $tld): void
    {
        $this->tld = $tld;
    }

    public function getPriceRegistration(): ?string
    {
        return $this->priceRegistration;
    }

    public function setPriceRegistration(?string $priceRegistration): void
    {
        $this->priceRegistration = $priceRegistration;
    }

    public function getPriceRenew(): ?string
    {
        return $this->priceRenew;
    }

    public function setPriceRenew(?string $priceRenew): void
    {
        $this->priceRenew = $priceRenew;
    }

    public function getPriceTransfer(): ?string
    {
        return $this->priceTransfer;
    }

    public function setPriceTransfer(?string $priceTransfer): void
    {
        $this->priceTransfer = $priceTransfer;
    }

    public function isAllowRegister(): ?bool
    {
        return $this->allowRegister;
    }

    public function setAllowRegister(?bool $allowRegister): void
    {
        $this->allowRegister = $allowRegister;
    }

    public function isAllowTransfer(): ?bool
    {
        return $this->allowTransfer;
    }

    public function setAllowTransfer(?bool $allowTransfer): void
    {
        $this->allowTransfer = $allowTransfer;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): void
    {
        $this->active = $active;
    }

    public function getMinYears(): ?int
    {
        return $this->minYears;
    }

    public function setMinYears(?int $minYears): void
    {
        $this->minYears = $minYears;
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
