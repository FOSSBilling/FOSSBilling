<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use FOSSBilling\Environment;

class Payment_Adapter_PayPalEmail extends Payment_AdapterAbstract implements FOSSBilling\InjectionAwareInterface
{
    protected ?Pimple\Container $di = null;

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
        if (!isset($this->config['email'])) {
            throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'PayPal', ':missing' => 'PayPal Email address'], 4001);
        }
    }

    public static function getConfig()
    {
        return [
            'supports_one_time_payments' => true,
            'supports_subscriptions' => true,
            'description' => 'Enter your PayPal email to start accepting payments by PayPal.',
            'logo' => [
                'logo' => 'paypal.png',
                'height' => '25px',
                'width' => '85px',
            ],
            'form' => [
                'email' => [
                    'text',
                    [
                        'label' => 'PayPal email address for payments',
                        'validators' => ['EmailAddress'],
                    ],
                ],
            ],
        ];
    }

    public function getHtml($api_admin, $invoice_id, $subscription)
    {
        $invoice = $api_admin->invoice_get(['id' => $invoice_id]);

        $data = [];
        if ($subscription) {
            $data = $this->getSubscriptionFields($invoice);
        } else {
            $data = $this->getOneTimePaymentFields($invoice);
        }

        $url = $this->serviceUrl();

        return $this->_generateForm($url, $data);
    }

    public function processTransaction($api_admin, $id, $data, $gateway_id)
    {
        if (!Environment::isTesting() && !$this->_isIpnValid($data)) {
            throw new Payment_Exception('IPN is invalid');
        }

        $ipn = $data['post'];

        $tx = $api_admin->invoice_transaction_get(['id' => $id]);

        // Set the invoice ID if it's not set
        if (!$tx['invoice_id']) {
            $invoiceID = $data['get']['invoice_id'];
            $tx['invoiceID'] = $invoiceID;
            $api_admin->invoice_transaction_update(['id' => $id, 'invoice_id' => $invoiceID]);
        }

        if (!$tx['type'] && isset($ipn['txn_type'])) {
            $api_admin->invoice_transaction_update(['id' => $id, 'type' => $ipn['txn_type']]);
        }

        if (!$tx['txn_id'] && isset($ipn['txn_id'])) {
            $api_admin->invoice_transaction_update(['id' => $id, 'txn_id' => $ipn['txn_id']]);
        }

        if (!$tx['txn_status'] && isset($ipn['payment_status'])) {
            $api_admin->invoice_transaction_update(['id' => $id, 'txn_status' => $ipn['payment_status']]);
        }

        if (!$tx['amount'] && isset($ipn['mc_gross'])) {
            $api_admin->invoice_transaction_update(['id' => $id, 'amount' => $ipn['mc_gross']]);
        }

        if (!$tx['currency'] && isset($ipn['mc_currency'])) {
            $api_admin->invoice_transaction_update(['id' => $id, 'currency' => $ipn['mc_currency']]);
        }

        $invoice = $api_admin->invoice_get(['id' => $tx['invoice_id']]);
        $client_id = $invoice['client']['id'];

        switch ($ipn['txn_type']) {
            case 'web_accept':
            case 'subscr_payment':
                if ($ipn['payment_status'] == 'Completed') {
                    $bd = [
                        'id' => $client_id,
                        'amount' => $ipn['mc_gross'],
                        'description' => 'PayPal transaction ' . $ipn['txn_id'],
                        'type' => 'PayPal',
                        'rel_id' => $ipn['txn_id'],
                    ];
                    if ($this->isIpnDuplicate($ipn)) {
                        throw new Payment_Exception('Cannot process duplicate IPN');
                    }
                    $api_admin->client_balance_add_funds($bd);
                    if ($tx['invoice_id']) {
                        $api_admin->invoice_pay_with_credits(['id' => $tx['invoice_id']]);
                    } else {
                        $api_admin->invoice_batch_pay_with_credits(['client_id' => $client_id]);
                    }
                }

                break;

            case 'subscr_signup':
                $sd = [
                    'client_id' => $client_id,
                    'gateway_id' => $gateway_id,
                    'currency' => $ipn['mc_currency'],
                    'sid' => $ipn['subscr_id'],
                    'status' => 'active',
                    'period' => str_replace(' ', '', $ipn['period3']),
                    'amount' => $ipn['amount3'],
                    'rel_type' => 'invoice',
                    'rel_id' => $invoice['id'],
                ];
                $api_admin->invoice_subscription_create($sd);

                $t = [
                    'id' => $id,
                    's_id' => $sd['sid'],
                    's_period' => $sd['period'],
                ];
                $api_admin->invoice_transaction_update($t);

                break;

            case 'recurring_payment_suspended_due_to_max_failed_payment':
            case 'subscr_failed':
            case 'subscr_eot':
            case 'subscr_cancel':
                $s = $api_admin->invoice_subscription_get(['sid' => $ipn['subscr_id']]);
                $api_admin->invoice_subscription_update(['id' => $s['id'], 'status' => 'canceled']);

                break;

            default:
                error_log('Unknown paypal transaction ' . $id);

                break;
        }

        if (isset($ipn['payment_status']) && $ipn['payment_status'] == 'Refunded') {
            $refd = [
                'id' => $invoice['id'],
                'note' => 'PayPal refund ' . $ipn['parent_txn_id'],
            ];
            $api_admin->invoice_refund($refd);
        }

        $d = [
            'id' => $id,
            'error' => '',
            'error_code' => '',
            'status' => 'processed',
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $api_admin->invoice_transaction_update($d);
    }

    private function serviceUrl()
    {
        if ($this->config['test_mode']) {
            return 'https://www.sandbox.paypal.com/cgi-bin/webscr';
        } else {
            return 'https://www.paypal.com/cgi-bin/webscr';
        }
    }

    private function _isIpnValid($data)
    {
        // use http_raw_post_data instead of post due to encoding
        parse_str($data['http_raw_post_data'], $post);
        $req = 'cmd=_notify-validate';
        foreach ((array) $post as $key => $value) {
            $value = urlencode(stripslashes($value));
            $req .= "&$key=$value";
        }
        $url = $this->serviceUrl();
        $ret = $this->download($url, $req);

        return $ret == 'VERIFIED';
    }

    public function moneyFormat($amount, $currency = null)
    {
        // HUF currency do not accept decimal values
        if ($currency == 'HUF') {
            return number_format($amount, 0);
        }

        return number_format($amount, 2, '.', '');
    }

    /**
     * @param string $url
     */
    private function download($url, $post_vars = false)
    {
        $post_contents = '';
        if ($post_vars) {
            if (is_array($post_vars)) {
                foreach ($post_vars as $key => $val) {
                    $post_contents .= ($post_contents ? '&' : '') . urlencode($key) . '=' . urlencode($val);
                }
            } else {
                $post_contents = $post_vars;
            }
        }

        $client = $this->getHttpClient()->withOptions([
            'verify_peer' => false,
            'verify_host' => false,
            'timeout' => 600,
        ]);
        $response = $client->request('POST', $url, [
            'body' => $post_contents,
        ]);

        return $response->getContent();
    }

    /**
     * @param string $url
     */
    private function _generateForm($url, $data, $method = 'post')
    {
        $form = '';
        $form .= '<form name="payment_form" action="' . $url . '" method="' . $method . '">' . PHP_EOL;
        foreach ($data as $key => $value) {
            $form .= sprintf('<input type="hidden" name="%s" value="%s" />', $key, $value) . PHP_EOL;
        }
        $form .= '<input class="btn btn-primary" type="submit" value="Pay with PayPal" id="payment_button"/>' . PHP_EOL;
        $form .= '</form>' . PHP_EOL . PHP_EOL;

        if (isset($this->config['auto_redirect']) && $this->config['auto_redirect']) {
            $form .= sprintf('<h2>%s</h2>', __trans('Redirecting to PayPal.com'));
            $form .= "<script type='text/javascript'>$(document).ready(function(){    document.getElementById('payment_button').style.display = 'none';    document.forms['payment_form'].submit();});</script>";
        }

        return $form;
    }

    public function isIpnDuplicate(array $ipn): bool
    {
        $sql = 'SELECT id
                FROM transaction
                WHERE txn_id = :transaction_id
                  AND txn_status = :transaction_status
                  AND type = :transaction_type
                  AND amount = :transaction_amount
                LIMIT 2';

        $bindings = [
            ':transaction_id' => $ipn['txn_id'],
            ':transaction_status' => $ipn['payment_status'],
            ':transaction_type' => $ipn['txn_type'],
            ':transaction_amount' => $ipn['mc_gross'],
        ];

        $rows = $this->di['db']->getAll($sql, $bindings);
        if ((is_countable($rows) ? count($rows) : 0) > 1) {
            return true;
        }

        return false;
    }

    public function getInvoiceTitle(array $invoice)
    {
        $p = [
            ':id' => sprintf('%05s', $invoice['nr']),
            ':serie' => $invoice['serie'],
            ':title' => $invoice['lines'][0]['title'],
        ];

        return __trans('Payment for invoice :serie:id [:title]', $p);
    }

    public function getSubscriptionFields(array $invoice): array
    {
        $data = [];
        $subs = $invoice['subscription'];

        $data['item_name'] = $this->getInvoiceTitle($invoice);
        $data['item_number'] = $invoice['nr'];
        $data['no_shipping'] = '1';
        $data['no_note'] = '1'; // Do not prompt payers to include a note with their payments. Allowable values for Subscribe buttons:
        $data['currency_code'] = $invoice['currency'];
        $data['return'] = $this->config['thankyou_url'];
        $data['cancel_return'] = $this->config['cancel_url'];
        $data['notify_url'] = $this->config['notify_url'];
        $data['business'] = $this->config['email'];

        $data['cmd'] = '_xclick-subscriptions';
        $data['rm'] = '2';

        $data['invoice'] = $invoice['id'];

        // Recurrence info
        $data['a3'] = $this->moneyFormat($invoice['total'], $invoice['currency']); // Regular subscription price.
        $data['p3'] = $subs['cycle']; // Subscription duration. Specify an integer value in the allowable range for the units of duration that you specify with t3.

        /*
         * t3: Regular subscription units of duration. Allowable values:
         *  D – for days; allowable range for p3 is 1 to 90
         *  W – for weeks; allowable range for p3 is 1 to 52
         *  M – for months; allowable range for p3 is 1 to 24
         *  Y – for years; allowable range for p3 is 1 to 5
         */
        $data['t3'] = $subs['unit'];

        $data['src'] = 1; // Recurring payments. Subscription payments recur unless subscribers cancel their subscriptions before the end of the current billing cycle or you limit the number of times that payments recur with the value that you specify for srt.
        $data['sra'] = 1; // Reattempt on failure. If a recurring payment fails, PayPal attempts to collect the payment two more times before canceling the subscription.
        $data['charset'] = 'UTF-8'; // Sets the character encoding for the billing information/log-in page, for the information you send to PayPal in your HTML button code, and for the information that PayPal returns to you as a result of checkout processes initiated by the payment button. The default is based on the character encoding settings in your account profile.

        // client data
        $buyer = $invoice['buyer'];
        $data['address1'] = $buyer['address'];
        $data['city'] = $buyer['city'];
        $data['email'] = $buyer['email'];
        $data['first_name'] = $buyer['first_name'];
        $data['last_name'] = $buyer['last_name'];
        $data['zip'] = $buyer['zip'];
        $data['state'] = $buyer['state'];
        $data['bn'] = 'FOSSBilling_SP';

        return $data;
    }

    public function getOneTimePaymentFields(array $invoice): array
    {
        $data = [];
        $data['item_name'] = $this->getInvoiceTitle($invoice);
        $data['item_number'] = $invoice['nr'];
        $data['no_shipping'] = '1';
        $data['no_note'] = '1';
        $data['currency_code'] = $invoice['currency'];
        $data['rm'] = '2';
        $data['return'] = $this->config['thankyou_url'];
        $data['cancel_return'] = $this->config['cancel_url'];
        $data['notify_url'] = $this->config['notify_url'];
        $data['business'] = $this->config['email'];
        $data['cmd'] = '_xclick';
        $data['amount'] = $this->moneyFormat($invoice['subtotal'], $invoice['currency']);
        $data['tax'] = $this->moneyFormat($invoice['tax'], $invoice['currency']);
        $data['bn'] = 'FOSSBilling_SP';
        $data['charset'] = 'utf-8';

        return $data;
    }
}
