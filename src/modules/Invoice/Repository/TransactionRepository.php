<?php

declare(strict_types=1);

namespace Box\Mod\Invoice\Repository;

use Box\Mod\Invoice\Entity\Transaction;
use Doctrine\ORM\EntityRepository;

class TransactionRepository extends EntityRepository
{
    public function findByInvoiceId(int $invoiceId): array
    {
        return $this->findBy(['invoiceId' => $invoiceId]);
    }

    public function findByTxnId(string $txnId): ?Transaction
    {
        $transaction = $this->findOneBy(['txnId' => $txnId]);

        return $transaction instanceof Transaction ? $transaction : null;
    }

    public function findBySId(string $sId): ?Transaction
    {
        $transaction = $this->findOneBy(['sId' => $sId]);

        return $transaction instanceof Transaction ? $transaction : null;
    }
}
