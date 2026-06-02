<?php

declare(strict_types=1);

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Massmailer\Entity;

use Doctrine\ORM\Mapping as ORM;
use FOSSBilling\Interfaces\ApiArrayInterface;

#[ORM\Entity(repositoryClass: \Box\Mod\Massmailer\Repository\MassmailerMessageRepository::class)]
#[ORM\Table(name: 'mod_massmailer')]
class MassmailerMessage implements ApiArrayInterface
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'from_email', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $fromEmail = null;

    #[ORM\Column(name: 'from_name', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $fromName = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $subject = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $content = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $filter = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $status = self::STATUS_DRAFT;

    #[ORM\Column(name: 'sent_at', type: \Doctrine\DBAL\Types\Types::STRING, length: 35, nullable: true)]
    private ?string $sentAt = null;

    #[ORM\Column(name: 'created_at', type: \Doctrine\DBAL\Types\Types::STRING, length: 35, nullable: true)]
    private ?string $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: \Doctrine\DBAL\Types\Types::STRING, length: 35, nullable: true)]
    private ?string $updatedAt = null;

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'from_email' => $this->fromEmail,
            'from_name' => $this->fromName,
            'subject' => $this->subject,
            'content' => $this->content,
            'filter' => json_decode($this->filter ?? '', true) ?? [],
            'status' => $this->status,
            'sent_at' => $this->sentAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFromEmail(): ?string
    {
        return $this->fromEmail;
    }

    public function setFromEmail(?string $fromEmail): self
    {
        $this->fromEmail = $fromEmail;

        return $this;
    }

    public function getFromName(): ?string
    {
        return $this->fromName;
    }

    public function setFromName(?string $fromName): self
    {
        $this->fromName = $fromName;

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

    public function getFilter(): ?string
    {
        return $this->filter;
    }

    public function setFilter(?string $filter): self
    {
        $this->filter = $filter;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getSentAt(): ?string
    {
        return $this->sentAt;
    }

    public function setSentAt(?string $sentAt): self
    {
        $this->sentAt = $sentAt;

        return $this;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?string $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?string $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
