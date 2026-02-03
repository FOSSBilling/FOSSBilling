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

#[ORM\Entity(repositoryClass: \FOSSBilling\ProductType\Domain\Repository\DomainRepository::class)]
#[ORM\Table(name: 'ext_product_domain')]
class Domain implements ApiArrayInterface, TimestampInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    /** @phpstan-readonly */
    private int $id;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    private int $clientId;

    #[ORM\ManyToOne(targetEntity: TldRegistrar::class)]
    #[ORM\JoinColumn(name: 'tld_registrar_id', referencedColumnName: 'id')]
    private ?TldRegistrar $registrar = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $sld = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 100, nullable: true)]
    private ?string $tld = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $ns1 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $ns2 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $ns3 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $ns4 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: true)]
    private ?int $period = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: true)]
    private ?int $privacy = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN)]
    private bool $locked = true;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $transferCode = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 30, nullable: true)]
    private ?string $action = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $contactEmail = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $contactCompany = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $contactFirstName = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $contactLastName = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $contactAddress1 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $contactAddress2 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $contactCity = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $contactState = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $contactPostcode = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $contactCountry = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $contactPhoneCc = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $contactPhone = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $details = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $syncedAt = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $registeredAt = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $expiresAt = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $updatedAt = null;

    public function __construct(int $clientId)
    {
        $this->clientId = $clientId;
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

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->clientId,
            'sld' => $this->sld,
            'tld' => $this->tld,
            'ns1' => $this->ns1,
            'ns2' => $this->ns2,
            'ns3' => $this->ns3,
            'ns4' => $this->ns4,
            'period' => $this->period,
            'privacy' => $this->privacy,
            'locked' => $this->locked,
            'transfer_code' => $this->transferCode,
            'action' => $this->action,
            'contact_email' => $this->contactEmail,
            'contact_company' => $this->contactCompany,
            'contact_first_name' => $this->contactFirstName,
            'contact_last_name' => $this->contactLastName,
            'contact_address1' => $this->contactAddress1,
            'contact_address2' => $this->contactAddress2,
            'contact_city' => $this->contactCity,
            'contact_state' => $this->contactState,
            'contact_postcode' => $this->contactPostcode,
            'contact_country' => $this->contactCountry,
            'contact_phone_cc' => $this->contactPhoneCc,
            'contact_phone' => $this->contactPhone,
            'details' => $this->details,
            'synced_at' => $this->syncedAt?->format('Y-m-d H:i:s'),
            'registered_at' => $this->registeredAt?->format('Y-m-d H:i:s'),
            'expires_at' => $this->expiresAt?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getClientId(): int
    {
        return $this->clientId;
    }

    public function setClientId(int $clientId): self
    {
        $this->clientId = $clientId;

        return $this;
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

    public function getSld(): ?string
    {
        return $this->sld;
    }

    public function setSld(?string $sld): self
    {
        $this->sld = $sld;

        return $this;
    }

    public function getTld(): ?string
    {
        return $this->tld;
    }

    public function setTld(?string $tld): self
    {
        $this->tld = $tld;

        return $this;
    }

    public function getNs1(): ?string
    {
        return $this->ns1;
    }

    public function setNs1(?string $ns1): self
    {
        $this->ns1 = $ns1;

        return $this;
    }

    public function getNs2(): ?string
    {
        return $this->ns2;
    }

    public function setNs2(?string $ns2): self
    {
        $this->ns2 = $ns2;

        return $this;
    }

    public function getNs3(): ?string
    {
        return $this->ns3;
    }

    public function setNs3(?string $ns3): self
    {
        $this->ns3 = $ns3;

        return $this;
    }

    public function getNs4(): ?string
    {
        return $this->ns4;
    }

    public function setNs4(?string $ns4): self
    {
        $this->ns4 = $ns4;

        return $this;
    }

    public function getPeriod(): ?int
    {
        return $this->period;
    }

    public function setPeriod(?int $period): self
    {
        $this->period = $period;

        return $this;
    }

    public function getPrivacy(): ?int
    {
        return $this->privacy;
    }

    public function setPrivacy(?int $privacy): self
    {
        $this->privacy = $privacy;

        return $this;
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function setLocked(bool $locked): self
    {
        $this->locked = $locked;

        return $this;
    }

    public function getTransferCode(): ?string
    {
        return $this->transferCode;
    }

    public function setTransferCode(?string $transferCode): self
    {
        $this->transferCode = $transferCode;

        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(?string $action): self
    {
        $this->action = $action;

        return $this;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(?string $contactEmail): self
    {
        $this->contactEmail = $contactEmail;

        return $this;
    }

    public function getContactCompany(): ?string
    {
        return $this->contactCompany;
    }

    public function setContactCompany(?string $contactCompany): self
    {
        $this->contactCompany = $contactCompany;

        return $this;
    }

    public function getContactFirstName(): ?string
    {
        return $this->contactFirstName;
    }

    public function setContactFirstName(?string $contactFirstName): self
    {
        $this->contactFirstName = $contactFirstName;

        return $this;
    }

    public function getContactLastName(): ?string
    {
        return $this->contactLastName;
    }

    public function setContactLastName(?string $contactLastName): self
    {
        $this->contactLastName = $contactLastName;

        return $this;
    }

    public function getContactAddress1(): ?string
    {
        return $this->contactAddress1;
    }

    public function setContactAddress1(?string $contactAddress1): self
    {
        $this->contactAddress1 = $contactAddress1;

        return $this;
    }

    public function getContactAddress2(): ?string
    {
        return $this->contactAddress2;
    }

    public function setContactAddress2(?string $contactAddress2): self
    {
        $this->contactAddress2 = $contactAddress2;

        return $this;
    }

    public function getContactCity(): ?string
    {
        return $this->contactCity;
    }

    public function setContactCity(?string $contactCity): self
    {
        $this->contactCity = $contactCity;

        return $this;
    }

    public function getContactState(): ?string
    {
        return $this->contactState;
    }

    public function setContactState(?string $contactState): self
    {
        $this->contactState = $contactState;

        return $this;
    }

    public function getContactPostcode(): ?string
    {
        return $this->contactPostcode;
    }

    public function setContactPostcode(?string $contactPostcode): self
    {
        $this->contactPostcode = $contactPostcode;

        return $this;
    }

    public function getContactCountry(): ?string
    {
        return $this->contactCountry;
    }

    public function setContactCountry(?string $contactCountry): self
    {
        $this->contactCountry = $contactCountry;

        return $this;
    }

    public function getContactPhoneCc(): ?string
    {
        return $this->contactPhoneCc;
    }

    public function setContactPhoneCc(?string $contactPhoneCc): self
    {
        $this->contactPhoneCc = $contactPhoneCc;

        return $this;
    }

    public function getContactPhone(): ?string
    {
        return $this->contactPhone;
    }

    public function setContactPhone(?string $contactPhone): self
    {
        $this->contactPhone = $contactPhone;

        return $this;
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }

    public function setDetails(?string $details): self
    {
        $this->details = $details;

        return $this;
    }

    public function getSyncedAt(): ?\DateTime
    {
        return $this->syncedAt;
    }

    public function setSyncedAt(?\DateTime $syncedAt): self
    {
        $this->syncedAt = $syncedAt;

        return $this;
    }

    public function getRegisteredAt(): ?\DateTime
    {
        return $this->registeredAt;
    }

    public function setRegisteredAt(?\DateTime $registeredAt): self
    {
        $this->registeredAt = $registeredAt;

        return $this;
    }

    public function getExpiresAt(): ?\DateTime
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTime $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

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
