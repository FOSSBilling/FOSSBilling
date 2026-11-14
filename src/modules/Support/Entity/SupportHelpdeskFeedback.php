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
 * Whether a help desk asks for closing feedback (missing row means on).
 * Saved on the help desk's page (see Box\Mod\Support\TicketFeedback).
 */
#[ORM\Entity(repositoryClass: \Box\Mod\Support\Repository\SupportHelpdeskFeedbackRepository::class)]
#[ORM\Table(name: 'support_helpdesk_feedback')]
class SupportHelpdeskFeedback
{
    #[ORM\Id]
    #[ORM\Column(name: 'helpdesk_id', type: Types::BIGINT)]
    private ?int $helpdeskId = null;

    #[ORM\Column(name: 'feedback_request', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $feedbackRequest = true;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $updatedAt = null;

    public function getHelpdeskId(): ?int
    {
        return $this->helpdeskId;
    }

    public function setHelpdeskId(?int $helpdeskId): self
    {
        $this->helpdeskId = $helpdeskId;

        return $this;
    }

    public function isFeedbackRequest(): bool
    {
        return $this->feedbackRequest;
    }

    public function setFeedbackRequest(bool $feedbackRequest): self
    {
        $this->feedbackRequest = $feedbackRequest;

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
}
