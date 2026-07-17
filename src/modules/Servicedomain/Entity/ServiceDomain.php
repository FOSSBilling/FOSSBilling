<?php

declare(strict_types=1);

namespace Box\Mod\Servicedomain\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \Box\Mod\Servicedomain\Repository\DomainRepository::class)]
#[ORM\Table(name: 'service_domain')]
#[ORM\Index(name: 'client_id_idx', columns: ['client_id'])]
#[ORM\Index(name: 'tld_registrar_id_idx', columns: ['tld_registrar_id'])]
#[ORM\HasLifecycleCallbacks]
class ServiceDomain
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'client_id', type: Types::BIGINT, nullable: true)]
    private ?int $clientId = null;

    #[ORM\Column(name: 'tld_registrar_id', type: Types::BIGINT, nullable: true)]
    private ?int $tldRegistrarId = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $sld = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $tld = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $ns1 = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $ns2 = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $ns3 = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $ns4 = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $period = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $privacy = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['default' => true])]
    private ?bool $locked = true;

    #[ORM\Column(name: 'transfer_code', type: Types::STRING, length: 255, nullable: true)]
    private ?string $transferCode = null;

    #[ORM\Column(type: Types::STRING, length: 30, nullable: true)]
    private ?string $action = null;

    #[ORM\Column(name: 'contact_email', type: Types::STRING, length: 255, nullable: true)]
    private ?string $contactEmail = null;

    #[ORM\Column(name: 'contact_company', type: Types::STRING, length: 255, nullable: true)]
    private ?string $contactCompany = null;

    #[ORM\Column(name: 'contact_first_name', type: Types::STRING, length: 255, nullable: true)]
    private ?string $contactFirstName = null;

    #[ORM\Column(name: 'contact_last_name', type: Types::STRING, length: 255, nullable: true)]
    private ?string $contactLastName = null;

    #[ORM\Column(name: 'contact_address1', type: Types::STRING, length: 255, nullable: true)]
    private ?string $contactAddress1 = null;

    #[ORM\Column(name: 'contact_address2', type: Types::STRING, length: 255, nullable: true)]
    private ?string $contactAddress2 = null;

    #[ORM\Column(name: 'contact_city', type: Types::STRING, length: 255, nullable: true)]
    private ?string $contactCity = null;

    #[ORM\Column(name: 'contact_state', type: Types::STRING, length: 255, nullable: true)]
    private ?string $contactState = null;

    #[ORM\Column(name: 'contact_postcode', type: Types::STRING, length: 255, nullable: true)]
    private ?string $contactPostcode = null;

    #[ORM\Column(name: 'contact_country', type: Types::STRING, length: 255, nullable: true)]
    private ?string $contactCountry = null;

    #[ORM\Column(name: 'contact_phone_cc', type: Types::STRING, length: 255, nullable: true)]
    private ?string $contactPhoneCc = null;

    #[ORM\Column(name: 'contact_phone', type: Types::STRING, length: 255, nullable: true)]
    private ?string $contactPhone = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $details = null;

    #[ORM\Column(name: 'synced_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $syncedAt = null;

    #[ORM\Column(name: 'registered_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $registeredAt = null;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $expiresAt = null;

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

    public function getTldRegistrarId(): ?int
    {
        return $this->tldRegistrarId;
    }

    public function setTldRegistrarId(?int $tldRegistrarId): void
    {
        $this->tldRegistrarId = $tldRegistrarId;
    }

    public function getSld(): ?string
    {
        return $this->sld;
    }

    public function setSld(?string $sld): void
    {
        $this->sld = $sld;
    }

    public function getTld(): ?string
    {
        return $this->tld;
    }

    public function setTld(?string $tld): void
    {
        $this->tld = $tld;
    }

    public function getNs1(): ?string
    {
        return $this->ns1;
    }

    public function setNs1(?string $ns1): void
    {
        $this->ns1 = $ns1;
    }

    public function getNs2(): ?string
    {
        return $this->ns2;
    }

    public function setNs2(?string $ns2): void
    {
        $this->ns2 = $ns2;
    }

    public function getNs3(): ?string
    {
        return $this->ns3;
    }

    public function setNs3(?string $ns3): void
    {
        $this->ns3 = $ns3;
    }

    public function getNs4(): ?string
    {
        return $this->ns4;
    }

    public function setNs4(?string $ns4): void
    {
        $this->ns4 = $ns4;
    }

    public function getPeriod(): ?int
    {
        return $this->period;
    }

    public function setPeriod(?int $period): void
    {
        $this->period = $period;
    }

    public function getPrivacy(): ?bool
    {
        return $this->privacy;
    }

    public function setPrivacy(?bool $privacy): void
    {
        $this->privacy = $privacy;
    }

    public function isLocked(): ?bool
    {
        return $this->locked;
    }

    public function setLocked(?bool $locked): void
    {
        $this->locked = $locked;
    }

    public function getTransferCode(): ?string
    {
        return $this->transferCode;
    }

    public function setTransferCode(?string $transferCode): void
    {
        $this->transferCode = $transferCode;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(?string $action): void
    {
        $this->action = $action;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(?string $contactEmail): void
    {
        $this->contactEmail = $contactEmail;
    }

    public function getContactCompany(): ?string
    {
        return $this->contactCompany;
    }

    public function setContactCompany(?string $contactCompany): void
    {
        $this->contactCompany = $contactCompany;
    }

    public function getContactFirstName(): ?string
    {
        return $this->contactFirstName;
    }

    public function setContactFirstName(?string $contactFirstName): void
    {
        $this->contactFirstName = $contactFirstName;
    }

    public function getContactLastName(): ?string
    {
        return $this->contactLastName;
    }

    public function setContactLastName(?string $contactLastName): void
    {
        $this->contactLastName = $contactLastName;
    }

    public function getContactAddress1(): ?string
    {
        return $this->contactAddress1;
    }

    public function setContactAddress1(?string $contactAddress1): void
    {
        $this->contactAddress1 = $contactAddress1;
    }

    public function getContactAddress2(): ?string
    {
        return $this->contactAddress2;
    }

    public function setContactAddress2(?string $contactAddress2): void
    {
        $this->contactAddress2 = $contactAddress2;
    }

    public function getContactCity(): ?string
    {
        return $this->contactCity;
    }

    public function setContactCity(?string $contactCity): void
    {
        $this->contactCity = $contactCity;
    }

    public function getContactState(): ?string
    {
        return $this->contactState;
    }

    public function setContactState(?string $contactState): void
    {
        $this->contactState = $contactState;
    }

    public function getContactPostcode(): ?string
    {
        return $this->contactPostcode;
    }

    public function setContactPostcode(?string $contactPostcode): void
    {
        $this->contactPostcode = $contactPostcode;
    }

    public function getContactCountry(): ?string
    {
        return $this->contactCountry;
    }

    public function setContactCountry(?string $contactCountry): void
    {
        $this->contactCountry = $contactCountry;
    }

    public function getContactPhoneCc(): ?string
    {
        return $this->contactPhoneCc;
    }

    public function setContactPhoneCc(?string $contactPhoneCc): void
    {
        $this->contactPhoneCc = $contactPhoneCc;
    }

    public function getContactPhone(): ?string
    {
        return $this->contactPhone;
    }

    public function setContactPhone(?string $contactPhone): void
    {
        $this->contactPhone = $contactPhone;
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }

    public function setDetails(?string $details): void
    {
        $this->details = $details;
    }

    public function getSyncedAt(): ?\DateTime
    {
        return $this->syncedAt;
    }

    public function setSyncedAt(?\DateTime $syncedAt): void
    {
        $this->syncedAt = $syncedAt;
    }

    public function getRegisteredAt(): ?\DateTime
    {
        return $this->registeredAt;
    }

    public function setRegisteredAt(?\DateTime $registeredAt): void
    {
        $this->registeredAt = $registeredAt;
    }

    public function getExpiresAt(): ?\DateTime
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTime $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
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
