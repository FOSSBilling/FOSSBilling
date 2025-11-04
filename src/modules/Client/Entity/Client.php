<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Client\Entity;

use Doctrine\ORM\Mapping as ORM;
use FOSSBilling\Interfaces\ApiArrayInterface;
use FOSSBilling\Interfaces\TimestampInterface;

/**
 * Client entity - represents a customer in the system.
 *
 * This entity mirrors the legacy 'client' table exactly to maintain
 * backward compatibility during the RedBean to Doctrine migration.
 */
#[ORM\Entity(repositoryClass: \Box\Mod\Client\Repository\ClientRepository::class)]
#[ORM\Table(name: 'client')]
#[ORM\HasLifecycleCallbacks]
class Client implements ApiArrayInterface, TimestampInterface
{
    // Status constants from Model_Client
    final public const STATUS_ACTIVE = 'active';
    final public const STATUS_SUSPENDED = 'suspended';
    final public const STATUS_CANCELED = 'canceled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    private int $id;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: true)]
    private ?int $aid = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: true)]
    private ?int $client_group_id = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, unique: true)]
    private string $email;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN, nullable: true)]
    private ?bool $email_approved = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255)]
    private string $pass;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $salt = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $auth_type = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 20)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $first_name = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $last_name = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $company = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $company_vat = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $company_number = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 10, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 10, nullable: true)]
    private ?string $gender = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $birthday = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 10, nullable: true)]
    private ?string $phone_cc = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $address_1 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $address_2 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 100, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 100, nullable: true)]
    private ?string $state = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 20, nullable: true)]
    private ?string $postcode = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 2, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 10, nullable: true)]
    private ?string $currency = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 50, nullable: true)]
    private ?string $document_type = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $document_nr = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 10, nullable: true)]
    private ?string $lang = null;

    // Custom fields (1-10)
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $custom_1 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $custom_2 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $custom_3 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $custom_4 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $custom_5 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $custom_6 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $custom_7 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $custom_8 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $custom_9 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $custom_10 = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE)]
    private \DateTime $created_at;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_MUTABLE)]
    private \DateTime $updated_at;

    /**
     * PrePersist lifecycle callback - sets created_at and updated_at.
     */
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->created_at = new \DateTime();
        $this->updated_at = new \DateTime();
    }

    /**
     * PreUpdate lifecycle callback - updates updated_at.
     */
    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updated_at = new \DateTime();
    }

    // Getters

    public function getId(): int
    {
        return $this->id;
    }

    public function getAid(): ?int
    {
        return $this->aid;
    }

    public function getClientGroupId(): ?int
    {
        return $this->client_group_id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getEmailApproved(): ?bool
    {
        return $this->email_approved;
    }

    public function getPass(): string
    {
        return $this->pass;
    }

    public function getSalt(): ?string
    {
        return $this->salt;
    }

    public function getAuthType(): ?string
    {
        return $this->auth_type;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getFirstName(): ?string
    {
        return $this->first_name;
    }

    public function getLastName(): ?string
    {
        return $this->last_name;
    }

    /**
     * Get full name (first + last).
     */
    public function getFullName(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function getCompanyVat(): ?string
    {
        return $this->company_vat;
    }

    public function getCompanyNumber(): ?string
    {
        return $this->company_number;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function getBirthday(): ?\DateTime
    {
        return $this->birthday;
    }

    public function getPhoneCc(): ?string
    {
        return $this->phone_cc;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getAddress1(): ?string
    {
        return $this->address_1;
    }

    public function getAddress2(): ?string
    {
        return $this->address_2;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function getPostcode(): ?string
    {
        return $this->postcode;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getDocumentType(): ?string
    {
        return $this->document_type;
    }

    public function getDocumentNr(): ?string
    {
        return $this->document_nr;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getLang(): ?string
    {
        return $this->lang;
    }

    public function getCustom1(): ?string
    {
        return $this->custom_1;
    }

    public function getCustom2(): ?string
    {
        return $this->custom_2;
    }

    public function getCustom3(): ?string
    {
        return $this->custom_3;
    }

    public function getCustom4(): ?string
    {
        return $this->custom_4;
    }

    public function getCustom5(): ?string
    {
        return $this->custom_5;
    }

    public function getCustom6(): ?string
    {
        return $this->custom_6;
    }

    public function getCustom7(): ?string
    {
        return $this->custom_7;
    }

    public function getCustom8(): ?string
    {
        return $this->custom_8;
    }

    public function getCustom9(): ?string
    {
        return $this->custom_9;
    }

    public function getCustom10(): ?string
    {
        return $this->custom_10;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updated_at;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->created_at = $createdAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updated_at = $updatedAt;
    }

    // Setters

    public function setAid(?int $aid): self
    {
        $this->aid = $aid;

        return $this;
    }

    public function setClientGroupId(?int $client_group_id): self
    {
        $this->client_group_id = $client_group_id;

        return $this;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function setEmailApproved(?bool $email_approved): self
    {
        $this->email_approved = $email_approved;

        return $this;
    }

    public function setPass(string $pass): self
    {
        $this->pass = $pass;

        return $this;
    }

    public function setSalt(?string $salt): self
    {
        $this->salt = $salt;

        return $this;
    }

    public function setAuthType(?string $auth_type): self
    {
        $this->auth_type = $auth_type;

        return $this;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function setFirstName(?string $first_name): self
    {
        $this->first_name = $first_name;

        return $this;
    }

    public function setLastName(?string $last_name): self
    {
        $this->last_name = $last_name;

        return $this;
    }

    public function setCompany(?string $company): self
    {
        $this->company = $company;

        return $this;
    }

    public function setCompanyVat(?string $company_vat): self
    {
        $this->company_vat = $company_vat;

        return $this;
    }

    public function setCompanyNumber(?string $company_number): self
    {
        $this->company_number = $company_number;

        return $this;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function setGender(?string $gender): self
    {
        $this->gender = $gender;

        return $this;
    }

    public function setBirthday(?\DateTime $birthday): self
    {
        $this->birthday = $birthday;

        return $this;
    }

    public function setPhoneCc(?string $phone_cc): self
    {
        $this->phone_cc = $phone_cc;

        return $this;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function setAddress1(?string $address_1): self
    {
        $this->address_1 = $address_1;

        return $this;
    }

    public function setAddress2(?string $address_2): self
    {
        $this->address_2 = $address_2;

        return $this;
    }

    public function setCity(?string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function setState(?string $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function setPostcode(?string $postcode): self
    {
        $this->postcode = $postcode;

        return $this;
    }

    public function setCountry(?string $country): self
    {
        $this->country = $country;

        return $this;
    }

    public function setCurrency(?string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function setDocumentType(?string $document_type): self
    {
        $this->document_type = $document_type;

        return $this;
    }

    public function setDocumentNr(?string $document_nr): self
    {
        $this->document_nr = $document_nr;

        return $this;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    public function setLang(?string $lang): self
    {
        $this->lang = $lang;

        return $this;
    }

    public function setCustom1(?string $custom_1): self
    {
        $this->custom_1 = $custom_1;

        return $this;
    }

    public function setCustom2(?string $custom_2): self
    {
        $this->custom_2 = $custom_2;

        return $this;
    }

    public function setCustom3(?string $custom_3): self
    {
        $this->custom_3 = $custom_3;

        return $this;
    }

    public function setCustom4(?string $custom_4): self
    {
        $this->custom_4 = $custom_4;

        return $this;
    }

    public function setCustom5(?string $custom_5): self
    {
        $this->custom_5 = $custom_5;

        return $this;
    }

    public function setCustom6(?string $custom_6): self
    {
        $this->custom_6 = $custom_6;

        return $this;
    }

    public function setCustom7(?string $custom_7): self
    {
        $this->custom_7 = $custom_7;

        return $this;
    }

    public function setCustom8(?string $custom_8): self
    {
        $this->custom_8 = $custom_8;

        return $this;
    }

    public function setCustom9(?string $custom_9): self
    {
        $this->custom_9 = $custom_9;

        return $this;
    }

    public function setCustom10(?string $custom_10): self
    {
        $this->custom_10 = $custom_10;

        return $this;
    }

    /**
     * Convert entity to API array format.
     *
     * Matches the structure from Client\Service::toApiArray()
     *
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        $data = [
            'id' => $this->id,
            'aid' => $this->aid,
            'email' => $this->email,
            'email_approved' => $this->email_approved,
            'type' => $this->type,
            'group_id' => $this->client_group_id,
            'company' => $this->company,
            'company_vat' => $this->company_vat,
            'company_number' => $this->company_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'gender' => $this->gender,
            'birthday' => $this->birthday?->format('Y-m-d'),
            'phone_cc' => $this->phone_cc,
            'phone' => $this->phone,
            'address_1' => $this->address_1,
            'address_2' => $this->address_2,
            'city' => $this->city,
            'state' => $this->state,
            'postcode' => $this->postcode,
            'country' => $this->country,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'document_nr' => $this->document_nr,
        ];

        // Add custom fields if they have values
        for ($i = 1; $i <= 10; ++$i) {
            $key = 'custom_' . $i;
            $value = $this->{'custom_' . $i};
            if (!empty($value)) {
                $data[$key] = $value;
            }
        }

        return $data;
    }
}
