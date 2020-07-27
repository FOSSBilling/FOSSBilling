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

class ServiceTransaction implements InjectionAwareInterface
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

    public function proccessReceivedATransactions()
    {
        $this->di['logger']->info('Executed action to process received transactions');
        $received = $this->getReceived();
        foreach($received as $transaction) {
            $model = $this->di['db']->getExistingModelById('Transaction', $transaction['id']);
            $this->preProcessTransaction($model);
        }
        return true;
    }

    public function update(\Model_Transaction $model, array $data)
    {
        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminTransactionUpdate', 'params'=>array('id'=>$model->id)));

        $model->invoice_id = $this->di['array_get']($data, 'invoice_id', $model->invoice_id);
        $model->txn_id = $this->di['array_get']($data, 'txn_id', $model->txn_id);
        $model->txn_status = $this->di['array_get']($data, 'txn_status', $model->txn_status);
        $model->gateway_id = $this->di['array_get']($data, 'gateway_id', $model->gateway_id);
        $model->amount = $this->di['array_get']($data, 'amount', $model->amount);
        $model->currency = $this->di['array_get']($data, 'currency', $model->currency);
        $model->type = $this->di['array_get']($data, 'type', $model->type);
        $model->note = $this->di['array_get']($data, 'note', $model->note);
        $model->status = $this->di['array_get']($data, 'status', $model->status);
        $model->error = $this->di['array_get']($data, 'error', $model->error);
        $model->error_code = $this->di['array_get']($data, 'error_code', $model->error_code);
        $model->validate_ipn = $this->di['array_get']($data, 'validate_ipn', $model->validate_ipn);
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);
        $this->di['events_manager']->fire(array('event'=>'onAfterAdminTransactionUpdate', 'params'=>array('id'=>$model->id)));

        $this->di['logger']->info('Updated transaction #%s', $model->id);
        return true;
    }

    public function createAndProcess($ipn)
    {
        $id = $this->create($ipn);
        $this->processTransaction($id);
        return $id;
    }

    public function create(array $data)
    {
        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminTransactionCreate', 'params'=>$data));

        $skip_validation = isset($data['skip_validation']) ? (bool)$data['skip_validation'] : false;
        if(!$skip_validation) {
            if(!isset($data['bb_invoice_id'])) {
                throw new \Box_Exception('Transaction invoice id is missing');
            }

            if(!isset($data['bb_gateway_id'])) {
                throw new \Box_Exception('Payment gateway id is missing');
            }
            $this->di['db']->getExistingModelById('Invoice', $data['bb_invoice_id'], 'Invoice was not found');
            $this->di['db']->getExistingModelById('PayGateway', $data['bb_gateway_id'], 'Gateway was not found');
        }

        $ipn = array(
            'get'                   =>  (isset($data['get']) && is_array($data['get'])) ? $data['get'] : NULL,
            'post'                  =>  (isset($data['post']) && is_array($data['post'])) ? $data['post'] : NULL,
            'http_raw_post_data'    =>  $this->di['array_get']($data, 'http_raw_post_data', NULL),
            'server'                =>  $this->di['array_get']($data, 'server', NULL),
        );

        $transaction = $this->di['db']->dispense('Transaction');
        $transaction->gateway_id    = $this->di['array_get']($data, 'bb_gateway_id', NULL);
        $transaction->invoice_id    = $this->di['array_get']($data, 'bb_invoice_id', NULL);
        $transaction->txn_id        = $this->di['array_get']($data, 'txn_id', NULL);
        $transaction->status        = 'received';
        $transaction->ip            = $this->di['request']->getClientAddress();
        $transaction->ipn           = json_encode($ipn);
        $transaction->note          = (isset($data['note'])) ? $data['note'] : NULL;
        $transaction->created_at    = date('Y-m-d H:i:s');
        $transaction->updated_at    = date('Y-m-d H:i:s');
        $newId = $this->di['db']->store($transaction);

        $this->di['logger']->info('Received transaction %s from payment gateway %s', $newId, $transaction->gateway_id);

        $this->di['events_manager']->fire(array('event'=>'onAfterAdminTransactionCreate', 'params'=>array('id'=>$newId)));

        return $newId;
    }

    public function delete(\Model_Transaction $model)
    {
        $id = $model->id;
        $this->di['db']->trash($model);
        $this->di['logger']->info('Removed transaction #%s', $id);
        return true;
    }

    public function toApiArray(\Model_Transaction $model, $deep = false, $identity = null)
    {
        $gateway = NULL;
        if($model->gateway_id) {
            $gtw = $this->di['db']->load('PayGateway', $model->gateway_id);
            if($gtw instanceof \Model_PayGateway) {
                $gateway = $gtw->name;
            }
        }

        $result = array(
            'id'    =>  $model->id,
            'invoice_id'    =>  $model->invoice_id,
            'txn_id'    =>  $model->txn_id,
            'txn_status'    =>  $model->txn_status,
            'gateway_id'    =>  $model->gateway_id,
            'gateway'    =>  $gateway,
            'amount'    =>  $model->amount,
            'currency'    =>  $model->currency,
            'type'    =>  $model->type,
            'status'    =>  $model->status,
            'ip'    =>  $model->ip,
            'validate_ipn'    =>  $model->validate_ipn,
            'error'    =>  $model->error,
            'error_code'    =>  $model->error_code,
            'note'    =>  $model->note,
            'created_at'    =>  $model->created_at,
            'updated_at'    =>  $model->updated_at,
        );
        if($deep) {
            $result['ipn'] = json_decode($model->ipn, true);
        }
        return $result;
    }

    public function getSearchQuery(array $data)
    {
        $sql = 'SELECT m.*
                FROM transaction as m
                LEFT JOIN invoice as i on m.invoice_id = i.id
                WHERE 1 ';

        $id           = $this->di['array_get']($data, 'id', NULL);
        $search       = $this->di['array_get']($data, 'search', NULL);
        $invoice_hash = $this->di['array_get']($data, 'invoice_hash', NULL);
        $invoice_id   = $this->di['array_get']($data, 'invoice_id', NULL);
        $gateway_id   = $this->di['array_get']($data, 'gateway_id', NULL);
        $client_id    = $this->di['array_get']($data, 'client_id', NULL);
        $status       = $this->di['array_get']($data, 'status', NULL);
        $currency     = $this->di['array_get']($data, 'currency', NULL);
        $type         = $this->di['array_get']($data, 'type', NULL);
        $txn_id       = $this->di['array_get']($data, 'txn_id', NULL);

        $date_from = $this->di['array_get']($data, 'date_from', NULL);
        $date_to   = $this->di['array_get']($data, 'date_to', NULL);

        $params = array();
        if ($id) {
            $sql .= ' AND m.id = :id';
            $params['id'] = $id;
        }

        if ($status) {
            $sql .= ' AND m.status = :status';
            $params['status'] = $status;
        }

        if ($invoice_hash) {
            $sql .= ' AND i.hash = :hash';
            $params['hash'] = $invoice_hash;
        }

        if ($invoice_id) {
            $sql .= ' AND m.invoice_id = :invoice_id';
            $params['invoice_id'] = $invoice_id;
        }

        if ($gateway_id) {
            $sql .= ' AND m.gateway_id = :gateway_id';
            $params['gateway_id'] = $gateway_id;
        }

        if ($client_id) {
            $sql .= ' AND i.client_id = :client_id';
            $params['client_id'] = $client_id;
        }

        if ($currency) {
            $sql .= ' AND m.currency = :currency';
            $params['currency'] = $currency;
        }

        if ($type) {
            $sql .= ' AND m.type = :type';
            $params['type'] = $type;
        }

        if ($txn_id) {
            $sql .= ' AND m.txn_id = :txn_id';
            $params['txn_id'] = $txn_id;
        }

        if ($date_from) {
            $sql .= ' AND UNIX_TIMESTAMP(m.created_at) >= :date_from';
            $params['date_from'] = strtotime($date_from);
        }

        if ($date_to) {
            $sql .= ' AND UNIX_TIMESTAMP(m.created_at) <= :date_to';
            $params['date_to'] = strtotime($date_to);
        }

        if ($search) {
            $sql .= ' AND m.note LIKE :note OR m.invoice_id LIKE :search_invoice_id OR m.txn_id LIKE :search_txn_id OR m.ipn LIKE :ipn';
            $params['note']               = "%$search%";
            $params['search__invoice_id'] = "%$search%";
            $params['search_txn_id']      = "%$search%";
            $params['ipn']                = "%$search%";
        }

        $sql .= ' ORDER BY m.id DESC';

        return array($sql, $params);
    }

    public function counter()
    {
        $sql = 'SELECT status, count(id) as counter
            FROM transaction
            GROUP BY status';
        $rows = $this->di['db']->getAll($sql);
        $data = array();
        foreach($rows as $row){
            $data[ $row['status'] ] = $row['counter'];
        }

        return array(
            'total' =>  array_sum($data),
            \Model_Transaction::STATUS_RECEIVED  =>  isset($data[\Model_Transaction::STATUS_RECEIVED])  ? $data[\Model_Transaction::STATUS_RECEIVED] : 0,
            \Model_Transaction::STATUS_APPROVED  =>  isset($data[\Model_Transaction::STATUS_APPROVED])  ? $data[\Model_Transaction::STATUS_APPROVED] : 0,
            \Model_Transaction::STATUS_PROCESSED =>  isset($data[\Model_Transaction::STATUS_PROCESSED]) ? $data[\Model_Transaction::STATUS_PROCESSED] : 0,
            \Model_Transaction::STATUS_ERROR     =>  isset($data[\Model_Transaction::STATUS_ERROR])     ? $data[\Model_Transaction::STATUS_ERROR] : 0,
        );
    }

    public function getStatusPairs()
    {
        return array(
            \Model_Transaction::STATUS_RECEIVED =>  'Received',
            \Model_Transaction::STATUS_APPROVED  => 'Approved',
            \Model_Transaction::STATUS_PROCESSED => 'Processed',
            \Model_Transaction::STATUS_ERROR     => 'Error',
        );
    }

    public function getStatuses()
    {
        return array(
            \Model_Transaction::STATUS_RECEIVED      =>  'Received',
            \Model_Transaction::STATUS_APPROVED      =>  'Approved/Verified',
            \Model_Transaction::STATUS_PROCESSED     =>  'Processed',
            \Model_Transaction::STATUS_ERROR         =>  'Error',
        );
    }

    public function getGatewayStatuses()
    {
        return array(
            \Payment_Transaction::STATUS_PENDING        =>  'Pending validation',
            \Payment_Transaction::STATUS_COMPLETE       =>  'Complete',
            \Payment_Transaction::STATUS_UNKNOWN        =>  'Unknown',

        );
    }

    public function getTypes()
    {
        return array(
            \Payment_Transaction::TXTYPE_PAYMENT         =>  'Payment',
            \Payment_Transaction::TXTYPE_REFUND          =>  'Refund',
            \Payment_Transaction::TXTYPE_SUBSCR_CREATE   =>  'Subscription create',
            \Payment_Transaction::TXTYPE_SUBSCR_CANCEL   =>  'Subscription cancel',
            \Payment_Transaction::TXTYPE_UNKNOWN         =>  'Unknown',
        );
    }

    /**
     * @param \Model_Transaction $model
     */
    public function oldProcessLogic($model)
    {
        $tx = $this->process($model);
        return !empty($tx->output) ? $tx->output : null;
    }

    public function preProcessTransaction(\Model_Transaction $model)
    {
        try {
            $output = $this->processTransaction($model->id);
        } catch (\Box_Exception $e) {
            // if gateway does not support new logic use old logic
            if($e->getCode() == 705) {
                $output = $this->oldProcessLogic($model);
            } else {
                $model->status = \Model_Transaction::STATUS_ERROR;
                $model->error = $e->getMessage();
                $model->error_code = $e->getCode();
                $model->updated_at = date('Y-m-d H:i:s');
                $this->di['db']->store($model);
                throw $e;
            }
        }

        $this->di['events_manager']->fire(array('event'=>'onAfterAdminTransactionProcess', 'params'=>array('id'=>$model->id)));
        $this->di['logger']->info('Processed transaction #%s', $model->id);
        return !empty($output) ? $output : true;

    }

    /**
     * New simplified transaction processing logic
     *
     * @since 2.9.11
     * @param type $id
     * @return mixed
     * @throws Box_Exception
     */
    public function processTransaction($id)
    {
        $tx = $this->di['db']->load('Transaction', $id);
        if(!$tx) {
            throw new \Box_Exception('Transaction :id not found.', array('id'=>$id), 404);
        }

        if(empty($tx->gateway_id)) {
            throw new \Box_Exception('Could not determine transaction origin. Transaction payment gateway is unknown.', null, 701);
        }

        $gtw = $this->di['db']->load('PayGateway', $tx->gateway_id);
        if(!$gtw instanceof \Model_PayGateway) {
            throw new \Box_Exception('Can not handle transaction received from unknown payment gateway: :id', array(':id'=>$tx->gateway_id), 704);
        }

        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        $adapter = $payGatewayService->getPaymentAdapter($gtw);
        if(!method_exists($adapter, 'processTransaction')) {
            throw new \Box_Exception('Payment adapter :adapter does not support action :action', array(':adapter'=>$gtw->name, ':action'=>'processTransaction'), 705);
        }

        $ipn = json_decode($tx->ipn, 1);
        return $adapter->processTransaction($this->di['api_system'], $id, $ipn, $tx->gateway_id);
    }

    public function getReceived()
    {
        $filter = array(
            'status'    =>  'received'
        );
        list($sql, $params) = $this->getSearchQuery($filter);

        return $this->di['db']->getAll($sql, $params);
    }

    public function process($tx)
    {
        $transaction = $this->di['db']->load('Transaction', $tx->id);

        if($this->_isProcessed($transaction)) {
            return $transaction;
        }

        try {
            $this->_parseIpnAndApprove($transaction);

            switch ($transaction->type) {
                case \Payment_Transaction::TXTYPE_PAYMENT:
                    $this->_debit($transaction);
                    break;

                case \Payment_Transaction::TXTYPE_REFUND:
                    $this->_refund($transaction);
                    break;

                case \Payment_Transaction::TXTYPE_SUBSCR_CREATE:
                    $this->_subscribe($transaction);
                    break;

                case \Payment_Transaction::TXTYPE_SUBSCR_CANCEL:
                    $this->_unsubscribe($transaction);
                    break;

                default:
                    throw new \Box_Exception('Unknown transaction #:id type: :type', array(':id'=>$transaction->id, ':type'=>$transaction->type), 632);
            }
        } catch(\Exception $e) {
            $transaction->status = \Model_Transaction::STATUS_ERROR;
            $transaction->error = $e->getMessage();
            $transaction->error_code = $e->getCode();
            $transaction->updated_at = date('Y-m-d H:i:s');
            $this->di['db']->store($transaction);

            if(BB_DEBUG) error_log($e->getMessage());
            if(APPLICATION_ENV == 'testing') throw $e;
        }

        return $transaction;
    }

    private function _isProcessed(\Model_Transaction $tx)
    {
        if($tx->status == \Model_Transaction::STATUS_PROCESSED) {
            $tx->error = NULL;
            $tx->error_code = NULL;
            $tx->updated_at = date('Y-m-d H:i:s');
            $this->di['db']->store($tx);
            return true;
        }

        if($this->hasProcessedTransaction($tx)) {

            $tx->note       .= 'Transaction was marked as processed. Transaction with same ID is already processed';
            $tx->updated_at = date('Y-m-d H:i:s');
            $this->di['db']->store($tx);

            $this->_markAsProcessed($tx);
            return true;
        }

        return false;
    }

    private function hasProcessedTransaction(\Model_Transaction $tx)
    {
        if(!$tx->txn_id) {
            return false;
        }

        $res = $this->di['db']->findOne('Transaction', 'status = "processed" and txn_id = ?', array($tx->txn_id));
        return empty($res);
    }

    private function _markAsProcessed(\Model_Transaction $tx)
    {
        $tx->error = NULL;
        $tx->error_code = NULL;
        $tx->status = \Model_Transaction::STATUS_PROCESSED;
        $tx->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($tx);
    }

    private function _parseIpnAndApprove(\Model_Transaction &$tx)
    {
        if($tx->status == \Model_Transaction::STATUS_APPROVED) {
            return $tx;
        }

        $invoiceService = $this->di['mod_service']('Invoice');
        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        $ipn = $this->di['tools']->decodeJ($tx->ipn);

        if(empty($tx->gateway_id)) {
            throw new \Box_Exception('Could not determine transaction origin. Transaction payment gateway is unknown.', null, 701);
        }

        $gtw = $this->di['db']->load('PayGateway', $tx->gateway_id);
        if(!$gtw instanceof \Model_PayGateway) {
            throw new \Box_Exception('Can not handle transaction received from unknown payment gateway: :id', array(':id'=>$tx->gateway_id), 704);
        }

        $adapter = $payGatewayService->getPaymentAdapter($gtw);
        if(!$tx->invoice_id && method_exists($adapter, 'getInvoiceId')) {
            $tx->invoice_id = $adapter->getInvoiceId($ipn);
        }

        if(!$tx->invoice_id) {
            throw new \Box_Exception('Transaction :id is not associated with an invoice.', array(':id'=>$tx->id), 702);
        }

        $invoice = $this->di['db']->load('Invoice', $tx->invoice_id);
        if(!$invoice instanceof \Model_Invoice) {
            throw new \Box_Exception('Invoice #:id not found', array(':id'=>$tx->invoice_id), 703);
        }

        $adapter = $payGatewayService->getPaymentAdapter($gtw, $invoice);
        $mpi = $invoiceService->getPaymentInvoice($invoice);

        if(APPLICATION_ENV != 'testing' && $tx->validate_ipn) {
            if(!$adapter->isIpnValid($ipn, $mpi)) {
                $tx->output        = $adapter->getOutput();
                throw new \Box_Exception('Instant payment notification (IPN) did not pass gateway :id validation', array(':id'=>$gtw->gateway), 706);
            }
            $tx->output        = $adapter->getOutput();
        }

        if(!method_exists($adapter, 'getTransaction')) {
            throw new \Box_Exception('Payment adapter :adapter does not support action :action', array(':adapter'=>$gtw->name, ':action'=>'getTransaction'), 705);
        }

        $response = $adapter->getTransaction($ipn, $mpi);
        if(!$response instanceof \Payment_Transaction) {
            throw new \Box_Exception('Payment gateway :id method getTransaction should return Payment_Transaction object', array(':id'=>$gtw->gateway), 705);
        }

        // if tx type is already defined, do not set them again
        if($response->getType()) {
            $tx->type          = $response->getType();
        }

        if($response->getId()) {
            $tx->txn_id          = $response->getId();
        }

        if($response->getStatus()) {
            $tx->txn_status          = $response->getStatus();
        }

        if($response->getSubscriptionId()) {
            $tx->s_id = $response->getSubscriptionId();
        }

        if ($response->getAmount()) {
            $tx->amount = $response->getAmount();
        }

        if ($response->getCurrency()) {
            $tx->currency = $response->getCurrency();
        }

        $tx->status     = \Model_Transaction::STATUS_APPROVED;
        $tx->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($tx);

        return $tx;
    }

    private function _debit(\Model_Transaction $tx)
    {
        if($this->_isProcessed($tx)) {
            return $tx;
        }

        $this->_validateApprovedTransaction($tx);

        $this->debitTransaction($tx);

        $this->_markAsProcessed($tx);

        // try pay for invoice after debit
        if($tx->invoice_id) {
            try {
                $invoiceService = $this->di['mod_service']('Invoice');
                $invoiceService->tryPayWithCredits($tx->Invoice);
            } catch(\Exception $e) {
                if($this->di['config']['debug']){
                    error_log($e->getMessage());
                }
            }
        }
    }

    private function _refund(\Model_Transaction $tx)
    {
        if($this->_isProcessed($tx)) {
            return $tx;
        }

        $this->_validateApprovedTransaction($tx);

        $invoice = $this->di['db']->load('Invoice', $tx->invoice_id);
        $note = sprintf('Transaction %s refund', $tx->id);

        $invoiceService = $this->di['mod_service']('Invoice');
        $invoiceService->refund($invoice, $note);

        $this->_markAsProcessed($tx);
        return $tx;
    }

    private function _subscribe(\Model_Transaction $tx)
    {
        if($this->_isProcessed($tx)) {
            return $tx;
        }

        $this->_validateApprovedTransaction($tx);

        if(empty($tx->s_id)) {
            throw new \Box_Exception('Can not create subscription. Subscription id from payment gateway was not received');
        }

        $invoice = $this->di['db']->load('Invoice', $tx->invoice_id);
        $subscriptionService = $this->di['mod_service']('Invoice', 'Subscription');
        $period = $subscriptionService->getSubscriptionPeriod($invoice);

        $s = $this->di['db']->dispense('Subscription');
        $s->client_id = $invoice->client_id;
        $s->pay_gateway_id = $tx->gateway_id;
        $s->sid = $tx->s_id;
        $s->period = $period;
        $s->rel_type = 'invoice';
        $s->rel_id = $invoice->id;
        $s->amount = $tx->amount;
        $s->currency = $invoice->currency;
        $s->status = 'active';
        $s->created_at = date('Y-m-d H:i:s');
        $s->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($s);

        $this->_markAsProcessed($tx);
        return $tx;
    }

    private function _unsubscribe(\Model_Transaction $tx)
    {
        if($this->_isProcessed($tx)) {
            return $tx;
        }


        $serviceSubscription = $this->di['mod_service']("Subscription");
        $model = $this->di['db']->load('Subscription', $tx->s_id);
        if(!$model instanceof \Model_Subscription) {
            throw new \Box_Exception('Subscription #:id was not found. Could not unsubscribe', array(':id'=>$tx->s_id));
        }

        $serviceSubscription->unsubscribe($model);

        $this->_markAsProcessed($tx);
        return $tx;
    }

    private function _validateApprovedTransaction(\Model_Transaction $tx)
    {
        if($tx->status != \Model_Transaction::STATUS_APPROVED) {
            throw new \Box_Exception('Only approved transaction can be processed');
        }

        if(empty($tx->invoice_id)) {
            throw new \Box_Exception('Transaction :id is not associated with an invoice.', array(':id'=>$tx->id), 7022);
        }

        $invoice = $this->di['db']->load('Invoice', $tx->invoice_id);

        // check that payment currency is correct
        if($invoice->currency != $tx->currency) {
            throw new \Box_Exception('Transaction currency :code do not match required currency :required', array(':code'=>$tx->currency, ':required'=>$invoice->currency), 709);
        }

        // check that payment status is completed if
        if($tx->txn_status == \Payment_Transaction::STATUS_PENDING) {
            throw new \Box_Exception('Transaction status on payment gateway is Pending. Only Complete or Unknown transactions can be processed.', null, 712);
        }
    }

    public function debitTransaction(\Model_Transaction $tx)
    {
        $proforma =  $this->di['db']->load('Invoice', $tx->invoice_id);
        $client =  $this->di['db']->load('Client', $proforma->client_id);

        if($client->currency != $proforma->currency) {
            throw new \Box_Exception('Client currency do not match invoice currency');
        }

        // do not debit negative or zero amount
        if($tx->amount < 0) {
            throw new \Box_Exception('Can not add negative amount to client balance for debit transaction');
        }

        $credit = $this->di['db']->dispense('ClientBalance');
        $credit->client_id = $client->id;
        $credit->type = 'transaction';
        $credit->rel_id = $tx->id;
        $credit->description = "Invoice #".$proforma->id . ' payment received from transaction #'.$tx->id;
        $credit->amount = $tx->amount;
        $credit->created_at = date('Y-m-d H:i:s');
        $credit->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($credit);
    }

}