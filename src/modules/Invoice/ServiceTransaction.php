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

use Box\Mod\Client\Entity\Client as ClientEntity;
use Box\Mod\Client\Entity\ClientBalance;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\Subscription;
use Box\Mod\Invoice\Entity\Transaction;
use Box\Mod\Invoice\Repository\InvoiceRepository;
use Box\Mod\Invoice\Repository\PayGatewayRepository;
use Box\Mod\Invoice\Repository\SubscriptionRepository;
use Box\Mod\Invoice\Repository\TransactionRepository;
use FOSSBilling\Environment;
use FOSSBilling\InformationException;
use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\Tools;

class ServiceTransaction implements InjectionAwareInterface
{
    private const int PROCESSING_RECOVERY_TIMEOUT = 300;

    protected ?\Pimple\Container $di = null;
    private ?bool $transactionIpnHashColumnExists = null;
    private ?TransactionRepository $transactionRepository = null;
    private ?InvoiceRepository $invoiceRepository = null;
    private ?PayGatewayRepository $payGatewayRepository = null;
    private ?SubscriptionRepository $subscriptionRepository = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
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
            $model = $this->getTransactionRepository()->find((int) $txId)
                ?? throw new InformationException('Transaction not found');
            $this->preProcessTransaction($model);
        }

        return true;
    }

    public function update(Transaction $model, array $data): bool
    {
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminTransactionUpdate', 'params' => ['id' => $model->getId()]]);

        $model->invoice_id = $data['invoice_id'] ?? $model->getInvoiceId();
        $model->txn_id = $data['txn_id'] ?? $model->getTxnId();
        $model->txn_status = $data['txn_status'] ?? $model->getTxnStatus();
        $model->gateway_id = $data['gateway_id'] ?? $model->getGatewayId();
        $model->amount = $data['amount'] ?? $model->getAmount();
        $model->currency = $data['currency'] ?? $model->getCurrency();
        $model->type = $data['type'] ?? $model->getType();
        $model->note = $data['note'] ?? $model->getNote();
        $model->status = $data['status'] ?? $model->getStatus();
        $model->error = $data['error'] ?? $model->getError();
        $model->error_code = $data['error_code'] ?? $model->getErrorCode();
        $model->validate_ipn = $data['validate_ipn'] ?? $model->isValidateIpn();
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['em']->persist($model);
        $this->di['em']->flush();
        $this->di['events_manager']->fire(['event' => 'onAfterAdminTransactionUpdate', 'params' => ['id' => $model->getId()]]);

        $this->di['logger']->info('Updated transaction #%s', $model->getId());

        return true;
    }

    public function createAndProcess($ipn)
    {
        $id = $this->create($ipn);

        $tx = $this->getTransactionRepository()->find($id)
            ?? throw new InformationException('Transaction not found');
        if ($tx->getStatus() === Transaction::STATUS_PROCESSED && empty($tx->getError())) {
            return $id;
        }

        try {
            $this->processTransaction($id);
        } catch (\Throwable $e) {
            $this->markTransactionError($id, $e);

            throw $e;
        }

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
        $tx = $this->getTransactionRepository()->find($id)
            ?? throw new InformationException('Transaction not found');
        if ($tx->getStatus() === Transaction::STATUS_PROCESSED && empty($tx->getError())) {
            return;
        }

        try {
            $this->processTransaction($id);
        } catch (\Throwable $e) {
            $this->markTransactionError($id, $e);
        }
    }

    public function create(array $data)
    {
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminTransactionCreate', 'params' => $data]);

        $skip_validation = Tools::normalizeBoolean($data['skip_validation'] ?? false);
        if (!empty($data['gateway_id'])) {
            try {
                $this->getPayGatewayRepository()->find((int) $data['gateway_id'])
                    ?? throw new InformationException('Gateway was not found');
            } catch (\Exception) {
                if (isset($this->di['logger'])) {
                    $this->di['logger']->warning('IPN with invalid gateway_id rejected: ' . $data['gateway_id']);
                }

                throw new InformationException('Invalid payment gateway');
            }
        }
        if (!$skip_validation) {
            if (!isset($data['invoice_id'])) {
                throw new InformationException('Transaction invoice ID is missing');
            }

            if (!isset($data['gateway_id'])) {
                throw new InformationException('Payment gateway ID is missing');
            }
            $this->getInvoiceRepository()->find((int) $data['invoice_id'])
                ?? throw new InformationException('Invoice was not found');
            $this->getPayGatewayRepository()->find((int) $data['gateway_id'])
                ?? throw new InformationException('Gateway was not found');
        }

        // Early duplicate check: if gateway + external transaction identifier already exists
        // and is processed, return the existing transaction id to ensure idempotency.
        $txnIdCandidate = $data['txn_id']
            ?? ($data['post']['txn_id'] ?? null)
            ?? ($data['get']['txn_id'] ?? null)
            ?? ($data['post']['payment_intent'] ?? null)
            ?? ($data['get']['payment_intent'] ?? null);
        if ($txnIdCandidate && !empty($data['gateway_id'])) {
            $existing = $this->di['em']->getRepository(Transaction::class)->findOneBy(['txnId' => $txnIdCandidate, 'gatewayId' => (int) $data['gateway_id']]);
            if ($existing instanceof Transaction && $existing->getStatus() === Transaction::STATUS_PROCESSED) {
                $this->di['logger']->info('Duplicate transaction ignored, returning existing processed transaction #%s', $existing->getId());

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
            $existingByHash = $this->di['em']->getRepository(Transaction::class)->findOneBy(['gatewayId' => (int) $data['gateway_id'], 'ipnHash' => $ipn_hash]);
            if ($existingByHash instanceof Transaction) {
                $this->di['logger']->info('Duplicate transaction detected by IPN hash, returning existing transaction #%s', $existingByHash->getId());

                return $existingByHash->getId();
            }
        }

        $transaction = new Transaction();
        $transaction->setGatewayId(isset($data['gateway_id']) ? (int) $data['gateway_id'] : null);
        $transaction->setInvoiceId(isset($data['invoice_id']) ? (int) $data['invoice_id'] : null);
        $transaction->setTxnId($data['txn_id'] ?? null);
        if ($supportsIpnHash) {
            $transaction->setIpnHash($ipn_hash ?? null);
        }
        $transaction->setStatus('received');
        $transaction->setIp($this->di['request']->getClientIp());
        $transaction->setIpn(json_encode($ipn));
        $transaction->setNote($data['note'] ?? null);
        $this->di['em']->persist($transaction);
        $this->di['em']->flush();
        $newId = $transaction->getId();

        $this->di['logger']->info('Received transaction %s from payment gateway %s', $newId, $transaction->getGatewayId());

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
                $this->di['logger']->warning('Could not determine whether transaction.ipn_hash exists; disabling IPN hash dedupe: %s', $e->getMessage());
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
        $this->di['logger']->info('Removed transaction #%s', $id);

        return true;
    }

    public function toApiArray(Transaction $model, $deep = false, $identity = null): array
    {
        $gateway = null;
        if ($model->getGatewayId() ?? $model->getGatewayId() ?? false) {
            $gId = $model instanceof Transaction ? $model->getGatewayId() : $model->gateway_id;
            $gtw = $this->getPayGatewayRepository()->find($gId);
            if ($gtw instanceof PayGateway) {
                $gateway = $gtw->getName();
            }
        }

        $result = [
            'id' => $model instanceof Transaction ? $model->getId() : $model->id,
            'invoice_id' => $model instanceof Transaction ? $model->getInvoiceId() : $model->invoice_id,
            'txn_id' => $model instanceof Transaction ? $model->getTxnId() : $model->txn_id,
            'txn_status' => $model instanceof Transaction ? $model->getTxnStatus() : $model->txn_status,
            'gateway_id' => $model instanceof Transaction ? $model->getGatewayId() : $model->gateway_id,
            'gateway' => $gateway,
            'amount' => (float) (($model instanceof Transaction ? $model->getAmount() : $model->amount) ?? 0),
            'currency' => $model instanceof Transaction ? $model->getCurrency() : $model->currency,
            'type' => $model instanceof Transaction ? $model->getType() : $model->type,
            'status' => $model instanceof Transaction ? $model->getStatus() : $model->status,
            'ip' => $model instanceof Transaction ? $model->getIp() : $model->ip,
            'validate_ipn' => $model instanceof Transaction ? $model->isValidateIpn() : $model->validate_ipn,
            'error' => $model instanceof Transaction ? $model->getError() : $model->error,
            'error_code' => $model instanceof Transaction ? $model->getErrorCode() : $model->error_code,
            'note' => $model instanceof Transaction ? $model->getNote() : $model->note,
            'created_at' => $model instanceof Transaction ? $model->getCreatedAt() : $model->created_at,
            'updated_at' => $model instanceof Transaction ? $model->getUpdatedAt() : $model->updated_at,
        ];
        if ($deep) {
            $ipn = $model instanceof Transaction ? $model->getIpn() : $model->ipn;
            $result['ipn'] = json_decode($ipn ?? '', true);
        }

        return $result;
    }

    /**
     * Convert a transaction search result without loading its model and gateway again.
     */
    public function searchResultToApiArray(array $row): array
    {
        return [
            'id' => $row['id'],
            'invoice_id' => $row['invoice_id'],
            'txn_id' => $row['txn_id'],
            'txn_status' => $row['txn_status'],
            'gateway_id' => $row['gateway_id'],
            'gateway' => $row['gateway'] ?? null,
            'amount' => (float) ($row['amount'] ?? 0),
            'currency' => $row['currency'],
            'type' => $row['type'],
            'status' => $row['status'],
            'ip' => $row['ip'],
            'validate_ipn' => $row['validate_ipn'],
            'error' => $row['error'],
            'error_code' => $row['error_code'],
            'note' => $row['note'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    public function getSearchQuery(array $data): array
    {
        $sql = 'SELECT m.*, pg.name AS gateway
                FROM transaction as m
                LEFT JOIN invoice as i on m.invoice_id = i.id
                LEFT JOIN pay_gateway as pg on m.gateway_id = pg.id
                WHERE 1 ';

        $id = $data['id'] ?? null;
        $search = $data['search'] ?? null;
        $invoice_hash = $data['invoice_hash'] ?? null;
        $invoice_id = $data['invoice_id'] ?? null;
        $gateway_id = $data['gateway_id'] ?? null;
        $client_id = $data['client_id'] ?? null;
        $status = $data['status'] ?? null;
        $currency = $data['currency'] ?? null;
        $type = $data['type'] ?? null;
        $txn_id = $data['txn_id'] ?? null;

        $date_from = $data['date_from'] ?? null;
        $date_to = $data['date_to'] ?? null;

        $params = [];
        if ($id) {
            $sql .= ' AND m.id = :id';
            $params['id'] = $id;
        }

        if ($status) {
            $sql .= ' AND m.status = :status';
            $params['status'] = $status;
        }

        if ($invoice_hash) {
            $sql .= ' AND i.hash = :hash';
            $params['hash'] = $invoice_hash;
        }

        if ($invoice_id) {
            $sql .= ' AND m.invoice_id = :invoice_id';
            $params['invoice_id'] = $invoice_id;
        }

        if ($gateway_id) {
            $sql .= ' AND m.gateway_id = :gateway_id';
            $params['gateway_id'] = $gateway_id;
        }

        if ($client_id) {
            $sql .= ' AND i.client_id = :client_id';
            $params['client_id'] = $client_id;
        }

        if ($currency) {
            $sql .= ' AND m.currency = :currency';
            $params['currency'] = $currency;
        }

        if ($type) {
            $sql .= ' AND m.type = :type';
            $params['type'] = $type;
        }

        if ($txn_id) {
            $sql .= ' AND m.txn_id = :txn_id';
            $params['txn_id'] = $txn_id;
        }

        if ($date_from) {
            $sql .= ' AND UNIX_TIMESTAMP(m.created_at) >= :date_from';
            $params['date_from'] = strtotime((string) $date_from);
        }

        if ($date_to) {
            $sql .= ' AND UNIX_TIMESTAMP(m.created_at) <= :date_to';
            $params['date_to'] = strtotime((string) $date_to);
        }

        if ($search) {
            $sql .= ' AND (m.note LIKE :note OR m.invoice_id LIKE :search_invoice_id OR m.txn_id LIKE :search_txn_id OR m.ipn LIKE :ipn)';
            $params['note'] = "%$search%";
            $params['search_invoice_id'] = "%$search%";
            $params['search_txn_id'] = "%$search%";
            $params['ipn'] = "%$search%";
        }

        $sql .= ' ORDER BY m.id DESC';

        return [$sql, $params];
    }

    public function counter(): array
    {
        $sql = 'SELECT status, count(id) as counter
            FROM transaction
            GROUP BY status';
        $rows = $this->di['dbal']->fetchAllAssociative($sql);
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

        return $this->di['dbal']->fetchAllAssociative($sql, [
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
        $affectedRows = $this->di['dbal']->executeStatement(
            'UPDATE transaction SET status = :status, updated_at = :updated_at WHERE id = :id AND (status IN (:received, :error_status) OR (status = :processing AND (updated_at IS NULL OR updated_at <= :threshold)))',
            [
                'status' => Transaction::STATUS_PROCESSING,
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $id,
                'received' => Transaction::STATUS_RECEIVED,
                'error_status' => Transaction::STATUS_ERROR,
                'processing' => Transaction::STATUS_PROCESSING,
                'threshold' => $this->getProcessingRecoveryThreshold(),
            ]
        );

        return $affectedRows > 0;
    }

    public function preProcessTransaction(Transaction $model)
    {
        try {
            $output = $this->processTransaction($model->getId());
        } catch (\Throwable $e) {
            $this->markTransactionError((int) $model->getId(), $e);

            throw $e;
        }

        $this->di['events_manager']->fire(['event' => 'onAfterAdminTransactionProcess', 'params' => ['id' => $model->getId()]]);
        $this->di['logger']->info('Processed transaction #%s', $model->getId());

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
        if (!$tx instanceof Transaction || $tx->getStatus() === Transaction::STATUS_PROCESSED) {
            return;
        }

        $tx->setStatus(Transaction::STATUS_ERROR);
        $tx->setError($e->getMessage());
        $tx->setErrorCode($e->getCode());
        $tx->setUpdatedAt(new \DateTime());
        $this->di['em']->persist($tx);
        $this->di['em']->flush();

        $this->di['logger']->error('Failed to process transaction #%s: %s', $id, $e->getMessage());
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
    public function processTransaction($id)
    {
        /** @var Transaction|null $tx */
        $tx = $this->getTransactionRepository()->find($id);
        if (!$tx) {
            throw new \FOSSBilling\Exception('Transaction :id not found.', ['id' => $id], 404);
        }

        $gatewayId = $tx->getGatewayId();
        if (empty($gatewayId)) {
            throw new \FOSSBilling\Exception('Could not determine transaction origin. Transaction payment gateway is unknown.', null, 701);
        }

        $gtw = $this->getPayGatewayRepository()->find($gatewayId);
        if (!$gtw instanceof PayGateway) {
            throw new \FOSSBilling\Exception('Cannot handle transaction received from unknown payment gateway: :id', [':id' => $gatewayId], 704);
        }

        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        $adapter = $payGatewayService->getPaymentAdapter($gtw);
        if (!method_exists($adapter, 'processTransaction')) {
            throw new \FOSSBilling\Exception('Payment adapter :adapter does not support action :action', [':adapter' => $gtw->getName(), ':action' => 'processTransaction'], 705);
        }

        $ipn = json_decode($tx->getIpn() ?? '', true);

        return $adapter->processTransaction($this->di['api_system'], (int) $id, $ipn, $gatewayId);
    }

    public function process(Transaction $tx): Transaction
    {
        $id = $tx instanceof Transaction ? $tx->getId() : $tx->id;
        $transaction = $this->getTransactionRepository()->find($id);

        if ($this->_isProcessed($transaction)) {
            return $transaction;
        }

        try {
            $this->_parseIpnAndApprove($transaction);

            $type = $transaction->getType();
            if ($type === \Payment_Transaction::TXTYPE_PAYMENT) {
                $this->_debit($transaction);
            } elseif ($type === \Payment_Transaction::TXTYPE_REFUND) {
                $this->_refund($transaction);
            } elseif ($type === \Payment_Transaction::TXTYPE_SUBSCR_CREATE) {
                $this->_subscribe($transaction);
            } elseif ($type === \Payment_Transaction::TXTYPE_SUBSCR_CANCEL) {
                $this->_unsubscribe($transaction);
            } else {
                throw new \FOSSBilling\Exception('Unknown transaction #:id type: :type', [':id' => $transaction->getId(), ':type' => $type], 632);
            }
        } catch (\Exception $e) {
            $transaction->setStatus(Transaction::STATUS_ERROR);
            $transaction->setError($e->getMessage());
            $transaction->setErrorCode($e->getCode());
            $transaction->setUpdatedAt(new \DateTime());
            $this->di['em']->persist($transaction);
            $this->di['em']->flush();

            if (defined('DEBUG')) {
                error_log($e->getMessage());
            }
            if (Environment::isTesting()) {
                throw $e;
            }
        }

        return $transaction;
    }

    private function _isProcessed(Transaction $tx): bool
    {
        if ($tx->getStatus() == Transaction::STATUS_PROCESSED) {
            $tx->setError(null);
            $tx->setErrorCode(null);
            $tx->setUpdatedAt(new \DateTime());
            $this->di['em']->persist($tx);
            $this->di['em']->flush();

            return true;
        }

        if ($this->hasProcessedTransaction($tx)) {
            $tx->setNote(($tx->getNote() ?? '') . 'Transaction was marked as processed. Transaction with same ID is already processed');
            $tx->setUpdatedAt(new \DateTime());
            $this->di['em']->persist($tx);
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

        $res = $this->di['em']->getRepository(Transaction::class)->findOneBy(['status' => 'processed', 'txnId' => $tx->getTxnId()]);

        return !empty($res);
    }

    private function _markAsProcessed(Transaction $tx): void
    {
        $tx->setError(null);
        $tx->setErrorCode(null);
        $tx->setStatus(Transaction::STATUS_PROCESSED);
        $tx->setUpdatedAt(new \DateTime());
        $this->di['em']->persist($tx);
        $this->di['em']->flush();
    }

    private function _parseIpnAndApprove(Transaction &$tx): Transaction
    {
        if ($tx->getStatus() == Transaction::STATUS_APPROVED) {
            return $tx;
        }

        $invoiceService = $this->di['mod_service']('Invoice');
        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');

        $ipn = json_decode($tx->getIpn() ?? '', true) ?? [];

        if (empty($tx->getGatewayId())) {
            throw new \FOSSBilling\Exception('Could not determine transaction origin. Transaction payment gateway is unknown.', null, 701);
        }

        $gtw = $this->getPayGatewayRepository()->find($tx->getGatewayId());
        if (!$gtw instanceof PayGateway) {
            throw new \FOSSBilling\Exception('Cannot handle transaction received from unknown payment gateway: :id', [':id' => $tx->getGatewayId()], 704);
        }

        $adapter = $payGatewayService->getPaymentAdapter($gtw);
        if (!$tx->getInvoiceId() && method_exists($adapter, 'getInvoiceId')) {
            $tx->setInvoiceId($adapter->getInvoiceId($ipn));
        }

        if (!$tx->getInvoiceId()) {
            throw new \FOSSBilling\Exception('Transaction :id is not associated with an invoice.', [':id' => $tx->getId()], 702);
        }

        $invoice = $this->getInvoiceRepository()->find($tx->getInvoiceId());
        if (!$invoice instanceof Invoice) {
            throw new \FOSSBilling\Exception('Invoice #:id not found', [':id' => $tx->getInvoiceId()], 703);
        }

        $adapter = $payGatewayService->getPaymentAdapter($gtw, $invoice);
        $mpi = $invoiceService->getPaymentInvoice($invoice);

        if (!Environment::isTesting() && $tx->isValidateIpn()) {
            if (!$adapter->isIpnValid($ipn, $mpi)) {
                $tx->setOutput($adapter->getOutput());

                throw new \FOSSBilling\Exception('Instant payment notification (IPN) did not pass gateway :id validation', [':id' => $gtw->getGateway()], 706);
            }
            $tx->setOutput($adapter->getOutput());
        }

        if (!method_exists($adapter, 'getTransaction')) {
            throw new \FOSSBilling\Exception('Payment adapter :adapter does not support action :action', [':adapter' => $gtw->getName(), ':action' => 'getTransaction'], 705);
        }

        $response = $adapter->getTransaction($ipn, $mpi);
        if (!$response instanceof \Payment_Transaction) {
            throw new \FOSSBilling\Exception('Payment gateway :id method getTransaction should return Payment_Transaction object', [':id' => $gtw->getGateway()], 705);
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
            $tx->setAmount($response->getAmount());
        }

        if ($response->getCurrency()) {
            $tx->setCurrency($response->getCurrency());
        }

        $tx->setStatus(Transaction::STATUS_APPROVED);
        $tx->setUpdatedAt(new \DateTime());
        $this->di['em']->persist($tx);
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

        if ($tx->getInvoiceId()) {
            try {
                $invoiceService = $this->di['mod_service']('Invoice');
                $invoice = $this->getInvoiceRepository()->find($tx->getInvoiceId());
                if ($invoice) {
                    $invoiceService->tryPayWithCredits($invoice);
                }
            } catch (\Exception $e) {
                if (defined('DEBUG')) {
                    error_log($e->getMessage());
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

        $invoice = $this->getInvoiceRepository()->find($tx->getInvoiceId());
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
            throw new \FOSSBilling\Exception('Cannot create subscription. Subscription ID from payment gateway was not received');
        }

        $invoice = $this->getInvoiceRepository()->find($tx->getInvoiceId());
        $subscriptionService = $this->di['mod_service']('Invoice', 'Subscription');
        $period = $subscriptionService->getSubscriptionPeriod($invoice);

        $s = new Subscription();
        $s->setClientId($invoice->getClientId());
        $s->setPayGatewayId($tx->getGatewayId());
        $s->setSid($tx->getSId());
        $s->setPeriod($period);
        $s->setRelType('invoice');
        $s->setRelId($invoice->getId());
        $s->setAmount((float) $tx->getAmount());
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

        $serviceSubscription = $this->di['mod_service']('Subscription');
        $model = $this->getSubscriptionRepository()->find((int) $tx->getSId());
        if (!$model instanceof Subscription) {
            throw new \FOSSBilling\Exception('Subscription #:id was not found. Could not unsubscribe', [':id' => $tx->getSId()]);
        }

        $serviceSubscription->unsubscribe($model);

        $this->_markAsProcessed($tx);

        return $tx;
    }

    private function _validateApprovedTransaction(Transaction $tx): void
    {
        if ($tx->getStatus() != Transaction::STATUS_APPROVED) {
            throw new \FOSSBilling\Exception('Only approved transaction can be processed');
        }

        if (empty($tx->getInvoiceId())) {
            throw new \FOSSBilling\Exception('Transaction :id is not associated with an invoice.', [':id' => $tx->getId()], 7022);
        }

        $invoice = $this->getInvoiceRepository()->find($tx->getInvoiceId());

        // check that payment currency is correct
        if ($invoice->getCurrency() != $tx->getCurrency()) {
            throw new \FOSSBilling\Exception('Transaction currency :code does not match required currency :required', [':code' => $tx->getCurrency(), ':required' => $invoice->getCurrency()], 709);
        }

        // check that payment status is completed if
        if ($tx->getTxnStatus() == \Payment_Transaction::STATUS_PENDING) {
            throw new \FOSSBilling\Exception('Transaction status on payment gateway is Pending. Only Complete or Unknown transactions can be processed.', null, 712);
        }
    }

    public function debitTransaction(Transaction $tx): void
    {
        $proforma = $this->getInvoiceRepository()->find($tx->getInvoiceId());
        $client = $this->di['em']->getRepository(ClientEntity::class)->find($proforma->getClientId());

        $clientCurrency = $client instanceof ClientEntity ? $client->getCurrency() : $client->currency;
        if ($clientCurrency != $proforma->getCurrency()) {
            throw new \FOSSBilling\Exception('Client currency does not match invoice currency');
        }

        // do not debit negative or zero amount
        if ($tx->getAmount() < 0) {
            throw new \FOSSBilling\Exception('Cannot add negative amount to client balance for debit transaction');
        }

        $credit = new ClientBalance();
        $credit->setClientId($client instanceof ClientEntity ? $client->getId() : $client->id);
        $credit->setType('transaction');
        $credit->setRelId((string) $tx->getId());
        $credit->setDescription('Invoice #' . $proforma->getId() . ' payment received from transaction #' . $tx->getId());
        $credit->setAmount($tx->getAmount());
        $this->di['em']->persist($credit);
        $this->di['em']->flush();
    }

    private function getInvoiceRepository(): InvoiceRepository
    {
        if ($this->invoiceRepository === null) {
            $this->invoiceRepository = $this->di['em']->getRepository(Invoice::class);
        }

        return $this->invoiceRepository;
    }

    private function getPayGatewayRepository(): PayGatewayRepository
    {
        if ($this->payGatewayRepository === null) {
            $this->payGatewayRepository = $this->di['em']->getRepository(PayGateway::class);
        }

        return $this->payGatewayRepository;
    }

    private function getSubscriptionRepository(): SubscriptionRepository
    {
        if ($this->subscriptionRepository === null) {
            $this->subscriptionRepository = $this->di['em']->getRepository(Subscription::class);
        }

        return $this->subscriptionRepository;
    }

    private function getTransactionRepository(): TransactionRepository
    {
        if ($this->transactionRepository === null) {
            $this->transactionRepository = $this->di['em']->getRepository(Transaction::class);
        }

        return $this->transactionRepository;
    }
}
