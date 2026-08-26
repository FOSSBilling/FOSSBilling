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

use Box\Mod\Client\Entity\Client;
use Box\Mod\Client\Entity\ClientBalance;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\Subscription;
use Box\Mod\Invoice\Entity\Transaction;
use Box\Mod\Invoice\Repository\TransactionRepository;
use FOSSBilling\Container\InjectionAwareInterface;
use FOSSBilling\System\Environment;

class ServiceTransaction implements InjectionAwareInterface
{
    private const int PROCESSING_RECOVERY_TIMEOUT = 300;

    protected ?\Pimple\Container $di = null;
    private ?bool $transactionIpnHashColumnExists = null;
    private ?TransactionRepository $transactionRepository = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getTransactionRepository(): TransactionRepository
    {
        if ($this->transactionRepository === null) {
            $this->transactionRepository = $this->di['em']->getRepository(Transaction::class);
        }

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
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminTransactionUpdate', 'params' => ['id' => $model->getId()]]);

        if (!empty($data['invoice_id'])) {
            $invoice = $this->di['em']->getRepository(Invoice::class)->find((int) $data['invoice_id']);
            if (!$invoice instanceof Invoice) {
                throw new \FOSSBilling\Exception\InformationException('Invoice not found');
            }
            $model->setInvoice($invoice);
        }
        $model->setTxnId(isset($data['txn_id']) ? (string) $data['txn_id'] : $model->getTxnId());
        $model->setTxnStatus($data['txn_status'] ?? $model->getTxnStatus());
        if (!empty($data['gateway_id'])) {
            $gateway = $this->di['em']->getRepository(PayGateway::class)->find((int) $data['gateway_id']);
            if (!$gateway instanceof PayGateway) {
                throw new \FOSSBilling\Exception\InformationException('Payment gateway not found');
            }
            $model->setGateway($gateway);
        }
        $model->setAmount(isset($data['amount']) ? (string) $data['amount'] : $model->getAmount());
        $model->setCurrency($data['currency'] ?? $model->getCurrency());
        $model->setType($data['type'] ?? $model->getType());
        $model->setNote($data['note'] ?? $model->getNote());
        $model->setStatus($data['status'] ?? $model->getStatus());
        $model->setError($data['error'] ?? $model->getError());
        $model->setErrorCode(isset($data['error_code']) ? (int) $data['error_code'] : $model->getErrorCode());
        $model->setValidateIpn(isset($data['validate_ipn']) ? (bool) $data['validate_ipn'] : $model->isValidateIpn());
        $model->setUpdatedAt(new \DateTime());
        $this->di['em']->flush();
        $this->di['events_manager']->fire(['event' => 'onAfterAdminTransactionUpdate', 'params' => ['id' => $model->getId()]]);

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
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminTransactionCreate', 'params' => $data]);

        $skip_validation = \FOSSBilling\Utils\Normalizer::normalizeBoolean($data['skip_validation'] ?? false);
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

                throw new \FOSSBilling\Exception\InformationException('Invalid payment gateway');
            }
        }
        if (!$skip_validation) {
            if (!isset($data['invoice_id'])) {
                throw new \FOSSBilling\Exception\InformationException('Transaction invoice ID is missing');
            }

            if (!isset($data['gateway_id'])) {
                throw new \FOSSBilling\Exception\InformationException('Payment gateway ID is missing');
            }
            $invoice = $this->di['em']->getRepository(Invoice::class)->find($data['invoice_id']);
            if ($invoice === null) {
                throw new \FOSSBilling\Exception\InformationException('Invoice was not found');
            }
            if ($this->di['em']->getRepository(PayGateway::class)->find((int) $data['gateway_id']) === null) {
                throw new \FOSSBilling\Exception\BaseException('Gateway was not found');
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

        $this->di['events_manager']->fire(['event' => 'onAfterAdminTransactionCreate', 'params' => ['id' => $newId]]);

        return $newId;
    }

    private function supportsTransactionIpnHash(): bool
    {
        if ($this->transactionIpnHashColumnExists !== null) {
            return $this->transactionIpnHashColumnExists;
        }

        try {
            $schemaManager = $this->di['dbal']->createSchemaManager();
            $columns = array_map(static fn ($column) => $column->getName(), $schemaManager->listTableColumns('transaction'));
            $indexes = array_map(static fn ($index) => $index->getName(), $schemaManager->listTableIndexes('transaction'));

            $supported = in_array('ipn_hash', $columns, true) && in_array('transaction_ipn_hash_idx', $indexes, true);
        } catch (\Throwable $e) {
            if (isset($this->di['logger'])) {
                $this->di['logger']->warning('Could not determine whether transaction.ipn_hash exists; disabling IPN hash dedupe: {exception}', ['exception' => $e]);
            }

            return false;
        }

        $this->transactionIpnHashColumnExists = $supported;

        return $this->transactionIpnHashColumnExists;
    }

    public function delete(Transaction $model): bool
    {
        $id = $model->getId();
        $this->di['em']->remove($model);
        $this->di['em']->flush();
        $this->di['logger']->info('Removed transaction #{id}', ['id' => $id]);

        return true;
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
            $result['ipn'] = json_decode($model->getIpn() ?? '', true);
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
        $sql = 'SELECT status, count(id) as counter
            FROM transaction
            GROUP BY status';
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
        $sql = 'SELECT m.*
                FROM transaction as m
                WHERE m.status = :received_status
                    OR (m.status = :processing_status AND (m.updated_at IS NULL OR m.updated_at <= :processing_retry_after))
                ORDER BY m.id DESC';

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
        $affectedRows = $this->di['em']->getConnection()->executeStatement(
            'UPDATE transaction SET status = ?, updated_at = ? WHERE id = ? AND (status IN (?, ?) OR (status = ? AND (updated_at IS NULL OR updated_at <= ?)))',
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

    public function preProcessTransaction(Transaction $model)
    {
        $output = $this->processTransactionWithErrorHandling((int) $model->getId());

        $this->di['events_manager']->fire(['event' => 'onAfterAdminTransactionProcess', 'params' => ['id' => $model->getId()]]);
        $this->di['logger']->info('Processed transaction #{model_id}', ['model_id' => $model->getId()]);

        return !empty($output) ? $output : true;
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
     * @throws \FOSSBilling\Exception\BaseException
     */
    public function processTransaction($id)
    {
        $tx = $this->getTransactionRepository()->find((int) $id);
        if ($tx === null) {
            throw new \FOSSBilling\Exception\BaseException('Transaction :id not found.', ['id' => $id], 404);
        }

        $gtw = $tx->getGateway();
        if (!$gtw instanceof PayGateway) {
            throw new \FOSSBilling\Exception\BaseException('Cannot handle transaction received from unknown payment gateway: :id', [':id' => $tx->getGateway()?->getId()], 704);
        }

        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        $adapter = $payGatewayService->getPaymentAdapter($gtw);
        if (!method_exists($adapter, 'processTransaction')) {
            throw new \FOSSBilling\Exception\BaseException('Payment adapter :adapter does not support action :action', [':adapter' => $gtw->getName(), ':action' => 'processTransaction'], 705);
        }

        $ipn = json_decode($tx->getIpn() ?? '', true);

        return $adapter->processTransaction($this->di['api_system'], (int) $id, $ipn, (int) $gtw->getId());
    }

    public function process(Transaction $tx): Transaction
    {
        $transaction = $this->getTransactionRepository()->find((int) $tx->getId());
        if ($transaction === null) {
            return $tx;
        }

        if ($this->_isProcessed($transaction)) {
            return $transaction;
        }

        try {
            $this->_parseIpnAndApprove($transaction);

            match ($transaction->getType()) {
                \Payment_Transaction::TXTYPE_PAYMENT => $this->_debit($transaction),
                \Payment_Transaction::TXTYPE_REFUND => $this->_refund($transaction),
                \Payment_Transaction::TXTYPE_SUBSCR_CREATE => $this->_subscribe($transaction),
                \Payment_Transaction::TXTYPE_SUBSCR_CANCEL => $this->_unsubscribe($transaction),
                default => throw new \FOSSBilling\Exception\BaseException('Unknown transaction #:id type: :type', [':id' => $transaction->getId(), ':type' => $transaction->getType()], 632),
            };
        } catch (\Exception $e) {
            $transaction->setStatus(Transaction::STATUS_ERROR);
            $transaction->setError($e->getMessage());
            $transaction->setErrorCode((int) $e->getCode());
            $transaction->setUpdatedAt(new \DateTime());
            $this->di['em']->flush();

            if (DEBUG) {
                $this->di['logger']->debug($e->getMessage());
            }
            if (Environment::isTesting()) {
                throw $e;
            }
        }

        return $transaction;
    }

    private function _isProcessed(Transaction $tx): bool
    {
        if ($tx->getStatus() === Transaction::STATUS_PROCESSED) {
            $tx->setError(null);
            $tx->setErrorCode(null);
            $tx->setUpdatedAt(new \DateTime());
            $this->di['em']->flush();

            return true;
        }

        if ($this->hasProcessedTransaction($tx)) {
            $tx->setNote(($tx->getNote() ?? '') . 'Transaction was marked as processed. Transaction with same ID is already processed');
            $tx->setUpdatedAt(new \DateTime());
            $this->di['em']->flush();

            $this->_markAsProcessed($tx);

            return true;
        }

        return false;
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

    private function hasProcessedTransaction(Transaction $tx)
    {
        if (!$tx->getTxnId()) {
            return false;
        }

        $res = $this->getTransactionRepository()->findOneProcessedByTxnId($tx->getTxnId());

        // Return true when a processed transaction with the same txn_id exists.
        return $res !== null;
    }

    private function _markAsProcessed(Transaction $tx): void
    {
        $tx->setError(null);
        $tx->setErrorCode(null);
        $tx->setStatus(Transaction::STATUS_PROCESSED);
        $tx->setUpdatedAt(new \DateTime());
        $this->di['em']->flush();
    }

    private function _parseIpnAndApprove(Transaction &$tx): Transaction
    {
        if ($tx->getStatus() === Transaction::STATUS_APPROVED) {
            return $tx;
        }

        $invoiceService = $this->di['mod_service']('Invoice');
        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');

        $ipn = json_decode($tx->getIpn() ?? '', true) ?? [];

        $gtw = $tx->getGateway();
        if (!$gtw instanceof PayGateway) {
            throw new \FOSSBilling\Exception\BaseException('Could not determine transaction origin. Transaction payment gateway is unknown.', null, 701);
        }

        $adapter = $payGatewayService->getPaymentAdapter($gtw);
        if (!$tx->getInvoice() && method_exists($adapter, 'getInvoiceId')) {
            $adapterInvoiceId = $adapter->getInvoiceId($ipn);
            if ($adapterInvoiceId) {
                $tx->setInvoice($this->di['em']->getRepository(Invoice::class)->find((int) $adapterInvoiceId));
            }
        }

        $invoice = $tx->getInvoice();
        if (!$invoice instanceof Invoice) {
            throw new \FOSSBilling\Exception\BaseException('Transaction :id is not associated with an invoice.', [':id' => $tx->getId()], 702);
        }

        $adapter = $payGatewayService->getPaymentAdapter($gtw, $invoice);
        $mpi = $invoiceService->getPaymentInvoice($invoice);

        if (!Environment::isTesting() && $tx->isValidateIpn()) {
            if (!$adapter->isIpnValid($ipn, $mpi)) {
                $tx->setOutput($adapter->getOutput());

                throw new \FOSSBilling\Exception\BaseException('Instant payment notification (IPN) did not pass gateway :id validation', [':id' => $gtw->getGateway()], 706);
            }
            $tx->setOutput($adapter->getOutput());
        }

        if (!method_exists($adapter, 'getTransaction')) {
            throw new \FOSSBilling\Exception\BaseException('Payment adapter :adapter does not support action :action', [':adapter' => $gtw->getName(), ':action' => 'getTransaction'], 705);
        }

        $response = $adapter->getTransaction($ipn, $mpi);
        if (!$response instanceof \Payment_Transaction) {
            throw new \FOSSBilling\Exception\BaseException('Payment gateway :id method getTransaction should return Payment_Transaction object', [':id' => $gtw->getGateway()], 705);
        }

        // if tx type is already defined, do not set them again
        if ($response->getType()) {
            $tx->setType($response->getType());
        }

        if ($response->getId()) {
            $tx->setTxnId($response->getId());
        }

        if ($response->getStatus()) {
            $tx->setTxnStatus($response->getStatus());
        }

        if ($response->getSubscriptionId()) {
            $tx->setSId($response->getSubscriptionId());
        }

        if ($response->getAmount()) {
            $tx->setAmount((string) $response->getAmount());
        }

        if ($response->getCurrency()) {
            $tx->setCurrency($response->getCurrency());
        }

        $tx->setStatus(Transaction::STATUS_APPROVED);
        $tx->setUpdatedAt(new \DateTime());
        $this->di['em']->flush();

        return $tx;
    }

    private function _debit(Transaction $tx)
    {
        if ($this->_isProcessed($tx)) {
            return $tx;
        }

        $this->_validateApprovedTransaction($tx);

        $this->debitTransaction($tx);

        $this->_markAsProcessed($tx);

        $invoice = $tx->getInvoice();
        if ($invoice instanceof Invoice) {
            try {
                $invoiceService = $this->di['mod_service']('Invoice');
                $invoiceService->tryPayWithCredits($invoice);
            } catch (\Exception $e) {
                if (DEBUG) {
                    $this->di['logger']->debug($e->getMessage());
                }
            }
        }
    }

    private function _refund(Transaction $tx): Transaction
    {
        if ($this->_isProcessed($tx)) {
            return $tx;
        }

        $this->_validateApprovedTransaction($tx);

        $invoice = $tx->getInvoice();
        if (!$invoice instanceof Invoice) {
            throw new \FOSSBilling\Exception\BaseException('Invoice #:id not found', [':id' => $tx->getInvoice()?->getId()], 703);
        }
        $note = sprintf('Transaction %s refund', $tx->getId());

        $invoiceService = $this->di['mod_service']('Invoice');
        $invoiceService->refund($invoice, $note);

        $this->_markAsProcessed($tx);

        return $tx;
    }

    private function _subscribe(Transaction $tx): Transaction
    {
        if ($this->_isProcessed($tx)) {
            return $tx;
        }

        $this->_validateApprovedTransaction($tx);

        if (empty($tx->getSId())) {
            throw new \FOSSBilling\Exception\BaseException('Cannot create subscription. Subscription ID from payment gateway was not received');
        }

        $invoice = $tx->getInvoice();
        if (!$invoice instanceof Invoice) {
            throw new \FOSSBilling\Exception\BaseException('Invoice #:id not found', [':id' => $tx->getInvoice()?->getId()], 703);
        }
        $subscriptionService = $this->di['mod_service']('Invoice', 'Subscription');
        $period = $subscriptionService->getSubscriptionPeriod($invoice);

        $s = new Subscription();
        $s->setClientId($invoice->getClientId() ?? null);
        $s->setPayGateway($tx->getGateway());
        $s->setSid($tx->getSId());
        $s->setPeriod($period);
        $s->setRelType('invoice');
        $s->setRelId($invoice->getId() ?? null);
        $s->setAmount($tx->getAmount());
        $s->setCurrency($invoice->getCurrency());
        $s->setStatus('active');
        $this->di['em']->persist($s);
        $this->di['em']->flush();

        $this->_markAsProcessed($tx);

        return $tx;
    }

    private function _unsubscribe(Transaction $tx): Transaction
    {
        if ($this->_isProcessed($tx)) {
            return $tx;
        }

        $serviceSubscription = $this->di['mod_service']('Invoice', 'Subscription');
        $model = $this->di['em']->getRepository(Subscription::class)->findOneBySid((string) $tx->getSId());
        if (!$model instanceof Subscription) {
            throw new \FOSSBilling\Exception\BaseException('Subscription #:id was not found. Could not unsubscribe', [':id' => $tx->getSId()]);
        }

        $serviceSubscription->unsubscribe($model);

        $this->_markAsProcessed($tx);

        return $tx;
    }

    private function _validateApprovedTransaction(Transaction $tx): void
    {
        if ($tx->getStatus() !== Transaction::STATUS_APPROVED) {
            throw new \FOSSBilling\Exception\BaseException('Only approved transaction can be processed');
        }

        $invoice = $tx->getInvoice();
        if (!$invoice instanceof Invoice) {
            throw new \FOSSBilling\Exception\BaseException('Transaction :id is not associated with an invoice.', [':id' => $tx->getId()], 7022);
        }

        // check that payment currency is correct
        if ($invoice->getCurrency() != $tx->getCurrency()) {
            throw new \FOSSBilling\Exception\BaseException('Transaction currency :code does not match required currency :required', [':code' => $tx->getCurrency(), ':required' => $invoice->getCurrency()], 709);
        }

        // check that payment status is completed if
        if ($tx->getTxnStatus() == \Payment_Transaction::STATUS_PENDING) {
            throw new \FOSSBilling\Exception\BaseException('Transaction status on payment gateway is Pending. Only Complete or Unknown transactions can be processed.', null, 712);
        }
    }

    public function debitTransaction(Transaction $tx): void
    {
        $proforma = $tx->getInvoice();
        if (!$proforma instanceof Invoice) {
            throw new \FOSSBilling\Exception\BaseException('Invoice #:id not found', [':id' => $tx->getInvoice()?->getId()], 703);
        }
        $client = $this->di['em']->getRepository(Client::class)->find($proforma->getClientId());
        if (!$client instanceof Client) {
            throw new \FOSSBilling\Exception\BaseException('Client #:id not found', [':id' => $proforma->getClientId()]);
        }

        if ($client->getCurrency() != $proforma->getCurrency()) {
            throw new \FOSSBilling\Exception\BaseException('Client currency does not match invoice currency');
        }

        // do not debit negative or zero amount
        if ((float) $tx->getAmount() < 0) {
            throw new \FOSSBilling\Exception\BaseException('Cannot add negative amount to client balance for debit transaction');
        }

        $credit = new ClientBalance();
        $credit->setClient($client);
        $credit->setType('transaction');
        $credit->setRelId((string) $tx->getId());
        $credit->setDescription('Invoice #' . $proforma->getId() . ' payment received from transaction #' . $tx->getId());
        $credit->setAmount($tx->getAmount());
        $this->di['em']->persist($credit);
        $this->di['em']->flush();
    }
}
