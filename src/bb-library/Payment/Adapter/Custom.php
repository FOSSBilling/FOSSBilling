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

class Payment_Adapter_Custom
{
    private $config = array();
    protected $di;

    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * @param Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    public static function getConfig()
    {
        return array(
            'can_load_in_iframe'   =>  true,
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'       =>  true,
            'description'     =>  'Custom payment gateway allows you to give instructions how can your client pay invoice. All system, client, order and invoice details can be printed. HTML and JavaScript code is supported.',
            'form'  => array(
                'single' => array('textarea', array(
                            'label' => 'Enter your text for single payment information',
                    ),
                ),
                'recurrent' => array('textarea', array(
                            'label' => 'Enter your text for subscription information',
                    ),
                ),
            ),
        );
    }

    /**
     * Generate payment text
     * 
     * @param Api_Admin $api_admin
     * @param int $invoice_id
     * @param bool $subscription
     * 
     * @since BoxBilling v2.9.15
     * 
     * @return string - html form with auto submit javascript
     */
    public function getHtml($api_admin, $invoice_id, $subscription)
    {
        $invoiceModel = $this->di['db']->load('Invoice', $invoice_id);
        $invoiceService = $this->di['mod_service']("Invoice");
        $invoice = $invoiceService->toApiArray($invoiceModel, true);

        $vars = array(
            '_client_id'    => $invoice['client']['id'],
            'invoice'   =>  $invoice,
            '_tpl'      =>  $subscription ? (isset($this->config['recurrent']) ? $this->config['recurrent'] : null) : (isset($this->config['single']) ? $this->config['single'] : null),
        );
        $systemService = $this->di['mod_service']('System');
        return $systemService->renderString($vars['_tpl'], true, $vars);
    }

    public function process($tx)
    {
        //do processing
        return true;
    }
}