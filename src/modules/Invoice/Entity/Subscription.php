<?php

declare(strict_types=1);

namespace Box\Mod\Invoice\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \Box\Mod\Invoice\Repository\SubscriptionRepository::class)]
#[ORM\Table(name: 'subscription')]
#[ORM\Index(name: 'client_id_idx', columns: ['client_id'])]
#[ORM\Index(name: 'pay_gateway_id_idx', columns: ['pay_gateway_id'])]
#[ORM\HasLifecycleCallbacks]
class Subscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'client_id', type: Types::BIGINT, nullable: true)]
    private ?int $clientId = null;

    #[ORM\Column(name: 'pay_gateway_id', type: Types::BIGINT, nullable: true)]
    private ?int $payGatewayId = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $sid = null;

    #[ORM\Column(name: 'rel_type', type: Types::STRING, length: 100, nullable: true)]
    private ?string $relType = null;

    #[ORM\Column(name: 'rel_id', type: Types::BIGINT, nullable: true)]
    private ?int $relId = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $period = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $amount = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $currency = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $status = null;

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

    public function getPayGatewayId(): ?int
    {
        return $this->payGatewayId;
    }

    public function setPayGatewayId(?int $payGatewayId): void
    {
        $this->payGatewayId = $payGatewayId;
    }

    public function getSid(): ?string
    {
        return $this->sid;
    }

    public function setSid(?string $sid): void
    {
        $this->sid = $sid;
    }

    public function getRelType(): ?string
    {
        return $this->relType;
    }

    public function setRelType(?string $relType): void
    {
        $this->relType = $relType;
    }

    public function getRelId(): ?int
    {
        return $this->relId;
    }

    public function setRelId(?int $relId): void
    {
        $this->relId = $relId;
    }

    public function getPeriod(): ?string
    {
        return $this->period;
    }

    public function setPeriod(?string $period): void
    {
        $this->period = $period;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(?float $amount): void
    {
        $this->amount = $amount;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): void
    {
        $this->currency = $currency;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
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
