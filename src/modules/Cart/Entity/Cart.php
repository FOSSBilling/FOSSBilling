<?php

declare(strict_types=1);

namespace Box\Mod\Cart\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \Box\Mod\Cart\Repository\CartRepository::class)]
#[ORM\Table(name: 'cart')]
#[ORM\UniqueConstraint(name: 'session_id_idx', columns: ['session_id'])]
#[ORM\Index(name: 'currency_id_idx', columns: ['currency_id'])]
#[ORM\Index(name: 'promo_id_idx', columns: ['promo_id'])]
#[ORM\HasLifecycleCallbacks]
class Cart
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'session_id', type: Types::STRING, length: 32, nullable: true)]
    private ?string $sessionId = null;

    #[ORM\Column(name: 'currency_id', type: Types::BIGINT, nullable: true)]
    private ?int $currencyId = null;

    #[ORM\Column(name: 'promo_id', type: Types::BIGINT, nullable: true)]
    private ?int $promoId = null;

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

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(?string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    public function getCurrencyId(): ?int
    {
        return $this->currencyId;
    }

    public function setCurrencyId(?int $currencyId): void
    {
        $this->currencyId = $currencyId;
    }

    public function getPromoId(): ?int
    {
        return $this->promoId;
    }

    public function setPromoId(?int $promoId): void
    {
        $this->promoId = $promoId;
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
        $now = new \DateTime();
        $this->createdAt ??= $now;
        $this->updatedAt ??= $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
