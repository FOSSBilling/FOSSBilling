<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Invoice\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'invoice_gateway_refund_attempt')]
#[ORM\Index(name: 'gateway_refund_attempt_invoice_idx', columns: ['invoice_id', 'state'])]
class GatewayRefundAttempt
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 32)]
    public string $id;

    #[ORM\Column(name: 'invoice_id', type: Types::BIGINT)]
    public int $invoiceId;

    #[ORM\Column(name: 'fingerprint', type: Types::STRING, length: 64)]
    public string $fingerprint;

    #[ORM\Column(type: Types::STRING, length: 16)]
    public string $state;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    public \DateTimeImmutable $createdAt;
}
