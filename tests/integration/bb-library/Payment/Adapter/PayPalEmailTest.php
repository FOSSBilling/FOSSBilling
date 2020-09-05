<?php
/**
 * @group Core
 */
class Payment_Adapter_PayPalEmailTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'gateway_PayPal.xml';
    
    public function testGateway()
    {
        $config = array();
        $config['test_mode'] = true;
        $config['email']      = 'example@gmail.com';
        $config['return_url'] = 'http://www.google.com?q=return';
        $config['cancel_url'] = 'http://www.google.com?q=cancel';
        $config['notify_url'] = 'http://www.google.com?q=notify';
        
        $adapter = new Payment_Adapter_PayPalEmail($config);
        $adapter->setDi($this->di);
        $adapter->getConfig();
        $adapter->getHtml($this->api_admin, 1, false);
        $adapter->getHtml($this->api_admin, 2, true);
        
        $subscr_payment = json_decode('{"get":{"bb_gateway_id":"1","bb_invoice_id":"1"},"post":{"transaction_subject":"Subscription for BoxBilling Pro License","payment_date":"11:24:34 Mar 08, 2012 PST","txn_type":"subscr_payment","subscr_id":"S-9X268688JL8514304","last_name":"Doe","residence_country":"GB","item_name":"Subscription for BoxBilling Pro License","payment_gross":"5.95","mc_currency":"USD","business":"clients@boxbilling.com","payment_type":"instant","protection_eligibility":"Ineligible","verify_sign":"A2QxLKJxNBOK3utJMiCwOkOiLG3tAo4.KtJCsCemU7s98tgtW37-hOOx","payer_status":"verified","payer_email":"john.doe@gmail.com","txn_id":"6SR32195AP993094V","receiver_email":"clients@boxbilling.com","first_name":"John","payer_id":"9W3LMGTFS5RTY","receiver_id":"79PMKLAHPL5YH","item_number":"BOX05697","payment_status":"Completed","payment_fee":"0.47","mc_fee":"0.47","mc_gross":"5.95","charset":"windows-1252","notify_version":"3.4","ipn_track_id":"54b4dbrfvg396"},"http_raw_post_data":"transaction_subject=Subscription+for+BoxBilling+Pro+License&payment_date=11%3A24%3A34+Mar+08%2C+2012+PST&txn_type=subscr_payment&subscr_id=S-9X268688JL8514304&last_name=Doe&residence_country=GB&item_name=Subscription+for+BoxBilling+Pro+License&payment_gross=5.95&mc_currency=USD&business=clients%40boxbilling.com&payment_type=instant&protection_eligibility=Ineligible&verify_sign=A2QxLKJxNBOK3utOLuCwOkOiLG3tAo4.KtJCsCemU7db97vtW37-hOOx&payer_status=verified&payer_email=mitcheyDoe%40gmail.com&txn_id=6SR99524AP993094V&receiver_email=clients%40boxbilling.com&first_name=John&payer_id=9W3MDRHPS5RTY&receiver_id=79PMKLAHYN3HN&item_number=BOX05697&payment_status=Completed&payment_fee=0.47&mc_fee=0.47&mc_gross=5.95&charset=windows-1252&notify_version=3.4&ipn_track_id=54b4dbfade396"}', 1);
        $adapter->processTransaction($this->api_admin, 1, $subscr_payment, 1);
        
        $web_accept = json_decode('{"get":{"bb_gateway_id":"1","bb_invoice_id":"1"},"post":{"transaction_subject":"Payment for invoice BOX05623","payment_date":"08:26:14 Mar 01, 2012 PST","txn_type":"web_accept","last_name":"Doe","residence_country":"US","item_name":"Payment for invoice BOX05623","payment_gross":"5.95","mc_currency":"USD","business":"sales@boxbilling.com","payment_type":"instant","protection_eligibility":"Ineligible","verify_sign":"A.DRDmKsJOHhCaHehJk34EQqLY1rAuZ0ksJDt.45zrn5eO7JxP0.4QP.","payer_status":"verified","tax":"0.00","payer_email":"john.doe@gmail.com","txn_id":"8X786252873429051","quantity":"1","receiver_email":"clients@boxbilling.com","first_name":"John","payer_id":"29NE5SEU5VR2E","receiver_id":"79PMKLAHPL5YH","item_number":"BOX05623","handling_amount":"0.00","payment_status":"Completed","payment_fee":"0.44","mc_fee":"0.44","shipping":"0.00","mc_gross":"5.95","custom":"","charset":"windows-1252","notify_version":"3.4","ipn_track_id":"6fd845df6f247"},"http_raw_post_data":"transaction_subject=Payment+for+invoice+BOX05623&payment_date=08%3A26%3A14+Mar+01%2C+2012+PST&txn_type=web_accept&last_name=Doe&residence_country=LT&item_name=Payment+for+invoice+BOX05623&payment_gross=5.95&mc_currency=USD&business=sales%40boxbilling.com&payment_type=instant&protection_eligibility=Ineligible&verify_sign=A.DRDmKsJOHhCaHehJr9EQqLY1rAuZ0ksJDt.45zrn5eO7JxP0.4QP.&payer_status=verified&tax=0.00&payer_email=fordnox%40gmail.com&txn_id=8X786252873429051&quantity=1&receiver_email=clients%40boxbilling.com&first_name=John&payer_id=29NE5SEU5VR2E&receiver_id=79PMKLAHYN3HN&item_number=BOX05623&handling_amount=0.00&payment_status=Completed&payment_fee=0.44&mc_fee=0.44&shipping=0.00&mc_gross=5.95&custom=&charset=windows-1252&notify_version=3.4&ipn_track_id=6fd802df6f247"}', 1);
        $adapter->processTransaction($this->api_admin, 1, $web_accept, 1);
        
        $refund_ipn = json_decode('{"get":{"bb_gateway_id":"1","bb_invoice_id":"1"},"post":{"transaction_subject":"Subscription for BoxBilling Pro License","payment_date":"17:42:05 Mar 13, 2012 PDT", "txn_type":"subscr_payment","subscr_id":"S-9X268688JL8514304","last_name":"Doe","residence_country":"GB","item_name":"Subscription for BoxBilling Pro License","payment_gross":"-5.95","mc_currency":"USD","business":"clients@boxbilling.com","payment_type":"instant","protection_eligibility":"Ineligible","verify_sign":"ArFZ5DF1pEd4euI2jpvbEwe5Q4BiAB46zG6F4TMlFNMtpDC7L5z6VPsB","payer_email":"mitcheyDoe@gmail.com","txn_id":"33R88456DU653693H","receiver_email":"clients@boxbilling.com","first_name":"John","parent_txn_id":"6SR99524AP993094V","payer_id":"9W3LMGTFS5RTY","receiver_id":"79PMKLAHPL5YH","reason_code":"refund","item_number":"BOX05697","payment_status":"Refunded","payment_fee":"-0.17","mc_fee":"-0.17","mc_gross":"-5.95","charset":"windows-1252","notify_version":"3.4","ipn_track_id":"71f325984a82e"},"http_raw_post_data":"transaction_subject=Subscription+for+BoxBilling+Pro+License&payment_date=17%3A42%3A05+Mar+13%2C+2012+PDT&subscr_id=S-9X268688JL8514304&last_name=Doe&residence_country=GB&item_name=Subscription+for+BoxBilling+Pro+License&payment_gross=-5.95&mc_currency=USD&business=clients%40boxbilling.com&payment_type=instant&protection_eligibility=Ineligible&verify_sign=ArFZ5DF1pEd6dfI2jpvbEwe5Q4BiAB46zG6F4TMlFNMtpDC7L5z6VPsB&payer_email=mitcheyDoe%40gmail.com&txn_id=33R88456DU653693H&receiver_email=clients%40boxbilling.com&first_name=John&parent_txn_id=6SR99524AP993094V&payer_id=9W3MDRHPS5RTY&receiver_id=79PMKLAHYN3HN&reason_code=refund&item_number=BOX05697&payment_status=Refunded&payment_fee=-0.17&mc_fee=-0.17&mc_gross=-5.95&charset=windows-1252&notify_version=3.4&ipn_track_id=71f784354a82e"}', 1);
        $adapter->processTransaction($this->api_admin, 1, $refund_ipn, 1);
    }

    public function testGatewayProductionUrl()
    {
        $config = array();
        $config['email'] = 'example@gmail.com';
        $config['test_mode'] = false;
        $config['return_url'] = 'http://www.google.com?q=return';
        $config['cancel_url'] = 'http://www.google.com?q=cancel';
        $config['notify_url'] = 'http://www.google.com?q=notify';

        $adapter = new Payment_Adapter_PayPalEmail($config);
        $adapter->setDi($this->di);
        $form = $adapter->getHtml($this->api_admin, 1, false);
        $this->assertMatchesRegularExpression('/action="https:\/\/www\.paypal\.com\/cgi-bin\/webscr"/', $form);
    }

    public function testGatewayTestmodeUrl()
    {
        $config = array();
        $config['email'] = 'example@gmail.com';
        $config['test_mode'] = true;
        $config['return_url'] = 'http://www.google.com?q=return';
        $config['cancel_url'] = 'http://www.google.com?q=cancel';
        $config['notify_url'] = 'http://www.google.com?q=notify';
        
        $adapter = new Payment_Adapter_PayPalEmail($config);
        $adapter->setDi($this->di);
        $form = $adapter->getHtml($this->api_admin, 1, false);
        $this->assertMatchesRegularExpression('/action="https:\/\/www\.sandbox\.paypal\.com\/cgi-bin\/webscr"/', $form);
    }
}