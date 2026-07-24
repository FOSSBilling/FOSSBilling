<?php

declare(strict_types=1);

namespace Box\Mod\Formbuilder\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \Box\Mod\Formbuilder\Repository\FormFieldRepository::class)]
#[ORM\Table(name: 'form_field')]
#[ORM\Index(name: 'form_id_idx', columns: ['form_id'])]
#[ORM\HasLifecycleCallbacks]
class FormField
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(name: 'form_id', type: Types::BIGINT, nullable: true)]
    private ?int $formId = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(name: 'hide_label', type: Types::BOOLEAN, nullable: true)]
    private ?bool $hideLabel = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(name: 'default_value', type: Types::STRING, length: 255, nullable: true)]
    private ?string $defaultValue = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $required = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $hidden = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $readonly = null;

    #[ORM\Column(name: 'is_unique', type: Types::BOOLEAN, nullable: true)]
    private ?bool $isUnique = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $prefix = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $suffix = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $options = null;

    #[ORM\Column(name: 'show_initial', type: Types::STRING, length: 255, nullable: true)]
    private ?string $showInitial = null;

    #[ORM\Column(name: 'show_middle', type: Types::STRING, length: 255, nullable: true)]
    private ?string $showMiddle = null;

    #[ORM\Column(name: 'show_prefix', type: Types::STRING, length: 255, nullable: true)]
    private ?string $showPrefix = null;

    #[ORM\Column(name: 'show_suffix', type: Types::STRING, length: 255, nullable: true)]
    private ?string $showSuffix = null;

    #[ORM\Column(name: 'text_size', type: Types::INTEGER, nullable: true)]
    private ?int $textSize = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFormId(): ?int
    {
        return $this->formId;
    }

    public function setFormId(?int $formId): void
    {
        $this->formId = $formId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function isHideLabel(): bool
    {
        return $this->hideLabel ?? false;
    }

    public function setHideLabel(bool $hideLabel): void
    {
        $this->hideLabel = $hideLabel;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getDefaultValue(): ?string
    {
        return $this->defaultValue;
    }

    public function setDefaultValue(?string $defaultValue): void
    {
        $this->defaultValue = $defaultValue;
    }

    public function isRequired(): bool
    {
        return $this->required ?? false;
    }

    public function setRequired(bool $required): void
    {
        $this->required = $required;
    }

    public function isHidden(): bool
    {
        return $this->hidden ?? false;
    }

    public function setHidden(bool $hidden): void
    {
        $this->hidden = $hidden;
    }

    public function isReadonly(): bool
    {
        return $this->readonly ?? false;
    }

    public function setReadonly(bool $readonly): void
    {
        $this->readonly = $readonly;
    }

    public function isUnique(): bool
    {
        return $this->isUnique ?? false;
    }

    public function setIsUnique(bool $isUnique): void
    {
        $this->isUnique = $isUnique;
    }

    public function getPrefix(): ?string
    {
        return $this->prefix;
    }

    public function setPrefix(?string $prefix): void
    {
        $this->prefix = $prefix;
    }

    public function getSuffix(): ?string
    {
        return $this->suffix;
    }

    public function setSuffix(?string $suffix): void
    {
        $this->suffix = $suffix;
    }

    public function getOptions(): ?string
    {
        return $this->options;
    }

    public function setOptions(?string $options): void
    {
        $this->options = $options;
    }

    public function getShowInitial(): ?string
    {
        return $this->showInitial;
    }

    public function setShowInitial(?string $showInitial): void
    {
        $this->showInitial = $showInitial;
    }

    public function getShowMiddle(): ?string
    {
        return $this->showMiddle;
    }

    public function setShowMiddle(?string $showMiddle): void
    {
        $this->showMiddle = $showMiddle;
    }

    public function getShowPrefix(): ?string
    {
        return $this->showPrefix;
    }

    public function setShowPrefix(?string $showPrefix): void
    {
        $this->showPrefix = $showPrefix;
    }

    public function getShowSuffix(): ?string
    {
        return $this->showSuffix;
    }

    public function setShowSuffix(?string $showSuffix): void
    {
        $this->showSuffix = $showSuffix;
    }

    public function getTextSize(): ?int
    {
        return $this->textSize;
    }

    public function setTextSize(?int $textSize): void
    {
        $this->textSize = $textSize;
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
        $this->createdAt ??= new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }
}
