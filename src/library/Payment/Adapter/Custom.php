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
    protected ?\Pimple\Container $di = null;

    public function __construct(private $config)
    {
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
     * @return string - html form with auto submit javascript
     */
    public function getHtml(Api_Handler $api_admin, int $invoice_id, bool $subscription): string
    {
        $invoiceModel = $this->di['db']->load('Invoice', $invoice_id);
        $invoiceService = $this->di['mod_service']("Invoice");
        $invoice = $invoiceService->toApiArray($invoiceModel, true);

        $vars = array(
            '_client_id'    => $invoice['client']['id'],
            'invoice'   =>  $invoice,
            '_tpl'      =>  $subscription ? ($this->config['recurrent'] ?? '"Custom" payment adapter is not fully configured.') : ($this->config['single'] ?? '"Custom" payment adapter is not fully configured.'),
        );
        $systemService = $this->di['mod_service']('System');
        return $systemService->renderString($vars['_tpl'], true, $vars);
    }

    public function processTransaction(Api_Handler $api_admin, int $id, array $data, int $gateway_id)
    {
        return true;
    }
}
