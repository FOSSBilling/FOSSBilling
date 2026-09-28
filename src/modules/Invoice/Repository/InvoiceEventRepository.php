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

class InvoiceEventRepository extends EntityRepository
{
    /**
     * @return list<\Box\Mod\Invoice\Entity\InvoiceEvent>
     */
    public function findByInvoiceId(int $invoiceId): array
    {
        return $this->findBy(['invoiceId' => $invoiceId], ['id' => 'ASC']);
    }

    /**
     * Journal rows live and die with their invoice: no retention pruning by design, this is the
     * audit trail. Deletion alongside the invoice covers drafts and client erasure; relaxed-mode
     * deletions of issued invoices deliberately keep their rows.
     */
    public function deleteByInvoiceId(int $invoiceId): int
    {
        return (int) $this->getEntityManager()->getConnection()->delete('invoice_event', ['invoice_id' => $invoiceId]);
    }
}
