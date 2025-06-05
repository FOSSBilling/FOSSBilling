<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

class Pagination implements InjectionAwareInterface
{
    private ?\Pimple\Container $di = null;

    public const MAX_PER_PAGE = PHP_INT_MAX; // If we ever want to enforce a limit
    public const DEFAULT_PER_PAGE = 100;

    public function setDi(?\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    /**
     * Get the system-wide default number of results per page.
     */
    public function getDefaultPerPage(): int
    {
        return self::DEFAULT_PER_PAGE;
    }

    /**
     * Paginate a SQL query using a simple LIMIT clause and a secondary count query.
     *
     * @param string   $query        the base SQL query without LIMIT
     * @param array    $params       the values to bind to the query
     * @param int|null $perPage      Optional number of items per page. (defaults to 100)
     * @param int|null $page         Optional current page number. (grabbed from query parameters by default)
     * @param string   $pageParam    query parameter key for the page number (default: "page")
     * @param string   $perPageParam query parameter key for the per-page count (default: "per_page")
     *
     * @return array{
     *     pages: int,
     *     page: int,
     *     per_page: int,
     *     total: int,
     *     list: array
     * }
     *
     * @throws InformationException if the page/per-page value or the SQL query is invalid
     */
    public function getPaginatedResultSet(string $query, array $params = [], ?int $perPage = null, ?int $page = null, string $pageParam = 'page', string $perPageParam = 'per_page'): array
    {
        $request = $this->di['request'];

        $page ??= filter_var($request->query->get($pageParam), FILTER_VALIDATE_INT, ['options' => ['default' => 1]]);
        $perPage ??= filter_var($request->query->get($perPageParam), FILTER_VALIDATE_INT, ['options' => ['default' => $this->getDefaultPerPage()]]);

        if ($page < 1) {
            throw new InformationException("Page number ($pageParam) must be a positive integer.");
        }
        if ($perPage < 1) {
            throw new InformationException("The number of items per page ($perPageParam) must be a positive integer.");
        }
        if ($perPage > self::MAX_PER_PAGE) {
            throw new InformationException("The number of items per page ($perPageParam) must be below the maximum allowed amount (" . self::MAX_PER_PAGE . ').');
        }

        $offset = ($page - 1) * $perPage;

        $paginatedQuery = $query . sprintf(' LIMIT %u, %u', $offset, $perPage);
        $result = $this->di['db']->getAll($paginatedQuery, $params);

        // Attempt to construct count query more reliably
        $fromPos = stripos($query, 'FROM');
        if ($fromPos === false) {
            throw new InformationException('Invalid SQL query. Missing FROM clause.');
        }

        $query = rtrim($query, " ;\n\r\t");
        $countQuery = 'SELECT COUNT(1) FROM (' . $query . ') AS sub';
        $total = (int) $this->di['db']->getCell($countQuery, $params);

        return [
            'pages' => $total > 0 ? (int) ceil($total / $perPage) : 0,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'list' => $result,
        ];
    }

    /* Deprecated functions */
    /**
     * @deprecated 0.7.0, you should use getPaginatedResultSet() instead which is a drop in replacement.
     */
    public function getAdvancedResultSet(string $query, array $params = [], ?int $perPage = null, ?int $page = null, string $pageParam = 'page', string $perPageParam = 'per_page'): array
    {
        trigger_error('getAdvancedResultSet() is deprecated and will be removed in a future version of FOSSBilling. Please use getPaginatedResultSet() instead.', E_USER_DEPRECATED);

        return $this->getPaginatedResultSet($query, $params, $perPage, $page, $pageParam, $perPageParam);
    }

    /**
     * @deprecated 0.7.0, you should use getPaginatedResultSet() instead which is a drop in replacement.
     */
    public function getSimpleResultSet(string $query, array $params = [], ?int $perPage = null, ?int $page = null, string $pageParam = 'page', string $perPageParam = 'per_page'): array
    {
        trigger_error('getSimpleResultSet() is deprecated and will be removed in a future version of FOSSBilling. Please use getPaginatedResultSet() instead.', E_USER_DEPRECATED);

        return $this->getPaginatedResultSet($query, $params, $perPage, $page, $pageParam, $perPageParam);
    }

    /**
     * @deprecated 0.7.0, you should use getDefaultPerPage() instead which is a drop in replacement.
     */
    public function getPer_page(): int
    {
        trigger_error('getPer_page() is deprecated and will be removed in a future version of FOSSBilling. Please use getDefaultPerPage() instead.', E_USER_DEPRECATED);

        return $this->getDefaultPerPage();
    }
}
