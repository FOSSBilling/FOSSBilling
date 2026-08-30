<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Box\Mod\Invoice\Entity\PayGatewayCustomer;
use Box\Mod\Invoice\Entity\PayGatewayProduct;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use FOSSBilling\Doctrine\EntityManagerFactory;
use Stripe\StripeClient;
use Symfony\Component\Intl\Currencies;

class Payment_Adapter_Stripe implements FOSSBilling\InjectionAwareInterface
{
    protected ?Pimple\Container $di = null;

    private StripeClient $stripe;

    /**
     * Overrides how newIsolatedEntityManager() obtains its EntityManager -
     * null in production. Tests substitute a mock via reflection instead of
     * exercising the real factory.
     */
    /** @phpstan-ignore property.unusedType (only ever set via reflection, from tests) */
    private ?Closure $entityManagerFactory = null;

    /**
     * Memoized isolated EntityManager - see newIsolatedEntityManager().
     */
    private ?Doctrine\ORM\EntityManagerInterface $isolatedEntityManager = null;

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

    /**
     * Building this opens a genuinely separate database connection - see
     * cacheGatewayCustomer() for why isolation from $this->di['em'] is
     * needed. EntityManagerFactory::create() opens a fresh connection on
     * every call (no shared/memoized connection on this branch), so simply
     * calling it again here - rather than reusing $this->di['em'] - is
     * already enough to get one.
     *
     * Memoized per adapter instance (one request each) so that resolving
     * both a customer and a product/price on the same checkout - the common
     * case for a client's first invoice - doesn't pay for that connection,
     * and EntityManagerFactory::create()'s metadata/cache bootstrap, twice.
     * A flush() that hits a unique constraint violation leaves Doctrine's
     * EntityManager closed (it does this itself, regardless of what callers
     * do), so the memoized instance is discarded and rebuilt once that
     * happens rather than reused into an unusable state.
     */
    private function newIsolatedEntityManager(): Doctrine\ORM\EntityManagerInterface
    {
        if ($this->isolatedEntityManager !== null && $this->isolatedEntityManager->isOpen()) {
            return $this->isolatedEntityManager;
        }

        return $this->isolatedEntityManager = $this->entityManagerFactory !== null
            ? ($this->entityManagerFactory)()
            : EntityManagerFactory::create();
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
        $invoiceModel = $this->di['db']->load('Invoice', $invoice_id);

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

    public function getAmountInCents(Model_Invoice $invoice): int
    {
        return $this->getAmountInMinorUnits($invoice);
    }

    public function getAmountInMinorUnits(Model_Invoice $invoice): int
    {
        $invoiceService = $this->di['mod_service']('Invoice');
        $amount = $invoiceService->getTotalWithTax($invoice);
        $multiplier = 10 ** $this->getCurrencyFractionDigits($invoice->currency);

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

    public function getInvoiceTitle(Model_Invoice $invoice): string
    {
        $invoiceItems = $this->di['db']->getAll('SELECT title FROM invoice_item WHERE invoice_id = :invoice_id', [':invoice_id' => $invoice->id]);

        $params = [
            ':id' => sprintf('%05s', $invoice->nr),
            ':serie' => $invoice->serie,
            ':title' => $invoiceItems[0]['title'] ?? '',
        ];
        $title = __trans('Payment for invoice :serie:id [:title]', $params);
        if (FOSSBilling\Tools::safeCount($invoiceItems) > 1) {
            $title = __trans('Payment for invoice :serie:id', $params);
        }

        return $title;
    }

    public function logError($e, Model_Transaction $tx): void
    {
        $body = $e->getJsonBody();
        $err = $body['error'];
        $tx->txn_status = $err['type'];
        $tx->error = $err['message'];
        $tx->status = Model_Transaction::STATUS_ERROR;
        $tx->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($tx);

        // @phpstan-ignore if.alwaysFalse (DEBUG is a runtime constant that may be true during debugging)
        if (DEBUG) {
            error_log(json_encode($e->getJsonBody()));
        }

        throw new Exception($tx->error);
    }

    public function processTransaction(FOSSBilling\Api\Proxy $api_admin, int $id, array $data, int $gateway_id): void
    {
        $tx = $this->di['db']->getExistingModelById('Transaction', $id);

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

            throw new FOSSBilling\Exception('There was an error when processing the transaction');
        }
    }

    private function resolveInvoice(Model_Transaction $tx, array $data): ?Model_Invoice
    {
        if ($tx->invoice_id) {
            return $this->di['db']->getExistingModelById('Invoice', $tx->invoice_id);
        }
        if (isset($data['get']['invoice_id']) && $data['get']['invoice_id']) {
            $invoice = $this->di['db']->getExistingModelById('Invoice', $data['get']['invoice_id']);
            $tx->invoice_id = $invoice->id;

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

    private function processPaymentIntent(Model_Transaction $tx, ?Model_Invoice $invoice, array $data): void
    {
        $charge = $this->stripe->paymentIntents->retrieve($data['get']['payment_intent'], []);

        $this->withStripeObjectLock(
            $charge->id,
            (int) $tx->gateway_id,
            fn () => $this->processPaymentIntentUnderLock($tx, $invoice, $charge)
        );
    }

    private function processPaymentIntentUnderLock(Model_Transaction $tx, ?Model_Invoice $invoice, object $charge): void
    {
        $invoiceService = $this->di['mod_service']('Invoice');

        $tx->txn_status = $charge->status;
        $tx->txn_id = $charge->id;
        $tx->amount = $this->getAmountFromMinorUnits($charge->amount, $charge->currency);
        $tx->currency = $charge->currency;
        $tx->type = Payment_Transaction::TXTYPE_PAYMENT;

        // Stripe may deliver the webhook before redirecting the customer.
        // Keep that transaction instead of recording the PaymentIntent twice.
        $existing = $this->di['db']->findOne(
            'Transaction',
            'txn_id = :txn_id AND gateway_id = :gateway_id AND id != :id AND status IN (:s1, :s2, :s3)',
            [
                ':txn_id' => $charge->id,
                ':gateway_id' => $tx->gateway_id,
                ':id' => $tx->id,
                ':s1' => Model_Transaction::STATUS_RECEIVED,
                ':s2' => Model_Transaction::STATUS_PROCESSING,
                ':s3' => Model_Transaction::STATUS_PROCESSED,
            ]
        );
        if ($existing instanceof Model_Transaction) {
            $this->di['db']->trash($tx);

            return;
        }

        if ($charge->status === 'succeeded') {
            if ($tx->status === Model_Transaction::STATUS_PROCESSED && empty($tx->error)) {
                $tx->updated_at = date('Y-m-d H:i:s');
                $this->di['db']->store($tx);

                return;
            }

            // Already-paid guard — prevents double-crediting when the
            // payment_intent.succeeded webhook processed the payment
            // before the redirect flow runs.
            if ($invoice instanceof Model_Invoice) {
                $fresh = $this->di['db']->findOne('Invoice', 'id = :id', [':id' => $invoice->id]);
                if ($fresh instanceof Model_Invoice && $fresh->status === Model_Invoice::STATUS_PAID) {
                    $tx->status = Model_Transaction::STATUS_PROCESSED;
                    $tx->updated_at = date('Y-m-d H:i:s');
                    $this->di['db']->store($tx);

                    return;
                }
            }

            $transactionService = $this->di['mod_service']('Invoice', 'Transaction');
            if (!$transactionService->claimForProcessing((int) $tx->id)) {
                return;
            }

            $tx->status = Model_Transaction::STATUS_PROCESSING;
        }

        $bd = [
            'amount' => $tx->amount,
            'description' => 'Stripe transaction ' . $charge->id,
            'type' => 'transaction',
            'rel_id' => $tx->id,
        ];

        if ($charge->status == 'succeeded' && $tx->status === Model_Transaction::STATUS_PROCESSING) {
            $clientService = $this->di['mod_service']('client');
            $client = $invoice
                ? $this->di['db']->getExistingModelById('Client', $invoice->client_id)
                : $this->getClientFromTransaction($tx, $charge);

            if ($invoice) {
                $expected = $invoiceService->getTotalWithTax($invoice);

                try {
                    $invoiceService->validatePaymentAmount((float) $tx->amount, $expected);
                } catch (FOSSBilling\Exception $e) {
                    $tx->status = Model_Transaction::STATUS_ERROR;
                    $tx->error = $e->getMessage();
                    $tx->updated_at = date('Y-m-d H:i:s');
                    $this->di['db']->store($tx);

                    throw $e;
                }
            }

            $clientService->addFunds($client, $bd['amount'], $bd['description'], $bd);

            if ($tx->invoice_id && $invoice && !$invoiceService->isInvoiceTypeDeposit($invoice)) {
                if (!$invoice->approved) {
                    $invoiceService->approveInvoice($invoice, ['use_credits' => false]);
                }
                $invoiceService->payInvoiceWithCredits($invoice);
            } elseif ($tx->invoice_id && $invoice && $invoiceService->isInvoiceTypeDeposit($invoice)) {
                $invoiceService->markAsPaid($invoice);
            } elseif (!$tx->invoice_id) {
                $invoiceService->doBatchPayWithCredits(['client_id' => $client->id]);
            }
        }

        $paymentStatus = match ($charge->status) {
            'succeeded' => Model_Transaction::STATUS_PROCESSED,
            'requires_action' => Model_Transaction::STATUS_RECEIVED,
            'requires_confirmation' => Model_Transaction::STATUS_RECEIVED,
            'requires_capture' => Model_Transaction::STATUS_RECEIVED,
            'processing' => Model_Transaction::STATUS_RECEIVED,
            'pending' => Model_Transaction::STATUS_RECEIVED,
            'requires_payment_method' => Model_Transaction::STATUS_ERROR,
            'canceled' => Model_Transaction::STATUS_ERROR,
            'failed' => Model_Transaction::STATUS_ERROR,
            default => Model_Transaction::STATUS_ERROR,
        };

        $tx->status = $paymentStatus;
        $tx->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($tx);
    }

    private function processSetupIntent($api_admin, Model_Transaction $tx, ?Model_Invoice $invoice, array $data, int $gateway_id): void
    {
        $setupIntent = $this->stripe->setupIntents->retrieve($data['get']['setup_intent'], []);

        $tx->txn_status = $setupIntent->status;
        $tx->txn_id = $setupIntent->id;

        // $invoice and $setupIntent are resolved independently, from separate
        // query parameters on the redirect URL (see resolveInvoice()) - unlike
        // the webhook flow, which derives the invoice from the setup intent's
        // own metadata and so can't have this mismatch. Without this check, a
        // request naming a victim's invoice alongside the requester's own
        // completed setup intent would subscribe/charge the requester but
        // credit and mark paid whatever invoice_id was supplied.
        if ($setupIntent->status === 'succeeded' && $invoice instanceof Model_Invoice && $this->setupIntentBelongsToInvoice($setupIntent, $invoice, $gateway_id)) {
            $customerId = $this->resolveSubscriptionCustomerId($setupIntent, $invoice);
            $priceId = $this->resolveSubscriptionPriceId($setupIntent, $invoice);

            try {
                $subscription = $this->createStripeSubscription($customerId, $priceId, $setupIntent, $invoice);
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
                    'customer' => $customerId,
                    'limit' => 1,
                ]);
                $subscription = count($subscriptions->data) > 0 ? $subscriptions->data[0] : null;

                if ($subscription === null) {
                    $tx->status = Model_Transaction::STATUS_PROCESSED;
                    $tx->updated_at = date('Y-m-d H:i:s');
                    $this->di['db']->store($tx);

                    return;
                }
            }

            $tx->s_id = $subscription->id;
            $tx->s_period = $this->getSubscriptionPeriodForInvoice($invoice);
            $tx->amount = $this->getAmountFromMinorUnits($this->getAmountInCents($invoice), $invoice->currency);
            $tx->currency = $invoice->currency;
            $tx->type = Payment_Transaction::TXTYPE_PAYMENT;
            $tx->status = Model_Transaction::STATUS_PROCESSED;

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
            $tx->status = Model_Transaction::STATUS_ERROR;
        }

        $tx->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($tx);
    }

    /**
     * Verify a setup intent was actually created for this invoice on this
     * gateway, per the metadata _generateSubscriptionForm() stamped onto it
     * when creating it - see processSetupIntent()'s call site for why this
     * check exists.
     */
    private function setupIntentBelongsToInvoice(Stripe\SetupIntent $setupIntent, Model_Invoice $invoice, int $gatewayId): bool
    {
        $metadataInvoiceId = $setupIntent->metadata->invoice_id ?? null;
        $metadataGatewayId = $setupIntent->metadata->gateway_id ?? null;

        return $metadataInvoiceId !== null
            && (int) $metadataInvoiceId === (int) $invoice->id
            && $metadataGatewayId !== null
            && (int) $metadataGatewayId === $gatewayId;
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
    private function processInitialSubscriptionPayment($api_admin, Model_Transaction $tx, Model_Invoice $invoice, Stripe\Subscription $subscription): void
    {
        // Already-paid guard — reload from DB to narrow the TOCTOU window when
        // the redirect flow and webhook handler race on the same subscription.
        $fresh = $this->di['db']->findOne('Invoice', 'id = :id', [':id' => $invoice->id]);
        if ($fresh instanceof Model_Invoice && $fresh->status === Model_Invoice::STATUS_PAID) {
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
            'id' => $invoice->client_id,
            'amount' => $this->getAmountFromMinorUnits(
                (int) ($latestInvoice->amount_paid ?? 0),
                (string) ($latestInvoice->currency ?? '')
            ),
            'description' => 'Stripe subscription initial payment ' . $latestInvoice->id,
            'type' => 'transaction',
            'rel_id' => $tx->id,
        ];

        $api_admin->client_balance_add_funds($bd);

        $invoiceService = $this->di['mod_service']('Invoice');
        if (!$invoiceService->isInvoiceTypeDeposit($invoice)) {
            if (!$invoice->approved) {
                $invoiceService->approveInvoice($invoice, ['use_credits' => false]);
            }
            $invoiceService->payInvoiceWithCredits($invoice);
        }
    }

    private function processWebhookEvent($api_admin, Model_Transaction $tx, array $data, int $gateway_id): void
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
            throw new FOSSBilling\Exception('Stripe webhook signing secret is not configured');
        }
        if (empty($sigHeader)) {
            throw new FOSSBilling\Exception('Missing Stripe-Signature header');
        }

        try {
            $event = Stripe\Webhook::constructEvent($rawBody, $sigHeader, $webhookSecret);
        } catch (UnexpectedValueException) {
            throw new FOSSBilling\Exception('Invalid Stripe webhook payload');
        } catch (Stripe\Exception\SignatureVerificationException) {
            throw new FOSSBilling\Exception('Invalid Stripe webhook signature');
        }

        $tx->txn_id = $event->id;
        $tx->txn_status = $event->type;

        // Delete transactions for events we don't handle to keep the
        // transactions list clean. Stripe sends many webhook events per
        // payment cycle (e.g. invoice.created, charge.succeeded) that are
        // not relevant to FOSSBilling.
        if (!in_array($event->type, self::HANDLED_EVENT_TYPES, true)) {
            $this->di['db']->trash($tx);

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
                $this->di['db']->trash($tx);

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

            throw new FOSSBilling\Exception('There was an error when processing the Stripe webhook');
        }

        if ($keepTransaction) {
            if ($tx->status !== Model_Transaction::STATUS_ERROR) {
                $tx->status = Model_Transaction::STATUS_PROCESSED;
            }
        } else {
            $this->di['db']->trash($tx);

            return;
        }

        $tx->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($tx);
    }

    private function eventBelongsToGateway(object $event, int $gatewayId): bool
    {
        $stripeObject = $event->data->object ?? null;
        if (!is_object($stripeObject)) {
            return false;
        }

        $eventGatewayId = $this->getGatewayIdFromStripeObject($stripeObject);

        // Older Stripe objects predate gateway_id metadata. Resolve them via
        // their local invoice/subscription association so upgrades do not
        // break in-flight payments or existing recurring subscriptions.
        if ($eventGatewayId === null) {
            $eventGatewayId = $this->getInvoiceGatewayId($stripeObject->metadata->invoice_id ?? null);
        }

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

    private function handleSubscriptionCreated($api_admin, Model_Transaction $tx, object $event, int $gateway_id): bool
    {
        $stripeSubscription = $event->data->object;
        $invoiceId = $stripeSubscription->metadata->invoice_id ?? null;
        $clientId = $stripeSubscription->metadata->client_id ?? null;

        if (!$invoiceId || !$clientId) {
            return false;
        }

        $tx->invoice_id = (int) $invoiceId;

        // Subscription record is now created inline by processSetupIntent and
        // handleSetupIntentSucceededWebhook. This handler only serves as a
        // fallback if those flows didn't run (e.g. subscription created outside
        // FOSSBilling). Use the shared helper to avoid duplication.
        $invoice = $this->di['db']->getExistingModelById('Invoice', (int) $invoiceId);
        $this->createOrUpdateSubscription($api_admin, $invoice, $stripeSubscription, $gateway_id);

        return false;
    }

    private function handleSubscriptionUpdated($api_admin, Model_Transaction $tx, object $event): bool
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
            if (DEBUG) {
                error_log('Stripe subscription updated webhook: ' . $e->getMessage());
            }
        }

        return false;
    }

    private function handleSubscriptionDeleted($api_admin, Model_Transaction $tx, object $event): bool
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

    private function handleInvoicePaymentSucceeded($api_admin, Model_Transaction $tx, object $event, int $gateway_id): bool
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

    private function handleInvoicePaymentSucceededUnderLock($api_admin, Model_Transaction $tx, object $stripeInvoice, string $subscriptionId): bool
    {
        // Dedup: Stripe sends both invoice.payment_succeeded and invoice.paid for
        // the same payment. Use the Stripe invoice ID as the shared natural key so
        // whichever event arrives second sees the first is already processing/done.
        $tx->txn_id = $stripeInvoice->id;
        $existing = $this->di['db']->findOne(
            'Transaction',
            'txn_id = :txn_id AND status IN (:s1, :s2) AND id != :id',
            [':txn_id' => $stripeInvoice->id, ':s1' => Model_Transaction::STATUS_PROCESSING, ':s2' => Model_Transaction::STATUS_PROCESSED, ':id' => $tx->id]
        );
        if ($existing instanceof Model_Transaction) {
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
            $tx->invoice_id = (int) $invoiceId;
            $this->di['db']->store($tx);
        }

        $isInitialPayment = ($stripeInvoice->billing_reason ?? '') === 'subscription_create';

        // Single DB fetch covers: (a) skip if already paid, (b) billing_reason fallback.
        if ($invoiceId) {
            $existingInvoice = $this->di['db']->findOne('Invoice', 'id = :id', [':id' => (int) $invoiceId]);
            if ($existingInvoice instanceof Model_Invoice) {
                // Skip if already paid — redirect flow may have processed it first.
                if ($existingInvoice->status === Model_Invoice::STATUS_PAID) {
                    return false;
                }
                // Fallback: billing_reason inconclusive but original invoice still unpaid.
                if (!$isInitialPayment && $existingInvoice->status === Model_Invoice::STATUS_UNPAID) {
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
            'rel_id' => $tx->id,
        ];

        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');
        if (!$transactionService->claimForProcessing($tx->id)) {
            return false;
        }

        $tx->type = Payment_Transaction::TXTYPE_PAYMENT;
        $tx->amount = $bd['amount'];
        $tx->currency = strtoupper((string) ($stripeInvoice->currency ?? ''));

        $api_admin->client_balance_add_funds($bd);

        $invoiceService = $this->di['mod_service']('Invoice');

        if ($isInitialPayment && $invoiceId) {
            $invoiceModel = $this->di['db']->getExistingModelById('Invoice', (int) $invoiceId);

            if (!$invoiceService->isInvoiceTypeDeposit($invoiceModel)) {
                if (!$invoiceModel->approved) {
                    $invoiceService->approveInvoice($invoiceModel, ['use_credits' => false]);
                }
                $invoiceService->payInvoiceWithCredits($invoiceModel);
            }
        } else {
            $renewalInvoice = $invoiceService->generateRenewalInvoiceForSubscriptionPayment(
                $stripeSubscription->id,
                (int) $clientId
            );

            if ($renewalInvoice instanceof Model_Invoice) {
                $tx->invoice_id = $renewalInvoice->id;
                if (!$invoiceService->isInvoiceTypeDeposit($renewalInvoice)) {
                    $invoiceService->payInvoiceWithCredits($renewalInvoice);
                }
            } else {
                $api_admin->invoice_batch_pay_with_credits(['client_id' => $clientId]);
            }
        }

        return true;
    }

    private function handleInvoicePaymentFailed($api_admin, Model_Transaction $tx, object $event): bool
    {
        $stripeInvoice = $this->resolveStripeInvoice($event->data->object);

        $subscriptionId = $this->extractSubscriptionId($stripeInvoice);

        if ($stripeInvoice === null || $subscriptionId === null) {
            return false;
        }

        try {
            $this->updateSubscriptionStatusFromGateway($api_admin, $subscriptionId, 'canceled');
        } catch (Exception $e) {
            if (DEBUG) {
                error_log('Stripe invoice payment failed webhook: ' . $e->getMessage());
            }
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
    private function handlePaymentIntentSucceededWebhook($api_admin, Model_Transaction $tx, object $event, int $gateway_id): bool
    {
        $paymentIntent = $event->data->object;

        return $this->withStripeObjectLock(
            $paymentIntent->id,
            $gateway_id,
            fn (): bool => $this->handlePaymentIntentSucceededWebhookUnderLock($tx, $paymentIntent, $gateway_id)
        );
    }

    private function handlePaymentIntentSucceededWebhookUnderLock(Model_Transaction $tx, object $paymentIntent, int $gateway_id): bool
    {
        // Set transaction metadata from the PaymentIntent
        $tx->txn_id = $paymentIntent->id;
        $tx->txn_status = $paymentIntent->status;
        $tx->amount = $this->getAmountFromMinorUnits($paymentIntent->amount, $paymentIntent->currency);
        $tx->currency = $paymentIntent->currency;
        $tx->type = Payment_Transaction::TXTYPE_PAYMENT;

        // Dedup: skip if already processed or currently being processed via
        // the redirect flow. The redirect transaction stores txn_id = PaymentIntent ID.
        // We check both PROCESSING and PROCESSED to catch the race where the
        // redirect flow is mid-processing when the webhook arrives.
        $existing = $this->di['db']->findOne(
            'Transaction',
            'txn_id = :txn_id AND gateway_id = :gateway_id AND id != :id AND status IN (:s1, :s2)',
            [
                ':txn_id' => $paymentIntent->id,
                ':gateway_id' => $gateway_id,
                ':id' => $tx->id,
                ':s1' => Model_Transaction::STATUS_PROCESSING,
                ':s2' => Model_Transaction::STATUS_PROCESSED,
            ]
        );
        if ($existing instanceof Model_Transaction) {
            $tx->invoice_id = $existing->invoice_id;

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
            $tx->invoice_id = (int) $invoiceId;
        }

        // Persist the PaymentIntent ID while the lock is held so a redirect
        // waiting on the same key observes this transaction after release.
        $this->di['db']->store($tx);

        if ($paymentIntent->status !== 'succeeded') {
            return false;
        }

        $invoice = $invoiceId ? $this->di['db']->getExistingModelById('Invoice', (int) $invoiceId) : null;

        // Delegate to the shared payment processing logic
        $this->applyOneTimePayment($tx, $invoice, $paymentIntent);

        return true;
    }

    private function withStripeObjectLock(string $objectId, int $gatewayId, callable $callback): mixed
    {
        $lockName = 'fb:stripe:' . substr(hash('sha256', $gatewayId . ':' . $objectId), 0, 54);
        $waitStartedAt = hrtime(true);
        $acquired = (int) $this->di['dbal']->fetchOne(
            'SELECT GET_LOCK(:lock_name, 10)',
            ['lock_name' => $lockName]
        );

        if ($acquired !== 1) {
            $waitDurationMs = (hrtime(true) - $waitStartedAt) / 1_000_000;
            // Box_Log::__call() vsprintf()s the message against the remaining arguments,
            // so pass the placeholders positionally rather than pre-formatting the string.
            $this->di['logger']->warning(
                'Timed out after %.1f ms waiting for Stripe object lock %s',
                $waitDurationMs,
                $lockName
            );

            throw new FOSSBilling\Exception('Timed out waiting to process this Stripe payment');
        }

        try {
            return $callback();
        } finally {
            $this->di['dbal']->fetchOne('SELECT RELEASE_LOCK(:lock_name)', ['lock_name' => $lockName]);
        }
    }

    private function handlePaymentIntentFailedWebhook($api_admin, Model_Transaction $tx, object $event): bool
    {
        $paymentIntent = $event->data->object;
        $tx->txn_id = $paymentIntent->id;
        $tx->txn_status = $paymentIntent->status;
        $tx->status = Model_Transaction::STATUS_ERROR;
        $tx->error = 'Payment failed via webhook';

        return true;
    }

    /**
     * Handle setup_intent.succeeded webhook for subscription creation.
     *
     * Provides reliability when the customer doesn't return via the redirect
     * flow. Uses the subscription creation idempotency key to prevent
     * duplicates if the redirect also fires.
     */
    private function handleSetupIntentSucceededWebhook($api_admin, Model_Transaction $tx, object $event, int $gateway_id): bool
    {
        $setupIntent = $event->data->object;

        $tx->txn_id = $setupIntent->id;
        $tx->txn_status = $setupIntent->status;

        if ($setupIntent->status !== 'succeeded') {
            $tx->status = Model_Transaction::STATUS_ERROR;
            $tx->updated_at = date('Y-m-d H:i:s');
            $this->di['db']->store($tx);

            return false;
        }

        // Dedup: skip if already processed or being processed via the redirect flow.
        $existing = $this->di['db']->findOne(
            'Transaction',
            'txn_id = :txn_id AND status IN (:s1, :s2)',
            [':txn_id' => $setupIntent->id, ':s1' => Model_Transaction::STATUS_PROCESSING, ':s2' => Model_Transaction::STATUS_PROCESSED]
        );
        if ($existing instanceof Model_Transaction) {
            $tx->invoice_id = $existing->invoice_id;

            return false;
        }

        $invoiceId = $setupIntent->metadata->invoice_id ?? null;
        if (!$invoiceId) {
            return false;
        }

        $tx->invoice_id = (int) $invoiceId;
        $this->di['db']->store($tx);

        $invoice = $this->di['db']->getExistingModelById('Invoice', (int) $invoiceId);
        $customerId = $this->resolveSubscriptionCustomerId($setupIntent, $invoice);
        $priceId = $this->resolveSubscriptionPriceId($setupIntent, $invoice);

        // createStripeSubscription uses an idempotency key based on the
        // invoice ID, so this is safe even if the redirect flow races.
        // If both fire simultaneously, Stripe returns the same subscription
        // to the first and a "concurrent request" error to the second.
        try {
            $subscription = $this->createStripeSubscription($customerId, $priceId, $setupIntent, $invoice);
        } catch (Stripe\Exception\ApiErrorException $e) {
            // Only treat idempotency conflicts as the expected race with the
            // redirect flow; rethrow all other API errors (card declined, auth
            // failures, etc.) so they surface to the caller.
            if ($e->getStripeCode() !== 'idempotency_key_in_use') {
                throw $e;
            }

            if (DEBUG) {
                error_log('Stripe setup_intent webhook: subscription creation deferred to redirect flow: ' . $e->getMessage());
            }

            return false;
        }

        $tx->s_id = $subscription->id;
        $tx->s_period = $this->getSubscriptionPeriodForInvoice($invoice);
        $tx->amount = $this->getAmountFromMinorUnits($this->getAmountInCents($invoice), $invoice->currency);
        $tx->currency = $invoice->currency;
        $tx->type = Payment_Transaction::TXTYPE_PAYMENT;
        $tx->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($tx);

        // Create the FOSSBilling subscription record immediately.
        $this->createOrUpdateSubscription($api_admin, $invoice, $subscription, $gateway_id);

        // Process the initial payment immediately so the invoice is paid
        // even if the redirect flow hasn't completed yet.
        $this->processInitialSubscriptionPayment($api_admin, $tx, $invoice, $subscription);

        return true;
    }

    private function handleSetupIntentFailedWebhook($api_admin, Model_Transaction $tx, object $event): bool
    {
        $setupIntent = $event->data->object;
        $tx->txn_id = $setupIntent->id;
        $tx->txn_status = $setupIntent->status;
        $tx->status = Model_Transaction::STATUS_ERROR;
        $tx->error = 'Setup Intent failed via webhook';

        return true;
    }

    /**
     * Create a FOSSBilling subscription record from a Stripe subscription.
     * Called from the redirect flow and webhook handler so the subscription
     * appears immediately, without depending on the customer.subscription.created
     * webhook event.
     */
    private function createOrUpdateSubscription($api_admin, Model_Invoice $invoice, object $subscription, int $gateway_id): void
    {
        $existing = $this->di['db']->findOne('Subscription', 'sid = :sid', [':sid' => $subscription->id]);
        if ($existing instanceof Model_Subscription) {
            return;
        }

        $sd = [
            'client_id' => $invoice->client_id,
            'gateway_id' => $gateway_id,
            'currency' => strtoupper($invoice->currency),
            'sid' => $subscription->id,
            'status' => 'active',
            'period' => $this->getSubscriptionPeriodForInvoice($invoice),
            'amount' => $this->getAmountFromMinorUnits($this->getAmountInCents($invoice), $invoice->currency),
            'rel_type' => 'invoice',
            'rel_id' => $invoice->id,
        ];

        try {
            $api_admin->invoice_subscription_create($sd);
        } catch (Exception $e) {
            if (DEBUG) {
                error_log('Failed to create FOSSBilling subscription for ' . $subscription->id . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Shared logic for applying a one-time payment to a client balance and
     * invoice. Used by both the redirect flow (processPaymentIntent) and the
     * payment_intent.succeeded webhook handler.
     */
    private function applyOneTimePayment(Model_Transaction $tx, ?Model_Invoice $invoice, object $charge): void
    {
        // Reload the invoice from the database to get the freshest status.
        // This narrows the TOCTOU race window when the redirect flow and
        // webhook process the same payment concurrently.
        if ($invoice instanceof Model_Invoice) {
            $fresh = $this->di['db']->findOne('Invoice', 'id = :id', [':id' => $invoice->id]);
            if ($fresh instanceof Model_Invoice) {
                $invoice = $fresh;
            }
        }

        // Skip if the invoice is already paid — prevents double-crediting
        // when the webhook arrives after the redirect flow.
        if ($invoice instanceof Model_Invoice && $invoice->status === Model_Invoice::STATUS_PAID) {
            return;
        }

        $invoiceService = $this->di['mod_service']('Invoice');

        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');
        if (!$transactionService->claimForProcessing((int) $tx->id)) {
            return;
        }

        $tx->status = Model_Transaction::STATUS_PROCESSING;

        $clientService = $this->di['mod_service']('client');
        $client = $invoice
            ? $this->di['db']->getExistingModelById('Client', $invoice->client_id)
            : $this->getClientFromTransaction($tx, $charge);

        if ($invoice) {
            $expected = $invoiceService->getTotalWithTax($invoice);

            try {
                $invoiceService->validatePaymentAmount((float) $tx->amount, $expected);
            } catch (FOSSBilling\Exception $e) {
                $tx->status = Model_Transaction::STATUS_ERROR;
                $tx->error = $e->getMessage();
                $tx->updated_at = date('Y-m-d H:i:s');
                $this->di['db']->store($tx);

                throw $e;
            }
        }

        $bd = [
            'amount' => $tx->amount,
            'description' => 'Stripe transaction ' . $charge->id,
            'type' => 'transaction',
            'rel_id' => $tx->id,
        ];

        $clientService->addFunds($client, $bd['amount'], $bd['description'], $bd);

        if ($tx->invoice_id && $invoice && !$invoiceService->isInvoiceTypeDeposit($invoice)) {
            if (!$invoice->approved) {
                $invoiceService->approveInvoice($invoice, ['use_credits' => false]);
            }
            $invoiceService->payInvoiceWithCredits($invoice);
        } elseif ($tx->invoice_id && $invoice && $invoiceService->isInvoiceTypeDeposit($invoice)) {
            $invoiceService->markAsPaid($invoice);
        } elseif (!$tx->invoice_id) {
            $invoiceService->doBatchPayWithCredits(['client_id' => $client->id]);
        }

        $tx->status = Model_Transaction::STATUS_PROCESSED;
        $tx->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($tx);
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

    /**
     * Resolve a Stripe customer ID for this invoice's client, preferring a
     * locally cached ID over asking Stripe - see the class-level fix this
     * belongs to (#4228/#4231) for why: two callers each re-deriving the
     * same customer via Stripe's Search API, which is only eventually
     * consistent, could mint duplicate customers under a race (webhook vs.
     * redirect, a reloaded checkout page, a duplicate tab), which then
     * submit mismatched parameters under the same subscription idempotency
     * key and get a hard idempotency_error.
     */
    private function getOrCreateCustomer(Model_Invoice $invoice): string
    {
        $gatewayId = (int) $this->config['gateway_id'];
        $clientId = (int) $invoice->client_id;

        $cached = $this->di['em']->getRepository(PayGatewayCustomer::class)
            ->findOneByGatewayAndClient($gatewayId, $clientId);
        if ($cached instanceof PayGatewayCustomer) {
            return $cached->getExternalCustomerId();
        }

        $customerId = $this->resolveCustomerIdFromStripe($invoice);

        return $this->cacheGatewayCustomer($gatewayId, $clientId, $customerId);
    }

    /**
     * Resolve a Stripe customer ID for an invoice that isn't cached locally
     * yet - via a one-time Search API lookup (so a buyer who already has a
     * Stripe customer from before this cache existed doesn't get a second
     * one created for them), falling back to creating a new customer. Only
     * ever reached on a cache miss, so the Search API's eventual
     * consistency isn't a steady-state concern here the way it was when
     * every checkout depended on it for dedup.
     */
    private function resolveCustomerIdFromStripe(Model_Invoice $invoice): string
    {
        $validatedEmail = filter_var($invoice->buyer_email, FILTER_VALIDATE_EMAIL);

        if ($validatedEmail !== false) {
            $customers = $this->stripe->customers->search([
                'query' => "email:'" . $this->escapeStripeSearchValue($validatedEmail) . "'",
                'limit' => 1,
            ]);

            if (count($customers->data) > 0) {
                return $customers->data[0]->id;
            }
        }

        return $this->stripe->customers->create([
            'email' => $invoice->buyer_email,
            'name' => trim($invoice->buyer_first_name . ' ' . $invoice->buyer_last_name),
            'address' => [
                'line1' => $invoice->buyer_address,
                'city' => $invoice->buyer_city,
                'state' => $invoice->buyer_state,
                'postal_code' => $invoice->buyer_zip,
                'country' => $invoice->buyer_country,
            ],
        ])->id;
    }

    /**
     * Persist a resolved customer ID so future lookups for this (gateway,
     * client) pair are a local read, and return the ID that ends up
     * cached. Uses its own isolated EntityManager (see
     * newIsolatedEntityManager()) rather than the shared $di['em'] - a
     * unique constraint violation would otherwise leave the EntityManager
     * other callers in this request rely on (e.g. to flush the
     * transaction/invoice being processed) in a closed, unusable state.
     *
     * Two requests for the same (gateway, client) can both miss the cache
     * read in getOrCreateCustomer() and each resolve their own Stripe
     * customer before either persists here. The unique constraint on
     * (pay_gateway_id, client_id) stops both rows from existing; the loser
     * re-reads and returns the winner's ID instead of its own, so every
     * caller converges on one customer - the loser's own Stripe customer
     * just ends up orphaned, unused but harmless.
     */
    private function cacheGatewayCustomer(int $gatewayId, int $clientId, string $externalCustomerId): string
    {
        $em = $this->newIsolatedEntityManager();

        try {
            $record = new PayGatewayCustomer();
            $record->setPayGatewayId($gatewayId);
            $record->setClientId($clientId);
            $record->setExternalCustomerId($externalCustomerId);

            $em->persist($record);
            $em->flush();

            return $externalCustomerId;
        } catch (UniqueConstraintViolationException) {
            // flush() leaves $em itself closed at this point (Doctrine's own
            // behavior on a failed commit) - reads still work fine on a
            // closed EntityManager, only further writes would need
            // newIsolatedEntityManager() to hand out a fresh one.
            /** @var Box\Mod\Invoice\Repository\PayGatewayCustomerRepository $repository */
            $repository = $em->getRepository(PayGatewayCustomer::class);
            $winner = $repository->findOneByGatewayAndClient($gatewayId, $clientId);

            return $winner instanceof PayGatewayCustomer ? $winner->getExternalCustomerId() : $externalCustomerId;
        }
    }

    private function createStripeSubscription(string $customerId, string $priceId, Stripe\SetupIntent $setupIntent, Model_Invoice $invoice): Stripe\Subscription
    {
        $subscriptionParams = [
            'customer' => $customerId,
            'items' => [[
                'price' => $priceId,
            ]],
            'default_payment_method' => $setupIntent->payment_method,
            'description' => $this->getInvoiceTitle($invoice),
            'metadata' => [
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id,
                'gateway_id' => (string) $this->config['gateway_id'],
            ],
        ];
        // Hashing the resolved params into the key, like _generateForm() and
        // generateSubscriptionFormUnderLock() do, covers the same invoice
        // producing setup intents with different customers/prices over time
        // (e.g. a stale setup intent's late webhook arriving after a reload
        // already completed checkout with a different one) - reusing a plain
        // invoice-id key across that would hit an idempotency_error instead
        // of the expected/handled idempotency_key_in_use race.
        $idempotencyKey = sprintf(
            'sub_invoice_%d_gateway_%d_%s',
            $invoice->id,
            $this->config['gateway_id'],
            hash('sha256', json_encode($subscriptionParams, JSON_THROW_ON_ERROR))
        );

        return $this->stripe->subscriptions->create($subscriptionParams, ['idempotency_key' => $idempotencyKey]);
    }

    /**
     * Resolve the Stripe customer to subscribe, preferring the customer
     * already attached to the setup intent (set once in
     * _generateSubscriptionForm()) over asking getOrCreateCustomer() again.
     *
     * Both the redirect flow (processSetupIntent) and the webhook flow
     * (handleSetupIntentSucceededWebhook) can run for the same invoice, and
     * createStripeSubscription() relies on both submitting identical
     * parameters under the same idempotency key. getOrCreateCustomer() is
     * safe to call from both now (see its docblock), but reading the ID
     * already sitting on the setup intent both flows already fetched skips
     * a redundant cache lookup for no behavioral difference.
     */
    private function resolveSubscriptionCustomerId(Stripe\SetupIntent $setupIntent, Model_Invoice $invoice): string
    {
        if (!empty($setupIntent->customer)) {
            return is_string($setupIntent->customer) ? $setupIntent->customer : $setupIntent->customer->id;
        }

        return $this->getOrCreateCustomer($invoice);
    }

    /**
     * Resolve the Stripe price to subscribe to, preferring the price ID
     * already stored in the setup intent's metadata (set once in
     * _generateSubscriptionForm()) over asking getOrCreatePriceId() again -
     * see resolveSubscriptionCustomerId() for why that's just avoiding a
     * redundant lookup rather than a correctness requirement.
     */
    private function resolveSubscriptionPriceId(Stripe\SetupIntent $setupIntent, Model_Invoice $invoice): string
    {
        $priceId = $setupIntent->metadata->price_id ?? null;
        if (!empty($priceId)) {
            return $priceId;
        }

        return $this->getOrCreatePriceId($invoice);
    }

    /**
     * Resolve the Stripe price ID for an invoice's product, preferring a
     * locally cached ID (see {@see PayGatewayProduct}) over asking Stripe -
     * for the same reason, and via the same pattern, as
     * {@see getOrCreateCustomer()}.
     */
    private function getOrCreatePriceId(Model_Invoice $invoice): string
    {
        $gatewayId = (int) $this->config['gateway_id'];
        $productName = $this->getInvoiceProductName($invoice);
        $amount = $this->getAmountInCents($invoice);
        $currency = strtolower($invoice->currency);
        $interval = $this->convertPeriodToStripe($this->getSubscriptionPeriodForInvoice($invoice));
        $cacheKey = $this->buildProductCacheKey($productName, $currency, $amount, $interval);

        $cached = $this->di['em']->getRepository(PayGatewayProduct::class)
            ->findOneByGatewayAndCacheKey($gatewayId, $cacheKey);
        if ($cached instanceof PayGatewayProduct) {
            return $cached->getExternalPriceId();
        }

        [$externalProductId, $externalPriceId] = $this->resolvePriceFromStripe($productName, $amount, $currency, $interval, $invoice);

        return $this->cacheGatewayProduct($gatewayId, $cacheKey, $productName, $externalProductId, $externalPriceId);
    }

    private function getInvoiceProductName(Model_Invoice $invoice): string
    {
        $invoiceItems = $this->di['db']->getAll(
            'SELECT title FROM invoice_item WHERE invoice_id = :invoice_id',
            [':invoice_id' => $invoice->id]
        );

        if (empty($invoiceItems)) {
            throw new RuntimeException('No invoice items found for invoice ID: ' . $invoice->id);
        }

        return $invoiceItems[0]['title'];
    }

    private function buildProductCacheKey(string $productName, string $currency, int $amount, string $interval): string
    {
        return hash('sha256', implode('|', [$productName, $currency, $amount, $interval]));
    }

    /**
     * Resolve a Stripe product/price pair that isn't cached locally yet -
     * via a one-time Search/List lookup, falling back to creating them.
     * Only ever reached on a cache miss - see resolveCustomerIdFromStripe().
     *
     * @return array{0: string, 1: string} the external product ID and price ID
     */
    private function resolvePriceFromStripe(string $productName, int $amount, string $currency, string $interval, Model_Invoice $invoice): array
    {
        $products = $this->stripe->products->search([
            'query' => "name:'" . $this->escapeStripeSearchValue($productName) . "'",
            'limit' => 1,
        ]);

        $product = count($products->data) > 0
            ? $products->data[0]
            : $this->stripe->products->create([
                'name' => $productName,
                'description' => $this->getInvoiceTitle($invoice),
            ]);

        $prices = $this->stripe->prices->all([
            'product' => $product->id,
            'recurring' => ['interval' => $interval],
            'currency' => $currency,
            'limit' => 100,
        ]);

        foreach ($prices->data as $existingPrice) {
            if ($existingPrice->unit_amount === $amount) {
                return [$product->id, $existingPrice->id];
            }
        }

        $price = $this->stripe->prices->create([
            'product' => $product->id,
            'unit_amount' => $amount,
            'currency' => $currency,
            'recurring' => ['interval' => $interval],
        ]);

        return [$product->id, $price->id];
    }

    /**
     * Persist a resolved product/price pair and return the price ID that
     * ends up cached - see cacheGatewayCustomer() for why this uses its own
     * isolated EntityManager and re-reads the winner on a unique constraint
     * violation.
     */
    private function cacheGatewayProduct(int $gatewayId, string $cacheKey, string $name, string $externalProductId, string $externalPriceId): string
    {
        $em = $this->newIsolatedEntityManager();

        try {
            $record = new PayGatewayProduct();
            $record->setPayGatewayId($gatewayId);
            $record->setCacheKey($cacheKey);
            $record->setName($name);
            $record->setExternalProductId($externalProductId);
            $record->setExternalPriceId($externalPriceId);

            $em->persist($record);
            $em->flush();

            return $externalPriceId;
        } catch (UniqueConstraintViolationException) {
            /** @var Box\Mod\Invoice\Repository\PayGatewayProductRepository $repository */
            $repository = $em->getRepository(PayGatewayProduct::class);
            $winner = $repository->findOneByGatewayAndCacheKey($gatewayId, $cacheKey);

            return $winner instanceof PayGatewayProduct ? $winner->getExternalPriceId() : $externalPriceId;
        }
    }

    private function getSubscriptionPeriodForInvoice(Model_Invoice $invoice): string
    {
        $subscriptionService = $this->di['mod_service']('Invoice', 'Subscription');
        $period = $subscriptionService->getSubscriptionPeriod($invoice);

        return $period ?? '1M';
    }

    private function convertPeriodToStripe(string $period): string
    {
        $unit = preg_replace('/[^A-Za-z]/', '', $period);

        return match (strtoupper((string) $unit)) {
            'D' => 'day',
            'W' => 'week',
            'M' => 'month',
            'Y' => 'year',
            default => 'month',
        };
    }

    private function getClientFromTransaction(Model_Transaction $tx, Stripe\PaymentIntent $charge): Model_Client
    {
        $clientId = (int) ($charge->metadata->client_id ?? 0);

        if ($clientId > 0) {
            try {
                return $this->di['db']->getExistingModelById('Client', $clientId);
            } catch (FOSSBilling\Exception $e) {
                throw new Payment_Exception('Unable to load client for transaction: :msg', [':msg' => $e->getMessage()]);
            }
        }

        throw new Payment_Exception('Unable to determine client for transaction. No invoice or client metadata available.');
    }

    protected function _generateForm(Model_Invoice $invoice): string
    {
        $intentParams = [
            'amount' => $this->getAmountInMinorUnits($invoice),
            'currency' => strtolower($invoice->currency),
            'description' => $this->getInvoiceTitle($invoice),
            'automatic_payment_methods' => ['enabled' => true],
            'receipt_email' => $invoice->buyer_email,
            'metadata' => [
                'client_id' => (string) $invoice->client_id,
                'invoice_id' => (string) $invoice->id,
                'gateway_id' => (string) $this->config['gateway_id'],
            ],
        ];
        $idempotencyKey = sprintf(
            'one_time_invoice_%d_gateway_%d_%s',
            $invoice->id,
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
            ':buyer_email' => htmlspecialchars((string) $invoice->buyer_email, ENT_QUOTES, 'UTF-8'),
            ':buyer_name' => htmlspecialchars(trim($invoice->buyer_first_name . ' ' . $invoice->buyer_last_name), ENT_QUOTES, 'UTF-8'),
            ':callbackUrl' => $this->config['notify_url'],
            ':invoice_hash' => $invoice->hash,
        ];

        return strtr($form, $bindings);
    }

    protected function _generateSubscriptionForm(Model_Invoice $invoice): string
    {
        // This is the sole point where the Stripe customer/product/price are
        // resolved for a subscription checkout, but the checkout page can be
        // loaded more than once for the same invoice (a reload, a duplicate
        // tab, the user pressing back and forward). getOrCreateCustomer()
        // and getOrCreatePriceId() are each individually safe to call from
        // two such loads at once (see their docblocks), but locking on the
        // invoice ID here still avoids two loads redundantly repeating the
        // same cache lookups and Stripe calls, and the idempotency key on
        // setupIntents->create() below makes a second load reuse the very
        // same setup intent instead of creating another one.
        return $this->withStripeObjectLock(
            'gen_subscription_form_invoice_' . $invoice->id,
            (int) $this->config['gateway_id'],
            fn (): string => $this->generateSubscriptionFormUnderLock($invoice)
        );
    }

    private function generateSubscriptionFormUnderLock(Model_Invoice $invoice): string
    {
        $customerId = $this->getOrCreateCustomer($invoice);
        $priceId = $this->getOrCreatePriceId($invoice);

        $setupIntentParams = [
            'customer' => $customerId,
            'payment_method_types' => ['card'],
            'usage' => 'off_session',
            'metadata' => [
                'invoice_id' => $invoice->id,
                'price_id' => $priceId,
                'gateway_id' => (string) $this->config['gateway_id'],
            ],
        ];
        // Hashing the resolved params into the key, like _generateForm() does for
        // one-time payments, covers the case where they legitimately differ between
        // two loads of the same invoice's checkout page (e.g. an admin edits the
        // invoice's items/amount between them) - reusing a plain invoice-id key
        // across that would hit the same idempotency_error this file exists to avoid.
        $idempotencyKey = sprintf(
            'setup_invoice_%d_gateway_%d_%s',
            $invoice->id,
            $this->config['gateway_id'],
            hash('sha256', json_encode($setupIntentParams, JSON_THROW_ON_ERROR))
        );
        $setupIntent = $this->stripe->setupIntents->create($setupIntentParams, ['idempotency_key' => $idempotencyKey]);

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
            ':buyer_email' => htmlspecialchars($invoice->buyer_email ?? '', ENT_QUOTES, 'UTF-8'),
            ':buyer_name' => htmlspecialchars(trim($invoice->buyer_first_name . ' ' . $invoice->buyer_last_name), ENT_QUOTES, 'UTF-8'),
            ':callbackUrl' => $this->config['notify_url'],
            ':invoice_hash' => $invoice->hash,
        ];

        return strtr($form, $bindings);
    }
}
