<?php

/**
 * Copyright 2022-2024 FOSSBilling
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

    public static function getConfig()
    {
        return [
            'supports_one_time_payments' => true,
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

    public function getHtml($api_admin, $invoice_id, $subscription)
    {
        $invoiceModel = $this->di['db']->load('Invoice', $invoice_id);

        return $this->_generateForm($invoiceModel);
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

    public function logError($e, Model_Transaction $tx)
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

    public function processTransaction($api_admin, $id, $data, $gateway_id)
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
            $charge = $this->stripe->paymentIntents->retrieve(
                $data['get']['payment_intent'],
                []
            );

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
        } catch (Stripe\Exception\CardException|Stripe\Exception\InvalidRequestException|Stripe\Exception\AuthenticationException|Stripe\Exception\ApiConnectionException|Stripe\Exception\ApiErrorException $e) {
            $this->logError($e, $tx);

            throw new FOSSBilling\Exception('There was an error when processing the transaction');
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
}
