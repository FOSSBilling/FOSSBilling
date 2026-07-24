<?php

declare(strict_types=1);

namespace Box\Mod\Order\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \Box\Mod\Order\Repository\OrderRepository::class)]
#[ORM\Table(name: 'client_order')]
#[ORM\Index(name: 'client_id_idx', columns: ['client_id'])]
#[ORM\Index(name: 'product_id_idx', columns: ['product_id'])]
#[ORM\Index(name: 'form_id_idx', columns: ['form_id'])]
#[ORM\Index(name: 'promo_id_idx', columns: ['promo_id'])]
#[ORM\HasLifecycleCallbacks]
class Order
{
    final public const string STATUS_PENDING_SETUP = 'pending_setup';
    final public const string STATUS_FAILED_SETUP = 'failed_setup';
    final public const string STATUS_FAILED_RENEW = 'failed_renew';
    final public const string STATUS_ACTIVE = 'active';
    final public const string STATUS_CANCELED = 'canceled';
    final public const string STATUS_SUSPENDED = 'suspended';

    final public const string ACTION_CREATE = 'create';
    final public const string ACTION_ACTIVATE = 'activate';
    final public const string ACTION_RENEW = 'renew';
    final public const string ACTION_SUSPEND = 'suspend';
    final public const string ACTION_UNSUSPEND = 'unsuspend';
    final public const string ACTION_CANCEL = 'cancel';
    final public const string ACTION_UNCANCEL = 'uncancel';
    final public const string ACTION_DELETE = 'delete';

    public static function getValidStatuses(): array
    {
        return [
            self::STATUS_PENDING_SETUP,
            self::STATUS_FAILED_SETUP,
            self::STATUS_FAILED_RENEW,
            self::STATUS_ACTIVE,
            self::STATUS_CANCELED,
            self::STATUS_SUSPENDED,
        ];
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'client_id', type: Types::BIGINT, nullable: true)]
    private ?int $clientId = null;

    #[ORM\Column(name: 'product_id', type: Types::BIGINT, nullable: true)]
    private ?int $productId = null;

    #[ORM\Column(name: 'form_id', type: Types::BIGINT, nullable: true)]
    private ?int $formId = null;

    #[ORM\Column(name: 'promo_id', type: Types::BIGINT, nullable: true)]
    private ?int $promoId = null;

    #[ORM\Column(name: 'promo_recurring', type: Types::BOOLEAN, nullable: true)]
    private ?bool $promoRecurring = null;

    #[ORM\Column(name: 'promo_used', type: Types::BIGINT, nullable: true)]
    private ?int $promoUsed = null;

    #[ORM\Column(name: 'group_id', type: Types::STRING, length: 255, nullable: true)]
    private ?string $groupId = null;

    #[ORM\Column(name: 'group_master', type: Types::BOOLEAN, nullable: true, options: ['default' => false])]
    private ?bool $groupMaster = false;

    #[ORM\Column(name: 'invoice_option', type: Types::STRING, length: 255, nullable: true)]
    private ?string $invoiceOption = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    private ?string $currency = null;

    #[ORM\Column(name: 'unpaid_invoice_id', type: Types::BIGINT, nullable: true)]
    private ?int $unpaidInvoiceId = null;

    #[ORM\Column(name: 'service_id', type: Types::BIGINT, nullable: true)]
    private ?int $serviceId = null;

    #[ORM\Column(name: 'service_type', type: Types::STRING, length: 100, nullable: true)]
    private ?string $serviceType = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    private ?string $period = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true, options: ['default' => 1])]
    private ?int $quantity = 1;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $unit = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $price = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $discount = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $config = null;

    #[ORM\Column(name: 'referred_by', type: Types::STRING, length: 255, nullable: true)]
    private ?string $referredBy = null;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $expiresAt = null;

    #[ORM\Column(name: 'activated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $activatedAt = null;

    #[ORM\Column(name: 'suspended_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $suspendedAt = null;

    #[ORM\Column(name: 'unsuspended_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $unsuspendedAt = null;

    #[ORM\Column(name: 'canceled_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $canceledAt = null;

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

    public function getProductId(): ?int
    {
        return $this->productId;
    }

    public function setProductId(?int $productId): void
    {
        $this->productId = $productId;
    }

    public function getFormId(): ?int
    {
        return $this->formId;
    }

    public function setFormId(?int $formId): void
    {
        $this->formId = $formId;
    }

    public function getPromoId(): ?int
    {
        return $this->promoId;
    }

    public function setPromoId(?int $promoId): void
    {
        $this->promoId = $promoId;
    }

    public function isPromoRecurring(): ?bool
    {
        return $this->promoRecurring;
    }

    public function setPromoRecurring(?bool $promoRecurring): void
    {
        $this->promoRecurring = $promoRecurring;
    }

    public function getPromoUsed(): ?int
    {
        return $this->promoUsed;
    }

    public function setPromoUsed(?int $promoUsed): void
    {
        $this->promoUsed = $promoUsed;
    }

    public function getGroupId(): ?string
    {
        return $this->groupId;
    }

    public function setGroupId(?string $groupId): void
    {
        $this->groupId = $groupId;
    }

    public function isGroupMaster(): bool
    {
        return (bool) $this->groupMaster;
    }

    public function setGroupMaster(?bool $groupMaster): void
    {
        $this->groupMaster = $groupMaster;
    }

    public function getInvoiceOption(): ?string
    {
        return $this->invoiceOption;
    }

    public function setInvoiceOption(?string $invoiceOption): void
    {
        $this->invoiceOption = $invoiceOption;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): void
    {
        $this->currency = $currency;
    }

    public function getUnpaidInvoiceId(): ?int
    {
        return $this->unpaidInvoiceId;
    }

    public function setUnpaidInvoiceId(?int $unpaidInvoiceId): void
    {
        $this->unpaidInvoiceId = $unpaidInvoiceId;
    }

    public function getServiceId(): ?int
    {
        return $this->serviceId;
    }

    public function setServiceId(?int $serviceId): void
    {
        $this->serviceId = $serviceId;
    }

    public function getServiceType(): ?string
    {
        return $this->serviceType;
    }

    public function setServiceType(?string $serviceType): void
    {
        $this->serviceType = $serviceType;
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

    public function getDiscount(): ?float
    {
        return $this->discount;
    }

    public function setDiscount(?float $discount): void
    {
        $this->discount = $discount;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): void
    {
        $this->reason = $reason;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function getConfig(): ?string
    {
        return $this->config;
    }

    public function setConfig(?string $config): void
    {
        $this->config = $config;
    }

    public function getReferredBy(): ?string
    {
        return $this->referredBy;
    }

    public function setReferredBy(?string $referredBy): void
    {
        $this->referredBy = $referredBy;
    }

    public function getExpiresAt(): ?\DateTime
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTime $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function getActivatedAt(): ?\DateTime
    {
        return $this->activatedAt;
    }

    public function setActivatedAt(?\DateTime $activatedAt): void
    {
        $this->activatedAt = $activatedAt;
    }

    public function getSuspendedAt(): ?\DateTime
    {
        return $this->suspendedAt;
    }

    public function setSuspendedAt(?\DateTime $suspendedAt): void
    {
        $this->suspendedAt = $suspendedAt;
    }

    public function getUnsuspendedAt(): ?\DateTime
    {
        return $this->unsuspendedAt;
    }

    public function setUnsuspendedAt(?\DateTime $unsuspendedAt): void
    {
        $this->unsuspendedAt = $unsuspendedAt;
    }

    public function getCanceledAt(): ?\DateTime
    {
        return $this->canceledAt;
    }

    public function setCanceledAt(?\DateTime $canceledAt): void
    {
        $this->canceledAt = $canceledAt;
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
