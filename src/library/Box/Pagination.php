<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use FOSSBilling\InjectionAwareInterface;

class Box_Pagination implements InjectionAwareInterface
{
    protected ?Pimple\Container $di = null;
    protected $per_page = 100;

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Pimple\Container
    {
        return $this->di;
    }

    /**
     * @return int
     */
    public function getPer_page()
    {
        return $this->per_page;
    }

    public function getSimpleResultSet($q, $values, ?int $per_page = 100, int $page = null)
    {
        if (is_null($page)) {
            $page = $_GET['page'] ?? 1;
        }
        $per_page = $_GET['per_page'] ?? $per_page ?? 100;

        if (!is_numeric($page) || $page < 1) {
            throw new FOSSBilling\InformationException('Invalid page number');
        }
        if (!is_numeric($per_page) || $per_page < 1) {
            throw new FOSSBilling\InformationException('Invalid per page number');
        }

        $offset = ($page - 1) * $per_page;

        $sql = $q;
        $sql .= sprintf(' LIMIT %u,%u', $offset, $per_page);
        $result = $this->di['db']->getAll($sql, $values);

        $exploded = explode('FROM', $q);
        $sql = 'SELECT count(1) FROM ' . $exploded[1];
        $total = $this->di['db']->getCell($sql, $values);

        $pages = ($per_page > 1) ? (int) ceil($total / $per_page) : 1;

        return [
            'pages' => $pages,
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'list' => $result,
        ];
    }

    public function getAdvancedResultSet($q, $values, ?int $per_page = 100)
    {
        $page = $_GET['page'] ?? 1;
        $per_page = $_GET['per_page'] ?? $per_page ?? 100;

        if (!is_numeric($page) || $page < 1) {
            throw new FOSSBilling\InformationException('Invalid page number');
        }
        if (!is_numeric($per_page) || $per_page < 1) {
            throw new FOSSBilling\InformationException('Invalid per page number');
        }

        $offset = ($page - 1) * $per_page;
        $q = str_replace('SELECT ', 'SELECT SQL_CALC_FOUND_ROWS ', $q);
        $q .= sprintf(' LIMIT %u,%u', $offset, $per_page);
        $result = $this->di['db']->getAll($q, $values);
        $total = $this->di['db']->getCell('SELECT FOUND_ROWS();');

        $pages = ($per_page > 1) ? (int) ceil($total / $per_page) : 1;

        return [
            'pages' => $pages,
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'list' => $result,
        ];
    }
}
