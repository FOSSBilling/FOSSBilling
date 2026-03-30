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

#[ORM\Entity(repositoryClass: \Box\Mod\Email\Repository\EmailQueueRepository::class)]
#[ORM\Table(name: 'email_queue')]
#[ORM\HasLifecycleCallbacks]
class EmailQueue implements ApiArrayInterface
{
    public const STATUS_UNSENT = 'unsent';
    public const STATUS_SENDING = 'sending';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $recipient;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $sender;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $subject;

    #[ORM\Column(type: Types::TEXT)]
    private string $content;

    #[ORM\Column(name: 'to_name', type: Types::STRING, length: 255, nullable: true)]
    private ?string $toName = null;

    #[ORM\Column(name: 'from_name', type: Types::STRING, length: 255, nullable: true)]
    private ?string $fromName = null;

    #[ORM\Column(name: 'client_id', type: Types::INTEGER, nullable: true)]
    private ?int $clientId = null;

    #[ORM\Column(name: 'admin_id', type: Types::INTEGER, nullable: true)]
    private ?int $adminId = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $priority = 1;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $tries = 0;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $status = self::STATUS_UNSENT;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $updatedAt = null;

    public function __construct(string $recipient, string $sender, string $subject, string $content)
    {
        $this->recipient = $recipient;
        $this->sender = $sender;
        $this->subject = $subject;
        $this->content = $content;
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->getId(),
            'recipient' => $this->getRecipient(),
            'sender' => $this->getSender(),
            'subject' => $this->getSubject(),
            'content' => $this->getContent(),
            'to_name' => $this->getToName(),
            'from_name' => $this->getFromName(),
            'client_id' => $this->getClientId(),
            'admin_id' => $this->getAdminId(),
            'priority' => $this->getPriority(),
            'tries' => $this->getTries(),
            'status' => $this->getStatus(),
            'created_at' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $this->getUpdatedAt()?->format('Y-m-d H:i:s'),
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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecipient(): string
    {
        return $this->recipient;
    }

    public function setRecipient(string $recipient): self
    {
        $this->recipient = $recipient;

        return $this;
    }

    public function getSender(): string
    {
        return $this->sender;
    }

    public function setSender(string $sender): self
    {
        $this->sender = $sender;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getToName(): ?string
    {
        return $this->toName;
    }

    public function setToName(?string $toName): self
    {
        $this->toName = $toName;

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

    public function getClientId(): ?int
    {
        return $this->clientId;
    }

    public function setClientId(?int $clientId): self
    {
        $this->clientId = $clientId;

        return $this;
    }

    public function getAdminId(): ?int
    {
        return $this->adminId;
    }

    public function setAdminId(?int $adminId): self
    {
        $this->adminId = $adminId;

        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function decrementPriority(): self
    {
        if ($this->priority > 0) {
            --$this->priority;
        }

        return $this;
    }

    public function getTries(): int
    {
        return $this->tries;
    }

    public function incrementTries(): self
    {
        ++$this->tries;

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

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
