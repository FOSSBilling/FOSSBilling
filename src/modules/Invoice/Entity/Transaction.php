<?php

declare(strict_types=1);

namespace Box\Mod\Invoice\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \Box\Mod\Invoice\Repository\TransactionRepository::class)]
#[ORM\Table(name: 'transaction')]
#[ORM\Index(name: 'invoice_id_idx', columns: ['invoice_id'])]
#[ORM\Index(name: 'transaction_ipn_hash_idx', columns: ['gateway_id', 'ipn_hash'])]
#[ORM\HasLifecycleCallbacks]
class Transaction
{
    final public const string STATUS_RECEIVED = 'received';
    final public const string STATUS_APPROVED = 'approved';
    final public const string STATUS_PROCESSING = 'processing';
    final public const string STATUS_PROCESSED = 'processed';
    final public const string STATUS_ERROR = 'error';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'invoice_id', type: Types::BIGINT, nullable: true)]
    private ?int $invoiceId = null;

    #[ORM\Column(name: 'gateway_id', type: Types::INTEGER, nullable: true)]
    private ?int $gatewayId = null;

    #[ORM\Column(name: 'txn_id', type: Types::STRING, length: 255, nullable: true)]
    private ?string $txnId = null;

    #[ORM\Column(name: 'txn_status', type: Types::STRING, length: 255, nullable: true)]
    private ?string $txnStatus = null;

    #[ORM\Column(name: 's_id', type: Types::STRING, length: 255, nullable: true)]
    private ?string $sId = null;

    #[ORM\Column(name: 's_period', type: Types::STRING, length: 255, nullable: true)]
    private ?string $sPeriod = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $amount = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
    private ?string $currency = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(type: Types::STRING, length: 255, options: ['default' => self::STATUS_RECEIVED])]
    private string $status = self::STATUS_RECEIVED;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $error = null;

    #[ORM\Column(name: 'error_code', type: Types::INTEGER, nullable: true)]
    private ?int $errorCode = null;

    #[ORM\Column(name: 'validate_ipn', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $validateIpn = true;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $ipn = null;

    #[ORM\Column(name: 'ipn_hash', type: Types::STRING, length: 64, nullable: true)]
    private ?string $ipnHash = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $output = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

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

    public function getGatewayId(): ?int
    {
        return $this->gatewayId;
    }

    public function setGatewayId(?int $gatewayId): void
    {
        $this->gatewayId = $gatewayId;
    }

    public function getTxnId(): ?string
    {
        return $this->txnId;
    }

    public function setTxnId(?string $txnId): void
    {
        $this->txnId = $txnId;
    }

    public function getTxnStatus(): ?string
    {
        return $this->txnStatus;
    }

    public function setTxnStatus(?string $txnStatus): void
    {
        $this->txnStatus = $txnStatus;
    }

    public function getSId(): ?string
    {
        return $this->sId;
    }

    public function setSId(?string $sId): void
    {
        $this->sId = $sId;
    }

    public function getSPeriod(): ?string
    {
        return $this->sPeriod;
    }

    public function setSPeriod(?string $sPeriod): void
    {
        $this->sPeriod = $sPeriod;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(?string $amount): void
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): void
    {
        $this->ip = $ip;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): void
    {
        $this->error = $error;
    }

    public function getErrorCode(): ?int
    {
        return $this->errorCode;
    }

    public function setErrorCode(?int $errorCode): void
    {
        $this->errorCode = $errorCode;
    }

    public function isValidateIpn(): bool
    {
        return $this->validateIpn;
    }

    public function setValidateIpn(bool $validateIpn): void
    {
        $this->validateIpn = $validateIpn;
    }

    public function getIpn(): ?string
    {
        return $this->ipn;
    }

    public function setIpn(?string $ipn): void
    {
        $this->ipn = $ipn;
    }

    public function getIpnHash(): ?string
    {
        return $this->ipnHash;
    }

    public function setIpnHash(?string $ipnHash): void
    {
        $this->ipnHash = $ipnHash;
    }

    public function getOutput(): ?string
    {
        return $this->output;
    }

    public function setOutput(?string $output): void
    {
        $this->output = $output;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $this->note = $note;
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
