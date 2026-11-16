<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Support\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A client's 1-5 star rating of a staff reply (see Box\Mod\Support\TicketFeedback).
 * One rating per message.
 */
#[ORM\Entity(repositoryClass: \Box\Mod\Support\Repository\SupportTicketMessageRatingRepository::class)]
#[ORM\Table(name: 'support_ticket_message_rating')]
class SupportTicketMessageRating
{
    #[ORM\Id]
    #[ORM\Column(name: 'message_id', type: Types::BIGINT)]
    private ?int $messageId = null;

    #[ORM\Column(name: 'ticket_id', type: Types::BIGINT)]
    private ?int $ticketId = null;

    #[ORM\Column(name: 'admin_id', type: Types::BIGINT)]
    private ?int $adminId = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $rating = null;

    #[ORM\Column(name: 'rated_by_kind', type: Types::STRING, length: 10)]
    private ?string $ratedByKind = null;

    #[ORM\Column(name: 'rated_by_id', type: Types::BIGINT)]
    private ?int $ratedById = null;

    #[ORM\Column(type: Types::STRING, length: 45)]
    private string $ip = '';

    #[ORM\Column(name: 'rated_at', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $ratedAt = null;

    public function getMessageId(): ?int
    {
        return $this->messageId;
    }

    public function setMessageId(?int $messageId): self
    {
        $this->messageId = $messageId;

        return $this;
    }

    public function getTicketId(): ?int
    {
        return $this->ticketId;
    }

    public function setTicketId(?int $ticketId): self
    {
        $this->ticketId = $ticketId;

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

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(?int $rating): self
    {
        $this->rating = $rating;

        return $this;
    }

    public function getRatedByKind(): ?string
    {
        return $this->ratedByKind;
    }

    public function setRatedByKind(?string $ratedByKind): self
    {
        $this->ratedByKind = $ratedByKind;

        return $this;
    }

    public function getRatedById(): ?int
    {
        return $this->ratedById;
    }

    public function setRatedById(?int $ratedById): self
    {
        $this->ratedById = $ratedById;

        return $this;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function setIp(string $ip): self
    {
        $this->ip = $ip;

        return $this;
    }

    public function getRatedAt(): ?\DateTimeImmutable
    {
        return $this->ratedAt;
    }

    public function setRatedAt(?\DateTimeImmutable $ratedAt): self
    {
        $this->ratedAt = $ratedAt;

        return $this;
    }
}
