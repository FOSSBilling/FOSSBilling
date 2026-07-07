<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Invoice;

use FOSSBilling\InjectionAwareInterface;

class ServiceInvoiceItem implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function markAsPaid(\Model_InvoiceItem $item, $charge = true): void
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
        $orderService = $this->di['mod_service']('Order');
        $order = $this->di['db']->load('ClientOrder', $oid);
        if ($order instanceof \Model_ClientOrder) {
            $orderService->unsetUnpaidInvoice($order);
        }
    }

    public function executeTask(\Model_InvoiceItem $item)
    {
        if ($item->status == \Model_InvoiceItem::STATUS_EXECUTED) {
            return true;
        }

        if ($item->type == \Model_InvoiceItem::TYPE_ORDER) {
            $order_id = $this->getOrderId($item);
            $order = $this->di['db']->load('ClientOrder', $order_id);
            if (!$order instanceof \Model_ClientOrder) {
                throw new \FOSSBilling\Exception('Could not activate proforma item. Order :id not found', [':id' => $order_id]);
            }
            $orderService = $this->di['mod_service']('Order');
            switch ($item->task) {
                case \Model_InvoiceItem::TASK_ACTIVATE:
                    $product = $this->di['mod_service']('Product')->findProductById((int) $order->product_id);
                    if ($product->getSetup() == \Box\Mod\Product\Service::SETUP_AFTER_PAYMENT) {
                        try {
                            $orderService->activateOrder($order);
                        } catch (\Exception $e) {
                            error_log($e->getMessage());
                            $orderService->saveStatusChange($order, "Order could not be activated due to error: {$e->getMessage()}.");
                        }
                    }

                    break;

                case \Model_InvoiceItem::TASK_RENEW:
                    try {
                        // Unsuspend order if suspended before renew
                        if ($order->status == \Model_ClientOrder::STATUS_SUSPENDED) {
                            $orderService->unsuspendFromOrder($order);
                        }

                        $order = $this->di['db']->load('ClientOrder', $order_id);
                        $orderService->renewOrder($order);
                    } catch (\Exception $e) {
                        error_log($e->getMessage());
                        $orderService->saveStatusChange($order, "Order could not renew due to error: {$e->getMessage()}.");
                    }

                    break;

                default:
                    // do nothing for unregistered tasks
                    break;
            }

            $this->markAsExecuted($item);
        }

        if ($item->type == \Model_InvoiceItem::TYPE_HOOK_CALL) {
            try {
                $params = json_decode($item->rel_id ?? '');
                $this->di['events_manager']->fire(['event' => $item->task, 'params' => $params]);
            } catch (\Exception $e) {
                error_log($e->getMessage());
            }
            $this->markAsExecuted($item);
        }

        if ($item->type == \Model_InvoiceItem::TYPE_DEPOSIT) {
            // do not request to add funds to client balance
            // associated invoice will have already been marked with a valid transaction and funds added
            $this->markAsExecuted($item);
        }

        if ($item->type == \Model_InvoiceItem::TYPE_CUSTOM) {
            // @todo ?
            $this->markAsExecuted($item);
        }
    }

    public function addNew(\Model_Invoice $proforma, array $data): int
    {
        $title = $data['title'] ?? '';
        if (empty($title)) {
            throw new \FOSSBilling\InformationException('Invoice item title is missing');
        }

        $period = $this->normalizePeriod($data['period'] ?? null);
        if ($period !== null) {
            $period = $this->di['period']($period)->getCode();
        }

        $type = $data['type'] ?? \Model_InvoiceItem::TYPE_CUSTOM;
        $rel_id = $data['rel_id'] ?? null;
        $task = $data['task'] ?? \Model_InvoiceItem::TASK_VOID;
        $status = $data['status'] ?? \Model_InvoiceItem::STATUS_PENDING_PAYMENT;

        $pi = $this->di['db']->dispense('InvoiceItem');
        $pi->invoice_id = $proforma->id;
        $pi->type = $type;
        $pi->rel_id = $rel_id;
        $pi->task = $task;
        $pi->status = $status;
        $pi->title = $data['title'];
        $pi->period = $period;
        $pi->quantity = $data['quantity'] ?? 1;
        $pi->unit = $data['unit'] ?? null;
        $pi->charged = $data['charged'] ?? 0;
        $pi->price = (float) ($data['price'] ?? 0);
        $pi->taxed = $data['taxed'] ?? false;
        $pi->created_at = date('Y-m-d H:i:s');
        $pi->updated_at = date('Y-m-d H:i:s');
        $itemId = $this->di['db']->store($pi);

        return (int) $itemId;
    }

    private function normalizePeriod(mixed $period): ?string
    {
        if ($period === null || $period === '' || $period === 0 || $period === '0') {
            return null;
        }

        return (string) $period;
    }

    public function getTotal(\Model_InvoiceItem $item): float
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

        return round($item->price * $rate / 100, 2);
    }

    public function update(\Model_InvoiceItem $item, array $data): void
    {
        $item->title = $data['title'] ?? $item->title;
        $item->price = $data['price'] ?? $item->price;

        $item_quantity = $data['quantity'] ?? 1;

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

    public function remove(\Model_InvoiceItem $model): bool
    {
        $id = $model->id;
        $this->di['db']->trash($model);
        $this->di['logger']->info('Removed invoice item "%s"', $id);

        return true;
    }

    public function generateForAddFunds(\Model_Invoice $proforma, $amount): void
    {
        $pi = $this->di['db']->dispense('InvoiceItem');
        $pi->invoice_id = $proforma->id;
        $pi->type = \Model_InvoiceItem::TYPE_DEPOSIT;
        $pi->rel_id = null;
        $pi->task = \Model_InvoiceItem::TASK_VOID;
        $pi->status = \Model_InvoiceItem::STATUS_PENDING_PAYMENT;
        $pi->title = __trans('Add funds to account');
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

    public function creditInvoiceItem(\Model_InvoiceItem $item): void
    {
        $total = $this->getTotalWithTax($item);

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

    public function getTotalWithTax(\Model_InvoiceItem $item): float
    {
        return $this->getTotal($item) + $this->getTax($item) * $item->quantity;
    }

    public function getOrderId(\Model_InvoiceItem $item): int
    {
        if ($item->type == \Model_InvoiceItem::TYPE_ORDER) {
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

    public function generateFromOrder(\Model_Invoice $proforma, \Model_ClientOrder $order, $task, $price, array $line = []): void
    {
        $corderService = $this->di['mod_service']('Order');

        $clientService = $this->di['mod_service']('client');
        $client = $this->di['db']->load('Client', $order->client_id);
        $taxed = $clientService->isClientTaxable($client);
        $quantity = $line['quantity'] ?? $order->quantity;
        $unit = $line['unit'] ?? $order->unit;
        $period = $this->normalizePeriod($line['period'] ?? $order->period);
        if ($period !== null) {
            $period = $this->di['period']($period)->getCode();
        }

        $pi = $this->di['db']->dispense('InvoiceItem');
        $pi->invoice_id = $proforma->id;
        $pi->type = \Model_InvoiceItem::TYPE_ORDER;
        $pi->rel_id = $order->id;
        $pi->task = $task;
        $pi->status = \Model_InvoiceItem::STATUS_PENDING_PAYMENT;
        $pi->title = $order->title;
        $pi->period = $period;
        $pi->quantity = $quantity;
        $pi->unit = $unit;
        $pi->price = $price;
        $pi->taxed = $taxed;
        $pi->created_at = date('Y-m-d H:i:s');
        $pi->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($pi);

        $corderService->setUnpaidInvoice($order, $proforma);

        // apply discount for new invoice if promo code is recurrent
        $productService = $this->di['mod_service']('Product');
        $promoAdjustment = $productService->getRenewalPromoAdjustment($order, (float) $price, (float) $quantity);
        if ($promoAdjustment !== null) {
            $pd = [
                'title' => $promoAdjustment['title'],
                'price' => $promoAdjustment['discount_amount'] * -1,
                'quantity' => 1,
                'unit' => 'discount',
                'rel_id' => $order->id,
                'taxed' => $taxed,
            ];

            $this->addNew($proforma, $pd);
            $productService->createPromoRedemption(
                $promoAdjustment['promo'],
                $client,
                $order,
                $proforma,
                \Box\Mod\Product\Entity\PromoRedemption::PHASE_RENEWAL,
                $promoAdjustment['discount_amount'],
                $promoAdjustment['currency'],
                $proforma->created_at ?? date('Y-m-d H:i:s'),
                \Box\Mod\Product\Entity\PromoRedemption::STATUS_RESERVED,
            );
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
                WHERE invoice_item.status != :item_status and invoice.status = :invoice_status
                AND (invoice.paid_at IS NULL OR invoice.paid_at <= :cutoff_time)';
        $bindings = [
            ':item_status' => \Model_InvoiceItem::STATUS_EXECUTED,
            ':invoice_status' => \Model_Invoice::STATUS_PAID,
            ':cutoff_time' => date('Y-m-d H:i:s', strtotime('-10 minutes')),
        ];

        return $this->di['db']->getAll($sql, $bindings);
    }
}
