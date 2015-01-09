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

class Payment_Adapter_Interkassa extends Payment_AdapterAbstract
{
    public function init()
    {
        if(!$this->getParam('ik_shop_id')) {
            throw new Payment_Exception('Shop ID is missing in gateway configuration');
        }
        if(!$this->getParam('ik_secret_key')) {
            throw new Payment_Exception('Secret key is missing in gateway configuration');
        }
    }

	/**
	 * Return gateway type
	 *
	 * @return string
	*/
    public function getType()
    {
        return Payment_AdapterAbstract::TYPE_FORM;
    }

    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'     =>  false,
            'description'     =>  'это удобный в использовании сервис, подключение к которому позволит Интернет-магазинам, веб-сайтам и прочим торговым площадкам принимать все возможные формы оплаты в максимально короткие сроки. http://www.interkassa.com/',
            'form'  => array(
                'ik_shop_id' => array('text', array(
                            'label' => 'Shop ID which is registered in "INTERKASSA" system. Can be found under area "Настройки магазина". Example: 64C18529-4B94-0B5D-7405-F2752F2B716C',
                            'value' => '',
                    ),
                 ),
                'ik_secret_key' => array('password', array(
                            'label' => 'Secret Key. This is a line with characters, which is added to payment requisites, which is sent to seller with a notification. It is used to check and form data signature.',
                            'value' => '',
                    ),
                 ),
            ),
        );
    }

	/**
	 * Return service call url
	 *
	 * @return string
	*/
    public function getServiceUrl()
    {
        return 'https://api.interkassa.com/v1/';
    }

	/**
	 * Init single payment call to webservice
	 * Invoice id is passed via notify_url
     *
	 * @return mixed
	*/
    public function singlePayment(Payment_Invoice $invoice)
    {
        $buyer = $invoice->getBuyer();
        return array(
            'ik_shop_id'            => $this->getParam('ik_shop_id'),
            'ik_payment_amount'     => $invoice->getTotalWithTax(),
            'ik_payment_id'         => $invoice->getId(),
            'ik_payment_desc'       => $invoice->getTitle(),
            'ik_paysystem_alias'    => '',

            'ik_baggage_fields'     => $buyer->getEmail(),

            'ik_success_url'     => $this->getParam('return_url'),
            'ik_success_method'  => 'GET',
            'ik_fail_url'        => $this->getParam('cancel_url'),
            'ik_fail_method'     => 'GET',
            'ik_status_url'      => $this->getParam('notify_url'),
            'ik_status_method'   => 'POST',
        );
    }

	/**
	 * Init recurrent payment call to webservice
	 *
	 * @return mixed
	*/
    public function recurrentPayment(Payment_Invoice $invoice)
    {
        throw new Payment_Exception('Interkassa payment gateway do not support recurrent payments');
    }

    /**
     * Handle IPN and return response object
     * @return Payment_Transaction
     */
    public function getTransaction($data, Payment_Invoice $invoice)
    {
        $ipn = $data['post'];

        $tx = new Payment_Transaction();
        $tx->setId($ipn['ik_payment_id']);
        $tx->setAmount($ipn['ik_payment_amount']);
        $tx->setCurrency($invoice->getCurrency());
        $tx->setType(Payment_Transaction::TXTYPE_PAYMENT);

        if($ipn['ik_payment_state'] == 'success' || $ipn['ik_payment_state'] == 'completed') {
            $tx->setStatus(Payment_Transaction::STATUS_COMPLETE);
        }
        
        return $tx;
    }

    public function isIpnValid($data, Payment_Invoice $invoice)
    {
        $status_data = $data['post'];
        $shop_id = $this->getParam('ik_shop_id');
        $secret_key = $this->getParam('ik_secret_key');
        
        if($shop_id != $status_data['ik_shop_id']) {
            error_log('Shop ids does not match');
            return false;
        }

        $sing_hash_str = $status_data['ik_shop_id'].':'.
                    $status_data['ik_payment_amount'].':'.
                    $status_data['ik_payment_id'].':'.
                    $status_data['ik_paysystem_alias'].':'.
                    $status_data['ik_baggage_fields'].':'.
                    $status_data['ik_payment_state'].':'.
                    $status_data['ik_trans_id'].':'.
                    $status_data['ik_currency_exch'].':'.
                    $status_data['ik_fees_payer'].':'.
                    $secret_key;
        $sign_hash = strtoupper(md5($sing_hash_str));
        return ($status_data['ik_sign_hash'] === $sign_hash);
    }
}