<?php

declare(strict_types=1);

namespace Box\Mod\Client\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use FOSSBilling\Interfaces\ApiArrayInterface;

#[ORM\Entity(repositoryClass: \Box\Mod\Client\Repository\ClientRepository::class)]
#[ORM\Table(name: 'client')]
#[ORM\Index(name: 'alternative_id_idx', columns: ['aid'])]
#[ORM\Index(name: 'client_group_id_idx', columns: ['client_group_id'])]
#[ORM\HasLifecycleCallbacks]
class Client implements ApiArrayInterface
{
    final public const string ACTIVE = 'active';
    final public const string SUSPENDED = 'suspended';
    final public const string CANCELED = 'canceled';
    final public const string GENDER_MALE = 'male';
    final public const string GENDER_FEMALE = 'female';
    final public const string GENDER_NON_BINARY = 'nonbinary';
    final public const string GENDER_OTHER = 'other';
    public const array ALLOWED_GENDERS = [
        self::GENDER_MALE,
        self::GENDER_FEMALE,
        self::GENDER_NON_BINARY,
        self::GENDER_OTHER,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $aid = null;

    #[ORM\Column(name: 'client_group_id', type: Types::INTEGER, nullable: true)]
    private ?int $clientGroupId = null;

    #[ORM\Column(type: Types::STRING, length: 30, options: ['default' => 'client'])]
    private string $role = 'client';

    #[ORM\Column(name: 'auth_type', type: Types::STRING, length: 255, nullable: true)]
    private ?string $authType = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, unique: true)]
    private ?string $email = null;

    #[ORM\Column(name: 'pass', type: Types::STRING, length: 255, nullable: true)]
    private ?string $pass = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $salt = null;

    #[ORM\Column(type: Types::STRING, length: 30, options: ['default' => self::ACTIVE])]
    private string $status = self::ACTIVE;

    #[ORM\Column(name: 'email_approved', type: Types::BOOLEAN, nullable: true)]
    private ?bool $emailApproved = null;

    #[ORM\Column(name: 'tax_exempt', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $taxExempt = false;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(name: 'first_name', type: Types::STRING, length: 100, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(name: 'last_name', type: Types::STRING, length: 100, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
    private ?string $gender = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $birthday = null;

    #[ORM\Column(name: 'phone_cc', type: Types::STRING, length: 10, nullable: true)]
    private ?string $phoneCc = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $company = null;

    #[ORM\Column(name: 'company_vat', type: Types::STRING, length: 100, nullable: true)]
    private ?string $companyVat = null;

    #[ORM\Column(name: 'company_number', type: Types::STRING, length: 255, nullable: true)]
    private ?string $companyNumber = null;

    #[ORM\Column(name: 'address_1', type: Types::STRING, length: 100, nullable: true)]
    private ?string $address1 = null;

    #[ORM\Column(name: 'address_2', type: Types::STRING, length: 100, nullable: true)]
    private ?string $address2 = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $state = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $postcode = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
    private ?string $currency = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
    private ?string $lang = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $timezone = null;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $ip = null;

    #[ORM\Column(name: 'api_token', type: Types::STRING, length: 128, nullable: true)]
    private ?string $apiToken = null;

    #[ORM\Column(name: 'referred_by', type: Types::STRING, length: 255, nullable: true)]
    private ?string $referredBy = null;

    #[ORM\Column(name: 'billing_email', type: Types::STRING, length: 255, nullable: true)]
    private ?string $billingEmail = null;

    #[ORM\Column(name: 'custom_1', type: Types::TEXT, nullable: true)]
    private ?string $custom1 = null;

    #[ORM\Column(name: 'custom_2', type: Types::TEXT, nullable: true)]
    private ?string $custom2 = null;

    #[ORM\Column(name: 'custom_3', type: Types::TEXT, nullable: true)]
    private ?string $custom3 = null;

    #[ORM\Column(name: 'custom_4', type: Types::TEXT, nullable: true)]
    private ?string $custom4 = null;

    #[ORM\Column(name: 'custom_5', type: Types::TEXT, nullable: true)]
    private ?string $custom5 = null;

    #[ORM\Column(name: 'custom_6', type: Types::TEXT, nullable: true)]
    private ?string $custom6 = null;

    #[ORM\Column(name: 'custom_7', type: Types::TEXT, nullable: true)]
    private ?string $custom7 = null;

    #[ORM\Column(name: 'custom_8', type: Types::TEXT, nullable: true)]
    private ?string $custom8 = null;

    #[ORM\Column(name: 'custom_9', type: Types::TEXT, nullable: true)]
    private ?string $custom9 = null;

    #[ORM\Column(name: 'custom_10', type: Types::TEXT, nullable: true)]
    private ?string $custom10 = null;

    #[ORM\Column(name: 'custom_11', type: Types::TEXT, nullable: true)]
    private ?string $custom11 = null;

    #[ORM\Column(name: 'custom_12', type: Types::TEXT, nullable: true)]
    private ?string $custom12 = null;

    #[ORM\Column(name: 'custom_13', type: Types::TEXT, nullable: true)]
    private ?string $custom13 = null;

    #[ORM\Column(name: 'custom_14', type: Types::TEXT, nullable: true)]
    private ?string $custom14 = null;

    #[ORM\Column(name: 'custom_15', type: Types::TEXT, nullable: true)]
    private ?string $custom15 = null;

    #[ORM\Column(name: 'custom_16', type: Types::TEXT, nullable: true)]
    private ?string $custom16 = null;

    #[ORM\Column(name: 'custom_17', type: Types::TEXT, nullable: true)]
    private ?string $custom17 = null;

    #[ORM\Column(name: 'custom_18', type: Types::TEXT, nullable: true)]
    private ?string $custom18 = null;

    #[ORM\Column(name: 'custom_19', type: Types::TEXT, nullable: true)]
    private ?string $custom19 = null;

    #[ORM\Column(name: 'custom_20', type: Types::TEXT, nullable: true)]
    private ?string $custom20 = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAid(): ?string
    {
        return $this->aid;
    }

    public function setAid(?string $aid): self
    {
        $this->aid = $aid;

        return $this;
    }

    public function getClientGroupId(): ?int
    {
        return $this->clientGroupId;
    }

    public function setClientGroupId(?int $clientGroupId): self
    {
        $this->clientGroupId = $clientGroupId;

        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getAuthType(): ?string
    {
        return $this->authType;
    }

    public function setAuthType(?string $authType): self
    {
        $this->authType = $authType;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getPass(): ?string
    {
        return $this->pass;
    }

    public function setPass(?string $pass): self
    {
        $this->pass = $pass;

        return $this;
    }

    public function getSalt(): ?string
    {
        return $this->salt;
    }

    public function setSalt(?string $salt): self
    {
        $this->salt = $salt;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    public function getEmailApproved(): ?bool
    {
        return $this->emailApproved;
    }

    public function setEmailApproved(?bool $emailApproved): self
    {
        $this->emailApproved = $emailApproved;

        return $this;
    }

    public function isTaxExempt(): bool
    {
        return $this->taxExempt;
    }

    public function setTaxExempt(bool $taxExempt): self
    {
        $this->taxExempt = $taxExempt;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFullName(): string
    {
        return trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function setGender(?string $gender): self
    {
        $this->gender = $gender;

        return $this;
    }

    public function getBirthday(): ?\DateTime
    {
        return $this->birthday;
    }

    public function setBirthday(?\DateTime $birthday): self
    {
        $this->birthday = $birthday;

        return $this;
    }

    public function getPhoneCc(): ?string
    {
        return $this->phoneCc;
    }

    public function setPhoneCc(?string $phoneCc): self
    {
        $this->phoneCc = $phoneCc;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(?string $company): self
    {
        $this->company = $company;

        return $this;
    }

    public function getCompanyVat(): ?string
    {
        return $this->companyVat;
    }

    public function setCompanyVat(?string $companyVat): self
    {
        $this->companyVat = $companyVat;

        return $this;
    }

    public function getCompanyNumber(): ?string
    {
        return $this->companyNumber;
    }

    public function setCompanyNumber(?string $companyNumber): self
    {
        $this->companyNumber = $companyNumber;

        return $this;
    }

    public function getAddress1(): ?string
    {
        return $this->address1;
    }

    public function setAddress1(?string $address1): self
    {
        $this->address1 = $address1;

        return $this;
    }

    public function getAddress2(): ?string
    {
        return $this->address2;
    }

    public function setAddress2(?string $address2): self
    {
        $this->address2 = $address2;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(?string $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function getPostcode(): ?string
    {
        return $this->postcode;
    }

    public function setPostcode(?string $postcode): self
    {
        $this->postcode = $postcode;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): self
    {
        $this->country = $country;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function getLang(): ?string
    {
        return $this->lang;
    }

    public function setLang(?string $lang): self
    {
        $this->lang = $lang;

        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): self
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(?string $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    public function getApiToken(): ?string
    {
        return $this->apiToken;
    }

    public function setApiToken(?string $apiToken): self
    {
        $this->apiToken = $apiToken;

        return $this;
    }

    public function getReferredBy(): ?string
    {
        return $this->referredBy;
    }

    public function setReferredBy(?string $referredBy): self
    {
        $this->referredBy = $referredBy;

        return $this;
    }

    public function getBillingEmail(): ?string
    {
        return $this->billingEmail;
    }

    public function setBillingEmail(?string $billingEmail): self
    {
        $this->billingEmail = $billingEmail;

        return $this;
    }

    public function getCustom1(): ?string
    {
        return $this->custom1;
    }

    public function setCustom1(?string $v): self
    {
        $this->custom1 = $v;

        return $this;
    }

    public function getCustom2(): ?string
    {
        return $this->custom2;
    }

    public function setCustom2(?string $v): self
    {
        $this->custom2 = $v;

        return $this;
    }

    public function getCustom3(): ?string
    {
        return $this->custom3;
    }

    public function setCustom3(?string $v): self
    {
        $this->custom3 = $v;

        return $this;
    }

    public function getCustom4(): ?string
    {
        return $this->custom4;
    }

    public function setCustom4(?string $v): self
    {
        $this->custom4 = $v;

        return $this;
    }

    public function getCustom5(): ?string
    {
        return $this->custom5;
    }

    public function setCustom5(?string $v): self
    {
        $this->custom5 = $v;

        return $this;
    }

    public function getCustom6(): ?string
    {
        return $this->custom6;
    }

    public function setCustom6(?string $v): self
    {
        $this->custom6 = $v;

        return $this;
    }

    public function getCustom7(): ?string
    {
        return $this->custom7;
    }

    public function setCustom7(?string $v): self
    {
        $this->custom7 = $v;

        return $this;
    }

    public function getCustom8(): ?string
    {
        return $this->custom8;
    }

    public function setCustom8(?string $v): self
    {
        $this->custom8 = $v;

        return $this;
    }

    public function getCustom9(): ?string
    {
        return $this->custom9;
    }

    public function setCustom9(?string $v): self
    {
        $this->custom9 = $v;

        return $this;
    }

    public function getCustom10(): ?string
    {
        return $this->custom10;
    }

    public function setCustom10(?string $v): self
    {
        $this->custom10 = $v;

        return $this;
    }

    public function getCustom11(): ?string
    {
        return $this->custom11;
    }

    public function setCustom11(?string $v): self
    {
        $this->custom11 = $v;

        return $this;
    }

    public function getCustom12(): ?string
    {
        return $this->custom12;
    }

    public function setCustom12(?string $v): self
    {
        $this->custom12 = $v;

        return $this;
    }

    public function getCustom13(): ?string
    {
        return $this->custom13;
    }

    public function setCustom13(?string $v): self
    {
        $this->custom13 = $v;

        return $this;
    }

    public function getCustom14(): ?string
    {
        return $this->custom14;
    }

    public function setCustom14(?string $v): self
    {
        $this->custom14 = $v;

        return $this;
    }

    public function getCustom15(): ?string
    {
        return $this->custom15;
    }

    public function setCustom15(?string $v): self
    {
        $this->custom15 = $v;

        return $this;
    }

    public function getCustom16(): ?string
    {
        return $this->custom16;
    }

    public function setCustom16(?string $v): self
    {
        $this->custom16 = $v;

        return $this;
    }

    public function getCustom17(): ?string
    {
        return $this->custom17;
    }

    public function setCustom17(?string $v): self
    {
        $this->custom17 = $v;

        return $this;
    }

    public function getCustom18(): ?string
    {
        return $this->custom18;
    }

    public function setCustom18(?string $v): self
    {
        $this->custom18 = $v;

        return $this;
    }

    public function getCustom19(): ?string
    {
        return $this->custom19;
    }

    public function setCustom19(?string $v): self
    {
        $this->custom19 = $v;

        return $this;
    }

    public function getCustom20(): ?string
    {
        return $this->custom20;
    }

    public function setCustom20(?string $v): self
    {
        $this->custom20 = $v;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function toApiArray(\Box\Mod\Staff\Entity\Admin|Client|\FOSSBilling\Identity\Guest|null $identity = null): array
    {
        return [
            'id' => $this->id,
            'aid' => $this->aid,
            'client_group_id' => $this->clientGroupId,
            'role' => $this->role,
            'auth_type' => $this->authType,
            'email' => $this->email,
            'status' => $this->status,
            'email_approved' => $this->emailApproved,
            'tax_exempt' => $this->taxExempt,
            'type' => $this->type,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'gender' => $this->gender,
            'birthday' => $this->birthday?->format('Y-m-d'),
            'phone_cc' => $this->phoneCc,
            'phone' => $this->phone,
            'company' => $this->company,
            'company_vat' => $this->companyVat,
            'company_number' => $this->companyNumber,
            'address_1' => $this->address1,
            'address_2' => $this->address2,
            'city' => $this->city,
            'state' => $this->state,
            'postcode' => $this->postcode,
            'country' => $this->country,
            'notes' => $this->notes,
            'currency' => $this->currency,
            'lang' => $this->lang,
            'timezone' => $this->timezone,
            'ip' => $this->ip,
            'referred_by' => $this->referredBy,
            'custom_1' => $this->custom1,
            'custom_2' => $this->custom2,
            'custom_3' => $this->custom3,
            'custom_4' => $this->custom4,
            'custom_5' => $this->custom5,
            'custom_6' => $this->custom6,
            'custom_7' => $this->custom7,
            'custom_8' => $this->custom8,
            'custom_9' => $this->custom9,
            'custom_10' => $this->custom10,
            'custom_11' => $this->custom11,
            'custom_12' => $this->custom12,
            'custom_13' => $this->custom13,
            'custom_14' => $this->custom14,
            'custom_15' => $this->custom15,
            'custom_16' => $this->custom16,
            'custom_17' => $this->custom17,
            'custom_18' => $this->custom18,
            'custom_19' => $this->custom19,
            'custom_20' => $this->custom20,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
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
