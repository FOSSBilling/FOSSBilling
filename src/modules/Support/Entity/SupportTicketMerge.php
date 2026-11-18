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
 * A ticket merged away into another one: the surviving ticket keeps every
 * message, note, file, rating and feedback entry; the deleted ticket's old
 * guest link and subject stay here so old pages and emailed replies resolve
 * to the survivor (see Box\Mod\Support\TicketMerge). Cannot be undone.
 */
#[ORM\Entity]
#[ORM\Table(name: 'support_ticket_merge')]
#[ORM\Index(name: 'support_ticket_merge_merged_into_idx', columns: ['merged_into'])]
#[ORM\Index(name: 'support_ticket_merge_access_hash_idx', columns: ['access_hash'], options: ['lengths' => [191]])]
class SupportTicketMerge
{
    #[ORM\Id]
    #[ORM\Column(name: 'ticket_id', type: Types::BIGINT)]
    private ?int $ticketId = null;

    #[ORM\Column(name: 'merged_into', type: Types::BIGINT)]
    private ?int $mergedInto = null;

    #[ORM\Column(name: 'access_hash', type: Types::STRING, length: 255, nullable: true)]
    private ?string $accessHash = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $subject = '';

    #[ORM\Column(name: 'merged_by', type: Types::BIGINT, nullable: true)]
    private ?int $mergedBy = null;

    #[ORM\Column(name: 'merged_at', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $mergedAt = null;

    public function getTicketId(): ?int
    {
        return $this->ticketId;
    }

    public function setTicketId(?int $ticketId): self
    {
        $this->ticketId = $ticketId;

        return $this;
    }

    public function getMergedInto(): ?int
    {
        return $this->mergedInto;
    }

    public function setMergedInto(?int $mergedInto): self
    {
        $this->mergedInto = $mergedInto;

        return $this;
    }

    public function getAccessHash(): ?string
    {
        return $this->accessHash;
    }

    public function setAccessHash(?string $accessHash): self
    {
        $this->accessHash = $accessHash;

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

    public function getMergedBy(): ?int
    {
        return $this->mergedBy;
    }

    public function setMergedBy(?int $mergedBy): self
    {
        $this->mergedBy = $mergedBy;

        return $this;
    }

    public function getMergedAt(): ?\DateTimeImmutable
    {
        return $this->mergedAt;
    }

    public function setMergedAt(?\DateTimeImmutable $mergedAt): self
    {
        $this->mergedAt = $mergedAt;

        return $this;
    }
}
