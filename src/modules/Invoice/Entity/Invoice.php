<?php

declare(strict_types=1);

namespace Box\Mod\Invoice\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \Box\Mod\Invoice\Repository\InvoiceRepository::class)]
#[ORM\Table(name: 'invoice')]
#[ORM\Index(name: 'client_id_idx', columns: ['client_id'])]
#[ORM\Index(name: 'invoice_status_approved_due_at_idx', columns: ['status', 'approved', 'due_at'])]
#[ORM\HasLifecycleCallbacks]
class Invoice
{
    final public const string STATUS_PAID = 'paid';
    final public const string STATUS_UNPAID = 'unpaid';
    final public const string STATUS_REFUNDED = 'refunded';
    final public const string STATUS_CANCELED = 'canceled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'client_id', type: Types::BIGINT, nullable: true)]
    private ?int $clientId = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $serie = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $nr = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, unique: true)]
    private ?string $hash = null;

    #[ORM\Column(type: Types::STRING, length: 25, nullable: true)]
    private ?string $currency = null;

    #[ORM\Column(name: 'currency_rate', type: Types::DECIMAL, precision: 13, scale: 6, nullable: true)]
    private ?string $currencyRate = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $credit = null;

    #[ORM\Column(name: 'base_income', type: Types::FLOAT, nullable: true)]
    private ?float $baseIncome = null;

    #[ORM\Column(name: 'base_refund', type: Types::FLOAT, nullable: true)]
    private ?float $baseRefund = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $refund = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'text_1', type: Types::TEXT, nullable: true)]
    private ?string $text1 = null;

    #[ORM\Column(name: 'text_2', type: Types::TEXT, nullable: true)]
    private ?string $text2 = null;

    #[ORM\Column(type: Types::STRING, length: 50, options: ['default' => self::STATUS_UNPAID])]
    private string $status = self::STATUS_UNPAID;

    #[ORM\Column(name: 'seller_company', type: Types::STRING, length: 255, nullable: true)]
    private ?string $sellerCompany = null;

    #[ORM\Column(name: 'seller_company_vat', type: Types::STRING, length: 255, nullable: true)]
    private ?string $sellerCompanyVat = null;

    #[ORM\Column(name: 'seller_company_number', type: Types::STRING, length: 255, nullable: true)]
    private ?string $sellerCompanyNumber = null;

    #[ORM\Column(name: 'seller_address', type: Types::STRING, length: 255, nullable: true)]
    private ?string $sellerAddress = null;

    #[ORM\Column(name: 'seller_phone', type: Types::STRING, length: 255, nullable: true)]
    private ?string $sellerPhone = null;

    #[ORM\Column(name: 'seller_email', type: Types::STRING, length: 255, nullable: true)]
    private ?string $sellerEmail = null;

    #[ORM\Column(name: 'buyer_first_name', type: Types::STRING, length: 255, nullable: true)]
    private ?string $buyerFirstName = null;

    #[ORM\Column(name: 'buyer_last_name', type: Types::STRING, length: 255, nullable: true)]
    private ?string $buyerLastName = null;

    #[ORM\Column(name: 'buyer_company', type: Types::STRING, length: 255, nullable: true)]
    private ?string $buyerCompany = null;

    #[ORM\Column(name: 'buyer_company_vat', type: Types::STRING, length: 255, nullable: true)]
    private ?string $buyerCompanyVat = null;

    #[ORM\Column(name: 'buyer_company_number', type: Types::STRING, length: 255, nullable: true)]
    private ?string $buyerCompanyNumber = null;

    #[ORM\Column(name: 'buyer_address', type: Types::STRING, length: 255, nullable: true)]
    private ?string $buyerAddress = null;

    #[ORM\Column(name: 'buyer_city', type: Types::STRING, length: 255, nullable: true)]
    private ?string $buyerCity = null;

    #[ORM\Column(name: 'buyer_state', type: Types::STRING, length: 255, nullable: true)]
    private ?string $buyerState = null;

    #[ORM\Column(name: 'buyer_country', type: Types::STRING, length: 255, nullable: true)]
    private ?string $buyerCountry = null;

    #[ORM\Column(name: 'buyer_zip', type: Types::STRING, length: 255, nullable: true)]
    private ?string $buyerZip = null;

    #[ORM\Column(name: 'buyer_phone', type: Types::STRING, length: 255, nullable: true)]
    private ?string $buyerPhone = null;

    #[ORM\Column(name: 'buyer_phone_cc', type: Types::STRING, length: 255, nullable: true)]
    private ?string $buyerPhoneCc = null;

    #[ORM\Column(name: 'buyer_email', type: Types::STRING, length: 255, nullable: true)]
    private ?string $buyerEmail = null;

    #[ORM\Column(name: 'gateway_id', type: Types::INTEGER, nullable: true)]
    private ?int $gatewayId = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $approved = false;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $taxname = null;

    #[ORM\Column(type: Types::STRING, length: 35, nullable: true)]
    private ?string $taxrate = null;

    #[ORM\Column(name: 'due_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $dueAt = null;

    #[ORM\Column(name: 'reminded_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $remindedAt = null;

    #[ORM\Column(name: 'paid_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $paidAt = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $updatedAt = null;

    #[ORM\Column(name: 'hash_expires_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $hashExpiresAt = null;

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

    public function getSerie(): ?string
    {
        return $this->serie;
    }

    public function setSerie(string|int|null $serie): void
    {
        $this->serie = is_int($serie) ? (string) $serie : $serie;
    }

    public function getNr(): ?string
    {
        return $this->nr;
    }

    public function setNr(?string $nr): void
    {
        $this->nr = $nr;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(?string $hash): void
    {
        $this->hash = $hash;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): void
    {
        $this->currency = $currency;
    }

    public function getCurrencyRate(): ?string
    {
        return $this->currencyRate;
    }

    public function setCurrencyRate(string|float|null $currencyRate): void
    {
        $this->currencyRate = is_float($currencyRate) ? (string) $currencyRate : $currencyRate;
    }

    public function getCredit(): ?float
    {
        return $this->credit;
    }

    public function setCredit(?float $credit): void
    {
        $this->credit = $credit;
    }

    public function getBaseIncome(): ?float
    {
        return $this->baseIncome;
    }

    public function setBaseIncome(?float $baseIncome): void
    {
        $this->baseIncome = $baseIncome;
    }

    public function getBaseRefund(): ?float
    {
        return $this->baseRefund;
    }

    public function setBaseRefund(?float $baseRefund): void
    {
        $this->baseRefund = $baseRefund;
    }

    public function getRefund(): ?float
    {
        return $this->refund;
    }

    public function setRefund(?float $refund): void
    {
        $this->refund = $refund;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function getText1(): ?string
    {
        return $this->text1;
    }

    public function setText1(?string $text1): void
    {
        $this->text1 = $text1;
    }

    public function getText2(): ?string
    {
        return $this->text2;
    }

    public function setText2(?string $text2): void
    {
        $this->text2 = $text2;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getSellerCompany(): ?string
    {
        return $this->sellerCompany;
    }

    public function setSellerCompany(?string $sellerCompany): void
    {
        $this->sellerCompany = $sellerCompany;
    }

    public function getSellerCompanyVat(): ?string
    {
        return $this->sellerCompanyVat;
    }

    public function setSellerCompanyVat(?string $sellerCompanyVat): void
    {
        $this->sellerCompanyVat = $sellerCompanyVat;
    }

    public function getSellerCompanyNumber(): ?string
    {
        return $this->sellerCompanyNumber;
    }

    public function setSellerCompanyNumber(?string $sellerCompanyNumber): void
    {
        $this->sellerCompanyNumber = $sellerCompanyNumber;
    }

    public function getSellerAddress(): ?string
    {
        return $this->sellerAddress;
    }

    public function setSellerAddress(?string $sellerAddress): void
    {
        $this->sellerAddress = $sellerAddress;
    }

    public function getSellerPhone(): ?string
    {
        return $this->sellerPhone;
    }

    public function setSellerPhone(?string $sellerPhone): void
    {
        $this->sellerPhone = $sellerPhone;
    }

    public function getSellerEmail(): ?string
    {
        return $this->sellerEmail;
    }

    public function setSellerEmail(?string $sellerEmail): void
    {
        $this->sellerEmail = $sellerEmail;
    }

    public function getBuyerFirstName(): ?string
    {
        return $this->buyerFirstName;
    }

    public function setBuyerFirstName(?string $buyerFirstName): void
    {
        $this->buyerFirstName = $buyerFirstName;
    }

    public function getBuyerLastName(): ?string
    {
        return $this->buyerLastName;
    }

    public function setBuyerLastName(?string $buyerLastName): void
    {
        $this->buyerLastName = $buyerLastName;
    }

    public function getBuyerCompany(): ?string
    {
        return $this->buyerCompany;
    }

    public function setBuyerCompany(?string $buyerCompany): void
    {
        $this->buyerCompany = $buyerCompany;
    }

    public function getBuyerCompanyVat(): ?string
    {
        return $this->buyerCompanyVat;
    }

    public function setBuyerCompanyVat(?string $buyerCompanyVat): void
    {
        $this->buyerCompanyVat = $buyerCompanyVat;
    }

    public function getBuyerCompanyNumber(): ?string
    {
        return $this->buyerCompanyNumber;
    }

    public function setBuyerCompanyNumber(?string $buyerCompanyNumber): void
    {
        $this->buyerCompanyNumber = $buyerCompanyNumber;
    }

    public function getBuyerAddress(): ?string
    {
        return $this->buyerAddress;
    }

    public function setBuyerAddress(?string $buyerAddress): void
    {
        $this->buyerAddress = $buyerAddress;
    }

    public function getBuyerCity(): ?string
    {
        return $this->buyerCity;
    }

    public function setBuyerCity(?string $buyerCity): void
    {
        $this->buyerCity = $buyerCity;
    }

    public function getBuyerState(): ?string
    {
        return $this->buyerState;
    }

    public function setBuyerState(?string $buyerState): void
    {
        $this->buyerState = $buyerState;
    }

    public function getBuyerCountry(): ?string
    {
        return $this->buyerCountry;
    }

    public function setBuyerCountry(?string $buyerCountry): void
    {
        $this->buyerCountry = $buyerCountry;
    }

    public function getBuyerZip(): ?string
    {
        return $this->buyerZip;
    }

    public function setBuyerZip(?string $buyerZip): void
    {
        $this->buyerZip = $buyerZip;
    }

    public function getBuyerPhone(): ?string
    {
        return $this->buyerPhone;
    }

    public function setBuyerPhone(?string $buyerPhone): void
    {
        $this->buyerPhone = $buyerPhone;
    }

    public function getBuyerPhoneCc(): ?string
    {
        return $this->buyerPhoneCc;
    }

    public function setBuyerPhoneCc(?string $buyerPhoneCc): void
    {
        $this->buyerPhoneCc = $buyerPhoneCc;
    }

    public function getBuyerEmail(): ?string
    {
        return $this->buyerEmail;
    }

    public function setBuyerEmail(?string $buyerEmail): void
    {
        $this->buyerEmail = $buyerEmail;
    }

    public function getGatewayId(): ?int
    {
        return $this->gatewayId;
    }

    public function setGatewayId(?int $gatewayId): void
    {
        $this->gatewayId = $gatewayId;
    }

    public function isApproved(): bool
    {
        return $this->approved;
    }

    public function setApproved(bool $approved): void
    {
        $this->approved = $approved;
    }

    public function getTaxname(): ?string
    {
        return $this->taxname;
    }

    public function setTaxname(?string $taxname): void
    {
        $this->taxname = $taxname;
    }

    public function getTaxrate(): ?string
    {
        return $this->taxrate;
    }

    public function setTaxrate(?string $taxrate): void
    {
        $this->taxrate = $taxrate;
    }

    public function getDueAt(): ?\DateTime
    {
        return $this->dueAt;
    }

    public function setDueAt(?\DateTime $dueAt): void
    {
        $this->dueAt = $dueAt;
    }

    public function getRemindedAt(): ?\DateTime
    {
        return $this->remindedAt;
    }

    public function setRemindedAt(?\DateTime $remindedAt): void
    {
        $this->remindedAt = $remindedAt;
    }

    public function getPaidAt(): ?\DateTime
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTime $paidAt): void
    {
        $this->paidAt = $paidAt;
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

    public function getHashExpiresAt(): ?\DateTime
    {
        return $this->hashExpiresAt;
    }

    public function setHashExpiresAt(?\DateTime $hashExpiresAt): void
    {
        $this->hashExpiresAt = $hashExpiresAt;
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
