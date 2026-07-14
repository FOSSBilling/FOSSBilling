<?php

declare(strict_types=1);

namespace Box\Mod\Invoice\Repository;

use Doctrine\ORM\EntityRepository;

class InvoiceItemRepository extends EntityRepository
{
    public function findByInvoiceId(int $invoiceId): array
    {
        return $this->findBy(['invoiceId' => $invoiceId]);
    }

    public function findPendingRenewal(int $relId): array
    {
        return $this->findBy(['relId' => (string) $relId, 'type' => \Box\Mod\Invoice\Entity\InvoiceItem::TYPE_ORDER, 'task' => \Box\Mod\Invoice\Entity\InvoiceItem::TASK_RENEW, 'status' => \Box\Mod\Invoice\Entity\InvoiceItem::STATUS_PENDING_PAYMENT]);
    }
}
