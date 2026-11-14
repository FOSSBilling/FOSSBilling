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
 * Tickets assigned to a staff member (see Box\Mod\Support\TicketAssign).
 * One row per assigned ticket; a ticket without a row, or whose staff
 * member was deleted or deactivated, is unassigned.
 */
#[ORM\Entity(repositoryClass: \Box\Mod\Support\Repository\SupportTicketAssignmentRepository::class)]
#[ORM\Table(name: 'support_ticket_assignment')]
class SupportTicketAssignment
{
    #[ORM\Id]
    #[ORM\Column(name: 'ticket_id', type: Types::BIGINT)]
    private ?int $ticketId = null;

    #[ORM\Column(name: 'admin_id', type: Types::BIGINT)]
    private ?int $adminId = null;

    #[ORM\Column(name: 'assigned_by', type: Types::BIGINT, nullable: true)]
    private ?int $assignedBy = null;

    #[ORM\Column(name: 'assigned_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $assignedAt = null;

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

    public function getAssignedBy(): ?int
    {
        return $this->assignedBy;
    }

    public function setAssignedBy(?int $assignedBy): self
    {
        $this->assignedBy = $assignedBy;

        return $this;
    }

    public function getAssignedAt(): ?\DateTime
    {
        return $this->assignedAt;
    }

    public function setAssignedAt(?\DateTime $assignedAt): self
    {
        $this->assignedAt = $assignedAt;

        return $this;
    }
}
