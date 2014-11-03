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

class Payment_Adapter_BluePay
{
    private $config = array();
    
    public function __construct($config)
    {
        $this->config = $config;
    }

    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'     =>  true,
            'description'     =>  '
<div style="float:right">
    <a href="http://www.bluepay.com/get-started?utm_source=boxbilling&utm_medium=cpc&utm_campaign=boxbilling" target="_blank"><img width="290" height="60" src="https://www.bluepay.com/sites/default/files/merchant_seal_03.gif" alt="Online Payment Gateway" border="0" /></a>
</div>
<br/>
<a href="http://www.bluepay.com/get-started?utm_source=boxbilling&utm_medium=cpc&utm_campaign=boxbilling" target="_blank">Create BluePay account</a>
<br/>
To find your ACCOUNT ID and SECRET KEY:
<ul>
    <li>Log into the Bluepay 2.0 gateway.</li>
    <li>From the Administration menu, choose Accounts, then List. </li>
    <li>On the Account List, under Options on the right-hand side, choose the first icon to view the account.  It looks like a pair of eyes.</li>
    <li>On the Account Admin page, you will find the ACCOUNT ID is the second item in the right-hand column and the SECRET KEY is about halfway down the page, near a large red warning.</li>
</ul>
',
            'form'  => array(
                'account_id' => array('text', array(
                    'label' => 'Account ID',
                    ),
                 ),
                'secret' => array('text', array(
                    'label' => 'Secret key',
                    ),
                 ),
            ),
        );
    }
    
    /**
     * 
     * @param type $api_admin
     * @param type $invoice_id
     * @param type $subscription
     * @see https://secure.assurebuy.com/BluePay/BluePay_bp10emu/BluePay%201-0%20Emulator.txt
     * @return string
     */
    public function getHtml($api_admin, $invoice_id, $subscription)
    {
        $invoice = $api_admin->invoice_get(array('id'=>$invoice_id));
        $buyer = $invoice['buyer'];
        
        $p = array(
            ':id'=>sprintf('%05s', $invoice['nr']), 
            ':serie'=>$invoice['serie'], 
            ':title'=>$invoice['lines'][0]['title']
        );
        $title = __('Payment for invoice :serie:id [:title]', $p);
        
        $mode           = ($this->config['test_mode']) ? "TEST" : "LIVE";
        $type           = 'SALE';
        $amount         = $invoice['total'];
        $account_id     = $this->config['account_id'];
        $secret_key     = $this->config['secret'];
        $tps            = md5($secret_key.$account_id.$amount.$mode);
        
        $message = '';
        if(isset($_GET['Result'])) {
            $format = '<h2 style="text-align: center; color:red;">%s</h2>';
            switch ($_GET['Result']) {
                case 'APPROVED':
                    $message = sprintf($format, $_GET['MESSAGE']);
                    break;
                
                case 'ERROR':
                case 'DECLINED':
                case 'MISSING':
                default:
                    $message = sprintf($format, $_GET['MESSAGE']);
                    break;
            }
        }
        
        $html = '
            <form action="https://secure.bluepay.com/interfaces/bp10emu" method=POST>
                <input type=hidden name=MERCHANT value="'.$account_id.'">
                <input type=hidden name=TRANSACTION_TYPE value="'.$type.'">
                <input type=hidden name=TAMPER_PROOF_SEAL value="'.$tps.'">
                <input type=hidden name=TPS_DEF value="MERCHANT AMOUNT MODE">
                <input type=hidden name=AMOUNT value="'.$amount.'">
                <input type=hidden name=APPROVED_URL value="'.$this->config['redirect_url'].'">
                <input type=hidden name=DECLINED_URL value="'.$this->config['cancel_url'].'">
                <input type=hidden name=MISSING_URL value="'.$this->config['return_url'].'">
                <input type=hidden name=COMMENT value="'.$title.'">
                <input type=hidden name=MODE         value="'.$mode.'">
                <input type=hidden name=AUTOCAP      value="0">
                <input type=hidden name=REBILLING    value="">
                <input type=hidden name=REB_CYCLES   value="">
                <input type=hidden name=REB_AMOUNT   value="">
                <input type=hidden name=REB_EXPR     value="">
                <input type=hidden name=REB_FIRST_DATE value="">
                <input type=hidden name=ORDER_ID value="'.$invoice['id'].'">
                <input type=hidden name=CUSTOM_ID  value="'.$invoice['id'].'">
                <input type=hidden name=INVOICE_ID  value="'.$invoice['id'].'">
    
                '.$message.'
                
                <table>
                    <tr>
                        <td>'.__('Card number').'</td>
                        <td>
                            <input type=text name=CC_NUM value="">
                        </td>
                    </tr>
                    <tr>
                        <td>'.__('CVV2').'</td>
                        <td>
                            <input type=text name=CVCCVV2 value="">
                        </td>
                    </tr>
                    <tr>
                        <td>'.__('Expiration Date').'</td>
                        <td>
                            <input type=text name=CC_EXPIRES value="" placeholder="MM/YY">
                        </td>
                    </tr>
                    <tr>
                        <td>'.__('Name').'</td>
                        <td>
                            <input type=text name=NAME value="'.$buyer['first_name'].' '. $buyer['last_name'].'">
                        </td>
                    </tr>
                    <tr>
                        <td>'.__('Address').'</td>
                        <td>
                            <input type=text name=Addr1 value="'.$buyer['address'].'">
                        </td>
                    </tr>
                    <tr>
                        <td>'.__('City').'</td>
                        <td>
                            <input type=text name=CITY value="'.$buyer['city'].'">
                        </td>
                    </tr>
                    <tr>
                        <td>'.__('State').'</td>
                        <td>
                            <input type=text name=STATE value="'.$buyer['state'].'">
                        </td>
                    </tr>
                    <tr>
                        <td>'.__('Zipcode').'</td>
                        <td>
                            <input type=text name=ZIPCODE value="'.$buyer['zip'].'">
                        </td>
                    </tr>
                    <tr>
                        <td>'.__('Phone').'</td>
                        <td>
                            <input type=text name=PHONE value="'.$buyer['phone'].'">
                        </td>
                    </tr>
                    <tr>
                        <td>'.__('Email').'</td>
                        <td>
                            <input type=text name=EMAIL value="'.$buyer['email'].'">
                        </td>
                    </tr>
                    
                    <tfoot>
                    <tr>
                        <td colspan=2>
                            <input type=SUBMIT value="'.__('Pay now').'" name=SUBMIT class="bb-button bb-button-submit bb-button-big">
                        </td>
                    </tr>
                    </tfoot>
                </table>
            </form>
        ';
        
        return $html;
    }

    public function processTransaction($api_admin, $id, $data, $gateway_id)
    {
        if(APPLICATION_ENV != 'testing' && !$this->_isIpnValid($data)) {
            throw new Exception('IPN is not valid');
        }
        
        $ipn = $data['post'];
        
        $tx = $api_admin->invoice_transaction_get(array('id'=>$id));
        
        
        $d = array(
            'id'        => $id, 
            'error'     => '',
            'error_code'=> '',
            'status'    => 'processed',
            'updated_at'=> date('c'),
        );
        $api_admin->invoice_transaction_update($d);
    }
}


