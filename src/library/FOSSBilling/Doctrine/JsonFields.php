<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Doctrine;

/**
 * Structured documents with whole-value application reads. ID selections retain their existing
 * domain validation; they are not used as substitutes for new queryable Doctrine associations.
 * Generic extension metadata mixes settings, encrypted configuration and token hashes, so it
 * stays opaque TEXT. Retired email preview storage is cleared by the portable update step.
 */
final class JsonFields
{
    /** @var array<string, array{entity: class-string, columns: array<string, string>}> */
    public const array FIELDS = [
        'mod_massmailer' => ['entity' => \Box\Mod\Massmailer\Entity\MassmailerMessage::class, 'columns' => ['filter' => 'filter']],
        'form_field' => ['entity' => \Box\Mod\Formbuilder\Entity\FormField::class, 'columns' => ['options' => 'options', 'default_value' => 'defaultValue']],
        'form' => ['entity' => \Box\Mod\Formbuilder\Entity\Form::class, 'columns' => ['style' => 'style']],
        'product' => ['entity' => \Box\Mod\Product\Entity\Product::class, 'columns' => ['config' => 'config', 'addons' => 'addons', 'upgrades' => 'upgrades']],
        'promo' => ['entity' => \Box\Mod\Product\Entity\Promo::class, 'columns' => ['products' => 'products', 'periods' => 'periods', 'client_groups' => 'clientGroups', 'requires_products' => 'requiresProducts']],
        'cart_product' => ['entity' => \Box\Mod\Cart\Entity\CartProduct::class, 'columns' => ['config' => 'config']],
        'client_order' => ['entity' => \Box\Mod\Order\Entity\Order::class, 'columns' => ['config' => 'config']],
        'service_custom' => ['entity' => \Box\Mod\Servicecustom\Entity\ServiceCustom::class, 'columns' => ['config' => 'config']],
        'service_apikey' => ['entity' => \Box\Mod\Serviceapikey\Entity\ServiceApiKey::class, 'columns' => ['config' => 'config']],
        'service_hosting_hp' => ['entity' => \Box\Mod\Servicehosting\Entity\ServiceHostingHp::class, 'columns' => ['config' => 'config']],
        'service_hosting_server' => ['entity' => \Box\Mod\Servicehosting\Entity\ServiceHostingServer::class, 'columns' => ['config' => 'config', 'assigned_ips' => 'assignedIps']],
        'service_license' => ['entity' => \Box\Mod\Servicelicense\Entity\ServiceLicense::class, 'columns' => ['ips' => 'ips', 'hosts' => 'hosts', 'paths' => 'paths', 'versions' => 'versions']],
        'pay_gateway' => ['entity' => \Box\Mod\Invoice\Entity\PayGateway::class, 'columns' => ['config' => 'config', 'accepted_currencies' => 'acceptedCurrencies']],
        'tld_registrar' => ['entity' => \Box\Mod\Servicedomain\Entity\TldRegistrar::class, 'columns' => ['config' => 'config']],
        'transaction' => ['entity' => \Box\Mod\Invoice\Entity\Transaction::class, 'columns' => ['ipn' => 'ipn']],
        'session' => ['entity' => \Box\Mod\System\Entity\Session::class, 'columns' => ['fingerprint' => 'fingerprint']],
    ];

    /** @return \Generator<int, array{id: int|string, value: ?string, type: ?string, id_type: \Doctrine\DBAL\ParameterType}> */
    public static function rows(\Doctrine\DBAL\Connection $connection, string $table, string $column, int $batchSize = 500, int|string|null $after = null, \Doctrine\DBAL\ParameterType $afterType = \Doctrine\DBAL\ParameterType::STRING): \Generator
    {
        $schema = $connection->createSchemaManager()->introspectTableByUnquotedName($table);
        $platform = $connection->getDatabasePlatform();
        $sqlite = $platform instanceof \Doctrine\DBAL\Platforms\SQLitePlatform;
        $binary = $platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform
            && in_array($schema->getColumn('id')->getTypeName(), [\Doctrine\DBAL\Types\Types::BINARY, \Doctrine\DBAL\Types\Types::BLOB], true);
        $type = $table === 'form_field' && $column === 'default_value' && $schema->hasColumn('type');
        $cursor = $after;
        $cursorType = $afterType;
        do {
            $rows = $connection->fetchAllAssociative('SELECT id, ' . $connection->quoteSingleIdentifier($column) . ' AS value' . ($type ? ', type' : '') . ($sqlite ? ', typeof(id) AS id_storage' : '') . ' FROM ' . $connection->quoteSingleIdentifier($table)
                . ($cursor === null ? '' : ' WHERE id > :cursor') . ' ORDER BY id LIMIT ' . $batchSize, $cursor === null ? [] : ['cursor' => $cursor], $cursor === null ? [] : ['cursor' => $cursorType]);
            foreach ($rows as $row) {
                $cursor = is_resource($row['id']) ? stream_get_contents($row['id']) : $row['id'];
                $cursorType = ($sqlite ? $row['id_storage'] === 'blob' : $binary) ? \Doctrine\DBAL\ParameterType::BINARY : \Doctrine\DBAL\ParameterType::STRING;
                $value = is_resource($row['value']) ? stream_get_contents($row['value']) : $row['value'];
                yield ['id' => $cursor, 'value' => $value, 'type' => $row['type'] ?? null, 'id_type' => $cursorType];
            }
        } while (count($rows) === $batchSize);
    }

    public static function prepareValue(array $row, bool $legacyDefault): ?string
    {
        if (!$legacyDefault || $row['value'] === null) {
            return $row['value'];
        }
        // Only checkbox defaults were collections. JSON-looking text must remain text.
        $decoded = $row['type'] === 'checkbox' ? json_decode($row['value'], true) : null;

        return json_encode(is_array($decoded) ? $decoded : $row['value'], JSON_THROW_ON_ERROR);
    }

    public static function displayId(string $table, int|string $id): int|string
    {
        return $table === 'session' ? 'sha256:' . hash('sha256', (string) $id) : $id;
    }
}
