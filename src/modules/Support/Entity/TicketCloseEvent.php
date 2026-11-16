<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Support\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'support_ticket_close_event')]
#[ORM\Index(name: 'ticket_close_event_pending_idx', columns: ['delivered_at', 'created_at'])]
#[ORM\Index(name: 'ticket_close_event_ticket_idx', columns: ['ticket_id'])]
class TicketCloseEvent
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 32)]
    public string $id;

    #[ORM\Column(name: 'ticket_id', type: Types::BIGINT)]
    public int $ticketId;

    #[ORM\Column(type: Types::STRING, length: 10)]
    public string $actor;

    #[ORM\Column(name: 'actor_id', type: Types::BIGINT, nullable: true)]
    public ?int $actorId = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'attempted_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $attemptedAt = null;

    #[ORM\Column(name: 'notification_queued_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $notificationQueuedAt = null;

    #[ORM\Column(name: 'feedback_queued_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $feedbackQueuedAt = null;

    #[ORM\Column(name: 'delivered_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $deliveredAt = null;
}
