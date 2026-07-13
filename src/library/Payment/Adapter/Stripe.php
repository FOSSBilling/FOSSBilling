<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Stripe\StripeClient;
use Symfony\Component\Intl\Currencies;

class Payment_Adapter_Stripe implements FOSSBilling\InjectionAwareInterface
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
                    'text', [
                        'label' => 'Live Secret Key:',
                        'required_when' => ['enabled' => true, 'test_mode' => false],
                    ],
                ],
                'webhook_secret' => [
                    'text', [
                        'label' => 'Live Webhook signing secret:',
                        'required_when' => ['enabled' => true, 'test_mode' => false],
                    ],
                ],
                'test_pub_key' => [
                    'text', [
                        'label' => 'Test Publishable Key:',
                        'required_when' => ['enabled' => true, 'test_mode' => true],
                    ],
                ],
                'test_api_key' => [
                    'text', [
                        'label' => 'Test Secret Key:',
                        'required_when' => ['enabled' => true, 'test_mode' => true],
                    ],
                ],
                'test_webhook_secret' => [
                    'text', [
                        'label' => 'Test Webhook signing secret:',
                        'required_when' => ['enabled' => true, 'test_mode' => true],
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
        $invoiceService = $this->di['mod_service']('Invoice');
        $charge = $this->stripe->paymentIntents->retrieve($data['get']['payment_intent'], []);

        $tx->txn_status = $charge->status;
        $tx->txn_id = $charge->id;
        $tx->amount = $this->getAmountFromMinorUnits($charge->amount, $charge->currency);
        $tx->currency = $charge->currency;
        $tx->type = Payment_Transaction::TXTYPE_PAYMENT;

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
                    $invoiceService->validatePaymentAmount($tx->amount, $expected);
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

        if ($setupIntent->status === 'succeeded' && $invoice instanceof Model_Invoice) {
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

        $s = $api_admin->invoice_subscription_get(['sid' => $stripeSubscription->id]);

        $status = match ($stripeSubscription->status) {
            'active' => 'active',
            'trialing' => 'active',
            'past_due' => 'active',
            default => 'canceled',
        };

        $api_admin->invoice_subscription_update(['id' => $s['id'], 'status' => $status, 'skip_gateway' => true]);

        return false;
    }

    private function handleSubscriptionDeleted($api_admin, Model_Transaction $tx, object $event): bool
    {
        $stripeSubscription = $event->data->object;

        try {
            $s = $api_admin->invoice_subscription_get(['sid' => $stripeSubscription->id]);
            $api_admin->invoice_subscription_update(['id' => $s['id'], 'status' => 'canceled', 'skip_gateway' => true]);
        } catch (Exception $e) {
            if (DEBUG) {
                error_log('Stripe subscription deleted webhook: ' . $e->getMessage());
            }
        }

        return false;
    }

    private function handleInvoicePaymentSucceeded($api_admin, Model_Transaction $tx, object $event, int $gateway_id): bool
    {
        $stripeInvoice = $this->resolveStripeInvoice($event->data->object);

        $subscriptionId = $this->extractSubscriptionId($stripeInvoice);

        if ($stripeInvoice === null || $subscriptionId === null) {
            return false;
        }

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
            $s = $api_admin->invoice_subscription_get(['sid' => $subscriptionId]);
            $api_admin->invoice_subscription_update(['id' => $s['id'], 'status' => 'canceled', 'skip_gateway' => true]);
        } catch (Exception $e) {
            if (DEBUG) {
                error_log('Stripe invoice payment failed webhook: ' . $e->getMessage());
            }
        }

        return false;
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
            'txn_id = :txn_id AND status IN (:s1, :s2)',
            [':txn_id' => $paymentIntent->id, ':s1' => Model_Transaction::STATUS_PROCESSING, ':s2' => Model_Transaction::STATUS_PROCESSED]
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
            $this->di['db']->store($tx);
        }

        if ($paymentIntent->status !== 'succeeded') {
            return false;
        }

        $invoice = $invoiceId ? $this->di['db']->getExistingModelById('Invoice', (int) $invoiceId) : null;

        // Delegate to the shared payment processing logic
        $this->applyOneTimePayment($tx, $invoice, $paymentIntent);

        return true;
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
                $invoiceService->validatePaymentAmount($tx->amount, $expected);
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

    private function getOrCreateCustomer(Model_Invoice $invoice): Stripe\Customer
    {
        $validatedEmail = filter_var($invoice->buyer_email, FILTER_VALIDATE_EMAIL);

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
            'email' => $invoice->buyer_email,
            'name' => trim($invoice->buyer_first_name . ' ' . $invoice->buyer_last_name),
            'address' => [
                'line1' => $invoice->buyer_address,
                'city' => $invoice->buyer_city,
                'state' => $invoice->buyer_state,
                'postal_code' => $invoice->buyer_zip,
                'country' => $invoice->buyer_country,
            ],
        ]);
    }

    private function createStripeSubscription(Stripe\Customer $customer, Stripe\SetupIntent $setupIntent, Model_Invoice $invoice): Stripe\Subscription
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
                'invoice_id' => $invoice->id,
                'client_id' => $invoice->client_id,
            ],
        ], ['idempotency_key' => 'sub_invoice_' . $invoice->id]);
    }

    private function getOrCreateProduct(Model_Invoice $invoice): Stripe\Product
    {
        $invoiceItems = $this->di['db']->getAll(
            'SELECT title FROM invoice_item WHERE invoice_id = :invoice_id',
            [':invoice_id' => $invoice->id]
        );

        if (empty($invoiceItems)) {
            throw new RuntimeException('No invoice items found for invoice ID: ' . $invoice->id);
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

    private function getOrCreatePrice(Stripe\Product $product, Model_Invoice $invoice): Stripe\Price
    {
        $amount = $this->getAmountInCents($invoice);
        $currency = strtolower($invoice->currency);
        $interval = $this->convertPeriodToStripe(
            $this->getSubscriptionPeriodForInvoice($invoice)
        );

        $prices = $this->stripe->prices->all([
            'product' => $product->id,
            'recurring' => ['interval' => $interval],
            'currency' => $currency,
            'limit' => 100,
        ]);

        foreach ($prices->data as $existingPrice) {
            if ($existingPrice->unit_amount === $amount) {
                return $existingPrice;
            }
        }

        return $this->stripe->prices->create([
            'product' => $product->id,
            'unit_amount' => $amount,
            'currency' => $currency,
            'recurring' => ['interval' => $interval],
        ]);
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
        $intent = $this->stripe->paymentIntents->create([
            'amount' => $this->getAmountInMinorUnits($invoice),
            'currency' => $invoice->currency,
            'description' => $this->getInvoiceTitle($invoice),
            'automatic_payment_methods' => ['enabled' => true],
            'receipt_email' => $invoice->buyer_email,
            'metadata' => [
                'client_id' => (string) $invoice->client_id,
                'invoice_id' => (string) $invoice->id,
            ],
        ]);

        $pubKey = ($this->config['test_mode']) ? $this->config['test_pub_key'] : $this->config['pub_key'];

        $dataAmount = $this->getAmountInMinorUnits($invoice);

        $settingService = $this->di['mod_service']('System');

        $title = $this->getInvoiceTitle($invoice);

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

        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        $payGateway = $this->di['db']->findOne('PayGateway', 'gateway = "Stripe"');
        $bindings = [
            ':pub_key' => $pubKey,
            ':intent_secret' => $intent->client_secret,
            ':amount' => $dataAmount,
            ':currency' => $invoice->currency,
            ':description' => $title,
            ':buyer_email' => htmlspecialchars((string) $invoice->buyer_email, ENT_QUOTES, 'UTF-8'),
            ':buyer_name' => htmlspecialchars(trim($invoice->buyer_first_name . ' ' . $invoice->buyer_last_name), ENT_QUOTES, 'UTF-8'),
            ':callbackUrl' => $payGatewayService->getCallbackUrl($payGateway, $invoice),
            ':redirectUrl' => $this->di['tools']->url('invoice/' . $invoice->hash),
            ':invoice_hash' => $invoice->hash,
        ];

        return strtr($form, $bindings);
    }

    protected function _generateSubscriptionForm(Model_Invoice $invoice): string
    {
        $customer = $this->getOrCreateCustomer($invoice);
        $product = $this->getOrCreateProduct($invoice);
        $price = $this->getOrCreatePrice($product, $invoice);

        $setupIntent = $this->stripe->setupIntents->create([
            'customer' => $customer->id,
            'payment_method_types' => ['card'],
            'usage' => 'off_session',
            'metadata' => [
                'invoice_id' => $invoice->id,
                'price_id' => $price->id,
            ],
        ]);

        $pubKey = ($this->config['test_mode']) ? $this->config['test_pub_key'] : $this->config['pub_key'];

        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        $payGateway = $this->di['db']->findOne('PayGateway', 'gateway = "Stripe"');

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
            ':callbackUrl' => $payGatewayService->getCallbackUrl($payGateway, $invoice),
            ':invoice_hash' => $invoice->hash,
        ];

        return strtr($form, $bindings);
    }
}
