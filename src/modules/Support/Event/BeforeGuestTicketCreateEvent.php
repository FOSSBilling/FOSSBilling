<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Support\Event;

use FOSSBilling\Events\Event;

/**
 * Mutable ticket fields for guest ticket creation, after legacy hooks have run.
 */
final class BeforeGuestTicketCreateEvent extends Event
{
    /** @param array<string, mixed> $input */
    public function __construct(
        public readonly array $input,
        private ?string $status,
        private ?string $subject,
        private ?string $message,
    ) {
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): void
    {
        $this->subject = $subject;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): void
    {
        $this->message = $message;
    }
}
