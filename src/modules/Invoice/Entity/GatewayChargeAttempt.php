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
#[ORM\Table(name: 'invoice_gateway_charge_attempt')]
#[ORM\Index(name: 'gateway_charge_attempt_invoice_idx', columns: ['invoice_id', 'state'])]
#[ORM\Index(name: 'gateway_charge_attempt_pending_idx', columns: ['state', 'created_at'])]
class GatewayChargeAttempt
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 32)]
    public string $id;

    #[ORM\Column(name: 'invoice_id', type: Types::BIGINT)]
    public int $invoiceId;

    #[ORM\Column(name: 'gateway_id', type: Types::BIGINT)]
    public int $gatewayId;

    #[ORM\Column(type: Types::STRING, length: 16)]
    public string $state;

    #[ORM\Column(name: 'owner_token', type: Types::STRING, length: 32, nullable: true)]
    public ?string $ownerToken = null;

    #[ORM\Column(name: 'payment_id', type: Types::STRING, length: 255, nullable: true)]
    public ?string $paymentId = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    public ?array $resolution = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    public \DateTime $createdAt;

    #[ORM\Column(name: 'resolved_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?\DateTime $resolvedAt = null;
}
