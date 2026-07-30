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

use Box\Mod\Client\Entity\ClientBalance;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Invoice\Repository\InvoiceItemRepository;
use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\Validation\PriceValidator;

class ServiceInvoiceItem implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    protected InvoiceItemRepository $invoiceItemRepository;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
        $this->invoiceItemRepository = $di['em']->getRepository(InvoiceItem::class);
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getInvoiceItemRepository(): InvoiceItemRepository
    {
        return $this->invoiceItemRepository;
    }

    public function markAsPaid(InvoiceItem $item, $charge = true): void
    {
        if ($charge && !$item->getCharged()) {
            $em = $this->di['em'];
            $em->wrapInTransaction(function () use ($item, $em): void {
                $this->persistCredit($item);
                $item->setCharged(true);
                $item->setStatus(InvoiceItem::STATUS_PENDING_SETUP);
                $em->persist($item);
            });

            $this->addChargeNote($item);
        } else {
            $item->setStatus(InvoiceItem::STATUS_PENDING_SETUP);
            $this->di['em']->persist($item);
            $this->di['em']->flush();
        }

        $oid = $this->getOrderId($item);
        $orderService = $this->di['mod_service']('Order');
        $order = $this->di['db']->load('ClientOrder', $oid);
        if ($order instanceof \Model_ClientOrder) {
            $orderService->unsetUnpaidInvoice($order);
        }
    }

    public function executeTask(InvoiceItem $item)
    {
        if ($item->getStatus() == InvoiceItem::STATUS_EXECUTED) {
            return true;
        }

        if ($item->getType() == InvoiceItem::TYPE_ORDER) {
            $order_id = $this->getOrderId($item);
            $order = $this->di['db']->load('ClientOrder', $order_id);
            if (!$order instanceof \Model_ClientOrder) {
                throw new \FOSSBilling\Exception('Could not activate proforma item. Order :id not found', [':id' => $order_id]);
            }
            $orderService = $this->di['mod_service']('Order');
            switch ($item->getTask()) {
                case InvoiceItem::TASK_ACTIVATE:
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

                case InvoiceItem::TASK_RENEW:
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

        if ($item->getType() == InvoiceItem::TYPE_HOOK_CALL) {
            try {
                $params = json_decode($item->getRelId() ?? '');
                $this->di['events_manager']->fire(['event' => $item->getTask(), 'params' => $params]);
            } catch (\Exception $e) {
                error_log($e->getMessage());
            }
            $this->markAsExecuted($item);
        }

        if ($item->getType() == InvoiceItem::TYPE_DEPOSIT) {
            // do not request to add funds to client balance
            // associated invoice will have already been marked with a valid transaction and funds added
            $this->markAsExecuted($item);
        }

        if ($item->getType() == InvoiceItem::TYPE_CUSTOM) {
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

        $pi = new InvoiceItem();
        $pi->setInvoiceId((int) $proforma->id);
        $pi->setType($data['type'] ?? InvoiceItem::TYPE_CUSTOM);
        $pi->setRelId($data['rel_id'] ?? null);
        $pi->setTask($data['task'] ?? InvoiceItem::TASK_VOID);
        $pi->setStatus($data['status'] ?? InvoiceItem::STATUS_PENDING_PAYMENT);
        $pi->setTitle($data['title']);
        $pi->setPeriod($period);
        $pi->setQuantity(PriceValidator::validateQuantity($data['quantity'] ?? 1));
        $pi->setUnit($data['unit'] ?? null);
        $pi->setCharged($data['charged'] ?? 0);
        $pi->setPrice(PriceValidator::validateSignedAmount($data['price'] ?? 0));
        $pi->setTaxed($data['taxed'] ?? false);
        $this->di['em']->persist($pi);
        $this->di['em']->flush();

        return (int) $pi->getId();
    }

    private function normalizePeriod(mixed $period): ?string
    {
        if ($period === null || $period === '' || $period === 0 || $period === '0') {
            return null;
        }

        return (string) $period;
    }

    public function getTotal(InvoiceItem $item): float
    {
        return floatval(($item->getPrice() ?? 0) * ($item->getQuantity() ?? 1));
    }

    public function getTax(InvoiceItem $item)
    {
        if (!$item->getTaxed()) {
            return 0;
        }

        $rate = $this->di['db']->getCell('SELECT taxrate FROM invoice WHERE id = :id', ['id' => $item->getInvoiceId()]);
        if ($rate <= 0) {
            return 0;
        }

        return round($item->getPrice() * $rate / 100, 2);
    }

    public function update(InvoiceItem $item, array $data): void
    {
        $item->setTitle($data['title'] ?? $item->getTitle());
        if (isset($data['price'])) {
            $item->setPrice(PriceValidator::validateSignedAmount($data['price']));
        }

        if (array_key_exists('quantity', $data)) {
            $item->setQuantity(PriceValidator::validateQuantity($data['quantity']));
        }

        if (isset($data['taxed']) && !empty($data['taxed'])) {
            $item->setTaxed((bool) $data['taxed']);
        } else {
            $item->setTaxed(false);
        }

        $this->di['em']->persist($item);
        $this->di['em']->flush();
    }

    public function remove(InvoiceItem $model): bool
    {
        $id = $model->getId();
        $this->di['em']->remove($model);
        $this->di['em']->flush();
        $this->di['logger']->info('Removed invoice item "%s"', $id);

        return true;
    }

    public function generateForAddFunds(\Model_Invoice $proforma, $amount): void
    {
        $pi = new InvoiceItem();
        $pi->setInvoiceId((int) $proforma->id);
        $pi->setType(InvoiceItem::TYPE_DEPOSIT);
        $pi->setRelId(null);
        $pi->setTask(InvoiceItem::TASK_VOID);
        $pi->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT);
        $pi->setTitle(__trans('Add funds to account'));
        $pi->setPeriod(null);
        $pi->setQuantity(1);
        $pi->setUnit(null);
        $pi->setCharged(1);
        $pi->setPrice(PriceValidator::validateSignedAmount($amount));
        $pi->setTaxed(false);
        $this->di['em']->persist($pi);
        $this->di['em']->flush();
    }

    public function creditInvoiceItem(InvoiceItem $item): void
    {
        $this->persistCredit($item);
        $this->di['em']->flush();
        $this->addChargeNote($item);
    }

    private function persistCredit(InvoiceItem $item): ClientBalance
    {
        $total = $this->getTotalWithTax($item);

        $invoice = $this->di['db']->getExistingModelById('Invoice', $item->getInvoiceId(), 'Invoice not found');
        $client = $this->di['db']->getExistingModelById('Client', $invoice->client_id, 'Client not found');

        $credit = new ClientBalance();
        $credit->setClientId((int) $client->id);
        $credit->setType('invoice');
        $credit->setRelId((string) $invoice->id);
        $credit->setDescription($item->getTitle());
        $credit->setAmount((string) (-$total));
        $this->di['em']->persist($credit);

        return $credit;
    }

    private function addChargeNote(InvoiceItem $item): void
    {
        $invoice = $this->di['db']->getExistingModelById('Invoice', $item->getInvoiceId(), 'Invoice not found');
        $total = $this->getTotalWithTax($item);
        $invoiceService = $this->di['mod_service']('Invoice');
        $invoiceService->addNote($invoice, sprintf('Charged clients balance with %s %s for %s', $total, $invoice->currency, $item->getTitle()));
    }

    public function getTotalWithTax(InvoiceItem $item): float
    {
        return $this->getTotal($item) + $this->getTax($item) * ($item->getQuantity() ?? 1);
    }

    public function getOrderId(InvoiceItem $item): int
    {
        if ($item->getType() == InvoiceItem::TYPE_ORDER) {
            return (int) $item->getRelId();
        }

        return 0;
    }

    protected function markAsExecuted(InvoiceItem $item)
    {
        $item->setStatus(InvoiceItem::STATUS_EXECUTED);
        $this->di['em']->persist($item);
        $this->di['em']->flush();
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

        $pi = new InvoiceItem();
        $pi->setInvoiceId((int) $proforma->id);
        $pi->setType(InvoiceItem::TYPE_ORDER);
        $pi->setRelId((string) $order->id);
        $pi->setTask($task);
        $pi->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT);
        $pi->setTitle($order->title);
        $pi->setPeriod($period);
        $pi->setQuantity(PriceValidator::validateQuantity($quantity));
        $pi->setUnit($unit);
        $pi->setPrice(PriceValidator::validateSignedAmount($price));
        $pi->setTaxed($taxed);
        $this->di['em']->persist($pi);
        $this->di['em']->flush();

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
     * @return array - array of raw invoice_item rows
     */
    public function getAllNotExecutePaidItems()
    {
        $sql = 'SELECT invoice_item.*
                FROM invoice_item
                  left join invoice on invoice_item.invoice_id = invoice.id
                WHERE invoice_item.status != :item_status and invoice.status = :invoice_status
                AND (invoice.paid_at IS NULL OR invoice.paid_at <= :cutoff_time)';
        $bindings = [
            ':item_status' => InvoiceItem::STATUS_EXECUTED,
            ':invoice_status' => \Model_Invoice::STATUS_PAID,
            ':cutoff_time' => date('Y-m-d H:i:s', strtotime('-10 minutes')),
        ];

        return $this->di['db']->getAll($sql, $bindings);
    }
}
