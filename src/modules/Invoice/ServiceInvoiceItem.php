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
use Box\Mod\Order\Service as OrderService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use FOSSBilling\Core\Container\InjectionAwareInterface;
use FOSSBilling\Core\Doctrine\EntityManagerFactory;
use FOSSBilling\Core\Doctrine\NamedLock;
use FOSSBilling\Core\Validation\PriceValidator;

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

    private function runInvoiceMutation(?Invoice $invoice, bool $skipEditableCheck, callable $mutation): mixed
    {
        if (!$invoice instanceof Invoice) {
            return $mutation();
        }

        $operation = function () use ($invoice, $skipEditableCheck, $mutation): mixed {
            /** @var Service $invoiceService */
            $invoiceService = $this->di['mod_service']('Invoice');
            $state = $invoice->getId() === null ? null : $invoiceService->lockInvoiceState($invoice);

            if (!$skipEditableCheck) {
                $editable = $state === null
                    ? $invoiceService->isInvoiceEditable($invoice)
                    : $invoiceService->isInvoiceStateEditable($state['status'], $state['issued']);
                if (!$editable) {
                    throw new \FOSSBilling\Core\Exception\InformationException('This invoice can no longer be edited. Issued invoices are immutable; correct them with a credit note or a replacement invoice.');
                }
            }

            return $mutation();
        };

        // A caller such as updateInvoice already owns the transaction and invoice-row lock. A
        // standalone item API call creates its own transaction so payment cannot commit between
        // the invoice-state check and the item flush.
        if ($invoice->getId() === null) {
            return $operation();
        }

        $connection = $this->di['em']->getConnection();
        if ($connection->isTransactionActive()) {
            return $operation();
        }

        return $this->di['em']->wrapInTransaction($operation);
    }

    public function markAsPaid(InvoiceItem $item, $charge = true): void
    {
        if ($charge && !$item->getCharged()) {
            $invoice = $item->getInvoice();
            if ($invoice === null) {
                throw new \FOSSBilling\Core\Exception\BaseException('Invoice not found');
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
                $this->di['logger']->withChannel('billing')->info('Invoice item #{item_id} was already credited; skipping duplicate credit.', ['item_id' => $item->getId()]);
                $this->resetEntityManager();
                $item = $this->di['em']->find(InvoiceItem::class, $item->getId());
                $invoice = $this->di['em']->find(Invoice::class, $invoice->getId());
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
        if ($item->getId() !== null && $item->getType() === InvoiceItem::TYPE_ORDER && $item->getTask() === InvoiceItem::TASK_RENEW) {
            $connection = $this->di['em']->getConnection();
            if ($connection->isTransactionActive()) {
                throw new \FOSSBilling\Core\Exception\InformationException('Renewal tasks require a committed transaction before external actions.');
            }
            $name = 'fb:invoice-item-renewal:' . $item->getId();
            if (!NamedLock::acquire($connection, $name, 0)) {
                return false;
            }

            try {
                $this->di['em']->refresh($item);
                if ($item->getStatus() === InvoiceItem::STATUS_EXECUTED) {
                    return true;
                }
                if ($item->getStatus() === InvoiceItem::STATUS_FAILED) {
                    return false;
                }

                return $this->executeTaskLocked($item);
            } finally {
                NamedLock::release($connection, $name);
            }
        }

        return $this->executeTaskLocked($item);
    }

    private function executeTaskLocked(InvoiceItem $item)
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
                    throw new \FOSSBilling\Core\Exception\BaseException('Could not activate proforma item. Order :id not found', [':id' => $order_id]);
                }
                $orderService = $this->di['mod_service']('Order');
                switch ($item->getTask()) {
                    case InvoiceItem::TASK_ACTIVATE:
                        $product = $this->di['mod_service']('Product')->findProductById((int) $order->getProductId());
                        if ($product->getSetup() == \Box\Mod\Product\Service::SETUP_AFTER_PAYMENT) {
                            try {
                                $orderService->activateOrder($order);
                            } catch (\Exception $e) {
                                $this->di['logger']->error($e->getMessage());
                                $orderService->saveStatusChange($order, "Order could not be activated due to error: {$e->getMessage()}.");
                                $taskFailed = true;
                            }
                        }

                        break;

                    case InvoiceItem::TASK_RENEW:
                        try {
                            $metadata = $orderService->getOrderMetaRepository()->getPairsForOrder($order_id);
                            $marker = 'free_domain_renewal_invoice_' . $item->getInvoice()?->getId();
                            $freeRenewal = ($metadata[$marker] ?? null) === '1';
                            $zeroPriceMarker = 'domain_renewal_zero_price_invoice_' . $item->getInvoice()?->getId();
                            $knownZeroPrice = ($metadata[$zeroPriceMarker] ?? null) === '1';
                            if (!$freeRenewal && !$knownZeroPrice && (float) $item->getPrice() === 0.0) {
                                $product = $this->di['mod_service']('Product')->findProductById((int) $order->getProductId());
                                if ($product->getType() === \Box\Mod\Product\Service::DOMAIN) {
                                    $item->setStatus(InvoiceItem::STATUS_FAILED);

                                    throw new \FOSSBilling\Core\Exception\InformationException('This zero-priced domain renewal needs staff review before it can be sent to the registrar.');
                                }
                            }
                            if ($freeRenewal || $knownZeroPrice) {
                                return $this->executeFreeDomainRenewal($item, $order, $orderService, $knownZeroPrice);
                            }

                            try {
                                $orderService->renewInvoiceItem($order, (int) $item->getId());
                            } catch (\Exception $e) {
                                if ($this->di['em']->isOpen()
                                    && ($orderService->getOrderMetaRepository()->getPairsForOrder($order_id)[OrderService::META_PAID_RENEWAL_OUTCOME_PREFIX . $item->getId()] ?? null) === OrderService::RENEWAL_OUTCOME_PENDING) {
                                    $item->setStatus(InvoiceItem::STATUS_FAILED);
                                }

                                throw $e;
                            }
                        } catch (\Exception $e) {
                            $this->di['logger']->error($e->getMessage());
                            $orderService->saveStatusChange($order, "Order could not renew due to error: {$e->getMessage()}.");
                            $taskFailed = true;
                        }

                        break;

                    default:
                        // do nothing for unregistered tasks
                        break;
                }
            } catch (\Exception $e) {
                $this->di['logger']->error($e->getMessage());
                $taskFailed = true;
            }

            if (!$taskFailed) {
                if ($item->getStatus() !== InvoiceItem::STATUS_EXECUTED) {
                    $this->markAsExecuted($item);
                }
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

    /** Run the domain claim protocol under the existing item and domain locks. */
    private function executeFreeDomainRenewal(InvoiceItem $item, Order $order, OrderService $orderService, bool $knownZeroPrice): ?bool
    {
        $orderId = $this->getOrderId($item);
        $connection = $this->di['em']->getConnection();
        if ($connection->isTransactionActive()) {
            throw new \FOSSBilling\Core\Exception\InformationException('Free domain renewals require a committed claim before contacting the registrar.');
        }
        $lock = 'fb:free-domain-renewal:' . $orderId;
        if (!NamedLock::acquire($connection, $lock, 30)) {
            throw new \FOSSBilling\Core\Exception\InformationException('Another renewal for this domain is already in progress.');
        }

        try {
            $renewed = $orderService->renewFreeDomainInvoiceItem($order, (int) $item->getId(), function () use ($item, $order, $orderService, $orderId, $knownZeroPrice): bool {
                $this->di['em']->refresh($item);
                $this->di['em']->refresh($order);
                if ($item->getStatus() === InvoiceItem::STATUS_EXECUTED) {
                    return false;
                }
                if ($item->getStatus() === InvoiceItem::STATUS_FAILED) {
                    return false;
                }
                $metadata = $orderService->getOrderMetaRepository()->getPairsForOrder($orderId);
                $claim = OrderService::META_FREE_RENEWAL_CLAIM_PREFIX . $item->getId();
                $approval = OrderService::META_FREE_RENEWAL_RETRY_PREFIX . $item->getId();
                if (($metadata[$claim] ?? null) !== OrderService::RENEWAL_OUTCOME_COMPLETED) {
                    // Eligibility applies to a new provider attempt. A
                    // completed claim only resumes local delivery and
                    // acknowledgement, even if the order was canceled.
                    if (!in_array($order->getStatus(), [Order::STATUS_ACTIVE, Order::STATUS_SUSPENDED, Order::STATUS_FAILED_RENEW], true)) {
                        $item->setStatus(InvoiceItem::STATUS_FAILED);

                        throw new \FOSSBilling\Core\Exception\InformationException('The domain order is no longer eligible for renewal and needs staff review.');
                    }
                    if (isset($metadata[$claim])) {
                        if (($metadata[$approval] ?? null) !== '1') {
                            $item->setStatus(InvoiceItem::STATUS_FAILED);

                            throw new \FOSSBilling\Core\Exception\InformationException('This renewal was already sent or attempted and needs staff review.');
                        }
                        // Consume the approval durably before the external call. A
                        // second interruption always requires another review.
                        $orderService->updateOrderMeta($order, [$approval => '0']);
                    } else {
                        $reviewedZero = $knownZeroPrice || ($metadata['domain_renewal_reviewed_zero_' . $item->getId()] ?? null) === '1';
                        $claimValues = [$claim => '1'];
                        if (!$reviewedZero) {
                            $until = $this->di['mod_service']('Product')->freeDomainRenewalUntil($orderId);
                            if ($until === null) {
                                if (($metadata[$approval] ?? null) === '1'
                                    || !$this->di['mod_service']('Product')->isFreeDomainRenewalOffered($orderId)) {
                                    $item->setStatus(InvoiceItem::STATUS_FAILED);

                                    throw new \FOSSBilling\Core\Exception\InformationException('The paid hosting term does not cover this renewal; staff review is still required.');
                                }

                                // Wait for the linked hosting payment without
                                // consuming a claim or a retry attempt.
                                return false;
                            }
                            $claimValues = ['free_domain_paid_through' => (string) $until, $claim => '1'];
                        }
                        if (($metadata[$approval] ?? null) === '1') {
                            $claimValues[$approval] = '0';
                        }
                        // Flush a durable claim before the external call.
                        $orderService->updateOrderMeta($order, $claimValues);
                    }
                }

                return true;
            });
            if (!$renewed) {
                return $item->getStatus() === InvoiceItem::STATUS_EXECUTED;
            }
            $this->markAsExecuted($item);
        } catch (\FOSSBilling\Core\Exception\InformationException $e) {
            if (!in_array($order->getStatus(), [Order::STATUS_ACTIVE, Order::STATUS_SUSPENDED, Order::STATUS_FAILED_RENEW], true)) {
                $item->setStatus(InvoiceItem::STATUS_FAILED);
            }

            throw $e;
        } finally {
            NamedLock::release($connection, $lock);
        }

        return null;
    }

    public function addNew(Invoice $proforma, array $data, bool $skipEditableCheck = false): int
    {
        // Deposit lines credit the client balance when the invoice is paid,
        // so they may only be created through the Add Funds flow
        // (generateForAddFunds) — never from generic invoice input.
        if (($data['type'] ?? InvoiceItem::TYPE_CUSTOM) === InvoiceItem::TYPE_DEPOSIT) {
            throw new \FOSSBilling\Core\Exception\InformationException('Deposit invoice items can only be created through the Add Funds flow.');
        }

        return $this->runInvoiceMutation($proforma, $skipEditableCheck, function () use ($proforma, $data): int {
            $title = $data['title'] ?? '';
            if (empty($title)) {
                throw new \FOSSBilling\Core\Exception\InformationException('Invoice item title is missing');
            }

            $period = $this->normalizePeriod($data['period'] ?? null);
            if ($period !== null) {
                $period = $this->di['period']($period)->getCode();
            }

            $pi = new InvoiceItem();
            $pi->setInvoice($proforma);
            $pi->setType($data['type'] ?? InvoiceItem::TYPE_CUSTOM);
            $pi->setRelId(isset($data['rel_id']) ? (string) $data['rel_id'] : null);
            $pi->setTask($data['task'] ?? InvoiceItem::TASK_VOID);
            $pi->setStatus($data['status'] ?? InvoiceItem::STATUS_PENDING_PAYMENT);
            $pi->setTitle($data['title']);
            $pi->setPeriod($period);
            $pi->setQuantity(PriceValidator::validateQuantity($data['quantity'] ?? 1));
            $pi->setUnit($data['unit'] ?? null);
            // Dual-write the typed order linkage (see invoice_item.order_id).
            // Derived after unit because discount lines name their order
            // through unit='discount' rather than type='order'.
            $pi->setOrderId(self::resolveOrderReference($pi->getType(), $pi->getUnit(), $pi->getRelId()));
            $pi->setCharged($data['charged'] ?? 0);
            $pi->setPrice(PriceValidator::validateSignedAmount($data['price'] ?? 0));
            $pi->setTaxed($data['taxed'] ?? false);
            $this->di['em']->persist($pi);
            $this->di['em']->flush();

            return (int) $pi->getId();
        });
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

        $price = $item->getPrice();
        if (!is_numeric($price)) {
            return 0;
        }

        $rate = $this->di['em']->getConnection()->fetchOne('SELECT taxrate FROM invoice WHERE id = :id', ['id' => $item->getInvoice()?->getId()]);
        if (!is_numeric($rate) || $rate <= 0) {
            return 0;
        }

        return round((float) $price * (float) $rate / 100, 2);
    }

    public function update(InvoiceItem $item, array $data): void
    {
        $this->runInvoiceMutation($item->getInvoice(), false, function () use ($item, $data): void {
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
        });
    }

    public function remove(InvoiceItem $model, bool $skipEditableCheck = false): bool
    {
        return $this->runInvoiceMutation($model->getInvoice(), $skipEditableCheck, function () use ($model): bool {
            $id = $model->getId();
            $this->di['em']->remove($model);
            $this->di['em']->flush();
            $this->di['logger']->info('Removed invoice item "{id}"', ['id' => $id]);

            return true;
        });
    }

    public function generateForAddFunds(Invoice $proforma, $amount): void
    {
        $pi = new InvoiceItem();
        $pi->setInvoice($proforma);
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
        $invoice = $item->getInvoice();
        if ($invoice === null) {
            throw new \FOSSBilling\Core\Exception\BaseException('Invoice not found');
        }
        $total = $this->getTotalWithTax($item);
        $this->persistCredit($item, $invoice, $total);

        // Idempotency: the unique constraint on invoice_item_id guarantees at most one
        // credit per item. A violation means a retry already credited it — treat as no-op.
        try {
            $this->di['em']->flush();
        } catch (UniqueConstraintViolationException) {
            $this->di['logger']->withChannel('billing')->info('Invoice item #{item_id} was already credited; skipping duplicate credit.', ['item_id' => $item->getId()]);
            $this->resetEntityManager();

            return;
        }

        $this->addChargeNote($item, $invoice, $total);
    }

    protected function resetEntityManager(): void
    {
        unset($this->di['em']);
        $this->di['em'] = EntityManagerFactory::create();
        /** @var InvoiceItemRepository $repository */
        $repository = $this->di['em']->getRepository(InvoiceItem::class);
        $this->invoiceItemRepository = $repository;
    }

    private function persistCredit(InvoiceItem $item, Invoice $invoice, float $total): ClientBalance
    {
        if ($item->getType() === InvoiceItem::TYPE_ORDER && $item->getRelId() !== null) {
            \Box\Mod\Order\DeletionRecovery::assertNotHeld($this->di['em']->getConnection(), (int) $item->getRelId(), true);
        }
        $client = $this->di['em']->getRepository(Client::class)->find($invoice->getClientId())
            ?? throw new \FOSSBilling\Core\Exception\BaseException('Client not found');

        $credit = new ClientBalance();
        $credit->setClient($client);
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
            // Prefer the typed linkage, falling back to the legacy string
            // reference for rows written before the order_id backfill ran.
            return $item->getOrderId() ?? (int) $item->getRelId();
        }

        return 0;
    }

    /**
     * Derives the typed order linkage from a new item's generic reference
     * (see invoice_item.order_id). Only the shapes this codebase produces for
     * order references qualify (type='order' lines, unit='discount' lines);
     * invoice/credit-note references, hook_call JSON and third-party line
     * types map to null.
     */
    public static function resolveOrderReference(?string $type, ?string $unit, ?string $relId): ?int
    {
        if ($relId === null || !is_numeric($relId)) {
            return null;
        }

        if ($type === InvoiceItem::TYPE_ORDER || $unit === 'discount') {
            return (int) $relId;
        }

        return null;
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
            $this->di['logger']->withChannel('billing')->error('Invoice item #{item_id} marked as failed after {attempts} task execution attempts.', ['item_id' => $item->getId(), 'attempts' => $attempts]);
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
            throw new \FOSSBilling\Core\Exception\InformationException('Invoice item was not found');
        }

        if ($item->getStatus() !== InvoiceItem::STATUS_FAILED) {
            throw new \FOSSBilling\Core\Exception\InformationException('Invoice item is not in a failed state');
        }

        if ($item->getType() === InvoiceItem::TYPE_ORDER && $item->getTask() === InvoiceItem::TASK_RENEW) {
            $order = $this->di['em']->getRepository(Order::class)->find($this->getOrderId($item));
            if ($order instanceof Order
                && ($this->di['mod_service']('Order')->getOrderMetaRepository()->getPairsForOrder((int) $order->getId())[OrderService::META_PAID_RENEWAL_OUTCOME_PREFIX . $id] ?? null) === OrderService::RENEWAL_OUTCOME_PENDING) {
                throw new \FOSSBilling\Core\Exception\InformationException('Review the provider outcome before approving a renewal retry.');
            }
        }

        $item->setStatus(InvoiceItem::STATUS_PENDING_SETUP);
        $item->setAttempts(0);
        $this->di['em']->persist($item);
        $this->di['em']->flush();
        $this->di['logger']->withChannel('billing')->info('Invoice item #{item_id} re-queued for execution by an admin.', ['item_id' => $item->getId()]);

        return $item;
    }

    /** Staff must verify the provider outcome and reconcile the order expiry. */
    public function reviewPaidRenewal(int $id, string $decision): void
    {
        if (!in_array($decision, ['retry', 'completed'], true)) {
            throw new \FOSSBilling\Core\Exception\InformationException('Invalid renewal review decision.');
        }
        $connection = $this->di['em']->getConnection();
        if ($connection->isTransactionActive()) {
            throw new \FOSSBilling\Core\Exception\InformationException('Renewal review must start outside a transaction.');
        }
        $name = 'fb:invoice-item-renewal:' . $id;
        if (!NamedLock::acquire($connection, $name, 0)) {
            throw new \FOSSBilling\Core\Exception\InformationException('This renewal is already being processed.');
        }

        try {
            $this->di['em']->wrapInTransaction(function () use ($id, $decision): void {
                $item = $this->invoiceItemRepository->find($id);
                if (!$item instanceof InvoiceItem) {
                    throw new \FOSSBilling\Core\Exception\InformationException('Invoice item was not found');
                }
                $this->di['em']->refresh($item);
                $orders = $this->di['mod_service']('Order');
                $order = $this->di['em']->getRepository(Order::class)->find($this->getOrderId($item));
                $checkpoint = OrderService::META_PAID_RENEWAL_OUTCOME_PREFIX . $id;
                if ($item->getType() !== InvoiceItem::TYPE_ORDER || $item->getTask() !== InvoiceItem::TASK_RENEW
                    || $item->getStatus() !== InvoiceItem::STATUS_FAILED || $item->getInvoice()?->getStatus() !== Invoice::STATUS_PAID
                    || !$order instanceof Order
                    || ($orders->getOrderMetaRepository()->getPairsForOrder((int) $order->getId())[$checkpoint] ?? null) !== OrderService::RENEWAL_OUTCOME_PENDING) {
                    throw new \FOSSBilling\Core\Exception\InformationException('This renewal has no uncertain outcome awaiting review.');
                }
                $orders->updateOrderMeta($order, [$checkpoint => $decision === 'retry' ? OrderService::RENEWAL_OUTCOME_RETRY : OrderService::RENEWAL_OUTCOME_COMPLETED]);
                $item->setStatus($decision === 'retry' ? InvoiceItem::STATUS_PENDING_SETUP : InvoiceItem::STATUS_EXECUTED);
                $item->setAttempts(0);
                $this->di['em']->flush();
            });
            $this->di['logger']->withChannel('billing')->info('Staff reviewed renewal item #{id}: {decision}', ['id' => $id, 'decision' => $decision]);
        } finally {
            NamedLock::release($connection, $name);
        }
    }

    /**
     * Apply an explicit staff decision after checking the registrar and the
     * order's expiry. A retry approval permits one external attempt only.
     */
    public function reviewDomainRenewal(int $id, string $decision): void
    {
        if (!in_array($decision, ['retry_free', 'retry_zero', 'completed'], true)) {
            throw new \FOSSBilling\Core\Exception\InformationException('Invalid renewal review decision.');
        }
        $item = $this->invoiceItemRepository->find($id);
        if (!$item instanceof InvoiceItem || $item->getType() !== InvoiceItem::TYPE_ORDER
            || $item->getTask() !== InvoiceItem::TASK_RENEW || $item->getPrice() === null || (float) $item->getPrice() !== 0.0) {
            throw new \FOSSBilling\Core\Exception\InformationException('This is not a zero-priced domain renewal.');
        }
        $orderId = $this->getOrderId($item);
        $order = $this->di['em']->getRepository(Order::class)->find($orderId);
        if (!$order instanceof Order || $this->di['mod_service']('Product')->findProductById((int) $order->getProductId())->getType() !== \Box\Mod\Product\Service::DOMAIN) {
            throw new \FOSSBilling\Core\Exception\InformationException('This is not a domain renewal.');
        }
        $connection = $this->di['em']->getConnection();
        if ($connection->isTransactionActive()) {
            throw new \FOSSBilling\Core\Exception\InformationException('Renewal review must start outside a transaction.');
        }
        $lock = 'fb:free-domain-renewal:' . $orderId;
        if (!NamedLock::acquire($connection, $lock, 30)) {
            throw new \FOSSBilling\Core\Exception\InformationException('Another renewal for this domain is already in progress.');
        }

        try {
            $this->di['em']->refresh($item);
            $this->di['em']->refresh($order);
            if ($item->getStatus() !== InvoiceItem::STATUS_FAILED || $item->getInvoice()?->getStatus() !== Invoice::STATUS_PAID) {
                throw new \FOSSBilling\Core\Exception\InformationException('Only a failed renewal on a paid invoice can be reviewed.');
            }
            $this->di['em']->wrapInTransaction(function () use ($item, $order, $decision): void {
                if ($decision === 'completed') {
                    $this->di['mod_service']('Order')->updateOrderMeta($order, [
                        OrderService::META_FREE_RENEWAL_CLAIM_PREFIX . $item->getId() => OrderService::RENEWAL_OUTCOME_COMPLETED,
                        OrderService::META_FREE_RENEWAL_RETRY_PREFIX . $item->getId() => '0',
                    ]);
                    $item->setStatus(InvoiceItem::STATUS_EXECUTED);
                } else {
                    $metadata = [
                        'free_domain_renewal_invoice_' . $item->getInvoice()->getId() => '1',
                        OrderService::META_FREE_RENEWAL_RETRY_PREFIX . $item->getId() => '1',
                        'domain_renewal_reviewed_zero_' . $item->getId() => $decision === 'retry_zero' ? '1' : '0',
                    ];
                    $this->di['mod_service']('Order')->updateOrderMeta($order, $metadata);
                    $item->setStatus(InvoiceItem::STATUS_PENDING_SETUP);
                    $item->setAttempts(0);
                }
                $this->di['em']->persist($item);
                $this->di['em']->flush();
            });
            $this->di['logger']->withChannel('billing')->info('Invoice item #{item_id} renewal review decision: {decision}', ['item_id' => $id, 'decision' => $decision]);
        } finally {
            NamedLock::release($connection, $lock);
        }
    }

    public function generateFromOrder(Invoice $proforma, Order $order, $task, $price, array $line = [], bool $applyPromo = true): void
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
        $pi->setInvoice($proforma);
        $pi->setType(InvoiceItem::TYPE_ORDER);
        $pi->setRelId((string) $order->getId());
        $pi->setOrderId((int) $order->getId());
        $pi->setTask($task);
        $pi->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT);
        $pi->setTitle($line['title'] ?? $order->getTitle());
        $pi->setPeriod($period);
        $pi->setQuantity(PriceValidator::validateQuantity($quantity));
        $pi->setUnit($unit);
        $pi->setPrice(PriceValidator::validateSignedAmount($price));
        $pi->setTaxed($taxed);
        $this->di['em']->persist($pi);
        $this->di['em']->flush();

        $corderService->setUnpaidInvoice($order, $proforma);
        if ($task === InvoiceItem::TASK_RENEW && !empty($line['free_domain_renewal'])) {
            $corderService->updateOrderMeta($order, ['free_domain_renewal_invoice_' . $proforma->getId() => '1']);
        } elseif ($task === InvoiceItem::TASK_RENEW && array_key_exists('free_domain_renewal', $line) && (float) $price === 0.0) {
            // A newly quoted zero TLD price is distinguishable from legacy
            // zero-priced rows whose provenance requires staff review.
            $corderService->updateOrderMeta($order, ['domain_renewal_zero_price_invoice_' . $proforma->getId() => '1']);
        }

        if (!$applyPromo) {
            return;
        }

        // apply discount for new invoice if promo code is recurrent
        $productService = $this->di['mod_service']('Product');
        $promoAdjustments = $productService->getRenewalPromoAdjustments($order, (float) $price, (float) $quantity);
        foreach ($promoAdjustments as $promoAdjustment) {
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
