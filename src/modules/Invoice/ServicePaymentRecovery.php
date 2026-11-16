<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Invoice;

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\PayGateway;
use FOSSBilling\Core\Container\InjectionAwareInterface;
use FOSSBilling\Core\Doctrine\NamedLock;
use FOSSBilling\Core\Exception\InformationException;
use Pimple\Container;

class ServicePaymentRecovery implements InjectionAwareInterface
{
    private ?Container $di = null;

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Container
    {
        return $this->di;
    }

    public function queue(): array
    {
        $db = $this->di['em']->getConnection();
        $table = $db->quoteSingleIdentifier('transaction');

        return [
            'attempts' => $db->fetchAllAssociative("SELECT id, invoice_id, gateway_id, state, payment_id, created_at FROM invoice_gateway_charge_attempt WHERE state = 'pending' ORDER BY created_at, id LIMIT 500"),
            'transactions' => $db->fetchAllAssociative("SELECT id, invoice_id, gateway_id, txn_id, updated_at FROM {$table} WHERE status = 'processing' ORDER BY updated_at, id LIMIT 500"),
        ];
    }

    private function context(string $id, ?string $reference): array
    {
        $attempt = $this->di['em']->getConnection()->fetchAssociative('SELECT * FROM invoice_gateway_charge_attempt WHERE id = ?', [$id]);
        if (!$attempt || $attempt['state'] !== 'pending') {
            throw new InformationException('This payment attempt no longer needs recovery.');
        }
        $invoice = $this->di['em']->find(Invoice::class, (int) $attempt['invoice_id']);
        $gateway = $this->di['em']->find(PayGateway::class, (int) $attempt['gateway_id']);
        if (!$invoice instanceof Invoice || !$gateway instanceof PayGateway) {
            throw new InformationException('The invoice or payment gateway is no longer available.');
        }
        $adapter = $this->di['mod_service']('Invoice', 'PayGateway')->getPaymentAdapter($gateway, $invoice);
        $known = $attempt['payment_id'];
        if ($known !== null && $reference !== null && $reference !== '' && $reference !== $known) {
            throw new InformationException('Use the payment reference already recorded for this attempt.');
        }
        $reference = $known ?? $reference;
        if ($reference === null || $reference === '' || !method_exists($adapter, 'inspectPayment')) {
            $result = ['state' => 'unknown', 'reference' => $reference];
        } else {
            $result = $adapter->inspectPayment($reference, (int) $invoice->getId());
            if (isset($result['currency']) && strcasecmp($result['currency'], (string) $invoice->getCurrency()) !== 0) {
                throw new InformationException('The payment currency does not match the invoice.');
            }
        }

        return [$attempt, $invoice, $adapter, $result];
    }

    public function inspectTransaction(int $id): array
    {
        $tx = $this->di['em']->find(Entity\Transaction::class, $id);
        if (!$tx instanceof Entity\Transaction) {
            throw new InformationException('Transaction not found.');
        }
        $payload = $tx->getIpn() ?? [];
        $reference = $tx->getTxnId() ?: ($payload['get']['payment_intent'] ?? $payload['get']['paypal_capture'] ?? $payload['get']['paypal_txn'] ?? null);
        $invoice = $tx->getInvoice();
        $gateway = $tx->getGateway();
        if (!$invoice instanceof Invoice || !$gateway instanceof PayGateway || !is_string($reference) || $reference === '') {
            return ['state' => 'unknown', 'reference' => $reference];
        }
        $adapter = $this->di['mod_service']('Invoice', 'PayGateway')->getPaymentAdapter($gateway, $invoice);
        if (!method_exists($adapter, 'inspectPayment')) {
            return ['state' => 'unknown', 'reference' => $reference];
        }
        $result = $adapter->inspectPayment($reference, (int) $invoice->getId());
        if (isset($result['currency']) && strcasecmp($result['currency'], (string) $invoice->getCurrency()) !== 0) {
            throw new InformationException('The payment currency does not match the invoice.');
        }

        return $result;
    }

    /** Query the provider without changing local payment state. */
    public function inspect(string $id, ?string $reference = null): array
    {
        return $this->context($id, $reference)[3];
    }

    /** Recover the existing payment, never initiate another charge. */
    public function reconcile(string $id, ?string $reference, bool $noPayment, string $note, int $staffId): array
    {
        $db = $this->di['em']->getConnection();
        if ($db->isTransactionActive()) {
            throw new \LogicException('Payment recovery cannot run inside a transaction.');
        }
        $invoiceId = $db->fetchOne('SELECT invoice_id FROM invoice_gateway_charge_attempt WHERE id = ?', [$id]);
        if ($invoiceId === false) {
            throw new InformationException('Payment attempt not found.');
        }
        $lock = 'fb:card-charge:' . (int) $invoiceId;
        if (!NamedLock::acquire($db, $lock, 0)) {
            throw new InformationException('The payment worker is still active. Stop it before recovery.');
        }

        $audit = ['staff_id' => $staffId, 'note' => mb_substr($note, 0, 1000), 'source' => $noPayment ? 'manual' : 'provider'];

        try {
            [$attempt, $invoice, $adapter, $result] = $this->context($id, $reference);
            if ($noPayment && (trim($note) === '' || $attempt['payment_id'] !== null || ($reference !== null && $reference !== '') || $result['state'] !== 'unknown')) {
                throw new InformationException('Manual resolution requires an unknown payment with no recorded reference and a gateway reconciliation note.');
            }
            if (!$noPayment && !in_array($result['state'], ['settled', 'pending', 'failed'], true)) {
                throw new InformationException('The gateway outcome is unknown. Reconcile it with the provider before continuing.');
            }
            $ownerToken = GatewayChargeAttempts::takeOwnership($db, $id, $attempt['owner_token']);

            try {
                \FOSSBilling\Core\Payment\ProcessingFence::charge($db, $id, $ownerToken, function () use ($db, $id, $attempt, $adapter, $result, $noPayment, $audit, $invoiceId): void {
                    if ($noPayment) {
                        GatewayChargeAttempts::resolve($db, $id, 'failed', $audit);
                    } else {
                        $paymentId = (string) $result['reference'];
                        if ($attempt['payment_id'] === null) {
                            GatewayChargeAttempts::recordPayment($db, $id, $paymentId);
                        }
                        $table = $db->quoteSingleIdentifier('transaction');
                        $existing = $db->fetchAssociative("SELECT id, status FROM {$table} WHERE invoice_id = ? AND gateway_id = ? AND txn_id = ? ORDER BY id LIMIT 1", [(int) $invoiceId, (int) $attempt['gateway_id'], $paymentId]);
                        $transactions = $this->di['mod_service']('Invoice', 'Transaction');
                        if ($existing !== false) {
                            if ($existing['status'] === 'processing') {
                                throw new InformationException('Release the interrupted transaction after stopping its worker before reconciling this payment.');
                            }
                            if ($existing['status'] !== 'processed') {
                                $transactions->preProcessTransaction($transactions->getTransactionRepository()->find((int) $existing['id']));
                            }
                        } elseif ($result['state'] !== 'failed') {
                            $transactions->createAndProcess([
                                'invoice_id' => (int) $invoiceId, 'gateway_id' => (int) $attempt['gateway_id'], 'source' => 'saved_card',
                                'get' => [GatewayChargeAttempts::referenceParameter($adapter) => $paymentId, 'invoice_id' => (int) $invoiceId],
                            ]);
                        }
                        if ($result['state'] === 'failed') {
                            GatewayChargeAttempts::resolve($db, $id, 'failed', $audit);
                        } elseif ($result['state'] === 'settled' && $db->fetchOne("SELECT id FROM {$table} WHERE invoice_id = ? AND gateway_id = ? AND txn_id = ? AND status = 'processed'", [(int) $invoiceId, (int) $attempt['gateway_id'], $paymentId]) !== false) {
                            GatewayChargeAttempts::resolve($db, $id, 'settled', $audit);
                        }
                    }
                });
            } catch (\Throwable $exception) {
                $this->di['em']->close();

                throw $exception;
            }
            $this->di['logger']->warning('Staff #{staff} reconciled payment attempt {attempt}: {state}; {note}', [
                'staff' => $staffId, 'attempt' => $id, 'state' => $noPayment ? 'no payment (manual)' : $result['state'], 'note' => $note,
            ]);

            return $result;
        } finally {
            NamedLock::release($db, $lock);
        }
    }
}
