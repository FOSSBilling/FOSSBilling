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

namespace Box\Mod\Invoice;

use Box\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    /**
     * @var \Box_Di
     */
    protected $di = null;

    /**
     * @param \Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return \Box_Di
     */
    public function getDi()
    {
        return $this->di;
    }

    public function getSearchQuery($data)
    {
        $sql="SELECT p.*
            FROM invoice p
            LEFT JOIN invoice_item pi ON (p.id = pi.invoice_id)
            LEFT JOIN client cl ON (cl.id = p.client_id)
            WHERE 1 ";

        $params = array();

        $search     = $this->di['array_get']($data, 'search', NULL);
        $order_id   = $this->di['array_get']($data, 'order_id', NULL);
        $id         = $this->di['array_get']($data, 'id', NULL);
        $id_nr      = $this->di['array_get']($data, 'nr', NULL);
        $client_id  = $this->di['array_get']($data, 'client_id', NULL);
        $client     = $this->di['array_get']($data, 'client', NULL);
        $created_at = $this->di['array_get']($data, 'created_at', NULL);
        $date_from  = $this->di['array_get']($data, 'date_from', NULL);
        $date_to    = $this->di['array_get']($data, 'date_to', NULL);
        $paid_at    = $this->di['array_get']($data, 'paid_at', NULL);
        $status     = $this->di['array_get']($data, 'status', NULL);
        $approved   = $this->di['array_get']($data, 'approved', NULL);
        $currency   = $this->di['array_get']($data, 'currency', NULL);
        
        if($order_id) {
            $sql .= ' AND pi.type = :item_type AND pi.rel_id = :order_id';
            $params['item_type'] = \Model_InvoiceItem::TYPE_ORDER;
            $params['order_id'] = $order_id;
        }
        
        if($id) {
            $sql .= ' AND p.id = :id';
            $params['id'] = $id;
        }
        
        if($id_nr) {
            $sql .= ' AND (p.id = :id_nr OR p.nr = :id_nr)';
            $params['id_nr'] = $id_nr;
        }
        
        if($approved) {
            $sql .= ' AND p.approved = :approved';
            $params['approved'] = $approved;
        }
        
        if($status) {
            $sql .= ' AND p.status = :status';
            $params['status'] = $status;
        }
        
        if($currency) {
            $sql .= ' AND p.currency = :currency';
            $params['currency'] = $currency;
        }
        
        if(NULL !== $client_id) {
            $sql .= ' AND p.client_id = :client_id';
            $params['client_id'] = $client_id;
        }
        
        if(NULL !== $client) {
            $sql .= ' AND (cl.first_name LIKE :client_search OR cl.last_name LIKE :client_search OR cl.id = :client OR cl.email = :client)';
            $params['client_search'] = $client.'%';
            $params['client'] = $client;
        }
        
        if($created_at) {
            $sql .= " AND DATE_FORMAT(p.created_at, '%Y-%m-%d') = :created_at";
            $params['created_at'] = date('Y-m-d', strtotime($created_at));
        }
        
        if($date_from) {
            $sql .= " AND UNIX_TIMESTAMP(p.created_at) >= :date_from";
            $params['date_from'] = strtotime($date_from);
        }
        
        if($date_to) {
            $sql .= " AND UNIX_TIMESTAMP(p.created_at) <= :date_to";
            $params['date_to'] = strtotime($date_to);
        }
        
        if($paid_at) {
            $sql .= " AND DATE_FORMAT(p.paid_at, '%Y-%m-%d') = :paid_at";
            $params['paid_at'] = date('Y-m-d', strtotime($paid_at));
        }

        if($search) {
            $sql .= " AND (p.id = :int OR p.nr LIKE :search_like OR p.id LIKE :search OR pi.title LIKE :search_like)";
            $params['int'] = (int)preg_replace("/[^0-9]/","",$search);
            $params['search_like'] = '%'.$search.'%';
            $params['search'] = $search;
        }
        
        $sql .= ' GROUP BY p.id ORDER BY p.id DESC';
        return array($sql, $params);
    }

    public function toApiArray(\Model_Invoice $invoice, $deep = true, $identity = null)
    {
        $row = $this->di['db']->toArray($invoice);
        
        $items = $this->di['db']->find('InvoiceItem', 'invoice_id = :iid', array('iid'=>$row['id']));
        
        $lines = array();
        $total = $tax_total = 0;
        $invoiceItemService = $this->di['mod_service']('Invoice', 'InvoiceItem');
        foreach($items as $item) {
            $order_id = ($item->type == \Model_InvoiceItem::TYPE_ORDER) ? $item->rel_id : null;
                        
            $line_total = $item->price * $item->quantity; 
            $total +=  $line_total;
            $line_tax = $invoiceItemService->getTax($item) * $item->quantity;
            $tax_total += $line_tax;
            $line = array(
                'id'        =>  $item->id,
                'title'     =>  $item->title,
                'period'    =>  $item->period,
                'quantity'  =>  $item->quantity,
                'unit'      =>  $item->unit,
                'price'     =>  $item->price,
                'tax'       =>  $line_tax,
                'taxed'     =>  $item->taxed,
                'charged'   =>  $item->charged,
                'total'     =>  $line_total,
                'order_id'  =>  $order_id,
                'type'      =>  $item->type,
                'rel_id'    =>  $item->rel_id,
                'task'      =>  $item->task,
                'status'    =>  $item->status,
            );
            $lines[] = $line;
        }
        $tax = $tax_total;

        $result = array();
        $result['id'] = $row['id'];
        $result['serie'] = $row['serie'];
        $result['nr'] = $row['nr'];

        $nr = (is_numeric($result['nr'])) ? $result['nr'] : $result['id'];
        $result['serie_nr'] = $result['serie'] . sprintf('%05s', $nr);

        $result['hash'] = $row['hash'];
        $result['gateway_id'] = $row['gateway_id'];
        $result['taxname'] = $row['taxname'];
        $result['taxrate'] = $row['taxrate'];
        $result['currency'] = $row['currency'];
        $result['currency_rate'] = $row['currency_rate'];
        $result['tax'] = $tax;
        $result['subtotal'] = $total;
        $result['total'] = $total + $tax;
        $result['status'] = $row['status'];
        $result['notes'] = $row['notes'];
        $result['text_1'] = $row['text_1'];
        $result['text_2'] = $row['text_2'];
        $result['due_at'] = $row['due_at'];
        $result['paid_at'] = $row['paid_at'];
        $result['created_at'] = $row['created_at'];
        $result['updated_at'] = $row['updated_at'];
        $result['lines']    = $lines;
        
        $result['buyer'] = array(
            'first_name'=> $row['buyer_first_name'],
            'last_name' => $row['buyer_last_name'],
            'company'   => $row['buyer_company'],
            'company_vat' => $row['buyer_company_vat'],
            'company_number' => $row['buyer_company_number'],
            'address'   => $row['buyer_address'],
            'city'      => $row['buyer_city'],
            'state'     => $row['buyer_state'],
            'country'   => $row['buyer_country'],
            'phone'     => $row['buyer_phone'],
            'phone_cc'  => $row['buyer_phone_cc'],
            'email'     => $row['buyer_email'],
            'zip'       => $row['buyer_zip'],
        );

        $systemService = $this->di['mod_service']('system');
        $c = $systemService->getCompany();
        $result['seller'] = array(
            'company'   => !empty($row['seller_company']) ? $row['seller_company'] : $c['name'],
            'company_vat'=> $row['seller_company_vat'],
            'company_number'=> $row['seller_company_number'],
            'address'   => !empty($row['seller_address']) ? $row['seller_address'] : trim($c['address_1'] .' '. $c['address_2'] .' '. $c['address_2']),
            'phone'     => !empty($row['seller_phone']) ? $row['seller_phone'] : $c['tel'],
            'email'     => !empty($row['seller_email']) ? $row['seller_email'] : $c['email'],
            'account_number' => !empty($c['account_number']) ? $c['account_number'] : null,
        );

            /**
             * Removed if($identity instanceof \Model_Admin) {}
             * Generates error when this function is called by cron
             */
            $client = $this->di['db']->load('Client', $row['client_id']);
            $clientService = $this->di['mod_service']('client');
            if($client instanceof \Model_Client) {
                $result['client'] = $clientService->toApiArray($client);
            } else {
                $result['client'] = null;
            }
            $result['reminded_at'] = $row['reminded_at'];
            $result['approved'] = (bool)$row['approved'];
            $result['income'] = $row['base_income'] - $row['base_refund'];
            $result['refund'] = $row['refund'];
            $result['credit'] = $row['credit'];

        $subscriptionService = $this->di['mod_service']('Invoice', 'Subscription');
        $result['subscribable'] = $subscriptionService->isSubscribable($row['id']);
        if($deep && $result['subscribable']) {
            $ip = $this->di['db']->getCell('SELECT period FROM invoice_item WHERE invoice_id = :id', array('id'=>$row['id']));
            $period = $this->di['period']($ip);
            $result['subscription'] = array(
                'unit'      =>  $period->getUnit(),
                'cycle'     =>  $period->getQty(),
                'period'    =>  $ip,
            );
        }
        
        return $result;
    }
    
    public static function onAfterAdminInvoicePaymentReceived(\Box_Event $event)
    {
        $params = $event->getParameters();
        $di = $event->getDi();
        $service = $di['mod_service']('invoice');

        try {
            $invoiceModel = $di['db']->load('Invoice', $params['id']);
            $invoice = $service->toApiArray($invoiceModel, array('id' =>$params['id']));
            if($invoice['total'] > 0) {
                $email = array();
                $email['to_client'] = $invoiceModel->client_id;
                $email['code']      = 'mod_invoice_paid';
                $email['invoice']   = $invoice;
                $emailService = $di['mod_service']('email');
                $emailService->sendTemplate($email);
            }
        } catch(\Exception $exc) {
            error_log($exc->getMessage());
        }
        
        return true;
    }
    
    public static function onAfterAdminInvoiceApprove(\Box_Event $event)
    {
        $params = $event->getParameters();
        $di = $event->getDi();
        $service = $di['mod_service']('invoice');

        try {
            $invoiceModel = $di['db']->load('Invoice', $params['id']);
            $invoice = $service->toApiArray($invoiceModel, array('id' =>$params['id']));
            $email = array();
            $email['to_client'] = $invoiceModel->client_id;
            $email['code']      = 'mod_invoice_created';
            $email['invoice']   = $invoice;
            $emailService = $di['mod_service']('Email');
            $emailService->sendTemplate($email);
        } catch(\Exception $exc) {
            error_log($exc->getMessage());
        }
        
        return true;
    }

    public static function onAfterAdminInvoiceReminderSent(\Box_Event $event)
    {
        $params = $event->getParameters();
        $di = $event->getDi();
        $service = $di['mod_service']('invoice');

        try {
            $invoiceModel = $di['db']->load('Invoice', $params['id']);
            $invoice = $service->toApiArray($invoiceModel, array('id' =>$params['id']));
            $email = array();
            $email['to_client'] = $invoiceModel->client_id;
            $email['code']      = 'mod_invoice_payment_reminder';
            $email['invoice']   = $invoice;
            $emailService = $di['mod_service']('Email');
            $emailService->sendTemplate($email);
        } catch(\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public static function onAfterAdminCronRun(\Box_Event $event)
    {
        $di = $event->getDi();
        $systemService = $di['mod_service']('System');
        $remove_after_days = $systemService->getParamValue('remove_after_days');
        if(isset($remove_after_days) && $remove_after_days) {
            //removing old invoices
            $days = (int)$remove_after_days;
            $sql="DELETE FROM invoice WHERE status = 'unpaid' AND DATEDIFF(NOW(), due_at) > $days";
            $di['db']->exec($sql);
        }
    }

    public static function onEventAfterInvoiceIsDue(\Box_Event $event)
    {
        $params = $event->getParameters();
        $di = $event->getDi();
        $service = $di['mod_service']('invoice');

        //send reminder once a day when 5 days has passed
        if($params['days_passed'] != 5) {
            return;
        }
        
        try {
            $invoiceModel = $di['db']->load('Invoice', $params['id']);
            $invoice = $service->toApiArray($invoiceModel, array('id' =>$params['id']));
            $email = array();
            $email['to_client'] = $invoice['client']['id'];
            $email['code']      = 'mod_invoice_due_after';
            $email['days_passed']= $params['days_passed'];
            $email['invoice']   = $invoice;

            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch(\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public function markAsPaid(\Model_Invoice $invoice, $charge = TRUE, $execute = false)
    {
        if($invoice->status == \Model_Invoice::STATUS_PAID) {
            return TRUE;
        }

        $invoiceItems = $this->di['db']->find('InvoiceItem', 'invoice_id = ?', array($invoice->id));
        $invoiceItemService = $this->di['mod_service']('Invoice', 'InvoiceItem');
        foreach($invoiceItems as $item) {
            $invoiceItemService->markAsPaid($item, $charge);
        }

        $systemService = $this->di['mod_service']('system');
        $ctable = $this->di['mod_service']('Currency');

        $invoice->serie         = $systemService->getParamValue('invoice_series_paid');
        $invoice->nr            = $this->getNextInvoiceNumber($invoice);
        $invoice->approved      = TRUE;
        $invoice->currency_rate = $ctable->getRateByCode($invoice->currency);
        $invoice->status        = \Model_Invoice::STATUS_PAID;
        $invoice->paid_at       = date('Y-m-d H:i:s');
        $invoice->updated_at    = date('Y-m-d H:i:s');
        $this->di['db']->store($invoice);

        $this->countIncome($invoice);

        $this->di['events_manager']->fire(array('event'=>'onAfterAdminInvoicePaymentReceived', 'params'=>array('id'=>$invoice->id)));

        if($execute) {

            foreach($invoiceItems as $item) {
                try {
                    $invoiceItemService->executeTask($item);
                } catch(\Exception $e) {
                    error_log($e);
                }
            }
        }

        $this->di['logger']->info('Marked invoice "%s" as paid', $invoice->id);
        return true;
    }

    public function getNextInvoiceNumber(\Model_Invoice $model)
    {
        $p = 'invoice_starting_number';
        $systemService = $this->di['mod_service']('system');
        $next_nr = $systemService->getParamValue($p);
        if(empty($next_nr)) {
            $next_nr = $model->id;

            //get last invoice number

            $r = $this->di['db']->findOne('Invoice', 'nr is not null order by id desc');
            if($r instanceof \Model_Invoice && is_numeric($r->nr)) {
                $next_nr = $r->nr + 1;
            }
        }
        $systemService->setParamValue($p, $next_nr+1);
        return $next_nr;
    }

    public function countIncome(\Model_Invoice $invoice)
    {
        $table = $this->di['mod_service']('Currency');

        $invoice->base_income = $table->toBaseCurrency($invoice->currency, $this->getTotal($invoice));
        $invoice->base_refund = $table->toBaseCurrency($invoice->currency, $invoice->refund);
        $this->di['db']->store($invoice);
    }

    public function prepareInvoice(\Model_Client $client, array $data)
    {
        if(!$client->currency) {
            $currencyService = $this->di['mod_service']('Currency');
            $currency = $currencyService->getDefault();
            $client->currency = $currency->code;
            $this->di['db']->store($client);
            error_log(sprintf('Client #%s currency was not defined. Set default currency %s', $client->id, $currency->code));
        }

        $model = $this->di['db']->dispense('Invoice');
        $model->client_id = $client->id;
        $model->status = \Model_Invoice::STATUS_UNPAID;
        $model->currency = $client->currency;
        $model->approved = 0;

        $model->gateway_id = $this->di['array_get']($data, 'gateway_id', $model->gateway_id);
        $model->text_1 = $this->di['array_get']($data, 'text_1', $model->text_1);
        $model->text_2 = $this->di['array_get']($data, 'text_2', $model->text_2);
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $invoiceId = $this->di['db']->store($model);

        $this->setInvoiceDefaults($model);

        if(isset($data['items']) && is_array($data['items'])) {
            $invoiceItemService = $this->di['mod_service']('Invoice', 'InvoiceItem');
            foreach($data['items'] as $d) {
                $invoiceItemService->addNew($model, $d);
            }
        }

        $this->di['logger']->info('Prepared new invoice "%s"', $invoiceId);

        if(isset($data['approve']) && $data['approve']) {
            try {
                $this->approveInvoice($model, array('id'=>$invoiceId));
                $this->di['logger']->info('Approved invoice %s instantly', $invoiceId);
            } catch(\Exception $e) {
                error_log($e->getMessage());
            }
        }

        return $model;
    }

    public function setInvoiceDefaults(\Model_Invoice $model)
    {
        $clientService = $this->di['mod_service']('Client');
        $systemService = $this->di['mod_service']('system');
        $client = $this->di['db']->load('Client', $model->client_id);
        $seller = $systemService->getCompany();

        $buyer = $clientService->toApiArray($client);

        $model->seller_company  = $seller['name'];
        $model->seller_company_vat  = $seller['vat_number'];
        $model->seller_company_number  = $seller['number'];
        $model->seller_address  = trim($seller['address_1'] .' '. $seller['address_2'] .' '. $seller['address_3']);
        $model->seller_phone    = $seller['tel'];
        $model->seller_email    = $seller['email'];

        $model->buyer_first_name        = $buyer['first_name'];
        $model->buyer_last_name         = $buyer['last_name'];
        $model->buyer_company           = $buyer['company'];
        $model->buyer_company_vat       = $buyer['company_vat'];
        $model->buyer_company_number    = $buyer['company_number'];
        $model->buyer_address   = $buyer['address_1'] .' '. $buyer['address_2'];
        $model->buyer_city      = $buyer['city'];
        $model->buyer_state     = $buyer['state'];
        $model->buyer_country   = $buyer['country'];
        $model->buyer_phone     = $buyer['phone_cc'] .' '.$buyer['phone'];
        $model->buyer_email     = $buyer['email'];
        $model->buyer_zip       = $buyer['postcode'];


        $invoice_due_days = $systemService->getParamValue('invoice_due_days');
        if (!is_numeric($invoice_due_days)) {
            $invoice_due_days = 1;
        }
        $due_time = strtotime('+' . $invoice_due_days . ' day');
        $model->due_at = date('Y-m-d H:i:s', $due_time);

        $model->serie = $systemService->getParamValue('invoice_series');
        $model->nr = $model->id;
        $model->hash = sha1(uniqid());

        $taxtitle = '';
        $taxService = $this->di['mod_service']('Invoice', 'Tax');
        $tax = $taxService->getTaxRateForClient($client, $taxtitle);
        $model->taxname = $taxtitle;
        $model->taxrate = $tax;

        $this->di['db']->store($model);
    }

    public function approveInvoice(\Model_Invoice $invoice, array $data)
    {
        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminInvoiceApprove', 'params'=>array('id'=>$invoice->id)));

        $invoice->approved = 1;
        $invoice->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($invoice);

        $this->di['events_manager']->fire(array('event'=>'onAfterAdminInvoiceApprove', 'params'=>array('id'=>$invoice->id)));

        if(isset($data['use_credits']) && $data['use_credits']) {
            $this->tryPayWithCredits($invoice);
        }

        $this->di['logger']->info('Approved invoice "%s"', $invoice->id);
        return true;
    }

    public function tryPayWithCredits(\Model_Invoice $invoice)
    {
        if(!$invoice->approved) {
            return ;
        }

        if ($this->isInvoiceTypeDeposit($invoice)){
            $this->markAsPaid($invoice, false);
        }

        $client = $this->di['db']->load('Client', $invoice->client_id);
        $cbrepo = $this->di['mod_service']('Client', 'Balance');
        $balance = $cbrepo->getClientBalance($client);
        $required = $this->getTotalWithTax($invoice);
        $epsilon = 0.05;

        if(abs($balance-$required) < $epsilon) {
            if($this->di['config']['debug']) error_log(sprintf('Setting invoice %s as paid with credits', $invoice->id));
            $this->markAsPaid($invoice);
            return true;
        }

        if($balance-$required > 0.00001) {
            if($this->di['config']['debug']) error_log(sprintf('Setting invoice %s as paid with credits', $invoice->id));
            $this->markAsPaid($invoice);
            return true;
        }
        if($this->di['config']['debug']) error_log(sprintf('Invoice %s could not be paid with credits. Money in balance %s Required: %s', $invoice->id, $balance, $required));
    }

    public function getTotalWithTax(\Model_Invoice $invoice)
    {
        $total = $this->getTotal($invoice) + $this->getTax($invoice);
        return (float)$total;
    }

    public function getTax(\Model_Invoice $invoice)
    {
        if($invoice->taxrate <= 0) {
            return 0;
        }

        $iiService = $this->di['mod_service']('Invoice', 'InvoiceItem');
        $items = $this->di['db']->find('InvoiceItem', 'invoice_id = ? ', array($invoice->id));
        $tax = 0;
        foreach($items as $item) {
            $tax += $iiService->getTax($item) * $item->quantity;
        }
        return $tax;
    }

    public function getTotal(\Model_Invoice $invoice)
    {
        $total = 0;
        $invoiceItems = $this->di['db']->find('InvoiceItem', 'invoice_id = ?', array($invoice->id));
        $invoiceItemService = $this->di['mod_service']('Invoice', 'InvoiceItem');
        foreach($invoiceItems as $item) {
            $total += $invoiceItemService->getTotal($item);
        }
        return (float)$total;
    }

    public function refundInvoice(\Model_Invoice $invoice, $note = null)
    {
        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminInvoiceRefund', 'params'=>array('id'=>$invoice->id)));

        $systemService = $this->di['mod_service']('system');
        $logic = $systemService->getParamValue('invoice_refund_logic', 'manual');
        $result = null;

        switch ($logic) {

            case 'credit_note':
            case 'negative_invoice':

                $total = $this->getTotalWithTax($invoice);
                if($total <= 0) {
                    throw new \Box_Exception('Can not refund invoice with negative amount');
                }

                $new = $this->di['db']->dispense('Invoice');
                $new->client_id = $invoice->client_id;
                $new->hash = md5(uniqid());
                $new->status = \Model_Invoice::STATUS_REFUNDED;
                $new->currency = $invoice->currency;
                $new->approved = true;
                $new->taxname = $invoice->taxname;
                $new->taxrate = $invoice->taxrate;

                $new->seller_company  = $invoice->seller_company;
                $new->seller_address  = $invoice->seller_address;
                $new->seller_phone    = $invoice->seller_phone;
                $new->seller_email    = $invoice->seller_email;

                $new->buyer_first_name= $invoice->buyer_first_name;
                $new->buyer_last_name = $invoice->buyer_last_name;
                $new->buyer_company   = $invoice->buyer_company;
                $new->buyer_address   = $invoice->buyer_address;
                $new->buyer_city      = $invoice->buyer_city;
                $new->buyer_state     = $invoice->buyer_state;
                $new->buyer_country   = $invoice->buyer_country;
                $new->buyer_phone     = $invoice->buyer_phone;
                $new->buyer_email     = $invoice->buyer_email;
                $new->buyer_zip       = $invoice->buyer_zip;

                $new->paid_at    = date('Y-m-d H:i:s');
                $new->created_at = date('Y-m-d H:i:s');
                $new->updated_at = date('Y-m-d H:i:s');
                $this->di['db']->store($new);

            $invoiceItems = $this->di['db']->find('InvoiceItem', 'invoice_id = ?', array($invoice->id));
                foreach($invoiceItems as $item) {
                    $pi = $this->di['db']->dispense('InvoiceItem');
                    $pi->invoice_id     = $new->id;
                    $pi->type           = $item->type;
                    $pi->rel_id         = $item->rel_id;
                    $pi->task           = $item->task;
                    $pi->status         = \Model_InvoiceItem::STATUS_EXECUTED; // ark refund invoce as executed
                    $pi->title          = $item->title;
                    $pi->period         = $item->period;
                    $pi->quantity       = $item->quantity;
                    $pi->unit           = $item->unit;
                    $pi->charged        = 1;
                    $pi->price          = -$item->price;
                    $pi->taxed          = $item->taxed;
                    $pi->created_at     = date('Y-m-d H:i:s');
                    $pi->updated_at     = date('Y-m-d H:i:s');
                    $this->di['db']->store($pi);
                }

                $this->countIncome($new);

                $this->addNote($invoice, sprintf('Refund invoice #%s generated', $new->id));
                $this->addNote($new, sprintf('Refund for #%s invoice', $invoice->id));
                if(!empty($note)) {
                    $this->addNote($new, $note);
                }

                if($logic == 'negative_invoice') {
                    $new->serie = $systemService->getParamValue('invoice_series_paid');
                    $new->nr = $this->getNextInvoiceNumber($new);
                    $this->di['db']->store($new);
                }

                if($logic == 'credit_note') {
                    $next_nr = $systemService->getParamValue('invoice_cn_starting_number', 1);
                    $new->serie = $systemService->getParamValue('invoice_cn_series', 'CN-');
                    $new->nr = $next_nr;
                    $this->di['db']->store($new);

                    //update next credit note starting number
                    $systemService->setParamValue('invoice_cn_starting_number', ++$next_nr, true);
                }
                $result = (int)$new->id;
                break;

            //@todo undocumented
            //@deprecated
            case 'same_invoice':
                $amount = $this->getTotalWithTax($invoice);
                $invoice->refund = empty($amount) ? null : $amount;
                $invoice->updated_at = date('Y-m-d H:i:s');
                $this->di['db']->store($invoice);

                if(!empty($note)) {
                    $this->addNote($invoice, $note);
                }

                // mark invoice as refunded if refund amount is equal or greater
                // than refund amount
                $total = $this->getTotalWithTax($invoice);
                if($invoice->refund >= $total) {
                    $invoice->status = \Model_Invoice::STATUS_REFUNDED;
                    $this->di['db']->store($invoice);
                }

                $this->countIncome($invoice);
                break;

            case 'manual':
                if($this->di['config']['debug']) error_log('Refunds are managed manually. No actions performed');
                break;
            default:
                break;
        }

        $this->di['events_manager']->fire(array('event'=>'onAfterAdminInvoiceRefund', 'params'=>array('id'=>$invoice->id)));

        $this->di['logger']->info('Refunded invoice #%s', $invoice->id);
        return $result;
    }

    public function updateInvoice(\Model_Invoice $model, array $data)
    {

        $invoiceItemService = $this->di['mod_service']('Invoice', 'InvoiceItem');

        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminInvoiceUpdate', 'params'=>$data));

        $model->gateway_id            = $this->di['array_get']($data, 'gateway_id', $model->gateway_id);
        $model->text_1                = $this->di['array_get']($data, 'text_1', $model->text_1);
        $model->text_2                = $this->di['array_get']($data, 'text_2', $model->text_2);
        $model->seller_company        = $this->di['array_get']($data, 'seller_company', $model->seller_company);
        $model->seller_company_vat    = $this->di['array_get']($data, 'seller_company_vat', $model->seller_company_vat);
        $model->seller_company_number = $this->di['array_get']($data, 'seller_company_number', $model->seller_company_number);
        $model->seller_address        = $this->di['array_get']($data, 'seller_address', $model->seller_address);
        $model->seller_phone          = $this->di['array_get']($data, 'seller_phone', $model->seller_phone);
        $model->seller_email          = $this->di['array_get']($data, 'seller_email', $model->seller_email);
        $model->buyer_first_name      = $this->di['array_get']($data, 'buyer_first_name', $model->buyer_first_name);
        $model->buyer_last_name       = $this->di['array_get']($data, 'buyer_last_name', $model->buyer_last_name);
        $model->buyer_company         = $this->di['array_get']($data, 'buyer_company', $model->buyer_company);
        $model->buyer_company_vat     = $this->di['array_get']($data, 'buyer_company_vat', $model->buyer_company_vat);
        $model->buyer_company_number  = $this->di['array_get']($data, 'buyer_company_number', $model->buyer_company_number);
        $model->buyer_address         = $this->di['array_get']($data, 'buyer_address', $model->buyer_address);
        $model->buyer_city            = $this->di['array_get']($data, 'buyer_city', $model->buyer_city);
        $model->buyer_state           = $this->di['array_get']($data, 'buyer_state', $model->buyer_state);
        $model->buyer_country         = $this->di['array_get']($data, 'buyer_country', $model->buyer_country);
        $model->buyer_zip             = $this->di['array_get']($data, 'buyer_zip', $model->buyer_zip);
        $model->buyer_phone           = $this->di['array_get']($data, 'buyer_phone', $model->buyer_phone);
        $model->buyer_email           = $this->di['array_get']($data, 'buyer_email', $model->buyer_email);

        $paid_at = $this->di['array_get']($data, 'paid_at', $model->paid_at);
        if(empty($paid_at)) {
                $model->paid_at = null;
        } else {
            $model->paid_at = date('Y-m-d H:i:s', strtotime($paid_at));
        }

        $due_at = $this->di['array_get']($data, 'due_at', $model->due_at);
        if(empty($due_at)) {
            $model->due_at = null;
        } else {
            $model->due_at = date('Y-m-d H:i:s', strtotime($due_at));
        }

        $model->serie    = $this->di['array_get']($data, 'serie', $model->serie);
        $model->nr       = $this->di['array_get']($data, 'nr', $model->nr);
        $model->status   = $this->di['array_get']($data, 'status', $model->status);
        $model->taxrate  = $this->di['array_get']($data, 'taxrate', $model->taxrate);
        $model->taxname  = $this->di['array_get']($data, 'taxname', $model->taxname);
        $model->approved = (int)$this->di['array_get']($data, 'approved', $model->approved);
        $model->notes    = $this->di['array_get']($data, 'notes', $model->notes);

        $created_at = $this->di['array_get']($data, 'created_at', '');
        if(!empty($created_at)) {
            $model->created_at = date('Y-m-d H:i:s', strtotime($created_at));
        }

        $ni = $this->di['array_get']($data, 'new_item', array());
        if(isset($ni['title']) && !empty($ni['title'])) {
            $invoiceItemService->addNew($model, $ni);
        }

        $items = $this->di['array_get']($data, 'items', array());
        foreach($items as $id=>$d) {
            $item = $this->di['db']->load('InvoiceItem', $id);
            if($item instanceof \Model_InvoiceItem) {
                $invoiceItemService->update($item, $d);
            }
        }

        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['events_manager']->fire(array('event'=>'onAfterAdminInvoiceUpdate', 'params'=>array('id'=>$model->id)));

        $this->di['logger']->info('Updated invoice "%s"', $model->id);
        return true;
    }

    public function rmInvoice(\Model_Invoice $model)
    {
        //remove related invoice from orders
        $sql="
            UPDATE client_order
            SET unpaid_invoice_id = NULL
            WHERE unpaid_invoice_id = :id";
        $this->di['db']->exec($sql, array('id'=>$model->id));

        $invoiceItems = $this->di['db']->find('InvoiceItem', 'invoice_id = ?', array($model->id));
        foreach($invoiceItems as $item) {
            $this->di['db']->trash($item);
        }
        $this->di['db']->trash($model);
        return true;
    }

    public function deleteInvoiceByAdmin(\Model_Invoice $model)
    {
        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminInvoiceDelete','params'=>array('id'=>$model->id)));

        $id = $model->id;
        $this->rmInvoice($model);

        $this->di['events_manager']->fire(array('event'=>'onAfterAdminInvoiceDelete', 'params'=>array('id'=>$id)));

        $this->di['logger']->info('Removed invoice #%s', $id);
        return TRUE;
    }

    public function deleteInvoiceByClient(\Model_Invoice $model)
    {
        $this->di['events_manager']->fire(array('event'=>'onBeforeClientInvoiceDelete', 'params'=>array('id'=>$model->id)));

        // check if invoice is associated with order
        $invoiceItem = $this->di['db']->find('InvoiceItem', 'invoice_id = ?', array($model->id));
        foreach($invoiceItem as $item) {
            if($item->type == \Model_InvoiceItem::TYPE_ORDER) {
                throw new \Box_Exception('Invoice is related to order #:id. Please cancel order first.', array(':id'=>$item->rel_id));
            }
        }

        $this->rmInvoice($model);
        $this->di['logger']->info('Removed invoice #%s', $model->id);
        return TRUE;
    }

    public function renewInvoice(\Model_ClientOrder $model, array $data)
    {
        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminGenerateRenewalInvoice', 'params'=>array('order_id'=>$model->id)));

        $due_days = isset($data['due_days']) ? (int)$data['due_days'] : null;
        $invoice = $this->generateForOrder($model, $due_days);
        $this->approveInvoice($invoice, (array('id'=>$invoice->id, 'use_credits'=>true)));

        $this->di['events_manager']->fire(array('event'=>'onAfterAdminGenerateRenewalInvoice', 'params'=>array('order_id'=>$model->id, 'id'=>$invoice->id)));

        $this->di['logger']->info('Generated renewal invoice #%s', $invoice->id);
        return $invoice->id;
    }

    public function doBatchPayWithCredits(array $data)
    {
        $unpaid = $this->findAllUnpaid($data);
        foreach($unpaid as $proforma) {
            try {
                $model = $this->di['db']->getExistingModelById('Invoice', $proforma['id']);
                $this->tryPayWithCredits($model);
            } catch(\Exception $e) {
                if($this->di['config']['debug']) {
                    error_log($e->getMessage());
                }
            }
        }
        $this->di['logger']->info('Executed action to try cover unpaid invoices with client credits');
        return true;
    }

    public function payInvoiceWithCredits(\Model_Invoice $model)
    {
        $this->tryPayWithCredits($model);
        $this->di['logger']->info('Cover invoice with client credits');
        return true;
    }

    /**
     * @param integer $due_days
     *
     * @return \Model_Invoice
     */
    public function generateForOrder(\Model_ClientOrder $order, $due_days = null)
    {
        //check if we do have invoice prepared already
        if(NULL !== $order->unpaid_invoice_id) {
            $p = $this->di['db']->load('Invoice', $order->unpaid_invoice_id);
            if($p instanceof \Model_Invoice) {
                return $p;
            }
        }

        if($order->price <= 0) {
            throw new \Box_Exception('Invoices are not generated for 0 amount orders');
        }

        $client = $this->di['db']->getExistingModelById('Client', $order->client_id, 'Client not found');

        // generate proforma
        $proforma = $this->di['db']->dispense('Invoice');
        $proforma->client_id = $client->id;
        $proforma->status = \Model_Invoice::STATUS_UNPAID;
        $proforma->currency = $order->currency;
        $proforma->approved = false;
        $proforma->created_at = date('Y-m-d H:i:s');
        $proforma->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($proforma);

        $this->setInvoiceDefaults($proforma);

        $price = $order->price;

        $invoiceItemService = $this->di['mod_service']('Invoice', "InvoiceItem");
        $invoiceItemService->generateFromOrder($proforma, $order, \Model_InvoiceItem::TASK_RENEW, $price);

        // invoice due date
        if($due_days > 0) {
            $proforma->due_at = date('Y-m-d H:i:s', strtotime('+'.$due_days.' days'));
            $this->di['db']->store($proforma);
        } else if($order->expires_at) {
            $proforma->due_at = $order->expires_at;
            $this->di['db']->store($proforma);
        }

        return $proforma;
    }

    public function generateInvoicesForExpiringOrders()
    {
        $orderService = $this->di['mod_service']('Order');
        $orders = $orderService->getSoonExpiringActiveOrders();

        if(count($orders) == 0) {
            return TRUE;
        }

        foreach($orders as $order) {
            try {
                $model = $this->di['db']->getExistingModelById('ClientOrder', $order['id']);
                $invoice = $this->generateForOrder($model);
                $this->approveInvoice($invoice, array('id'=>$invoice->id, 'use_credits'=>true));
            } catch(\Exception $e) {
                error_log($e->getMessage());
            }
        }

        $this->di['logger']->info('Executed action to generate new invoices for expiring orders');
        return true;
    }

    public function doBatchPaidInvoiceActivation()
    {
        $invoiceItemService = $this->di['mod_service']('Invoice', 'InvoiceItem');

        $invoiceItems = (array) $invoiceItemService->getAllNotExecutePaidItems();
        foreach($invoiceItems as $item) {
            try {
                $model = $this->di['db']->getExistingModelById('InvoiceItem', $item['id']);
                $invoiceItemService->executeTask($model);
            } catch(\Exception $e) {
                error_log($e->getMessage());
            }
        }
        $this->di['logger']->info('Executed action to activate paid invoices');
        return true;
    }

    public function doBatchRemindersSend()
    {
        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminInvoiceSendReminders'));
        $list = $this->getUnpaidInvoicesLateFor();
        foreach($list as $invoice) {
            $this->sendInvoiceReminder($invoice);
        }
        $this->di['logger']->info('Executed action to send invoice payment reminders');
        return true;
    }

    public function doBatchInvokeDueEvent(array $data)
    {
        $once_per_day = isset($data['once_per_day']) ? (bool)$data['once_per_day']: true;
        $key = 'invoice_overdue_invoked';

        //do not use api call to get system param to avoid invoking system module event hooks
        $ss = $this->di['mod_service']('System');
        $last_time = $ss->getParamValue($key);
        if($once_per_day && $last_time && (time() - strtotime($last_time)) < 86400) {
            //error_log('Already executed today.');
            return false;
        }

        $before_due_list = $this->di['db']->getAll("SELECT id, DATEDIFF(due_at, NOW()) as days_left FROM invoice WHERE status = 'unpaid' AND approved = 1 AND due_at > NOW()");
        foreach($before_due_list as $params) {
            $this->di['events_manager']->fire(array('event'=>'onEventBeforeInvoiceIsDue', 'params'=>$params));
        }

        $after_due_list = $this->di['db']->getAll("SELECT id, ABS(DATEDIFF(due_at, NOW())) as days_passed FROM invoice WHERE status = 'unpaid' AND approved = 1 AND ((due_at < NOW()) OR (ABS(DATEDIFF(due_at, NOW())) = 0 ))");
        foreach($after_due_list as $params) {
            $this->di['events_manager']->fire(array('event'=>'onEventAfterInvoiceIsDue', 'params'=>$params));
        }

        $ss->setParamValue($key, date('Y-m-d H:i:s'));
        $this->di['logger']->info('Executed action to invoke invoice due event');
        return true;
    }

    public function sendInvoiceReminder(\Model_Invoice $invoice)
    {
        // do not send accidental reminder for paid invoices
        if($invoice->status == \Model_Invoice::STATUS_PAID) {
            return true;
        }

        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminInvoiceSendReminder', 'params'=>array('id'=>$invoice->id)));

        $invoice->reminded_at = date('Y-m-d H:i:s');
        $invoice->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($invoice);

        $this->di['events_manager']->fire(array('event'=>'onAfterAdminInvoiceReminderSent', 'params'=>array('id'=>$invoice->id)));

        $this->di['logger']->info('Invoice payment reminder sent');
        return true;
    }

    public function counter()
    {
        $sql = 'SELECT status, count(id) as counter
                 FROM invoice
                 group by status';
        $rows = $this->di['db']->getAll($sql);
        $data = array();
        foreach ($rows as $row){
            $data [ $row['status'] ] = $row['counter'];
        }
        return array(
            'total' =>  array_sum($data),
            \Model_Invoice::STATUS_PAID =>  isset($data[\Model_Invoice::STATUS_PAID]) ? $data[\Model_Invoice::STATUS_PAID] : 0,
            \Model_Invoice::STATUS_UNPAID =>  isset($data[\Model_Invoice::STATUS_UNPAID]) ? $data[\Model_Invoice::STATUS_UNPAID] : 0,
            \Model_Invoice::STATUS_REFUNDED =>  isset($data[\Model_Invoice::STATUS_REFUNDED]) ? $data[\Model_Invoice::STATUS_REFUNDED] : 0,
            \Model_Invoice::STATUS_CANCELED =>  isset($data[\Model_Invoice::STATUS_CANCELED]) ? $data[\Model_Invoice::STATUS_CANCELED] : 0,
        );
    }

    public function generateFundsInvoice(\Model_Client $client, $amount)
    {
        if(!$client->currency) {
            throw new \Box_Exception('You must have at least one active order before you can add funds so you cannot proceed at the current time!');
        }

        $systemService = $this->di['mod_service']('system');

        $min_amount = $systemService->getParamValue('funds_min_amount', NULL);
        $max_amount = $systemService->getParamValue('funds_max_amount', NULL);

        if($min_amount && $amount < $min_amount) {
            throw new \Box_Exception('Amount is not valid', null, 981);
        }

        if($max_amount && $amount > $max_amount) {
            throw new \Box_Exception('Amount is not valid', null, 982);
        }

        $proforma = $this->di['db']->dispense('Invoice');
        $proforma->client_id = $client->id;
        $proforma->status = \Model_Invoice::STATUS_UNPAID;
        $proforma->currency = $client->currency;
        $proforma->approved = $this->_isAutoApproved();
        $proforma->created_at = date('Y-m-d H:i:s');
        $proforma->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($proforma);

        $this->setInvoiceDefaults($proforma);

        $invoiceItemService = $this->di['mod_service']('Invoice', 'InvoiceItem');
        $invoiceItemService->generateForAddFunds($proforma, $amount);

        return $proforma;
    }

    public function processInvoice(array $data)
    {
        $subscribe = false;

        $invoice = $this->di['db']->findOne('Invoice', 'hash = ?', array($data['hash']));
        if(!$invoice instanceof \Model_Invoice) {
            throw new \Box_Exception('Invoice not found', null, 812);
        }

        $gtw = $this->di['db']->load('PayGateway', $data['gateway_id']);
        if(!$gtw instanceof \Model_PayGateway) {
            throw new \Box_Exception('Payment method not found', null, 813);
        }

        if(!$gtw->enabled) {
            throw new \Box_Exception('Payment method not enabled', null, 814);
        }

        $subscribeService = $this->di['mod_service']('Invoice', 'Subscription');
        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        if($subscribeService->isSubscribable($invoice->id) && $payGatewayService->canPerformRecurrentPayment($gtw)) {
            $subscribe = true;
        }


        $adapter = $payGatewayService->getPaymentAdapter($gtw, $invoice, $data);
        if (method_exists($adapter, 'setDi')) {
            $adapter->setDi($this->di);
        }
        
        if (method_exists($adapter, 'setLog')) {
            $adapter->setLog($this->di['logger']);
        }

        $pgc = $adapter->getConfig();

        //@since v2.9.15
        if(method_exists($adapter, 'getHtml')) {
            $html = $adapter->getHtml($this->di['api_system'], $invoice->id, $subscribe);
            return array(
                'iframe'        =>  isset($pgc['can_load_in_iframe']) ? (bool)$pgc['can_load_in_iframe'] : false,
                'type'          =>  'html',
                'service_url'   =>  '',
                'subscription'  =>  $subscribe,
                'result'        =>  $html,
            );
        }

        $i = clone $invoice;
        $mpi = $this->getPaymentInvoice($i, $subscribe);
        $r = ($subscribe) ? $adapter->recurrentPayment($mpi) : $adapter->singlePayment($mpi);
        $this->di['logger']->info('Went to pay for invoice #%s via %s', $invoice->id, $gtw->gateway);

        //@bug https://github.com/boxbilling/BoxBilling/issues/108
        if($adapter->getType() != 'html') {
            $r = (array)$r;
        }

        return array(
            'type'          =>  $adapter->getType(),
            'service_url'   =>  $adapter->getServiceURL(),
            'subscription'  =>  $subscribe,
            'result'        =>  $r,
        );
    }

    public function generatePDF($hash, $identity)
    {
        $invoice = $this->di['db']->findOne('Invoice', 'hash = :hash', array(':hash' => $hash));
        if (!$invoice instanceof \Model_Invoice) {
            throw new \Box_Exception('Invoice not found');
        }

        if (isset($invoice->currency)) {
            $currencyCode = $invoice->currency;
        } else {
            $client       = $this->di['db']->getExistingModelById('Client', $invoice->client_id, 'Client not found');
            $currencyCode = $client->currency;
        }

        $invoice       = $this->toApiArray($invoice, false, $identity);
        $systemService = $this->di['mod_service']('System');
        $company       = $systemService->getCompany();
        $pdf           = $this->di['pdf'];
        $pdf->AddPage();

        $pdf->AddFont('DejaVu', '', 'DejaVuSansCondensed.ttf', true);
        $pdf->AddFont('DejaVu', 'B', 'DejaVuSansCondensed-Bold.ttf', true);
        $pdf->SetFont('DejaVu', 'B', 20);
        $font_size = 8;
        $left      = 10;

        $pdf->text(90, 20, $invoice['serie_nr']);

        $pdf->SetFont('DejaVu', '', $font_size);

        if (isset($company['logo_url']) && !empty($company['logo_url'])) {
            $url = $company['logo_url'];
            if (substr($url, -4) === '.png') {
                $pdf->ImagePngWithAlpha($url, $left + 75, 35, 50);
            } else {
                //Converting to .png
                $img = imagecreatefromstring(file_get_contents($url));
                if ($img) {
                    $filename = 'logo.png';
                    if (imagepng($img, $filename)) {
                        $pdf->ImagePngWithAlpha($filename, $left + 75, 35, 50);
                        unlink($filename);
                    }
                } else {
                    throw new \Box_Exception('Error converting image to .png');
                }
            }
        }

        $localeDateFormat = $this->di['config']['locale_date_format'];
        $invoiceDate = strftime($localeDateFormat, strtotime( $this->di['array_get']($invoice, 'due_at', $invoice['created_at']) ));
        $invoice_info = __("Invoice number: ") . $invoice['nr'] . "\n" .
            __("Invoice date: ") . $invoiceDate . "\n" .
            __("Due date: ") . strftime($localeDateFormat, strtotime($invoice['due_at'])) . "\n" .
            __("Invoice status: ") . ucfirst($invoice['status']);
        $pdf->SetFont('DejaVu', 'B', $font_size);
        $pdf->text($left + 15, 75, __("Invoice"));
        $pdf->SetFont('DejaVu', '', $font_size);

        $pdf->SetXY($left, 70);
        $pdf->MultiCell(60, 10, "\n" . $invoice_info, 0, "L", 0);

        $company_info = __("Name: ") . $invoice['seller']['company'] . "\n" .
            __("Address: ") . $invoice['seller']['address'] . "\n" .
            __("Company VAT: ") . $invoice['seller']['company_vat'] . "\n" .
            __("Company number: ") . $invoice['seller']['company_number'] . "\n" .
            __("Account: ") . $invoice['seller']['account_number'] . "\n" .
            __("Phone: ") . $invoice['seller']['phone'] . "\n" .
            __("Email: ") . $invoice['seller']['email'];
        $pdf->SetFont('DejaVu', 'B', $font_size);
        $pdf->text(95, 75, __("Company"));
        $pdf->SetFont('DejaVu', '', $font_size);
        $pdf->SetXY(75, 70);
        $pdf->MultiCell(60, 10, "\n" . $company_info, 0, "L", 0);

        $buyer_info = __("Name: ") . $invoice['buyer']['first_name'].' ' . $invoice['buyer']['last_name'] . "\n" .
            __("Company: ") . $invoice['buyer']['company'] . "\n" .
            __("Address: ") . $invoice['buyer']['address'] . "\n" .
            __("Company VAT: ") . $invoice['seller']['company_vat'] . "\n" .
            __("Company number: ") . $invoice['seller']['company_number'] . "\n" .
            __("Phone: ") . $invoice['buyer']['phone'];
        $pdf->SetFont('DejaVu', 'B', $font_size);
        $pdf->text(145, 75, __("Billing and delivery address"));
        $pdf->SetFont('DejaVu', '', $font_size);

        $pdf->SetXY(140, 70);
        $pdf->MultiCell(60, 10, "\n" . $buyer_info, 0, "L", 0);

        $header = array(__('#'), __('Title'), __('Price'), __('Total'));
        $pdf->SetXY($left, 150);
        $w = array(10, 120, 30, 30);

        $counted = count($header);
        for ($i = 0; $i < $counted; $i++)
            $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C');
        $pdf->Ln();

        $nr = 1;
        foreach ($invoice['lines'] as $row) {
            $pdf->Cell($w[0], 6, $nr++, 'LR');
            $pdf->Cell($w[1], 6, $row['title'], 'LR');
            $pdf->Cell($w[2], 6, ($row['quantity'] > 1) ? $row['quantity'] . ' x ' . $this->money($row['price'], $currencyCode) : $this->money($row['price'], $currencyCode), 'LR', 0, 'R');
            $pdf->Cell($w[3], 6, $this->money($row['total'], $currencyCode), 'LR', 0, 'R');
            $pdf->Ln();
        }
        $pdf->Cell(array_sum($w), 0, '', 'T');

        $y = $pdf->GetY();
        $pdf->SetXY(120, $y + 10);
        if ($invoice['tax'] > 0) {
            $pdf->Cell(40, 6, $invoice['taxname'] . ' ' . $invoice['taxrate'] . "%", 'LRTB', 0, 'C');
            $pdf->Cell(40, 6, $this->money($invoice['tax'], $currencyCode), 'LRTB', 0, 'C');
            $pdf->Ln();
            $pdf->SetX(120);
        }

        $pdf->SetX(120);
        if (isset($invoice['discount']) && $invoice['discount'] > 0) {
            $pdf->Cell(40, 8, __('Discount '), 'LRTB', 0, 'C');
            $pdf->Cell(40, 8, $this->money($invoice['discount'], $currencyCode), 'LRTB', 0, 'C');
            $pdf->Ln();
            $pdf->SetX(120);
        }

        $pdf->SetFont('DejaVu', 'B', $font_size + 2);
        $pdf->Cell(40, 10, __('Total'), 'LRTB', 0, 'C');
        $pdf->Cell(40, 10, $this->money($invoice['total'], $currencyCode), 'LRTB', 0, 'C');
        $pdf->Ln();

        $pdf->Output($invoice["serie_nr"] . ".pdf", "I");
    }

    private function money($price, $currencyCode)
    {
        return $this->di['api_guest']->currency_format(array("price" => $price, 'code' => $currencyCode, 'convert' => false));
    }

    public function addNote(\Model_Invoice $model, $note)
    {
        $n = $model->notes;
        $model->notes = $n . date('Y-m-d H:i:s') .': '.$note.'       '.PHP_EOL;
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);
        return true;
    }

    /**
     * Return list of unpaid invoices which can be covered from client balance
     * @param array $filter
     * @return array
     */
    public function findAllUnpaid(array $filter = null)
    {
        $sql = 'SELECT m.*
                FROM invoice as m
                    LEFT JOIN client as cl on m.client_id = cl.id
                    LEFT JOIN client_balance as cb on m.client_id = cb.client_id
                    LEFT JOIN invoice_item as pi on pi.invoice_id = m.id
                WHERE m.status = :status
                    AND m.approved = 1
                    AND cb.amount >= pi.price';
        $params = array('status' => \Model_Invoice::STATUS_UNPAID);

        $client_id = isset($filter['client_id']) ? (int)$filter['client_id'] : null;

        if($client_id) {
            $sql .= ' AND m.client_id = :client_id ';
            $params['client_id'] = $client_id;
        }

        $sql .= ' GROUP BY m.id, cl.id
                 ORDER BY m.id DESC';

        return $this->di['db']->getAll($sql, $params);
    }

    public function findAllPaid()
    {
        return $this->di['db']->find('Invoice', 'status = ? order by id desc', array(\Model_Invoice::STATUS_PAID));
    }

    public function getUnpaidInvoicesLateFor($days_after_issue = 2)
    {
        $conditions = 'status = ? and approved = 1 and reminded_at is null and DATEDIFF(NOW(), created_at) > ?';
        return $this->di['db']->find('Invoice', $conditions, array(\Model_Invoice::STATUS_UNPAID, $days_after_issue));
    }

    /**
     * @return bool
     */
    private function _isAutoApproved()
    {
        /**
         * @var \Box\Mod\System\Service $systemService
         */
        $systemService = $this->di['mod_service']('system');
        return (bool) $systemService->getParamValue('invoice_auto_approval', true);
    }

    /**
     * @param \Model_Invoice $invoice
     * @param bool $subscribe
     * @return \Payment_Invoice
     */
    public function getPaymentInvoice(\Model_Invoice $invoice, $subscribe = false)
    {
        $proforma = $this->toApiArray($invoice);
        $client = $this->getBuyer($invoice);

        $buyer = new \Payment_Invoice_Buyer();
        $buyer
            ->setEmail($client['email'])
            ->setFirstName($client['first_name'])
            ->setLastName($client['last_name'])
            ->setCompany($client['company'])
            ->setAddress($client['address'])
            ->setCity($client['city'])
            ->setState($client['state'])
            ->setZip($client['zip'])
            ->setPhone($client['phone'])
            ->setPhoneCountryCode($client['phone_cc'])
            ->setCountry($client['country']);

        $first_title = null;
        $items = array();
        foreach($proforma['lines'] as $item) {
            $pi = new \Payment_Invoice_Item();
            $pi
                ->setId($item['id'])
                ->setTitle($item['title'])
                ->setDescription($item['title'])
                ->setPrice($item['price'])
                ->setTax($item['tax'])
                ->setQuantity($item['quantity']);
            $items[] = $pi;
            if(is_null($first_title) && count($proforma['lines']) == 1) {
                $first_title = $item['title'];
            }
        }

        $params = array(
            ':id'=>sprintf('%05s', $proforma['nr']),
            ':serie'=>$proforma['serie'],
            ':title'=>$first_title);
        if($first_title) {
            $title = __('Payment for invoice :serie:id [:title]', $params);
        } else {
            $title = __('Payment for invoice :serie:id', $params);
        }

        $mpi = new \Payment_Invoice();
        $mpi->setId($invoice->id);
        $mpi->setNumber($proforma['nr']);
        $mpi->setBuyer($buyer);
        $mpi->setCurrency($proforma['currency']);
        $mpi->setTitle($title);
        $mpi->setItems($items);

        $subscribeService = $this->di['mod_service']('Invoice', 'Subscription');
        // can subscribe only if proforma has one item with defined period
        if($subscribe && $subscribeService->isSubscribable($invoice->id)) {

            $subitem = $invoice->InvoiceItem->getFirst();
            $period = $this->di['period']($subitem->period);

            $bs = new \Payment_Invoice_Subscription();
            $bs->setId($proforma['id']);
            $bs->setAmount($mpi->getTotalWithTax());
            $bs->setCycle($period->getQty());
            $bs->setUnit($period->getUnit());

            $mpi->setSubscription($bs);
            $mpi->setTitle('Subscription for '.$subitem->title);
        }

        return $mpi;
    }

    public function getBuyer(\Model_Invoice $invoice)
    {
        return array(
            'first_name'=> $invoice->buyer_first_name,
            'last_name' => $invoice->buyer_last_name,
            'company'   => $invoice->buyer_company,
            'address'   => $invoice->buyer_address,
            'city'      => $invoice->buyer_city,
            'state'     => $invoice->buyer_state,
            'country'   => $invoice->buyer_country,
            'phone'     => $invoice->buyer_phone,
            'phone_cc'  => '',
            'email'     => $invoice->buyer_email,
            'zip'       => $invoice->buyer_zip,
        );
    }

    public function rmByClient(\Model_Client $client)
    {
        $invoices = $this->di['db']->find('Invoice', 'client_id = ?', array($client->id));
        foreach($invoices as $invoice) {
            $invoiceItems = $this->di['db']->find('InvoiceItem', 'invoice_id = ?', array($invoice->id));
            foreach ($invoiceItems as $invoiceItem){
                $this->di['db']->trash($invoiceItem);
            }
            $this->di['db']->trash($invoice);
        }
    }

    /**
     * @param \Model_Invoice $invoice
     * @return bool
     */
    public function isInvoiceTypeDeposit(\Model_Invoice $invoice)
    {
        $invoiceItems = $this->di['db']->find('InvoiceItem', 'invoice_id = ?', array($invoice->id));
        foreach($invoiceItems as $item) {
            if($item->type == \Model_InvoiceItem::TYPE_DEPOSIT) {
                return true;
            }
        }
        return false;
    }
}