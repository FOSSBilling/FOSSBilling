<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Invoice\Repository;

use Doctrine\ORM\EntityRepository;

class InvoiceCreditRepository extends EntityRepository
{
    public function getSumByInvoiceId(int $invoiceId): float
    {
        $result = $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT COALESCE(SUM(amount), 0) FROM invoice_credit WHERE invoice_id = :invoice_id',
            ['invoice_id' => $invoiceId],
        );

        return round((float) $result, 2);
    }

    /**
     * @param list<int> $invoiceIds
     *
     * @return array<int, float> applied-credit sums keyed by invoice id
     */
    public function getSumsByInvoiceIds(array $invoiceIds): array
    {
        if ($invoiceIds === []) {
            return [];
        }

        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT invoice_id, COALESCE(SUM(amount), 0) AS amount FROM invoice_credit WHERE invoice_id IN (:invoice_ids) GROUP BY invoice_id',
            ['invoice_ids' => $invoiceIds],
            ['invoice_ids' => \Doctrine\DBAL\ArrayParameterType::INTEGER],
        );

        $sums = [];
        foreach ($rows as $row) {
            $sums[(int) $row['invoice_id']] = round((float) $row['amount'], 2);
        }

        return $sums;
    }

    public function deleteByInvoiceId(int $invoiceId): int
    {
        return $this->getEntityManager()->getConnection()->delete('invoice_credit', ['invoice_id' => $invoiceId]);
    }
}
