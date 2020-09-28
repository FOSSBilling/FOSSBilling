<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

class Payment_Adapter_SimplePay implements \Box\InjectionAwareInterface
{
    private $config = array();

    protected $di;

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

        if(!isset($this->config['api_key'])) {
            throw new Payment_Exception('Payment gateway "SimplePay" is not configured properly. Please update configuration parameter "api_key" at "Configuration -> Payments".');
        }

        if(!isset($this->config['pub_key'])) {
            throw new Payment_Exception('Payment gateway "SimplePay" is not configured properly. Please update configuration parameter "pub_key" at "Configuration -> Payments".');
        }
    }

    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'   =>  true,
            'description'     =>  ' You authenticate to the SimplePay API by providing one of your API keys in the request. You can manage your API keys from your account.',
            'form'  => array(
                'test_api_key' => array('text', array(
                    'label' => 'Test Secret key:',
                    'required' => false,
                ),
                ),
               'test_pub_key' => array('text', array(
                   'label' => 'Test Publishable key:',
                   'required' => false,
               ),
               ),
                'api_key' => array('text', array(
                    'label' => 'Live Secret key:',
                ),
                ),
               'pub_key' => array('text', array(
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
            ':id'=>sprintf('%05s', $invoice->nr),
            ':serie'=>$invoice->serie,
            ':title'=>$invoiceItems[0]['title']);
        $title = __('Payment for invoice :serie:id [:title]', $params);
        if(count($invoiceItems) > 1) {
            $title = __('Payment for invoice :serie:id', $params);
        }
        return $title;
    }

    public function logError(Exception $e, Model_Transaction $tx)
    {
        $body           = $e->getJsonBody();
        $err            = $body['error'];
        $tx->txn_status = $err ['type'];
        $tx->error      = $err['message'];
        $tx->status     = 'processed';
        $tx->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($tx);


        if ($this->di['config']['debug']){
            error_log(json_encode($e->getJsonBody()));
        }
        throw new Exception($tx->error);
    }

    public function processTransaction($api_admin, $id, $data, $gateway_id)
    {

        $invoice = $this->di['db']->getExistingModelById('Invoice', $data['get']['bb_invoice_id']);
        $tx      = $this->di['db']->getExistingModelById('Transaction', $id);

        $title = $this->getInvoiceTitle($invoice);

    $invoiceService = $this->di['mod_service']('Invoice');

        $tx->invoice_id = $invoice->id;
        $tx->type = 'SimplePay Checkout';

        $tx->txn_status = "success";
        $tx->txn_id = $data['post']['simplePayToken'];
        $tx->amount = $invoiceService->getTotalWithTax($invoice);
        $tx->currency = $invoice->currency;

        $bd = array(
            'amount'        =>  $tx->amount,
            'description'   =>  'SimplePay transaction '.$data['post']['simplePayToken'],
            'type'          =>  'transaction',
            'rel_id'        =>  $tx->id,
        );
        $client = $this->di['db']->getExistingModelById('Client', $invoice->client_id);
        $clientService = $this->di['mod_service']('client');
        $clientService->addFunds($client, $bd['amount'], $bd['description'], $bd);
        if($tx->invoice_id) {
            $invoiceService->payInvoiceWithCredits($invoice);
        }
        $invoiceService->doBatchPayWithCredits(array('client_id'=>$client->id));

        $tx->status = 'processed';
        $tx->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($tx);
    }

    /**
     * @param string $url
     */
    protected function _generateForm(Model_Invoice $invoice)
    {
        $pubKey = $this->config['pub_key'];
        if ($this->config['test_mode']){
            $pubKey = $this->get_test_pub_key();
        }

        $dataAmount = $this->getAmountInCents($invoice);

        $settingService = $this->di['mod_service']('System');
        $company = $settingService->getCompany();

        $title = $this->getInvoiceTitle($invoice);

        $form = '<form action=":callbackUrl" method="POST" class="api_form" data-api-redirect=":redirectUrl">
                 <div class="loading" style="display:none;"><span>{% trans \'Loading ...\' %}</span></div>
                 <script src="https://checkout.simplepay.ng/simplepay.js"></script>
                 <script>
                     var handler = SimplePay.configure({
                                                token: handleTokenCall,
                                                key: \':key\',
                                                image: ":image",
                                   });

                    function handleTokenCall(token){
                        form = $(".api_form");
                        form.append($(\'<input type="hidden" name="simplePayToken" />\').val(token));
                        form.submit();

                    }
                 </script>
                 <script>
                    function openHandler(){
                        handler.open(SimplePay.CHECKOUT, {
                               email: ":email",
                               phone: ":phone",
                               description: ":description",
                               address: ":address",
                               postal_code: ":postal",
                               city: ":city",
                               country: ":country",
                               amount: ":amount",
                               currency: ":currency"
                        });
                    };
                    $(document).ready ( function(){
                        openHandler();
                    });

                    document.addEventListener("bb_ajax_post_message_error", function(e) {
                        openHandler();
                    });


                 </script>
                 </form>';

        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        $payGateway = $this->di['db']->findOne('PayGateway', 'gateway = "SimplePay"');
        $bindings = array(
            ':key' => $pubKey,
            ':amount' => $dataAmount,
            ':currency' => $invoice->currency,
            ':name' => $company['name'],
            ':description' => $title,
            ':image' => $company['logo_url'],
            ':email' => $invoice->buyer_email,
            ':phone' => $invoice->buyer_phone,
            ':address' => $invoice->buyer_address,
            ':postal' => $invoice->buyer_zip,
            ':city' => $invoice->buyer_city,
            ':country' => $invoice->buyer_country,
            ':callbackUrl' => $payGatewayService->getCallbackUrl($payGateway, $invoice),
            ':redirectUrl' => $this->di['tools']->url('invoice/'.$invoice->hash)
        );
    
        return strtr($form, $bindings);
    }

    public function get_test_pub_key()
    {
        if(!isset($this->config['test_pub_key'])) {
            throw new Payment_Exception('Payment gateway "SimplePay" is not configured properly. Please update configuration parameter "test_pub_key" at "Configuration -> Payments".');
        }
        return $this->config['test_pub_key'];
    }

    public function get_test_api_key()
    {
        if(!isset($this->config['test_api_key'])) {
            throw new Payment_Exception('Payment gateway "SimplePay" is not configured properly. Please update configuration parameter "test_api_key" at "Configuration -> Payments".');
        }
        return $this->config['test_api_key'];
    }
}
