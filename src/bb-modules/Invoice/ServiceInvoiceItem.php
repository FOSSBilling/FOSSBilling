<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Invoice;

use Box\InjectionAwareInterface;

class ServiceInvoiceItem implements InjectionAwareInterface
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

    public function markAsPaid(\Model_InvoiceItem $item, $charge = true)
    {
        if ($charge && !$item->charged) {
            $this->creditInvoiceItem($item);
            $item->charged = true;
            $item->updated_at = date('Y-m-d H:i:s');
        }

        $item->status = \Model_InvoiceItem::STATUS_PENDING_SETUP;
        $item->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($item);

        $oid = $this->getOrderId($item);
        if (null !== $oid) {
            $orderService = $this->di['mod_service']('Order');
            $order = $this->di['db']->load('ClientOrder', $oid);
            if ($order instanceof \Model_ClientOrder) {
                $orderService->unsetUnpaidInvoice($order);
            }
        }
    }

    public function executeTask(\Model_InvoiceItem $item)
    {
        if (\Model_InvoiceItem::STATUS_EXECUTED == $item->status) {
            return true;
        }

        if (\Model_InvoiceItem::TYPE_ORDER == $item->type) {
            $order_id = $this->getOrderId($item);
            $order = $this->di['db']->load('ClientOrder', $order_id);
            if (!$order instanceof \Model_ClientOrder) {
                throw new \Box_Exception('Could not activate proforma item. Order :id not found', [':id' => $order_id]);
            }
            $orderService = $this->di['mod_service']('Order');
            switch ($item->task) {
                case \Model_InvoiceItem::TASK_ACTIVATE:
                    $product = $this->di['db']->getExistingModelById('Product', $order->product_id);
                    if (\Model_Product::SETUP_AFTER_PAYMENT == $product->setup) {
                        try {
                            $orderService->activateOrder($order);
                        } catch (\Exception $e) {
                            error_log($e->getMessage());
                            $orderService->saveStatusChange($order, 'Order could not be activated due to error: '.$e->getMessage());
                        }
                    }
                    break;

                case \Model_InvoiceItem::TASK_RENEW:
                    try {
                        // Unsuspend order if suspended before renew
                        if (\Model_ClientOrder::STATUS_SUSPENDED == $order->status) {
                            $orderService->unsuspendFromOrder($order);
                        }

                        $order = $this->di['db']->load('ClientOrder', $order_id);
                        $orderService->renewOrder($order);
                    } catch (\Exception $e) {
                        error_log($e->getMessage());
                        $orderService->saveStatusChange($order, 'Order could not renew due to error: '.$e->getMessage());
                    }
                    break;

                default:
                    // do nothing for unregistered tasks
                    break;
            }

            $this->markAsExecuted($item);
        }

        if (\Model_InvoiceItem::TYPE_HOOK_CALL == $item->type) {
            try {
                $params = json_decode($item->rel_id);
                $this->di['events_manager']->fire(['event' => $item->task, 'params' => $params]);
            } catch (\Exception $e) {
                error_log($e->getMessage());
            }
            $this->markAsExecuted($item);
        }

        if (\Model_InvoiceItem::TYPE_DEPOSIT == $item->type) {
            $clientService = $this->di['mod_service']('Client');

            $invoice = $this->di['db']->getExistingModelById('Invoice', $item->invoice_id);
            $client = $this->di['db']->getExistingModelById('Client', $invoice->client_id);
            $data = [
                'type' => 'invoice',
                'rel_id' => $item->invoice_id,
            ];
            $clientService->addFunds($client, $this->getTotal($item), $item->title, $data);

            $this->markAsExecuted($item);
        }

        if (\Model_InvoiceItem::TYPE_CUSTOM == $item->type) {
            // @todo ?
            $this->markAsExecuted($item);
        }
    }

    public function addNew(\Model_Invoice $proforma, array $data)
    {
        $title = $this->di['array_get']($data, 'title', '');
        if (empty($title)) {
            throw new \Box_Exception('Invoice item title is missing');
        }

        $period = $this->di['array_get']($data, 'period', 0);
        if ($period) {
            $periodCheck = $this->di['period']($period);
        }

        $type = $this->di['array_get']($data, 'type', \Model_InvoiceItem::TYPE_CUSTOM);
        $rel_id = $this->di['array_get']($data, 'rel_id');
        $task = $this->di['array_get']($data, 'task', \Model_InvoiceItem::TASK_VOID);
        $status = $this->di['array_get']($data, 'status', \Model_InvoiceItem::STATUS_PENDING_PAYMENT);

        $pi = $this->di['db']->dispense('InvoiceItem');
        $pi->invoice_id = $proforma->id;
        $pi->type = $type;
        $pi->rel_id = $rel_id;
        $pi->task = $task;
        $pi->status = $status;
        $pi->title = $data['title'];
        $pi->period = $period;
        $pi->quantity = $this->di['array_get']($data, 'quantity', 1);
        $pi->unit = $this->di['array_get']($data, 'unit');
        $pi->charged = $this->di['array_get']($data, 'charged', 0);
        $pi->price = (float) $this->di['array_get']($data, 'price', 0);
        $pi->taxed = $this->di['array_get']($data, 'taxed', false);
        $pi->created_at = date('Y-m-d H:i:s');
        $pi->updated_at = date('Y-m-d H:i:s');
        $itemId = $this->di['db']->store($pi);

        return (int) $itemId;
    }

    public function getTotal(\Model_InvoiceItem $item)
    {
        return floatval($item->price * $item->quantity);
    }

    public function getTax(\Model_InvoiceItem $item)
    {
        if (!$item->taxed) {
            return 0;
        }

        $rate = $this->di['db']->getCell('SELECT taxrate FROM invoice WHERE id = :id', ['id' => $item->invoice_id]);
        if ($rate <= 0) {
            return 0;
        }

        return round(($item->price * $rate / 100), 2);
    }

    public function update(\Model_InvoiceItem $item, array $data)
    {
        $item->title = $this->di['array_get']($data, 'title', $item->title);
        $item->price = $this->di['array_get']($data, 'price', $item->price);

        $item_quantity = $this->di['array_get']($data, 'quantity', 1);

        if ($item_quantity != $item->quantity) {
            $item->quantity = $item_quantity > 0 ? $item_quantity : 1;
        }

        if (isset($data['taxed']) && !empty($data['taxed'])) {
            $item->taxed = (bool) $data['taxed'];
        } else {
            $item->taxed = false;
        }

        $item->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($item);
    }

    public function remove(\Model_InvoiceItem $model)
    {
        $id = $model->id;
        $this->di['db']->trash($model);
        $this->di['logger']->info('Removed invoice item "%s"', $id);

        return true;
    }

    public function generateForAddFunds(\Model_Invoice $proforma, $amount)
    {
        $pi = $this->di['db']->dispense('InvoiceItem');
        $pi->invoice_id = $proforma->id;
        $pi->type = \Model_InvoiceItem::TYPE_DEPOSIT;
        $pi->rel_id = null;
        $pi->task = \Model_InvoiceItem::TASK_VOID;
        $pi->status = \Model_InvoiceItem::STATUS_PENDING_PAYMENT;
        $pi->title = __('Add funds to account');
        $pi->period = null;
        $pi->quantity = 1;
        $pi->unit = null;
        $pi->charged = 1;
        $pi->price = $amount;
        $pi->taxed = false;
        $pi->created_at = date('Y-m-d H:i:s');
        $pi->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($pi);
    }

    public function creditInvoiceItem(\Model_InvoiceItem $item)
    {
        $total = $this->getTotalWithTax($item);
        if ($total <= 0) {
            return true;
        }

        $invoice = $this->di['db']->getExistingModelById('Invoice', $item->invoice_id, 'Invoice not found');
        $client = $this->di['db']->getExistingModelById('Client', $invoice->client_id, 'Client not found');

        $credit = $this->di['db']->dispense('ClientBalance');
        $credit->client_id = $client->id;
        $credit->type = 'invoice';
        $credit->rel_id = $invoice->id;
        $credit->description = $item->title;
        $credit->amount = -$total;
        $credit->created_at = date('Y-m-d H:i:s');
        $credit->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($credit);

        $invoiceService = $this->di['mod_service']('Invoice');
        $invoiceService->addNote($invoice, sprintf('Charged clients balance with %s %s for %s', $total, $invoice->currency, $item->title));
    }

    public function getTotalWithTax(\Model_InvoiceItem $item)
    {
        return $this->getTotal($item) + $this->getTax($item) * $item->quantity;
    }

    public function getOrderId(\Model_InvoiceItem $item)
    {
        if (\Model_InvoiceItem::TYPE_ORDER == $item->type) {
            return (int) $item->rel_id;
        }

        return 0;
    }

    protected function markAsExecuted(\Model_InvoiceItem $item)
    {
        $item->status = \Model_InvoiceItem::STATUS_EXECUTED;
        $item->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($item);
    }

    public function generateFromOrder(\Model_Invoice $proforma, \Model_ClientOrder $order, $task, $price)
    {
        $corderService = $this->di['mod_service']('Order');

        $clientService = $this->di['mod_service']('client');
        $client = $this->di['db']->load('Client', $order->client_id);
        $taxed = $clientService->isClientTaxable($client);

        $pi = $this->di['db']->dispense('InvoiceItem');
        $pi->invoice_id = $proforma->id;
        $pi->type = \Model_InvoiceItem::TYPE_ORDER;
        $pi->rel_id = $order->id;
        $pi->task = $task;
        $pi->status = \Model_InvoiceItem::STATUS_PENDING_PAYMENT;
        $pi->title = $order->title;
        $pi->period = $order->period;
        $pi->quantity = $order->quantity;
        $pi->unit = $order->unit;
        $pi->price = $price;
        $pi->taxed = $taxed;
        $pi->created_at = date('Y-m-d H:i:s');
        $pi->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($pi);

        $corderService->setUnpaidInvoice($order, $proforma);

        // apply discount for new invoice if promo code is recurrent
        if ($order->promo_recurring) {
            $order_total = $order->price * $order->quantity;
            $promo_discount = $order->discount;
            if ($promo_discount > $order_total) {
                $promo_discount = $order_total;
            }

            $discount_title = $this->_getTitleForPromoDiscount($order->promo_id, $order->currency);

            $pd = [
                'title' => $discount_title,
                'price' => $promo_discount * -1,
                'quantity' => 1,
                'unit' => 'discount',
                'rel_id' => $order->id,
                'taxed' => $taxed,
            ];

            $this->addNew($proforma, $pd);
            ++$order->promo_used;
            $this->di['db']->store($order);
        }
    }

    private function _getTitleForPromoDiscount($promo_id, $currency)
    {
        $promo = $this->di['db']->findOne('Promo', 'id = ?', [$promo_id]);

        $api_guest = $this->di['api_guest'];

        switch ($promo->type) {
            case \Model_Promo::ABSOLUTE:
                    $currencyAmount = $api_guest->currency_format(['code' => $currency, 'price' => $promo->value]);

                    return __('Promotional Code: :code - :value Discount', [':code' => $promo->code, ':value' => $currencyAmount]);

            case \Model_Promo::PERCENTAGE:
                    return __('Promotional Code: :code - :value%', [':code' => $promo->code, ':value' => $promo->value]);

            default:
                break;
        }
    }

    /**
     * Get list of paid invoice not executed invoice items.
     *
     * @return array - array of Model_InvoiceItem items
     */
    public function getAllNotExecutePaidItems()
    {
        $sql = 'SELECT invoice_item.*
                FROM invoice_item
                  left join invoice on invoice_item.invoice_id = invoice.id
                WHERE invoice_item.status != :item_status and invoice.status = :invoice_status';
        $bindings = [
            ':item_status' => \Model_InvoiceItem::STATUS_EXECUTED,
            ':invoice_status' => \Model_Invoice::STATUS_PAID,
        ];

        return $this->di['db']->getAll($sql, $bindings);
    }
}
