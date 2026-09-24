<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Invoice;

use Box\Mod\Client\Entity\Client;
use Box\Mod\Client\Entity\ClientBalance;
use Box\Mod\Cron\Event\AfterAdminCronRunEvent;
use Box\Mod\Currency\Entity\Currency;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\Transaction;
use Box\Mod\Invoice\Event\AfterAdminGenerateRenewalInvoiceEvent;
use Box\Mod\Invoice\Event\AfterAdminInvoiceApproveEvent;
use Box\Mod\Invoice\Event\AfterAdminInvoiceAttachOrderEvent;
use Box\Mod\Invoice\Event\AfterAdminInvoiceDebitEvent;
use Box\Mod\Invoice\Event\AfterAdminInvoiceDeleteEvent;
use Box\Mod\Invoice\Event\AfterAdminInvoicePaymentReceivedEvent;
use Box\Mod\Invoice\Event\AfterAdminInvoiceRefundEvent;
use Box\Mod\Invoice\Event\AfterAdminInvoiceReissueEvent;
use Box\Mod\Invoice\Event\AfterAdminInvoiceReminderRecordedEvent;
use Box\Mod\Invoice\Event\AfterAdminInvoiceUpdateEvent;
use Box\Mod\Invoice\Event\AfterInvoiceIsDueEvent;
use Box\Mod\Invoice\Event\BeforeAdminGenerateRenewalInvoiceEvent;
use Box\Mod\Invoice\Event\BeforeAdminInvoiceApproveEvent;
use Box\Mod\Invoice\Event\BeforeAdminInvoiceAttachOrderEvent;
use Box\Mod\Invoice\Event\BeforeAdminInvoiceDebitEvent;
use Box\Mod\Invoice\Event\BeforeAdminInvoiceDeleteEvent;
use Box\Mod\Invoice\Event\BeforeAdminInvoiceRefundEvent;
use Box\Mod\Invoice\Event\BeforeAdminInvoiceReissueEvent;
use Box\Mod\Invoice\Event\BeforeAdminInvoiceSendReminderEvent;
use Box\Mod\Invoice\Event\BeforeAdminInvoiceSendRemindersEvent;
use Box\Mod\Invoice\Event\BeforeAdminInvoiceUpdateEvent;
use Box\Mod\Invoice\Event\BeforeInvoiceIsDueEvent;
use Box\Mod\Invoice\Repository\InvoiceItemRepository;
use Box\Mod\Invoice\Repository\InvoiceRepository;
use Box\Mod\Order\Entity\Order;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use FOSSBilling\Doctrine\EntityManagerFactory;
use FOSSBilling\Doctrine\RowLock;
use FOSSBilling\Doctrine\SqlExpr;
use FOSSBilling\Environment;
use FOSSBilling\Http\ResponseFactory;
use FOSSBilling\i18n;
use FOSSBilling\InformationException;
use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\Tools;
use FOSSBilling\Validation\PriceValidator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Twig\Loader\FilesystemLoader;

class Service implements InjectionAwareInterface
{
    /**
     * Columns on the `invoice` table permitted in CSV exports.
     * The `hash` column (bearer token for public invoice access) is excluded.
     */
    private const array EXPORTABLE_COLUMNS = [
        'id', 'client_id', 'serie', 'nr', 'currency', 'currency_rate',
        'credit', 'base_income', 'base_refund', 'refund', 'notes',
        'text_1', 'text_2', 'status', 'seller_company', 'seller_company_vat',
        'seller_company_number', 'seller_address', 'seller_phone', 'seller_email',
        'buyer_first_name', 'buyer_last_name', 'buyer_company', 'buyer_company_vat',
        'buyer_company_number', 'buyer_address', 'buyer_city', 'buyer_state',
        'buyer_country', 'buyer_zip', 'buyer_phone', 'buyer_phone_cc',
        'buyer_email', 'gateway_id', 'approved', 'taxname', 'taxrate',
        'due_at', 'reminded_at', 'paid_at', 'created_at', 'updated_at',
    ];

    /** Subset of EXPORTABLE_COLUMNS used when the caller passes no headers. */
    private const array DEFAULT_EXPORT_COLUMNS = [
        'id', 'client_id', 'nr', 'currency', 'credit', 'base_income', 'base_refund',
        'refund', 'notes', 'status', 'buyer_first_name', 'buyer_last_name',
        'buyer_company', 'buyer_company_vat', 'buyer_company_number', 'buyer_address',
        'buyer_city', 'buyer_state', 'buyer_country', 'buyer_zip', 'buyer_phone',
        'buyer_phone_cc', 'buyer_email', 'approved', 'taxname', 'taxrate',
        'due_at', 'reminded_at', 'paid_at',
    ];

    protected ?\Pimple\Container $di = null;
    private Filesystem $filesystem;
    private ?int $invoiceNumberPadding = null;
    private ?InvoiceItemRepository $invoiceItemRepository = null;
    private ?InvoiceRepository $invoiceRepository = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
        if (isset($di['filesystem'])) {
            $this->filesystem = $di['filesystem'];
        }
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getInvoiceItemRepository(): InvoiceItemRepository
    {
        $this->invoiceItemRepository ??= $this->di['em']->getRepository(InvoiceItem::class);

        return $this->invoiceItemRepository;
    }

    public function getInvoiceRepository(): InvoiceRepository
    {
        $this->invoiceRepository ??= $this->di['em']->getRepository(Invoice::class);

        return $this->invoiceRepository;
    }

    protected function resetEntityManager(): void
    {
        $connection = $this->di['em']->getConnection();
        unset($this->di['em']);
        $this->di['em'] = $this->createEntityManager($connection);
        $this->invoiceItemRepository = null;
        $this->invoiceRepository = null;
    }

    protected function createEntityManager(?Connection $connection = null): EntityManagerInterface
    {
        return EntityManagerFactory::create($connection);
    }

    public function getModulePermissions(): array
    {
        return [
            'view' => [
                'type' => 'bool',
                'display_name' => __trans('View invoices'),
                'description' => __trans('Allows the staff member to view invoices and invoice details.'),
            ],
            'manage_invoices' => [
                'type' => 'bool',
                'display_name' => __trans('Manage invoices'),
                'description' => __trans('Allows the staff member to create, update, delete, and manage invoices.'),
            ],
            'manage_transactions' => [
                'type' => 'bool',
                'display_name' => __trans('Manage transactions'),
                'description' => __trans('Allows the staff member to view, create, update, delete, and process transactions.'),
            ],
            'manage_gateways' => [
                'type' => 'bool',
                'display_name' => __trans('Manage payment gateways'),
                'description' => __trans('Allows the staff member to install, configure, and remove payment gateways.'),
            ],
            'manage_subscriptions' => [
                'type' => 'bool',
                'display_name' => __trans('Manage subscriptions'),
                'description' => __trans('Allows the staff member to view, create, update, and delete subscriptions.'),
            ],
            'manage_tax' => [
                'type' => 'bool',
                'display_name' => __trans('Manage tax rules'),
                'description' => __trans('Allows the staff member to create, update, and delete tax rules.'),
            ],
            'export' => [
                'type' => 'bool',
                'display_name' => __trans('Export invoice data'),
                'description' => __trans('Allows the staff member to export invoice data as CSV.'),
            ],
            'manage_settings' => [],
        ];
    }

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    /**
     * Convert an invoice entity into the fields needed by list views.
     *
     * Unlike toApiArray(), this does not load invoice items, orders, products, the client,
     * company details, or subscription information for every invoice in the result set.
     * The totals come from a grouped aggregate query (`getInvoiceTotals`) rather than
     * per-invoice loading; keys are absent for invoices without items.
     *
     * @param array{subtotal?: float, taxable_subtotal?: float} $totals
     */
    public function toApiSummaryFromEntity(Invoice $invoice, array $totals): array
    {
        $subtotal = (float) ($totals['subtotal'] ?? 0);
        $taxableSubtotal = (float) ($totals['taxable_subtotal'] ?? 0);
        $taxRate = (float) ($invoice->getTaxrate() ?? 0);
        $tax = $taxRate > 0 && $taxableSubtotal !== 0.0 ? round($taxableSubtotal * $taxRate / 100, 2) : 0;
        $invoiceNumber = is_numeric($invoice->getNr() ?? null) ? (int) $invoice->getNr() : (int) $invoice->getId();
        $clientId = $invoice->getClientId();

        return [
            'id' => $invoice->getId(),
            'serie' => $invoice->getSerie(),
            'nr' => $invoice->getNr(),
            'serie_nr' => $invoice->getSerie() . sprintf('%0' . $this->getInvoiceNumberPadding() . 's', $invoiceNumber),
            'client_id' => $clientId,
            'client' => $clientId === null ? null : ['id' => $clientId],
            'currency' => $invoice->getCurrency(),
            'tax' => $tax,
            'subtotal' => $subtotal,
            'total' => $subtotal + $tax,
            'status' => $invoice->getStatus(),
            'due_at' => $invoice->getDueAt()?->format('Y-m-d H:i:s'),
            'paid_at' => $invoice->getPaidAt()?->format('Y-m-d H:i:s'),
            'created_at' => $invoice->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $invoice->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'buyer' => [
                'first_name' => $invoice->getBuyerFirstName(),
                'last_name' => $invoice->getBuyerLastName(),
                'email' => $invoice->getBuyerEmail(),
            ],
            'approved' => $invoice->isApproved(),
        ];
    }

    private function getInvoiceNumberPadding(): int
    {
        if ($this->invoiceNumberPadding === null) {
            $padding = $this->di['mod_service']('system')->getParamValue('invoice_number_padding');
            $this->invoiceNumberPadding = $padding !== null && $padding !== '' ? (int) $padding : 5;
        }

        return $this->invoiceNumberPadding;
    }

    private function formatSerieNr(?string $serie, int|string|null $nr): string
    {
        return $serie . sprintf('%0' . $this->getInvoiceNumberPadding() . 's', $nr ?? '');
    }

    public function toApiArray(Invoice $invoice, $deep = true, $identity = null, bool $includeClientBillingEmail = false): array
    {
        $this->ensureValidHash($invoice);
        $row = [
            'id' => $invoice->getId(),
            'client_id' => $invoice->getClientId(),
            'serie' => $invoice->getSerie(),
            'nr' => $invoice->getNr(),
            'hash' => $invoice->getHash(),
            'currency' => $invoice->getCurrency(),
            'currency_rate' => $invoice->getCurrencyRate(),
            'credit' => $invoice->getCredit(),
            'base_income' => $invoice->getBaseIncome(),
            'base_refund' => $invoice->getBaseRefund(),
            'refund' => $invoice->getRefund(),
            'notes' => $invoice->getNotes(),
            'text_1' => $invoice->getText1(),
            'text_2' => $invoice->getText2(),
            'status' => $invoice->getStatus(),
            'seller_company' => $invoice->getSellerCompany(),
            'seller_company_vat' => $invoice->getSellerCompanyVat(),
            'seller_company_number' => $invoice->getSellerCompanyNumber(),
            'seller_address' => $invoice->getSellerAddress(),
            'seller_phone' => $invoice->getSellerPhone(),
            'seller_email' => $invoice->getSellerEmail(),
            'buyer_first_name' => $invoice->getBuyerFirstName(),
            'buyer_last_name' => $invoice->getBuyerLastName(),
            'buyer_company' => $invoice->getBuyerCompany(),
            'buyer_company_vat' => $invoice->getBuyerCompanyVat(),
            'buyer_company_number' => $invoice->getBuyerCompanyNumber(),
            'buyer_address' => $invoice->getBuyerAddress(),
            'buyer_city' => $invoice->getBuyerCity(),
            'buyer_state' => $invoice->getBuyerState(),
            'buyer_country' => $invoice->getBuyerCountry(),
            'buyer_zip' => $invoice->getBuyerZip(),
            'buyer_phone' => $invoice->getBuyerPhone(),
            'buyer_phone_cc' => $invoice->getBuyerPhoneCc(),
            'buyer_email' => $invoice->getBuyerEmail(),
            'gateway_id' => $invoice->getGateway()?->getId(),
            'approved' => $invoice->isApproved(),
            'credit_note_for_invoice_id' => $invoice->getCreditNoteForInvoiceId(),
            'debit_note_for_invoice_id' => $invoice->getDebitNoteForInvoiceId(),
            'replaces_invoice_id' => $invoice->getReplacesInvoiceId(),
            'replaced_by_invoice_id' => $invoice->getReplacedByInvoiceId(),
            'taxname' => $invoice->getTaxname(),
            'taxrate' => $invoice->getTaxrate(),
            'due_at' => $invoice->getDueAt()?->format('Y-m-d H:i:s'),
            'reminded_at' => $invoice->getRemindedAt()?->format('Y-m-d H:i:s'),
            'paid_at' => $invoice->getPaidAt()?->format('Y-m-d H:i:s'),
            'created_at' => $invoice->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $invoice->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'hash_expires_at' => $invoice->getHashExpiresAt()?->format('Y-m-d H:i:s'),
        ];

        $items = $this->getInvoiceItemRepository()->findByInvoiceId((int) $row['id']);
        $lines = [];
        $total = 0;
        $taxable_subtotal = 0;

        foreach ($items as $item) {
            $order_id = ($item->getType() == InvoiceItem::TYPE_ORDER) ? $item->getRelId() : null;

            $line_total = ($item->getPrice() ?? 0) * ($item->getQuantity() ?? 1);
            $total += $line_total;

            if ($item->getTaxed()) {
                $taxable_subtotal += $line_total;
            }

            $line = [
                'id' => $item->getId(),
                'title' => $item->getTitle(),
                'period' => $item->getPeriod(),
                'quantity' => $item->getQuantity() ?? 1,
                'unit' => $item->getUnit(),
                'price' => $item->getPrice() ?? 0,
                'tax' => 0, // Tax will be calculated on the total taxable subtotal
                'taxed' => (int) $item->getTaxed(),
                'charged' => (int) $item->getCharged(),
                'total' => $line_total,
                'order_id' => $order_id,
                'type' => $item->getType(),
                'rel_id' => $item->getRelId(),
                'task' => $item->getTask(),
                'status' => $item->getStatus(),
            ];
            $lines[] = $line;
        }

        $current_invoice_tax_rate = (float) $row['taxrate'];
        if ($current_invoice_tax_rate > 0 && $taxable_subtotal != 0) {
            $tax = round($taxable_subtotal * $current_invoice_tax_rate / 100, 2);
        } else {
            $tax = 0;
        }

        $result = [];
        $result['id'] = $row['id'];
        $result['serie'] = $row['serie'];
        $result['nr'] = $row['nr'];
        $result['client_id'] = $invoice->getClientId();

        $nr = is_numeric($row['nr']) ? intval($row['nr']) : $result['id'];
        $result['serie_nr'] = $this->formatSerieNr($invoice->getSerie(), $nr);

        $result['hash'] = $row['hash'];
        $result['hash_expires_at'] = $row['hash_expires_at'] ?? null;
        $result['gateway_id'] = $row['gateway_id'] ?? null;
        $result['taxname'] = $row['taxname'];
        $result['taxrate'] = $row['taxrate'];
        $result['currency'] = $row['currency'];
        $result['currency_rate'] = $row['currency_rate'] ?? 1;
        $result['tax'] = $tax;
        $result['subtotal'] = $total;
        $result['total'] = $total + $tax;
        $result['status'] = $row['status'];
        $result['notes'] = $row['notes'];
        $result['text_1'] = $row['text_1'] ?? null;
        $result['text_2'] = $row['text_2'] ?? null;
        $result['due_at'] = $row['due_at'];
        $result['paid_at'] = $row['paid_at'] ?? null;
        $result['created_at'] = $row['created_at'];
        $result['updated_at'] = $row['updated_at'];
        $result['lines'] = $lines;

        $result['buyer'] = [
            'first_name' => $row['buyer_first_name'],
            'last_name' => $row['buyer_last_name'],
            'company' => $row['buyer_company'],
            'company_vat' => $row['buyer_company_vat'],
            'company_number' => $row['buyer_company_number'],
            'address' => $row['buyer_address'],
            'city' => $row['buyer_city'],
            'state' => $row['buyer_state'],
            'country' => $row['buyer_country'],
            'phone' => $row['buyer_phone'],
            'phone_cc' => $row['buyer_phone_cc'] ?? '',
            'email' => $row['buyer_email'],
            'zip' => $row['buyer_zip'],
        ];

        $systemService = $this->di['mod_service']('system');
        $c = $systemService->getCompany();
        $result['seller'] = [
            'company' => !empty($row['seller_company']) ? $row['seller_company'] : ($c['name'] ?? ''),
            'company_vat' => $row['seller_company_vat'] ?? '',
            'company_number' => $row['seller_company_number'] ?? '',
            'address' => !empty($row['seller_address']) ? $row['seller_address'] : trim(($c['address_1'] ?? '') . ' ' . ($c['address_2'] ?? '') . ' ' . ($c['address_3'] ?? '')),
            'address_1' => $c['address_1'] ?? '',
            'address_2' => $c['address_2'] ?? '',
            'address_3' => $c['address_3'] ?? '',
            'phone' => !empty($row['seller_phone']) ? $row['seller_phone'] : ($c['tel'] ?? ''),
            'email' => !empty($row['seller_email']) ? $row['seller_email'] : ($c['email'] ?? ''),
            'account_number' => $c['account_number'] ?? null,
            'bank_name' => $c['bank_name'] ?? null,
            'bic' => $c['bic'] ?? null,
        ];

        /**
         * Generates error when this function is called by cron.
         */
        $client = isset($row['client_id']) ? $this->di['em']->getRepository(Client::class)->find($row['client_id']) : null;
        $clientService = $this->di['mod_service']('client');
        if ($client instanceof Client) {
            $result['client'] = $clientService->toApiArray($client);
            if ($includeClientBillingEmail) {
                $result['client']['billing_email'] = $client->getBillingEmail();
            }
        } else {
            $result['client'] = null;
        }
        $result['reminded_at'] = $row['reminded_at'] ?? null;
        $result['approved'] = (bool) $row['approved'];
        $result['editable'] = $this->isInvoiceEditable($invoice);
        $result['credit_note_for_invoice_id'] = $row['credit_note_for_invoice_id'] ?? null;
        $result['debit_note_for_invoice_id'] = $row['debit_note_for_invoice_id'] ?? null;
        $result['replaces_invoice_id'] = $row['replaces_invoice_id'] ?? null;
        $result['replaced_by_invoice_id'] = $row['replaced_by_invoice_id'] ?? null;
        $result['refunded_by_invoice_ids'] = [];
        $result['refunded_total'] = round((float) ($row['refund'] ?? 0), 2);
        $result['remaining_refundable'] = round(max(0, $result['total'] - $result['refunded_total']), 2);
        if ($invoice->getStatus() === Invoice::STATUS_REFUNDED || $result['refunded_total'] > 0) {
            $result['refunded_by_invoice_ids'] = array_map(
                fn (Invoice $creditNote): ?int => $creditNote->getId(),
                $this->findRefundingInvoices($invoice)
            );
        }
        $result['income'] = ($row['base_income'] ?? 0) - ($row['base_refund'] ?? 0);
        $result['refund'] = $row['refund'] ?? 0;
        $result['credit'] = $row['credit'] ?? 0;

        $subscriptionService = $this->di['mod_service']('Invoice', 'Subscription');
        $subscriptionPeriod = $subscriptionService->getSubscriptionPeriod($invoice);
        $result['subscribable'] = $subscriptionPeriod !== null;
        if ($deep && $result['subscribable']) {
            $period = $this->di['period']($subscriptionPeriod);
            $result['subscription'] = [
                'unit' => $period->getUnit(),
                'cycle' => $period->getQty(),
                'period' => $subscriptionPeriod,
            ];
        }

        // Add order information for email templates
        $result['orders'] = [];
        $orderIds = array_unique(array_filter(array_column($lines, 'order_id')));

        // Ensure order IDs are safe integers before using in SQL
        $orderIds = array_map(intval(...), $orderIds);
        $orderIds = array_filter($orderIds, static fn ($id): bool => $id > 0);
        $orderIds = array_values($orderIds);

        if (!empty($orderIds)) {
            // Batch load orders
            $orders = $this->di['em']->getRepository(Order::class)->findBy(['id' => $orderIds]);

            // Batch load related products
            $rawProductIds = array_map(static fn (Order $order): int => $order->getProductId() ?? 0, $orders);
            $nonEmptyProductIds = array_filter($rawProductIds);
            $productIds = array_unique($nonEmptyProductIds);

            // Ensure product IDs are safe integers before using in SQL
            $productIds = array_values(array_filter($productIds, static fn ($id): bool => $id > 0));

            $productService = $this->di['mod_service']('product');
            $productsById = !empty($productIds) ? $productService->getProductSnapshotMap($productIds) : [];

            foreach ($orders as $order) {
                $productId = $order->getProductId() !== null ? (int) $order->getProductId() : 0;
                $product = $productsById[$productId] ?? null;
                $expiresAt = $order->getExpiresAt();
                $orderData = [
                    'id' => $order->getId(),
                    'title' => $order->getTitle(),
                    'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
                ];

                if ($product) {
                    $orderData['product_name'] = $product['title'];
                    $orderData['product_type'] = $product['type'];
                }

                $result['orders'][] = $orderData;
            }
        }

        return $result;
    }

    /**
     * Promotions recorded against this invoice at checkout, grouped by promo
     * and order. Used by the admin UI to show and remove applied promos.
     *
     * @return list<array{
     *     promo_id: int,
     *     code: ?string,
     *     title: string,
     *     order_id: ?int,
     *     discount_amount: float,
     *     status: string,
     *     removable: bool
     * }>
     */
    public function getInvoicePromoApplications(Invoice $invoice): array
    {
        $productService = $this->di['mod_service']('Product');
        $redemptions = $productService->getPromoRedemptionRepository()->findBy(
            [
                'invoiceId' => (int) $invoice->getId(),
                'phase' => \Box\Mod\Product\Entity\PromoRedemption::PHASE_CHECKOUT,
            ],
            ['id' => 'ASC']
        );

        if ($redemptions === []) {
            return [];
        }

        $grouped = [];
        foreach ($redemptions as $redemption) {
            $promo = $redemption->getPromo();
            if (!$promo instanceof \Box\Mod\Product\Entity\Promo) {
                continue;
            }

            $key = $promo->getId() . ':' . ($redemption->getClientOrderId() ?? 0);
            $grouped[$key] ??= [
                'promo_id' => (int) $promo->getId(),
                'code' => $promo->getCode(),
                'title' => '',
                'order_id' => $redemption->getClientOrderId() !== null ? (int) $redemption->getClientOrderId() : null,
                'discount_amount' => 0.0,
                'status' => $redemption->getStatus(),
                'removable' => false,
            ];
            $grouped[$key]['discount_amount'] += (float) ($redemption->getDiscountAmount() ?? 0);
            if ($redemption->getStatus() === \Box\Mod\Product\Entity\PromoRedemption::STATUS_RESERVED) {
                $grouped[$key]['removable'] = true;
            }
        }

        $applications = [];
        foreach ($grouped as $entry) {
            try {
                $promo = $productService->findPromoById($entry['promo_id']);
                $entry['title'] = $productService->getPromoDiscountTitle($promo, $invoice->getCurrency() ?? '');
            } catch (\Exception) {
                $entry['title'] = $entry['code'] ?? '';
            }

            $entry['removable'] = $entry['removable'] && $invoice->getStatus() === Invoice::STATUS_UNPAID;
            $entry['discount_amount'] = round($entry['discount_amount'], 2);
            $applications[] = $entry;
        }

        return $applications;
    }

    #[AsEventListener]
    public function sendPaidInvoiceEmail(AfterAdminInvoicePaymentReceivedEvent $event): void
    {
        try {
            $invoiceModel = $this->di['em']->getRepository(Invoice::class)->find($event->invoiceId);
            if (!$invoiceModel instanceof Invoice) {
                return;
            }

            $invoice = $this->toApiArray($invoiceModel, true, null, true);
            if (($invoice['total'] ?? 0) > 0) {
                $this->sendInvoiceEmail($invoiceModel, $invoice, 'mod_invoice_paid');
            }
        } catch (\Exception $exc) {
            $this->di['logger']->withChannel('email')->error('Failed to send email for invoice payment', ['exception' => $exc]);
        }
    }

    #[AsEventListener]
    public function sendApprovedInvoiceEmail(AfterAdminInvoiceApproveEvent $event): void
    {
        try {
            $invoiceModel = $this->di['em']->getRepository(Invoice::class)->find($event->invoiceId);
            if (!$invoiceModel instanceof Invoice) {
                return;
            }

            $invoice = $this->toApiArray($invoiceModel, true, null, true);
            if (($invoice['total'] ?? 0) > 0
                && ($invoice['status'] ?? null) !== Invoice::STATUS_PAID
                && isset($invoice['client']['id'])
            ) {
                $this->sendInvoiceEmail($invoiceModel, $invoice, 'mod_invoice_created', (int) $invoice['client']['id']);
            }

            // Sending the created-email extends the hash lifetime so the
            // recipient has a fresh window to act on the link.
            $this->extendInvoiceHashLifetime($invoiceModel);
        } catch (\Exception $exc) {
            $this->di['logger']->withChannel('email')->error('Failed to send email for invoice approval', ['exception' => $exc]);
        }
    }

    private function sendInvoiceEmail(Invoice $invoice, array $invoiceData, string $templateCode, ?int $clientId = null, array $extraVars = []): void
    {
        $email = [
            'to_client' => $clientId ?? $invoice->getClientId(),
            'code' => $templateCode,
            'invoice' => $invoiceData,
        ] + $extraVars;
        $email = $this->withBillingRecipient($email, $invoiceData);

        $attachment = $this->getInvoicePdfAttachment($invoice);
        if ($attachment !== null) {
            $email['attachment'] = $attachment;
        }

        $this->di['mod_service']('email')->sendTemplate($email);
    }

    /**
     * Notify the client that their paid invoice was refunded as a credit note.
     */
    private function sendRefundEmail(Invoice $original, Invoice $creditNote): void
    {
        $creditNoteData = $this->toApiArray($creditNote);
        if (($creditNoteData['total'] ?? 0) >= 0 || $original->getClientId() === null) {
            return;
        }

        $this->sendInvoiceEmail(
            $creditNote,
            $creditNoteData,
            'mod_invoice_refunded',
            null,
            ['original_invoice' => $this->toApiArray($original)]
        );
        $this->extendInvoiceHashLifetime($creditNote);
    }

    public function sendInvoiceReminderEmail(AfterAdminInvoiceReminderRecordedEvent $event): void
    {
        $di = $this->di ?? throw new \LogicException('The Invoice service dependency injection container has not been set.');

        try {
            $invoiceModel = $di['em']->getRepository(Invoice::class)->find($event->invoiceId);
            if (!$invoiceModel instanceof Invoice) {
                return;
            }

            $invoice = $this->toApiArray($invoiceModel, true, null, true);
            $email = [];
            $email['to_client'] = $invoiceModel->getClientId();
            $email['code'] = 'mod_invoice_payment_reminder';
            $email['invoice'] = $invoice;
            $email = $this->withBillingRecipient($email, $invoice);
            $attachment = $this->getInvoicePdfAttachment($invoiceModel);
            if ($attachment !== null) {
                $email['attachment'] = $attachment;
            }
            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);

            // Sending a payment reminder also re-extends the hash lifetime
            // since the recipient is being re-engaged via the same link.
            $this->extendInvoiceHashLifetime($invoiceModel);
        } catch (\Throwable $exc) {
            $di['logger']->withChannel('email')->error('Failed to send invoice reminder email', ['exception' => $exc]);
        }
    }

    #[AsEventListener]
    public function onEventBeforeInvoiceIsDue(BeforeInvoiceIsDueEvent $event): void
    {
        $di = $this->di ?? throw new \LogicException('The Invoice service dependency injection container has not been set.');
        $claimed = false;

        try {
            if (!$this->isInvoiceReminderIntervalEnabled('invoice_reminder_before_due_days', $event->daysLeft, '', $event->reminderIntervals)) {
                return;
            }

            // Atomically claim the invoice before sending anything: this is what stops the same
            // reminder being sent twice when this event is dispatched more than once for the
            // same invoice (overlapping cron runs, the once-daily batch and the pending-reminder
            // fallback both firing it, etc).
            $now = new \DateTimeImmutable();
            $claimed = (bool) $di['em']->getConnection()->executeStatement(
                "UPDATE invoice SET reminded_at = :now, updated_at = :now WHERE id = :id AND status = 'unpaid' AND approved = true AND due_at > :now AND (reminded_at IS NULL OR reminded_at < :today_start)",
                [
                    'id' => $event->invoiceId,
                    'now' => $now->format('Y-m-d H:i:s'),
                    'today_start' => $now->modify('today')->format('Y-m-d H:i:s'),
                ]
            );
            if (!$claimed) {
                return;
            }

            $invoiceModel = $di['em']->getRepository(Invoice::class)->find($event->invoiceId);
            if ($invoiceModel instanceof Invoice) {
                $this->sendInvoiceReminder($invoiceModel);
            }
        } catch (\Exception $exc) {
            if ($claimed) {
                // sendInvoiceReminder() handles errors after recording separately. An exception
                // reaching here occurred before the reminder was recorded, so release the claim.
                $di['em']->getConnection()->executeStatement('UPDATE invoice SET reminded_at = NULL WHERE id = :id', ['id' => $event->invoiceId]);
            }
            $di['logger']->withChannel('email')->error('Failed to send invoice reminder email', ['id' => $event->invoiceId, 'exception' => $exc]);
        }
    }

    #[AsEventListener]
    public function removeExpiredUnpaidInvoices(AfterAdminCronRunEvent $event): void
    {
        $di = $this->di ?? throw new \LogicException('The Invoice service dependency injection container has not been set.');
        $systemService = $di['mod_service']('System');
        $remove_after_days = $systemService->getParamValue('remove_after_days');
        if (isset($remove_after_days) && $remove_after_days) {
            // removing old unpaid invoices, through rmInvoice() so related
            // orders, invoice items, and reserved resources stay consistent
            $days = (int) $remove_after_days;
            $service = $di['mod_service']('invoice');
            $invoices = $service->getInvoiceRepository()->findUnpaidOlderThan($days);
            foreach ($invoices as $invoiceModel) {
                $id = $invoiceModel->getId();
                $service->rmInvoice($invoiceModel);
                $di['logger']->info('Removed expired unpaid invoice #{id}', ['id' => $id]);
            }
        }
    }

    #[AsEventListener]
    public function onEventAfterInvoiceIsDue(AfterInvoiceIsDueEvent $event): void
    {
        $di = $this->di ?? throw new \LogicException('The Invoice service dependency injection container has not been set.');
        $claimed = false;

        try {
            if (!$this->isInvoiceReminderIntervalEnabled('invoice_reminder_after_due_days', $event->daysPassed, '5', $event->reminderIntervals)) {
                return;
            }

            // Atomically claim the invoice before sending anything: this is what stops the same
            // reminder being sent twice when this event is dispatched more than once for the
            // same invoice (overlapping cron runs, the once-daily batch and the pending-reminder
            // fallback both firing it, etc). The claim UPDATE already persists reminded_at and
            // updated_at, so there's no need to store the loaded model again once sent below.
            // due_at < :tomorrow_start is a portable stand-in for MySQL's
            // (due_at < NOW()) OR (ABS(DATEDIFF(due_at, NOW())) = 0): "already overdue, or due
            // sometime today" is exactly "due before the start of tomorrow".
            $now = new \DateTimeImmutable();
            $todayStart = $now->modify('today');
            $claimed = (bool) $di['em']->getConnection()->executeStatement(
                "UPDATE invoice SET reminded_at = :now, updated_at = :now WHERE id = :id AND status = 'unpaid' AND approved = true AND due_at < :tomorrow_start AND (reminded_at IS NULL OR reminded_at < :today_start)",
                [
                    'id' => $event->invoiceId,
                    'now' => $now->format('Y-m-d H:i:s'),
                    'today_start' => $todayStart->format('Y-m-d H:i:s'),
                    'tomorrow_start' => $todayStart->modify('+1 day')->format('Y-m-d H:i:s'),
                ]
            );
            if (!$claimed) {
                return;
            }

            $invoiceModel = $di['em']->getRepository(Invoice::class)->find($event->invoiceId);
            if (!$invoiceModel instanceof Invoice) {
                return;
            }

            $invoice = $this->toApiArray($invoiceModel, true, null, true);
            if (!isset($invoice['client']) || !is_array($invoice['client']) || !isset($invoice['client']['id'])) {
                throw new \FOSSBilling\Exception('Invoice client data is unavailable.');
            }

            $email = [];
            $email['to_client'] = $invoice['client']['id'];
            $email['code'] = 'mod_invoice_due_after';
            $email['days_passed'] = $event->daysPassed;
            $email['invoice'] = $invoice;
            $email = $this->withBillingRecipient($email, $invoice);
            $attachment = $this->getInvoicePdfAttachment($invoiceModel);
            if ($attachment !== null) {
                $email['attachment'] = $attachment;
            }

            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            if ($claimed) {
                // Nothing past sendTemplate() can throw, so reaching here with a claim already
                // made means the email was never confirmed queued. Release the claim so a later
                // cron run retries this invoice instead of losing the reminder.
                $di['em']->getConnection()->executeStatement('UPDATE invoice SET reminded_at = NULL WHERE id = :id', ['id' => $event->invoiceId]);
            }
            $di['logger']->withChannel('email')->error('Failed to send overdue invoice email', ['id' => $event->invoiceId, 'exception' => $exc]);
        }
    }

    /**
     * Route invoice notifications to the client's optional billing address while retaining
     * to_client so templates, timezone handling, and client email history keep working.
     * Uses the internal `client_billing_email` override validated by the email service.
     */
    public function withBillingRecipient(array $email, array $invoice): array
    {
        $billingEmail = trim((string) ($invoice['client']['billing_email'] ?? ''));
        if ($billingEmail !== '' && filter_var($billingEmail, FILTER_VALIDATE_EMAIL) !== false) {
            $email['client_billing_email'] = $billingEmail;
        }

        return $email;
    }

    public function markAsPaid(Invoice $invoice, $charge = true, $execute = false, bool $deferEvents = false): bool
    {
        /** @var InvoiceItem[] $invoiceItems */
        $invoiceItems = [];
        $paid = $this->di['em']->wrapInTransaction(function () use (&$invoiceItems, $invoice, $charge): bool {
            return $this->markAsPaidInTransaction($invoice, $charge, $invoiceItems);
        });

        // Another payment request may have acquired the row lock first and completed the payment.
        // Treat that as an idempotent success, but do not send duplicate events or execute tasks.
        if (!$paid) {
            return true;
        }

        // Listeners render PDFs and send email, so a caller holding row locks defers this until
        // after it has committed rather than holding them for the duration of an SMTP send.
        if (!$deferEvents) {
            $this->firePaymentReceivedEvent($invoice);
        }

        if ($execute) {
            $this->executeInvoiceItemTasks($invoiceItems, $this->di['mod_service']('Invoice', 'InvoiceItem'));
        }

        $this->di['logger']->info("Marked invoice {$invoice->getId()} as paid.");

        return true;
    }

    /**
     * Mark an invoice as paid while the caller owns its invoice-row lock.
     *
     * @param InvoiceItem[] $invoiceItems
     */
    private function markAsPaidInTransaction(Invoice $invoice, bool $charge, array &$invoiceItems): bool
    {
        $state = $this->lockAndRefreshInvoice($invoice);
        if ($state['status'] === Invoice::STATUS_PAID) {
            return false;
        }
        if ($state['status'] === Invoice::STATUS_CANCELED || $invoice->getReplacedByInvoiceId() !== null) {
            throw new InformationException('This invoice was canceled and cannot be marked as paid');
        }

        $invoiceItems = $this->getInvoiceItemRepository()->findByInvoiceId((int) $invoice->getId());
        $invoiceItemService = $this->di['mod_service']('Invoice', 'InvoiceItem');
        $systemService = $this->di['mod_service']('system');

        foreach ($invoiceItems as $item) {
            $invoiceItemService->markAsPaid($item, $charge);
        }

        $currencyService = $this->di['mod_service']('currency');
        /** @var \Box\Mod\Currency\Repository\CurrencyRepository $currencyRepository */
        $currencyRepository = $currencyService->getCurrencyRepository();

        $invoice->setSerie($systemService->getParamValue('invoice_series_paid'));
        $invoice->setApproved(true);

        $currencyRate = $currencyRepository->getRateByCode((string) $invoice->getCurrency());
        if ($currencyRate === null) {
            throw new \FOSSBilling\Exception("Currency rate for code '{$invoice->getCurrency()}' is not configured.");
        }
        $invoice->setCurrencyRate($currencyRate);

        $invoice->setStatus(Invoice::STATUS_PAID);
        $invoice->setPaidAt(new \DateTime());
        $this->di['em']->persist($invoice);
        $this->di['em']->flush();

        $this->countIncome($invoice);
        $productService = $this->di['mod_service']('Product');
        $productService->commitReservedPromoRedemptionsForInvoice($invoice);

        return true;
    }

    public function markAsPaidByAdmin(Invoice $invoice, array $data = []): bool
    {
        if ($invoice->getStatus() === Invoice::STATUS_PAID) {
            return true;
        }
        if ($invoice->getStatus() === Invoice::STATUS_CANCELED || $invoice->getReplacedByInvoiceId() !== null) {
            throw new InformationException('This invoice was canceled and cannot be marked as paid');
        }

        $execute = Tools::normalizeBoolean($data['execute'] ?? false);
        $payGateway = $this->validateAdminMarkAsPaidRequest($data, $invoice);
        $transactionId = isset($data['transactionId']) ? trim((string) $data['transactionId']) : null;

        if ((int) $invoice->getGateway()?->getId() !== (int) $payGateway->getId()) {
            $invoice->setGateway($payGateway);
            $this->di['em']->persist($invoice);
            $this->di['em']->flush();
        }

        if ($payGateway->getGateway() === 'Custom' && $payGateway->isEnabled()) {
            $paid = $this->di['em']->wrapInTransaction(function () use ($invoice, $payGateway, $transactionId): bool {
                // Re-validate under the invoice lock: the invoice may have
                // been canceled or replaced after the preflight check above.
                // Creating the transaction record in this transaction means a
                // rejection rolls it back instead of stranding a received
                // record on a canceled invoice.
                $state = $this->lockAndRefreshInvoice($invoice);
                if ($state['status'] === Invoice::STATUS_PAID) {
                    return false;
                }
                // Re-read through the repository: the entity getter was
                // already narrowed by the preflight check above, while the
                // row may have changed under us.
                $locked = $this->di['em']->getRepository(Invoice::class)->find($invoice->getId());
                if ($state['status'] === Invoice::STATUS_CANCELED || ($locked instanceof Invoice && $locked->getReplacedByInvoiceId() !== null)) {
                    throw new InformationException('This invoice was canceled and cannot be marked as paid');
                }

                $transactionService = $this->di['mod_service']('Invoice', 'Transaction');
                $invoiceTotal = $this->getTotalWithTax($invoice);
                $newtx = $transactionService->create([
                    'invoice_id' => $invoice->getId(),
                    'gateway_id' => $invoice->getGateway()?->getId(),
                    'currency' => $invoice->getCurrency(),
                    'status' => 'received',
                    'source' => 'admin',
                    'post' => [
                        'invoice_id' => $invoice->getId(),
                        'txn_id' => $transactionId,
                    ],
                    'txn_id' => $transactionId,
                ]);
                $transaction = $this->di['em']->getRepository(Transaction::class)->find((int) $newtx);
                if ($transaction === null) {
                    throw new InformationException('Transaction not found');
                }
                if ((int) $transaction->getInvoice()?->getId() !== (int) $invoice->getId()) {
                    throw new InformationException('Transaction ID is already associated with another invoice.');
                }

                $result = $this->markAsPaid($invoice, false, false, true);
                if ($result) {
                    $transaction->setAmount((string) $invoiceTotal);
                    $transaction->setCurrency($invoice->getCurrency());
                    $transaction->setStatus(Transaction::STATUS_PROCESSED);
                    $gatewayTitle = $payGateway->getName() ?: $payGateway->getGateway();
                    $transaction->setNote(sprintf('%s transaction No: %s', $gatewayTitle, $transactionId));
                    $transaction->setUpdatedAt(new \DateTime());
                    $this->di['em']->flush();
                }

                return $result;
            });

            // Events and tasks run after the commit above, so neither
            // notifications nor provisioning precede the recorded payment.
            if ($paid) {
                $this->firePaymentReceivedEvent($invoice);
                if ($execute) {
                    $this->executeInvoiceItemTasks(
                        $this->getInvoiceItemRepository()->findByInvoiceId((int) $invoice->getId()),
                        $this->di['mod_service']('Invoice', 'InvoiceItem')
                    );
                }
            }

            return $paid;
        }

        return $this->markAsPaid($invoice, false, $execute);
    }

    public function validateAdminMarkAsPaidRequest(array $data, ?Invoice $invoice = null): PayGateway
    {
        $gatewayId = isset($data['gateway_id']) && !empty($data['gateway_id']) ? (int) $data['gateway_id'] : $invoice?->getGateway()?->getId() ?? 0;
        if ($gatewayId <= 0) {
            throw new InformationException('Payment gateway is required when marking an invoice as paid.');
        }

        $payGateway = $this->di['em']->getRepository(PayGateway::class)->find($gatewayId);
        if ($payGateway === null) {
            throw new InformationException('Payment gateway not found');
        }
        if ($payGateway->getGateway() === 'Custom' && $payGateway->isEnabled()) {
            $transactionId = trim((string) ($data['transactionId'] ?? ''));
            if ($transactionId === '') {
                throw new InformationException('Transaction ID is required when using the Custom payment gateway.');
            }
        }

        return $payGateway;
    }

    /**
     * Finds all paid invoices associated with a given client order.
     *
     * @param Order $order the client order for which to find paid invoices
     *
     * @return array An array of paid invoices. Each element in the array represents an invoice record
     *               as returned by the database, typically as an associative array or an object.
     */
    public function findPaidInvoicesForOrder(Order $order): array
    {
        return $this->getInvoiceRepository()->findPaidByRelId($order->getId());
    }

    public function getNextInvoiceNumber()
    {
        $systemService = $this->di['mod_service']('system');

        // Claimed and advanced in one locked step, otherwise two concurrent approvals take the
        // same number and issue two invoices sharing an invoice number.
        $next_nr = $systemService->reserveNextNumericParamValue('invoice_starting_number');

        if ($next_nr === null) {
            // In theory this code should never need to be called, but is provided as a fallback
            $r = $this->getInvoiceRepository()->findLatestWithNr();
            if (!$r instanceof Invoice || !is_numeric($r->getNr())) {
                throw new \FOSSBilling\Exception('Unable to determine the next invoice number');
            }

            // Seeding the counter and reserving from it has to be one locked step too, otherwise
            // two callers deriving the same seed both write it and both reserve the same number.
            $next_nr = $systemService->reserveNextNumericParamValue('invoice_starting_number', intval($r->getNr()) + 1);
            if ($next_nr === null) {
                throw new \FOSSBilling\Exception('Unable to determine the next invoice number');
            }
        }

        return $next_nr;
    }

    public function countIncome(Invoice $invoice): void
    {
        $table = $this->di['mod_service']('currency');

        $invoice->setBaseIncome($table->toBaseCurrency($invoice->getCurrency(), $this->getTotal($invoice)));
        if ($invoice->getRefund() !== null) {
            $invoice->setBaseRefund($table->toBaseCurrency($invoice->getCurrency(), (float) $invoice->getRefund()));
        } else {
            $invoice->setBaseRefund(null);
        }

        $this->di['em']->persist($invoice);
        $this->di['em']->flush();
    }

    public function prepareInvoice(Client $client, array $data): Invoice
    {
        if (!$client->getCurrency()) {
            $currencyService = $this->di['mod_service']('currency');
            /** @var \Box\Mod\Currency\Repository\CurrencyRepository $currencyRepository */
            $currencyRepository = $currencyService->getCurrencyRepository();
            $currency = $currencyRepository->findDefault();

            if (!$currency instanceof Currency) {
                throw new \FOSSBilling\Exception('Default currency not found');
            }

            $currencyCode = $currency->getCode();
            $client->setCurrency($currencyCode);
            $this->di['em']->persist($client);
            $this->di['em']->flush();
            if (isset($this->di['logger'])) {
                $this->di['logger']->info('Client #{client_id} currency was not defined. Set default currency {currency_code}.', ['client_id' => $client->getId(), 'currency_code' => $currencyCode]);
            }
        }

        $model = new Invoice();
        $model->setClientId($client->getId() ?? null);
        $model->setStatus(Invoice::STATUS_UNPAID);
        $model->setCurrency($client->getCurrency());
        $model->setApproved(false);

        if (!empty($data['gateway_id'])) {
            $gateway = $this->di['em']->getRepository(PayGateway::class)->find((int) $data['gateway_id']);
            if (!$gateway instanceof PayGateway) {
                throw new InformationException('Payment gateway not found');
            }
            $model->setGateway($gateway);
        }
        $model->setText1($data['text_1'] ?? $model->getText1());
        $model->setText2($data['text_2'] ?? $model->getText2());
        $this->di['em']->persist($model);
        $this->di['em']->flush();
        $invoiceId = $model->getId();

        $this->setInvoiceDefaults($model);

        if (isset($data['items']) && is_array($data['items'])) {
            $invoiceItemService = $this->di['mod_service']('Invoice', 'InvoiceItem');
            foreach ($data['items'] as $d) {
                $invoiceItemService->addNew($model, $d);
            }
        }

        $this->di['logger']->info("Prepared new invoice {$invoiceId}.");

        if (isset($data['approve']) && $data['approve']) {
            try {
                $this->approveInvoice($model, ['id' => $invoiceId]);
                $this->di['logger']->info("Approved invoice {$invoiceId} instantly.");
            } catch (\Exception $e) {
                $this->di['logger']->warning($e->getMessage());
            }
        }

        return $model;
    }

    public function setInvoiceDefaults(Invoice $model): void
    {
        $clientService = $this->di['mod_service']('Client');
        $systemService = $this->di['mod_service']('system');
        $client = $this->di['em']->getRepository(Client::class)->find($model->getClientId());
        $seller = $systemService->getCompany();

        $buyer = $client instanceof Client
            ? $clientService->toApiArray($client)
            : array_fill_keys([
                'first_name', 'last_name', 'company', 'company_vat', 'company_number',
                'address_1', 'address_2', 'city', 'state', 'country',
                'phone_cc', 'phone', 'email', 'postcode',
            ], null);

        $model->setSellerCompany($seller['name']);
        $model->setSellerCompanyVat($seller['vat_number']);
        $model->setSellerCompanyNumber($seller['number']);
        $model->setSellerAddress(trim("{$seller['address_1']} {$seller['address_2']} {$seller['address_3']}"));
        $model->setSellerPhone($seller['tel']);
        $model->setSellerEmail($seller['email']);

        $model->setBuyerFirstName($buyer['first_name']);
        $model->setBuyerLastName($buyer['last_name']);
        $model->setBuyerCompany($buyer['company']);
        $model->setBuyerCompanyVat($buyer['company_vat']);
        $model->setBuyerCompanyNumber($buyer['company_number']);
        $model->setBuyerAddress("{$buyer['address_1']} {$buyer['address_2']}");
        $model->setBuyerCity($buyer['city']);
        $model->setBuyerState($buyer['state']);
        $model->setBuyerCountry($buyer['country']);
        $model->setBuyerPhone("{$buyer['phone_cc']} {$buyer['phone']}");
        $model->setBuyerEmail($buyer['email']);
        $model->setBuyerZip($buyer['postcode']);

        $invoice_due_days = $systemService->getParamValue('invoice_due_days');
        if (!is_numeric($invoice_due_days)) {
            $invoice_due_days = 1;
        }
        $due_time = strtotime("+{$invoice_due_days} day");
        $model->setDueAt(new \DateTime(date('Y-m-d H:i:s', $due_time)));

        $serie = $systemService->getParamValue('invoice_series');
        $model->setSerie($serie !== null ? (string) $serie : null);
        $model->setNr($this->getNextInvoiceNumber());
        $model->setHash(bin2hex(random_bytes(random_int(15, 30))));
        $model->setHashExpiresAt($this->computeHashExpiration());

        $taxtitle = '';
        $taxService = $this->di['mod_service']('Invoice', 'Tax');
        $tax = $taxService->getTaxRateForClient($client, $taxtitle);
        $model->setTaxname($taxtitle);
        $model->setTaxrate($tax);

        $notes = $this->di['mod_service']('system')->getParamValue('invoice_default_note');
        $model->setNotes($notes !== null ? (string) $notes : null);

        $this->di['em']->persist($model);
        $this->di['em']->flush();
    }

    public function approveInvoice(Invoice $invoice, array $data): bool
    {
        $this->di['event_dispatcher']->dispatch(new BeforeAdminInvoiceApproveEvent((int) $invoice->getId()));

        $this->di['em']->wrapInTransaction(function () use ($invoice): void {
            $this->lockAndRefreshInvoice($invoice);
            $invoice->setApproved(true);
            $this->di['em']->persist($invoice);
            $this->di['em']->flush();
        });

        if (isset($data['use_credits']) && $data['use_credits']) {
            $this->tryPayWithCredits($invoice);
        }

        $this->di['event_dispatcher']->dispatch(new AfterAdminInvoiceApproveEvent((int) $invoice->getId()));

        $this->di['logger']->info("Approved invoice {$invoice->getId()}.");

        return true;
    }

    public function validatePaymentAmount(float $received, float $expected): void
    {
        $epsilon = 0.01;
        if ($received < $expected - $epsilon) {
            throw new \FOSSBilling\Exception('Payment amount does not match the expected invoice total. Expected :expected, received :received.', [':expected' => number_format($expected, 2, '.', ''), ':received' => number_format($received, 2, '.', '')]);
        }

        // Warn on significant overpayments — this can indicate a misdirected
        // payment applied to the wrong invoice.
        $overpaymentTolerance = 1.00;
        if ($received > $expected + $overpaymentTolerance) {
            $this->di['logger']->warning(
                'Payment amount significantly exceeds the expected invoice total. Expected {expected}, received {received}.',
                ['expected' => number_format($expected, 2, '.', ''), 'received' => number_format($received, 2, '.', '')]
            );
        }
    }

    public function tryPayWithCredits(Invoice $invoice): bool
    {
        $paid = $this->di['em']->wrapInTransaction(function () use ($invoice): bool {
            // Refresh after locking so a stale entity cannot authorize a credit deduction.
            $state = $this->lockAndRefreshInvoice($invoice);
            if (!$state['approved']) {
                return false;
            }
            if ($state['status'] === Invoice::STATUS_PAID) {
                return false;
            }

            $clientId = (int) $invoice->getClientId();
            $cbrepo = $this->di['mod_service']('Client', 'Balance');

            // Locks the balance for the rest of this transaction, so a concurrent request cannot
            // spend the same credit.
            $balance = $cbrepo->getClientBalanceForUpdate($clientId);

            $required = $this->getTotalWithTax($invoice);
            // Compare at two-decimal monetary scale: balances are DECIMAL(18,2) sums while the
            // total is float arithmetic, so e.g. 0.30 and 0.1 * 3 differ as raw floats.
            if (round($balance, 2) < round($required, 2)) {
                // @phpstan-ignore if.alwaysFalse (DEBUG is a runtime constant that may be true during debugging)
                if (DEBUG) {
                    $this->di['logger']->withChannel('billing')->info("Invoice {$invoice->getId()} could not be paid with credits. Money in balance {$balance} Required: {$required}.");
                }

                return false;
            }

            // @phpstan-ignore if.alwaysFalse
            if (DEBUG) {
                $this->di['logger']->withChannel('billing')->info("Setting invoice {$invoice->getId()} as paid with credits for the amount of {$required}.");
            }

            if ($required > 0.0) {
                // Nothing is charged against the client's balance for a zero or negative invoice,
                // so don't record a $0 credit transaction.
                $balanceTransaction = new ClientBalance();
                $balanceTransaction->setClient($this->di['em']->getReference(Client::class, $clientId));
                $balanceTransaction->setType('invoice');
                $balanceTransaction->setRelId((string) $invoice->getId());

                $invoice_identifier = $invoice->getNr() ?: $invoice->getId();
                $balanceTransaction->setDescription("Payment for invoice #{$invoice_identifier} using account credit.");

                $balanceTransaction->setAmount((string) (-$required));
                $this->di['em']->persist($balanceTransaction);
                $this->di['em']->flush();
            }

            // Events and tasks run after the commit below, so neither notifications nor
            // provisioning are held under the balance lock.
            $this->markAsPaid($invoice, false, false, true);

            return true;
        });

        if ($paid) {
            $this->firePaymentReceivedEvent($invoice);
            $this->executeInvoiceItemTasks(
                $this->getInvoiceItemRepository()->findByInvoiceId((int) $invoice->getId()),
                $this->di['mod_service']('Invoice', 'InvoiceItem')
            );
        }

        return $paid;
    }

    private function firePaymentReceivedEvent(Invoice $invoice): void
    {
        $this->di['event_dispatcher']->dispatch(new AfterAdminInvoicePaymentReceivedEvent((int) $invoice->getId()));
    }

    /**
     * Execute invoice-item tasks after the payment transaction has committed.
     *
     * @param InvoiceItem[]      $invoiceItems
     * @param ServiceInvoiceItem $invoiceItemService
     */
    private function executeInvoiceItemTasks(array $invoiceItems, $invoiceItemService): void
    {
        foreach ($invoiceItems as $item) {
            try {
                $invoiceItemService->executeTask($item);
            } catch (\Exception $e) {
                $this->di['logger']->warning($e->getMessage());
            }
        }
    }

    public function getTotalWithTax(Invoice $invoice): float
    {
        return $this->getTotal($invoice) + $this->getTax($invoice);
    }

    public function getTax(Invoice $invoice): float
    {
        if ($invoice->getTaxrate() <= 0) {
            return 0.0;
        }

        $items = $this->getInvoiceItemRepository()->findByInvoiceId((int) $invoice->getId());

        if (empty($items)) {
            return 0.0;
        }

        $taxable_subtotal = 0.0;
        foreach ($items as $item) {
            if ($item->getTaxed()) {
                $taxable_subtotal += (($item->getPrice() ?? 0) * ($item->getQuantity() ?? 1));
            }
        }

        if ($taxable_subtotal == 0) {
            return 0.0;
        }

        return round($taxable_subtotal * (float) $invoice->getTaxrate() / 100, 2);
    }

    public function getTotal(Invoice $invoice): float
    {
        $total = 0;
        $invoiceItems = $this->getInvoiceItemRepository()->findByInvoiceId((int) $invoice->getId());
        $invoiceItemService = $this->di['mod_service']('Invoice', 'InvoiceItem');
        foreach ($invoiceItems as $item) {
            $total += $invoiceItemService->getTotal($item);
        }

        return (float) $total;
    }

    public function refundInvoice(Invoice $invoice, $note = null, ?array $items = null): ?int
    {
        $this->di['event_dispatcher']->dispatch(new BeforeAdminInvoiceRefundEvent((int) $invoice->getId()));

        $systemService = $this->di['mod_service']('system');
        $logic = $systemService->getParamValue('invoice_refund_logic', 'manual');
        $result = null;

        switch ($logic) {
            case 'credit_note':
            case 'negative_invoice':
                $new = $this->di['em']->wrapInTransaction(function () use ($invoice, $items, $logic, $note, $systemService): Invoice {
                    // Reserve the number before any invoice reads. SQLite must acquire its write
                    // lock before the locking read; the outer transaction rolls this back on failure.
                    $nextNumber = $logic === 'negative_invoice'
                        ? $this->getNextInvoiceNumber()
                        : $systemService->reserveNextNumericParamValue('invoice_cn_starting_number', 1);
                    if ($nextNumber === null) {
                        throw new \FOSSBilling\Exception('Unable to determine the next invoice number');
                    }

                    // Use the current locked status rather than the possibly stale entity state.
                    if ($this->getInvoiceRepository()->lockAndGetStatus((int) $invoice->getId()) !== Invoice::STATUS_PAID) {
                        throw new InformationException('Only paid invoices can be refunded');
                    }
                    $this->di['em']->refresh($invoice);

                    $total = $this->getTotalWithTax($invoice);
                    if ($total <= 0) {
                        throw new InformationException('Cannot refund invoice with negative amount');
                    }

                    $creditLines = $this->resolveRefundLines($invoice, $items);

                    $new = new Invoice();
                    $new->setClientId($invoice->getClientId());
                    $new->setCreditNoteForInvoiceId($invoice->getId());
                    $new->setHash(bin2hex(random_bytes(random_int(15, 30))));
                    $new->setHashExpiresAt($this->computeHashExpiration());
                    $new->setStatus(Invoice::STATUS_REFUNDED);
                    $new->setCurrency($invoice->getCurrency());
                    $new->setApproved(true);
                    $new->setTaxname($invoice->getTaxname());
                    $new->setTaxrate($invoice->getTaxrate());

                    $new->setSellerCompany($invoice->getSellerCompany());
                    $new->setSellerCompanyVat($invoice->getSellerCompanyVat());
                    $new->setSellerCompanyNumber($invoice->getSellerCompanyNumber());
                    $new->setSellerAddress($invoice->getSellerAddress());
                    $new->setSellerPhone($invoice->getSellerPhone());
                    $new->setSellerEmail($invoice->getSellerEmail());

                    $new->setBuyerFirstName($invoice->getBuyerFirstName());
                    $new->setBuyerLastName($invoice->getBuyerLastName());
                    $new->setBuyerCompany($invoice->getBuyerCompany());
                    $new->setBuyerCompanyVat($invoice->getBuyerCompanyVat());
                    $new->setBuyerCompanyNumber($invoice->getBuyerCompanyNumber());
                    $new->setBuyerAddress($invoice->getBuyerAddress());
                    $new->setBuyerCity($invoice->getBuyerCity());
                    $new->setBuyerState($invoice->getBuyerState());
                    $new->setBuyerCountry($invoice->getBuyerCountry());
                    $new->setBuyerPhone($invoice->getBuyerPhone());
                    $new->setBuyerPhoneCc($invoice->getBuyerPhoneCc());
                    $new->setBuyerEmail($invoice->getBuyerEmail());
                    $new->setBuyerZip($invoice->getBuyerZip());
                    $new->setText1($invoice->getText1());
                    $new->setText2($invoice->getText2());
                    $new->setSerie($logic === 'negative_invoice'
                        ? $systemService->getParamValue('invoice_series_paid')
                        : $systemService->getParamValue('invoice_cn_series', 'CN-'));
                    $new->setNr($nextNumber);

                    $new->setPaidAt(new \DateTime());
                    $this->di['em']->persist($new);
                    $this->di['em']->flush();

                    $entityManager = $this->di['em'];
                    foreach ($creditLines as [$item, $qty]) {
                        $pi = new InvoiceItem();
                        $pi->setInvoice($new);
                        $pi->setType($item->getType());
                        $pi->setRelId($item->getRelId());
                        $pi->setRefundedItemId($item->getId());
                        $pi->setTask($item->getTask());
                        $pi->setStatus(InvoiceItem::STATUS_EXECUTED); // Mark refund invoice as executed
                        $pi->setTitle($item->getTitle());
                        $pi->setPeriod($item->getPeriod());
                        $pi->setQuantity($qty);
                        $pi->setUnit($item->getUnit());
                        $pi->setCharged(1);
                        $pi->setPrice(-($item->getPrice() ?? 0));
                        $pi->setTaxed($item->getTaxed());
                        $entityManager->persist($pi);
                    }
                    $entityManager->flush();

                    // Credit notes carry no income themselves; the offset is
                    // tracked on the original below so reporting nets out.
                    $creditAmount = round(abs($this->getTotalWithTax($new)), 2);
                    $remaining = round($total - (float) ($invoice->getRefund() ?? 0), 2);
                    if ($creditAmount - $remaining > 0.005) {
                        throw new InformationException('Refund amount exceeds the remaining refundable amount of :amount', [':amount' => $remaining]);
                    }

                    $invoice->setRefund(round((float) ($invoice->getRefund() ?? 0) + $creditAmount, 2));
                    if (round($total - (float) $invoice->getRefund(), 2) <= 0.005) {
                        $invoice->setStatus(Invoice::STATUS_REFUNDED);
                    }
                    $this->countIncome($invoice);

                    $this->addNote($invoice, "Refund invoice #{$new->getId()} generated.");
                    $this->addNote($new, "Refund for #{$invoice->getId()} invoice.");
                    if (!empty($note)) {
                        $this->addNote($new, $note);
                    }

                    return $new;
                });
                $result = (int) $new->getId();

                try {
                    $this->sendRefundEmail($invoice, $new);
                } catch (\Throwable $exception) {
                    $this->di['logger']->withChannel('email')->error('Failed to send refund email', [
                        'invoice_id' => $invoice->getId(),
                        'credit_note_id' => $new->getId(),
                        'exception' => $exception,
                    ]);
                }

                break;

            case 'manual':
                // @phpstan-ignore if.alwaysFalse
                if (DEBUG) {
                    $this->di['logger']->warning('Refunds are managed manually. No actions performed.');
                }

                break;
            default:
                break;
        }

        $this->di['event_dispatcher']->dispatch(new AfterAdminInvoiceRefundEvent((int) $invoice->getId()));

        $this->di['logger']->info("Refunded invoice #{$invoice->getId()}.");

        return $result;
    }

    /**
     * Resolve which lines a refund credits: every line at its remaining
     * quantity when no selection is given, otherwise the selected
     * line/quantity pairs validated against their remainders.
     *
     * @return list<array{InvoiceItem, int}>
     */
    private function resolveRefundLines(Invoice $invoice, ?array $items): array
    {
        $invoiceItems = $this->getInvoiceItemRepository()->findByInvoiceId((int) $invoice->getId());
        $credited = $this->getCreditedQuantities($invoice);

        if (empty($items)) {
            $lines = [];
            foreach ($invoiceItems as $item) {
                $remaining = ($item->getQuantity() ?? 1) - ($credited[(int) $item->getId()] ?? 0);
                if ($remaining > 0) {
                    $lines[] = [$item, $remaining];
                }
            }
            if ($lines === [] && $invoiceItems !== []) {
                throw new InformationException('No remaining refundable amount on this invoice');
            }

            return $lines;
        }

        $byId = [];
        foreach ($invoiceItems as $item) {
            $byId[(int) $item->getId()] = $item;
        }

        $lines = [];
        foreach ($items as $id => $qty) {
            $id = (int) $id;
            if (!isset($byId[$id])) {
                throw new InformationException('Invoice line #:id was not found', [':id' => $id]);
            }
            if (!is_numeric($qty)) {
                throw new InformationException('Refund quantity must be a valid number.');
            }
            if ((float) $qty <= 0) {
                continue;
            }
            $item = $byId[$id];
            if (($item->getPrice() ?? 0) <= 0) {
                throw new InformationException('Only charge lines can be refunded');
            }
            $remaining = ($item->getQuantity() ?? 1) - ($credited[$id] ?? 0);
            $qty = PriceValidator::validateQuantity($qty);
            if ($qty > $remaining) {
                throw new InformationException('Refund quantity exceeds the remaining refundable quantity of :max', [':max' => $remaining]);
            }
            $lines[] = [$item, $qty];
        }

        if ($lines === []) {
            throw new InformationException('No invoice lines selected for refund');
        }

        return $lines;
    }

    /**
     * Credit notes issued against the given invoice, newest last.
     *
     * @return list<Invoice>
     */
    private function findRefundingInvoices(Invoice $invoice): array
    {
        return $this->di['em']->getRepository(Invoice::class)->findBy(['creditNoteForInvoiceId' => $invoice->getId()]);
    }

    /**
     * Display references (id => number) for every invoice related to the
     * given one, loaded in a single query for detail views.
     *
     * @return array<int, string>
     */
    public function getRelatedInvoiceReferences(Invoice $invoice): array
    {
        $ids = array_filter([
            $invoice->getCreditNoteForInvoiceId(),
            $invoice->getDebitNoteForInvoiceId(),
        ]);
        foreach ($this->findRefundingInvoices($invoice) as $creditNote) {
            $ids[] = $creditNote->getId();
        }
        foreach ($this->getDebitingInvoiceIds($invoice) as $id) {
            $ids[] = $id;
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return [];
        }

        $references = [];
        $related = $this->di['em']->getRepository(Invoice::class)->findBy(['id' => $ids]);
        foreach ($related as $item) {
            $nr = is_numeric($item->getNr()) ? (int) $item->getNr() : (int) $item->getId();
            $references[(int) $item->getId()] = $this->formatSerieNr($item->getSerie(), $nr);
        }

        return $references;
    }

    /**
     * Remaining refundable quantity per invoice line, after earlier partials.
     *
     * @return array<int, int> line id => remaining qty
     */
    public function getLineRemainingQuantities(Invoice $invoice): array
    {
        $remaining = [];
        $credited = $this->getCreditedQuantities($invoice);
        $items = $this->getInvoiceItemRepository()->findByInvoiceId((int) $invoice->getId());
        foreach ($items as $item) {
            $remaining[(int) $item->getId()] = max(0, ($item->getQuantity() ?? 1) - ($credited[(int) $item->getId()] ?? 0));
        }

        return $remaining;
    }

    /**
     * Quantities already credited per source line across all credit notes
     * issued against the given invoice.
     *
     * @return array<int, int> source line id => credited quantity
     */
    private function getCreditedQuantities(Invoice $invoice): array
    {
        $credited = [];
        foreach ($this->findRefundingInvoices($invoice) as $creditNote) {
            $items = $this->getInvoiceItemRepository()->findByInvoiceId((int) $creditNote->getId());
            foreach ($items as $item) {
                $sourceId = $item->getRefundedItemId();
                if ($sourceId !== null) {
                    $credited[$sourceId] = ($credited[$sourceId] ?? 0) + ($item->getQuantity() ?? 0);
                }
            }
        }

        return $credited;
    }

    /**
     * Issue a debit note against an approved invoice: a separate payable
     * document for charges the original missed. The original keeps its status;
     * only the link and notes tie the two together. Debit lines are inert
     * custom lines so paying the note never provisions anything.
     *
     * @param list<array{title?: string, price?: mixed, quantity?: mixed, taxed?: mixed, unit?: string}> $items
     */
    public function debitInvoice(Invoice $invoice, array $items, $note = null): int
    {
        $this->di['event_dispatcher']->dispatch(new BeforeAdminInvoiceDebitEvent((int) $invoice->getId()));

        if (!$this->isDebitable($invoice)) {
            throw new InformationException('Only approved unpaid or paid invoices can be debited');
        }

        // Validate every line before anything is written.
        $debitLines = $this->resolveDebitLines($items);
        $systemService = $this->di['mod_service']('system');

        $new = $this->di['em']->wrapInTransaction(function () use ($invoice, $note, $debitLines, $systemService): Invoice {
            // Reserve the number before any invoice reads. SQLite must acquire its write
            // lock before the locking read; the outer transaction rolls this back on failure.
            $next_nr = $systemService->reserveNextNumericParamValue('invoice_dn_starting_number', 1);
            if ($next_nr === null) {
                throw new \FOSSBilling\Exception('Unable to determine the next debit note number');
            }

            // Recheck eligibility against the locked state; the invoice may
            // have been paid, canceled, or refunded while waiting on the lock.
            $state = $this->lockAndRefreshInvoice($invoice);
            if (!$state['approved']
                || !in_array($state['status'], [Invoice::STATUS_UNPAID, Invoice::STATUS_PAID], true)
                || $invoice->getCreditNoteForInvoiceId() !== null
            ) {
                throw new InformationException('Only approved unpaid or paid invoices can be debited');
            }

            $new = new Invoice();
            $new->setClientId($invoice->getClientId());
            $new->setDebitNoteForInvoiceId($invoice->getId());
            $new->setHash(bin2hex(random_bytes(random_int(15, 30))));
            $new->setHashExpiresAt($this->computeHashExpiration());
            $new->setStatus(Invoice::STATUS_UNPAID);
            $new->setCurrency($invoice->getCurrency());
            $new->setApproved(true);
            $new->setTaxname($invoice->getTaxname());
            $new->setTaxrate($invoice->getTaxrate());

            $new->setSellerCompany($invoice->getSellerCompany());
            $new->setSellerCompanyVat($invoice->getSellerCompanyVat());
            $new->setSellerCompanyNumber($invoice->getSellerCompanyNumber());
            $new->setSellerAddress($invoice->getSellerAddress());
            $new->setSellerPhone($invoice->getSellerPhone());
            $new->setSellerEmail($invoice->getSellerEmail());

            $new->setBuyerFirstName($invoice->getBuyerFirstName());
            $new->setBuyerLastName($invoice->getBuyerLastName());
            $new->setBuyerCompany($invoice->getBuyerCompany());
            $new->setBuyerCompanyVat($invoice->getBuyerCompanyVat());
            $new->setBuyerCompanyNumber($invoice->getBuyerCompanyNumber());
            $new->setBuyerAddress($invoice->getBuyerAddress());
            $new->setBuyerCity($invoice->getBuyerCity());
            $new->setBuyerState($invoice->getBuyerState());
            $new->setBuyerCountry($invoice->getBuyerCountry());
            $new->setBuyerPhone($invoice->getBuyerPhone());
            $new->setBuyerPhoneCc($invoice->getBuyerPhoneCc());
            $new->setBuyerEmail($invoice->getBuyerEmail());
            $new->setBuyerZip($invoice->getBuyerZip());
            $new->setText1($invoice->getText1());
            $new->setText2($invoice->getText2());
            $new->setSerie($systemService->getParamValue('invoice_dn_series', 'DN-'));
            $new->setNr($next_nr);

            $invoice_due_days = $systemService->getParamValue('invoice_due_days');
            if (!is_numeric($invoice_due_days)) {
                $invoice_due_days = 1;
            }
            $new->setDueAt(new \DateTime(date('Y-m-d H:i:s', strtotime("+{$invoice_due_days} day"))));
            $this->di['em']->persist($new);
            $this->di['em']->flush();

            $entityManager = $this->di['em'];
            foreach ($debitLines as [$title, $price, $quantity, $unit, $taxed]) {
                $pi = new InvoiceItem();
                $pi->setInvoice($new);
                $pi->setType(InvoiceItem::TYPE_CUSTOM);
                $pi->setTask(InvoiceItem::TASK_VOID);
                $pi->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT);
                $pi->setTitle($title);
                $pi->setQuantity($quantity);
                $pi->setUnit($unit);
                $pi->setCharged(0);
                $pi->setPrice($price);
                $pi->setTaxed($taxed);
                $entityManager->persist($pi);
            }
            $entityManager->flush();

            $this->countIncome($new);

            $this->addNote($invoice, "Debit invoice #{$new->getId()} generated.");
            $this->addNote($new, "Debit for #{$invoice->getId()} invoice.");
            if (!empty($note)) {
                $this->addNote($new, $note);
            }

            return $new;
        });
        $result = (int) $new->getId();

        try {
            $this->sendDebitEmail($invoice, $new);
        } catch (\Throwable $exception) {
            $this->di['logger']->withChannel('email')->error('Failed to send debit email', [
                'invoice_id' => $invoice->getId(),
                'debit_note_id' => $new->getId(),
                'exception' => $exception,
            ]);
        }

        $this->di['event_dispatcher']->dispatch(new AfterAdminInvoiceDebitEvent((int) $invoice->getId(), $result));

        $this->di['logger']->info("Debited invoice #{$invoice->getId()}.");

        return $result;
    }

    private function isDebitable(Invoice $invoice): bool
    {
        return $invoice->isApproved()
            && in_array($invoice->getStatus(), [Invoice::STATUS_UNPAID, Invoice::STATUS_PAID], true)
            && $invoice->getCreditNoteForInvoiceId() === null;
    }

    /**
     * Validate and normalize debit lines before anything is written, so a bad
     * line can never leave a half-created debit note behind.
     *
     * @return list<array{string, float, int, ?string, bool}>
     */
    private function resolveDebitLines(array $items): array
    {
        $lines = [];
        foreach ($items as $entry) {
            if (!is_array($entry)) {
                throw new InformationException('Debit lines are invalid');
            }
            $title = trim((string) ($entry['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $price = PriceValidator::validateAmount($entry['price'] ?? 0, 'Price');
            if ($price <= 0) {
                throw new InformationException('Debit lines must charge a positive amount');
            }
            $lines[] = [
                $title,
                $price,
                PriceValidator::validateQuantity($entry['quantity'] ?? 1),
                $entry['unit'] ?? null,
                (bool) ($entry['taxed'] ?? false),
            ];
        }

        if ($lines === []) {
            throw new InformationException('No debit lines given');
        }

        return $lines;
    }

    /**
     * Notify the client that a debit note was issued against their invoice.
     */
    private function sendDebitEmail(Invoice $original, Invoice $debitNote): void
    {
        $debitNoteData = $this->toApiArray($debitNote);
        if (($debitNoteData['total'] ?? 0) <= 0 || $original->getClientId() === null) {
            return;
        }

        $this->sendInvoiceEmail(
            $debitNote,
            $debitNoteData,
            'mod_invoice_debited',
            null,
            ['original_invoice' => $this->toApiArray($original)]
        );
        $this->extendInvoiceHashLifetime($debitNote);
    }

    /**
     * Attach a product to an editable invoice by creating its order and adding
     * it as an order line, so paying the invoice provisions the service.
     *
     * Accepts either `order_id` (attach an existing pending order) or
     * `product_id` with `quantity`, `price` (optional override, zero allowed),
     * `period`, `config` (product custom order form values), `group_id`
     * (attach as an addon of that order group) and `title`.
     *
     * @return int the attached order id
     */
    public function attachOrderToInvoice(Invoice $invoice, array $data): int
    {
        $this->di['event_dispatcher']->dispatch(new BeforeAdminInvoiceAttachOrderEvent((int) $invoice->getId()));

        if (!$this->isInvoiceEditable($invoice)) {
            throw new InformationException('This invoice can no longer be edited. Approved invoices are locked once issued; correct them with a credit note or a replacement invoice.');
        }

        $payload = $this->resolveAttachOrderPayload($data);

        $order = $this->di['em']->wrapInTransaction(function () use ($invoice, $payload): Order {
            $this->lockAndRefreshInvoice($invoice);
            if (!$this->isInvoiceEditable($invoice)) {
                throw new InformationException('This invoice can no longer be edited. Approved invoices are locked once issued; correct them with a credit note or a replacement invoice.');
            }

            return $this->createAndAttachOrder($invoice, $payload);
        });

        // Drafts are sent by the approval path; only re-send issued invoices.
        // The send is failure-tolerant: the order and line are already
        // committed, and a retry must not create a second order.
        if ($invoice->isApproved()) {
            try {
                $this->resendUpdatedInvoice($invoice);
            } catch (\Throwable $exception) {
                $this->di['logger']->withChannel('email')->error('Failed to send updated invoice email', [
                    'invoice_id' => $invoice->getId(),
                    'order_id' => $order->getId(),
                    'exception' => $exception,
                ]);
            }
        }

        $this->di['event_dispatcher']->dispatch(new AfterAdminInvoiceAttachOrderEvent((int) $invoice->getId(), (int) $order->getId()));

        $this->di['logger']->info("Attached order {$order->getId()} to invoice {$invoice->getId()}.");

        return (int) $order->getId();
    }

    /**
     * Cancel an approved unpaid invoice and issue a replacement carrying its
     * lines forward, so the correction keeps a clean audit trail while the
     * client pays a single invoice. The replacement takes the next invoice
     * number; the original number stays with the canceled record.
     *
     * Accepts an optional `reason` note plus the same product payload as
     * attachOrderToInvoice to add one order to the replacement in the same step.
     *
     * @return int the replacement invoice id
     */
    public function reissueInvoice(Invoice $original, array $data = []): int
    {
        $this->di['event_dispatcher']->dispatch(new BeforeAdminInvoiceReissueEvent((int) $original->getId()));

        if (!$original->isApproved() || $original->getStatus() !== Invoice::STATUS_UNPAID) {
            throw new InformationException('Only approved unpaid invoices can be reissued');
        }
        if ($original->getCreditNoteForInvoiceId() !== null || $original->getDebitNoteForInvoiceId() !== null) {
            throw new InformationException('Credit and debit notes cannot be reissued');
        }
        if ($original->getReplacedByInvoiceId() !== null) {
            throw new InformationException('This invoice has already been reissued as invoice #:id', [':id' => $original->getReplacedByInvoiceId()]);
        }

        $attachPayload = (!empty($data['order_id']) || !empty($data['product_id']))
            ? $this->resolveAttachOrderPayload($data)
            : null;
        $reason = trim((string) ($data['reason'] ?? ''));
        $systemService = $this->di['mod_service']('system');

        $new = $this->di['em']->wrapInTransaction(function () use ($original, $attachPayload, $reason, $systemService): Invoice {
            // Reserve the number before any invoice reads. SQLite must acquire its write
            // lock before the locking read; the outer transaction rolls this back on failure.
            $next_nr = $this->getNextInvoiceNumber();
            if ($next_nr === null) {
                throw new \FOSSBilling\Exception('Unable to determine the next invoice number');
            }

            $state = $this->lockAndRefreshInvoice($original);
            if (!$state['approved'] || $state['status'] !== Invoice::STATUS_UNPAID) {
                throw new InformationException('Only approved unpaid invoices can be reissued');
            }
            // Re-check against the locked row, which may have changed while
            // waiting on the lock.
            $locked = $this->di['em']->getRepository(Invoice::class)->find($original->getId());
            if ($locked instanceof Invoice && $locked->getReplacedByInvoiceId() !== null) {
                throw new InformationException('This invoice has already been reissued as invoice #:id', [':id' => $locked->getReplacedByInvoiceId()]);
            }

            $original->setStatus(Invoice::STATUS_CANCELED);
            $this->di['em']->persist($original);

            // No reservation release: every line moves to the replacement
            // below, so reserved stock (order-level meta) stays valid and
            // promo redemptions are transferred instead of released.
            $productService = $this->di['mod_service']('Product');

            $new = new Invoice();
            $new->setClientId($original->getClientId());
            $new->setReplacesInvoiceId($original->getId());
            $new->setHash(bin2hex(random_bytes(random_int(15, 30))));
            $new->setHashExpiresAt($this->computeHashExpiration());
            $new->setStatus(Invoice::STATUS_UNPAID);
            $new->setCurrency($original->getCurrency());
            $new->setApproved(true);
            $new->setTaxname($original->getTaxname());
            $new->setTaxrate($original->getTaxrate());
            $new->setGateway($original->getGateway());
            $new->setDueAt($original->getDueAt());

            $new->setSellerCompany($original->getSellerCompany());
            $new->setSellerCompanyVat($original->getSellerCompanyVat());
            $new->setSellerCompanyNumber($original->getSellerCompanyNumber());
            $new->setSellerAddress($original->getSellerAddress());
            $new->setSellerPhone($original->getSellerPhone());
            $new->setSellerEmail($original->getSellerEmail());

            $new->setBuyerFirstName($original->getBuyerFirstName());
            $new->setBuyerLastName($original->getBuyerLastName());
            $new->setBuyerCompany($original->getBuyerCompany());
            $new->setBuyerCompanyVat($original->getBuyerCompanyVat());
            $new->setBuyerCompanyNumber($original->getBuyerCompanyNumber());
            $new->setBuyerAddress($original->getBuyerAddress());
            $new->setBuyerCity($original->getBuyerCity());
            $new->setBuyerState($original->getBuyerState());
            $new->setBuyerCountry($original->getBuyerCountry());
            $new->setBuyerPhone($original->getBuyerPhone());
            $new->setBuyerPhoneCc($original->getBuyerPhoneCc());
            $new->setBuyerEmail($original->getBuyerEmail());
            $new->setBuyerZip($original->getBuyerZip());
            $new->setText1($original->getText1());
            $new->setText2($original->getText2());
            $new->setSerie($systemService->getParamValue('invoice_series'));
            $new->setNr($next_nr);
            $this->di['em']->persist($new);
            $this->di['em']->flush();

            $orderService = $this->di['mod_service']('Order');
            $entityManager = $this->di['em'];
            $movedOrderIds = [];
            $discountOrderIds = [];
            foreach ($this->getInvoiceItemRepository()->findByInvoiceId((int) $original->getId()) as $item) {
                $orderToLink = null;
                if ($item->getType() === InvoiceItem::TYPE_ORDER) {
                    $orderToLink = $entityManager->getRepository(Order::class)->find((int) $item->getRelId());
                    if (!$orderToLink instanceof Order) {
                        throw new InformationException('Order #:id attached to this invoice could not be found', [':id' => $item->getRelId()]);
                    }
                    if ((int) $orderToLink->getClientId() !== (int) $new->getClientId()) {
                        throw new InformationException('Order #:id does not belong to this invoice\'s client', [':id' => $orderToLink->getId()]);
                    }
                } elseif ($item->getUnit() === 'discount' && is_numeric($item->getRelId() ?? '')) {
                    // Same shape findOrderDiscountLine() matches on: a promo
                    // discount naming its order.
                    $discountOrderIds[(int) $item->getRelId()] = true;
                }

                $copy = new InvoiceItem();
                $copy->setInvoice($new);
                $copy->setType($item->getType());
                $copy->setRelId($item->getRelId());
                $copy->setTask($item->getTask());
                $copy->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT);
                $copy->setTitle($item->getTitle());
                $copy->setPeriod($item->getPeriod());
                $copy->setQuantity($item->getQuantity() ?? 1);
                $copy->setUnit($item->getUnit());
                $copy->setCharged(0);
                $copy->setPrice($item->getPrice() ?? 0);
                $copy->setTaxed($item->getTaxed());
                $entityManager->persist($copy);

                if ($orderToLink instanceof Order) {
                    $orderService->setUnpaidInvoice($orderToLink, $new);
                    $movedOrderIds[] = (int) $orderToLink->getId();
                }
            }
            $entityManager->flush();

            // Orders still pointing at the original have no moved line (e.g.
            // their line was deleted earlier) and must not keep pointing at a
            // canceled invoice; unpointed they invoice normally again. When
            // such an order's discount line was copied above, its backing
            // redemption moves with the discount so payment still commits it
            // and renewals keep it; otherwise the hold is freed, since a
            // stranded RESERVED redemption would block reusing the code.
            // Reserved stock stays on the still-pending order either way.
            foreach ($orderService->getOrderRepository()->findByUnpaidInvoiceId((int) $original->getId()) as $straggler) {
                if (isset($discountOrderIds[(int) $straggler->getId()])) {
                    $movedOrderIds[] = (int) $straggler->getId();
                } else {
                    $productService->releaseReservedPromoRedemptionsForOrder($straggler, 'invoice_reissued');
                }
                $orderService->unsetUnpaidInvoice($straggler);
            }

            $productService->transferReservedPromoRedemptionsForOrders($movedOrderIds, $new);

            $original->setReplacedByInvoiceId($new->getId());
            $entityManager->persist($original);
            $entityManager->flush();

            if ($attachPayload !== null) {
                $this->createAndAttachOrder($new, $attachPayload);
            }

            $this->addNote($original, "Reissued as invoice #{$new->getId()}.");
            $this->addNote($new, "Replacement for invoice #{$original->getId()}.");
            if ($reason !== '') {
                $this->addNote($new, $reason);
            }

            return $new;
        });
        $result = (int) $new->getId();

        try {
            $this->resendUpdatedInvoice($new);
        } catch (\Throwable $exception) {
            $this->di['logger']->withChannel('email')->error('Failed to send replacement invoice email', [
                'invoice_id' => $original->getId(),
                'replacement_id' => $new->getId(),
                'exception' => $exception,
            ]);
        }

        $this->di['event_dispatcher']->dispatch(new AfterAdminInvoiceReissueEvent((int) $original->getId(), $result));

        $this->di['logger']->info("Reissued invoice #{$original->getId()} as #{$result}.");

        return $result;
    }

    /**
     * Validate the attach payload shape before anything is written, so a bad
     * product reference can never leave a half-created order behind.
     *
     * @return array{order_id: int}|array{product_id: int, quantity: int, price: float|null, period: string|null, config: array, group_id: string|null, title: string|null}
     */
    private function resolveAttachOrderPayload(array $data): array
    {
        if (!empty($data['order_id'])) {
            if (!empty($data['product_id'])) {
                throw new InformationException('Provide either an order or product details, not both');
            }

            return ['order_id' => (int) $data['order_id']];
        }

        if (empty($data['product_id'])) {
            throw new InformationException('Product was not passed');
        }

        $config = $data['config'] ?? [];
        if (!is_array($config)) {
            throw new InformationException('Product configuration is invalid');
        }

        $period = $data['period'] ?? null;
        if ($period !== null && trim((string) $period) === '') {
            $period = null;
        }

        $groupId = $data['group_id'] ?? null;
        if ($groupId !== null && trim((string) $groupId) === '') {
            $groupId = null;
        }

        $title = $data['title'] ?? null;
        if ($title !== null && trim((string) $title) === '') {
            $title = null;
        }

        return [
            'product_id' => (int) $data['product_id'],
            'quantity' => PriceValidator::validateQuantity($data['quantity'] ?? 1),
            'price' => array_key_exists('price', $data) && $data['price'] !== null && (string) $data['price'] !== ''
                ? PriceValidator::validateAmount($data['price'], 'Price')
                : null,
            'period' => $period !== null ? (string) $period : null,
            'config' => $config,
            'group_id' => $groupId !== null ? (string) $groupId : null,
            'title' => $title !== null ? (string) $title : null,
        ];
    }

    /**
     * Create the order (no invoice of its own) and attach it to the invoice as
     * an order line. The caller must hold the invoice row lock; the order and
     * the line are created in the caller's transaction.
     */
    private function createAndAttachOrder(Invoice $invoice, array $payload): Order
    {
        $orderService = $this->di['mod_service']('Order');

        if (isset($payload['order_id'])) {
            $order = $this->di['em']->getRepository(Order::class)->find($payload['order_id']);
            if (!$order instanceof Order) {
                throw new InformationException('Order not found');
            }
        } else {
            $client = $this->di['em']->getRepository(Client::class)->find($invoice->getClientId());
            if (!$client instanceof Client) {
                throw new InformationException('Client not found');
            }
            $product = $this->di['mod_service']('Product')->findProductById($payload['product_id']);

            $orderData = [
                'quantity' => $payload['quantity'],
                'invoice_option' => 'no-invoice',
                'activate' => false,
                'config' => $payload['config'],
            ];
            if ($payload['price'] !== null) {
                $orderData['price'] = $payload['price'];
            }
            if ($payload['period'] !== null) {
                $orderData['period'] = $payload['period'];
            }
            if ($payload['group_id'] !== null) {
                $orderData['group_id'] = $payload['group_id'];
            }
            if ($payload['title'] !== null) {
                $orderData['title'] = $payload['title'];
            }

            $orderId = $orderService->createOrder($client, $product, $orderData);
            $order = $this->di['em']->getRepository(Order::class)->find($orderId);
            if (!$order instanceof Order) {
                throw new \FOSSBilling\Exception('Order could not be created');
            }
        }

        // Lock the order before reading any of its state: two staff requests
        // attaching the same pending order hold different invoice locks, so
        // only the order row itself serializes them. Held through line
        // creation and linking below.
        $this->lockAndRefreshOrder($order);

        if ((int) $order->getClientId() !== (int) $invoice->getClientId()) {
            throw new InformationException('Order does not belong to this invoice\'s client');
        }
        if ($order->getUnpaidInvoiceId() !== null) {
            throw new InformationException('Order is already attached to invoice #:id', [':id' => $order->getUnpaidInvoiceId()]);
        }
        if ($order->getStatus() !== Order::STATUS_PENDING_SETUP) {
            throw new InformationException('Only pending orders can be attached to an invoice');
        }
        if ($order->getCurrency() !== $invoice->getCurrency()) {
            throw new InformationException('Order currency does not match the invoice currency');
        }

        $this->di['mod_service']('Invoice', 'InvoiceItem')->generateFromOrder(
            $invoice,
            $order,
            InvoiceItem::TASK_ACTIVATE,
            $order->getPrice(),
            ['quantity' => $order->getQuantity()],
            false
        );
        $this->addNote($invoice, "Order #{$order->getId()} attached.");

        return $order;
    }

    /**
     * Ids of debit notes issued against the given invoice.
     *
     * @return list<int|null>
     */
    public function getDebitingInvoiceIds(Invoice $invoice): array
    {
        $notes = $this->di['em']->getRepository(Invoice::class)->findBy(['debitNoteForInvoiceId' => $invoice->getId()]);

        return array_map(fn (Invoice $debitNote): ?int => $debitNote->getId(), $notes);
    }

    public function updateInvoice(Invoice $model, array $data): bool
    {
        // Fast rejection for the common case; the authoritative check is repeated after the row
        // lock inside the mutation transaction below.
        if (!$this->isInvoiceEditable($model)) {
            throw new InformationException('This invoice can no longer be edited. Approved invoices are locked once issued; correct them with a credit note or a replacement invoice.');
        }

        $invoiceItemService = $this->di['mod_service']('Invoice', 'InvoiceItem');
        $previousStatus = null;
        $wasApproved = false;

        $changedFields = array_values(array_filter(array_keys($data), is_string(...)));
        sort($changedFields);
        $this->di['event_dispatcher']->dispatch(new BeforeAdminInvoiceUpdateEvent((int) $model->getId(), $changedFields));

        $this->di['em']->wrapInTransaction(function () use ($model, $data, $invoiceItemService, &$previousStatus, &$wasApproved): void {
            $this->lockAndRefreshInvoice($model);
            if (!$this->isInvoiceEditable($model)) {
                throw new InformationException('This invoice can no longer be edited. Approved invoices are locked once issued; correct them with a credit note or a replacement invoice.');
            }

            $previousStatus = $model->getStatus();
            $wasApproved = $model->isApproved();

            if (!empty($data['gateway_id'])) {
                $gateway = $this->di['em']->getRepository(PayGateway::class)->find((int) $data['gateway_id']);
                if (!$gateway instanceof PayGateway) {
                    throw new InformationException('Payment gateway not found');
                }
                if (!$gateway->isEnabled()) {
                    throw new InformationException('Payment gateway is not enabled');
                }
                $model->setGateway($gateway);
            } elseif (array_key_exists('gateway_id', $data) && $data['gateway_id'] === null) {
                $model->setGateway(null);
            }
            $model->setText1($data['text_1'] ?? $model->getText1());
            $model->setText2($data['text_2'] ?? $model->getText2());
            $model->setSellerCompany($data['seller_company'] ?? $model->getSellerCompany());
            $model->setSellerCompanyVat($data['seller_company_vat'] ?? $model->getSellerCompanyVat());
            $model->setSellerCompanyNumber($data['seller_company_number'] ?? $model->getSellerCompanyNumber());
            $model->setSellerAddress($data['seller_address'] ?? $model->getSellerAddress());
            $model->setSellerPhone($data['seller_phone'] ?? $model->getSellerPhone());
            $model->setSellerEmail($data['seller_email'] ?? $model->getSellerEmail());
            $model->setBuyerFirstName($data['buyer_first_name'] ?? $model->getBuyerFirstName());
            $model->setBuyerLastName($data['buyer_last_name'] ?? $model->getBuyerLastName());
            $model->setBuyerCompany($data['buyer_company'] ?? $model->getBuyerCompany());
            $model->setBuyerCompanyVat($data['buyer_company_vat'] ?? $model->getBuyerCompanyVat());
            $model->setBuyerCompanyNumber($data['buyer_company_number'] ?? $model->getBuyerCompanyNumber());
            $model->setBuyerAddress($data['buyer_address'] ?? $model->getBuyerAddress());
            $model->setBuyerCity($data['buyer_city'] ?? $model->getBuyerCity());
            $model->setBuyerState($data['buyer_state'] ?? $model->getBuyerState());
            $model->setBuyerCountry($data['buyer_country'] ?? $model->getBuyerCountry());
            $model->setBuyerZip($data['buyer_zip'] ?? $model->getBuyerZip());
            $model->setBuyerPhone($data['buyer_phone'] ?? $model->getBuyerPhone());
            $model->setBuyerEmail($data['buyer_email'] ?? $model->getBuyerEmail());

            $paid_at = $data['paid_at'] ?? ($model->getPaidAt() ? $model->getPaidAt()->format('Y-m-d H:i:s') : null);
            if (empty($paid_at)) {
                $model->setPaidAt(null);
            } else {
                $paidAtTimestamp = strtotime((string) $paid_at);
                if ($paidAtTimestamp === false) {
                    throw new InformationException('Invalid date format for paid_at: :value', [':value' => (string) $paid_at]);
                }
                $model->setPaidAt(new \DateTime(date('Y-m-d H:i:s', $paidAtTimestamp)));
            }

            $due_at = $data['due_at'] ?? ($model->getDueAt() ? $model->getDueAt()->format('Y-m-d H:i:s') : null);
            if (empty($due_at)) {
                $model->setDueAt(null);
            } else {
                $dueAtTimestamp = strtotime((string) $due_at);
                if ($dueAtTimestamp === false) {
                    throw new InformationException('Invalid date format for due_at: :value', [':value' => (string) $due_at]);
                }
                $model->setDueAt(new \DateTime(date('Y-m-d H:i:s', $dueAtTimestamp)));
            }

            $model->setSerie($data['serie'] ?? $model->getSerie());
            $model->setNr($data['nr'] ?? $model->getNr());
            $model->setStatus($data['status'] ?? $model->getStatus());
            $model->setTaxrate($data['taxrate'] ?? $model->getTaxrate());
            $model->setTaxname($data['taxname'] ?? $model->getTaxname());
            $model->setApproved((bool) ($data['approved'] ?? $model->isApproved()));
            $model->setNotes($data['notes'] ?? $model->getNotes());

            $created_at = $data['created_at'] ?? '';
            if (!empty($created_at)) {
                $createdAtTimestamp = strtotime((string) $created_at);
                if ($createdAtTimestamp === false) {
                    throw new InformationException('Invalid date format for created_at: :value', [':value' => (string) $created_at]);
                }
                $model->setCreatedAt(new \DateTime(date('Y-m-d H:i:s', $createdAtTimestamp)));
            }

            $ni = $data['new_item'] ?? [];
            if (isset($ni['title']) && !empty($ni['title'])) {
                $invoiceItemService->addNew($model, $ni);
            }

            $items = $data['items'] ?? [];
            foreach ($items as $id => $d) {
                $item = $this->getInvoiceItemRepository()->find((int) $id);
                if ($item instanceof InvoiceItem) {
                    $invoiceItemService->update($item, $d);
                }
            }

            $this->di['em']->persist($model);
            $this->di['em']->flush();

            if ($previousStatus === Invoice::STATUS_UNPAID && $model->getStatus() === Invoice::STATUS_CANCELED) {
                $productService = $this->di['mod_service']('Product');
                $productService->releaseReservedPromoRedemptionsForInvoice($model, 'invoice_canceled');
                $productService->releaseReservedStockForInvoice($model, 'invoice_canceled');
            }
        });

        $this->di['event_dispatcher']->dispatch(new AfterAdminInvoiceUpdateEvent((int) $model->getId()));

        $this->di['logger']->info("Updated invoice {$model->getId()}.");

        // An edit to an already-approved invoice changes what the client was
        // sent, so re-send it (the approval path sends on its own).
        if ($wasApproved && $model->isApproved() && empty($data['approve'])) {
            $this->resendUpdatedInvoice($model);
        }

        return true;
    }

    /**
     * Re-send an approved invoice whose content just changed, keeping the
     * client's copy and payment link in sync with what they will be charged.
     */
    protected function resendUpdatedInvoice(Invoice $model): void
    {
        $invoiceData = $this->toApiArray($model);
        if (($invoiceData['total'] ?? 0) > 0
            && $model->getStatus() !== Invoice::STATUS_PAID
            && $model->getClientId() !== null
        ) {
            $this->sendInvoiceEmail($model, $invoiceData, 'mod_invoice_created');
        }
        $this->extendInvoiceHashLifetime($model);
    }

    /**
     * Apply an existing promo to one order on an unpaid invoice.
     *
     * The discount is recorded as that promo (order discount, discount line,
     * and checkout redemption) rather than a hand-typed negative line, so it
     * shows up in promo reporting and carries to renewals when recurring.
     *
     * @return float the applied discount amount (invoice currency)
     */
    public function promoAddToInvoice(Invoice $invoice, \Box\Mod\Product\Entity\Promo $promo, ?Order $order = null): float
    {
        if ($invoice->getStatus() !== Invoice::STATUS_UNPAID) {
            throw new InformationException('Promotions can only be applied to unpaid invoices');
        }

        $order = $this->findPromoTargetOrder($invoice, $order);
        $client = $this->di['em']->getRepository(Client::class)->find($invoice->getClientId())
            ?? throw new InformationException('Client not found');

        $productService = $this->di['mod_service']('Product');
        if (!$productService->promoCanBeApplied($promo)) {
            throw new InformationException('The promo code has expired or does not exist');
        }

        if (!$productService->isPromoAvailableForClientGroup($promo, $client)) {
            throw new InformationException('Promo code cannot be applied to this client');
        }

        if (!$productService->canClientUsePromo($client, $promo)) {
            throw new InformationException('This client has already used this promo code');
        }

        $product = $productService->findProductById((int) $order->getProductId());
        $promoConfig = json_decode($order->getConfig() ?? '', true) ?? [];
        if ($product->getType() !== \Box\Mod\Product\Service::DOMAIN && !isset($promoConfig['period']) && $order->getPeriod()) {
            $promoConfig['period'] = $order->getPeriod();
        }

        if (!$productService->isPromoApplicableToProduct($promo, $product, $promoConfig)) {
            throw new InformationException('This promo code does not apply to the selected product or billing period');
        }

        $currencyService = $this->di['mod_service']('Currency');
        $currencyRepository = $currencyService->getCurrencyRepository();
        $rate = $currencyRepository->getRateByCode((string) $order->getCurrency());
        if ($rate === null) {
            throw new \FOSSBilling\Exception("Currency rate for '{$order->getCurrency()}' is not configured");
        }

        if ($promo->getType() === \Box\Mod\Product\Entity\Promo::PERCENTAGE) {
            // Percentage comes off what the invoice actually charges for the
            // order (matching order line, already in the invoice currency),
            // not the order record or catalog pricing: the two differ for
            // repriced renewals, edited lines, or staff price overrides.
            // Absolute promos stay on the catalog-based path below so their
            // base-currency value keeps the existing rate conversion.
            $discountBase = round($this->getOrderLineTotal($invoice, $order) * (float) $promo->getValue() / 100, 2);
        } else {
            $rawDiscount = (float) $productService->getProductDiscount($product, $promo, $promoConfig);
            $discountBase = $rawDiscount * $rate;
        }
        if ($discountBase <= 0) {
            throw new InformationException('This promo code gives no discount on the selected order');
        }

        $amount = $this->di['em']->wrapInTransaction(function () use ($invoice, $order, $client, $promo, $productService, $discountBase): float {
            // The unpaid pre-check above races with payment: re-read the
            // status under a row lock so a concurrent markAsPaid cannot slip
            // between the check and these writes. The lock also serializes
            // concurrent promo edits on this invoice.
            if ($this->getInvoiceRepository()->lockAndGetStatus((int) $invoice->getId()) !== Invoice::STATUS_UNPAID) {
                throw new InformationException('Promotions can only be applied to unpaid invoices');
            }

            // Refresh against changes committed while waiting for the lock,
            // then value the discount from that state rather than the
            // pre-transaction snapshot.
            $this->di['em']->refresh($order);

            // In-transaction re-check so concurrent applications cannot both
            // consume the last once-per-client use.
            if ($productService->clientHasActivePromoApplicationForUpdate($client, $promo)) {
                throw new InformationException('This client has already used this promo code');
            }

            $productService->usePromo($promo);

            // Cap against the same invoice line total so the discount cannot
            // exceed what this invoice charges for the order. Re-resolved
            // under the lock so concurrent line edits are honored.
            $remaining = $this->getOrderLineTotal($invoice, $order) - (float) ($order->getDiscount() ?? 0);
            $amount = min($discountBase, $remaining);
            if ($amount <= 0) {
                throw new InformationException('This promo code gives no discount on the selected order');
            }

            $order->setDiscount((float) ($order->getDiscount() ?? 0) + $amount);
            if ($order->getPromoId() === null) {
                $order->setPromoId((int) $promo->getId());
                $order->setPromoRecurring($promo->isRecurring());
            } else {
                // promo_recurring follows the primary promo only: stacking a
                // recurring promo beside a one-time primary must not make the
                // primary renew. The stacked promo carries forward through
                // its own checkout redemption instead. A deleted primary
                // keeps its existing flag; renewal skips it either way.
                try {
                    $primaryPromo = $productService->findPromoById($order->getPromoId());
                    $order->setPromoRecurring($primaryPromo->isRecurring());
                } catch (\FOSSBilling\Exception) {
                    // Leave the existing flag untouched.
                }
            }
            $order->setPromoUsed(1);
            $this->di['em']->persist($order);

            $discountLine = $this->findOrderDiscountLine($invoice, $order);
            if ($discountLine instanceof InvoiceItem) {
                $discountLine->setPrice((float) ($discountLine->getPrice() ?? 0) - $amount);
                $this->di['em']->persist($discountLine);
            } else {
                $clientService = $this->di['mod_service']('client');
                $invoiceItemService = $this->di['mod_service']('Invoice', 'InvoiceItem');
                // Promo application holds the invoice lock with its own unpaid
                // check, so it bypasses the edit lock by design.
                $invoiceItemService->addNew($invoice, [
                    'title' => __trans('Discount: :product', [':product' => $order->getTitle()]),
                    'price' => $amount * -1,
                    'quantity' => 1,
                    'unit' => 'discount',
                    'rel_id' => (string) $order->getId(),
                    'taxed' => $clientService->isClientTaxable($client),
                ], true);
            }

            $productService->createPromoRedemption(
                $promo,
                $client,
                $order,
                $invoice,
                \Box\Mod\Product\Entity\PromoRedemption::PHASE_CHECKOUT,
                $amount,
                $order->getCurrency(),
                $order->getCreatedAt()?->format('Y-m-d H:i:s'),
                \Box\Mod\Product\Entity\PromoRedemption::STATUS_RESERVED,
            );

            $this->di['em']->flush();

            return $amount;
        });

        $this->di['logger']->info('Applied promo {promo_code} to invoice #{invoice_id}', ['promo_code' => $promo->getCode(), 'invoice_id' => $invoice->getId()]);

        return $amount;
    }

    /**
     * Remove a previously applied promo from one order on an unpaid invoice.
     *
     * @return float the removed discount amount (invoice currency)
     */
    public function promoRemoveFromInvoice(Invoice $invoice, \Box\Mod\Product\Entity\Promo $promo, ?Order $order = null): float
    {
        if ($invoice->getStatus() !== Invoice::STATUS_UNPAID) {
            throw new InformationException('Promotions can only be removed from unpaid invoices');
        }

        $order = $this->findPromoTargetOrder($invoice, $order);

        $productService = $this->di['mod_service']('Product');

        // Reserved redemptions are read inside the transaction, after the
        // lock: a concurrent removal (or cancellation releasing them) must
        // not make this call subtract a discount twice.
        $amount = $this->di['em']->wrapInTransaction(function () use ($invoice, $order, $promo, $productService): float {
            // Same race as applying: a concurrent markAsPaid must not slip
            // between the pre-check and these writes.
            if ($this->getInvoiceRepository()->lockAndGetStatus((int) $invoice->getId()) !== Invoice::STATUS_UNPAID) {
                throw new InformationException('Promotions can only be removed from unpaid invoices');
            }

            $redemptions = $productService->getPromoRedemptionRepository()->findBy([
                'clientOrderId' => (int) $order->getId(),
                'promo' => $promo,
                'phase' => \Box\Mod\Product\Entity\PromoRedemption::PHASE_CHECKOUT,
                'status' => \Box\Mod\Product\Entity\PromoRedemption::STATUS_RESERVED,
            ]);

            if ($redemptions === []) {
                throw new InformationException('This promotion is not applied to the selected order');
            }

            $amount = 0.0;
            foreach ($redemptions as $redemption) {
                $amount += (float) ($redemption->getDiscountAmount() ?? 0);
            }

            if ($amount <= 0) {
                throw new InformationException('This promotion has no discount recorded on the selected order');
            }

            $released = $productService->releaseCheckoutPromoRedemptions($order, $promo, 'admin_removed', $invoice);
            if ($released === 0) {
                throw new InformationException('This promotion is not applied to the selected order');
            }

            $this->di['em']->refresh($order);

            $order->setDiscount(max(0.0, (float) ($order->getDiscount() ?? 0) - $amount));

            // Recompute the primary promo from the remaining active checkout
            // applications on this order.
            $remaining = $productService->getPromoRedemptionRepository()->findBy(
                [
                    'clientOrderId' => (int) $order->getId(),
                    'phase' => \Box\Mod\Product\Entity\PromoRedemption::PHASE_CHECKOUT,
                    'status' => [
                        \Box\Mod\Product\Entity\PromoRedemption::STATUS_RESERVED,
                        \Box\Mod\Product\Entity\PromoRedemption::STATUS_COMMITTED,
                    ],
                ],
                ['id' => 'ASC']
            );

            $remainingPromos = [];
            foreach ($remaining as $redemption) {
                $remainingPromo = $redemption->getPromo();
                if ($remainingPromo instanceof \Box\Mod\Product\Entity\Promo) {
                    $remainingPromos[(int) $remainingPromo->getId()] = $remainingPromo;
                }
            }

            if ($remainingPromos === []) {
                $order->setPromoId(null);
                $order->setPromoRecurring(false);
                $order->setPromoUsed(0);
            } else {
                // Keep the current primary when it remains applied; otherwise
                // fall back to the earliest remaining application. Either way
                // promo_recurring follows that primary promo only.
                $primaryId = (int) $order->getPromoId();
                if (!isset($remainingPromos[$primaryId])) {
                    $primaryId = array_key_first($remainingPromos);
                    $order->setPromoId($primaryId);
                }
                $order->setPromoRecurring($remainingPromos[$primaryId]->isRecurring());
                $order->setPromoUsed(1);
            }
            $this->di['em']->persist($order);

            $discountLine = $this->findOrderDiscountLine($invoice, $order);
            if ($discountLine instanceof InvoiceItem) {
                $newPrice = (float) ($discountLine->getPrice() ?? 0) + $amount;
                if ($newPrice >= 0) {
                    $this->di['em']->remove($discountLine);
                } else {
                    $discountLine->setPrice($newPrice);
                    $this->di['em']->persist($discountLine);
                }
            }

            $this->di['em']->flush();

            return $amount;
        });

        $this->di['logger']->info('Removed promo {promo_code} from invoice #{invoice_id}', ['promo_code' => $promo->getCode(), 'invoice_id' => $invoice->getId()]);

        return $amount;
    }

    private function findPromoTargetOrder(Invoice $invoice, ?Order $order): Order
    {
        if ($order instanceof Order) {
            if ($this->orderBelongsToInvoice($invoice, $order)) {
                return $order;
            }

            throw new InformationException('The selected order is not on this invoice');
        }

        $orderIds = [];
        foreach ($this->getInvoiceItemRepository()->findByInvoiceId((int) $invoice->getId()) as $item) {
            if ($item->getType() === InvoiceItem::TYPE_ORDER && is_numeric((string) $item->getRelId())) {
                $orderIds[(int) $item->getRelId()] = true;
            }
        }

        $orderService = $this->di['mod_service']('Order');
        foreach ($orderService->getOrderRepository()->findByUnpaidInvoiceId((int) $invoice->getId()) as $linkedOrder) {
            $orderIds[(int) $linkedOrder->getId()] = true;
        }

        $orderIds = array_keys($orderIds);
        if ($orderIds === []) {
            throw new InformationException('This invoice has no orders to apply the promotion to');
        }

        if (count($orderIds) > 1) {
            throw new InformationException('This invoice covers several orders; select which order to apply the promotion to');
        }

        return $this->di['em']->getRepository(Order::class)->find($orderIds[0])
            ?? throw new InformationException('Order not found');
    }

    private function orderBelongsToInvoice(Invoice $invoice, Order $order): bool
    {
        if ((int) $order->getUnpaidInvoiceId() === (int) $invoice->getId()) {
            return true;
        }

        foreach ($this->getInvoiceItemRepository()->findByInvoiceId((int) $invoice->getId()) as $item) {
            if (is_numeric((string) $item->getRelId()) && (int) $item->getRelId() === (int) $order->getId()) {
                return true;
            }
        }

        return false;
    }

    private function findOrderDiscountLine(Invoice $invoice, Order $order): ?InvoiceItem
    {
        foreach ($this->getInvoiceItemRepository()->findByInvoiceId((int) $invoice->getId()) as $item) {
            if ($item->getUnit() === 'discount' && (string) $item->getRelId() === (string) $order->getId()) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Total the invoice charges for the order (line price × quantity).
     * Falls back to the order record when the invoice has no matching order
     * line yet; the two are equal for freshly generated invoices.
     */
    private function getOrderLineTotal(Invoice $invoice, Order $order): float
    {
        foreach ($this->getInvoiceItemRepository()->findByInvoiceId((int) $invoice->getId()) as $item) {
            if ($item->getType() === InvoiceItem::TYPE_ORDER && (string) $item->getRelId() === (string) $order->getId()) {
                return (float) $item->getPrice() * (float) ($item->getQuantity() ?? 1);
            }
        }

        return (float) $order->getPrice() * (float) $order->getQuantity();
    }

    public function rmInvoice(Invoice $model, bool $requireUnapprovedUnpaid = false): bool
    {
        $entityManager = $this->di['em'];
        $entityManager->wrapInTransaction(function () use ($model, $entityManager, $requireUnapprovedUnpaid): void {
            $this->lockAndRefreshInvoice($model);

            if ($requireUnapprovedUnpaid && ($model->isApproved() || $model->getStatus() !== Invoice::STATUS_UNPAID)) {
                throw new InformationException('Only unapproved, unpaid invoices can be deleted. Revoke an approved invoice instead.');
            }

            $productService = $this->di['mod_service']('Product');
            $productService->releaseReservedPromoRedemptionsForInvoice($model, 'invoice_deleted');
            $productService->releaseReservedStockForInvoice($model, 'invoice_deleted');

            // remove related invoice from orders
            $sql = '
                UPDATE client_order
                SET unpaid_invoice_id = NULL
                WHERE unpaid_invoice_id = :id';
            $entityManager->getConnection()->executeStatement($sql, ['id' => $model->getId()]);

            // Detach (not delete) transactions referencing this invoice - a transaction is a real
            // record of a payment attempt/event, same reasoning as unpaid_invoice_id above. Runs
            // inside the same transaction as the flushes below: without that, a later flush
            // failing (e.g. removing the invoice itself) would leave these transactions
            // permanently detached from an invoice that was never actually deleted.
            $entityManager->getRepository(Transaction::class)->detachFromInvoice((int) $model->getId());

            $invoiceItems = $this->getInvoiceItemRepository()->findByInvoiceId((int) $model->getId());
            foreach ($invoiceItems as $item) {
                $entityManager->remove($item);
            }
            $entityManager->flush();
            $entityManager->remove($model);
            $entityManager->flush();
        });

        return true;
    }

    public function deleteInvoiceByAdmin(Invoice $model): bool
    {
        // Fast rejection for the common case; rmInvoice rechecks the fresh state under its row
        // lock before detaching or deleting anything.
        if ($model->isApproved() || $model->getStatus() !== Invoice::STATUS_UNPAID) {
            throw new InformationException('Only unapproved, unpaid invoices can be deleted. Revoke an approved invoice instead.');
        }

        $this->di['event_dispatcher']->dispatch(new BeforeAdminInvoiceDeleteEvent((int) $model->getId()));

        $id = $model->getId();
        $this->rmInvoice($model, true);

        $this->di['event_dispatcher']->dispatch(new AfterAdminInvoiceDeleteEvent((int) $id));

        $this->di['logger']->info('Removed invoice #{id}', ['id' => $id]);

        return true;
    }

    public function renewInvoice(Order $model, array $data): ?int
    {
        $this->di['event_dispatcher']->dispatch(new BeforeAdminGenerateRenewalInvoiceEvent((int) $model->getId()));

        $due_days = isset($data['due_days']) ? (int) $data['due_days'] : null;
        $invoice = $this->generateForOrder($model, $due_days);
        $this->approveInvoice($invoice, ['id' => $invoice->getId(), 'use_credits' => true]);

        $this->di['event_dispatcher']->dispatch(new AfterAdminGenerateRenewalInvoiceEvent((int) $model->getId(), (int) $invoice->getId()));

        $this->di['logger']->info("Generated renewal invoice #{$invoice->getId()}.");

        return $invoice->getId();
    }

    public function doBatchPayWithCredits(array $data): bool
    {
        $unpaid = $this->findAllUnpaid($data);
        $invoiceIds = array_map(static fn (array $proforma): int => (int) ($proforma['id'] ?? 0), $unpaid);
        $models = $this->getInvoiceRepository()->findBy(['id' => $invoiceIds]);
        foreach ($models as $model) {
            try {
                $this->tryPayWithCredits($model);
            } catch (\Exception $e) {
                // @phpstan-ignore if.alwaysFalse
                if (DEBUG) {
                    $this->di['logger']->warning($e->getMessage());
                }
            }
        }
        $this->di['logger']->info('Executed action to try cover unpaid invoices with client credits.');

        return true;
    }

    public function payInvoiceWithCredits(Invoice $model): bool
    {
        $this->tryPayWithCredits($model);
        $this->di['logger']->info('Cover invoice with client credits.');

        return true;
    }

    /**
     * @param int $due_days
     */
    public function generateForOrder(Order $order, $due_days = null, bool $applyPromo = true): Invoice
    {
        // check if we do have invoice prepared already
        if ($order->getUnpaidInvoiceId() !== null) {
            $p = $this->getInvoiceRepository()->find($order->getUnpaidInvoiceId());
            if ($p instanceof Invoice && $p->getStatus() === Invoice::STATUS_UNPAID) {
                return $p;
            }

            $orderService = $this->di['mod_service']('Order');
            $orderService->unsetUnpaidInvoice($order);
        }

        $price = $order->getPrice();
        $line = [
            'price' => $order->getPrice(),
            'quantity' => $order->getQuantity(),
        ];

        // Domain renewal pricing is resolved from the registrar/config rather than
        // the order, since it legitimately changes between registration and renewal.
        // Other products keep the order's own price so admin-edited prices are respected.
        if (in_array($order->getStatus(), [
            Order::STATUS_ACTIVE,
            Order::STATUS_FAILED_RENEW,
            Order::STATUS_SUSPENDED,
        ], true)) {
            $productService = $this->di['mod_service']('Product');
            $product = $productService->findProductById((int) $order->getProductId());

            if ($productService instanceof \Box\Mod\Product\Service && $product->getType() === \Box\Mod\Product\Service::DOMAIN) {
                $config = json_decode($order->getConfig() ?? '', true) ?? [];
                $currencyService = $this->di['mod_service']('Currency');
                $currencyRepository = $currencyService->getCurrencyRepository();
                $rate = $currencyRepository->getRateByCode($order->getCurrency());
                if ($rate === null) {
                    throw new \FOSSBilling\Exception("Currency rate for '{$order->getCurrency()}' is not configured");
                }

                $renewalLine = $productService->getProductRenewalLineConfig($product, $config);
                $price = $renewalLine['price'] * $rate;
                $line = [
                    'price' => $price,
                    'quantity' => $renewalLine['quantity'],
                ];

                $domainService = $productService->getProductModuleService($product);
                if (method_exists($domainService, 'getRenewalTitle')) {
                    $renewalTitle = $domainService->getRenewalTitle($config);
                    if ($renewalTitle !== null) {
                        $line['title'] = $renewalTitle;
                    }
                }
            }
        }

        if (($price * ($line['quantity'] ?? 1)) < 0) {
            throw new InformationException('Invoices are not generated for negative amount orders.');
        }

        $client = $this->di['em']->getRepository(Client::class)->find($order->getClientId())
            ?? throw new InformationException('Client not found');

        // generate proforma after validating the resolved renewal amount
        $proforma = new Invoice();
        $proforma->setClientId($client->getId() !== null ? (int) $client->getId() : null);
        $proforma->setStatus(Invoice::STATUS_UNPAID);
        $proforma->setCurrency($order->getCurrency());
        $proforma->setApproved(false);
        $this->di['em']->persist($proforma);
        $this->di['em']->flush();

        $this->setInvoiceDefaults($proforma);

        $invoiceItemService = $this->di['mod_service']('Invoice', 'InvoiceItem');
        $invoiceItemService->generateFromOrder($proforma, $order, InvoiceItem::TASK_RENEW, $price, $line, $applyPromo);

        // invoice due date
        if ($due_days > 0) {
            $proforma->setDueAt(new \DateTime('+' . $due_days . ' days'));
            $this->di['em']->persist($proforma);
            $this->di['em']->flush();
        } else {
            $expiresAt = $order->getExpiresAt();
            if ($expiresAt !== null) {
                $proforma->setDueAt($expiresAt);
                $this->di['em']->persist($proforma);
                $this->di['em']->flush();
            }
        }

        return $proforma;
    }

    public function generateInvoicesForExpiringOrders(): bool
    {
        $orderService = $this->di['mod_service']('Order');
        $orders = $orderService->getSoonExpiringActiveOrders();

        if (Tools::safeCount($orders) == 0) {
            return true;
        }

        $orderIds = array_map(static fn (array $order): int => (int) ($order['id'] ?? 0), $orders);
        $models = $this->di['em']->getRepository(Order::class)->findBy(['id' => $orderIds]);
        foreach ($models as $model) {
            try {
                $invoice = $this->generateForOrder($model);
                $this->approveInvoice($invoice, ['id' => $invoice->getId(), 'use_credits' => true]);
            } catch (\Exception $e) {
                $this->di['logger']->warning($e->getMessage());
            }
        }

        $this->di['logger']->info('Executed action to generate new invoices for expiring orders.');

        return true;
    }

    public function doBatchPaidInvoiceActivation(): bool
    {
        $invoiceItemService = $this->di['mod_service']('Invoice', 'InvoiceItem');

        $invoiceItems = (array) $invoiceItemService->getAllNotExecutePaidItems();
        $connection = $this->di['em']->getConnection();
        foreach ($invoiceItems as $item) {
            try {
                $connection->transactional(function () use ($connection, $item, $invoiceItemService): void {
                    // Claim the row so concurrent cron processes cannot execute the same item twice.
                    $status = $connection->fetchOne(
                        'SELECT status FROM invoice_item WHERE id = :id' . RowLock::suffix($connection),
                        ['id' => (int) ($item['id'] ?? 0)]
                    );
                    if (in_array($status, [InvoiceItem::STATUS_EXECUTED, InvoiceItem::STATUS_FAILED], true)) {
                        return;
                    }

                    $model = $this->getInvoiceItemRepository()->find((int) ($item['id'] ?? 0));
                    if (!$model instanceof InvoiceItem) {
                        throw new InformationException('Invoice item was not found');
                    }
                    $invoiceItemService->executeTask($model);
                });
            } catch (\Exception $e) {
                $this->di['logger']->error($e->getMessage());

                // A failed ORM flush closes the EntityManager and clear() can't reopen
                // it. Replace it with a fresh instance so the rest of the cron run can
                // keep writing, then stop the batch. Otherwise clear the identity map
                // between iterations.
                if (!$this->di['em']->isOpen()) {
                    $this->resetEntityManager();

                    break;
                }
                $this->di['em']->clear();
            }
        }
        $this->di['logger']->info('Executed action to activate paid invoices.');

        return true;
    }

    public function doBatchRemindersSend(): bool
    {
        $this->di['event_dispatcher']->dispatch(new BeforeAdminInvoiceSendRemindersEvent());
        $result = $this->doBatchInvokeDueEvent(['once_per_day' => true]);
        if (!$result) {
            // Pick up invoices that became reminder-eligible after today's due-event batch ran.
            $result = $this->doBatchInvokePendingReminderEvents();
        }
        if ($result) {
            $this->di['logger']->info('Executed action to send invoice payment reminders.');
        }

        return $result;
    }

    public function doBatchInvokeDueEvent(array $data): bool
    {
        $once_per_day = isset($data['once_per_day']) ? (bool) $data['once_per_day'] : true;
        $key = 'invoice_overdue_invoked';

        // Read the setting directly to avoid dispatching system module events.
        $ss = $this->di['mod_service']('System');
        $last_time = $ss->getParamValue($key);
        if ($once_per_day && $last_time && (time() - strtotime((string) $last_time)) < 86400) {
            return false;
        }

        $this->fireDueReminderEvents();

        $ss->setParamValue($key, date('Y-m-d H:i:s'));
        $this->di['logger']->info('Executed action to invoke invoice due event');

        return true;
    }

    protected function doBatchInvokePendingReminderEvents(): bool
    {
        $this->fireDueReminderEvents();

        return true;
    }

    /**
     * Fires the before/after due-date events for every unpaid, approved invoice in range, same
     * as before this class started reminder-throttling. Listeners (built-in or third-party
     * extensions) decide for themselves whether a given invoice is actionable; this method does
     * not filter by reminder interval so it does not narrow what extensions can observe. The
     * built-in reminder handlers (onEventBeforeInvoiceIsDue, onEventAfterInvoiceIsDue) are the
     * ones responsible for matching the configured interval and atomically claiming the invoice
     * via reminded_at before actually sending anything, which is what keeps overlapping cron
     * runs and repeated dispatch of this same event from sending duplicate reminders.
     */
    private function fireDueReminderEvents(): void
    {
        $ss = $this->di['mod_service']('System');
        $beforeDueReminderIntervals = $this->parseInvoiceReminderIntervals($ss->getParamValue('invoice_reminder_before_due_days', ''));
        $afterDueReminderIntervals = $this->parseInvoiceReminderIntervals($ss->getParamValue('invoice_reminder_after_due_days', '5'));

        $connection = $this->di['em']->getConnection();
        $now = new \DateTimeImmutable();
        $tomorrowStart = $now->modify('today')->modify('+1 day')->format('Y-m-d H:i:s');
        $nowFormatted = $now->format('Y-m-d H:i:s');

        $daysLeft = SqlExpr::dateDiffDays($connection, 'due_at', ':now');
        $beforeDueList = $connection->fetchAllAssociative(
            "SELECT id, {$daysLeft} as days_left FROM invoice WHERE status = 'unpaid' AND approved = true AND due_at > :now",
            ['now' => $nowFormatted]
        );
        foreach ($beforeDueList as $params) {
            $this->di['event_dispatcher']->dispatch(new BeforeInvoiceIsDueEvent(
                (int) $params['id'],
                (int) $params['days_left'],
                $beforeDueReminderIntervals,
            ));
        }

        // due_at < :tomorrow_start is a portable stand-in for MySQL's
        // (due_at < NOW()) OR (ABS(DATEDIFF(due_at, NOW())) = 0): "already overdue, or due
        // sometime today" is exactly "due before the start of tomorrow".
        $daysPassed = SqlExpr::dateDiffDays($connection, 'due_at', ':now');
        $afterDueList = $connection->fetchAllAssociative(
            "SELECT id, ABS({$daysPassed}) as days_passed FROM invoice WHERE status = 'unpaid' AND approved = true AND due_at < :tomorrow_start",
            ['now' => $nowFormatted, 'tomorrow_start' => $tomorrowStart]
        );
        foreach ($afterDueList as $params) {
            $this->di['event_dispatcher']->dispatch(new AfterInvoiceIsDueEvent(
                (int) $params['id'],
                (int) $params['days_passed'],
                $afterDueReminderIntervals,
            ));
        }
    }

    public function sendInvoiceReminder(Invoice $invoice): bool
    {
        // do not send accidental reminder for paid invoices
        if ($invoice->getStatus() == Invoice::STATUS_PAID) {
            return true;
        }

        $this->di['event_dispatcher']->dispatch(new BeforeAdminInvoiceSendReminderEvent((int) $invoice->getId()));

        $invoice->setRemindedAt(new \DateTime());
        $this->di['em']->persist($invoice);
        $this->di['em']->flush();

        $recordedEvent = new AfterAdminInvoiceReminderRecordedEvent((int) $invoice->getId());
        $this->sendInvoiceReminderEmail($recordedEvent);

        try {
            $this->di['event_dispatcher']->dispatch($recordedEvent);
        } catch (\Throwable $error) {
            // The reminder was already recorded and the built-in email was attempted. A failing
            // observer must not release the daily claim and cause a duplicate email on retry.
            $this->di['logger']->withChannel('email')->error('Invoice reminder event listener failed', ['exception' => $error]);
        }

        $this->di['logger']->info('Invoice payment reminder recorded');

        return true;
    }

    public function counter(): array
    {
        $sql = 'SELECT status, count(id) as counter
                 FROM invoice
                 group by status';
        $rows = $this->di['em']->getConnection()->fetchAllAssociative($sql);
        $data = [];
        foreach ($rows as $row) {
            $data[$row['status']] = $row['counter'];
        }

        return [
            'total' => array_sum($data),
            Invoice::STATUS_PAID => $data[Invoice::STATUS_PAID] ?? 0,
            Invoice::STATUS_UNPAID => $data[Invoice::STATUS_UNPAID] ?? 0,
            Invoice::STATUS_REFUNDED => $data[Invoice::STATUS_REFUNDED] ?? 0,
            Invoice::STATUS_CANCELED => $data[Invoice::STATUS_CANCELED] ?? 0,
        ];
    }

    public function isFundsEnabled(): bool
    {
        $systemService = $this->di['mod_service']('system');

        return (bool) $systemService->getParamValue('funds_enabled', true);
    }

    public function generateFundsInvoice(Client $client, $amount): Invoice
    {
        if (!$client->getCurrency()) {
            throw new InformationException('You must have at least one active order before you can add funds so you cannot proceed at the current time!');
        }

        if (!$this->isFundsEnabled()) {
            throw new InformationException('Adding funds to the account balance is currently disabled', null, 980);
        }

        $systemService = $this->di['mod_service']('system');

        $min_amount = $systemService->getParamValue('funds_min_amount', null);
        $max_amount = $systemService->getParamValue('funds_max_amount', null);

        if ($min_amount && $amount < $min_amount) {
            throw new InformationException('Amount must be at least :min_amount', [':min_amount' => $min_amount], 981);
        }

        if ($max_amount && $amount > $max_amount) {
            throw new InformationException('Amount cannot exceed :max_amount', [':max_amount' => $max_amount], 982);
        }

        $proforma = new Invoice();
        $proforma->setClientId($client->getId() ?? null);
        $proforma->setStatus(Invoice::STATUS_UNPAID);
        $proforma->setCurrency($client->getCurrency());
        $proforma->setApproved($this->_isAutoApproved());
        $this->di['em']->persist($proforma);
        $this->di['em']->flush();

        $this->setInvoiceDefaults($proforma);

        $invoiceItemService = $this->di['mod_service']('Invoice', 'InvoiceItem');
        $invoiceItemService->generateForAddFunds($proforma, $amount);

        return $proforma;
    }

    public function processInvoice(array $data): array
    {
        $allowSubscribe = $data['allow_subscription'] ?? true;
        $subscribe = false;

        $invoice = $this->getInvoiceRepository()->findByHash($data['hash']);
        if (!$invoice instanceof Invoice) {
            throw new InformationException('Invoice not found', null, 812);
        }

        $this->checkInvoiceAuth($invoice, InvoiceOperation::PAYMENT);

        if ($invoice->getStatus() === Invoice::STATUS_CANCELED || $invoice->getReplacedByInvoiceId() !== null) {
            throw new InformationException('This invoice was canceled and cannot be paid');
        }

        $gtw = $this->di['em']->getRepository(PayGateway::class)->find((int) $data['gateway_id']);
        if (!$gtw instanceof PayGateway) {
            throw new InformationException('Payment method not found', null, 813);
        }

        if (!$gtw->isEnabled()) {
            throw new \FOSSBilling\Exception('Payment method not enabled', null, 814);
        }

        $subscribeService = $this->di['mod_service']('Invoice', 'Subscription');
        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        if ($subscribeService->isSubscribable($invoice->getId()) && $payGatewayService->canPerformRecurrentPayment($gtw) && $allowSubscribe) {
            $subscribe = true;
        }

        if (!$subscribe && !$payGatewayService->canPerformSinglePayment($gtw)) {
            throw new \FOSSBilling\Exception('One-time payments are not enabled for the selected payment gateway', null, 815);
        }

        $adapter = $payGatewayService->getPaymentAdapter($gtw, $invoice, $data);
        if (method_exists($adapter, 'setDi')) {
            $adapter->setDi($this->di);
        }

        if (method_exists($adapter, 'setLog')) {
            $adapter->setLog($this->di['logger']);
        }

        $pgc = $adapter->getConfig();

        // @since v2.9.15
        if (method_exists($adapter, 'getHtml')) {
            $html = $adapter->getHtml($this->di['api_system'], (int) $invoice->getId(), $subscribe);

            return [
                'iframe' => isset($pgc['can_load_in_iframe']) && (bool) $pgc['can_load_in_iframe'],
                'type' => 'html',
                'service_url' => '',
                'subscription' => $subscribe,
                'result' => $html,
            ];
        }

        $i = clone $invoice;
        $mpi = $this->getPaymentInvoice($i, $subscribe);
        $r = ($subscribe) ? $adapter->recurrentPayment($mpi) : $adapter->singlePayment($mpi);
        $this->di['logger']->info('Went to pay for invoice #{invoice_id} via {gateway}', ['invoice_id' => $invoice->getId(), 'gateway' => $gtw->getGateway()]);

        // @bug https://github.com/boxbilling/boxbilling/issues/108
        if ($adapter->getType() != 'html') {
            $r = (array) $r;
        }

        return [
            'type' => $adapter->getType(),
            'service_url' => $adapter->getServiceURL(),
            'subscription' => $subscribe,
            'result' => $r,
        ];
    }

    public function generatePDF($hash, $identity): Response
    {
        $invoiceModel = $this->getInvoiceRepository()->findByHash($hash);

        if (!$invoiceModel instanceof Invoice) {
            throw new InformationException('Invoice not found');
        }

        $this->checkInvoiceAuth($invoiceModel, InvoiceOperation::READ);

        $invoice = $this->toApiArray($invoiceModel, false, $identity);
        $content = $this->renderInvoicePdfContent($invoiceModel, $invoice);

        return $this->createPdfResponse($content, $invoice['serie_nr']);
    }

    /**
     * Build the PDF invoice as an email attachment, provided the admin has opted into it via
     * the "Attach PDF invoice to invoice emails" setting. Returns null if the setting is off
     * or the PDF could not be generated, so callers can skip attaching without failing the send.
     *
     * @return array{content: string, name: string, mime: string}|null
     */
    public function getInvoicePdfAttachment(Invoice $invoiceModel): ?array
    {
        $systemService = $this->di['mod_service']('system');
        if (!$systemService->getParamValue('invoice_email_attach_pdf')) {
            return null;
        }

        try {
            $invoice = $this->toApiArray($invoiceModel, false);
            $content = $this->renderInvoicePdfContent($invoiceModel, $invoice);

            return [
                'content' => $content,
                'name' => $this->sanitizePdfFileName((string) $invoice['serie_nr']) . '.pdf',
                'mime' => 'application/pdf',
            ];
        } catch (\Exception $e) {
            $this->di['logger']->withChannel('email')->error('Failed to generate PDF invoice attachment: ' . $e->getMessage());

            return null;
        }
    }

    protected function renderInvoicePdfContent(Invoice $invoiceModel, array $invoice): string
    {
        $systemService = $this->di['mod_service']('system');
        $c = $systemService->getCompany();
        $document_format = $systemService->getParamValue('invoice_document_format', 'Letter');

        if ($invoiceModel->getCurrency() !== null) {
            $currencyCode = $invoiceModel->getCurrency();
        } else {
            $client = $this->di['em']->getRepository(Client::class)->find($invoiceModel->getClientId())
                ?? throw new InformationException('Client not found');
            $currencyCode = $client->getCurrency();
        }

        $CSS = $this->getPdfCss();

        $pdf = $this->createPdfGenerator();
        $pdf->setPaper($document_format, 'portrait');
        $pdf->setBasePath(Path::join(__DIR__, 'templates', 'pdf'));
        $options = $pdf->getOptions();

        $sellerLines = 0;
        $buyerLines = 0;
        $logoSource = '';

        if (!empty($c['logo_url'])) {
            [$logoSource, $remote] = $this->getPdfLogoSource($c['logo_url']);
            $options->set('isRemoteEnabled', $remote);
        }

        $vars = [
            'currency_code' => $currencyCode,
            'css' => $CSS,
            'logo_source' => $logoSource,
            'seller' => $this->getSellerData($invoice, $sellerLines),
            'seller_lines' => $sellerLines,
            'footer' => $this->getFooterInfo($c),
            'buyer' => $this->getBuyerData($invoice, $buyerLines),
            'buyer_lines' => $buyerLines,
            'invoice' => $invoice,
            'locale' => i18n::getActiveLocale($this->di['request'], true, $this->di['cookie_queue']),
        ];

        $twigFactory = $this->di['twig_factory'];
        $twig = $twigFactory->createBaseEnvironment();
        $loader = new FilesystemLoader(Path::join(__DIR__, 'templates', 'pdf'));
        $twig->setLoader($loader);
        $html = $twig->render($this->getPdfTemplate(), $vars);

        $pdf->setOptions($options);
        $pdf->loadHtml($html);
        $pdf->render();

        return $pdf->output();
    }

    public function addNote(Invoice $model, $note): bool
    {
        $n = $model->getNotes();
        $model->setNotes($n . date('Y-m-d H:i:s') . ': ' . $note . '       ' . PHP_EOL);
        $this->di['em']->persist($model);
        $this->di['em']->flush();

        return true;
    }

    /**
     * Return list of unpaid invoices which can be covered from client balance.
     * Deposit invoices are excluded as they cannot be covered from client balance.
     *
     * @return array
     */
    public function findAllUnpaid(?array $filter = null)
    {
        $sql = 'SELECT m.*
                FROM invoice as m
                    LEFT JOIN client as cl on m.client_id = cl.id
                    LEFT JOIN client_balance as cb on m.client_id = cb.client_id
                    LEFT JOIN invoice_item as pi on pi.invoice_id = m.id
                WHERE m.status = :status
                    AND m.approved = true
                    AND cb.amount >= pi.price
                    AND pi.type != :type';
        $params = ['status' => Invoice::STATUS_UNPAID, 'type' => InvoiceItem::TYPE_DEPOSIT];

        $client_id = isset($filter['client_id']) ? (int) $filter['client_id'] : null;

        if ($client_id) {
            $sql .= ' AND m.client_id = :client_id ';
            $params['client_id'] = $client_id;
        }

        $sql .= ' GROUP BY m.id, cl.id
                 ORDER BY m.id DESC';

        return $this->di['em']->getConnection()->fetchAllAssociative($sql, $params);
    }

    /**
     * @return Invoice[]
     */
    public function findAllPaid()
    {
        return $this->getInvoiceRepository()->findPaid();
    }

    /**
     * @return Invoice[]
     */
    public function getUnpaidInvoicesLateFor($days_after_issue = 2)
    {
        $cutoff = strtotime("-{$days_after_issue} days");

        return $this->getInvoiceRepository()->findUnpaidApprovedNotRemindedBefore($cutoff);
    }

    public function isInvoiceReminderIntervalEnabled(string $param, int $days, string $default = '', mixed $intervals = null): bool
    {
        if ($days < 1) {
            return false;
        }

        if ($intervals === null) {
            $systemService = $this->di['mod_service']('system');
            $intervals = $systemService->getParamValue($param, $default);
        }

        return in_array($days, $this->parseInvoiceReminderIntervals($intervals), true);
    }

    public function parseInvoiceReminderIntervals(mixed $value): array
    {
        if (is_array($value)) {
            $parts = $value;
        } else {
            $parts = preg_split('/[,\s]+/', (string) $value) ?: [];
        }

        $days = [];
        foreach ($parts as $part) {
            if ($part === '' || !is_numeric($part)) {
                continue;
            }

            $day = (int) $part;
            if ($day > 0) {
                $days[] = $day;
            }
        }

        $days = array_values(array_unique($days));
        sort($days);

        return $days;
    }

    private function _isAutoApproved(): bool
    {
        /**
         * @var \Box\Mod\System\Service $systemService
         */
        $systemService = $this->di['mod_service']('system');

        return (bool) $systemService->getParamValue('invoice_auto_approval', true);
    }

    /**
     * Whether an approved but still unpaid invoice may be edited.
     *
     * In many jurisdictions an issued (approved) invoice is immutable and
     * corrections must go through a credit note or a replacement invoice,
     * so this defaults to off. Enabling it treats approved unpaid invoices
     * as editable quotes: lines and details can change and the invoice is
     * re-sent to the client afterwards.
     */
    public function allowUnpaidInvoiceEdits(): bool
    {
        /**
         * @var \Box\Mod\System\Service $systemService
         */
        $systemService = $this->di['mod_service']('system');

        return (bool) $systemService->getParamValue('invoice_allow_edit_unpaid', false);
    }

    /**
     * Whether the invoice's content (lines, amounts, details) may be changed.
     *
     * Unapproved invoices are drafts and always editable. Approved invoices
     * are immutable once paid, refunded, or canceled; an approved unpaid
     * invoice is only editable when the `invoice_allow_edit_unpaid` setting
     * permits it.
     */
    public function isInvoiceEditable(Invoice $invoice): bool
    {
        return $this->isInvoiceStateEditable($invoice->getStatus(), $invoice->isApproved());
    }

    public function isInvoiceStateEditable(string $status, bool $approved): bool
    {
        if (in_array($status, [Invoice::STATUS_PAID, Invoice::STATUS_REFUNDED, Invoice::STATUS_CANCELED], true)) {
            return false;
        }

        if (!$approved) {
            return true;
        }

        return $this->allowUnpaidInvoiceEdits();
    }

    /**
     * Lock an invoice row and return its current approval/status state.
     *
     * Callers must keep the surrounding transaction open until their mutation is complete. The
     * lock is the serialization point shared by invoice edits, item edits, approval, payment,
     * and deletion.
     *
     * @return array{status: string, approved: bool}
     */
    public function lockInvoiceState(Invoice $invoice): array
    {
        if ($invoice->getId() === null) {
            return [
                'status' => $invoice->getStatus(),
                'approved' => $invoice->isApproved(),
            ];
        }

        $state = $this->getInvoiceRepository()->lockAndGetState($invoice->getId());
        if ($state === null) {
            throw new InformationException('Invoice not found');
        }

        return $state;
    }

    /**
     * @return array{status: string, approved: bool}
     */
    private function lockAndRefreshInvoice(Invoice $invoice): array
    {
        $state = $this->lockInvoiceState($invoice);

        if ($invoice->getId() !== null) {
            $this->di['em']->refresh($invoice);
        }

        return $state;
    }

    /**
     * Lock an order row and refresh the entity, so a concurrent request
     * cannot attach the same order to another invoice between the link check
     * and the line creation below. The caller must hold the surrounding
     * transaction open until linking is complete.
     */
    private function lockAndRefreshOrder(Order $order): void
    {
        if ($order->getId() === null) {
            return;
        }

        $this->di['em']->getRepository(Order::class)->lockAndGetUnpaidInvoiceId($order->getId());
        $this->di['em']->refresh($order);
    }

    /**
     * @param bool $subscribe
     */
    public function getPaymentInvoice(Invoice $invoice, $subscribe = false): \Payment_Invoice
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
        $items = [];
        foreach ($proforma['lines'] as $item) {
            $pi = new \Payment_Invoice_Item();
            $pi
                ->setId($item['id'])
                ->setTitle($item['title'])
                ->setDescription($item['title'])
                ->setPrice($item['price'])
                ->setTax($item['tax'])
                ->setQuantity($item['quantity']);
            $items[] = $pi;
            if (is_null($first_title) && Tools::safeCount($proforma['lines']) == 1) {
                $first_title = $item['title'];
            }
        }

        $invoice_number_padding = $this->di['mod_service']('system')->getParamValue('invoice_number_padding');
        $invoice_number_padding = $invoice_number_padding !== null && $invoice_number_padding !== '' ? $invoice_number_padding : 5;

        $params = [
            ':id' => sprintf('%0' . $invoice_number_padding . 's', $proforma['nr']),
            ':serie' => $proforma['serie'],
            ':title' => $first_title,
        ];
        if ($first_title) {
            $title = __trans('Payment for invoice :serie:id [:title]', $params);
        } else {
            $title = __trans('Payment for invoice :serie:id', $params);
        }

        $mpi = new \Payment_Invoice();
        $mpi->setId($invoice->getId());
        $mpi->setNumber($proforma['nr']);
        $mpi->setBuyer($buyer);
        $mpi->setCurrency($proforma['currency']);
        $mpi->setTitle($title);
        $mpi->setItems($items);

        $subscribeService = $this->di['mod_service']('Invoice', 'Subscription');
        // can subscribe only if proforma has one item with defined period
        if ($subscribe && $subscribeService->isSubscribable($invoice->getId())) {
            $subitem = $this->getInvoiceItemRepository()->findOneByInvoiceIdAndType($invoice->getId(), InvoiceItem::TYPE_ORDER);
            if ($subitem instanceof InvoiceItem) {
                $period = $this->di['period']($subitem->getPeriod());

                $bs = new \Payment_Invoice_Subscription();
                $bs->setId($proforma['id']);
                $bs->setAmount($mpi->getTotalWithTax());
                $bs->setCycle($period->getQty());
                $bs->setUnit($period->getUnit());

                $mpi->setSubscription($bs);
                $mpi->setTitle('Subscription for ' . $subitem->getTitle());
            }
        }

        return $mpi;
    }

    public function getBuyer(Invoice $invoice): array
    {
        return [
            'first_name' => $invoice->getBuyerFirstName(),
            'last_name' => $invoice->getBuyerLastName(),
            'company' => $invoice->getBuyerCompany(),
            'address' => $invoice->getBuyerAddress(),
            'city' => $invoice->getBuyerCity(),
            'state' => $invoice->getBuyerState(),
            'country' => $invoice->getBuyerCountry(),
            'phone' => $invoice->getBuyerPhone(),
            'phone_cc' => $invoice->getBuyerPhoneCc() ?? '',
            'email' => $invoice->getBuyerEmail(),
            'zip' => $invoice->getBuyerZip(),
        ];
    }

    public function rmByClient(Client $client): void
    {
        $invoices = $this->getInvoiceRepository()->findByClientId((int) $client->getId());
        foreach ($invoices as $invoice) {
            $this->rmInvoice($invoice);
        }
    }

    public function isInvoiceTypeDeposit(Invoice $invoice): bool
    {
        $invoiceItems = $this->getInvoiceItemRepository()->findByInvoiceId((int) $invoice->getId());

        foreach ($invoiceItems as $item) {
            if ($item->getType() == InvoiceItem::TYPE_DEPOSIT) {
                return true;
            }
        }

        return false;
    }

    public function exportCSV(array $headers): Response
    {
        if ($headers) {
            $headers = array_values(array_intersect(self::EXPORTABLE_COLUMNS, $headers));
        }

        if (!$headers) {
            $headers = self::DEFAULT_EXPORT_COLUMNS;
        }

        return $this->di['csv_response_factory']->create('invoice', 'invoices.csv', $headers);
    }

    public function checkInvoiceAuth(Invoice $invoice, InvoiceOperation $operation = InvoiceOperation::READ): void
    {
        if ($this->di['auth']->isAdminLoggedIn() || Environment::isCLI()) {
            return;
        }

        $invoiceClientId = $invoice->getClientId();
        $systemService = $this->di['mod_service']('system');
        $hash_access = $systemService->getParamValue('invoice_accessible_from_hash', '0');
        $hashAccessAllowed = $hash_access === '1' && in_array($operation, [InvoiceOperation::READ, InvoiceOperation::PAYMENT], true);

        $client = null;
        if ($this->di['auth']->isClientLoggedIn()) {
            $client = $this->di['loggedin_client'];
        }
        $isOwner = $client !== null && (int) $invoiceClientId === (int) $client->getId();

        if (!$isOwner && $this->isHashExpired($invoice)) {
            throw new InformationException('This invoice link has expired', [], 403);
        }

        if (!$hashAccessAllowed && !$isOwner) {
            throw new InformationException('You do not have permission to perform this action', [], 403);
        }
    }

    /**
     * Computes the hash_expires_at timestamp. Returns null when the admin
     * has disabled hash expiration (invoice_hash_lifetime_days = 0).
     */
    private function computeHashExpiration(): ?\DateTime
    {
        $days = (int) $this->di['mod_service']('system')->getParamValue('invoice_hash_lifetime_days', '90');
        if ($days <= 0) {
            return null;
        }

        return new \DateTime("+{$days} days");
    }

    /**
     * Re-stamps hash_expires_at on an existing invoice using the current
     * invoice_hash_lifetime_days setting. Also self-heals invoices whose
     * hash is empty or in a legacy format by generating a fresh modern
     * hash. Called when an admin re-sends an invoice or payment reminder.
     */
    public function extendInvoiceHashLifetime(Invoice $invoice): void
    {
        $hash = $invoice->getHash();
        $isModern = is_string($hash) && preg_match('/^[a-f0-9]{30,60}$/', $hash) === 1;
        if (!$isModern) {
            $invoice->setHash(bin2hex(random_bytes(random_int(15, 30))));
        }
        $invoice->setHashExpiresAt($this->computeHashExpiration());
        $this->di['em']->persist($invoice);
        $this->di['em']->flush();
    }

    /**
     * Regenerates the hash if it is missing or in a legacy format. No-op
     * for valid hashes, making it safe to call from read paths.
     */
    public function ensureValidHash(Invoice $invoice): void
    {
        $hash = $invoice->getHash();
        $isModern = is_string($hash) && preg_match('/^[a-f0-9]{30,60}$/', $hash) === 1;
        if ($isModern) {
            return;
        }

        $invoice->setHash(bin2hex(random_bytes(random_int(15, 30))));
        $invoice->setHashExpiresAt($this->computeHashExpiration());
        $this->di['em']->persist($invoice);
        $this->di['em']->flush($invoice);
    }

    private function isHashExpired(Invoice $invoice): bool
    {
        $expires = $invoice->getHashExpiresAt();
        if ($expires === null) {
            return false;
        }

        return $expires->getTimestamp() < time();
    }

    // Start of PDF related functions
    protected function createPdfGenerator(): Dompdf
    {
        $fontCachePath = Path::join(PATH_CACHE, 'dompdf');
        $this->filesystem->mkdir($fontCachePath);

        $options = new Options();
        $options->setFontDir($fontCachePath);
        $options->setFontCache($fontCachePath);
        $options->setChroot(PATH_ROOT);

        return new Dompdf($options);
    }

    protected function createPdfResponse(string $content, string $fileName): Response
    {
        $response = (new ResponseFactory())->html($content);
        $safeFileName = str_replace(['/', '\\', '%'], '-', trim($fileName));
        if ($safeFileName === '') {
            $safeFileName = 'invoice';
        }

        $fallbackFileName = $this->sanitizePdfFileName($fileName);

        $disposition = $response->headers->makeDisposition(
            HeaderUtils::DISPOSITION_INLINE,
            $safeFileName . '.pdf',
            $fallbackFileName . '.pdf'
        );

        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    /**
     * Reduce a file name to a plain ASCII form safe for both HTTP fallback
     * Content-Disposition names and MIME attachment part headers.
     */
    private function sanitizePdfFileName(string $fileName): string
    {
        $fallbackFileName = preg_replace('/[^A-Za-z0-9._-]/', '-', trim($fileName));
        $fallbackFileName = trim((string) $fallbackFileName, '.-');

        return $fallbackFileName !== '' ? $fallbackFileName : 'invoice';
    }

    protected function getPdfCss(): string
    {
        $basePath = Path::join(__DIR__, 'templates', 'pdf');
        $customCssPath = Path::join($basePath, 'custom-invoice.css');
        $defaultCssPath = Path::join($basePath, 'default-invoice.css');

        if ($this->filesystem->exists($customCssPath)) {
            $CSS = $this->filesystem->readFile($customCssPath);
        } else {
            $CSS = $this->filesystem->readFile($defaultCssPath);
        }

        if (empty($CSS)) {
            $CSS = $this->filesystem->readFile($defaultCssPath);
        }

        return $CSS;
    }

    protected function getPdfTemplate(): string
    {
        if ($this->filesystem->exists(Path::join(__DIR__, 'templates', 'pdf', 'custom-invoice.twig'))) {
            return 'custom-invoice.twig';
        }

        return 'default-invoice.twig';
    }

    protected function getPdfLogoSource(string $originalUrl): array
    {
        $source = parse_url($originalUrl, PHP_URL_PATH);
        $remote = false;

        // prevent openbasedir error from preventing pdf creation when debug mode is enabled
        if (@!$this->filesystem->exists($source)) {
            $source = Path::join($this->di['request']->server->get('DOCUMENT_ROOT', ''), $source);
            if (!$this->filesystem->exists($source)) {
                // Assume the URL points to an image not hosted on this server
                $source = $originalUrl;
                $remote = true;
            }
        }

        if (!$remote) {
            $canonicalPath = Path::canonicalize($source);
            $canonicalRoot = Path::canonicalize(PATH_ROOT);
            if (!Path::isBasePath($canonicalRoot, $canonicalPath)) {
                $source = $originalUrl;
                $remote = true;
            } elseif ($canonicalPath !== $source) {
                $source = $canonicalPath;
            }
        }

        // Only permit http/https remote URLs. Other schemes such as file://, php://, or phar://
        // could be passed to Dompdf with remote loading enabled, leading to local file disclosure
        // or other server-side vulnerabilities. Malformed URLs (where parse_url returns non-string)
        // are also rejected by skipping the logo entirely.
        if ($remote) {
            $scheme = parse_url($source, PHP_URL_SCHEME);
            if (!is_string($scheme) || !in_array(strtolower($scheme), ['http', 'https'], true)) {
                return ['', false];
            }
        }

        if (!$remote && str_ends_with($source, '.svg')) {
            $source = 'data:image/svg+xml;base64,' . base64_encode($this->filesystem->readFile($source));
            $remote = false;
        }

        return [$source, $remote];
    }

    private function getSellerData(array $invoice, int &$lines): array
    {
        $sourceData = [
            'Name' => $invoice['seller']['company'],
            'Address 1' => $invoice['seller']['address_1'],
            'Address 2' => $invoice['seller']['address_2'],
            'Address 3' => $invoice['seller']['address_3'],
            'Phone' => $invoice['seller']['phone'],
            'Email' => $invoice['seller']['email'],
            'VAT Number' => $invoice['seller']['company_vat'],
        ];

        foreach ($sourceData as $label => $data) {
            if ($data === null || empty(trim((string) $data))) {
                unset($sourceData[$label]);
            } else {
                ++$lines;
            }
        }

        return $sourceData;
    }

    private function getBuyerData(array $invoice, int &$lines): array
    {
        $sourceData = [
            'Company' => $invoice['buyer']['company'],
            'Name' => $invoice['buyer']['first_name'] . ' ' . $invoice['buyer']['last_name'],
            'Address' => $invoice['buyer']['address'],
            'City' => $invoice['buyer']['city'],
            'State' => $invoice['buyer']['state'],
            'Zip' => $invoice['buyer']['zip'],
            'Country' => $invoice['buyer']['country'],
            'Phone' => $invoice['buyer']['phone'],
            'VAT Number' => $invoice['buyer']['company_vat'],
        ];

        foreach ($sourceData as $label => $data) {
            if ($data === null || empty(trim((string) $data))) {
                unset($sourceData[$label]);
            } else {
                ++$lines;
            }
        }

        return $sourceData;
    }

    private function getFooterInfo(array $company): array
    {
        // Keep all keys defined so PDF templates rendered with strict_variables don't fail on missing optional company details.
        return [
            'company_name' => $company['name'] ?? null,
            'bank_name' => $company['bank_name'] ?? null,
            'account_number' => $company['account_number'] ?? null,
            'bic' => $company['bic'] ?? null,
            'display_bank_info' => $company['display_bank_info'] ?? null,
            'company_vat' => $company['vat_number'] ?? null,
            'company_number' => $company['number'] ?? null,
            'www' => $company['www'] ?? null,
            'email' => $company['email'] ?? null,
            'phone' => $company['tel'] ?? null,
            'signature' => $company['signature'] ?? null,
            'address_1' => $company['address_1'] ?? null,
            'address_2' => $company['address_2'] ?? null,
            'address_3' => $company['address_3'] ?? null,
        ];
    }

    /**
     * Get the order ID from an invoice's items.
     * Returns the first order ID found in the invoice items.
     *
     * @param int $invoiceId The invoice ID to search
     *
     * @return int|null The order ID or null if not found
     */
    public function getOrderIdFromInvoice(int $invoiceId): ?int
    {
        $item = $this->getInvoiceItemRepository()->findOneByInvoiceIdAndType($invoiceId, InvoiceItem::TYPE_ORDER);

        if ($item instanceof InvoiceItem) {
            return (int) $item->getRelId();
        }

        return null;
    }

    /**
     * Generate a renewal invoice for a subscription payment that arrived without an invoice.
     * This handles the case where PayPal/Stripe sends a subscription payment before
     * the cron job generates the renewal invoice.
     *
     * @param string $subscriptionSid The subscription ID from the payment gateway
     * @param int    $clientId        The client ID
     *
     * @return Invoice|null The generated invoice or null if unable to generate
     */
    public function generateRenewalInvoiceForSubscriptionPayment(string $subscriptionSid, int $clientId): ?Invoice
    {
        try {
            $subscription = $this->di['em']->getRepository(Entity\Subscription::class)->findOneBy(['sid' => $subscriptionSid]);
            if (!$subscription instanceof Entity\Subscription) {
                return null;
            }

            if ($subscription->getRelType() !== 'invoice') {
                return null;
            }

            $originalOrderId = $this->getOrderIdFromInvoice((int) $subscription->getRelId());
            if ($originalOrderId === null) {
                return null;
            }

            $originalOrder = $this->di['em']->getRepository(Order::class)->find($originalOrderId);
            if (!$originalOrder instanceof Order) {
                return null;
            }

            // Use the original order directly. A previous approach searched for
            // any active order with the same product_id, but that is broken for
            // products like domain registrations where multiple orders share
            // the same product — it would find an unrelated order and generate
            // a renewal invoice for the wrong service.
            //
            // Accept the same "still renewable" statuses generateForOrder() itself
            // recognizes below, not just active: the batch-suspend cron can suspend
            // an order (on expiry) before a delayed gateway subscription-payment IPN
            // for that same renewal arrives. generateForOrder() already reuses any
            // unpaid invoice the cron generated ahead of time, so this lets that
            // invoice be paid and the order un-suspended/renewed as normal.
            if (!in_array($originalOrder->getStatus(), [
                Order::STATUS_ACTIVE,
                Order::STATUS_SUSPENDED,
                Order::STATUS_FAILED_RENEW,
            ], true)) {
                return null;
            }

            $invoice = $this->generateForOrder($originalOrder);
            $this->approveInvoice($invoice, ['use_credits' => false]);

            $this->di['logger']->info("Generated renewal invoice #{$invoice->getId()} for subscription payment (SID: {$subscriptionSid}, client: {$clientId}).");

            return $invoice;
        } catch (\Exception $e) {
            $this->di['logger']->warning('Failed to generate renewal invoice for subscription payment: ' . $e->getMessage());

            return null;
        }
    }

    // End of PDF related functions
}
