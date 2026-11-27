<?php

declare(strict_types=1);

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Massmailer\Entity;

use Doctrine\ORM\Mapping as ORM;
use FOSSBilling\Core\Api\ArrayInterface;
use FOSSBilling\Core\Doctrine\TimestampInterface;
use FOSSBilling\Core\Doctrine\TimestampTrait;

#[ORM\Entity(repositoryClass: \Box\Mod\Massmailer\Repository\MassmailerMessageRepository::class)]
#[ORM\Table(name: 'mod_massmailer')]
#[ORM\HasLifecycleCallbacks]
class MassmailerMessage implements ArrayInterface, TimestampInterface
{
    use TimestampTrait;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    /** Queued for its recipients in the background, a slice per cron run. */
    public const STATUS_SENDING = 'sending';

    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BIGINT)]
        private ?int $id = null,
    ) {
    }

    #[ORM\Column(name: 'from_email', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $fromEmail = null;

    #[ORM\Column(name: 'from_name', type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $fromName = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $subject = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::TEXT, nullable: true)]
    private ?string $content = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::JSON, nullable: true)]
    private ?array $filter = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    private ?string $status = self::STATUS_DRAFT;

    #[ORM\Column(name: 'sent_at', type: \Doctrine\DBAL\Types\Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    /** Recipients when sending started; null until the message is sent. */
    #[ORM\Column(name: 'send_total', type: \Doctrine\DBAL\Types\Types::INTEGER, nullable: true)]
    private ?int $sendTotal = null;

    #[ORM\Column(name: 'send_queued', type: \Doctrine\DBAL\Types\Types::INTEGER, options: ['default' => 0])]
    private int $sendQueued = 0;

    #[ORM\Column(name: 'send_failed', type: \Doctrine\DBAL\Types\Types::INTEGER, options: ['default' => 0])]
    private int $sendFailed = 0;

    /**
     * The last client a background send handled. Recipients are processed
     * newest client first, so the send resumes with the clients below it.
     */
    #[ORM\Column(name: 'send_last_client_id', type: \Doctrine\DBAL\Types\Types::BIGINT, nullable: true)]
    private ?int $sendLastClientId = null;

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'from_email' => $this->fromEmail,
            'from_name' => $this->fromName,
            'subject' => $this->subject,
            'content' => $this->content,
            'filter' => $this->getFilter() ?? [],
            'status' => $this->status,
            'sent_at' => $this->sentAt?->format('Y-m-d H:i:s'),
            'send_progress' => $this->sendTotal === null ? null : [
                'total' => $this->sendTotal,
                'queued' => $this->sendQueued,
                'failed' => $this->sendFailed,
            ],
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
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

    public function getFilter(): ?array
    {
        return $this->filter;
    }

    public function setFilter(?array $filter): self
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

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): self
    {
        $this->sentAt = $sentAt;

        return $this;
    }

    public function getSendTotal(): ?int
    {
        return $this->sendTotal;
    }

    public function getSendQueued(): int
    {
        return $this->sendQueued;
    }

    public function getSendFailed(): int
    {
        return $this->sendFailed;
    }

    public function getSendLastClientId(): ?int
    {
        return $this->sendLastClientId;
    }

    /**
     * Resets the progress for a send to $total recipients.
     */
    public function startSending(int $recipientCount): self
    {
        $this->status = self::STATUS_SENDING;
        $this->sendTotal = $recipientCount;
        $this->sendQueued = 0;
        $this->sendFailed = 0;
        $this->sendLastClientId = null;

        return $this;
    }

    /**
     * Records that the background send handled $clientId.
     */
    public function recordSendAttempt(int $clientId, bool $wasQueued): self
    {
        $this->sendLastClientId = $clientId;
        if ($wasQueued) {
            ++$this->sendQueued;
        } else {
            ++$this->sendFailed;
        }

        return $this;
    }
}
