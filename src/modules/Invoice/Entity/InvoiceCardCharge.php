<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Invoice\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Counts the automatic saved-method charges tried for an invoice, so the
 * cron spaces retries and eventually stops. A row is created on the first
 * try; HELD (99) attempts stops automatic tries for an invoice whose
 * payment may already have been taken but is not confirmed yet - staff can
 * still charge it on purpose.
 */
#[ORM\Entity(repositoryClass: \Box\Mod\Invoice\Repository\InvoiceCardChargeRepository::class)]
#[ORM\Table(name: 'invoice_card_charge')]
class InvoiceCardCharge
{
    final public const int HELD = 99;

    #[ORM\Id]
    #[ORM\Column(name: 'invoice_id', type: Types::BIGINT)]
    private ?int $invoiceId = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $attempts = 0;

    #[ORM\Column(name: 'last_attempt_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastAttemptAt = null;

    #[ORM\Column(name: 'last_error', type: Types::STRING, length: 255, nullable: true)]
    private ?string $lastError = null;

    public function getInvoiceId(): ?int
    {
        return $this->invoiceId;
    }

    public function setInvoiceId(?int $invoiceId): self
    {
        $this->invoiceId = $invoiceId;

        return $this;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function setAttempts(int $attempts): self
    {
        $this->attempts = $attempts;

        return $this;
    }

    public function getLastAttemptAt(): ?\DateTimeImmutable
    {
        return $this->lastAttemptAt;
    }

    public function setLastAttemptAt(?\DateTimeImmutable $lastAttemptAt): self
    {
        $this->lastAttemptAt = $lastAttemptAt;

        return $this;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(?string $lastError): self
    {
        $this->lastError = $lastError;

        return $this;
    }

    public function isHeld(): bool
    {
        return $this->attempts >= self::HELD;
    }
}
