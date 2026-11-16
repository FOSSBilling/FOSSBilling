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
 * Tickets pinned to the top of the staff ticket list (see Box\Mod\Support\TicketBulk).
 * One row per pinned ticket.
 */
#[ORM\Entity(repositoryClass: \Box\Mod\Support\Repository\SupportTicketPinRepository::class)]
#[ORM\Table(name: 'support_ticket_pin')]
class SupportTicketPin
{
    #[ORM\Id]
    #[ORM\Column(name: 'ticket_id', type: Types::BIGINT)]
    private ?int $ticketId = null;

    #[ORM\Column(name: 'pinned_by', type: Types::BIGINT, nullable: true)]
    private ?int $pinnedBy = null;

    #[ORM\Column(name: 'pinned_at', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $pinnedAt = null;

    public function getTicketId(): ?int
    {
        return $this->ticketId;
    }

    public function setTicketId(?int $ticketId): self
    {
        $this->ticketId = $ticketId;

        return $this;
    }

    public function getPinnedBy(): ?int
    {
        return $this->pinnedBy;
    }

    public function setPinnedBy(?int $pinnedBy): self
    {
        $this->pinnedBy = $pinnedBy;

        return $this;
    }

    public function getPinnedAt(): ?\DateTimeImmutable
    {
        return $this->pinnedAt;
    }

    public function setPinnedAt(?\DateTimeImmutable $pinnedAt): self
    {
        $this->pinnedAt = $pinnedAt;

        return $this;
    }
}
