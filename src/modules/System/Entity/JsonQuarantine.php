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

/**
 * Values the JSON migration nulled instead of converting, kept for review and
 * manual reconciliation. Unlike migration-progress records, these survive
 * finalization until an administrator restores or dismisses them.
 */
#[ORM\Entity]
#[ORM\Table(name: 'json_quarantine')]
class JsonQuarantine
{
    public function __construct(
        #[ORM\Column(name: 'table_name', type: Types::STRING, length: 64)]
        private string $tableName,
        #[ORM\Column(name: 'column_name', type: Types::STRING, length: 64)]
        private string $columnName,
        #[ORM\Column(name: 'row_id', type: Types::BLOB)]
        private mixed $rowId,
        #[ORM\Column(name: 'id_binary', type: Types::BOOLEAN)]
        private bool $idBinary,
        #[ORM\Column(name: 'display_id', type: Types::STRING, length: 128)]
        private string $displayId,
        #[ORM\Column(type: Types::STRING, length: 32)]
        private string $issue,
        #[ORM\Column(type: Types::BLOB)]
        private mixed $original,
        #[ORM\Id]
        #[ORM\GeneratedValue]
        #[ORM\Column(type: Types::BIGINT)]
        private ?int $id = null,
        #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
        private ?\DateTime $createdAt = null,
    ) {
        $this->createdAt ??= new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function getColumnName(): string
    {
        return $this->columnName;
    }

    public function getRowId(): mixed
    {
        return $this->rowId;
    }

    public function isIdBinary(): bool
    {
        return $this->idBinary;
    }

    public function getDisplayId(): string
    {
        return $this->displayId;
    }

    public function getIssue(): string
    {
        return $this->issue;
    }

    public function getOriginal(): mixed
    {
        return $this->original;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }
}
