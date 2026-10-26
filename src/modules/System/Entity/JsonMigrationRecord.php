<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\System\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Opaque recovery/progress records, never exposed through the public settings API. */
#[ORM\Entity]
#[ORM\Table(name: 'json_migration_record')]
class JsonMigrationRecord
{
    public function __construct(
        #[ORM\Column(name: 'record_key', type: Types::BINARY, length: 64, unique: true)]
        private string $recordKey,
        #[ORM\Column(type: Types::BLOB)]
        private mixed $payload,
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(type: Types::BIGINT)]
        private ?int $id = null,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecordKey(): string
    {
        return $this->recordKey;
    }

    public function getPayload(): mixed
    {
        return $this->payload;
    }
}
