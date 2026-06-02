<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use FOSSBilling\Interfaces\ApiArrayInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class Pagination implements InjectionAwareInterface
{
    private ?\Pimple\Container $di = null;

    public function setDi(?\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    /**
     * Build the standard paginated response array.
     */
    private function buildPaginatedResponse(int $page, int $perPage, int $total, array $list): array
    {
        return [
            'pages' => $total > 0 ? (int) ceil($total / $perPage) : 0,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'list' => $list,
        ];
    }

    /**
     * Paginate results from a Doctrine QueryBuilder.
     *
     * Applies pagination to a Doctrine QueryBuilder and returns metadata and normalized entities.
     * Entities implementing `ApiArrayInterface` will use `toApiArray()`, others will be normalized
     * using Symfony's ObjectNormalizer.
     *
     * @param QueryBuilder      $qb         the Doctrine QueryBuilder instance to paginate
     * @param PaginationOptions $pagination pagination options
     *
     * @return array{
     *     pages: int,      // Total number of pages
     *     page: int,       // Current page number
     *     per_page: int,   // Items per page
     *     total: int,      // Total number of items
     *     list: array      // List of paginated items as arrays
     * }
     */
    public function paginateDoctrineQuery(QueryBuilder $qb, PaginationOptions $pagination): array
    {
        $serializer = new Serializer([new ObjectNormalizer()]);

        $offset = ($pagination->page - 1) * $pagination->perPage;
        $qb->setFirstResult($offset)
           ->setMaxResults($pagination->perPage);
        $paginator = new DoctrinePaginator($qb, true);

        $total = count($paginator);

        $list = [];
        foreach ($paginator as $entity) {
            if ($entity instanceof ApiArrayInterface) {
                $list[] = $entity->toApiArray();
            } else {
                $list[] = $serializer->normalize($entity);
            }
        }

        return $this->buildPaginatedResponse($pagination->page, $pagination->perPage, $total, $list);
    }

    /**
     * Paginate a SQL query using a simple LIMIT clause and a secondary count query.
     *
     * @param string            $query      the base SQL query without LIMIT
     * @param array             $params     the values to bind to the query
     * @param PaginationOptions $pagination pagination options
     *
     * @return array{
     *     pages: int,      // Total number of pages
     *     page: int,       // Current page number
     *     per_page: int,   // Items per page
     *     total: int,      // Total number of items
     *     list: array      // List of paginated items as arrays
     * }
     *
     * @throws InformationException if the SQL query is invalid
     */
    public function getPaginatedResultSet(string $query, array $params, PaginationOptions $pagination): array
    {
        $offset = ($pagination->page - 1) * $pagination->perPage;

        if (stripos($query, 'FROM') === false) {
            throw new InformationException('Invalid SQL query. Missing FROM clause.');
        }

        $paginatedQuery = $query . sprintf(' LIMIT %u, %u', $offset, $pagination->perPage);
        $result = $this->di['db']->getAll($paginatedQuery, $params);

        $query = rtrim($query, " ;\n\r\t");
        $countQuery = 'SELECT COUNT(1) FROM (' . $query . ') AS sub';
        $total = (int) $this->di['db']->getCell($countQuery, $params);

        return $this->buildPaginatedResponse($pagination->page, $pagination->perPage, $total, $result);
    }
}
