<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Box\Mod\Client\Entity\Client;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\Subscription;
use Box\Mod\Invoice\Entity\Transaction;
use FOSSBilling\Doctrine\NamedLock;
use FOSSBilling\Period;
use Stripe\StripeClient;
use Symfony\Component\Intl\Currencies;

class Payment_Adapter_Stripe implements FOSSBilling\Container\InjectionAwareInterface
{
    protected ?Pimple\Container $di = null;

    private StripeClient $stripe;

    /**
     * Stripe webhook event types that this adapter processes.
     * Events not in this list are silently acknowledged and their
     * transaction records are deleted to keep the transactions list clean.
     */
    public const HANDLED_EVENT_TYPES = [
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
        'invoice.payment_succeeded',
        'invoice.paid',
        'invoice.payment_failed',
        'invoice_payment.paid',
        'invoice_payment.failed',
        'payment_intent.succeeded',
        'payment_intent.payment_failed',
        'setup_intent.succeeded',
        'setup_intent.setup_failed',
    ];

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Pimple\Container
    {
        return $this->di;
    }

    private function debugLog(string $message): void
    {
        // @phpstan-ignore if.alwaysFalse (DEBUG is a runtime constant that may be true during debugging)
        if (DEBUG) {
            $this->di['logger']->debug($message);
        }
    }

    public function __construct(private $config)
    {
        if ($this->config['test_mode']) {
            if (!isset($this->config['test_api_key'])) {
                throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'Stripe', ':missing' => 'Test API Key'], 4001);
            }
            if (!isset($this->config['test_pub_key'])) {
                throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'Stripe', ':missing' => 'Test publishable key'], 4001);
            }

            $this->stripe = new StripeClient($this->config['test_api_key']);
        } else {
            if (!isset($this->config['api_key'])) {
                throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'Stripe', ':missing' => 'API key'], 4001);
            }
            if (!isset($this->config['pub_key'])) {
                throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'Stripe', ':missing' => 'Publishable key'], 4001);
            }

            $this->stripe = new StripeClient($this->config['api_key']);
        }
    }

    public static function getConfig(): array
    {
        return [
            'supports_one_time_payments' => true,
            'supports_subscriptions' => true,
            'description' => 'You authenticate to the Stripe API by providing one of your API keys in the request. You can manage your API keys from your account.',
            'logo' => [
                'logo' => 'stripe.png',
                'height' => '30px',
                'width' => '65px',
            ],
            'form' => [
                'pub_key' => [
                    'text', [
                        'label' => 'Live Publishable Key:',
                        'required_when' => ['enabled' => true, 'test_mode' => false],
                    ],
                ],
                'api_key' => [
                    'password', [
                        'label' => 'Live Secret Key:',
                        'required_when' => ['enabled' => true, 'test_mode' => false],
                        'secret' => true,
                    ],
                ],
                'webhook_secret' => [
                    'password', [
                        'label' => 'Live Webhook signing secret:',
                        'required_when' => ['enabled' => true, 'test_mode' => false],
                        'secret' => true,
                    ],
                ],
                'test_pub_key' => [
                    'text', [
                        'label' => 'Test Publishable Key:',
                        'required_when' => ['enabled' => true, 'test_mode' => true],
                    ],
                ],
                'test_api_key' => [
                    'password', [
                        'label' => 'Test Secret Key:',
                        'required_when' => ['enabled' => true, 'test_mode' => true],
                        'secret' => true,
                    ],
                ],
                'test_webhook_secret' => [
                    'password', [
                        'label' => 'Test Webhook signing secret:',
                        'required_when' => ['enabled' => true, 'test_mode' => true],
                        'secret' => true,
                    ],
                ],
            ],
        ];
    }

    public function getHtml(FOSSBilling\Api\Proxy $api_admin, int $invoice_id, bool $subscription): string
    {
        $invoiceModel = $this->di['em']->getRepository(Invoice::class)->find($invoice_id);
        if (!$invoiceModel instanceof Invoice) {
            throw new FOSSBilling\Exception\BaseException('Invoice not found');
        }

        if ($subscription) {
            return $this->_generateSubscriptionForm($invoiceModel);
        }

        return $this->_generateForm($invoiceModel);
    }

    public function cancelSubscription(string $subscriptionId): void
    {
        $subscription = $this->stripe->subscriptions->retrieve($subscriptionId, []);
        if ($subscription->status === Stripe\Subscription::STATUS_CANCELED) {
            return;
        }

        $this->stripe->subscriptions->cancel($subscriptionId, []);
    }

    public function cancelSubscriptionAtPeriodEnd(string $subscriptionId): void
    {
        $subscription = $this->stripe->subscriptions->retrieve($subscriptionId, []);
        if ($subscription->status === Stripe\Subscription::STATUS_CANCELED || ($subscription->cancel_at_period_end ?? false)) {
            return;
        }

        $this->stripe->subscriptions->update($subscriptionId, ['cancel_at_period_end' => true]);
    }

    public function getAmountInCents(Invoice $invoice): int
    {
        return $this->getAmountInMinorUnits($invoice);
    }

    public function getAmountInMinorUnits(Invoice $invoice): int
    {
        $invoiceService = $this->di['mod_service']('Invoice');
        $amount = $invoiceService->getTotalWithTax($invoice);
        $multiplier = 10 ** $this->getCurrencyFractionDigits($invoice->getCurrency());

        return (int) round($amount * $multiplier);
    }

    private function getAmountFromMinorUnits(int $amount, string $currency): float
    {
        $divisor = 10 ** $this->getCurrencyFractionDigits($currency);

        return $amount / $divisor;
    }

    private function getCurrencyFractionDigits(string $currency): int
    {
        $currency = strtoupper($currency);

        return Currencies::exists($currency) ? Currencies::getFractionDigits($currency) : 2;
    }

    public function getInvoiceTitle(Invoice $invoice): string
    {
        $invoiceItems = $this->di['em']->getConnection()->fetchAllAssociative('SELECT title FROM invoice_item WHERE invoice_id = :invoice_id', ['invoice_id' => $invoice->getId()]);

        $params = [
            ':id' => sprintf('%05s', $invoice->getNr()),
            ':serie' => $invoice->getSerie(),
            ':title' => $invoiceItems[0]['title'] ?? '',
        ];
        $title = __trans('Payment for invoice :serie:id [:title]', $params);
        if (FOSSBilling\Utils\Arr::safeCount($invoiceItems) > 1) {
            $title = __trans('Payment for invoice :serie:id', $params);
        }

        return $title;
    }

    public function logError($e, Transaction $tx): void
    {
        $body = $e->getJsonBody();
        $err = $body['error'];
        $tx->setTxnStatus($err['type']);
        $tx->setError($err['message']);
        $tx->setStatus(Transaction::STATUS_ERROR);
        $tx->setUpdatedAt(new DateTime());
        $this->di['em']->flush();

        $this->debugLog((string) json_encode($e->getJsonBody()));

        throw new Exception($tx->getError());
    }

    public function processTransaction(FOSSBilling\Api\Proxy $api_admin, int $id, array $data, int $gateway_id): void
    {
        $tx = $this->di['em']->getRepository(Transaction::class)->find($id);
        if (!$tx instanceof Transaction) {
            throw new FOSSBilling\Exception\BaseException('Transaction not found');
        }

        if ($this->isStripeWebhook($data)) {
            $this->processWebhookEvent($api_admin, $tx, $data, $gateway_id);

            return;
        }

        $invoice = $this->resolveInvoice($tx, $data);

        try {
            if (isset($data['get']['payment_intent'])) {
                $this->processPaymentIntent($tx, $invoice, $data);
            } elseif (isset($data['get']['setup_intent'])) {
                $this->processSetupIntent($api_admin, $tx, $invoice, $data, $gateway_id);
            }
        } catch (Stripe\Exception\CardException|Stripe\Exception\InvalidRequestException|Stripe\Exception\AuthenticationException|Stripe\Exception\ApiConnectionException|Stripe\Exception\ApiErrorException $e) {
            $this->logError($e, $tx);

            throw new FOSSBilling\Exception\BaseException('There was an error when processing the transaction');
        }
    }

    private function resolveInvoice(Transaction $tx, array $data): ?Invoice
    {
        if ($tx->getInvoice()) {
            return $tx->getInvoice();
        }
        if (isset($data['get']['invoice_id']) && $data['get']['invoice_id']) {
            $invoice = $this->di['em']->getRepository(Invoice::class)->find((int) $data['get']['invoice_id']);
            if (!$invoice instanceof Invoice) {
                return null;
            }

            $tx->setInvoice($invoice);

            return $invoice;
        }

        return null;
    }

    private function isStripeWebhook(array $data): bool
    {
        $rawBody = $data['http_raw_post_data'] ?? null;
        if (empty($rawBody)) {
            return false;
        }

        $payload = json_decode((string) $rawBody, true);

        return is_array($payload) && isset($payload['type']);
    }

    private function processPaymentIntent(Transaction $tx, ?Invoice $invoice, array $data): void
    {
        $charge = $this->stripe->paymentIntents->retrieve($data['get']['payment_intent'], []);

        $this->withStripeObjectLock(
            $charge->id,
            (int) $tx->getGateway()?->getId(),
            fn () => $this->processPaymentIntentUnderLock($tx, $invoice, $charge)
        );
    }

    private function processPaymentIntentUnderLock(Transaction $tx, ?Invoice $invoice, object $charge): void
    {
        $invoiceService = $this->di['mod_service']('Invoice');

        $tx->setTxnStatus($charge->status);
        $tx->setTxnId($charge->id);
        $tx->setAmount((string) $this->getAmountFromMinorUnits($charge->amount, $charge->currency));
        $tx->setCurrency($charge->currency);
        $tx->setType(Payment_Transaction::TXTYPE_PAYMENT);

        // Stripe may deliver the webhook before redirecting the customer.
        // Keep that transaction instead of recording the PaymentIntent twice.
        $transactionRepository = $this->di['em']->getRepository(Transaction::class);
        $existing = $transactionRepository->findActiveByTxnIdAndGatewayId($charge->id, (int) $tx->getGateway()?->getId(), (int) $tx->getId());
        if ($existing instanceof Transaction) {
            $this->di['em']->remove($tx);
            $this->di['em']->flush();

            return;
        }

        if ($charge->status === 'succeeded') {
            if ($tx->getStatus() === Transaction::STATUS_PROCESSED && empty($tx->getError())) {
                $tx->setUpdatedAt(new DateTime());
                $this->di['em']->flush();

                return;
            }

            // Already-paid guard — prevents double-crediting when the
            // payment_intent.succeeded webhook processed the payment
            // before the redirect flow runs.
            if ($invoice instanceof Invoice) {
                $fresh = $this->di['em']->getRepository(Invoice::class)->find($invoice->getId());
                if ($fresh instanceof Invoice && $fresh->getStatus() === Invoice::STATUS_PAID) {
                    $tx->setStatus(Transaction::STATUS_PROCESSED);
                    $tx->setUpdatedAt(new DateTime());
                    $this->di['em']->flush();

                    return;
                }
            }

            $transactionService = $this->di['mod_service']('Invoice', 'Transaction');
            if (!$transactionService->claimForProcessing((int) $tx->getId())) {
                return;
            }

            $tx->setStatus(Transaction::STATUS_PROCESSING);
        }

        $bd = [
            'amount' => $tx->getAmount(),
            'description' => 'Stripe transaction ' . $charge->id,
            'type' => 'transaction',
            'rel_id' => $tx->getId(),
        ];

        if ($charge->status == 'succeeded' && $tx->getStatus() === Transaction::STATUS_PROCESSING) {
            $clientService = $this->di['mod_service']('client');
            $client = $invoice
                ? $this->di['em']->getRepository(Client::class)->find($invoice->getClientId())
                    ?? throw new FOSSBilling\Exception\InformationException('Client not found') : $this->getClientFromTransaction($tx, $charge);

            if ($invoice) {
                $expected = $invoiceService->getTotalWithTax($invoice);

                try {
                    $invoiceService->validatePaymentAmount((float) $tx->getAmount(), $expected);
                } catch (FOSSBilling\Exception\BaseException $e) {
                    $tx->setStatus(Transaction::STATUS_ERROR);
                    $tx->setError($e->getMessage());
                    $tx->setUpdatedAt(new DateTime());
                    $this->di['em']->flush();

                    throw $e;
                }
            }

            $clientService->addFunds($client, $bd['amount'], $bd['description'], $bd);

            if ($tx->getInvoice() && $invoice && !$invoiceService->isInvoiceTypeDeposit($invoice)) {
                if (!$invoice->isApproved()) {
                    $invoiceService->approveInvoice($invoice, ['use_credits' => false]);
                }
                $invoiceService->payInvoiceWithCredits($invoice);
            } elseif ($tx->getInvoice() && $invoice && $invoiceService->isInvoiceTypeDeposit($invoice)) {
                $invoiceService->markAsPaid($invoice);
            } elseif (!$tx->getInvoice()) {
                $invoiceService->doBatchPayWithCredits(['client_id' => (int) $client->getId()]);
            }
        }

        $paymentStatus = match ($charge->status) {
            'succeeded' => Transaction::STATUS_PROCESSED,
            'requires_action' => Transaction::STATUS_RECEIVED,
            'requires_confirmation' => Transaction::STATUS_RECEIVED,
            'requires_capture' => Transaction::STATUS_RECEIVED,
            'processing' => Transaction::STATUS_RECEIVED,
            'pending' => Transaction::STATUS_RECEIVED,
            'requires_payment_method' => Transaction::STATUS_ERROR,
            'canceled' => Transaction::STATUS_ERROR,
            'failed' => Transaction::STATUS_ERROR,
            default => Transaction::STATUS_ERROR,
        };

        $tx->setStatus($paymentStatus);
        $tx->setUpdatedAt(new DateTime());
        $this->di['em']->flush();
    }

    private function processSetupIntent($api_admin, Transaction $tx, ?Invoice $invoice, array $data, int $gateway_id): void
    {
        $setupIntent = $this->stripe->setupIntents->retrieve($data['get']['setup_intent'], []);

        $tx->setTxnStatus($setupIntent->status);
        $tx->setTxnId($setupIntent->id);

        if ($setupIntent->status === 'succeeded' && $invoice instanceof Invoice) {
            $customer = $this->getOrCreateCustomer($invoice);

            try {
                $subscription = $this->createStripeSubscription($customer, $setupIntent, $invoice);
            } catch (Stripe\Exception\ApiErrorException $e) {
                // Only handle the expected race where the setup_intent.succeeded
                // webhook created the subscription concurrently with the same
                // idempotency key. All other API errors (card declined, auth
                // failures, network issues) must propagate so the caller sees them.
                if ($e->getStripeCode() !== 'idempotency_key_in_use') {
                    throw $e;
                }

                // Webhook beat us here — find the subscription it created.
                $subscriptions = $this->stripe->subscriptions->all([
                    'customer' => $customer->id,
                    'limit' => 1,
                ]);
                $subscription = count($subscriptions->data) > 0 ? $subscriptions->data[0] : null;

                if ($subscription === null) {
                    $tx->setStatus(Transaction::STATUS_PROCESSED);
                    $tx->setUpdatedAt(new DateTime());
                    $this->di['em']->flush();

                    return;
                }
            }

            $tx->setSId($subscription->id);
            $tx->setSPeriod($this->getSubscriptionPeriodForInvoice($invoice));
            $tx->setAmount((string) $this->getAmountFromMinorUnits($this->getAmountInCents($invoice), $invoice->getCurrency()));
            $tx->setCurrency($invoice->getCurrency());
            $tx->setType(Payment_Transaction::TXTYPE_PAYMENT);
            $tx->setStatus(Transaction::STATUS_PROCESSED);

            // Create the FOSSBilling subscription record immediately so it
            // shows up in the subscriptions list without depending on the
            // customer.subscription.created webhook event.
            $this->createOrUpdateSubscription($api_admin, $invoice, $subscription, $gateway_id);

            // Process the initial subscription payment immediately so the user
            // sees a paid invoice on redirect. Stripe charges the first invoice
            // synchronously during subscription creation when using
            // charge_automatically with a default_payment_method.
            $this->processInitialSubscriptionPayment($api_admin, $tx, $invoice, $subscription);
        } else {
            $tx->setStatus(Transaction::STATUS_ERROR);
        }

        $tx->setUpdatedAt(new DateTime());
        $this->di['em']->flush();
    }

    /**
     * Process the initial subscription payment immediately after subscription
     * creation so the user sees a paid invoice when redirected back.
     *
     * Stripe charges the first invoice synchronously during subscription
     * creation (charge_automatically + default_payment_method). This method
     * retrieves that invoice and applies the payment to FOSSBilling right
     * away, rather than waiting for the invoice.paid webhook to arrive.
     */
    private function processInitialSubscriptionPayment($api_admin, Transaction $tx, Invoice $invoice, Stripe\Subscription $subscription): void
    {
        // Already-paid guard — reload from DB to narrow the TOCTOU window when
        // the redirect flow and webhook handler race on the same subscription.
        $fresh = $this->di['em']->getRepository(Invoice::class)->find($invoice->getId());
        if ($fresh instanceof Invoice && $fresh->getStatus() === Invoice::STATUS_PAID) {
            return;
        }

        $latestInvoiceId = $subscription->latest_invoice ?? null;
        if (empty($latestInvoiceId)) {
            return;
        }

        $latestInvoice = is_string($latestInvoiceId)
            ? $this->stripe->invoices->retrieve($latestInvoiceId, [])
            : $latestInvoiceId;

        if (($latestInvoice->status ?? '') !== 'paid') {
            return;
        }

        $bd = [
            'id' => $invoice->getClientId(),
            'amount' => $this->getAmountFromMinorUnits(
                (int) ($latestInvoice->amount_paid ?? 0),
                (string) ($latestInvoice->currency ?? '')
            ),
            'description' => 'Stripe subscription initial payment ' . $latestInvoice->id,
            'type' => 'transaction',
            'rel_id' => $tx->getId(),
        ];

        $api_admin->client_balance_add_funds($bd);

        $invoiceService = $this->di['mod_service']('Invoice');
        if (!$invoiceService->isInvoiceTypeDeposit($invoice)) {
            if (!$invoice->isApproved()) {
                $invoiceService->approveInvoice($invoice, ['use_credits' => false]);
            }
            $invoiceService->payInvoiceWithCredits($invoice);
        }
    }

    private function processWebhookEvent($api_admin, Transaction $tx, array $data, int $gateway_id): void
    {
        $rawBody = $data['http_raw_post_data'] ?? '';
        $sigHeader = $data['server']['HTTP_STRIPE_SIGNATURE'] ?? '';
        $webhookSecret = $this->config['test_mode']
            ? ($this->config['test_webhook_secret'] ?? '')
            : ($this->config['webhook_secret'] ?? '');

        // Webhook events credit funds and mark invoices paid based on their
        // contents, so a verified signature is mandatory. Without a signing
        // secret configured there is no way to distinguish a genuine Stripe
        // event from a forged one, so refuse to process the event at all
        // rather than trusting an unsigned payload.
        if (empty($webhookSecret)) {
            throw new FOSSBilling\Exception\BaseException('Stripe webhook signing secret is not configured');
        }
        if (empty($sigHeader)) {
            throw new FOSSBilling\Exception\BaseException('Missing Stripe-Signature header');
        }

        try {
            $event = Stripe\Webhook::constructEvent($rawBody, $sigHeader, $webhookSecret);
        } catch (UnexpectedValueException) {
            throw new FOSSBilling\Exception\BaseException('Invalid Stripe webhook payload');
        } catch (Stripe\Exception\SignatureVerificationException) {
            throw new FOSSBilling\Exception\BaseException('Invalid Stripe webhook signature');
        }

        $tx->setTxnId($event->id);
        $tx->setTxnStatus($event->type);

        // Delete transactions for events we don't handle to keep the
        // transactions list clean. Stripe sends many webhook events per
        // payment cycle (e.g. invoice.created, charge.succeeded) that are
        // not relevant to FOSSBilling.
        if (!in_array($event->type, self::HANDLED_EVENT_TYPES, true)) {
            $this->di['em']->remove($tx);
            $this->di['em']->flush();

            return;
        }

        // Each handler returns true to keep the transaction (actual payment
        // processed) or false to delete it (informational event, dedup, or
        // subscription lifecycle change that doesn't represent a payment).
        $keepTransaction = false;

        try {
            // A Stripe account can deliver the same event to multiple webhook
            // endpoints. Only the FOSSBilling gateway which created the Stripe
            // object may process it; otherwise two gateway records configured
            // for one Stripe account can both credit the same payment.
            if (!$this->eventBelongsToGateway($event, $gateway_id)) {
                $this->di['em']->remove($tx);
                $this->di['em']->flush();

                return;
            }

            $keepTransaction = match ($event->type) {
                'customer.subscription.created' => $this->handleSubscriptionCreated($api_admin, $tx, $event, $gateway_id),
                'customer.subscription.updated' => $this->handleSubscriptionUpdated($api_admin, $tx, $event),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($api_admin, $tx, $event),
                'invoice.payment_succeeded' => $this->handleInvoicePaymentSucceeded($api_admin, $tx, $event, $gateway_id),
                'invoice.paid' => $this->handleInvoicePaymentSucceeded($api_admin, $tx, $event, $gateway_id),
                'invoice.payment_failed' => $this->handleInvoicePaymentFailed($api_admin, $tx, $event),
                'invoice_payment.paid' => $this->handleInvoicePaymentSucceeded($api_admin, $tx, $event, $gateway_id),
                'invoice_payment.failed' => $this->handleInvoicePaymentFailed($api_admin, $tx, $event),
                'payment_intent.succeeded' => $this->handlePaymentIntentSucceededWebhook($api_admin, $tx, $event, $gateway_id),
                'payment_intent.payment_failed' => $this->handlePaymentIntentFailedWebhook($api_admin, $tx, $event),
                'setup_intent.succeeded' => $this->handleSetupIntentSucceededWebhook($api_admin, $tx, $event, $gateway_id),
                'setup_intent.setup_failed' => $this->handleSetupIntentFailedWebhook($api_admin, $tx, $event),
            };
        } catch (Stripe\Exception\CardException|Stripe\Exception\InvalidRequestException|Stripe\Exception\AuthenticationException|Stripe\Exception\ApiConnectionException|Stripe\Exception\ApiErrorException $e) {
            $this->logError($e, $tx);

            throw new FOSSBilling\Exception\BaseException('There was an error when processing the Stripe webhook');
        }

        if ($keepTransaction) {
            if ($tx->getStatus() !== Transaction::STATUS_ERROR) {
                $tx->setStatus(Transaction::STATUS_PROCESSED);
            }
        } else {
            $this->di['em']->remove($tx);
            $this->di['em']->flush();

            return;
        }

        $tx->setUpdatedAt(new DateTime());
        $this->di['em']->flush();
    }

    private function eventBelongsToGateway(object $event, int $gatewayId): bool
    {
        $stripeObject = $event->data->object ?? null;
        if (!is_object($stripeObject)) {
            return false;
        }

        $eventGatewayId = $this->getGatewayIdFromStripeObject($stripeObject);

        $eventGatewayId ??= $this->getInvoiceGatewayId($stripeObject->metadata->invoice_id ?? null);

        if ($eventGatewayId === null && str_starts_with((string) ($event->type ?? ''), 'customer.subscription.')) {
            $eventGatewayId = $this->getLocalSubscriptionGatewayId($stripeObject->id ?? null);
        }

        if ($eventGatewayId === null && str_starts_with((string) ($event->type ?? ''), 'invoice')) {
            $stripeInvoice = $this->resolveStripeInvoice($stripeObject);
            $subscriptionId = $this->extractSubscriptionId($stripeInvoice);
            if ($subscriptionId !== null) {
                $eventGatewayId = $this->getLocalSubscriptionGatewayId($subscriptionId);
                if ($eventGatewayId === null) {
                    $stripeSubscription = $this->stripe->subscriptions->retrieve($subscriptionId, []);
                    $eventGatewayId = $this->getGatewayIdFromStripeObject($stripeSubscription)
                        ?? $this->getInvoiceGatewayId($stripeSubscription->metadata->invoice_id ?? null);
                }
            }
        }

        return $eventGatewayId === $gatewayId;
    }

    private function getGatewayIdFromStripeObject(object $stripeObject): ?int
    {
        $gatewayId = $stripeObject->metadata->gateway_id
            ?? $stripeObject->parent->subscription_details->metadata->gateway_id
            ?? $stripeObject->subscription_details->metadata->gateway_id
            ?? $stripeObject->lines->data[0]->metadata->gateway_id
            ?? null;

        return is_numeric($gatewayId) && (int) $gatewayId > 0 ? (int) $gatewayId : null;
    }

    private function getInvoiceGatewayId(mixed $invoiceId): ?int
    {
        if (!is_numeric($invoiceId) || (int) $invoiceId <= 0) {
            return null;
        }

        $gatewayId = $this->di['dbal']->fetchOne(
            'SELECT gateway_id FROM invoice WHERE id = :id',
            ['id' => (int) $invoiceId]
        );

        return is_numeric($gatewayId) && (int) $gatewayId > 0 ? (int) $gatewayId : null;
    }

    private function getLocalSubscriptionGatewayId(mixed $subscriptionId): ?int
    {
        if (!is_string($subscriptionId) || $subscriptionId === '') {
            return null;
        }

        $gatewayId = $this->di['dbal']->fetchOne(
            'SELECT pay_gateway_id FROM subscription WHERE sid = :sid',
            ['sid' => $subscriptionId]
        );

        return is_numeric($gatewayId) && (int) $gatewayId > 0 ? (int) $gatewayId : null;
    }

    private function handleSubscriptionCreated($api_admin, Transaction $tx, object $event, int $gateway_id): bool
    {
        $stripeSubscription = $event->data->object;
        $invoiceId = $stripeSubscription->metadata->invoice_id ?? null;
        $clientId = $stripeSubscription->metadata->client_id ?? null;

        if (!$invoiceId || !$clientId) {
            return false;
        }

        $invoice = $this->di['em']->getRepository(Invoice::class)->find((int) $invoiceId);
        if (!$invoice instanceof Invoice) {
            return false;
        }
        $tx->setInvoice($invoice);

        // Subscription record is now created inline by processSetupIntent and
        // handleSetupIntentSucceededWebhook. This handler only serves as a
        // fallback if those flows didn't run (e.g. subscription created outside
        // FOSSBilling). Use the shared helper to avoid duplication.
        $this->createOrUpdateSubscription($api_admin, $invoice, $stripeSubscription, $gateway_id);

        return false;
    }

    private function handleSubscriptionUpdated($api_admin, Transaction $tx, object $event): bool
    {
        $stripeSubscription = $event->data->object;

        $status = ($stripeSubscription->cancel_at_period_end ?? false)
            ? Box\Mod\Invoice\ServiceSubscription::STATUS_PENDING_CANCELLATION
            : match ($stripeSubscription->status) {
                'active' => 'active',
                'trialing' => 'active',
                'past_due' => 'active',
                default => 'canceled',
            };

        try {
            $this->updateSubscriptionStatusFromGateway($api_admin, $stripeSubscription->id, $status);
        } catch (Exception $e) {
            $this->debugLog('Stripe subscription updated webhook: ' . $e->getMessage());
        }

        return false;
    }

    private function handleSubscriptionDeleted($api_admin, Transaction $tx, object $event): bool
    {
        $stripeSubscription = $event->data->object;
        $subscriptionService = $this->di['mod_service']('Invoice', 'Subscription');
        $subscriptionId = $subscriptionService->findIdBySid($stripeSubscription->id);
        if ($subscriptionId === null) {
            return false;
        }

        $subscriptionService->finalizeCancellationFromGateway($subscriptionId);

        return false;
    }

    private function handleInvoicePaymentSucceeded($api_admin, Transaction $tx, object $event, int $gateway_id): bool
    {
        $stripeInvoice = $this->resolveStripeInvoice($event->data->object);

        $subscriptionId = $this->extractSubscriptionId($stripeInvoice);

        if ($stripeInvoice === null || $subscriptionId === null) {
            return false;
        }

        // Those events can arrive concurrently, so the dedup below has to be held under a lock.
        return $this->withStripeObjectLock(
            $stripeInvoice->id,
            $gateway_id,
            fn (): bool => $this->handleInvoicePaymentSucceededUnderLock($api_admin, $tx, $stripeInvoice, $subscriptionId)
        );
    }

    private function handleInvoicePaymentSucceededUnderLock($api_admin, Transaction $tx, object $stripeInvoice, string $subscriptionId): bool
    {
        // Dedup: Stripe sends both invoice.payment_succeeded and invoice.paid for
        // the same payment. Use the Stripe invoice ID as the shared natural key so
        // whichever event arrives second sees the first is already processing/done.
        $tx->setTxnId($stripeInvoice->id);
        $existing = $this->di['em']->getRepository(Transaction::class)
            ->findProcessingOrProcessedByTxnId($stripeInvoice->id, null, (int) $tx->getId());
        if ($existing instanceof Transaction) {
            return false;
        }

        $stripeSubscription = $this->stripe->subscriptions->retrieve($subscriptionId, []);
        $invoiceId = $stripeSubscription->metadata->invoice_id ?? null;
        $clientId = $stripeSubscription->metadata->client_id ?? null;

        if (!$clientId) {
            return false;
        }

        // Link the transaction to the invoice as early as possible so the
        // association survives any early return or failure further below.
        if ($invoiceId) {
            $tx->setInvoice($this->di['em']->getRepository(Invoice::class)->find((int) $invoiceId));
            $this->di['em']->flush();
        }

        $isInitialPayment = ($stripeInvoice->billing_reason ?? '') === 'subscription_create';

        // Single DB fetch covers: (a) skip if already paid, (b) billing_reason fallback.
        if ($invoiceId) {
            $existingInvoice = $this->di['em']->getRepository(Invoice::class)->find((int) $invoiceId);
            if ($existingInvoice instanceof Invoice) {
                // Skip if already paid — redirect flow may have processed it first.
                if ($existingInvoice->getStatus() === Invoice::STATUS_PAID) {
                    return false;
                }
                // Fallback: billing_reason inconclusive but original invoice still unpaid.
                if (!$isInitialPayment && $existingInvoice->getStatus() === Invoice::STATUS_UNPAID) {
                    $isInitialPayment = true;
                }
            }
        }

        $bd = [
            'id' => $clientId,
            'amount' => $this->getAmountFromMinorUnits(
                (int) ($stripeInvoice->amount_paid ?? 0),
                (string) ($stripeInvoice->currency ?? '')
            ),
            'description' => $isInitialPayment
                ? 'Stripe subscription initial payment ' . $stripeInvoice->id
                : 'Stripe subscription recurring payment ' . $stripeInvoice->id,
            'type' => 'transaction',
            'rel_id' => $tx->getId(),
        ];

        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');
        if (!$transactionService->claimForProcessing((int) $tx->getId())) {
            return false;
        }

        $tx->setType(Payment_Transaction::TXTYPE_PAYMENT);
        $tx->setAmount((string) $bd['amount']);
        $tx->setCurrency(strtoupper((string) ($stripeInvoice->currency ?? '')));

        $api_admin->client_balance_add_funds($bd);

        $invoiceService = $this->di['mod_service']('Invoice');

        if ($isInitialPayment && $invoiceId) {
            $invoiceModel = $this->di['em']->getRepository(Invoice::class)->find((int) $invoiceId);

            if ($invoiceModel instanceof Invoice && !$invoiceService->isInvoiceTypeDeposit($invoiceModel)) {
                if (!$invoiceModel->isApproved()) {
                    $invoiceService->approveInvoice($invoiceModel, ['use_credits' => false]);
                }
                $invoiceService->payInvoiceWithCredits($invoiceModel);
            }
        } else {
            $renewalInvoice = $invoiceService->generateRenewalInvoiceForSubscriptionPayment(
                $stripeSubscription->id,
                (int) $clientId
            );

            if ($renewalInvoice instanceof Invoice) {
                $tx->setInvoice($renewalInvoice);
                if (!$invoiceService->isInvoiceTypeDeposit($renewalInvoice)) {
                    $invoiceService->payInvoiceWithCredits($renewalInvoice);
                }
            } else {
                $api_admin->invoice_batch_pay_with_credits(['client_id' => $clientId]);
            }
        }

        return true;
    }

    private function handleInvoicePaymentFailed($api_admin, Transaction $tx, object $event): bool
    {
        $stripeInvoice = $this->resolveStripeInvoice($event->data->object);

        $subscriptionId = $this->extractSubscriptionId($stripeInvoice);

        if ($stripeInvoice === null || $subscriptionId === null) {
            return false;
        }

        try {
            $this->updateSubscriptionStatusFromGateway($api_admin, $subscriptionId, 'canceled');
        } catch (Exception $e) {
            $this->debugLog('Stripe invoice payment failed webhook: ' . $e->getMessage());
        }

        return false;
    }

    private function updateSubscriptionStatusFromGateway($api_admin, string $subscriptionId, string $status): void
    {
        $subscription = $api_admin->invoice_subscription_get(['sid' => $subscriptionId]);
        $subscriptionService = $this->di['mod_service']('Invoice', 'Subscription');
        $subscriptionService->updateStatusFromGateway((int) $subscription['id'], $status);
    }

    /**
     * Handle payment_intent.succeeded webhook for one-time payments.
     *
     * Provides reliability when the customer doesn't return via the redirect
     * flow (e.g. browser closed). Includes dedup so it's safe to receive this
     * event even if the redirect already processed the payment.
     */
    private function handlePaymentIntentSucceededWebhook($api_admin, Transaction $tx, object $event, int $gateway_id): bool
    {
        $paymentIntent = $event->data->object;

        return $this->withStripeObjectLock(
            $paymentIntent->id,
            $gateway_id,
            fn (): bool => $this->handlePaymentIntentSucceededWebhookUnderLock($tx, $paymentIntent, $gateway_id)
        );
    }

    private function handlePaymentIntentSucceededWebhookUnderLock(Transaction $tx, object $paymentIntent, int $gateway_id): bool
    {
        // Set transaction metadata from the PaymentIntent
        $tx->setTxnId($paymentIntent->id);
        $tx->setTxnStatus($paymentIntent->status);
        $tx->setAmount((string) $this->getAmountFromMinorUnits($paymentIntent->amount, $paymentIntent->currency));
        $tx->setCurrency($paymentIntent->currency);
        $tx->setType(Payment_Transaction::TXTYPE_PAYMENT);

        // Dedup: skip if already processed or currently being processed via
        // the redirect flow. The redirect transaction stores txn_id = PaymentIntent ID.
        // We check both PROCESSING and PROCESSED to catch the race where the
        // redirect flow is mid-processing when the webhook arrives.
        $existing = $this->di['em']->getRepository(Transaction::class)
            ->findProcessingOrProcessedByTxnId($paymentIntent->id, $gateway_id, (int) $tx->getId());
        if ($existing instanceof Transaction) {
            $tx->setInvoice($existing->getInvoice());

            return false;
        }

        // Link transaction to the invoice from PaymentIntent metadata.
        // PaymentIntents created internally by Stripe Subscriptions don't
        // carry FOSSBilling metadata — those are handled via invoice events.
        $invoiceId = $paymentIntent->metadata->invoice_id ?? null;
        $clientId = $paymentIntent->metadata->client_id ?? null;

        if (!$invoiceId && !$clientId) {
            // This is a subscription-internal PaymentIntent, not a one-time
            // payment from FOSSBilling. Skip it — the invoice_payment.paid
            // or invoice.payment_succeeded webhook handles subscription payments.
            return false;
        }

        if ($invoiceId) {
            $tx->setInvoice($this->di['em']->getRepository(Invoice::class)->find((int) $invoiceId));
        }

        // Persist the PaymentIntent ID while the lock is held so a redirect
        // waiting on the same key observes this transaction after release.
        $this->di['em']->flush();

        if ($paymentIntent->status !== 'succeeded') {
            return false;
        }

        $invoice = $tx->getInvoice();

        // Delegate to the shared payment processing logic
        $this->applyOneTimePayment($tx, $invoice, $paymentIntent);

        return true;
    }

    private function withStripeObjectLock(string $objectId, int $gatewayId, callable $callback): mixed
    {
        $lockName = 'fb:stripe:' . substr(hash('sha256', $gatewayId . ':' . $objectId), 0, 54);
        $waitStartedAt = hrtime(true);

        if (!NamedLock::acquire($this->di['dbal'], $lockName, 10)) {
            $waitDurationMs = (hrtime(true) - $waitStartedAt) / 1_000_000;
            $this->di['logger']->warning(
                'Timed out after {duration_ms} ms waiting for Stripe object lock {lock_name}',
                ['duration_ms' => $waitDurationMs, 'lock_name' => $lockName]
            );

            throw new FOSSBilling\Exception\BaseException('Timed out waiting to process this Stripe payment');
        }

        try {
            return $callback();
        } finally {
            NamedLock::release($this->di['dbal'], $lockName);
        }
    }

    private function handlePaymentIntentFailedWebhook($api_admin, Transaction $tx, object $event): bool
    {
        $paymentIntent = $event->data->object;
        $tx->setTxnId($paymentIntent->id);
        $tx->setTxnStatus($paymentIntent->status);
        $tx->setStatus(Transaction::STATUS_ERROR);
        $tx->setError('Payment failed via webhook');

        return true;
    }

    /**
     * Handle setup_intent.succeeded webhook for subscription creation.
     *
     * Provides reliability when the customer doesn't return via the redirect
     * flow. Uses the subscription creation idempotency key to prevent
     * duplicates if the redirect also fires.
     */
    private function handleSetupIntentSucceededWebhook($api_admin, Transaction $tx, object $event, int $gateway_id): bool
    {
        $setupIntent = $event->data->object;

        $tx->setTxnId($setupIntent->id);
        $tx->setTxnStatus($setupIntent->status);

        if ($setupIntent->status !== 'succeeded') {
            $tx->setStatus(Transaction::STATUS_ERROR);
            $tx->setUpdatedAt(new DateTime());
            $this->di['em']->flush();

            return false;
        }

        // Dedup: skip if already processed or being processed via the redirect flow.
        $existing = $this->di['em']->getRepository(Transaction::class)
            ->findProcessingOrProcessedByTxnId($setupIntent->id);
        if ($existing instanceof Transaction) {
            $tx->setInvoice($existing->getInvoice());

            return false;
        }

        $invoiceId = $setupIntent->metadata->invoice_id ?? null;
        if (!$invoiceId) {
            return false;
        }

        $invoice = $this->di['em']->getRepository(Invoice::class)->find((int) $invoiceId);
        if (!$invoice instanceof Invoice) {
            return false;
        }
        $tx->setInvoice($invoice);
        $this->di['em']->flush();

        $customer = $this->getOrCreateCustomer($invoice);

        // createStripeSubscription uses an idempotency key based on the
        // invoice ID, so this is safe even if the redirect flow races.
        // If both fire simultaneously, Stripe returns the same subscription
        // to the first and a "concurrent request" error to the second.
        try {
            $subscription = $this->createStripeSubscription($customer, $setupIntent, $invoice);
        } catch (Stripe\Exception\ApiErrorException $e) {
            // Only treat idempotency conflicts as the expected race with the
            // redirect flow; rethrow all other API errors (card declined, auth
            // failures, etc.) so they surface to the caller.
            if ($e->getStripeCode() !== 'idempotency_key_in_use') {
                throw $e;
            }

            $this->debugLog('Stripe setup_intent webhook: subscription creation deferred to redirect flow: ' . $e->getMessage());

            return false;
        }

        $tx->setSId($subscription->id);
        $tx->setSPeriod($this->getSubscriptionPeriodForInvoice($invoice));
        $tx->setAmount((string) $this->getAmountFromMinorUnits($this->getAmountInCents($invoice), $invoice->getCurrency()));
        $tx->setCurrency($invoice->getCurrency());
        $tx->setType(Payment_Transaction::TXTYPE_PAYMENT);
        $tx->setUpdatedAt(new DateTime());
        $this->di['em']->flush();

        // Create the FOSSBilling subscription record immediately.
        $this->createOrUpdateSubscription($api_admin, $invoice, $subscription, $gateway_id);

        // Process the initial payment immediately so the invoice is paid
        // even if the redirect flow hasn't completed yet.
        $this->processInitialSubscriptionPayment($api_admin, $tx, $invoice, $subscription);

        return true;
    }

    private function handleSetupIntentFailedWebhook($api_admin, Transaction $tx, object $event): bool
    {
        $setupIntent = $event->data->object;
        $tx->setTxnId($setupIntent->id);
        $tx->setTxnStatus($setupIntent->status);
        $tx->setStatus(Transaction::STATUS_ERROR);
        $tx->setError('Setup Intent failed via webhook');

        return true;
    }

    /**
     * Create a FOSSBilling subscription record from a Stripe subscription.
     * Called from the redirect flow and webhook handler so the subscription
     * appears immediately, without depending on the customer.subscription.created
     * webhook event.
     */
    private function createOrUpdateSubscription($api_admin, Invoice $invoice, object $subscription, int $gateway_id): void
    {
        $existing = $this->di['em']->getRepository(Subscription::class)->findOneBy(['sid' => $subscription->id]);
        if ($existing instanceof Subscription) {
            return;
        }

        $sd = [
            'client_id' => $invoice->getClientId(),
            'gateway_id' => $gateway_id,
            'currency' => strtoupper($invoice->getCurrency()),
            'sid' => $subscription->id,
            'status' => 'active',
            'period' => $this->getSubscriptionPeriodForInvoice($invoice),
            'amount' => $this->getAmountFromMinorUnits($this->getAmountInCents($invoice), $invoice->getCurrency()),
            'rel_type' => 'invoice',
            'rel_id' => $invoice->getId(),
        ];

        try {
            $api_admin->invoice_subscription_create($sd);
        } catch (Exception $e) {
            $this->debugLog('Failed to create FOSSBilling subscription for ' . $subscription->id . ': ' . $e->getMessage());
        }
    }

    /**
     * Shared logic for applying a one-time payment to a client balance and
     * invoice. Used by both the redirect flow (processPaymentIntent) and the
     * payment_intent.succeeded webhook handler.
     */
    private function applyOneTimePayment(Transaction $tx, ?Invoice $invoice, object $charge): void
    {
        // Reload the invoice from the database to get the freshest status.
        // This narrows the TOCTOU race window when the redirect flow and
        // webhook process the same payment concurrently.
        if ($invoice instanceof Invoice) {
            $fresh = $this->di['em']->getRepository(Invoice::class)->find($invoice->getId());
            if ($fresh instanceof Invoice) {
                $invoice = $fresh;
            }
        }

        // Skip if the invoice is already paid — prevents double-crediting
        // when the webhook arrives after the redirect flow.
        if ($invoice instanceof Invoice && $invoice->getStatus() === Invoice::STATUS_PAID) {
            return;
        }

        $invoiceService = $this->di['mod_service']('Invoice');

        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');
        if (!$transactionService->claimForProcessing((int) $tx->getId())) {
            return;
        }

        $tx->setStatus(Transaction::STATUS_PROCESSING);

        $clientService = $this->di['mod_service']('client');
        $client = $invoice
            ? $this->di['em']->getRepository(Client::class)->find($invoice->getClientId())
                ?? throw new FOSSBilling\Exception\InformationException('Client not found') : $this->getClientFromTransaction($tx, $charge);

        if ($invoice) {
            $expected = $invoiceService->getTotalWithTax($invoice);

            try {
                $invoiceService->validatePaymentAmount((float) $tx->getAmount(), $expected);
            } catch (FOSSBilling\Exception\BaseException $e) {
                $tx->setStatus(Transaction::STATUS_ERROR);
                $tx->setError($e->getMessage());
                $tx->setUpdatedAt(new DateTime());
                $this->di['em']->flush();

                throw $e;
            }
        }

        $bd = [
            'amount' => $tx->getAmount(),
            'description' => 'Stripe transaction ' . $charge->id,
            'type' => 'transaction',
            'rel_id' => $tx->getId(),
        ];

        $clientService->addFunds($client, $bd['amount'], $bd['description'], $bd);

        if ($tx->getInvoice() && $invoice && !$invoiceService->isInvoiceTypeDeposit($invoice)) {
            if (!$invoice->isApproved()) {
                $invoiceService->approveInvoice($invoice, ['use_credits' => false]);
            }
            $invoiceService->payInvoiceWithCredits($invoice);
        } elseif ($tx->getInvoice() && $invoice && $invoiceService->isInvoiceTypeDeposit($invoice)) {
            $invoiceService->markAsPaid($invoice);
        } elseif (!$tx->getInvoice()) {
            $invoiceService->doBatchPayWithCredits(['client_id' => (int) $client->getId()]);
        }

        $tx->setStatus(Transaction::STATUS_PROCESSED);
        $tx->setUpdatedAt(new DateTime());
        $this->di['em']->flush();
    }

    /**
     * Resolve the event payload to a Stripe invoice object.
     *
     * Handles both legacy invoice events (where data.object is already an
     * invoice) and the newer invoice_payment events introduced in API version
     * 2026-06-24, where data.object is an invoice_payment that references the
     * invoice by ID and does not embed subscription/billing_reason fields.
     *
     * @return object|null The full Stripe invoice object, or null on failure
     */
    private function resolveStripeInvoice(object $paymentObject): ?object
    {
        if (($paymentObject->object ?? '') === 'invoice_payment') {
            if (empty($paymentObject->invoice)) {
                return null;
            }

            return $this->stripe->invoices->retrieve($paymentObject->invoice, []);
        }

        return $paymentObject;
    }

    /**
     * Extract the subscription ID from a Stripe invoice object.
     *
     * Handles both the legacy API (where subscription is a top-level field)
     * and API version 2026-06-24+ (where it moved to parent.subscription_details).
     *
     * @param object|null $stripeInvoice The invoice object from Stripe
     *
     * @return string|null The subscription ID (e.g. sub_123), or null if not found
     */
    private function extractSubscriptionId(?object $stripeInvoice): ?string
    {
        if ($stripeInvoice === null) {
            return null;
        }

        // Legacy API: top-level subscription field
        if (!empty($stripeInvoice->subscription)) {
            return $stripeInvoice->subscription;
        }

        // New API (2026-06-24+): nested under parent.subscription_details
        if (!empty($stripeInvoice->parent->subscription_details->subscription)) {
            return $stripeInvoice->parent->subscription_details->subscription;
        }

        // Fallback: check line items for subscription reference
        if (!empty($stripeInvoice->lines->data[0]->parent->subscription_item_details->subscription)) {
            return $stripeInvoice->lines->data[0]->parent->subscription_item_details->subscription;
        }

        return null;
    }

    private function escapeStripeSearchValue(string $value): string
    {
        return str_replace(['\\', '\''], ['\\\\', '\\\''], $value);
    }

    private function getOrCreateCustomer(Invoice $invoice): Stripe\Customer
    {
        $validatedEmail = filter_var($invoice->getBuyerEmail(), FILTER_VALIDATE_EMAIL);

        if ($validatedEmail !== false) {
            $customers = $this->stripe->customers->search([
                'query' => "email:'" . $this->escapeStripeSearchValue($validatedEmail) . "'",
                'limit' => 1,
            ]);
        } else {
            $customers = (object) ['data' => []];
        }

        if (count($customers->data) > 0) {
            return $customers->data[0];
        }

        return $this->stripe->customers->create([
            'email' => $invoice->getBuyerEmail(),
            'name' => trim($invoice->getBuyerFirstName() . ' ' . $invoice->getBuyerLastName()),
            'address' => [
                'line1' => $invoice->getBuyerAddress(),
                'city' => $invoice->getBuyerCity(),
                'state' => $invoice->getBuyerState(),
                'postal_code' => $invoice->getBuyerZip(),
                'country' => $invoice->getBuyerCountry(),
            ],
        ]);
    }

    private function createStripeSubscription(Stripe\Customer $customer, Stripe\SetupIntent $setupIntent, Invoice $invoice): Stripe\Subscription
    {
        $product = $this->getOrCreateProduct($invoice);
        $price = $this->getOrCreatePrice($product, $invoice);

        return $this->stripe->subscriptions->create([
            'customer' => $customer->id,
            'items' => [[
                'price' => $price->id,
            ]],
            'default_payment_method' => $setupIntent->payment_method,
            'description' => $this->getInvoiceTitle($invoice),
            'metadata' => [
                'invoice_id' => (string) $invoice->getId(),
                'client_id' => (string) $invoice->getClientId(),
                'gateway_id' => (string) $this->config['gateway_id'],
            ],
        ], ['idempotency_key' => 'sub_invoice_' . $invoice->getId()]);
    }

    private function getOrCreateProduct(Invoice $invoice): Stripe\Product
    {
        $invoiceItems = $this->di['em']->getConnection()->fetchAllAssociative(
            'SELECT title FROM invoice_item WHERE invoice_id = :invoice_id',
            ['invoice_id' => $invoice->getId()]
        );

        if (empty($invoiceItems)) {
            throw new RuntimeException('No invoice items found for invoice ID: ' . $invoice->getId());
        }

        $productName = $invoiceItems[0]['title'];

        $products = $this->stripe->products->search([
            'query' => "name:'" . $this->escapeStripeSearchValue($productName) . "'",
            'limit' => 1,
        ]);

        if (count($products->data) > 0) {
            return $products->data[0];
        }

        return $this->stripe->products->create([
            'name' => $productName,
            'description' => $this->getInvoiceTitle($invoice),
        ]);
    }

    private function getOrCreatePrice(Stripe\Product $product, Invoice $invoice): Stripe\Price
    {
        $amount = $this->getAmountInCents($invoice);
        $currency = strtolower($invoice->getCurrency());
        $recurring = $this->getStripeRecurringParams(
            $this->getSubscriptionPeriodForInvoice($invoice)
        );

        // Stripe's price list filter only supports 'interval' (not 'interval_count') under
        // 'recurring', so the match below must also compare interval_count explicitly -
        // otherwise a monthly price could be reused for a quarterly/yearly one with the
        // same unit_amount.
        $prices = $this->stripe->prices->all([
            'product' => $product->id,
            'recurring' => ['interval' => $recurring['interval']],
            'currency' => $currency,
            'limit' => 100,
        ]);

        foreach ($prices->data as $existingPrice) {
            if ($existingPrice->unit_amount === $amount && ($existingPrice->recurring->interval_count ?? null) === $recurring['interval_count']) {
                return $existingPrice;
            }
        }

        return $this->stripe->prices->create([
            'product' => $product->id,
            'unit_amount' => $amount,
            'currency' => $currency,
            'recurring' => $recurring,
        ]);
    }

    private function getSubscriptionPeriodForInvoice(Invoice $invoice): string
    {
        $subscriptionService = $this->di['mod_service']('Invoice', 'Subscription');
        $period = $subscriptionService->getSubscriptionPeriod($invoice);

        return $period ?? '1M';
    }

    /**
     * Converts a billing period code (e.g. "1M", "3Y", "45D") into Stripe's recurring price
     * parameters. Stripe caps how large interval_count can be per unit, so periods that
     * exceed those caps (billing periods allow up to 5 years) are rejected outright
     * rather than silently mis-billed.
     *
     * @see https://docs.stripe.com/api/prices/create#create_price-recurring-interval_count
     *
     * @return array{interval: string, interval_count: int}
     */
    private function getStripeRecurringParams(string $periodCode): array
    {
        $period = new Period($periodCode);
        $interval = $this->convertPeriodToStripe($period);
        $intervalCount = $period->getQty();

        $maxIntervalCount = match ($interval) {
            'day' => 1095,
            'week' => 156,
            'month' => 36,
            'year' => 3,
            default => 1,
        };

        if ($intervalCount > $maxIntervalCount) {
            throw new Payment_Exception('The billing period ":period" is not supported by the Stripe payment gateway. The maximum supported interval is :max :interval(s)', [':period' => $periodCode, ':max' => $maxIntervalCount, ':interval' => $interval]);
        }

        return [
            'interval' => $interval,
            'interval_count' => $intervalCount,
        ];
    }

    private function convertPeriodToStripe(Period $period): string
    {
        return match ($period->getUnit()) {
            Period::UNIT_DAY => 'day',
            Period::UNIT_WEEK => 'week',
            Period::UNIT_MONTH => 'month',
            Period::UNIT_YEAR => 'year',
            default => 'month',
        };
    }

    private function getClientFromTransaction(Transaction $tx, Stripe\PaymentIntent $charge): Client
    {
        $clientId = (int) ($charge->metadata->client_id ?? 0);

        if ($clientId > 0) {
            try {
                return $this->di['em']->getRepository(Client::class)->find($clientId)
                    ?? throw new FOSSBilling\Exception\InformationException('Client not found');
            } catch (FOSSBilling\Exception\BaseException $e) {
                throw new Payment_Exception('Unable to load client for transaction: :msg', [':msg' => $e->getMessage()]);
            }
        }

        throw new Payment_Exception('Unable to determine client for transaction. No invoice or client metadata available.');
    }

    protected function _generateForm(Invoice $invoice): string
    {
        $intentParams = [
            'amount' => $this->getAmountInMinorUnits($invoice),
            'currency' => strtolower($invoice->getCurrency()),
            'description' => $this->getInvoiceTitle($invoice),
            'automatic_payment_methods' => ['enabled' => true],
            'receipt_email' => $invoice->getBuyerEmail(),
            'metadata' => [
                'client_id' => (string) $invoice->getClientId(),
                'invoice_id' => (string) $invoice->getId(),
                'gateway_id' => (string) $this->config['gateway_id'],
            ],
        ];
        $idempotencyKey = sprintf(
            'one_time_invoice_%d_gateway_%d_%s',
            $invoice->getId(),
            $this->config['gateway_id'],
            hash('sha256', json_encode($intentParams, JSON_THROW_ON_ERROR))
        );
        $intent = $this->stripe->paymentIntents->create($intentParams, ['idempotency_key' => $idempotencyKey]);

        $pubKey = ($this->config['test_mode']) ? $this->config['test_pub_key'] : $this->config['pub_key'];

        $form = '<form id="payment-form" data-secret=":intent_secret">
                <div class="loading" style="display:none;"><span>{% trans \'Loading ...\' %}</span></div>
                <script src="https://js.stripe.com/v3/"></script>

                    <div id="error-message">
                        <!-- Error messages will be displayed here -->
                    </div>
                    <div id="payment-element">
                        <!-- Stripe Elements will create form elements here -->
                    </div>

                    <button id="submit" class="btn btn-primary mt-2" style="margin-top: 0.5em;">Submit</button>

                <script>
                    const stripe = Stripe(\':pub_key\');

                    var stripeAppearance = {
                        theme: (document.documentElement.getAttribute(\'data-bs-theme\') === \'dark\'
                                || localStorage.getItem(\'theme\') === \'dark\')
                            ? \'night\'
                            : \'stripe\'
                    };

                    var elements = stripe.elements({
                        clientSecret: \':intent_secret\',
                        appearance: stripeAppearance,
                      });

                    var paymentElement = elements.create(\'payment\', {
                        billingDetails: {
                            name: \'never\',
                            email: \'never\',
                        },
                    });

                    paymentElement.mount(\'#payment-element\');

                    const form = document.getElementById(\'payment-form\');

                    form.addEventListener(\'submit\', async (event) => {
                    event.preventDefault();

                    const {error} = await stripe.confirmPayment({
                        elements,
                        confirmParams: {
                            return_url: \':callbackUrl&redirect=true&invoice_hash=:invoice_hash\',
                            payment_method_data: {
                                billing_details: {
                                    name: \':buyer_name\',
                                    email: \':buyer_email\',
                                },
                            },
                        },
                    });

                    if (error) {
                        const messageContainer = document.querySelector(\'#error-message\');
                        messageContainer.innerHTML = `<p class="alert alert-danger">${error.message}</p>`;
                    }
                    });

                  </script>
                </form>';

        $bindings = [
            ':pub_key' => $pubKey,
            ':intent_secret' => $intent->client_secret,
            ':buyer_email' => htmlspecialchars((string) $invoice->getBuyerEmail(), ENT_QUOTES, 'UTF-8'),
            ':buyer_name' => htmlspecialchars(trim($invoice->getBuyerFirstName() . ' ' . $invoice->getBuyerLastName()), ENT_QUOTES, 'UTF-8'),
            ':callbackUrl' => $this->config['notify_url'],
            ':invoice_hash' => $invoice->getHash(),
        ];

        return strtr($form, $bindings);
    }

    protected function _generateSubscriptionForm(Invoice $invoice): string
    {
        $customer = $this->getOrCreateCustomer($invoice);
        $product = $this->getOrCreateProduct($invoice);
        $price = $this->getOrCreatePrice($product, $invoice);

        $setupIntent = $this->stripe->setupIntents->create([
            'customer' => $customer->id,
            'payment_method_types' => ['card'],
            'usage' => 'off_session',
            'metadata' => [
                'invoice_id' => (string) $invoice->getId(),
                'price_id' => $price->id,
                'gateway_id' => (string) $this->config['gateway_id'],
            ],
        ]);

        $pubKey = ($this->config['test_mode']) ? $this->config['test_pub_key'] : $this->config['pub_key'];

        $form = '<form id="subscription-form" data-secret=":setup_intent_secret">
                <div class="loading" style="display:none;"><span>{% trans \'Loading ...\' %}</span></div>
                <script src="https://js.stripe.com/v3/"></script>

                    <div id="error-message">
                    </div>
                    <div id="payment-element">
                    </div>

                    <button id="submit" class="btn btn-primary mt-2" style="margin-top: 0.5em;">Subscribe</button>

                <script>
                    const stripe = Stripe(\':pub_key\');

                    var elements = stripe.elements({
                        clientSecret: \':setup_intent_secret\',
                    });

                    var paymentElement = elements.create(\'payment\', {
                        billingDetails: {
                            name: \'never\',
                            email: \'never\',
                        },
                    });

                    paymentElement.mount(\'#payment-element\');

                    const form = document.getElementById(\'subscription-form\');

                    form.addEventListener(\'submit\', async (event) => {
                        event.preventDefault();

                        const {error} = await stripe.confirmSetup({
                            elements,
                            confirmParams: {
                                return_url: \':callbackUrl&redirect=true&invoice_hash=:invoice_hash\',
                                payment_method_data: {
                                    billing_details: {
                                        name: \':buyer_name\',
                                        email: \':buyer_email\',
                                    },
                                },
                            },
                        });

                        if (error) {
                            const messageContainer = document.querySelector(\'#error-message\');
                            messageContainer.innerHTML = `<p class="alert alert-danger">${error.message}</p>`;
                        }
                    });
                </script>
            </form>';

        $bindings = [
            ':pub_key' => $pubKey,
            ':setup_intent_secret' => $setupIntent->client_secret,
            ':buyer_email' => htmlspecialchars($invoice->getBuyerEmail() ?? '', ENT_QUOTES, 'UTF-8'),
            ':buyer_name' => htmlspecialchars(trim($invoice->getBuyerFirstName() . ' ' . $invoice->getBuyerLastName()), ENT_QUOTES, 'UTF-8'),
            ':callbackUrl' => $this->config['notify_url'],
            ':invoice_hash' => $invoice->getHash(),
        ];

        return strtr($form, $bindings);
    }
}
