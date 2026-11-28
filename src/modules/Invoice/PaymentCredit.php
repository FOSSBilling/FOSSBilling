<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Invoice;

use Box\Mod\Invoice\Entity\Transaction;
use Doctrine\ORM\EntityManagerInterface;
use FOSSBilling\Core\Payment\ProcessingFence;

/** A credit already committed by an earlier owner must survive a processing retry. */
final class PaymentCredit
{
    public static function current(EntityManagerInterface $em): ?Transaction
    {
        foreach (array_reverse(ProcessingFence::claims()) as $claim) {
            if ($claim['table'] === 'transaction') {
                $transaction = $em->find(Transaction::class, $claim['id']);
                if (!$transaction instanceof Transaction) {
                    throw new \FOSSBilling\Core\Payment\OwnershipLost('The payment transaction no longer exists.');
                }

                return $transaction;
            }
        }

        return null;
    }

    public static function key(EntityManagerInterface $em, array $data): ?string
    {
        $transaction = self::current($em);

        return $transaction === null ? null : self::forTransaction($transaction, $data);
    }

    public static function reference(Transaction $transaction, array $data): string
    {
        if (is_string($data['payment_reference'] ?? null) && $data['payment_reference'] !== '') {
            return $data['payment_reference'];
        }
        $code = $transaction->getGateway()?->getGateway();
        if (in_array($code, ['PayPalEmail', 'PayPalAutoPay'], true)) {
            return (string) ($data['rel_id'] ?? $transaction->getTxnId() ?? 'transaction:' . $transaction->getId());
        }
        if ($code === 'Stripe') {
            $payload = $transaction->getIpn() ?? [];
            $event = json_decode((string) ($payload['http_raw_post_data'] ?? ''), true);
            foreach ([$transaction->getTxnId(), $payload['get']['payment_intent'] ?? null, $event['data']['object']['id'] ?? null] as $reference) {
                if (is_string($reference) && preg_match('/^(?:pi|in|ch)_/', $reference)) {
                    return $reference;
                }
            }
        }

        return 'transaction:' . $transaction->getId();
    }

    public static function forTransaction(Transaction $transaction, array $data): string
    {
        $gateway = $transaction->getGateway();
        $code = $gateway?->getGateway() ?? '';
        $realm = in_array($code, ['PayPalEmail', 'PayPalAutoPay'], true) ? 'paypal' : $code . ':' . $gateway?->getId();

        return hash('sha256', json_encode([$realm, self::reference($transaction, $data), $data['type'] ?? 'gift'], JSON_THROW_ON_ERROR));
    }

    /** Unmarked historic credits are evidence for staff review, never evidence of absence. */
    public static function assertNoLegacyCredit(EntityManagerInterface $em, int $clientId, array $data): void
    {
        $transaction = self::current($em);
        if ($transaction === null) {
            return;
        }
        $state = $transaction->getCreditRecoveryState();
        if ($state === 1) { // staff verified that no earlier wallet credit exists
            return;
        }
        if ($state !== 0) {
            throw new \FOSSBilling\Core\Exception\InformationException('Reconcile this payment with the existing wallet history on its transaction page before crediting it.');
        }
        $reference = self::reference($transaction, $data);
        if (str_starts_with($reference, 'transaction:')) {
            return; // A newly created transaction cannot have an unmarked credit of its own.
        }
        $db = $em->getConnection();
        $table = $db->quoteSingleIdentifier('transaction');
        $code = $transaction->getGateway()?->getGateway();
        $gatewayPredicate = in_array($code, ['PayPalEmail', 'PayPalAutoPay'], true)
            ? "g.gateway IN ('PayPalEmail', 'PayPalAutoPay')" : 'g.id = ' . (int) $transaction->getGateway()?->getId();
        $related = $db->fetchAllAssociative("SELECT t.id, t.credit_recovery_state FROM {$table} t JOIN pay_gateway g ON g.id = t.gateway_id WHERE t.txn_id = ? AND {$gatewayPredicate}", [$reference]);
        foreach ($related as $row) {
            if ($row['credit_recovery_state'] === null && (int) $row['id'] !== $transaction->getId()) {
                throw new \FOSSBilling\Core\Exception\InformationException('An older transaction for this payment needs wallet reconciliation before another callback can credit it.');
            }
        }
        $relations = array_map(static fn (array $row): string => (string) $row['id'], $related);
        $relations[] = (string) $transaction->getId();
        if (isset($data['rel_id'])) {
            $relations[] = (string) $data['rel_id'];
        }
        $descriptions = ['Stripe transaction ' . $reference, 'Stripe subscription initial payment ' . $reference, 'Stripe subscription recurring payment ' . $reference, 'PayPal transaction ' . $reference];
        $legacy = $db->executeQuery(
            'SELECT id FROM client_balance WHERE client_id = ? AND type = ? AND payment_key IS NULL AND amount > 0 AND (rel_id IN (?) OR description IN (?)) LIMIT 1',
            [$clientId, $data['type'] ?? 'gift', $relations, $descriptions],
            [\Doctrine\DBAL\ParameterType::INTEGER, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ArrayParameterType::STRING, \Doctrine\DBAL\ArrayParameterType::STRING]
        )->fetchOne();
        if ($legacy !== false) {
            throw new \FOSSBilling\Core\Exception\InformationException('An unmarked wallet credit may already record this payment. Reconcile it on the transaction page before continuing.');
        }
    }
}
