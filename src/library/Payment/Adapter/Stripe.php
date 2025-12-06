<?php

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
                        'label' => 'Live Publishable key:',
                    ],
                ],
                'api_key' => [
                    'text', [
                        'label' => 'Live Secret key:',
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
            ],
        ];
    }

    public function getHtml($api_admin, $invoice_id, $subscription): string
    {
        $invoiceModel = $this->di['db']->load('Invoice', $invoice_id);

        if ($subscription) {
            return $this->_generateSubscriptionForm($invoiceModel, $subscription);
        } else {
            return $this->_generateForm($invoiceModel);
        }
    }

    public function getAmountInCents(Model_Invoice $invoice)
    {
        $invoiceService = $this->di['mod_service']('Invoice');

        return $invoiceService->getTotalWithTax($invoice) * 100;
    }

    public function getInvoiceTitle(Model_Invoice $invoice)
    {
        $invoiceItems = $this->di['db']->getAll('SELECT title from invoice_item WHERE invoice_id = :invoice_id', [':invoice_id' => $invoice->id]);

        $params = [
            ':id' => sprintf('%05s', $invoice->nr),
            ':serie' => $invoice->serie,
            ':title' => $invoiceItems[0]['title'],
        ];
        $title = __trans('Payment for invoice :serie:id [:title]', $params);
        if ((is_countable($invoiceItems) ? count($invoiceItems) : 0) > 1) {
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
        $tx->status = 'processed';
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

        // Use the invoice ID associated with the transaction or else fallback to the ID passed via GET.
        if ($tx->invoice_id) {
            $invoice = $this->di['db']->getExistingModelById('Invoice', $tx->invoice_id);
        } else {
            $invoice = $this->di['db']->getExistingModelById('Invoice', $data['get']['invoice_id']);
            $tx->invoice_id = $invoice->id;
        }

        try {
            // Handle subscription webhook
            if (isset($data['post']['type']) && strpos($data['post']['type'], 'customer.subscription') !== false) {
                $this->processSubscriptionWebhook($api_admin, $id, $data, $gateway_id);
                return;
            }

            // Handle regular payment intent
            if (isset($data['get']['payment_intent'])) {
                $this->processPaymentIntent($api_admin, $tx, $invoice, $data);
            }
            // Handle subscription setup intent
            elseif (isset($data['get']['setup_intent'])) {
                $this->processSetupIntent($api_admin, $tx, $invoice, $data, $gateway_id);
            }
        } catch (Stripe\Exception\CardException|Stripe\Exception\InvalidRequestException|Stripe\Exception\AuthenticationException|Stripe\Exception\ApiConnectionException|Stripe\Exception\ApiErrorException $e) {
            $this->logError($e, $tx);
            throw new FOSSBilling\Exception('There was an error when processing the transaction');
        }
    }
    
    private function processPaymentIntent($api_admin, $tx, $invoice, $data)
    {
        $charge = $this->stripe->paymentIntents->retrieve($data['get']['payment_intent'], []);

        $tx->txn_status = $charge->status;
        $tx->txn_id = $charge->id;
        $tx->amount = $charge->amount / 100;
        $tx->currency = $charge->currency;

        $bd = [
            'amount' => $tx->amount,
            'description' => 'Stripe transaction ' . $charge->id,
            'type' => 'transaction',
            'rel_id' => $tx->id,
        ];

        // Only pay the invoice if the transaction has 'succeeded' on Stripe's end & the associated FOSSBilling transaction hasn't been processed.
        if ($charge->status == 'succeeded' && $tx->status !== 'processed') {
            // Instance the services we need
            $clientService = $this->di['mod_service']('client');
            $invoiceService = $this->di['mod_service']('Invoice');

            // Update the account funds
            $client = $this->di['db']->getExistingModelById('Client', $invoice->client_id);
            $clientService->addFunds($client, $bd['amount'], $bd['description'], $bd);

            // Now pay the invoice / batch pay if there's no invoice associated with the transaction
            if ($tx->invoice_id) {
                $invoiceService->payInvoiceWithCredits($invoice);
            } else {
                $invoiceService->doBatchPayWithCredits(['client_id' => $client->id]);
            }
        }

        $paymentStatus = match ($charge->status) {
            'succeeded' => 'processed',
            'pending' => 'received',
            'failed' => 'error',
        };

        $tx->status = $paymentStatus;
        $tx->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($tx);
    }

    private function processSetupIntent($api_admin, $tx, $invoice, $data, $gateway_id)
    {
        $setupIntent = $this->stripe->setupIntents->retrieve($data['get']['setup_intent'], []);

        $tx->txn_status = $setupIntent->status;
        $tx->txn_id = $setupIntent->id;
        $tx->currency = $invoice->currency;

        if ($setupIntent->status == 'succeeded') {
            // Create customer if not exists
            $customer = $this->getOrCreateCustomer($invoice);
            
            // Create subscription
            $subscription = $this->createSubscription($customer, $setupIntent, $invoice);
            
            // Save subscription details
            $sd = [
                'client_id' => $invoice->client_id,
                'gateway_id' => $gateway_id,
                'currency' => $invoice->currency,
                'sid' => $subscription->id,
                'status' => 'active',
                'period' => $this->convertStripePeriodToFOSSBilling($subscription->items->data[0]->price->recurring->interval),
                'amount' => $subscription->items->data[0]->price->unit_amount / 100,
                'rel_type' => 'invoice',
                'rel_id' => $invoice->id,
            ];

            $api_admin->invoice_subscription_create($sd);

            // Pay the initial invoice immediately
            $invoiceService = $this->di['mod_service']('Invoice');
            $clientService = $this->di['mod_service']('client');
            $client = $this->di['db']->getExistingModelById('Client', $invoice->client_id);
            
            // Add funds to client account for the initial payment
            $bd = [
                'amount' => $invoiceService->getTotalWithTax($invoice),
                'description' => 'Stripe subscription setup payment ' . $subscription->id,
                'type' => 'transaction',
                'rel_id' => $tx->id,
            ];
            
            $clientService->addFunds($client, $bd['amount'], $bd['description'], $bd);
            
            // Pay the invoice
            $invoiceService->payInvoiceWithCredits($invoice);

            $tx->s_id = $subscription->id;
            $tx->s_period = $sd['period'];
            $tx->amount = $bd['amount'];
            $tx->status = 'processed';
        }

        $tx->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($tx);
    }

    private function processSubscriptionWebhook($api_admin, $id, $data, $gateway_id)
    {
        $event = $data['post'];
        $subscription = $event['data']['object'];

        switch ($event['type']) {
            case 'customer.subscription.created':
                // Already handled in processSetupIntent
                break;

            case 'customer.subscription.updated':
                $s = $api_admin->invoice_subscription_get(['sid' => $subscription['id']]);
                $status = $subscription['status'] == 'active' ? 'active' : 'canceled';
                $api_admin->invoice_subscription_update(['id' => $s['id'], 'status' => $status]);
                break;

            case 'customer.subscription.deleted':
                $s = $api_admin->invoice_subscription_get(['sid' => $subscription['id']]);
                $api_admin->invoice_subscription_update(['id' => $s['id'], 'status' => 'canceled']);
                break;

            case 'invoice.payment_succeeded':
                $invoice = $subscription;
                $stripeSubscription = $this->stripe->subscriptions->retrieve($invoice['subscription'], []);
                
                if ($stripeSubscription) {
                    $s = $api_admin->invoice_subscription_get(['sid' => $stripeSubscription->id]);
                    
                    if ($s) {
                        $invoiceModel = $this->di['db']->getExistingModelById('Invoice', $s['rel_id']);
                        
                        // Check if this is the first payment (initial invoice)
                        $isInitialPayment = $invoice['billing_reason'] === 'subscription_create';
                        
                        $bd = [
                            'id' => $invoiceModel->client_id,
                            'amount' => $invoice['amount_paid'] / 100,
                            'description' => $isInitialPayment ? 
                                'Stripe subscription initial payment ' . $invoice['id'] : 
                                'Stripe subscription payment ' . $invoice['id'],
                            'type' => 'Stripe',
                            'rel_id' => $invoice['id'],
                        ];
                        
                        $api_admin->client_balance_add_funds($bd);
                        
                        if ($isInitialPayment) {
                            // Pay the specific invoice for initial payment
                            $invoiceService = $this->di['mod_service']('Invoice');
                            $invoiceService->payInvoiceWithCredits($invoiceModel);
                        } else {
                            // For recurring payments, use batch pay
                            $api_admin->invoice_batch_pay_with_credits(['client_id' => $invoiceModel->client_id]);
                        }
                    }
                }
                break;

            case 'invoice.payment_failed':
                $invoice = $subscription;
                $subscription = $this->stripe->subscriptions->retrieve($invoice['subscription'], []);
                
                if ($subscription) {
                    $s = $api_admin->invoice_subscription_get(['sid' => $subscription->id]);
                    $api_admin->invoice_subscription_update(['id' => $s['id'], 'status' => 'canceled']);
                }
                break;
        }
    }

    private function getOrCreateCustomer(Model_Invoice $invoice)
    {
        // Try to find existing customer
        $customers = $this->stripe->customers->search([
            'query' => "email:'" . $invoice->buyer_email . "'",
        ]);

        if (count($customers->data) > 0) {
            return $customers->data[0];
        }

        // Create new customer
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

    private function createSubscription($customer, $setupIntent, $invoice)
    {
        // Get or create product and price
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

    private function getOrCreateProduct(Model_Invoice $invoice)
    {
        $invoiceItems = $this->di['db']->getAll('SELECT title from invoice_item WHERE invoice_id = :invoice_id', [':invoice_id' => $invoice->id]);
        if (empty($invoiceItems)) {
            throw new \RuntimeException('No invoice items found for the given invoice ID: ' . $invoice->id);
        }
        if (empty($invoiceItems)) {
            throw new \RuntimeException('No invoice items found for invoice ID: ' . $invoice->id);
        }
        $productName = $invoiceItems[0]['title'];

        // Try to find existing product
        $products = $this->stripe->products->search([
            'query' => "name:'" . $productName . "'",
        ]);

        if (count($products->data) > 0) {
            return $products->data[0];
        }

        // Create new product
        return $this->stripe->products->create([
            'name' => $productName,
            'description' => $this->getInvoiceTitle($invoice),
        ]);
    }

    private function getOrCreatePrice($product, $invoice)
    {
        $invoiceService = $this->di['mod_service']('Invoice');
        $amount = $invoiceService->getTotalWithTax($invoice) * 100;

        // Get subscription details from invoice
        $subscription = $invoice->subscription ?? null;
        $interval = 'month'; // default

        if ($subscription) {
            $interval = $this->convertFOSSBillingPeriodToStripe($subscription['unit']);
        }

        // Try to find existing price
        $prices = $this->stripe->prices->all([
            'product' => $product->id,
            'recurring' => [
                'interval' => $interval
            ],
            'unit_amount' => $amount,
            'currency' => strtolower($invoice->currency),
        ]);

        if (count($prices->data) > 0) {
            return $prices->data[0];
        }

        // Create new price
        return $this->stripe->prices->create([
            'product' => $product->id,
            'unit_amount' => $amount,
            'currency' => strtolower($invoice->currency),
            'recurring' => [
                'interval' => $interval,
            ],
        ]);
    }

    private function convertFOSSBillingPeriodToStripe($period)
    {
        return match (strtoupper($period)) {
            'D' => 'day',
            'W' => 'week',
            'M' => 'month',
            'Y' => 'year',
            default => 'month',
        };
    }

    private function convertStripePeriodToFOSSBilling($period)
    {
        return match ($period) {
            'day' => 'D',
            'week' => 'W',
            'month' => 'M',
            'year' => 'Y',
            default => 'M',
        };
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

                    <button id="submit" class="btn btn-primary mt-2" style="margin-top: 0.5em;">Pay Now</button>

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

    protected function _generateSubscriptionForm(Model_Invoice $invoice, $subscription): string
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

        $form = '<form id="subscription-form" data-secret=":setup_intent_secret">
                <div class="loading" style="display:none;"><span>{% trans \'Loading ...\' %}</span></div>
                <script src="https://js.stripe.com/v3/"></script>

                    <div id="error-message">
                        <!-- Error messages will be displayed here -->
                    </div>
                    <div id="payment-element">
                        <!-- Stripe Elements will create form elements here -->
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

        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        $payGateway = $this->di['db']->findOne('PayGateway', 'gateway = "Stripe"');
        $bindings = [
            ':pub_key' => $pubKey,
            ':setup_intent_secret' => $setupIntent->client_secret,
            ':buyer_email' => $invoice->buyer_email,
            ':buyer_name' => trim($invoice->buyer_first_name . ' ' . $invoice->buyer_last_name),
            ':callbackUrl' => $payGatewayService->getCallbackUrl($payGateway, $invoice),
            ':invoice_hash' => $invoice->hash,
        ];

        return strtr($form, $bindings);
    }
}
