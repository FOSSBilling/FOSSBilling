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
        $invoiceModel = $this->di['em']->getRepository(Box\Mod\Invoice\Entity\Invoice::class)->find($invoice_id);

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

    public function getAmountInCents(Box\Mod\Invoice\Entity\Invoice $invoice): int
    {
        return $this->getAmountInMinorUnits($invoice);
    }

    public function getAmountInMinorUnits(Box\Mod\Invoice\Entity\Invoice $invoice): int
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

    public function getInvoiceTitle(Box\Mod\Invoice\Entity\Invoice $invoice): string
    {
        $invoiceItems = $this->di['em']->getConnection()->fetchAllAssociative(
            'SELECT title FROM invoice_item WHERE invoice_id = :invoice_id',
            ['invoice_id' => $invoice->getId()]
        );

        $params = [
            ':id' => sprintf('%05s', $invoice->getNr()),
            ':serie' => $invoice->getSerie(),
            ':title' => $invoiceItems[0]['title'] ?? '',
        ];
        $title = __trans('Payment for invoice :serie:id [:title]', $params);
        if (FOSSBilling\Tools::safeCount($invoiceItems) > 1) {
            $title = __trans('Payment for invoice :serie:id', $params);
        }

        return $title;
    }

    public function logError($e, Box\Mod\Invoice\Entity\Transaction $tx): void
    {
        $body = $e->getJsonBody();
        $err = $body['error'];
        $tx->setTxnStatus($err['type']);
        $tx->setError($err['message']);
        $tx->setStatus(Box\Mod\Invoice\Entity\Transaction::STATUS_ERROR);
        $tx->setUpdatedAt(new DateTime());
        $this->di['em']->persist($tx);
        $this->di['em']->flush();

        // @phpstan-ignore if.alwaysFalse (DEBUG is a runtime constant that may be true during debugging)
        if (DEBUG) {
            error_log(json_encode($e->getJsonBody()));
        }

        throw new Exception($tx->getError());
    }

    public function processTransaction(FOSSBilling\Api\Proxy $api_admin, int $id, array $data, int $gateway_id): void
    {
        $tx = $this->di['em']->getRepository(Box\Mod\Invoice\Entity\Transaction::class)->find($id)
            ?? throw new FOSSBilling\InformationException('Transaction not found');

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

    private function resolveInvoice(Box\Mod\Invoice\Entity\Transaction $tx, array $data): ?Box\Mod\Invoice\Entity\Invoice
    {
        if ($tx->getInvoiceId()) {
            return $this->di['em']->getRepository(Box\Mod\Invoice\Entity\Invoice::class)->find($tx->getInvoiceId())
                ?? throw new FOSSBilling\InformationException('Invoice not found');
        }
        if (isset($data['get']['invoice_id']) && $data['get']['invoice_id']) {
            $invoice = $this->di['em']->getRepository(Box\Mod\Invoice\Entity\Invoice::class)->find($data['get']['invoice_id'])
                ?? throw new FOSSBilling\InformationException('Invoice not found');
            $tx->setInvoiceId($invoice->getId());

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

    private function processPaymentIntent(Box\Mod\Invoice\Entity\Transaction $tx, ?Box\Mod\Invoice\Entity\Invoice $invoice, array $data): void
    {
        $charge = $this->stripe->paymentIntents->retrieve($data['get']['payment_intent'], []);

        $this->withPaymentIntentLock(
            $charge->id,
            (int) $tx->getGatewayId(),
            fn () => $this->processPaymentIntentUnderLock($tx, $invoice, $charge)
        );
    }

    private function processPaymentIntentUnderLock(Box\Mod\Invoice\Entity\Transaction $tx, ?Box\Mod\Invoice\Entity\Invoice $invoice, object $charge): void
    {
        $invoiceService = $this->di['mod_service']('Invoice');

        $tx->setTxnStatus($charge->status);
        $tx->setTxnId($charge->id);
        $tx->setAmount((string) $this->getAmountFromMinorUnits($charge->amount, $charge->currency));
        $tx->setCurrency($charge->currency);
        $tx->setType(Payment_Transaction::TXTYPE_PAYMENT);

        $existing = $this->findExistingTransaction(
            $charge->id,
            (int) $tx->getGatewayId(),
            $tx->getId(),
            [Box\Mod\Invoice\Entity\Transaction::STATUS_RECEIVED, Box\Mod\Invoice\Entity\Transaction::STATUS_PROCESSING, Box\Mod\Invoice\Entity\Transaction::STATUS_PROCESSED]
        );
        if ($existing instanceof Box\Mod\Invoice\Entity\Transaction) {
            $this->di['em']->remove($tx);
            $this->di['em']->flush();

            return;
        }

        if ($charge->status === 'succeeded') {
            if ($tx->getStatus() === Box\Mod\Invoice\Entity\Transaction::STATUS_PROCESSED && empty($tx->getError())) {
                $tx->setUpdatedAt(new DateTime());
                $this->di['em']->persist($tx);
                $this->di['em']->flush();

                return;
            }

            if ($invoice instanceof Box\Mod\Invoice\Entity\Invoice) {
                $fresh = $this->di['em']->getRepository(Box\Mod\Invoice\Entity\Invoice::class)->findOneBy(['id' => $invoice->getId()]);
                if ($fresh instanceof Box\Mod\Invoice\Entity\Invoice && $fresh->getStatus() === Box\Mod\Invoice\Entity\Invoice::STATUS_PAID) {
                    $tx->setStatus(Box\Mod\Invoice\Entity\Transaction::STATUS_PROCESSED);
                    $tx->setUpdatedAt(new DateTime());
                    $this->di['em']->persist($tx);
                    $this->di['em']->flush();

                    return;
                }
            }

            $transactionService = $this->di['mod_service']('Invoice', 'Transaction');
            if (!$transactionService->claimForProcessing((int) $tx->getId())) {
                return;
            }

            $tx->setStatus(Box\Mod\Invoice\Entity\Transaction::STATUS_PROCESSING);
        }

        $bd = [
            'amount' => $tx->getAmount(),
            'description' => 'Stripe transaction ' . $charge->id,
            'type' => 'transaction',
            'rel_id' => $tx->getId(),
        ];

        if ($charge->status == 'succeeded' && $tx->getStatus() === Box\Mod\Invoice\Entity\Transaction::STATUS_PROCESSING) {
            $clientService = $this->di['mod_service']('client');
            $client = $invoice
                ? $this->di['em']->getRepository(Box\Mod\Client\Entity\Client::class)->find($invoice->getClientId())
                    ?? throw new FOSSBilling\InformationException('Client not found') : $this->getClientFromTransaction($tx, $charge);

            if ($invoice) {
                $expected = $invoiceService->getTotalWithTax($invoice);

                try {
                    $invoiceService->validatePaymentAmount((float) $tx->getAmount(), $expected);
                } catch (FOSSBilling\Exception $e) {
                    $tx->setStatus(Box\Mod\Invoice\Entity\Transaction::STATUS_ERROR);
                    $tx->setError($e->getMessage());
                    $tx->setUpdatedAt(new DateTime());
                    $this->di['em']->persist($tx);
                    $this->di['em']->flush();

                    throw $e;
                }
            }

            $clientService->addFunds($client, $bd['amount'], $bd['description'], $bd);

            if ($tx->getInvoiceId() && $invoice && !$invoiceService->isInvoiceTypeDeposit($invoice)) {
                if (!$invoice->isApproved()) {
                    $invoiceService->approveInvoice($invoice, ['use_credits' => false]);
                }
                $invoiceService->payInvoiceWithCredits($invoice);
            } elseif ($tx->getInvoiceId() && $invoice && $invoiceService->isInvoiceTypeDeposit($invoice)) {
                $invoiceService->markAsPaid($invoice);
            } elseif (!$tx->getInvoiceId()) {
                $invoiceService->doBatchPayWithCredits(['client_id' => $client->getId()]);
            }
        }

        $paymentStatus = match ($charge->status) {
            'succeeded' => Box\Mod\Invoice\Entity\Transaction::STATUS_PROCESSED,
            'requires_action' => Box\Mod\Invoice\Entity\Transaction::STATUS_RECEIVED,
            'requires_confirmation' => Box\Mod\Invoice\Entity\Transaction::STATUS_RECEIVED,
            'requires_capture' => Box\Mod\Invoice\Entity\Transaction::STATUS_RECEIVED,
            'processing' => Box\Mod\Invoice\Entity\Transaction::STATUS_RECEIVED,
            'pending' => Box\Mod\Invoice\Entity\Transaction::STATUS_RECEIVED,
            'requires_payment_method' => Box\Mod\Invoice\Entity\Transaction::STATUS_ERROR,
            'canceled' => Box\Mod\Invoice\Entity\Transaction::STATUS_ERROR,
            'failed' => Box\Mod\Invoice\Entity\Transaction::STATUS_ERROR,
            default => Box\Mod\Invoice\Entity\Transaction::STATUS_ERROR,
        };

        $tx->setStatus($paymentStatus);
        $tx->setUpdatedAt(new DateTime());
        $this->di['em']->persist($tx);
        $this->di['em']->flush();
    }

    private function processSetupIntent($api_admin, Box\Mod\Invoice\Entity\Transaction $tx, ?Box\Mod\Invoice\Entity\Invoice $invoice, array $data, int $gateway_id): void
    {
        $setupIntent = $this->stripe->setupIntents->retrieve($data['get']['setup_intent'], []);

        $tx->setTxnStatus($setupIntent->status);
        $tx->setTxnId($setupIntent->id);

        if ($setupIntent->status === 'succeeded' && $invoice instanceof Box\Mod\Invoice\Entity\Invoice) {
            $customer = $this->getOrCreateCustomer($invoice);

            try {
                $subscription = $this->createStripeSubscription($customer, $setupIntent, $invoice);
            } catch (Stripe\Exception\ApiErrorException $e) {
                if ($e->getStripeCode() !== 'idempotency_key_in_use') {
                    throw $e;
                }

                $subscriptions = $this->stripe->subscriptions->all([
                    'customer' => $customer->id,
                    'limit' => 1,
                ]);
                $subscription = count($subscriptions->data) > 0 ? $subscriptions->data[0] : null;

                if ($subscription === null) {
                    $tx->setStatus(Box\Mod\Invoice\Entity\Transaction::STATUS_PROCESSED);
                    $tx->setUpdatedAt(new DateTime());
                    $this->di['em']->persist($tx);
                    $this->di['em']->flush();

                    return;
                }
            }

            $tx->setSId($subscription->id);
            $tx->setSPeriod($this->getSubscriptionPeriodForInvoice($invoice));
            $tx->setAmount((string) $this->getAmountFromMinorUnits($this->getAmountInCents($invoice), $invoice->getCurrency()));
            $tx->setCurrency($invoice->getCurrency());
            $tx->setType(Payment_Transaction::TXTYPE_PAYMENT);
            $tx->setStatus(Box\Mod\Invoice\Entity\Transaction::STATUS_PROCESSED);

            $this->createOrUpdateSubscription($api_admin, $invoice, $subscription, $gateway_id);

            $this->processInitialSubscriptionPayment($api_admin, $tx, $invoice, $subscription);
        } else {
            $tx->setStatus(Box\Mod\Invoice\Entity\Transaction::STATUS_ERROR);
        }

        $tx->setUpdatedAt(new DateTime());
        $this->di['em']->persist($tx);
        $this->di['em']->flush();
    }

    private function processInitialSubscriptionPayment($api_admin, Box\Mod\Invoice\Entity\Transaction $tx, Box\Mod\Invoice\Entity\Invoice $invoice, Stripe\Subscription $subscription): void
    {
        $fresh = $this->di['em']->getRepository(Box\Mod\Invoice\Entity\Invoice::class)->findOneBy(['id' => $invoice->getId()]);
        if ($fresh instanceof Box\Mod\Invoice\Entity\Invoice && $fresh->getStatus() === Box\Mod\Invoice\Entity\Invoice::STATUS_PAID) {
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

    private function processWebhookEvent($api_admin, Box\Mod\Invoice\Entity\Transaction $tx, array $data, int $gateway_id): void
    {
        $rawBody = $data['http_raw_post_data'] ?? '';
        $sigHeader = $data['server']['HTTP_STRIPE_SIGNATURE'] ?? '';
        $webhookSecret = $this->config['test_mode']
            ? ($this->config['test_webhook_secret'] ?? '')
            : ($this->config['webhook_secret'] ?? '');

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

        $tx->setTxnId($event->id);
        $tx->setTxnStatus($event->type);

        if (!in_array($event->type, self::HANDLED_EVENT_TYPES, true)) {
            $this->di['em']->remove($tx);
            $this->di['em']->flush();

            return;
        }

        $keepTransaction = false;

        try {
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

            throw new FOSSBilling\Exception('There was an error when processing the Stripe webhook');
        }

        if ($keepTransaction) {
            if ($tx->getStatus() !== Box\Mod\Invoice\Entity\Transaction::STATUS_ERROR) {
                $tx->setStatus(Box\Mod\Invoice\Entity\Transaction::STATUS_PROCESSED);
            }
        } else {
            $this->di['em']->remove($tx);
            $this->di['em']->flush();

            return;
        }

        $tx->setUpdatedAt(new DateTime());
        $this->di['em']->persist($tx);
        $this->di['em']->flush();
    }

    private function eventBelongsToGateway(object $event, int $gatewayId): bool
    {
        $stripeObject = $event->data->object ?? null;
        if (!is_object($stripeObject)) {
            return false;
        }

        $eventGatewayId = $this->getGatewayIdFromStripeObject($stripeObject);

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

    private function handleSubscriptionCreated($api_admin, Box\Mod\Invoice\Entity\Transaction $tx, object $event, int $gateway_id): bool
    {
        $stripeSubscription = $event->data->object;
        $invoiceId = $stripeSubscription->metadata->invoice_id ?? null;
        $clientId = $stripeSubscription->metadata->client_id ?? null;

        if (!$invoiceId || !$clientId) {
            return false;
        }

        $tx->setInvoiceId((int) $invoiceId);

        $invoice = $this->di['em']->getRepository(Box\Mod\Invoice\Entity\Invoice::class)->find((int) $invoiceId)
            ?? throw new FOSSBilling\InformationException('Invoice not found');
        $this->createOrUpdateSubscription($api_admin, $invoice, $stripeSubscription, $gateway_id);

        return false;
    }

    private function handleSubscriptionUpdated($api_admin, Box\Mod\Invoice\Entity\Transaction $tx, object $event): bool
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

    private function handleSubscriptionDeleted($api_admin, Box\Mod\Invoice\Entity\Transaction $tx, object $event): bool
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

    private function handleInvoicePaymentSucceeded($api_admin, Box\Mod\Invoice\Entity\Transaction $tx, object $event, int $gateway_id): bool
    {
        $stripeInvoice = $this->resolveStripeInvoice($event->data->object);

        $subscriptionId = $this->extractSubscriptionId($stripeInvoice);

        if ($stripeInvoice === null || $subscriptionId === null) {
            return false;
        }

        $tx->setTxnId($stripeInvoice->id);
        $existing = $this->findExistingTransactionsByTxnId(
            $stripeInvoice->id,
            $tx->getId(),
            [Box\Mod\Invoice\Entity\Transaction::STATUS_PROCESSING, Box\Mod\Invoice\Entity\Transaction::STATUS_PROCESSED]
        );
        if ($existing instanceof Box\Mod\Invoice\Entity\Transaction) {
            return false;
        }

        $stripeSubscription = $this->stripe->subscriptions->retrieve($subscriptionId, []);
        $invoiceId = $stripeSubscription->metadata->invoice_id ?? null;
        $clientId = $stripeSubscription->metadata->client_id ?? null;

        if (!$clientId) {
            return false;
        }

        if ($invoiceId) {
            $tx->setInvoiceId((int) $invoiceId);
            $this->di['em']->persist($tx);
            $this->di['em']->flush();
        }

        $isInitialPayment = ($stripeInvoice->billing_reason ?? '') === 'subscription_create';

        if ($invoiceId) {
            $existingInvoice = $this->di['em']->getRepository(Box\Mod\Invoice\Entity\Invoice::class)->findOneBy(['id' => (int) $invoiceId]);
            if ($existingInvoice instanceof Box\Mod\Invoice\Entity\Invoice) {
                if ($existingInvoice->getStatus() === Box\Mod\Invoice\Entity\Invoice::STATUS_PAID) {
                    return false;
                }
                if (!$isInitialPayment && $existingInvoice->getStatus() === Box\Mod\Invoice\Entity\Invoice::STATUS_UNPAID) {
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
        if (!$transactionService->claimForProcessing($tx->getId())) {
            return false;
        }

        $tx->setType(Payment_Transaction::TXTYPE_PAYMENT);
        $tx->setAmount((string) $bd['amount']);
        $tx->setCurrency(strtoupper((string) ($stripeInvoice->currency ?? '')));

        $api_admin->client_balance_add_funds($bd);

        $invoiceService = $this->di['mod_service']('Invoice');

        if ($isInitialPayment && $invoiceId) {
            $invoiceModel = $this->di['em']->getRepository(Box\Mod\Invoice\Entity\Invoice::class)->find((int) $invoiceId)
                ?? throw new FOSSBilling\InformationException('Invoice not found');

            if (!$invoiceService->isInvoiceTypeDeposit($invoiceModel)) {
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

            if ($renewalInvoice instanceof Box\Mod\Invoice\Entity\Invoice) {
                $tx->setInvoiceId($renewalInvoice->getId());
                if (!$invoiceService->isInvoiceTypeDeposit($renewalInvoice)) {
                    $invoiceService->payInvoiceWithCredits($renewalInvoice);
                }
            } else {
                $api_admin->invoice_batch_pay_with_credits(['client_id' => $clientId]);
            }
        }

        return true;
    }

    private function handleInvoicePaymentFailed($api_admin, Box\Mod\Invoice\Entity\Transaction $tx, object $event): bool
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

    private function handlePaymentIntentSucceededWebhook($api_admin, Box\Mod\Invoice\Entity\Transaction $tx, object $event, int $gateway_id): bool
    {
        $paymentIntent = $event->data->object;

        return $this->withPaymentIntentLock(
            $paymentIntent->id,
            $gateway_id,
            fn (): bool => $this->handlePaymentIntentSucceededWebhookUnderLock($tx, $paymentIntent, $gateway_id)
        );
    }

    private function handlePaymentIntentSucceededWebhookUnderLock(Box\Mod\Invoice\Entity\Transaction $tx, object $paymentIntent, int $gateway_id): bool
    {
        $tx->setTxnId($paymentIntent->id);
        $tx->setTxnStatus($paymentIntent->status);
        $tx->setAmount((string) $this->getAmountFromMinorUnits($paymentIntent->amount, $paymentIntent->currency));
        $tx->setCurrency($paymentIntent->currency);
        $tx->setType(Payment_Transaction::TXTYPE_PAYMENT);

        $existing = $this->findExistingTransaction(
            $paymentIntent->id,
            $gateway_id,
            $tx->getId(),
            [Box\Mod\Invoice\Entity\Transaction::STATUS_PROCESSING, Box\Mod\Invoice\Entity\Transaction::STATUS_PROCESSED]
        );
        if ($existing instanceof Box\Mod\Invoice\Entity\Transaction) {
            $tx->setInvoiceId($existing->getInvoiceId());

            return false;
        }

        $invoiceId = $paymentIntent->metadata->invoice_id ?? null;
        $clientId = $paymentIntent->metadata->client_id ?? null;

        if (!$invoiceId && !$clientId) {
            return false;
        }

        if ($invoiceId) {
            $tx->setInvoiceId((int) $invoiceId);
        }

        $this->di['em']->persist($tx);
        $this->di['em']->flush();

        if ($paymentIntent->status !== 'succeeded') {
            return false;
        }

        $invoice = $invoiceId
            ? $this->di['em']->getRepository(Box\Mod\Invoice\Entity\Invoice::class)->find((int) $invoiceId)
                ?? throw new FOSSBilling\InformationException('Invoice not found') : null;

        $this->applyOneTimePayment($tx, $invoice, $paymentIntent);

        return true;
    }

    private function withPaymentIntentLock(string $paymentIntentId, int $gatewayId, callable $callback): mixed
    {
        $lockName = 'fb:stripe:' . substr(hash('sha256', $gatewayId . ':' . $paymentIntentId), 0, 54);
        $waitStartedAt = hrtime(true);
        $acquired = (int) $this->di['dbal']->fetchOne(
            'SELECT GET_LOCK(:lock_name, 10)',
            ['lock_name' => $lockName]
        );

        if ($acquired !== 1) {
            $waitDurationMs = (hrtime(true) - $waitStartedAt) / 1_000_000;
            $this->di['logger']->warning(
                'Timed out after %.1f ms waiting for Stripe PaymentIntent lock %s',
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

    private function handlePaymentIntentFailedWebhook($api_admin, Box\Mod\Invoice\Entity\Transaction $tx, object $event): bool
    {
        $paymentIntent = $event->data->object;
        $tx->setTxnId($paymentIntent->id);
        $tx->setTxnStatus($paymentIntent->status);
        $tx->setStatus(Box\Mod\Invoice\Entity\Transaction::STATUS_ERROR);
        $tx->setError('Payment failed via webhook');

        return true;
    }

    private function handleSetupIntentSucceededWebhook($api_admin, Box\Mod\Invoice\Entity\Transaction $tx, object $event, int $gateway_id): bool
    {
        $setupIntent = $event->data->object;

        $tx->setTxnId($setupIntent->id);
        $tx->setTxnStatus($setupIntent->status);

        if ($setupIntent->status !== 'succeeded') {
            $tx->setStatus(Box\Mod\Invoice\Entity\Transaction::STATUS_ERROR);
            $tx->setUpdatedAt(new DateTime());
            $this->di['em']->persist($tx);
            $this->di['em']->flush();

            return false;
        }

        $existing = $this->findExistingTransactionsByTxnId(
            $setupIntent->id,
            $tx->getId(),
            [Box\Mod\Invoice\Entity\Transaction::STATUS_PROCESSING, Box\Mod\Invoice\Entity\Transaction::STATUS_PROCESSED]
        );
        if ($existing instanceof Box\Mod\Invoice\Entity\Transaction) {
            $tx->setInvoiceId($existing->getInvoiceId());

            return false;
        }

        $invoiceId = $setupIntent->metadata->invoice_id ?? null;
        if (!$invoiceId) {
            return false;
        }

        $tx->setInvoiceId((int) $invoiceId);
        $this->di['em']->persist($tx);
        $this->di['em']->flush();

        $invoice = $this->di['em']->getRepository(Box\Mod\Invoice\Entity\Invoice::class)->find((int) $invoiceId)
            ?? throw new FOSSBilling\InformationException('Invoice not found');
        $customer = $this->getOrCreateCustomer($invoice);

        try {
            $subscription = $this->createStripeSubscription($customer, $setupIntent, $invoice);
        } catch (Stripe\Exception\ApiErrorException $e) {
            if ($e->getStripeCode() !== 'idempotency_key_in_use') {
                throw $e;
            }

            if (DEBUG) {
                error_log('Stripe setup_intent webhook: subscription creation deferred to redirect flow: ' . $e->getMessage());
            }

            return false;
        }

        $tx->setSId($subscription->id);
        $tx->setSPeriod($this->getSubscriptionPeriodForInvoice($invoice));
        $tx->setAmount((string) $this->getAmountFromMinorUnits($this->getAmountInCents($invoice), $invoice->getCurrency()));
        $tx->setCurrency($invoice->getCurrency());
        $tx->setType(Payment_Transaction::TXTYPE_PAYMENT);
        $tx->setUpdatedAt(new DateTime());
        $this->di['em']->persist($tx);
        $this->di['em']->flush();

        $this->createOrUpdateSubscription($api_admin, $invoice, $subscription, $gateway_id);

        $this->processInitialSubscriptionPayment($api_admin, $tx, $invoice, $subscription);

        return true;
    }

    private function handleSetupIntentFailedWebhook($api_admin, Box\Mod\Invoice\Entity\Transaction $tx, object $event): bool
    {
        $setupIntent = $event->data->object;
        $tx->setTxnId($setupIntent->id);
        $tx->setTxnStatus($setupIntent->status);
        $tx->setStatus(Box\Mod\Invoice\Entity\Transaction::STATUS_ERROR);
        $tx->setError('Setup Intent failed via webhook');

        return true;
    }

    private function createOrUpdateSubscription($api_admin, Box\Mod\Invoice\Entity\Invoice $invoice, object $subscription, int $gateway_id): void
    {
        $existing = $this->di['em']->getRepository(Box\Mod\Invoice\Entity\Subscription::class)->findOneBy(['sid' => $subscription->id]);
        if ($existing instanceof Box\Mod\Invoice\Entity\Subscription) {
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
            if (DEBUG) {
                error_log('Failed to create FOSSBilling subscription for ' . $subscription->id . ': ' . $e->getMessage());
            }
        }
    }

    private function applyOneTimePayment(Box\Mod\Invoice\Entity\Transaction $tx, ?Box\Mod\Invoice\Entity\Invoice $invoice, object $charge): void
    {
        if ($invoice instanceof Box\Mod\Invoice\Entity\Invoice) {
            $fresh = $this->di['em']->getRepository(Box\Mod\Invoice\Entity\Invoice::class)->findOneBy(['id' => $invoice->getId()]);
            if ($fresh instanceof Box\Mod\Invoice\Entity\Invoice) {
                $invoice = $fresh;
            }
        }

        if ($invoice instanceof Box\Mod\Invoice\Entity\Invoice && $invoice->getStatus() === Box\Mod\Invoice\Entity\Invoice::STATUS_PAID) {
            return;
        }

        $invoiceService = $this->di['mod_service']('Invoice');

        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');
        if (!$transactionService->claimForProcessing((int) $tx->getId())) {
            return;
        }

        $tx->setStatus(Box\Mod\Invoice\Entity\Transaction::STATUS_PROCESSING);

        $clientService = $this->di['mod_service']('client');
        $client = $invoice
            ? $this->di['em']->getRepository(Box\Mod\Client\Entity\Client::class)->find($invoice->getClientId())
                ?? throw new FOSSBilling\InformationException('Client not found') : $this->getClientFromTransaction($tx, $charge);

        if ($invoice) {
            $expected = $invoiceService->getTotalWithTax($invoice);

            try {
                $invoiceService->validatePaymentAmount((float) $tx->getAmount(), $expected);
            } catch (FOSSBilling\Exception $e) {
                $tx->setStatus(Box\Mod\Invoice\Entity\Transaction::STATUS_ERROR);
                $tx->setError($e->getMessage());
                $tx->setUpdatedAt(new DateTime());
                $this->di['em']->persist($tx);
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

        if ($tx->getInvoiceId() && $invoice && !$invoiceService->isInvoiceTypeDeposit($invoice)) {
            if (!$invoice->isApproved()) {
                $invoiceService->approveInvoice($invoice, ['use_credits' => false]);
            }
            $invoiceService->payInvoiceWithCredits($invoice);
        } elseif ($tx->getInvoiceId() && $invoice && $invoiceService->isInvoiceTypeDeposit($invoice)) {
            $invoiceService->markAsPaid($invoice);
        } elseif (!$tx->getInvoiceId()) {
            $invoiceService->doBatchPayWithCredits(['client_id' => $client->getId()]);
        }

        $tx->setStatus(Box\Mod\Invoice\Entity\Transaction::STATUS_PROCESSED);
        $tx->setUpdatedAt(new DateTime());
        $this->di['em']->persist($tx);
        $this->di['em']->flush();
    }

    /**
     * Resolve the event payload to a Stripe invoice object.
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
     */
    private function extractSubscriptionId(?object $stripeInvoice): ?string
    {
        if ($stripeInvoice === null) {
            return null;
        }

        if (!empty($stripeInvoice->subscription)) {
            return $stripeInvoice->subscription;
        }

        if (!empty($stripeInvoice->parent->subscription_details->subscription)) {
            return $stripeInvoice->parent->subscription_details->subscription;
        }

        if (!empty($stripeInvoice->lines->data[0]->parent->subscription_item_details->subscription)) {
            return $stripeInvoice->lines->data[0]->parent->subscription_item_details->subscription;
        }

        return null;
    }

    private function escapeStripeSearchValue(string $value): string
    {
        return str_replace(['\\', '\''], ['\\\\', '\\\''], $value);
    }

    private function getOrCreateCustomer(Box\Mod\Invoice\Entity\Invoice $invoice): Stripe\Customer
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

    private function createStripeSubscription(Stripe\Customer $customer, Stripe\SetupIntent $setupIntent, Box\Mod\Invoice\Entity\Invoice $invoice): Stripe\Subscription
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
                'invoice_id' => $invoice->getId(),
                'client_id' => $invoice->getClientId(),
                'gateway_id' => (string) $this->config['gateway_id'],
            ],
        ], ['idempotency_key' => 'sub_invoice_' . $invoice->getId()]);
    }

    private function getOrCreateProduct(Box\Mod\Invoice\Entity\Invoice $invoice): Stripe\Product
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

    private function getOrCreatePrice(Stripe\Product $product, Box\Mod\Invoice\Entity\Invoice $invoice): Stripe\Price
    {
        $amount = $this->getAmountInCents($invoice);
        $currency = strtolower($invoice->getCurrency());
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

    private function getSubscriptionPeriodForInvoice(Box\Mod\Invoice\Entity\Invoice $invoice): string
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

    private function getClientFromTransaction(Box\Mod\Invoice\Entity\Transaction $tx, Stripe\PaymentIntent $charge): Box\Mod\Client\Entity\Client
    {
        $clientId = (int) ($charge->metadata->client_id ?? 0);

        if ($clientId > 0) {
            try {
                return $this->di['em']->getRepository(Box\Mod\Client\Entity\Client::class)->find($clientId)
                    ?? throw new FOSSBilling\InformationException('Client not found');
            } catch (FOSSBilling\Exception $e) {
                throw new Payment_Exception('Unable to load client for transaction: :msg', [':msg' => $e->getMessage()]);
            }
        }

        throw new Payment_Exception('Unable to determine client for transaction. No invoice or client metadata available.');
    }

    protected function _generateForm(Box\Mod\Invoice\Entity\Invoice $invoice): string
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

    protected function _generateSubscriptionForm(Box\Mod\Invoice\Entity\Invoice $invoice): string
    {
        $customer = $this->getOrCreateCustomer($invoice);
        $product = $this->getOrCreateProduct($invoice);
        $price = $this->getOrCreatePrice($product, $invoice);

        $setupIntent = $this->stripe->setupIntents->create([
            'customer' => $customer->id,
            'payment_method_types' => ['card'],
            'usage' => 'off_session',
            'metadata' => [
                'invoice_id' => $invoice->getId(),
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

    private function findExistingTransaction(string $txnId, int $gatewayId, ?int $excludeId, array $statuses): ?Box\Mod\Invoice\Entity\Transaction
    {
        $params = ['txn_id' => $txnId, 'gateway_id' => $gatewayId, 'id' => $excludeId];
        $statusPlaceholders = [];
        foreach ($statuses as $i => $status) {
            $key = 's' . $i;
            $params[$key] = $status;
            $statusPlaceholders[] = ':' . $key;
        }
        $sql = 'SELECT id FROM transaction WHERE txn_id = :txn_id AND gateway_id = :gateway_id AND id != :id AND status IN (' . implode(',', $statusPlaceholders) . ') LIMIT 1';

        $id = $this->di['dbal']->fetchOne($sql, $params);

        return $id ? $this->di['em']->getRepository(Box\Mod\Invoice\Entity\Transaction::class)->find((int) $id) : null;
    }

    private function findExistingTransactionsByTxnId(string $txnId, ?int $excludeId, array $statuses): ?Box\Mod\Invoice\Entity\Transaction
    {
        $params = ['txn_id' => $txnId, 'id' => $excludeId];
        $statusPlaceholders = [];
        foreach ($statuses as $i => $status) {
            $key = 's' . $i;
            $params[$key] = $status;
            $statusPlaceholders[] = ':' . $key;
        }
        $sql = 'SELECT id FROM transaction WHERE txn_id = :txn_id AND status IN (' . implode(',', $statusPlaceholders) . ') AND id != :id LIMIT 1';

        $id = $this->di['dbal']->fetchOne($sql, $params);

        return $id ? $this->di['em']->getRepository(Box\Mod\Invoice\Entity\Transaction::class)->find((int) $id) : null;
    }
}
