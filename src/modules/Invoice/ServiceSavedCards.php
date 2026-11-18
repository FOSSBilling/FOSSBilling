<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Invoice;

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceCardCharge;
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\PayGatewayCustomer;
use Box\Mod\Invoice\Entity\Subscription;
use Box\Mod\Invoice\Entity\Transaction;
use Box\Mod\Invoice\Repository\PayGatewayCustomerRepository;
use FOSSBilling\Core\Container\InjectionAwareInterface;
use FOSSBilling\Core\Doctrine\EntityManagerFactory;
use FOSSBilling\Core\Doctrine\NamedLock;
use FOSSBilling\Core\Events\EventDispatcher;
use FOSSBilling\Core\Exception\InformationException;
use FOSSBilling\Core\Payment\ProcessingFence;

/**
 * Automatic payments with saved payment methods. A client saves a Stripe
 * card when paying an invoice or on
 * the client area's Payment methods page. Each gateway keeps the saved
 * method in pay_gateway_customer (default_payment_method); the one the
 * client saved or chose last (auto_pay_at) pays. The cron charges it for
 * unpaid invoices without a payment method or with one of these gateways:
 * the set number of days before the due date, then every set number of days
 * up to the set number of retries (invoice_card_charge counts the tries). A
 * failed charge emails the client (mod_invoice_card_failed). Invoices of
 * orders that a gateway subscription pays are left to it. Staff can charge
 * the saved method from the invoice page.
 */
class ServiceSavedCards implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    /** {days_before, every, retries} from the Invoices settings page. */
    public function settings(): array
    {
        $config = $this->di['mod_service']('extension')->getConfig('mod_invoice');
        // Before the retry count, card_retries switched the two retries on or off.
        $retries = $config['card_retry_count'] ?? ((string) ($config['card_retries'] ?? '1') === '0' ? 0 : 2);

        return [
            'days_before' => max(0, min(30, (int) ($config['card_days_before'] ?? 0))),
            'every' => max(1, min(30, (int) ($config['card_retry_every'] ?? 7))),
            'retries' => max(0, min(5, (int) $retries)),
        ];
    }

    public function saveSettings(array $data): void
    {
        $days = filter_var($data['card_days_before'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 30]]);
        if ($days === false) {
            throw new InformationException('Days before the due date must be a whole number from 0 to 30.');
        }
        $every = filter_var($data['card_retry_every'] ?? 7, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 30]]);
        if ($every === false) {
            throw new InformationException('Days between retries must be a whole number from 1 to 30.');
        }
        $retries = filter_var($data['card_retry_count'] ?? 2, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 5]]);
        if ($retries === false) {
            throw new InformationException('Retries must be a whole number from 0 to 5.');
        }
        $extension = $this->di['mod_service']('extension');
        $extension->setConfig(array_merge($extension->getConfig('mod_invoice'), [
            'card_days_before' => (string) $days,
            'card_retry_every' => (string) $every,
            'card_retry_count' => (string) $retries,
            'ext' => 'mod_invoice',
        ]));
        $this->di['logger']->info('Invoice: saved payment methods are charged {days} days before the due date, retried {retries} times every {every} days', ['days' => $days, 'retries' => $retries, 'every' => $every]);
    }

    /** The enabled Stripe payment gateway's adapter, or null. */
    public function stripe(): ?\Payment_Adapter_Stripe
    {
        $gateway = $this->di['em']->getRepository(PayGateway::class)->findEnabledByGateway('Stripe');
        if (!$gateway instanceof PayGateway) {
            return null;
        }
        $adapter = $this->di['mod_service']('Invoice', 'PayGateway')->getPaymentAdapter($gateway);

        return $adapter instanceof \Payment_Adapter_Stripe ? $adapter : null;
    }

    /** @return list<\Payment_Adapter_Stripe|\Payment_Adapter_PayPalAutoPay|\Payment_Adapter_PayPalEmail> the gateways that charge saved methods */
    public function adapters(): array
    {
        return array_values(array_filter([$this->stripe(), $this->autopay(), $this->paypal()]));
    }

    /** The enabled PayPal Email gateway's adapter when clients can save their PayPal account, or null. */
    public function paypal(): ?\Payment_Adapter_PayPalEmail
    {
        $gateway = $this->di['em']->getRepository(PayGateway::class)->findEnabledByGateway('PayPalEmail');
        if (!$gateway instanceof PayGateway) {
            return null;
        }
        $adapter = $this->di['mod_service']('Invoice', 'PayGateway')->getPaymentAdapter($gateway);

        return $adapter instanceof \Payment_Adapter_PayPalEmail && $adapter->canVault() ? $adapter : null;
    }

    /** The enabled PayPal Auto-Pay gateway's adapter (billing agreements), or null. */
    public function autopay(): ?\Payment_Adapter_PayPalAutoPay
    {
        $gateway = $this->di['em']->getRepository(PayGateway::class)->findEnabledByGateway('PayPalAutoPay');
        if (!$gateway instanceof PayGateway) {
            return null;
        }
        $adapter = $this->di['mod_service']('Invoice', 'PayGateway')->getPaymentAdapter($gateway);

        return $adapter instanceof \Payment_Adapter_PayPalAutoPay ? $adapter : null;
    }

    /**
     * The method that pays the client's invoices: the saved one the client saved or chose last.
     * {adapter, payment_method, card (its label)}, or null.
     *
     * @return array{adapter: object, payment_method: string, card: string, auto_pay_at: string}|null
     */
    public function method(int $clientId): ?array
    {
        $method = null;
        foreach ($this->adapters() as $adapter) {
            $saved = $adapter->savedCard($clientId);
            if ($saved === null) {
                continue;
            }
            if ($method === null || $saved['auto_pay_at'] > $method['auto_pay_at']) {
                $method = $saved + ['adapter' => $adapter];
            }
        }

        return $method;
    }

    /** The client's method for automatic charges: {adapter, payment_method, card}, or null. */
    public function card(int $clientId): ?array
    {
        return $this->method($clientId);
    }

    /** The saved method of a gateway's client ({payment_method, card, auto_pay_at}), or null. */
    public function saved(int $gatewayId, bool $testMode, int $clientId): ?array
    {
        $row = $this->getCustomerRepository()->findSaved($gatewayId, $testMode, $clientId);
        if (!$row instanceof PayGatewayCustomer || (string) $row->getDefaultPaymentMethod() === '') {
            return null;
        }

        return [
            'payment_method' => (string) $row->getDefaultPaymentMethod(),
            'card' => (string) $row->getDefaultCard(),
            'auto_pay_at' => $row->getAutoPayAt()?->format('Y-m-d H:i:s') ?? '',
        ];
    }

    /** Keeps a saved method (one per client and gateway) as the method that pays the client's invoices. */
    public function remember(int $gatewayId, bool $testMode, int $clientId, string $customerId, string $paymentMethod, string $label): void
    {
        $now = new \DateTimeImmutable();
        $row = $this->getCustomerRepository()->findOneByGatewayAndClient($gatewayId, $clientId, $testMode);
        if (!$row instanceof PayGatewayCustomer) {
            $row = new PayGatewayCustomer();
            $row->setPayGateway($this->di['em']->getReference(PayGateway::class, $gatewayId));
            $row->setClientId($clientId);
            $row->setTestMode($testMode);
            $row->setExternalCustomerId($customerId);
            $row->setSavedCards(true);
            $row->setDefaultPaymentMethod($paymentMethod);
            $row->setDefaultCard(mb_substr($label, 0, 100));
            $row->setAutoPayAt($now);
            $this->di['em']->persist($row);
        } else {
            $row->setExternalCustomerId($customerId);
            $row->setSavedCards(true);
            $row->setDefaultPaymentMethod($paymentMethod);
            $row->setDefaultCard(mb_substr($label, 0, 100));
            $row->setAutoPayAt($now);
        }
        $this->di['em']->flush();
    }

    /** Makes a gateway's saved method the one that pays the client's invoices. */
    public function choose(int $gatewayId, bool $testMode, int $clientId): void
    {
        $row = $this->getCustomerRepository()->findSaved($gatewayId, $testMode, $clientId);
        if ($row instanceof PayGatewayCustomer) {
            $row->setAutoPayAt(new \DateTimeImmutable());
            $this->di['em']->flush();
        }
    }

    /** Forgets a saved method wherever it is kept (its agreement cancelled at PayPal first). */
    public function forget(int $gatewayId, bool $testMode, string $paymentMethod): void
    {
        $this->getCustomerRepository()->clearSavedMethod($gatewayId, $testMode, $paymentMethod);
    }

    /** From the cron: charges the saved method of every invoice whose next try is due today. */
    public function runDue(): void
    {
        $adapters = $this->adapters();
        if ($adapters === []) {
            return;
        }
        $this->endPaypalSubscriptions();
        $gatewayIds = array_map(static fn (object $adapter): int => $adapter->gatewayId(), $adapters);
        $settings = $this->settings();
        $connection = $this->di['em']->getConnection();
        foreach ($adapters as $adapter) {
            $rows = $connection->fetchAllAssociative(
                "SELECT i.id, i.client_id, i.due_at, COALESCE(c.attempts, 0) AS attempts FROM invoice i
                   JOIN pay_gateway_customer g ON g.client_id = i.client_id AND g.pay_gateway_id = ? AND g.test_mode = ? AND g.saved_cards = TRUE
                        AND g.default_payment_method IS NOT NULL AND g.default_payment_method <> ''
                   LEFT JOIN invoice_card_charge c ON c.invoice_id = i.id
                  WHERE i.status = 'unpaid' AND i.issued = TRUE AND i.due_at IS NOT NULL
                    AND (i.gateway_id IS NULL OR i.gateway_id IN (" . implode(',', $gatewayIds) . ')) AND COALESCE(c.attempts, 0) < ?
                    AND (c.last_attempt_at IS NULL OR c.last_attempt_at < ?)
                    AND NOT ' . PendingPayments::sql('i.id', $connection) . "
                    AND NOT EXISTS (SELECT 1 FROM invoice_item ii
                          JOIN invoice_item si ON si.type = ii.type AND si.rel_id = ii.rel_id
                          JOIN subscription s ON s.rel_type = 'invoice' AND s.rel_id = si.invoice_id AND s.status = 'active'
                         WHERE ii.invoice_id = i.id AND ii.type = 'order')
                  ORDER BY i.id",
                // Tries at least the retry gap apart, also for an invoice long overdue when the method was saved.
                [$adapter->gatewayId(), (int) $adapter->testMode(), 1 + $settings['retries'], date('Y-m-d', strtotime('-' . ($settings['every'] - 1) . ' days'))]
            );
            $today = date('Y-m-d');
            foreach ($rows as $row) {
                if (self::tryDate((string) $row['due_at'], (int) $row['attempts'], $settings) > $today
                    || !(($this->method((int) $row['client_id'])['adapter'] ?? null) instanceof $adapter)) {
                    continue;
                }
                $invoice = $this->di['em']->find(Invoice::class, (int) $row['id']);
                if ($invoice instanceof Invoice) {
                    try {
                        $this->charge($invoice, $adapter, false);
                    } catch (\Throwable $e) {
                        // One invoice (a charge the gateway left unclear, a lock
                        // held by a concurrent request) must not abort the rest.
                        $this->di['logger']->warning('Invoice #{id}: automatic saved-method charge failed: {message}', ['id' => $row['id'], 'message' => $e->getMessage()]);
                        $this->recoverEntityManager();
                    }
                }
            }
        }
    }

    /** Discard a failed payment's unit of work before another invoice or cron task runs. */
    private function recoverEntityManager(): void
    {
        $manager = $this->di['em'];
        if ($manager->isOpen()) {
            return;
        }
        $connection = $manager->getConnection();
        if (ProcessingFence::claims() !== [] || $connection->isTransactionActive()) {
            throw new \LogicException('Payment processing must unwind before its entity manager can be replaced.');
        }
        // Do not continue on an unusable or uncertain database connection.
        $connection->fetchOne('SELECT 1');
        $replacement = EntityManagerFactory::create($connection);
        unset($this->di['em']);
        $this->di['em'] = $replacement;
        // Module services are created per call, but the dispatcher retains
        // listener services whose repositories belong to the discarded manager.
        if ($this->di['event_dispatcher'] instanceof EventDispatcher) {
            $this->di['event_dispatcher']->refresh();
        }
    }

    /** The day of a try (0 = the first) for an invoice due on $dueAt. */
    public static function tryDate(string $dueAt, int $try, array $settings): string
    {
        return date('Y-m-d', strtotime(substr($dueAt, 0, 10) . ' ' . ($try * $settings['every'] - $settings['days_before']) . ' days'));
    }

    /**
     * A client who saved their PayPal account no longer needs their PayPal subscriptions: they are cancelled
     * (PayPal emails the client) and the saved account pays the renewal invoices.
     */
    private function endPaypalSubscriptions(): void
    {
        $paypal = $this->paypal();
        if ($paypal === null) {
            return;
        }
        $connection = $this->di['em']->getConnection();
        $ids = $connection->fetchFirstColumn(
            "SELECT s.id FROM subscription s JOIN pay_gateway_customer g ON g.client_id = s.client_id AND g.pay_gateway_id = s.pay_gateway_id AND g.test_mode = ?
              WHERE s.pay_gateway_id = ? AND s.status = 'active' AND s.sid IS NOT NULL AND s.sid <> '' AND g.saved_cards = TRUE
                AND g.default_payment_method IS NOT NULL AND g.default_payment_method <> ''
                AND (s.updated_at IS NULL OR s.updated_at < ?)",
            // A subscription PayPal refused to cancel is tried again a day later, not every cron run.
            [(int) $paypal->testMode(), $paypal->gatewayId(), date('Y-m-d H:i:s', strtotime('-1 day'))]
        );
        foreach ($ids as $id) {
            $subscription = $this->di['em']->find(Subscription::class, (int) $id);
            if (!$subscription instanceof Subscription) {
                continue;
            }

            try {
                $this->di['mod_service']('Invoice', 'Subscription')->cancel($subscription);
                $this->di['logger']->info('Subscription #{id} ({sid}): cancelled at PayPal, the saved PayPal account pays from now on', ['id' => $id, 'sid' => $subscription->getSid()]);
            } catch (\Throwable $e) {
                $subscription->setUpdatedAt(new \DateTimeImmutable());
                $this->di['em']->flush();
                $this->di['logger']->warning('Subscription #{id} ({sid}): not cancelled at PayPal: {message}', ['id' => $id, 'sid' => $subscription->getSid(), 'message' => $e->getMessage()]);
            }
        }
    }

    /**
     * Charges the saved method for the invoice's balance and records the payment: {paid, error}. A try of the cron
     * counts towards the retries and a failure emails the client; staff tries do neither.
     *
     * @return array{paid: bool, error: string|null}
     */
    public function charge(Invoice $invoice, \Payment_Adapter_Stripe|\Payment_Adapter_PayPalAutoPay|\Payment_Adapter_PayPalEmail $adapter, bool $byStaff, bool $byClient = false): array
    {
        if ($this->di['em']->getConnection()->isTransactionActive()) {
            throw new \LogicException('Saved payments cannot run inside a transaction.');
        }
        $lockName = 'fb:card-charge:' . (int) $invoice->getId();
        if (!NamedLock::acquire($this->di['em']->getConnection(), $lockName, 10)) {
            throw new InformationException('Another request is being processed. Please try again.');
        }

        try {
            return $this->chargeUnderLock($invoice, $adapter, $byStaff, $byClient);
        } finally {
            NamedLock::release($this->di['em']->getConnection(), $lockName);
        }
    }

    /** @return array{paid: bool, error: string|null} */
    private function chargeUnderLock(Invoice $invoice, \Payment_Adapter_Stripe|\Payment_Adapter_PayPalAutoPay|\Payment_Adapter_PayPalEmail $adapter, bool $byStaff, bool $byClient = false): array
    {
        $connection = $this->di['em']->getConnection();
        $row = $connection->fetchAssociative('SELECT status, gateway_id, issued FROM invoice WHERE id = ?', [(int) $invoice->getId()]) ?: [];
        if (($row['status'] ?? null) !== Invoice::STATUS_UNPAID) {
            return ['paid' => false, 'error' => 'This invoice is already paid or cancelled.'];
        }
        if (!$byStaff && empty($row['issued'])) {
            return ['paid' => false, 'error' => null];
        }
        if (PendingPayments::forInvoice($connection, (int) $invoice->getId()) !== null) {
            return ['paid' => false, 'error' => 'A payment for this invoice is still clearing.'];
        }
        // A cron try leaves an invoice the client chose to pay another way after runDue read it (a receipt sent meanwhile).
        if (!$byStaff && $row['gateway_id'] !== null && !in_array((int) $row['gateway_id'], array_map(static fn (object $a): int => $a->gatewayId(), $this->adapters()), true)) {
            return ['paid' => false, 'error' => null];
        }
        if (GatewayChargeAttempts::pending($connection, (int) $invoice->getId()) !== null) {
            return ['paid' => false, 'error' => 'A previous payment attempt needs reconciliation before another charge.'];
        }
        $charge = $this->di['em']->find(InvoiceCardCharge::class, (int) $invoice->getId());
        if ($charge instanceof InvoiceCardCharge) {
            $this->di['em']->refresh($charge);
        }
        $attempt = $charge?->getAttempts() ?? 0;
        // Held after runDue read it (a staff charge holds it): a cron try would charge twice.
        if (!$byStaff && $attempt >= InvoiceCardCharge::HELD) {
            return ['paid' => false, 'error' => null];
        }
        // The client's own "Pay now" never charges again while a payment may be taken (staff may, on purpose).
        if ($byClient && $attempt >= InvoiceCardCharge::HELD) {
            return ['paid' => false, 'error' => 'A payment for this invoice is still clearing.'];
        }
        if (!$byStaff) {
            // Another cron may have declined a charge after runDue selected it.
            $settings = $this->settings();
            $retryBefore = new \DateTimeImmutable('today -' . ($settings['every'] - 1) . ' days');
            if ($attempt > $settings['retries'] || ($charge?->getLastAttemptAt() !== null && $charge->getLastAttemptAt() >= $retryBefore)) {
                return ['paid' => false, 'error' => null];
            }
        }
        if ($this->di['mod_service']('Invoice')->invoiceHasDeletionHold($invoice)) {
            return ['paid' => false, 'error' => 'Order deletion is awaiting recovery. Retry deletion before billing or changing the service.'];
        }
        $this->recordAttempt((int) $invoice->getId(), !$byStaff);
        // The saved method's gateway takes the payment, with its fee.
        $gateway = $this->di['em']->find(PayGateway::class, $adapter->gatewayId());
        if (!$gateway instanceof PayGateway) {
            throw new InformationException('The payment gateway for the saved method is not available.');
        }
        $this->di['mod_service']('Invoice', 'GatewayFees')->apply($invoice, $gateway);
        $card = (string) ($adapter->savedCard((int) $invoice->getClientId())['card'] ?? '');

        $ownerToken = null;
        $attemptId = GatewayChargeAttempts::begin($connection, (int) $invoice->getId(), $adapter->gatewayId(), $ownerToken);

        try {
            return ProcessingFence::charge($connection, $attemptId, $ownerToken, fn (): array => $this->chargePrepared($invoice, $adapter, $byStaff, $card, $attempt, $attemptId));
        } catch (\Throwable $exception) {
            $this->di['em']->close();

            throw $exception;
        }
    }

    private function chargePrepared(Invoice $invoice, \Payment_Adapter_Stripe|\Payment_Adapter_PayPalAutoPay|\Payment_Adapter_PayPalEmail $adapter, bool $byStaff, string $card, int $attempt, string $attemptId): array
    {
        $connection = $this->di['em']->getConnection();

        try {
            $paymentId = $adapter->chargeSavedCard($invoice, $attemptId);
        } catch (\Throwable $e) {
            if ($e instanceof \FOSSBilling\Core\Payment\OwnershipLost) {
                throw $e;
            }
            $error = $e instanceof InformationException ? $e->getMessage() : 'The payment service did not answer. Please try again later.';
            $this->recordError((int) $invoice->getId(), $error);
            if (!$e instanceof InformationException) {
                // Maybe taken without an answer (a timeout): a new try could charge twice.
                $this->hold((int) $invoice->getId(), 'No answer: ' . $e->getMessage());
            }
            $this->di['logger']->warning('Invoice #{id}: charging the saved method {card} failed: {message}', ['id' => $invoice->getId(), 'card' => $card, 'message' => $e->getMessage()]);
            if (!$byStaff && $e instanceof InformationException) {
                $this->emailFailure($invoice, $card, $error, $attempt + 1);
            }

            if ($e instanceof InformationException) {
                GatewayChargeAttempts::resolve($connection, $attemptId, 'failed');
            }

            return ['paid' => false, 'error' => $error];
        }
        GatewayChargeAttempts::recordPayment($connection, $attemptId, $paymentId);
        // Taken: until it is recorded as paid (a bank debit may clear later, or reading it back may fail), never charged again.
        $this->hold((int) $invoice->getId(), 'Payment ' . $paymentId . ' is not completed yet.');
        $txId = $this->di['mod_service']('Invoice', 'Transaction')->createAndProcess([
            'invoice_id' => (int) $invoice->getId(),
            'gateway_id' => $adapter->gatewayId(),
            'source' => 'saved_card',
            'get' => [GatewayChargeAttempts::referenceParameter($adapter) => $paymentId, 'invoice_id' => (int) $invoice->getId()],
        ]);
        $this->di['logger']->info('Invoice #{id}: charged the saved method {card} ({payment})', ['id' => $invoice->getId(), 'card' => $card, 'payment' => $paymentId]);
        if ($connection->fetchOne('SELECT status FROM invoice WHERE id = ?', [(int) $invoice->getId()]) !== Invoice::STATUS_PAID) {
            // The payment we just recorded tells whether a bank debit started
            // (it clears later) or something else went wrong re-reading it.
            $tx = $txId !== null ? $this->di['em']->find(Transaction::class, $txId) : null;

            return ['paid' => false, 'error' => $tx?->getTxnStatus() === Transaction::STATUS_PROCESSING
                ? 'The payment has started. The invoice is marked paid when it clears.'
                : 'The payment was taken but is not completed yet. Check it with the payment service.'];
        }

        $table = $connection->quoteSingleIdentifier('transaction');
        if ($connection->fetchOne("SELECT id FROM {$table} WHERE invoice_id = ? AND gateway_id = ? AND txn_id = ? AND status = 'processed'", [(int) $invoice->getId(), $adapter->gatewayId(), $paymentId]) !== false) {
            GatewayChargeAttempts::resolve($connection, $attemptId, 'settled');
        }

        return ['paid' => true, 'error' => null];
    }

    private function recordAttempt(int $invoiceId, bool $count): void
    {
        if (!$count) {
            return;
        }
        $charge = $this->di['em']->find(InvoiceCardCharge::class, $invoiceId);
        $isNew = !$charge instanceof InvoiceCardCharge;
        if ($isNew) {
            $charge = new InvoiceCardCharge();
            $charge->setInvoiceId($invoiceId);
            $charge->setAttempts(0);
        }
        $charge->setAttempts($charge->getAttempts() + 1);
        $charge->setLastAttemptAt(new \DateTimeImmutable());
        if ($isNew) {
            $this->di['em']->persist($charge);
        }
        $this->di['em']->flush();
    }

    private function recordError(int $invoiceId, string $error): void
    {
        $charge = $this->di['em']->find(InvoiceCardCharge::class, $invoiceId);
        if ($charge instanceof InvoiceCardCharge) {
            $charge->setLastError(mb_substr($error, 0, 255));
            $this->di['em']->flush();
        }
    }

    /** Stops automatic tries for an invoice whose payment may have been taken already (staff can still charge it). */
    public function hold(int $invoiceId, string $note): void
    {
        $charge = $this->di['em']->find(InvoiceCardCharge::class, $invoiceId);
        $isNew = !$charge instanceof InvoiceCardCharge;
        if ($isNew) {
            $charge = new InvoiceCardCharge();
            $charge->setInvoiceId($invoiceId);
        }
        $charge->setAttempts(InvoiceCardCharge::HELD);
        $charge->setLastAttemptAt(new \DateTimeImmutable());
        $charge->setLastError(mb_substr($note, 0, 255));
        if ($isNew) {
            $this->di['em']->persist($charge);
        }
        $this->di['em']->flush();
    }

    /** A saved bank debit that was clearing failed: the client is told, with no automatic retry. */
    public function emailDebitFailure(Invoice $invoice, string $method, string $reason): void
    {
        $this->emailFailure($invoice, $method, $reason, InvoiceCardCharge::HELD);
    }

    private function emailFailure(Invoice $invoice, string $card, string $reason, int $tries): void
    {
        $settings = $this->settings();
        $dueAt = $invoice->getDueAt()?->format('Y-m-d') ?? date('Y-m-d');
        $retryAt = $tries <= $settings['retries']
            ? max(self::tryDate($dueAt, $tries, $settings), date('Y-m-d', strtotime('+' . $settings['every'] . ' days')))
            : '';

        try {
            $service = $this->di['mod_service']('Invoice');
            $data = $service->toApiArray($invoice, true, null, true);
            $email = $service->withBillingRecipient([
                'to_client' => (int) $invoice->getClientId(),
                'code' => 'mod_invoice_card_failed',
                'invoice' => $data,
                'card' => $card,
                'reason' => $reason,
                'retry_at' => $retryAt,
            ], $data);
            $this->di['mod_service']('email')->sendTemplate($email);
        } catch (\Throwable $e) {
            $this->di['logger']->error('Invoice #{id}: the failed payment email was not sent: {message}', ['id' => $invoice->getId(), 'message' => $e->getMessage()]);
        }
    }

    private function getCustomerRepository(): PayGatewayCustomerRepository
    {
        /* @var PayGatewayCustomerRepository */
        return $this->di['em']->getRepository(PayGatewayCustomer::class);
    }
}
