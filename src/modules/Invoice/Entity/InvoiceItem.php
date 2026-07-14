<?php

declare(strict_types=1);

namespace Box\Mod\Invoice\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \Box\Mod\Invoice\Repository\InvoiceItemRepository::class)]
#[ORM\Table(name: 'invoice_item')]
#[ORM\Index(name: 'invoice_id_idx', columns: ['invoice_id'])]
#[ORM\HasLifecycleCallbacks]
class InvoiceItem
{
    final public const string TYPE_DEPOSIT = 'deposit';
    final public const string TYPE_CUSTOM = 'custom';
    final public const string TYPE_ORDER = 'order';
    final public const string TYPE_HOOK_CALL = 'hook_call';

    final public const string TASK_VOID = 'void';
    final public const string TASK_ACTIVATE = 'activate';
    final public const string TASK_RENEW = 'renew';

    final public const string STATUS_PENDING_PAYMENT = 'pending_payment';
    final public const string STATUS_PENDING_SETUP = 'pending_setup';
    final public const string STATUS_EXECUTED = 'executed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'invoice_id', type: Types::BIGINT, nullable: true)]
    private ?int $invoiceId = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(name: 'rel_id', type: Types::TEXT, nullable: true)]
    private ?string $relId = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $task = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
    private ?string $period = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?int $quantity = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $unit = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $price = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $charged = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $taxed = false;

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

    public function getInvoiceId(): ?int
    {
        return $this->invoiceId;
    }

    public function setInvoiceId(?int $invoiceId): void
    {
        $this->invoiceId = $invoiceId;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getRelId(): ?string
    {
        return $this->relId;
    }

    public function setRelId(?string $relId): void
    {
        $this->relId = $relId;
    }

    public function getTask(): ?string
    {
        return $this->task;
    }

    public function setTask(?string $task): void
    {
        $this->task = $task;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getPeriod(): ?string
    {
        return $this->period;
    }

    public function setPeriod(?string $period): void
    {
        $this->period = $period;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(?int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): void
    {
        $this->unit = $unit;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(?float $price): void
    {
        $this->price = $price;
    }

    public function isCharged(): bool
    {
        return $this->charged;
    }

    public function setCharged(bool $charged): void
    {
        $this->charged = $charged;
    }

    public function isTaxed(): bool
    {
        return $this->taxed;
    }

    public function setTaxed(bool $taxed): void
    {
        $this->taxed = $taxed;
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
