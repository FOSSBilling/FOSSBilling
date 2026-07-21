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

use Box\Mod\Client\Entity\Client as ClientEntity;
use Box\Mod\Client\Entity\ClientBalance;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Invoice\Repository\InvoiceItemRepository;
use Box\Mod\Order\Entity\Order as OrderEntity;
use FOSSBilling\InformationException;
use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\Validation\PriceValidator;

class ServiceInvoiceItem implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;
    private ?InvoiceItemRepository $invoiceItemRepository = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function markAsPaid(InvoiceItem $item, bool $charge = true): void
    {
        $itemId = $item instanceof InvoiceItem ? $item->getId() : $item->id;

        if ($charge && !$item->isCharged()) {
            $this->creditInvoiceItem($item);
            $item->charged = true;
            $item->updated_at = date('Y-m-d H:i:s');
        }

        $item->status = InvoiceItem::STATUS_PENDING_SETUP;
        $item->updated_at = date('Y-m-d H:i:s');
        $this->di['em']->persist($item);
        $this->di['em']->flush();

        $oid = $this->getOrderId($item);
        $orderService = $this->di['mod_service']('Order');
        $order = $this->di['em']->getRepository(OrderEntity::class)->find($oid);
        if ($order instanceof OrderEntity || $order instanceof OrderEntity) {
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
            $order = $this->di['em']->getRepository(OrderEntity::class)->find($order_id);
            if (!$order instanceof OrderEntity && !$order instanceof OrderEntity) {
                throw new \FOSSBilling\Exception('Could not activate proforma item. Order :id not found', [':id' => $order_id]);
            }
            $orderService = $this->di['mod_service']('Order');
            switch ($item->getTask()) {
                case InvoiceItem::TASK_ACTIVATE:
                    $productId = $order instanceof OrderEntity ? (int) $order->getProductId() : (int) $order->product_id;
                    $product = $this->di['mod_service']('Product')->findProductById($productId);
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
                        $status = $order instanceof OrderEntity ? $order->getStatus() : $order->status;
                        if ($status == OrderEntity::STATUS_SUSPENDED) {
                            $orderService->unsuspendFromOrder($order);
                        }

                        $order = $this->di['em']->getRepository(OrderEntity::class)->find($order_id);
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

    public function addNew(Invoice $proforma, array $data): int
    {
        $invoiceId = $proforma instanceof Invoice ? $proforma->getId() : $proforma->id;

        $title = $data['title'] ?? '';
        if (empty($title)) {
            throw new InformationException('Invoice item title is missing');
        }

        $period = $this->normalizePeriod($data['period'] ?? null);
        if ($period !== null) {
            $period = $this->di['period']($period)->getCode();
        }

        $type = $data['type'] ?? InvoiceItem::TYPE_CUSTOM;
        $rel_id = $data['rel_id'] ?? null;
        $task = $data['task'] ?? InvoiceItem::TASK_VOID;
        $status = $data['status'] ?? InvoiceItem::STATUS_PENDING_PAYMENT;

        $pi = new InvoiceItem();
        $pi->setInvoiceId($invoiceId);
        $pi->setType($type);
        $pi->setRelId($rel_id !== null ? (string) $rel_id : null);
        $pi->setTask($task);
        $pi->setStatus($status);
        $pi->setTitle($data['title']);
        $pi->setPeriod($period);
        $pi->setQuantity(PriceValidator::validateQuantity($data['quantity'] ?? 1));
        $pi->setUnit($data['unit'] ?? null);
        $pi->setCharged((bool) ($data['charged'] ?? 0));
        $pi->setPrice(PriceValidator::validateSignedAmount($data['price'] ?? 0));
        $pi->setTaxed((bool) ($data['taxed'] ?? false));
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
        return floatval($item->getPrice() * $item->getQuantity());
    }

    public function getTax(InvoiceItem $item)
    {
        $taxed = $item instanceof InvoiceItem ? $item->isTaxed() : $item->taxed;
        if (!$taxed) {
            return 0;
        }

        $invoiceId = $item instanceof InvoiceItem ? $item->getInvoiceId() : $item->invoice_id;
        $rate = $this->di['dbal']->fetchOne('SELECT taxrate FROM invoice WHERE id = :id', ['id' => $invoiceId]);
        if ($rate <= 0) {
            return 0;
        }

        $price = $item instanceof InvoiceItem ? $item->getPrice() : $item->price;

        return round($price * $rate / 100, 2);
    }

    public function update(InvoiceItem $item, array $data): void
    {
        $item->title = $data['title'] ?? $item->getTitle();
        if (isset($data['price'])) {
            $item->price = PriceValidator::validateSignedAmount($data['price']);
        }

        if (array_key_exists('quantity', $data)) {
            $item->quantity = PriceValidator::validateQuantity($data['quantity']);
        }

        if (isset($data['taxed']) && !empty($data['taxed'])) {
            $item->taxed = (bool) $data['taxed'];
        } else {
            $item->taxed = false;
        }

        $item->updated_at = date('Y-m-d H:i:s');
        $this->di['em']->persist($item);
        $this->di['em']->flush();
    }

    public function remove(InvoiceItem $model): bool
    {
        $id = $model instanceof InvoiceItem ? $model->getId() : $model->id;
        $this->di['em']->remove($model);
        $this->di['em']->flush();
        $this->di['logger']->info('Removed invoice item "%s"', $id);

        return true;
    }

    public function generateForAddFunds(Invoice $proforma, float $amount): void
    {
        $invoiceId = $proforma instanceof Invoice ? $proforma->getId() : $proforma->id;

        $pi = new InvoiceItem();
        $pi->setInvoiceId($invoiceId);
        $pi->setType(InvoiceItem::TYPE_DEPOSIT);
        $pi->setRelId(null);
        $pi->setTask(InvoiceItem::TASK_VOID);
        $pi->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT);
        $pi->setTitle(__trans('Add funds to account'));
        $pi->setPeriod(null);
        $pi->setQuantity(1);
        $pi->setUnit(null);
        $pi->setCharged(true);
        $pi->setPrice($amount);
        $pi->setTaxed(false);
        $this->di['em']->persist($pi);
        $this->di['em']->flush();
    }

    public function creditInvoiceItem(InvoiceItem $item): void
    {
        $total = $this->getTotalWithTax($item);

        $invoiceId = $item instanceof InvoiceItem ? $item->getInvoiceId() : $item->invoice_id;
        $invoice = $this->di['em']->getRepository(Invoice::class)->find($invoiceId)
            ?? throw new InformationException('Invoice not found');
        $client = $this->di['em']->getRepository(ClientEntity::class)->find($invoice->getClientId())
            ?? throw new InformationException('Client not found');

        $credit = new ClientBalance();
        $credit->setClientId($client->getId());
        $credit->setType('invoice');
        $credit->setRelId((string) $invoice->getId());
        $credit->setDescription($item instanceof InvoiceItem ? $item->getTitle() : $item->title);
        $credit->setAmount((string) (-$total));
        $this->di['em']->persist($credit);
        $this->di['em']->flush();

        $invoiceService = $this->di['mod_service']('Invoice');
        $invoiceService->addNote($invoice, sprintf('Charged clients balance with %s %s for %s', $total, $invoice->getCurrency(), $item instanceof InvoiceItem ? $item->getTitle() : $item->title));
    }

    public function getTotalWithTax(InvoiceItem $item): float
    {
        return $this->getTotal($item) + $this->getTax($item) * $item->getQuantity();
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
        $item->status = InvoiceItem::STATUS_EXECUTED;
        $item->updated_at = date('Y-m-d H:i:s');
        $this->di['em']->persist($item);
        $this->di['em']->flush();
    }

    public function generateFromOrder(Invoice $proforma, OrderEntity $order, $task, $price, array $line = []): void
    {
        $invoiceId = $proforma instanceof Invoice ? $proforma->getId() : $proforma->id;

        $corderService = $this->di['mod_service']('Order');

        $clientService = $this->di['mod_service']('client');
        $orderClientId = $order instanceof OrderEntity ? $order->getClientId() : $order->client_id;
        $client = $this->di['em']->getRepository(ClientEntity::class)->find($orderClientId);
        $taxed = $clientService->isClientTaxable($client);
        $quantity = $line['quantity'] ?? ($order instanceof OrderEntity ? $order->getQuantity() : $order->quantity);
        $unit = $line['unit'] ?? ($order instanceof OrderEntity ? $order->getUnit() : $order->unit);
        $period = $this->normalizePeriod($line['period'] ?? ($order instanceof OrderEntity ? $order->getPeriod() : $order->period));
        if ($period !== null) {
            $period = $this->di['period']($period)->getCode();
        }

        $pi = new InvoiceItem();
        $pi->setInvoiceId($invoiceId);
        $pi->setType(InvoiceItem::TYPE_ORDER);
        $pi->setRelId((string) ($order instanceof OrderEntity ? $order->getId() : $order->id));
        $pi->setTask($task);
        $pi->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT);
        $pi->setTitle($order instanceof OrderEntity ? $order->getTitle() : $order->title);
        $pi->setPeriod($period);
        $pi->setQuantity($quantity);
        $pi->setUnit($unit);
        $pi->setPrice($price);
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
                'rel_id' => $order->getId(),
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
                $proforma->getCreatedAt() ?? date('Y-m-d H:i:s'),
                \Box\Mod\Product\Entity\PromoRedemption::STATUS_RESERVED,
            );
        }
    }

    /**
     * Get list of paid invoice not executed invoice items.
     *
     * @return array - array of InvoiceItem items
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
            ':invoice_status' => Invoice::STATUS_PAID,
            ':cutoff_time' => date('Y-m-d H:i:s', strtotime('-10 minutes')),
        ];

        return $this->di['dbal']->fetchAllAssociative($sql, $bindings);
    }

    private function getInvoiceItemRepository(): InvoiceItemRepository
    {
        if ($this->invoiceItemRepository === null) {
            $this->invoiceItemRepository = $this->di['em']->getRepository(InvoiceItem::class);
        }

        return $this->invoiceItemRepository;
    }
}
