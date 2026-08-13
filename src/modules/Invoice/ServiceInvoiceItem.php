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

use Box\Mod\Client\Entity\Client;
use Box\Mod\Client\Entity\ClientBalance;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Invoice\Repository\InvoiceItemRepository;
use Box\Mod\Order\Entity\Order;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use FOSSBilling\Doctrine\EntityManagerFactory;
use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\Validation\PriceValidator;

class ServiceInvoiceItem implements InjectionAwareInterface
{
    public const int MAX_TASK_ATTEMPTS = 3;

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
            $invoice = $this->di['em']->getRepository(Invoice::class)->find($item->getInvoiceId());
            if ($invoice === null) {
                throw new \FOSSBilling\Exception('Invoice not found');
            }
            $total = $this->getTotalWithTax($item);
            $em = $this->di['em'];

            try {
                $em->wrapInTransaction(function () use ($item, $invoice, $total, $em): void {
                    $this->persistCredit($item, $invoice, $total);
                    $item->setCharged(true);
                    $item->setStatus(InvoiceItem::STATUS_PENDING_SETUP);
                    $em->persist($item);
                });
            } catch (UniqueConstraintViolationException) {
                // Idempotency: the unique constraint on invoice_item_id means a prior
                // attempt already credited this item. Mark it as charged without re-crediting.
                $this->di['logger']->setChannel('billing')->info(sprintf('Invoice item #%d was already credited; skipping duplicate credit.', (int) $item->getId()));
                $this->resetEntityManager();
                $item = $this->di['em']->find(InvoiceItem::class, $item->getId());
                $item->setCharged(true);
                $item->setStatus(InvoiceItem::STATUS_PENDING_SETUP);
                $this->di['em']->persist($item);
                $this->di['em']->flush();
            }

            $this->addChargeNote($item, $invoice, $total);
        } else {
            $item->setStatus(InvoiceItem::STATUS_PENDING_SETUP);
            $this->di['em']->persist($item);
            $this->di['em']->flush();
        }

        $oid = $this->getOrderId($item);
        $orderService = $this->di['mod_service']('Order');
        $order = $this->di['em']->getRepository(Order::class)->find($oid);
        if ($order instanceof Order) {
            $orderService->unsetUnpaidInvoice($order);
        }
    }

    public function executeTask(InvoiceItem $item)
    {
        if ($item->getStatus() == InvoiceItem::STATUS_EXECUTED) {
            return true;
        }

        if ($item->getType() == InvoiceItem::TYPE_ORDER) {
            $taskFailed = false;

            try {
                $order_id = $this->getOrderId($item);
                $order = $this->di['em']->getRepository(Order::class)->find($order_id);
                if (!$order instanceof Order) {
                    throw new \FOSSBilling\Exception('Could not activate proforma item. Order :id not found', [':id' => $order_id]);
                }
                $orderService = $this->di['mod_service']('Order');
                switch ($item->getTask()) {
                    case InvoiceItem::TASK_ACTIVATE:
                        $product = $this->di['mod_service']('Product')->findProductById((int) $order->getProductId());
                        if ($product->getSetup() == \Box\Mod\Product\Service::SETUP_AFTER_PAYMENT) {
                            try {
                                $orderService->activateOrder($order);
                            } catch (\Exception $e) {
                                error_log($e->getMessage());
                                $orderService->saveStatusChange($order, "Order could not be activated due to error: {$e->getMessage()}.");
                                $taskFailed = true;
                            }
                        }

                        break;

                    case InvoiceItem::TASK_RENEW:
                        try {
                            // Unsuspend order if suspended before renew
                            if ($order->getStatus() == Order::STATUS_SUSPENDED) {
                                $orderService->unsuspendFromOrder($order);
                            }

                            $order = $this->di['em']->getRepository(Order::class)->find($order_id);
                            $orderService->renewOrder($order);
                        } catch (\Exception $e) {
                            error_log($e->getMessage());
                            $orderService->saveStatusChange($order, "Order could not renew due to error: {$e->getMessage()}.");
                            $taskFailed = true;
                        }

                        break;

                    default:
                        // do nothing for unregistered tasks
                        break;
                }
            } catch (\Exception $e) {
                error_log($e->getMessage());
                $taskFailed = true;
            }

            if (!$taskFailed) {
                $this->markAsExecuted($item);
            } else {
                $this->recordTaskFailure($item);
            }
        }

        if ($item->getType() == InvoiceItem::TYPE_HOOK_CALL) {
            $taskFailed = false;

            try {
                $params = json_decode($item->getRelId() ?? '');
                $this->di['events_manager']->fire(['event' => $item->getTask(), 'params' => $params]);
            } catch (\Exception $e) {
                error_log($e->getMessage());
                $taskFailed = true;
            }
            if (!$taskFailed) {
                $this->markAsExecuted($item);
            } else {
                $this->recordTaskFailure($item);
            }
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
        $title = $data['title'] ?? '';
        if (empty($title)) {
            throw new \FOSSBilling\InformationException('Invoice item title is missing');
        }

        $period = $this->normalizePeriod($data['period'] ?? null);
        if ($period !== null) {
            $period = $this->di['period']($period)->getCode();
        }

        $pi = new InvoiceItem();
        $pi->setInvoiceId((int) $proforma->getId());
        $pi->setType($data['type'] ?? InvoiceItem::TYPE_CUSTOM);
        $pi->setRelId(isset($data['rel_id']) ? (string) $data['rel_id'] : null);
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

        $rate = $this->di['em']->getConnection()->fetchOne('SELECT taxrate FROM invoice WHERE id = :id', ['id' => $item->getInvoiceId()]);
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

    public function generateForAddFunds(Invoice $proforma, $amount): void
    {
        $pi = new InvoiceItem();
        $pi->setInvoiceId((int) $proforma->getId());
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
        $invoice = $this->di['em']->getRepository(Invoice::class)->find($item->getInvoiceId());
        if ($invoice === null) {
            throw new \FOSSBilling\Exception('Invoice not found');
        }
        $total = $this->getTotalWithTax($item);
        $this->persistCredit($item, $invoice, $total);

        // Idempotency: the unique constraint on invoice_item_id guarantees at most one
        // credit per item. A violation means a retry already credited it — treat as no-op.
        try {
            $this->di['em']->flush();
        } catch (UniqueConstraintViolationException) {
            $this->di['logger']->setChannel('billing')->info(sprintf('Invoice item #%d was already credited; skipping duplicate credit.', (int) $item->getId()));
            $this->resetEntityManager();

            return;
        }

        $this->addChargeNote($item, $invoice, $total);
    }

    protected function resetEntityManager(): void
    {
        unset($this->di['em']);
        $this->di['em'] = EntityManagerFactory::create();
    }

    private function persistCredit(InvoiceItem $item, Invoice $invoice, float $total): ClientBalance
    {
        $client = $this->di['em']->getRepository(Client::class)->find($invoice->getClientId())
            ?? throw new \FOSSBilling\Exception('Client not found');

        $credit = new ClientBalance();
        $credit->setClientId((int) $client->getId());
        $credit->setType('invoice');
        $credit->setRelId((string) $invoice->getId());
        $credit->setInvoiceItemId($item->getId());
        $credit->setDescription($item->getTitle());
        $credit->setAmount((string) (-$total));
        $this->di['em']->persist($credit);

        return $credit;
    }

    private function addChargeNote(InvoiceItem $item, Invoice $invoice, float $total): void
    {
        $invoiceService = $this->di['mod_service']('Invoice');
        $invoiceService->addNote($invoice, sprintf('Charged clients balance with %s %s for %s', $total, $invoice->getCurrency(), $item->getTitle()));
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

    protected function recordTaskFailure(InvoiceItem $item): void
    {
        $attempts = $item->getAttempts() + 1;
        $item->setAttempts($attempts);

        if ($attempts >= self::MAX_TASK_ATTEMPTS) {
            $item->setStatus(InvoiceItem::STATUS_FAILED);
            $this->di['logger']->setChannel('billing')->error(sprintf('Invoice item #%d marked as failed after %d task execution attempts.', (int) $item->getId(), $attempts));
        }

        $this->di['em']->persist($item);
        $this->di['em']->flush();
    }

    public function getFailedItems(): array
    {
        return $this->invoiceItemRepository->findFailed();
    }

    public function requeueItem(int $id): InvoiceItem
    {
        $item = $this->invoiceItemRepository->find($id);
        if (!$item instanceof InvoiceItem) {
            throw new \FOSSBilling\InformationException('Invoice item was not found');
        }

        if ($item->getStatus() !== InvoiceItem::STATUS_FAILED) {
            throw new \FOSSBilling\InformationException('Invoice item is not in a failed state');
        }

        $item->setStatus(InvoiceItem::STATUS_PENDING_SETUP);
        $item->setAttempts(0);
        $this->di['em']->persist($item);
        $this->di['em']->flush();
        $this->di['logger']->setChannel('billing')->info(sprintf('Invoice item #%d re-queued for execution by an admin.', (int) $item->getId()));

        return $item;
    }

    public function generateFromOrder(Invoice $proforma, Order $order, $task, $price, array $line = []): void
    {
        $corderService = $this->di['mod_service']('Order');

        $clientService = $this->di['mod_service']('client');
        $client = $this->di['em']->getRepository(Client::class)->find($order->getClientId());
        $taxed = $clientService->isClientTaxable($client);
        $quantity = $line['quantity'] ?? $order->getQuantity();
        $unit = $line['unit'] ?? $order->getUnit();
        $period = $this->normalizePeriod($line['period'] ?? $order->getPeriod());
        if ($period !== null) {
            $period = $this->di['period']($period)->getCode();
        }

        $pi = new InvoiceItem();
        $pi->setInvoiceId((int) $proforma->getId());
        $pi->setType(InvoiceItem::TYPE_ORDER);
        $pi->setRelId((string) $order->getId());
        $pi->setTask($task);
        $pi->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT);
        $pi->setTitle($order->getTitle());
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
                'rel_id' => (string) $order->getId(),
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
                $proforma->getCreatedAt()?->format('Y-m-d H:i:s') ?? date('Y-m-d H:i:s'),
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
                WHERE invoice_item.status NOT IN (:status_executed, :status_failed) and invoice.status = :invoice_status
                AND (invoice.paid_at IS NULL OR invoice.paid_at <= :cutoff_time)';
        $bindings = [
            'status_executed' => InvoiceItem::STATUS_EXECUTED,
            'status_failed' => InvoiceItem::STATUS_FAILED,
            'invoice_status' => Invoice::STATUS_PAID,
            'cutoff_time' => date('Y-m-d H:i:s', strtotime('-10 minutes')),
        ];

        return $this->di['em']->getConnection()->fetchAllAssociative($sql, $bindings);
    }
}
