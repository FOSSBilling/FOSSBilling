<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Email\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use FOSSBilling\Interfaces\ApiArrayInterface;

#[ORM\Entity(repositoryClass: \Box\Mod\Email\Repository\EmailTemplateRepository::class)]
#[ORM\Table(name: 'email_template')]
class EmailTemplate implements ApiArrayInterface
{
    public const BUILTIN_TEMPLATE = 0;
    public const CUSTOM_TEMPLATE = 1;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'action_code', type: Types::STRING, length: 255, unique: true)]
    private string $actionCode;

    #[ORM\Column(type: Types::STRING, length: 30, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(name: 'is_custom', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isCustom = false;

    #[ORM\Column(name: 'is_overridden', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isOverridden = false;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $subject = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $vars = null;

    public function __construct(string $actionCode)
    {
        $this->actionCode = $actionCode;
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->getId(),
            'action_code' => $this->getActionCode(),
            'category' => $this->getCategory(),
            'enabled' => $this->isEnabled(),
            'subject' => $this->getSubject(),
            'description' => $this->getDescription(),
            'is_custom' => $this->isCustom(),
            'is_overridden' => $this->isOverridden(),
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getActionCode(): string
    {
        return $this->actionCode;
    }

    public function setActionCode(string $actionCode): self
    {
        $this->actionCode = $actionCode;

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function isCustom(): bool
    {
        return $this->isCustom;
    }

    public function setIsCustom(bool $isCustom): self
    {
        $this->isCustom = $isCustom;

        return $this;
    }

    public function isOverridden(): bool
    {
        return $this->isOverridden;
    }

    public function setIsOverridden(bool $isOverridden): self
    {
        $this->isOverridden = $isOverridden;

        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getVars(): ?string
    {
        return $this->vars;
    }

    public function setVars(?string $vars): self
    {
        $this->vars = $vars;

        return $this;
    }
}
