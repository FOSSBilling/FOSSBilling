<?php

/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

class Payment_Adapter_Stripe implements \Box\InjectionAwareInterface
{
    private $config = array();

    protected $di;

    private $stripe;

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }

    public function __construct($config)
    {
        $this->config = $config;

        if (!isset($this->config['api_key'])) {
            throw new Payment_Exception('Payment gateway "Stripe" is not configured properly. Please update configuration parameter "api_key" at "Configuration -> Payments".');
        }

        if (!isset($this->config['pub_key'])) {
            throw new Payment_Exception('Payment gateway "Stripe" is not configured properly. Please update configuration parameter "pub_key" at "Configuration -> Payments".');
        }

        $api_key = $this->config['test_mode'] ? $this->get_test_api_key() : $this->config['api_key'];

        $this->stripe = new \Stripe\StripeClient($api_key);
    }

    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'   =>  true,
            'description'     =>  ' You authenticate to the Stripe API by providing one of your API keys in the request. You can manage your API keys from your account.',
            'form'  => array(
                'test_api_key' => array(
                    'text', array(
                        'label' => 'Test Secret key:',
                        'required' => false,
                    ),
                ),
                'test_pub_key' => array(
                    'text', array(
                        'label' => 'Test Publishable key:',
                        'required' => false,
                    ),
                ),
                'api_key' => array(
                    'text', array(
                        'label' => 'Live Secret key:',
                    ),
                ),
                'pub_key' => array(
                    'text', array(
                        'label' => 'Live publishable key:',
                    ),
                ),
            ),
        );
    }

    public function getHtml($api_admin, $invoice_id, $subscription)
    {
        $invoiceModel = $this->di['db']->load('Invoice', $invoice_id);

        return $this->_generateForm($invoiceModel);
    }

    public function getAmountInCents(\Model_Invoice $invoice)
    {
        $invoiceService = $this->di['mod_service']('Invoice');
        return $invoiceService->getTotalWithTax($invoice) * 100;
    }

    public function getInvoiceTitle(\Model_Invoice $invoice)
    {
        $invoiceItems = $this->di['db']->getAll('SELECT title from invoice_item WHERE invoice_id = :invoice_id', array(':invoice_id' => $invoice->id));

        $params = array(
            ':id' => sprintf('%05s', $invoice->nr),
            ':serie' => $invoice->serie,
            ':title' => $invoiceItems[0]['title']
        );
        $title = __trans('Payment for invoice :serie:id [:title]', $params);
        if (count($invoiceItems) > 1) {
            $title = __trans('Payment for invoice :serie:id', $params);
        }
        return $title;
    }

    public function logError(Exception $e, Model_Transaction $tx)
    {
        $body           = $e->getJsonBody();
        $err            = $body['error'];
        $tx->txn_status = $err['type'];
        $tx->error      = $err['message'];
        $tx->status     = 'processed';
        $tx->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($tx);


        if ($this->di['config']['debug']) {
            error_log(json_encode($e->getJsonBody()));
        }
        throw new Exception($tx->error);
    }

    public function processTransaction($api_admin, $id, $data, $gateway_id)
    {

        $invoice = $this->di['db']->getExistingModelById('Invoice', $data['get']['bb_invoice_id']);
        $tx      = $this->di['db']->getExistingModelById('Transaction', $id);

        $tx->invoice_id = $invoice->id;
        $tx->type = $data['post']['stripeTokenType'];

        try {
            $charge = $this->stripe->paymentIntents->retrieve(
                $data['get']['payment_intent'],
                []
            );

            if (!isset($charge)) {
                throw new \Exception("Failed to get the charge item from Stripe.");
            }

            $tx->txn_status = $charge->status;
            $tx->txn_id = $charge->id;
            $tx->amount = $charge->amount / 100;
            $tx->currency = $charge->currency;

            $bd = array(
                'amount'        =>  $tx->amount,
                'description'   =>  'Stripe transaction ' . $charge->id,
                'type'          =>  'transaction',
                'rel_id'        =>  $tx->id,
            );

            $client = $this->di['db']->getExistingModelById('Client', $invoice->client_id);
            $clientService = $this->di['mod_service']('client');
            $clientService->addFunds($client, $bd['amount'], $bd['description'], $bd);

            $invoiceService = $this->di['mod_service']('Invoice');
            if ($tx->invoice_id) {
                $invoiceService->payInvoiceWithCredits($invoice);
            }
            $invoiceService->doBatchPayWithCredits(array('client_id' => $client->id));
        } catch (\Stripe\Exception\CardException $e) {
            $this->logError($e, $tx);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            $this->logError($e, $tx);
        } catch (\Stripe\Exception\AuthenticationException $e) {
            $this->logError($e, $tx);
        } catch (\Stripe\Exception\ApiConnectionException $e) {
            $this->logError($e, $tx);
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

    /**
     * @param string $url
     */
    protected function _generateForm(Model_Invoice $invoice)
    {
        $intent = $this->stripe->paymentIntents->create([
            'amount' => $this->getAmountInCents($invoice),
            'currency' => $invoice->currency,
            "description" => $this->getInvoiceTitle($invoice),
            'automatic_payment_methods' => ['enabled' => true],
            "receipt_email" => $invoice->buyer_email
        ]);

        $pubKey = $this->config['pub_key'];
        if ($this->config['test_mode']) {
            $pubKey = $this->get_test_pub_key();
        }

        $dataAmount = $this->getAmountInCents($invoice);

        $settingService = $this->di['mod_service']('System');
        $company = $settingService->getCompany();

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
                            email: \':email\',
                        },
                        business: {
                            name: \':name\',
                        }
                    });

                    paymentElement.mount(\'#payment-element\');

                    const form = document.getElementById(\'payment-form\');

                    form.addEventListener(\'submit\', async (event) => {
                    event.preventDefault();

                    const {error} = await stripe.confirmPayment({
                        elements,
                        confirmParams: {
                            return_url: \':callbackUrl&bb_redirect=true&bb_invoice_hash=:invoice_hash\',
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
        $bindings = array(
            ':pub_key' => $pubKey,
            ':intent_secret' => $intent->client_secret,
            ':amount' => $dataAmount,
            ':currency' => $invoice->currency,
            ':description' => $title,
            ':email' => $invoice->buyer_email,
            ':callbackUrl' => $payGatewayService->getCallbackUrl($payGateway, $invoice),
            ':redirectUrl' => $this->di['tools']->url('invoice/' . $invoice->hash),
            ':invoice_hash' => $invoice->hash
        );
        return strtr($form, $bindings);
    }

    public function get_test_pub_key()
    {
        if (!isset($this->config['test_pub_key'])) {
            throw new Payment_Exception('Payment gateway "Stripe" is not configured properly. Please update configuration parameter "test_pub_key" at "Configuration -> Payments".');
        }
        return $this->config['test_pub_key'];
    }

    public function get_test_api_key()
    {
        if (!isset($this->config['test_api_key'])) {
            throw new Payment_Exception('Payment gateway "Stripe" is not configured properly. Please update configuration parameter "test_api_key" at "Configuration -> Payments".');
        }
        return $this->config['test_api_key'];
    }
}
