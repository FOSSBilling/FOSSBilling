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
use Box\Mod\Currency\Entity\Currency;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\Transaction;
use Box\Mod\Invoice\Repository\InvoiceItemRepository;
use Box\Mod\Invoice\Repository\InvoiceRepository;
use Box\Mod\Order\Entity\Order;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use FOSSBilling\Container\InjectionAwareInterface;
use FOSSBilling\Doctrine\EntityManagerFactory;
use FOSSBilling\Doctrine\RowLock;
use FOSSBilling\Doctrine\SqlExpr;
use FOSSBilling\Exception\InformationException;
use FOSSBilling\Http\ResponseFactory;
use FOSSBilling\I18n\I18n;
use FOSSBilling\System\Environment;
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
        $result['serie_nr'] = $result['serie'] . sprintf('%0' . $this->getInvoiceNumberPadding() . 's', $nr);

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
        $result['approved'] = $row['approved'];
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

    public static function onAfterAdminInvoicePaymentReceived(\FOSSBilling\Event\Event $event): bool
    {
        $params = $event->getParameters();
        $di = $event->getDi();
        $service = $di['mod_service']('invoice');

        try {
            $invoiceModel = $di['em']->getRepository(Invoice::class)->find($params['id'] ?? 0);
            if (!$invoiceModel instanceof Invoice) {
                return true;
            }

            $invoice = $service->toApiArray($invoiceModel, true, null, true);
            if (($invoice['total'] ?? 0) > 0) {
                $service->sendInvoiceEmail($invoiceModel, $invoice, 'mod_invoice_paid');
            }
        } catch (\Exception $exc) {
            $di['logger']->withChannel('email')->error('Failed to send email for invoice payment', ['exception' => $exc]);
        }

        return true;
    }

    public static function onAfterInvoiceCreate(\FOSSBilling\Event\Event $event): bool
    {
        $params = $event->getParameters();
        $di = $event->getDi();
        $service = $di['mod_service']('invoice');

        try {
            $invoiceModel = $di['em']->getRepository(Invoice::class)->find($params['id']);
            if (!$invoiceModel instanceof Invoice) {
                return true;
            }

            $invoice = $service->toApiArray($invoiceModel, true, null, true);
            $service->sendInvoiceEmail($invoiceModel, $invoice, 'mod_invoice_created');
        } catch (\Exception $exc) {
            $di['logger']->withChannel('email')->error('Failed to send email for invoice creation', ['exception' => $exc]);
        }

        return true;
    }

    public static function onAfterAdminInvoiceApprove(\FOSSBilling\Event\Event $event): bool
    {
        $params = $event->getParameters();
        $di = $event->getDi();
        $service = $di['mod_service']('invoice');

        try {
            $invoiceModel = $di['em']->getRepository(Invoice::class)->find($params['id'] ?? 0);

            if (($params['total'] ?? 0) > 0
                && ($params['status'] ?? null) !== Invoice::STATUS_PAID
                && isset($params['client']['id'])
            ) {
                if ($invoiceModel instanceof Invoice) {
                    $service->sendInvoiceEmail($invoiceModel, $params, 'mod_invoice_created', (int) $params['client']['id']);
                }
            }

            // Sending the created-email extends the hash lifetime so the
            // recipient has a fresh window to act on the link.
            if ($invoiceModel instanceof Invoice) {
                $service->extendInvoiceHashLifetime($invoiceModel);
            }
        } catch (\Exception $exc) {
            $di['logger']->withChannel('email')->error('Failed to send email for invoice approval', ['exception' => $exc]);
        }

        return true;
    }

    private function sendInvoiceEmail(Invoice $invoice, array $invoiceData, string $templateCode, ?int $clientId = null): void
    {
        $email = [
            'to_client' => $clientId ?? $invoice->getClientId(),
            'code' => $templateCode,
            'invoice' => $invoiceData,
        ];
        $email = $this->withBillingRecipient($email, $invoiceData);

        $attachment = $this->getInvoicePdfAttachment($invoice);
        if ($attachment !== null) {
            $email['attachment'] = $attachment;
        }

        $this->di['mod_service']('email')->sendTemplate($email);
    }

    public static function onAfterAdminInvoiceReminderSent(\FOSSBilling\Event\Event $event): void
    {
        $params = $event->getParameters();
        $di = $event->getDi();
        $service = $di['mod_service']('invoice');

        try {
            $invoiceModel = $di['em']->getRepository(Invoice::class)->find($params['id'] ?? 0);
            if (!$invoiceModel instanceof Invoice) {
                return;
            }

            $invoice = $service->toApiArray($invoiceModel, true, null, true);
            $email = [];
            $email['to_client'] = $invoiceModel->getClientId();
            $email['code'] = 'mod_invoice_payment_reminder';
            $email['invoice'] = $invoice;
            $email = $service->withBillingRecipient($email, $invoice);
            $attachment = $service->getInvoicePdfAttachment($invoiceModel);
            if ($attachment !== null) {
                $email['attachment'] = $attachment;
            }
            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);

            // Sending a payment reminder also re-extends the hash lifetime
            // since the recipient is being re-engaged via the same link.
            $service->extendInvoiceHashLifetime($invoiceModel);
        } catch (\Exception $exc) {
            $di['logger']->withChannel('email')->error('Failed to send invoice reminder email', ['exception' => $exc]);
        }
    }

    public static function onEventBeforeInvoiceIsDue(\FOSSBilling\Event\Event $event): void
    {
        $params = $event->getParameters();
        $di = $event->getDi();
        $service = $di['mod_service']('invoice');
        $claimed = false;

        try {
            if (!$service->isInvoiceReminderIntervalEnabled('invoice_reminder_before_due_days', (int) ($params['days_left'] ?? 0), '', $params['reminder_intervals'] ?? null)) {
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
                    'id' => $params['id'] ?? 0,
                    'now' => $now->format('Y-m-d H:i:s'),
                    'today_start' => $now->modify('today')->format('Y-m-d H:i:s'),
                ]
            );
            if (!$claimed) {
                return;
            }

            $invoiceModel = $di['em']->getRepository(Invoice::class)->find($params['id'] ?? 0);
            if ($invoiceModel instanceof Invoice) {
                $service->sendInvoiceReminder($invoiceModel);
            }
        } catch (\Exception $exc) {
            if ($claimed) {
                // sendInvoiceReminder()'s downstream send handler (onAfterAdminInvoiceReminderSent)
                // catches its own failures internally, so any exception reaching here means the
                // email was never queued. Release the claim so a later cron run retries it instead
                // of the reminder being silently lost for the day.
                $di['em']->getConnection()->executeStatement('UPDATE invoice SET reminded_at = NULL WHERE id = :id', ['id' => $params['id'] ?? 0]);
            }
            $di['logger']->withChannel('email')->error('Failed to send invoice reminder email', ['id' => $params['id'] ?? null, 'exception' => $exc]);
        }
    }

    public static function onAfterAdminCronRun(\FOSSBilling\Event\Event $event): void
    {
        $di = $event->getDi();
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

    public static function onEventAfterInvoiceIsDue(\FOSSBilling\Event\Event $event): void
    {
        $params = $event->getParameters();
        $di = $event->getDi();
        $service = $di['mod_service']('invoice');
        $claimed = false;

        try {
            if (!$service->isInvoiceReminderIntervalEnabled('invoice_reminder_after_due_days', (int) ($params['days_passed'] ?? 0), '5', $params['reminder_intervals'] ?? null)) {
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
                    'id' => $params['id'] ?? 0,
                    'now' => $now->format('Y-m-d H:i:s'),
                    'today_start' => $todayStart->format('Y-m-d H:i:s'),
                    'tomorrow_start' => $todayStart->modify('+1 day')->format('Y-m-d H:i:s'),
                ]
            );
            if (!$claimed) {
                return;
            }

            $invoiceModel = $di['em']->getRepository(Invoice::class)->find($params['id']);
            if (!$invoiceModel instanceof Invoice) {
                return;
            }

            $invoice = $service->toApiArray($invoiceModel, true, null, true);
            if (!isset($invoice['client']) || !is_array($invoice['client']) || !isset($invoice['client']['id'])) {
                throw new \FOSSBilling\Exception\BaseException('Invoice client data is unavailable.');
            }

            $email = [];
            $email['to_client'] = $invoice['client']['id'];
            $email['code'] = 'mod_invoice_due_after';
            $email['days_passed'] = $params['days_passed'];
            $email['invoice'] = $invoice;
            $email = $service->withBillingRecipient($email, $invoice);
            $attachment = $service->getInvoicePdfAttachment($invoiceModel);
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
                $di['em']->getConnection()->executeStatement('UPDATE invoice SET reminded_at = NULL WHERE id = :id', ['id' => $params['id'] ?? 0]);
            }
            $di['logger']->withChannel('email')->error('Failed to send overdue invoice email', ['id' => $params['id'] ?? null, 'exception' => $exc]);
        }
    }

    /**
     * Route invoice notifications to the client's optional billing address while retaining
     * to_client so templates, timezone handling, and client email history keep working.
     */
    public function withBillingRecipient(array $email, array $invoice): array
    {
        $billingEmail = trim((string) ($invoice['client']['billing_email'] ?? ''));
        if ($billingEmail !== '') {
            $email['to'] = $billingEmail;
        }

        return $email;
    }

    public function markAsPaid(Invoice $invoice, $charge = true, $execute = false, bool $deferEvents = false): bool
    {
        if ($invoice->getStatus() == Invoice::STATUS_PAID) {
            return true;
        }

        $invoiceItems = $this->getInvoiceItemRepository()->findByInvoiceId((int) $invoice->getId());
        $invoiceItemService = $this->di['mod_service']('Invoice', 'InvoiceItem');
        $systemService = $this->di['mod_service']('system');

        $this->di['em']->wrapInTransaction(function () use ($invoice, $charge, $invoiceItems, $invoiceItemService, $systemService): void {
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
                throw new \FOSSBilling\Exception\BaseException("Currency rate for code '{$invoice->getCurrency()}' is not configured.");
            }
            $invoice->setCurrencyRate($currencyRate);

            $invoice->setStatus(Invoice::STATUS_PAID);
            $invoice->setPaidAt(new \DateTime());
            $this->di['em']->persist($invoice);
            $this->di['em']->flush();

            $this->countIncome($invoice);
            $productService = $this->di['mod_service']('Product');
            $productService->commitReservedPromoRedemptionsForInvoice($invoice);
        });

        // Listeners render PDFs and send email, so a caller holding row locks defers this until
        // after it has committed rather than holding them for the duration of an SMTP send.
        if (!$deferEvents) {
            $this->firePaymentReceivedEvent($invoice);
        }

        if ($execute) {
            $this->executeInvoiceItemTasks($invoiceItems, $invoiceItemService);
        }

        $this->di['logger']->info("Marked invoice {$invoice->getId()} as paid.");

        return true;
    }

    public function markAsPaidByAdmin(Invoice $invoice, array $data = []): bool
    {
        if ($invoice->getStatus() === Invoice::STATUS_PAID) {
            return true;
        }

        $execute = \FOSSBilling\Utils\Normalizer::normalizeBoolean($data['execute'] ?? false);
        $payGateway = $this->validateAdminMarkAsPaidRequest($data, $invoice);
        $transactionId = isset($data['transactionId']) ? trim((string) $data['transactionId']) : null;

        if ((int) $invoice->getGateway()?->getId() !== (int) $payGateway->getId()) {
            $invoice->setGateway($payGateway);
            $this->di['em']->persist($invoice);
            $this->di['em']->flush();
        }

        if ($payGateway->getGateway() === 'Custom' && $payGateway->isEnabled()) {
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

            $result = $this->markAsPaid($invoice, false, $execute);
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
                throw new \FOSSBilling\Exception\BaseException('Unable to determine the next invoice number');
            }

            // Seeding the counter and reserving from it has to be one locked step too, otherwise
            // two callers deriving the same seed both write it and both reserve the same number.
            $next_nr = $systemService->reserveNextNumericParamValue('invoice_starting_number', intval($r->getNr()) + 1);
            if ($next_nr === null) {
                throw new \FOSSBilling\Exception\BaseException('Unable to determine the next invoice number');
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
                throw new \FOSSBilling\Exception\BaseException('Default currency not found');
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
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminInvoiceApprove', 'params' => $this->toApiArray($invoice)]);

        $invoice->setApproved(true);
        $this->di['em']->persist($invoice);
        $this->di['em']->flush();

        if (isset($data['use_credits']) && $data['use_credits']) {
            $this->tryPayWithCredits($invoice);
        }

        $this->di['events_manager']->fire(['event' => 'onAfterAdminInvoiceApprove', 'params' => $this->toApiArray($invoice, true, null, true)]);

        $this->di['logger']->info("Approved invoice {$invoice->getId()}.");

        return true;
    }

    public function validatePaymentAmount(float $received, float $expected): void
    {
        $epsilon = 0.01;
        if ($received < $expected - $epsilon) {
            throw new \FOSSBilling\Exception\BaseException('Payment amount does not match the expected invoice total. Expected :expected, received :received.', [':expected' => number_format($expected, 2, '.', ''), ':received' => number_format($received, 2, '.', '')]);
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
        if (!$invoice->isApproved()) {
            return false;
        }
        if ($invoice->getStatus() == Invoice::STATUS_PAID) {
            if (DEBUG) {
                $this->di['logger']->withChannel('billing')->info("Skipping credit payment for already paid invoice {$invoice->getId()}.");
            }

            return false;
        }

        $paid = $this->di['em']->wrapInTransaction(function () use ($invoice): bool {
            $clientId = (int) $invoice->getClientId();
            $cbrepo = $this->di['mod_service']('Client', 'Balance');

            // Locks the balance for the rest of this transaction, so a concurrent request cannot
            // spend the same credit.
            $balance = $cbrepo->getClientBalanceForUpdate($clientId);

            // Another request could have paid this invoice while we waited for the lock. A locking
            // read, as a plain one can be served from a snapshot predating that request's commit.
            if ($this->getInvoiceRepository()->lockAndGetStatus((int) $invoice->getId()) === Invoice::STATUS_PAID) {
                return false;
            }

            $required = $this->getTotalWithTax($invoice);
            $epsilon = 0.01;
            $difference = $balance - $required;

            if ($difference < -$epsilon) {
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

            if ($required > $epsilon) {
                // Nothing at or below the epsilon is actually charged against the client's balance,
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
        $this->di['events_manager']->fire(['event' => 'onAfterAdminInvoicePaymentReceived', 'params' => ['id' => $invoice->getId()]]);
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

    public function refundInvoice(Invoice $invoice, $note = null): ?int
    {
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminInvoiceRefund', 'params' => $this->toApiArray($invoice)]);

        $systemService = $this->di['mod_service']('system');
        $logic = $systemService->getParamValue('invoice_refund_logic', 'manual');
        $result = null;

        switch ($logic) {
            case 'credit_note':
            case 'negative_invoice':
                $total = $this->getTotalWithTax($invoice);
                if ($total <= 0) {
                    throw new InformationException('Cannot refund invoice with negative amount');
                }

                $new = new Invoice();
                $new->setClientId($invoice->getClientId());
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

                $new->setPaidAt(new \DateTime());
                $this->di['em']->persist($new);
                $this->di['em']->flush();

                $invoiceItems = $this->getInvoiceItemRepository()->findByInvoiceId((int) $invoice->getId());
                $entityManager = $this->di['em'];
                foreach ($invoiceItems as $item) {
                    $pi = new InvoiceItem();
                    $pi->setInvoice($new);
                    $pi->setType($item->getType());
                    $pi->setRelId($item->getRelId());
                    $pi->setTask($item->getTask());
                    $pi->setStatus(InvoiceItem::STATUS_EXECUTED); // Mark refund invoice as executed
                    $pi->setTitle($item->getTitle());
                    $pi->setPeriod($item->getPeriod());
                    $pi->setQuantity($item->getQuantity());
                    $pi->setUnit($item->getUnit());
                    $pi->setCharged(1);
                    $pi->setPrice(-($item->getPrice() ?? 0));
                    $pi->setTaxed($item->getTaxed());
                    $entityManager->persist($pi);
                }
                $entityManager->flush();

                $this->countIncome($new);

                $this->addNote($invoice, "Refund invoice #{$new->getId()} generated.");
                $this->addNote($new, "Refund for #{$invoice->getId()} invoice.");
                if (!empty($note)) {
                    $this->addNote($new, $note);
                }

                if ($logic == 'negative_invoice') {
                    $new->setSerie($systemService->getParamValue('invoice_series_paid'));
                    $this->di['em']->persist($new);
                    $this->di['em']->flush();
                }

                if ($logic == 'credit_note') {
                    $next_nr = $systemService->getParamValue('invoice_cn_starting_number', 1);
                    $new->setSerie($systemService->getParamValue('invoice_cn_series', 'CN-'));
                    $new->setNr($next_nr);
                    $this->di['em']->persist($new);
                    $this->di['em']->flush();

                    // update next credit note starting number
                    $systemService->setParamValue('invoice_cn_starting_number', ++$next_nr, true);
                }
                $result = (int) $new->getId();

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

        $this->di['events_manager']->fire(['event' => 'onAfterAdminInvoiceRefund', 'params' => ['id' => $invoice->getId()]]);

        $this->di['logger']->info("Refunded invoice #{$invoice->getId()}.");

        return $result;
    }

    public function updateInvoice(Invoice $model, array $data): bool
    {
        $invoiceItemService = $this->di['mod_service']('Invoice', 'InvoiceItem');
        $previousStatus = $model->getStatus();

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminInvoiceUpdate', 'params' => $data]);

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

        $this->di['events_manager']->fire(['event' => 'onAfterAdminInvoiceUpdate', 'params' => $this->toApiArray($model)]);

        $this->di['logger']->info("Updated invoice {$model->getId()}.");

        return true;
    }

    public function rmInvoice(Invoice $model): bool
    {
        $productService = $this->di['mod_service']('Product');
        $productService->releaseReservedPromoRedemptionsForInvoice($model, 'invoice_deleted');
        $productService->releaseReservedStockForInvoice($model, 'invoice_deleted');

        $entityManager = $this->di['em'];
        $entityManager->wrapInTransaction(function () use ($model, $entityManager): void {
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
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminInvoiceDelete', 'params' => ['id' => $model->getId()]]);

        $id = $model->getId();
        $this->rmInvoice($model);

        $this->di['events_manager']->fire(['event' => 'onAfterAdminInvoiceDelete', 'params' => ['id' => $id]]);

        $this->di['logger']->info('Removed invoice #{id}', ['id' => $id]);

        return true;
    }

    public function renewInvoice(Order $model, array $data): ?int
    {
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminGenerateRenewalInvoice', 'params' => ['order_id' => $model->getId()]]);

        $due_days = isset($data['due_days']) ? (int) $data['due_days'] : null;
        $invoice = $this->generateForOrder($model, $due_days);
        $this->approveInvoice($invoice, ['id' => $invoice->getId(), 'use_credits' => true]);

        $this->di['events_manager']->fire(['event' => 'onAfterAdminGenerateRenewalInvoice', 'params' => ['order_id' => $model->getId(), 'id' => $invoice->getId()]]);

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
    public function generateForOrder(Order $order, $due_days = null): Invoice
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
                    throw new \FOSSBilling\Exception\BaseException("Currency rate for '{$order->getCurrency()}' is not configured");
                }

                $renewalLine = $productService->getProductRenewalLineConfig($product, $config);
                $price = $renewalLine['price'] * $rate;
                $line = [
                    'price' => $price,
                    'quantity' => $renewalLine['quantity'],
                ];
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
        $invoiceItemService->generateFromOrder($proforma, $order, InvoiceItem::TASK_RENEW, $price, $line);

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

        if (\FOSSBilling\Utils\Arr::safeCount($orders) == 0) {
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
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminInvoiceSendReminders']);
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

        // do not use api call to get system param to avoid invoking system module event hooks
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
            $params['reminder_intervals'] = $beforeDueReminderIntervals;
            $this->di['events_manager']->fire(['event' => 'onEventBeforeInvoiceIsDue', 'params' => $params]);
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
            $params['reminder_intervals'] = $afterDueReminderIntervals;
            $this->di['events_manager']->fire(['event' => 'onEventAfterInvoiceIsDue', 'params' => $params]);
        }
    }

    public function sendInvoiceReminder(Invoice $invoice): bool
    {
        // do not send accidental reminder for paid invoices
        if ($invoice->getStatus() == Invoice::STATUS_PAID) {
            return true;
        }

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminInvoiceSendReminder', 'params' => ['id' => $invoice->getId()]]);

        $invoice->setRemindedAt(new \DateTime());
        $this->di['em']->persist($invoice);
        $this->di['em']->flush();

        $this->di['events_manager']->fire(['event' => 'onAfterAdminInvoiceReminderSent', 'params' => ['id' => $invoice->getId()]]);

        $this->di['logger']->info('Invoice payment reminder sent');

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

        $gtw = $this->di['em']->getRepository(PayGateway::class)->find((int) $data['gateway_id']);
        if (!$gtw instanceof PayGateway) {
            throw new InformationException('Payment method not found', null, 813);
        }

        if (!$gtw->isEnabled()) {
            throw new \FOSSBilling\Exception\BaseException('Payment method not enabled', null, 814);
        }

        $subscribeService = $this->di['mod_service']('Invoice', 'Subscription');
        $payGatewayService = $this->di['mod_service']('Invoice', 'PayGateway');
        if ($subscribeService->isSubscribable($invoice->getId()) && $payGatewayService->canPerformRecurrentPayment($gtw) && $allowSubscribe) {
            $subscribe = true;
        }

        if (!$subscribe && !$payGatewayService->canPerformSinglePayment($gtw)) {
            throw new \FOSSBilling\Exception\BaseException('One-time payments are not enabled for the selected payment gateway', null, 815);
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
            'locale' => I18n::getActiveLocale($this->di['request'], true, $this->di['cookie_queue']),
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
            if (is_null($first_title) && \FOSSBilling\Utils\Arr::safeCount($proforma['lines']) == 1) {
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
            if ($data === null || empty(trim($data))) {
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
            if ($data === null || empty(trim($data))) {
                unset($sourceData[$label]);
            } else {
                ++$lines;
            }
        }

        return $sourceData;
    }

    private function getFooterInfo(array $company): array
    {
        $sourceData = [
            'company_name' => $company['name'],
            'bank_name' => $company['bank_name'],
            'account_number' => $company['account_number'],
            'bic' => $company['bic'],
            'display_bank_info' => $company['display_bank_info'],
            'company_vat' => $company['vat_number'],
            'company_number' => $company['number'],
            'www' => $company['www'],
            'email' => $company['email'],
            'phone' => $company['tel'],
            'signature' => $company['signature'],
            'address_1' => $company['address_1'],
            'address_2' => $company['address_2'],
            'address_3' => $company['address_3'],
        ];

        foreach ($sourceData as $label => $data) {
            if ($data === null || empty(trim($data))) {
                unset($sourceData[$label]);
            }
        }

        return $sourceData;
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
