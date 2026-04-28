<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Stripe\StripeClient;

class Payment_Adapter_Stripe implements FOSSBilling\InjectionAwareInterface
{
    protected ?Pimple\Container $di = null;

    private StripeClient $stripe;

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
                        'label' => 'Live publishable key:',
                    ],
                ],
                'api_key' => [
                    'text', [
                        'label' => 'Live Secret key:',
                    ],
                ],
                'webhook_secret' => [
                    'text', [
                        'label' => 'Live Webhook signing secret:',
                        'required' => false,
                    ],
                ],
                'test_pub_key' => [
                    'text', [
                        'label' => 'Test Publishable key:',
                        'required' => false,
                    ],
                ],
                'test_api_key' => [
                    'text', [
                        'label' => 'Test Secret key:',
                        'required' => false,
                    ],
                ],
                'test_webhook_secret' => [
                    'text', [
                        'label' => 'Test Webhook signing secret:',
                        'required' => false,
                    ],
                ],
            ],
        ];
    }

    public function getHtml($api_admin, $invoice_id, $subscription): string
    {
        $invoiceModel = $this->di['db']->load('Invoice', $invoice_id);

        if ($subscription) {
            return $this->_generateSubscriptionForm($invoiceModel);
        }

        return $this->_generateForm($invoiceModel);
    }

    public function getAmountInCents(Model_Invoice $invoice): int
    {
        $invoiceService = $this->di['mod_service']('Invoice');

        return (int) ($invoiceService->getTotalWithTax($invoice) * 100);
    }

    public function getInvoiceTitle(Model_Invoice $invoice): string
    {
        $invoiceItems = $this->di['db']->getAll('SELECT title from invoice_item WHERE invoice_id = :invoice_id', [':invoice_id' => $invoice->id]);

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

        if (DEBUG) {
            error_log(json_encode($e->getJsonBody()));
        }

        throw new Exception($tx->error);
    }

    public function processTransaction($api_admin, $id, $data, $gateway_id): void
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

        $payload = json_decode($rawBody, true);
        if (!is_array($payload) || !isset($payload['type'])) {
            return false;
        }

        $eventType = $payload['type'];
        $subscriptionPrefixes = ['customer.subscription.', 'invoice.'];

        foreach ($subscriptionPrefixes as $prefix) {
            if (str_starts_with($eventType, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function processPaymentIntent(Model_Transaction $tx, ?Model_Invoice $invoice, array $data): void
    {
        $invoiceService = $this->di['mod_service']('Invoice');
        $charge = $this->stripe->paymentIntents->retrieve($data['get']['payment_intent'], []);

        $tx->txn_status = $charge->status;
        $tx->txn_id = $charge->id;
        $tx->amount = $charge->amount / 100;
        $tx->currency = $charge->currency;

        if ($charge->status === 'succeeded') {
            if ($tx->status === Model_Transaction::STATUS_PROCESSED && empty($tx->error)) {
                $tx->updated_at = date('Y-m-d H:i:s');
                $this->di['db']->store($tx);

                return;
            }

            $transactionService = $this->di['mod_service']('Invoice', 'Transaction');
            if (!$transactionService->claimForProcessing($tx->id)) {
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

            $clientService->addFunds($client, $bd['amount'], $bd['description'], $bd);

            if ($tx->invoice_id && $invoice && !$invoiceService->isInvoiceTypeDeposit($invoice)) {
                if (!$invoice->approved) {
                    $invoiceService->approveInvoice($invoice, ['use_credits' => false]);
                }
                $invoiceService->payInvoiceWithCredits($invoice);
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
            $subscription = $this->createStripeSubscription($customer, $setupIntent, $invoice);

            $tx->s_id = $subscription->id;
            $tx->s_period = $this->getSubscriptionPeriodForInvoice($invoice);
            $tx->amount = $this->getAmountInCents($invoice) / 100;
            $tx->currency = $invoice->currency;
            $tx->status = Model_Transaction::STATUS_PROCESSED;
        } else {
            $tx->status = Model_Transaction::STATUS_ERROR;
        }

        $tx->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($tx);
    }

    private function processWebhookEvent($api_admin, Model_Transaction $tx, array $data, int $gateway_id): void
    {
        $rawBody = $data['http_raw_post_data'] ?? '';
        $sigHeader = $data['server']['HTTP_STRIPE_SIGNATURE'] ?? '';
        $webhookSecret = $this->config['test_mode']
            ? ($this->config['test_webhook_secret'] ?? '')
            : ($this->config['webhook_secret'] ?? '');

        if (!empty($webhookSecret) && !empty($sigHeader)) {
            try {
                $event = Stripe\Webhook::constructEvent($rawBody, $sigHeader, $webhookSecret);
            } catch (UnexpectedValueException $e) {
                throw new FOSSBilling\Exception('Invalid Stripe webhook payload');
            } catch (Stripe\Exception\SignatureVerificationException $e) {
                throw new FOSSBilling\Exception('Invalid Stripe webhook signature');
            }
        } else {
            $event = json_decode($rawBody, false);
            if (!$event || !isset($event->type)) {
                throw new FOSSBilling\Exception('Unable to parse Stripe webhook event');
            }
        }

        $tx->txn_id = $event->id;
        $tx->txn_status = $event->type;

        try {
            match ($event->type) {
                'customer.subscription.created' => $this->handleSubscriptionCreated($api_admin, $tx, $event, $gateway_id),
                'customer.subscription.updated' => $this->handleSubscriptionUpdated($api_admin, $tx, $event),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($api_admin, $tx, $event),
                'invoice.payment_succeeded' => $this->handleInvoicePaymentSucceeded($api_admin, $tx, $event, $gateway_id),
                'invoice.payment_failed' => $this->handleInvoicePaymentFailed($api_admin, $tx, $event),
                default => null,
            };
        } catch (Stripe\Exception\CardException|Stripe\Exception\InvalidRequestException|Stripe\Exception\AuthenticationException|Stripe\Exception\ApiConnectionException|Stripe\Exception\ApiErrorException $e) {
            $this->logError($e, $tx);

            throw new FOSSBilling\Exception('There was an error when processing the Stripe webhook');
        }

        $tx->status = Model_Transaction::STATUS_PROCESSED;
        $tx->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($tx);
    }

    private function handleSubscriptionCreated($api_admin, Model_Transaction $tx, object $event, int $gateway_id): void
    {
        $stripeSubscription = $event->data->object;
        $invoiceId = $stripeSubscription->metadata->invoice_id ?? null;
        $clientId = $stripeSubscription->metadata->client_id ?? null;

        if (!$invoiceId || !$clientId) {
            return;
        }

        $tx->invoice_id = $invoiceId;

        $existingSubscription = $this->di['db']->findOne('Subscription', 'sid = :sid', [':sid' => $stripeSubscription->id]);
        if ($existingSubscription instanceof Model_Subscription) {
            return;
        }

        $sd = [
            'client_id' => $clientId,
            'gateway_id' => $gateway_id,
            'currency' => $stripeSubscription->currency ?? '',
            'sid' => $stripeSubscription->id,
            'status' => 'active',
            'period' => $this->getSubscriptionPeriodForInvoiceId((int) $invoiceId),
            'amount' => ($stripeSubscription->plan->amount ?? 0) / 100,
            'rel_type' => 'invoice',
            'rel_id' => $invoiceId,
        ];

        $api_admin->invoice_subscription_create($sd);
    }

    private function handleSubscriptionUpdated($api_admin, Model_Transaction $tx, object $event): void
    {
        $stripeSubscription = $event->data->object;

        $s = $api_admin->invoice_subscription_get(['sid' => $stripeSubscription->id]);

        $status = match ($stripeSubscription->status) {
            'active' => 'active',
            'trialing' => 'active',
            'past_due' => 'active',
            default => 'canceled',
        };

        $api_admin->invoice_subscription_update(['id' => $s['id'], 'status' => $status]);
    }

    private function handleSubscriptionDeleted($api_admin, Model_Transaction $tx, object $event): void
    {
        $stripeSubscription = $event->data->object;

        try {
            $s = $api_admin->invoice_subscription_get(['sid' => $stripeSubscription->id]);
            $api_admin->invoice_subscription_update(['id' => $s['id'], 'status' => 'canceled']);
        } catch (Exception $e) {
            if (DEBUG) {
                error_log('Stripe subscription deleted webhook: ' . $e->getMessage());
            }
        }
    }

    private function handleInvoicePaymentSucceeded($api_admin, Model_Transaction $tx, object $event, int $gateway_id): void
    {
        $stripeInvoice = $event->data->object;

        if (empty($stripeInvoice->subscription)) {
            return;
        }

        $stripeSubscription = $this->stripe->subscriptions->retrieve($stripeInvoice->subscription, []);
        $invoiceId = $stripeSubscription->metadata->invoice_id ?? null;
        $clientId = $stripeSubscription->metadata->client_id ?? null;

        if (!$clientId) {
            return;
        }

        $isInitialPayment = ($stripeInvoice->billing_reason ?? '') === 'subscription_create';

        $bd = [
            'id' => $clientId,
            'amount' => ($stripeInvoice->amount_paid ?? 0) / 100,
            'description' => $isInitialPayment
                ? 'Stripe subscription initial payment ' . $stripeInvoice->id
                : 'Stripe subscription recurring payment ' . $stripeInvoice->id,
            'type' => 'Stripe',
            'rel_id' => $stripeInvoice->id,
        ];

        $transactionService = $this->di['mod_service']('Invoice', 'Transaction');
        if (!$transactionService->claimForProcessing($tx->id)) {
            return;
        }

        $api_admin->client_balance_add_funds($bd);

        $invoiceService = $this->di['mod_service']('Invoice');

        if ($isInitialPayment && $invoiceId) {
            $invoiceModel = $this->di['db']->getExistingModelById('Invoice', (int) $invoiceId);
            $tx->invoice_id = $invoiceModel->id;

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
    }

    private function handleInvoicePaymentFailed($api_admin, Model_Transaction $tx, object $event): void
    {
        $stripeInvoice = $event->data->object;

        if (empty($stripeInvoice->subscription)) {
            return;
        }

        try {
            $s = $api_admin->invoice_subscription_get(['sid' => $stripeInvoice->subscription]);
            $api_admin->invoice_subscription_update(['id' => $s['id'], 'status' => 'canceled']);
        } catch (Exception $e) {
            if (DEBUG) {
                error_log('Stripe invoice payment failed webhook: ' . $e->getMessage());
            }
        }
    }

    private function getOrCreateCustomer(Model_Invoice $invoice): Stripe\Customer
    {
        $customers = $this->stripe->customers->search([
            'query' => "email:'" . addslashes($invoice->buyer_email) . "'",
            'limit' => 1,
        ]);

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
        ]);
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
        $escapedName = addslashes($productName);

        $products = $this->stripe->products->search([
            'query' => "name:'" . $escapedName . "'",
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
            'unit_amount' => $amount,
            'currency' => $currency,
            'limit' => 1,
        ]);

        if (count($prices->data) > 0) {
            return $prices->data[0];
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

    private function getSubscriptionPeriodForInvoiceId(int $invoiceId): string
    {
        $query = 'SELECT period FROM invoice_item WHERE invoice_id = :id LIMIT 1';
        $period = $this->di['db']->getCell($query, [':id' => $invoiceId]);

        return $period ?? '1M';
    }

    private function convertPeriodToStripe(string $period): string
    {
        $unit = preg_replace('/[^A-Za-z]/', '', $period);

        return match (strtoupper($unit)) {
            'D' => 'day',
            'W' => 'week',
            'M' => 'month',
            'Y' => 'year',
            default => 'month',
        };
    }

    private function getClientFromTransaction(Model_Transaction $tx, Stripe\PaymentIntent $charge): Model_Client
    {
        if ($charge->customer) {
            $client = $this->di['db']->findOne('Client', 'stripe_customer_id = :customer_id', [':customer_id' => $charge->customer]);
            if ($client instanceof Model_Client) {
                return $client;
            }
        }

        throw new Payment_Exception('Unable to determine client for transaction. No invoice or customer information available.');
    }

    protected function _generateForm(Model_Invoice $invoice): string
    {
        $intent = $this->stripe->paymentIntents->create([
            'amount' => $this->getAmountInCents($invoice),
            'currency' => $invoice->currency,
            'description' => $this->getInvoiceTitle($invoice),
            'automatic_payment_methods' => ['enabled' => true],
            'receipt_email' => $invoice->buyer_email,
        ]);

        $pubKey = ($this->config['test_mode']) ? $this->config['test_pub_key'] : $this->config['pub_key'];

        $dataAmount = $this->getAmountInCents($invoice);

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

                    var elements = stripe.elements({
                        clientSecret: \':intent_secret\',
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
            ':buyer_email' => $invoice->buyer_email,
            ':buyer_name' => trim($invoice->buyer_first_name . ' ' . $invoice->buyer_last_name),
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
