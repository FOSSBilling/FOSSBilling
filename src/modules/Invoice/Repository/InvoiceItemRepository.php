<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Invoice\Repository;

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class InvoiceItemRepository extends EntityRepository
{
    /**
     * @return InvoiceItem[]
     */
    public function findByInvoiceId(int $invoiceId): array
    {
        return $this->findBy(['invoice' => $this->getEntityManager()->getReference(Invoice::class, $invoiceId)]);
    }

    /**
     * First invoice item of the given type for an invoice (legacy
     * RedBeanPHP `findOne` first-match semantics).
     */
    public function findOneByInvoiceIdAndType(int $invoiceId, string $type): ?InvoiceItem
    {
        $item = $this->findOneBy(['invoice' => $this->getEntityManager()->getReference(Invoice::class, $invoiceId), 'type' => $type]);

        return $item instanceof InvoiceItem ? $item : null;
    }

    /**
     * @return InvoiceItem[]
     */
    public function findFailed(): array
    {
        return $this->findBy(['status' => InvoiceItem::STATUS_FAILED], ['id' => 'DESC']);
    }

    /**
     * Build a QueryBuilder for invoice-item searches/listings.
     *
     * @param array $data filter and pagination parameters (unused for now)
     */
    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        return $this->createQueryBuilder('ii')
            ->orderBy('ii.id', 'DESC');
    }
}
