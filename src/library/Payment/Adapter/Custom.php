<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

class Payment_Adapter_Custom
{
    private $config = array();
    protected ?\Pimple\Container $di;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function setDi(\Pimple\Container $di): void
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
            'logo' => array(
                'logo' => 'custom.png',
                'height' => '50px',
                'width' => '50px',
            ),
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
     * @since FOSSBilling v2.9.15
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
            '_tpl'      =>  $subscription ? (isset($this->config['recurrent']) ? $this->config['recurrent'] : '"Custom" payment adapter is not fully configured.') : (isset($this->config['single']) ? $this->config['single'] : '"Custom" payment adapter is not fully configured.'),
        );
        $systemService = $this->di['mod_service']('System');
        return $systemService->renderString($vars['_tpl'], true, $vars);
    }

    public function process($tx)
    {
        return true;
    }
}
