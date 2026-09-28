<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Invoice;

use Box\Mod\Client\Entity\ClientBalance;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\Transaction;
use Box\Mod\Invoice\Event\AfterAdminTransactionCreateEvent;
use Box\Mod\Invoice\Event\AfterAdminTransactionProcessEvent;
use Box\Mod\Invoice\Event\AfterAdminTransactionUpdateEvent;
use Box\Mod\Invoice\Event\BeforeAdminTransactionCreateEvent;
use Box\Mod\Invoice\Event\BeforeAdminTransactionUpdateEvent;
use Box\Mod\Invoice\Repository\TransactionRepository;
use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\Tools;

class ServiceTransaction implements InjectionAwareInterface
{
    private const int PROCESSING_RECOVERY_TIMEOUT = 300;

    /** @var list<string> */
    private const array CREATE_EVENT_INPUT_FIELDS = [
        'amount', 'currency', 'gateway_id', 'invoice_id', 'skip_validation', 'source', 'txn_id', 'txn_status', 'type',
    ];

    protected ?\Pimple\Container $di = null;
    private ?bool $transactionIpnHashColumnExists = null;
    private ?TransactionRepository $transactionRepository = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getTransactionRepository(): TransactionRepository
    {
        $this->transactionRepository ??= $this->di['em']->getRepository(Transaction::class);

        return $this->transactionRepository;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function processReceivedATransactions(): bool
    {
        $this->di['logger']->info('Executed action to process received transactions');
        $received = $this->getReceived();
        foreach ($received as $transaction) {
            $txId = $transaction['id'] ?? null;
            $model = $this->getTransactionRepository()->find((int) $txId);
            if ($model === null) {
                continue;
            }

            try {
                $this->preProcessTransaction($model);
            } catch (\Throwable) {
                // The individual transaction has already been marked as failed.
                // Continue processing the rest of the batch.
            }
        }

        return true;
    }

    public function update(Transaction $model, array $data): bool
    {
        $transactionId = (int) $model->getId();
        $this->di['event_dispatcher']->dispatch(new BeforeAdminTransactionUpdateEvent($transactionId));

        // A processed transaction records money that already moved. Its
        // history fields are frozen; only operational annotations (note,
        // error, error code) may still be edited.
        if ($model->getStatus() === Transaction::STATUS_PROCESSED) {
            $this->assertNoProcessedHistoryChange($model, $data);
        }

        if (!empty($data['invoice_id'])) {
            $invoice = $this->di['em']->getRepository(Invoice::class)->find((int) $data['invoice_id']);
            if (!$invoice instanceof Invoice) {
                throw new \FOSSBilling\InformationException('Invoice not found');
            }
            $this->assertInvoiceAcceptsTransactions($invoice);
            $model->setInvoice($invoice);
        }
        $model->setTxnId(isset($data['txn_id']) ? (string) $data['txn_id'] : $model->getTxnId());
        $model->setTxnStatus($data['txn_status'] ?? $model->getTxnStatus());
        if (!empty($data['gateway_id'])) {
            $gateway = $this->di['em']->getRepository(PayGateway::class)->find((int) $data['gateway_id']);
            if (!$gateway instanceof PayGateway) {
                throw new \FOSSBilling\InformationException('Payment gateway not found');
            }
            $model->setGateway($gateway);
        }
        $model->setAmount(isset($data['amount']) ? (string) $data['amount'] : $model->getAmount());
        $model->setCurrency($data['currency'] ?? $model->getCurrency());
        $model->setType($data['type'] ?? $model->getType());
        $model->setSId($data['s_id'] ?? $model->getSId());
        $model->setSPeriod($data['s_period'] ?? $model->getSPeriod());
        $model->setNote($data['note'] ?? $model->getNote());
        $model->setStatus($data['status'] ?? $model->getStatus());
        $model->setError($data['error'] ?? $model->getError());
        $model->setErrorCode(isset($data['error_code']) ? (int) $data['error_code'] : $model->getErrorCode());
        $model->setValidateIpn(isset($data['validate_ipn']) ? (bool) $data['validate_ipn'] : $model->isValidateIpn());
        $model->setUpdatedAt(new \DateTime());
        $this->di['em']->flush();
        $this->di['event_dispatcher']->dispatch(new AfterAdminTransactionUpdateEvent($transactionId));

        $this->di['logger']->info('Updated transaction #{model_id}', ['model_id' => $model->getId()]);

        return true;
    }

    public function createAndProcess($ipn): ?int
    {
        $id = $this->create($ipn);

        $tx = $this->getTransactionRepository()->find((int) $id);
        if ($tx === null) {
            return $id;
        }
        if ($tx->getStatus() === Transaction::STATUS_PROCESSED && empty($tx->getError())) {
            return $id;
        }

        // A duplicate IPN delivery may be creating-and-processing the same
        // logical payment concurrently; the loser returns the id untouched.
        if (!$this->claimForProcessing((int) $id)) {
            $this->di['logger']->info('Skipped processing transaction #{id}: already claimed by another worker', ['id' => $id]);

            return $id;
        }

        $this->processTransactionWithErrorHandling((int) $id);

        return $id;
    }

    /**
     * Process a transaction by ID, catching and logging any errors.
     *
     * Used for asynchronous webhook processing where the HTTP response has
     * already been sent (e.g. via fastcgi_finish_request). Ensures errors
     * are recorded on the transaction without propagating to the caller.
     */
    public function processAndCatchErrors(int $id): void
    {
        $tx = $this->getTransactionRepository()->find($id);
        if ($tx === null) {
            return;
        }
        if ($tx->getStatus() === Transaction::STATUS_PROCESSED && empty($tx->getError())) {
            return;
        }

        // Webhook retries can arrive while an earlier delivery is still
        // being processed; the loser leaves the row to its owner.
        if (!$this->claimForProcessing($id)) {
            return;
        }

        try {
            $this->processTransaction($id);
        } catch (\Throwable $e) {
            $this->markTransactionError($id, $e);
        }
    }

    private function processTransactionWithErrorHandling(int $id): mixed
    {
        try {
            return $this->processTransaction($id);
        } catch (\Throwable $e) {
            $this->markTransactionError($id, $e);

            throw $e;
        }
    }

    public function create(array $data): ?int
    {
        $this->di['event_dispatcher']->dispatch(new BeforeAdminTransactionCreateEvent($this->getSafeCreateEventInput($data)));

        $skip_validation = Tools::normalizeBoolean($data['skip_validation'] ?? false);
        if (!empty($data['gateway_id'])) {
            try {
                $gateway = $this->di['em']->getRepository(PayGateway::class)->find((int) $data['gateway_id']);
            } catch (\Exception) {
                $gateway = null;
            }
            if ($gateway === null) {
                if (isset($this->di['logger'])) {
                    $this->di['logger']->warning('IPN with invalid gateway_id rejected: ' . $data['gateway_id']);
                }

                throw new \FOSSBilling\InformationException('Invalid payment gateway');
            }
        }
        if (!$skip_validation) {
            if (!isset($data['invoice_id'])) {
                throw new \FOSSBilling\InformationException('Transaction invoice ID is missing');
            }

            if (!isset($data['gateway_id'])) {
                throw new \FOSSBilling\InformationException('Payment gateway ID is missing');
            }
            $invoice = $this->di['em']->getRepository(Invoice::class)->find($data['invoice_id']);
            if ($invoice === null) {
                throw new \FOSSBilling\InformationException('Invoice was not found');
            }
            if ($this->di['em']->getRepository(PayGateway::class)->find((int) $data['gateway_id']) === null) {
                throw new \FOSSBilling\Exception('Gateway was not found');
            }
        }

        // Early duplicate check: if gateway + external transaction identifier already exists
        // and is processed, return the existing transaction id to ensure idempotency.
        $txnIdCandidate = $data['txn_id']
            ?? ($data['post']['txn_id'] ?? null)
            ?? ($data['get']['txn_id'] ?? null)
            ?? ($data['post']['payment_intent'] ?? null)
            ?? ($data['get']['payment_intent'] ?? null);
        if ($txnIdCandidate && !empty($data['gateway_id'])) {
            $existing = $this->getTransactionRepository()->findOneByTxnIdAndGatewayId((string) $txnIdCandidate, (int) $data['gateway_id']);
            if ($existing !== null && $existing->getStatus() === Transaction::STATUS_PROCESSED) {
                $this->di['logger']->info('Duplicate transaction ignored, returning existing processed transaction #{existing_id}', ['existing_id' => $existing->getId()]);

                return $existing->getId();
            }
        }

        $ipn = [
            'source' => is_string($data['source'] ?? null) ? $data['source'] : null,
            'get' => (isset($data['get']) && is_array($data['get'])) ? $data['get'] : null,
            'post' => (isset($data['post']) && is_array($data['post'])) ? $data['post'] : null,
            'http_raw_post_data' => $data['http_raw_post_data'] ?? null,
            'server' => $data['server'] ?? null,
        ];

        // Fallback dedupe: compute a canonical hash of the IPN payload and
        // look up an existing transaction by (gateway_id, ipn_hash).
        $ipn_hash = $this->ipnHash($ipn);
        $supportsIpnHash = $this->supportsTransactionIpnHash();
        if ($supportsIpnHash && !empty($data['gateway_id']) && !empty($ipn_hash)) {
            $existingByHash = $this->getTransactionRepository()->findOneByGatewayIdAndIpnHash((int) $data['gateway_id'], $ipn_hash);
            if ($existingByHash !== null) {
                $this->di['logger']->info('Duplicate transaction detected by IPN hash, returning existing transaction #{transaction_id}', ['transaction_id' => $existingByHash->getId()]);

                return $existingByHash->getId();
            }
        }

        $transaction = new Transaction();
        if (isset($data['gateway_id'])) {
            $transaction->setGateway($this->di['em']->getRepository(PayGateway::class)->find((int) $data['gateway_id']));
        }
        if (isset($data['invoice_id'])) {
            $transaction->setInvoice($this->di['em']->getRepository(Invoice::class)->find((int) $data['invoice_id']));
        }
        $transaction->setTxnId($data['txn_id'] ?? null);
        if ($supportsIpnHash) {
            $transaction->setIpnHash($ipn_hash ?? null);
        }
        $transaction->setStatus(Transaction::STATUS_RECEIVED);
        $transaction->setIp($this->di['request']->getClientIp());
        $transaction->setIpn(json_encode($ipn) ?: null);
        $transaction->setNote($data['note'] ?? null);
        $this->di['em']->persist($transaction);
        $this->di['em']->flush();
        $newId = (int) $transaction->getId();

        $this->di['logger']->info('Received transaction {transaction_id} from payment gateway {gateway_id}', ['transaction_id' => $newId, 'gateway_id' => $transaction->getGateway()?->getId()]);

        $this->di['event_dispatcher']->dispatch(new AfterAdminTransactionCreateEvent($newId));

        return $newId;
    }

    private function supportsTransactionIpnHash(): bool
    {
        if ($this->transactionIpnHashColumnExists !== null) {
            return $this->transactionIpnHashColumnExists;
        }

        try {
            $schemaManager = $this->di['dbal']->createSchemaManager();
            $table = $schemaManager->introspectTableByUnquotedName('transaction');
            $supported = $table->hasColumn('ipn_hash') && $table->hasIndex('transaction_ipn_hash_idx');
        } catch (\Throwable $e) {
            if (isset($this->di['logger'])) {
                $this->di['logger']->warning('Could not determine whether transaction.ipn_hash exists; disabling IPN hash dedupe: {exception}', ['exception' => $e]);
            }

            return false;
        }

        $this->transactionIpnHashColumnExists = $supported;

        return $this->transactionIpnHashColumnExists;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, bool|float|int|string|null>
     */
    private function getSafeCreateEventInput(array $data): array
    {
        $input = [];
        foreach (self::CREATE_EVENT_INPUT_FIELDS as $field) {
            if (array_key_exists($field, $data) && ($data[$field] === null || is_scalar($data[$field]))) {
                $input[$field] = $data[$field];
            }
        }

        return $input;
    }

    public function delete(Transaction $model): bool
    {
        $id = $model->getId();
        // A processed transaction records money that already moved (including
        // any client-balance credit written when it was debited). Deleting it
        // would rewrite financial history and orphan those rows.
        if ($model->getStatus() === Transaction::STATUS_PROCESSED) {
            throw new \FOSSBilling\InformationException('Processed transactions cannot be deleted because they record money that already moved.');
        }
        // Unprocessed transactions move no money, but drop any balance rows
        // that reference them so no orphans remain.
        $balances = $this->di['em']->getRepository(ClientBalance::class)->findBy(['type' => 'transaction', 'relId' => (string) $id]);
        foreach ($balances as $balance) {
            $this->di['em']->remove($balance);
        }
        $this->di['em']->remove($model);
        $this->di['em']->flush();
        $this->di['logger']->info('Removed transaction #{id}', ['id' => $id]);

        return true;
    }

    /**
     * Refuse changes to a processed transaction's money/history fields.
     * Only operational annotations may still be edited.
     */
    private function assertNoProcessedHistoryChange(Transaction $model, array $data): void
    {
        // Forms round-trip API renderings: '42.5' for a stored DECIMAL '42.50', '' for
        // nulls. Compare amounts numerically at 2dp and treat '' as null so
        // re-submitting unchanged values is not flagged as a history change.
        $norm = static fn ($v): ?string => ($v === null || $v === '') ? null : (string) $v;
        $differs = static fn (string $key, $current): bool => array_key_exists($key, $data) && $norm($data[$key]) !== $norm($current);
        $changed = [];
        if (!empty($data['invoice_id']) && (int) $data['invoice_id'] !== (int) $model->getInvoice()?->getId()) {
            $changed[] = 'invoice_id';
        }
        if (array_key_exists('amount', $data) && $norm($data['amount']) !== null
            && number_format((float) $data['amount'], 2, '.', '') !== number_format((float) $model->getAmount(), 2, '.', '')) {
            $changed[] = 'amount';
        }
        if ($differs('currency', $model->getCurrency())) {
            $changed[] = 'currency';
        }
        if (!empty($data['gateway_id']) && (int) $data['gateway_id'] !== (int) $model->getGateway()?->getId()) {
            $changed[] = 'gateway_id';
        }
        if ($differs('type', $model->getType())) {
            $changed[] = 'type';
        }
        if ($differs('txn_id', $model->getTxnId())) {
            $changed[] = 'txn_id';
        }
        if ($differs('txn_status', $model->getTxnStatus())) {
            $changed[] = 'txn_status';
        }
        if ($differs('s_id', $model->getSId())) {
            $changed[] = 's_id';
        }
        if ($differs('s_period', $model->getSPeriod())) {
            $changed[] = 's_period';
        }
        if ($differs('status', $model->getStatus())) {
            $changed[] = 'status';
        }
        if ($changed !== []) {
            throw new \FOSSBilling\InformationException('Processed transactions cannot change :fields because they record money that already moved.', [':fields' => implode(', ', $changed)]);
        }
    }

    /**
     * Refuse to link a transaction to an invoice that can no longer accept
     * payments. Paid invoices are allowed: a payment arriving for an invoice
     * that was just paid concurrently is credited to the client balance.
     */
    private function assertInvoiceAcceptsTransactions(Invoice $invoice): void
    {
        if ($invoice->getStatus() === Invoice::STATUS_CANCELED
            || $invoice->getStatus() === Invoice::STATUS_REFUNDED
            || $invoice->getReplacedByInvoiceId() !== null
        ) {
            throw new \FOSSBilling\InformationException('Transactions cannot be linked to a canceled, refunded, or replaced invoice.');
        }
    }

    public function toApiArray(Transaction $model, $deep = false, $identity = null): array
    {
        $gateway = null;
        $gtw = $model->getGateway();
        if ($gtw instanceof PayGateway) {
            $gateway = $gtw->getName();
        }

        $result = [
            'id' => $model->getId(),
            'invoice_id' => $model->getInvoice()?->getId(),
            'txn_id' => $model->getTxnId(),
            'txn_status' => $model->getTxnStatus(),
            'gateway_id' => $model->getGateway()?->getId(),
            'gateway' => $gateway,
            'amount' => (float) ($model->getAmount() ?? 0),
            'currency' => $model->getCurrency(),
            'type' => $model->getType(),
            'status' => $model->getStatus(),
            'ip' => $model->getIp(),
            'validate_ipn' => $model->isValidateIpn(),
            'error' => $model->getError(),
            'error_code' => $model->getErrorCode(),
            'note' => $model->getNote(),
            'created_at' => $model->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $model->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
        if ($deep) {
            $result['ipn'] = json_decode($model->getIpn() ?? '', true) ?? [];
        }

        return $result;
    }

    /**
     * Convert a transaction list result into the API shape without loading the
     * transaction's gateway again.
     *
     * The gateway name is provided by the list query itself (a LEFT JOIN to
     * `pay_gateway`), avoiding the per-row lookup that `toApiArray()` performs.
     */
    public function transactionResultToApiArray(Transaction $transaction, ?string $gateway): array
    {
        return [
            'id' => $transaction->getId(),
            'invoice_id' => $transaction->getInvoice()?->getId(),
            'txn_id' => $transaction->getTxnId(),
            'txn_status' => $transaction->getTxnStatus(),
            'gateway_id' => $transaction->getGateway()?->getId(),
            'gateway' => $gateway,
            'amount' => (float) ($transaction->getAmount() ?? 0),
            'currency' => $transaction->getCurrency(),
            'type' => $transaction->getType(),
            'status' => $transaction->getStatus(),
            'ip' => $transaction->getIp(),
            'validate_ipn' => $transaction->isValidateIpn(),
            'error' => $transaction->getError(),
            'error_code' => $transaction->getErrorCode(),
            'note' => $transaction->getNote(),
            'created_at' => $transaction->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $transaction->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    public function counter(): array
    {
        // `transaction` is a reserved word (bare use is a syntax error on SQLite): quote it.
        $table = $this->di['em']->getConnection()->quoteSingleIdentifier('transaction');
        $sql = "SELECT status, count(id) as counter
            FROM {$table}
            GROUP BY status";
        $rows = $this->di['em']->getConnection()->fetchAllAssociative($sql);
        $data = [];
        foreach ($rows as $row) {
            $data[$row['status']] = $row['counter'];
        }

        return [
            'total' => array_sum($data),
            Transaction::STATUS_RECEIVED => $data[Transaction::STATUS_RECEIVED] ?? 0,
            Transaction::STATUS_APPROVED => $data[Transaction::STATUS_APPROVED] ?? 0,
            Transaction::STATUS_PROCESSING => $data[Transaction::STATUS_PROCESSING] ?? 0,
            Transaction::STATUS_PROCESSED => $data[Transaction::STATUS_PROCESSED] ?? 0,
            Transaction::STATUS_ERROR => $data[Transaction::STATUS_ERROR] ?? 0,
        ];
    }

    public function getStatusPairs(): array
    {
        return [
            Transaction::STATUS_RECEIVED => 'Received',
            Transaction::STATUS_APPROVED => 'Approved',
            Transaction::STATUS_PROCESSING => 'Processing',
            Transaction::STATUS_PROCESSED => 'Processed',
            Transaction::STATUS_ERROR => 'Error',
        ];
    }

    public function getStatuses(): array
    {
        return [
            Transaction::STATUS_RECEIVED => 'Received',
            Transaction::STATUS_APPROVED => 'Approved/Verified',
            Transaction::STATUS_PROCESSING => 'Processing',
            Transaction::STATUS_PROCESSED => 'Processed',
            Transaction::STATUS_ERROR => 'Error',
        ];
    }

    public function getGatewayStatuses(): array
    {
        return [
            \Payment_Transaction::STATUS_SUCCEEDED => 'Succeeded',
            \Payment_Transaction::STATUS_COMPLETE => 'Complete',
            \Payment_Transaction::STATUS_PENDING => 'Pending validation',
            \Payment_Transaction::STATUS_FAILED => 'Failed',
            \Payment_Transaction::STATUS_UNKNOWN => 'Unknown',
        ];
    }

    public function getTypes(): array
    {
        return [
            \Payment_Transaction::TXTYPE_PAYMENT => 'Payment',
            \Payment_Transaction::TXTYPE_REFUND => 'Refund',
            \Payment_Transaction::TXTYPE_SUBSCR_CREATE => 'Subscription create',
            \Payment_Transaction::TXTYPE_SUBSCR_CANCEL => 'Subscription cancel',
            \Payment_Transaction::TXTYPE_UNKNOWN => 'Unknown',
        ];
    }

    public function getReceived()
    {
        // `transaction` is a reserved word (bare use is a syntax error on SQLite): quote it.
        $table = $this->di['em']->getConnection()->quoteSingleIdentifier('transaction');
        $sql = "SELECT m.*
                FROM {$table} as m
                WHERE m.status = :received_status
                    OR (m.status = :processing_status AND (m.updated_at IS NULL OR m.updated_at <= :processing_retry_after))
                ORDER BY m.id DESC";

        return $this->di['em']->getConnection()->fetchAllAssociative($sql, [
            'received_status' => Transaction::STATUS_RECEIVED,
            'processing_status' => Transaction::STATUS_PROCESSING,
            'processing_retry_after' => $this->getProcessingRecoveryThreshold(),
        ]);
    }

    private function getProcessingRecoveryThreshold(): string
    {
        return date('Y-m-d H:i:s', time() - self::PROCESSING_RECOVERY_TIMEOUT);
    }

    /**
     * Atomically claim a transaction for processing.
     * Uses conditional UPDATE to prevent race conditions when multiple
     * workers attempt to process the same transaction simultaneously.
     *
     * Accepts 'received' status immediately, allows stale 'processing'
     * transactions to be reclaimed after the recovery timeout, and allows
     * 'error' transactions to be retried (e.g. via the admin Process button
     * or PayPal IPN retries).
     *
     * @param int $id Transaction ID
     *
     * @return bool True if the transaction was successfully claimed, false if already being processed
     */
    public function claimForProcessing(int $id): bool
    {
        $connection = $this->di['em']->getConnection();
        // `transaction` is a reserved word: quote it portably, or the claim is a
        // syntax error on SQLite and no payment can complete there.
        $table = $connection->quoteSingleIdentifier('transaction');
        $affectedRows = $connection->executeStatement(
            "UPDATE {$table} SET status = ?, updated_at = ? WHERE id = ? AND (status IN (?, ?) OR (status = ? AND (updated_at IS NULL OR updated_at <= ?)))",
            [
                Transaction::STATUS_PROCESSING,
                date('Y-m-d H:i:s'),
                $id,
                Transaction::STATUS_RECEIVED,
                Transaction::STATUS_ERROR,
                Transaction::STATUS_PROCESSING,
                $this->getProcessingRecoveryThreshold(),
            ]
        );

        return $affectedRows > 0;
    }

    public function preProcessTransaction(Transaction $model): bool
    {
        // Serialize concurrent processing attempts on the atomic claim: a
        // double-clicked Process button or overlapping cron runs converge on
        // a single processor instead of invoking the gateway adapter twice.
        if (!$this->claimForProcessing((int) $model->getId())) {
            $this->di['logger']->info('Skipped processing transaction #{id}: already claimed by another worker', ['id' => $model->getId()]);

            return true;
        }

        // Processing failures throw, so reaching this point means the
        // transaction was handled successfully regardless of what the
        // gateway adapter itself returns (some return void).
        $this->processTransactionWithErrorHandling((int) $model->getId());

        $this->di['event_dispatcher']->dispatch(new AfterAdminTransactionProcessEvent((int) $model->getId()));
        $this->di['logger']->info('Processed transaction #{model_id}', ['model_id' => $model->getId()]);

        return true;
    }

    /**
     * Mark a transaction as errored due to a processing failure.
     *
     * Reloads the transaction from the database so the status is current, and
     * only marks it as errored if it has not already been processed, ensuring
     * a successful processing is never clobbered by a stale exception.
     */
    private function markTransactionError(int $id, \Throwable $e): void
    {
        $tx = $this->getTransactionRepository()->find($id);
        if ($tx === null) {
            return;
        }

        $this->di['em']->refresh($tx);
        if ($tx->getStatus() === Transaction::STATUS_PROCESSED) {
            return;
        }

        $tx->setStatus(Transaction::STATUS_ERROR);
        $tx->setError($e->getMessage());
        $tx->setErrorCode((int) $e->getCode());
        $tx->setUpdatedAt(new \DateTime());
        $this->di['em']->flush();

        $this->di['logger']->error('Failed to process transaction #{id}: {exception}', ['id' => $id, 'exception' => $e]);
    }

    /**
     * New simplified transaction processing logic.
     *
     * @since 2.9.11
     *
     * @param int $id
     *
     * @throws \FOSSBilling\Exception
     */
    /**
     * Dispatch a transaction to its payment adapter. This is the single funnel all adapter
     * invocations pass through, and every caller (preProcessTransaction, createAndProcess,
     * processAndCatchErrors) holds the processing claim on entry — adapters must not
     * re-claim the row, or the claim fails and the payment is silently skipped.
     */
    public function processTransaction($id)
    {
        $tx = $this->getTransactionRepository()->find((int) $id);
        if ($tx === null) {
            throw new \FOSSBilling\Exception('Transaction :id not found.', ['id' => $id], 404);
        }

        $gtw = $tx->getGateway();
        if (!$gtw instanceof PayGateway) {
            throw new \FOSSBilling\Exception('Cannot handle transaction received from unknown payment gateway: :id', [':id' => $tx->getGateway()?->getId()], 704);
        }

        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        $adapter = $payGatewayService->getPaymentAdapter($gtw);
        if (!method_exists($adapter, 'processTransaction')) {
            throw new \FOSSBilling\Exception('Payment adapter :adapter does not support action :action', [':adapter' => $gtw->getName(), ':action' => 'processTransaction'], 705);
        }

        $ipn = json_decode($tx->getIpn() ?? '', true);

        return $adapter->processTransaction($this->di['api_system'], (int) $id, $ipn, (int) $gtw->getId());
    }

    /**
     * Recursively sort array keys to produce a deterministic representation.
     */
    private function recursiveKsort($arr)
    {
        if (!is_array($arr)) {
            return $arr;
        }

        foreach ($arr as $k => $v) {
            if (is_array($v)) {
                $arr[$k] = $this->recursiveKsort($v);
            }
        }

        ksort($arr);

        return $arr;
    }

    /**
     * Normalize IPN payload into canonical JSON string.
     */
    private function normalizeIpn($ipn)
    {
        if (!is_array($ipn)) {
            return '';
        }

        $sorted = $this->recursiveKsort($ipn);

        return json_encode($sorted, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Compute SHA-256 hash of normalized IPN payload.
     */
    private function ipnHash($ipn): ?string
    {
        $norm = $this->normalizeIpn($ipn);
        if (empty($norm)) {
            return null;
        }

        return hash('sha256', (string) $norm);
    }
}
