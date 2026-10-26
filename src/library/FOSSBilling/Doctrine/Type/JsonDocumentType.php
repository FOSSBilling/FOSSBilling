<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\JsonType;
use Doctrine\DBAL\Types\Type;
use FOSSBilling\Doctrine\JsonDocument;

/**
 * Text-backed JSON conversion for the legacy string API boundary.
 * Native JSON DDL is deliberately deferred: it rejects invalid historical rows and may
 * normalize documents whose exact bytes existing callers still expect to read.
 */
final class JsonDocumentType extends JsonType
{
    public const string NAME = 'json_document';

    public static function register(): void
    {
        if (!Type::hasType(self::NAME)) {
            Type::addType(self::NAME, self::class);
        }
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof JsonDocument) {
            return $value->json();
        }
        if (is_array($value) || $value instanceof \stdClass) {
            return parent::convertToDatabaseValue($value, $platform);
        }

        throw new \InvalidArgumentException('Use a JsonDocument for raw JSON strings or pass structured JSON data.');
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?JsonDocument
    {
        if ($value === null || $value instanceof JsonDocument) {
            return $value;
        }
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException('A stored JSON document must be a string.');
        }

        return JsonDocument::fromJson($value);
    }
}
