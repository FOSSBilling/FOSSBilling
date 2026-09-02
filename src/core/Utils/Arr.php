<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Utils;

use FOSSBilling\Container\InjectionAwareInterface;

final class Arr implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public static function safeCount(mixed $value): int
    {
        return is_countable($value) ? count($value) : 0;
    }

    /**
     * @return mixed[]
     */
    public static function sortByOneKey(array $array, mixed $key, bool $asc = true): array
    {
        $result = [];
        $values = [];
        foreach ($array as $id => $value) {
            $values[$id] = $value[$key] ?? '';
        }

        if ($asc) {
            asort($values);
        } else {
            arsort($values);
        }

        foreach ($values as $k => $v) {
            $result[$k] = $array[$k];
        }

        return $result;
    }

    /**
     * @return mixed[]
     */
    public function getPairsForTableByIds(string $table, mixed $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            throw new \FOSSBilling\Exception\InformationException('Invalid table name');
        }

        $count = self::safeCount($ids);
        $slots = $count ? implode(',', array_fill(0, $count, '?')) : '';

        $rows = $this->di['em']->getConnection()->fetchAllAssociative(
            'SELECT id, title FROM `' . $table . '` WHERE id in (' . $slots . ')',
            $ids
        );

        $result = [];
        foreach ($rows as $record) {
            $result[$record['id']] = $record['title'];
        }

        return $result;
    }
}
