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

use Box\Mod\Client\Entity\Client as ClientEntity;
use Box\Mod\Client\Entity\ClientBalance;
use Box\Mod\Currency\Entity\Currency;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\Subscription;
use Box\Mod\Invoice\Entity\Tax;
use Box\Mod\Invoice\Entity\Transaction;
use Box\Mod\Invoice\Repository\InvoiceItemRepository;
use Box\Mod\Invoice\Repository\InvoiceRepository;
use Box\Mod\Invoice\Repository\PayGatewayRepository;
use Box\Mod\Invoice\Repository\SubscriptionRepository;
use Box\Mod\Invoice\Repository\TaxRepository;
use Box\Mod\Invoice\Repository\TransactionRepository;
use Box\Mod\Order\Entity\Order as OrderEntity;
use Dompdf\Dompdf;
use Dompdf\Options;
use FOSSBilling\Environment;
use FOSSBilling\Http\ResponseFactory;
use FOSSBilling\i18n;
use FOSSBilling\InformationException;
use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\Tools;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Twig\Loader\FilesystemLoader;

class Service implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;
    private Filesystem $filesystem;
    private ?InvoiceRepository $invoiceRepository = null;
    private ?InvoiceItemRepository $invoiceItemRepository = null;
    private ?TransactionRepository $transactionRepository = null;
    private ?SubscriptionRepository $subscriptionRepository = null;
    private ?PayGatewayRepository $payGatewayRepository = null;
    private ?TaxRepository $taxRepository = null;

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

    public function getSearchQuery($data): array
    {
        $sql = 'SELECT p.*
            FROM invoice p
            LEFT JOIN invoice_item pi ON (p.id = pi.invoice_id)
            LEFT JOIN client cl ON (cl.id = p.client_id)
            WHERE 1 ';

        $params = [];

        $search = $data['search'] ?? null;
        $order_id = $data['order_id'] ?? null;
        $id = $data['id'] ?? null;
        $id_nr = $data['nr'] ?? null;
        $client_id = $data['client_id'] ?? null;
        $client = $data['client'] ?? null;
        $created_at = $data['created_at'] ?? null;
        $date_from = $data['date_from'] ?? null;
        $date_to = $data['date_to'] ?? null;
        $paid_at = $data['paid_at'] ?? null;
        $status = $data['status'] ?? null;
        $approved = $data['approved'] ?? null;
        $currency = $data['currency'] ?? null;

        if ($order_id) {
            $sql .= ' AND pi.type = :item_type AND pi.rel_id = :order_id';
            $params['item_type'] = InvoiceItem::TYPE_ORDER;
            $params['order_id'] = $order_id;
        }

        if ($id) {
            $sql .= ' AND p.id = :id';
            $params['id'] = $id;
        }

        if ($id_nr) {
            $sql .= ' AND (p.id = :id_nr OR p.nr = :id_nr)';
            $params['id_nr'] = $id_nr;
        }

        if ($approved) {
            $sql .= ' AND p.approved = :approved';
            $params['approved'] = (int) $approved;
        }

        if ($status) {
            $sql .= ' AND p.status = :status';
            $params['status'] = $status;
        }

        if ($currency) {
            $sql .= ' AND p.currency = :currency';
            $params['currency'] = $currency;
        }

        if ($client_id) {
            $sql .= ' AND p.client_id = :client_id';
            $params['client_id'] = $client_id;
        }

        if ($client) {
            $sql .= ' AND (cl.first_name LIKE :client_search OR cl.last_name LIKE :client_search OR cl.id = :client OR cl.email = :client)';
            $params['client_search'] = $client . '%';
            $params['client'] = $client;
        }

        if ($created_at) {
            $sql .= " AND DATE_FORMAT(p.created_at, '%Y-%m-%d') = :created_at";
            $params['created_at'] = date('Y-m-d', (int) strtotime((string) $created_at));
        }

        if ($date_from) {
            $sql .= ' AND UNIX_TIMESTAMP(p.created_at) >= :date_from';
            $params['date_from'] = strtotime((string) $date_from);
        }

        if ($date_to) {
            $sql .= ' AND UNIX_TIMESTAMP(p.created_at) <= :date_to';
            $params['date_to'] = strtotime((string) $date_to);
        }

        if ($paid_at) {
            $sql .= " AND DATE_FORMAT(p.paid_at, '%Y-%m-%d') = :paid_at";
            $params['paid_at'] = date('Y-m-d', (int) strtotime((string) $paid_at));
        }

        if ($search) {
            $sql .= ' AND (p.id = :search_numeric_id OR p.nr LIKE :search_like OR p.id LIKE :search OR pi.title LIKE :search_like)';
            $params['search_numeric_id'] = (int) preg_replace('/[^0-9]/', '', (string) $search);
            $params['search_like'] = '%' . $search . '%';
            $params['search'] = $search;
        }

        $sql .= ' GROUP BY p.id ORDER BY p.id DESC';

        return [$sql, $params];
    }

    public function toApiArray(Invoice $invoice, $deep = true, $identity = null, bool $includeClientBillingEmail = false): array
    {
        $this->ensureValidHash($invoice);

        $row = $this->buildRowFromEntity($invoice);
            $invoiceId = $invoice->getId();
            $items = $this->getInvoiceItemRepository()->findByInvoiceId($invoiceId);

        $lines = [];
        $total = 0;
        $taxable_subtotal = 0;

        foreach ($items as $item) {
            $itemType = $item instanceof InvoiceItem ? $item->getType() : $item->type;
            $itemRelId = $item instanceof InvoiceItem ? $item->getRelId() : $item->rel_id;
            $order_id = ($itemType == InvoiceItem::TYPE_ORDER) ? $itemRelId : null;

            $itemPrice = $item instanceof InvoiceItem ? ($item->getPrice() ?? 0) : ($item->price ?? 0);
            $itemQuantity = $item instanceof InvoiceItem ? ($item->getQuantity() ?? 1) : ($item->quantity ?? 1);
            $line_total = $itemPrice * $itemQuantity;
            $total += $line_total;

            $itemTaxed = $item instanceof InvoiceItem ? $item->isTaxed() : $item->taxed;
            if ($itemTaxed) {
                $taxable_subtotal += $line_total;
            }

            $line = [
                'id' => $item instanceof InvoiceItem ? $item->getId() : $item->id,
                'title' => $item instanceof InvoiceItem ? $item->getTitle() : $item->title,
                'period' => $item instanceof InvoiceItem ? $item->getPeriod() : $item->period,
                'quantity' => $itemQuantity,
                'unit' => $item instanceof InvoiceItem ? $item->getUnit() : $item->unit,
                'price' => $itemPrice,
                'tax' => 0,
                'taxed' => $itemTaxed,
                'charged' => $item instanceof InvoiceItem ? $item->isCharged() : $item->charged,
                'total' => $line_total,
                'order_id' => $order_id,
                'type' => $itemType,
                'rel_id' => $itemRelId,
                'task' => $item instanceof InvoiceItem ? $item->getTask() : $item->task,
                'status' => $item instanceof InvoiceItem ? $item->getStatus() : $item->status,
            ];
            $lines[] = $line;
        }

        $current_invoice_tax_rate = $row['taxrate'];
        if ($current_invoice_tax_rate > 0 && $taxable_subtotal != 0) {
            $tax = round($taxable_subtotal * $current_invoice_tax_rate / 100, 2);
        } else {
            $tax = 0;
        }

        $invoice_number_padding = $this->di['mod_service']('system')->getParamValue('invoice_number_padding');
        $invoice_number_padding = $invoice_number_padding !== null && $invoice_number_padding !== '' ? $invoice_number_padding : 5;

        $result = [];
        $result['id'] = $row['id'];
        $result['serie'] = $row['serie'];
        $result['nr'] = $row['nr'];
        $result['client_id'] = $row['client_id'];

        $nr = is_numeric($row['nr']) ? intval($row['nr']) : $result['id'];
        $result['serie_nr'] = $result['serie'] . sprintf('%0' . $invoice_number_padding . 's', $nr);

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
            'address_1' => !empty($row['seller_address_1']) ? $row['seller_address_1'] : ($c['address_1'] ?? ''),
            'address_2' => !empty($row['seller_address_2']) ? $row['seller_address_2'] : ($c['address_2'] ?? ''),
            'address_3' => !empty($row['seller_address_3']) ? $row['seller_address_3'] : ($c['address_3'] ?? ''),
            'phone' => !empty($row['seller_phone']) ? $row['seller_phone'] : ($c['tel'] ?? ''),
            'email' => !empty($row['seller_email']) ? $row['seller_email'] : ($c['email'] ?? ''),
            'account_number' => $c['account_number'] ?? null,
            'bank_name' => $c['bank_name'] ?? null,
            'bic' => $c['bic'] ?? null,
        ];

        /**
         * Removed if($identity instanceof Admin) {}
         * Generates error when this function is called by cron.
         */
        $client = isset($row['client_id']) ? $this->di['em']->getRepository(ClientEntity::class)->find($row['client_id']) : null;
        $clientService = $this->di['mod_service']('client');
        if ($client instanceof ClientEntity) {
            $result['client'] = $clientService->toApiArray($client);
            if ($includeClientBillingEmail) {
                $result['client']['billing_email'] = $client instanceof ClientEntity ? $client->getBillingEmail() : $client->billing_email;
            }
        } else {
            $result['client'] = null;
        }
        $result['reminded_at'] = $row['reminded_at'] ?? null;
        $result['approved'] = (bool) ($row['approved'] ?? false);
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
            $orders = $this->di['em']->getRepository(OrderEntity::class)->findBy(['id' => $orderIds]);

            $rawProductIds = array_map(static fn (OrderEntity $order): int => $order->getProductId() ?? 0, $orders);
            $nonEmptyProductIds = array_filter($rawProductIds);
            $productIds = array_unique($nonEmptyProductIds);

            // Ensure product IDs are safe integers before using in SQL
            $productIds = array_values(array_filter($productIds, static fn ($id): bool => $id > 0));

            $productService = $this->di['mod_service']('product');
            $productsById = !empty($productIds) ? $productService->getProductSnapshotMap($productIds) : [];

            foreach ($orders as $order) {
                $productId = $order->getProductId() ?? 0;
                $product = $productsById[$productId] ?? null;
                $orderData = [
                    'id' => $order->getId(),
                    'title' => $order->getTitle(),
                    'expires_at' => $order->getExpiresAt(),
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

    public static function onAfterAdminInvoicePaymentReceived(\Box_Event $event): bool
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
            $di['logger']->setChannel('email')->error('Failed to send email for invoice payment', ['exception' => $exc->getMessage()]);
        }

        return true;
    }

    public static function onAfterInvoiceCreate(\Box_Event $event): bool
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
            $di['logger']->setChannel('email')->error('Failed to send email for invoice creation', ['exception' => $exc->getMessage()]);
        }

        return true;
    }

    public static function onAfterAdminInvoiceApprove(\Box_Event $event): bool
    {
        $params = $event->getParameters();
        $di = $event->getDi();
        $service = $di['mod_service']('invoice');

        try {
            $invoiceModel = $di['em']->getRepository(Invoice::class)->find($params['id'] ?? 0);

            if (!$invoiceModel instanceof Invoice) {
                return true;
            }

            if (($params['total'] ?? 0) > 0
                && ($params['status'] ?? null) !== Invoice::STATUS_PAID
                && isset($params['client']['id'])
            ) {
                $service->sendInvoiceEmail($invoiceModel, $params, 'mod_invoice_created', (int) $params['client']['id']);
            }

            // Sending the created-email extends the hash lifetime so the
            // recipient has a fresh window to act on the link.
            $service->extendInvoiceHashLifetime($invoiceModel);
        } catch (\Exception $exc) {
            $di['logger']->setChannel('email')->error('Failed to send email for invoice approval', ['exception' => $exc->getMessage()]);
        }

        return true;
    }

    private function sendInvoiceEmail(Invoice $invoice, array $invoiceData, string $templateCode, ?int $clientId = null): void
    {
        $email = [
            'to_client' => $clientId ?? ($invoice instanceof Invoice ? $invoice->getClientId() : $invoice->client_id),
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

    public static function onAfterAdminInvoiceReminderSent(\Box_Event $event): void
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
            $di['logger']->setChannel('email')->error('Failed to send invoice reminder email', ['exception' => $exc->getMessage()]);
        }
    }

    public static function onEventBeforeInvoiceIsDue(\Box_Event $event): void
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
            $claimed = (bool) $di['em']->getConnection()->executeStatement(
                "UPDATE invoice SET reminded_at = NOW(), updated_at = NOW() WHERE id = :id AND status = 'unpaid' AND approved = 1 AND due_at > NOW() AND (reminded_at IS NULL OR DATE(reminded_at) < CURDATE())",
                [':id' => $params['id'] ?? 0]
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
                $di['em']->getConnection()->executeStatement('UPDATE invoice SET reminded_at = NULL WHERE id = :id', [':id' => $params['id'] ?? 0]);
            }
            $di['logger']->setChannel('email')->error('Failed to send invoice reminder email', ['id' => $params['id'] ?? null, 'exception' => $exc->getMessage()]);
        }
    }

    public static function onAfterAdminCronRun(\Box_Event $event): void
    {
        $di = $event->getDi();
        $systemService = $di['mod_service']('System');
        $remove_after_days = $systemService->getParamValue('remove_after_days');
        if (isset($remove_after_days) && $remove_after_days) {
            // removing old invoices
            $days = (int) $remove_after_days;
            $sql = 'DELETE FROM invoice WHERE status = :status AND DATEDIFF(NOW(), due_at) > :days';
            $di['em']->getConnection()->executeStatement($sql, [':days' => $days, ':status' => Invoice::STATUS_UNPAID]);
        }
    }

    public static function onEventAfterInvoiceIsDue(\Box_Event $event): void
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
            $claimed = (bool) $di['em']->getConnection()->executeStatement(
                "UPDATE invoice SET reminded_at = NOW(), updated_at = NOW() WHERE id = :id AND status = 'unpaid' AND approved = 1 AND ((due_at < NOW()) OR (ABS(DATEDIFF(due_at, NOW())) = 0)) AND (reminded_at IS NULL OR DATE(reminded_at) < CURDATE())",
                [':id' => $params['id'] ?? 0]
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
                throw new \FOSSBilling\Exception('Invoice client data is unavailable.');
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
                $di['em']->getConnection()->executeStatement('UPDATE invoice SET reminded_at = NULL WHERE id = :id', [':id' => $params['id'] ?? 0]);
            }
            $di['logger']->setChannel('email')->error('Failed to send overdue invoice email', ['id' => $params['id'] ?? null, 'exception' => $exc->getMessage()]);
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

    public function markAsPaid(Invoice $invoice, $charge = true, $execute = false): bool
    {
        $invoiceStatus = $invoice instanceof Invoice ? $invoice->getStatus() : $invoice->status;
        if ($invoiceStatus == Invoice::STATUS_PAID) {
            return true;
        }

        $invoiceId = $invoice instanceof Invoice ? $invoice->getId() : $invoice->id;
        $invoiceItems = $this->getInvoiceItemRepository()->findByInvoiceId((int) $invoiceId);
        $invoiceItemService = $this->di['mod_service']('Invoice', 'InvoiceItem');
        foreach ($invoiceItems as $item) {
            $invoiceItemService->markAsPaid($item, $charge);
        }

        $systemService = $this->di['mod_service']('system');

        $currencyService = $this->di['mod_service']('currency');
        /** @var \Box\Mod\Currency\Repository\CurrencyRepository $currencyRepository */
        $currencyRepository = $currencyService->getCurrencyRepository();

        $serie = $systemService->getParamValue('invoice_series_paid');
        $invoiceCurrency = $invoice instanceof Invoice ? $invoice->getCurrency() : $invoice->currency;

        if ($invoice instanceof Invoice) {
            $invoice->setSerie($serie);
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
        } else {
            $invoice->serie = $serie;
            $invoice->approved = true;

            $currencyRate = $currencyRepository->getRateByCode((string) $invoice->currency);
            if ($currencyRate === null) {
                throw new \FOSSBilling\Exception("Currency rate for code '{$invoice->currency}' is not configured.");
            }
            $invoice->currency_rate = $currencyRate;

            $invoice->status = Invoice::STATUS_PAID;
            $invoice->paid_at = date('Y-m-d H:i:s');
            $invoice->updated_at = date('Y-m-d H:i:s');
            $this->di['em']->persist($invoice);
        }

        $this->countIncome($invoice);
        $productService = $this->di['mod_service']('Product');
        $productService->commitReservedPromoRedemptionsForInvoice($invoice);

        $this->di['events_manager']->fire(['event' => 'onAfterAdminInvoicePaymentReceived', 'params' => ['id' => $invoiceId]]);

        if ($execute) {
            foreach ($invoiceItems as $item) {
                try {
                    $invoiceItemService->executeTask($item);
                } catch (\Exception $e) {
                    $this->di['logger']->warning($e->getMessage());
                }
            }
        }

        $this->di['logger']->info("Marked invoice {$invoiceId} as paid.");

        return true;
    }

    public function markAsPaidByAdmin(Invoice $invoice, array $data = []): bool
    {
        $invoiceStatus = $invoice instanceof Invoice ? $invoice->getStatus() : $invoice->status;
        if ($invoiceStatus === Invoice::STATUS_PAID) {
            return true;
        }

        $execute = Tools::normalizeBoolean($data['execute'] ?? false);
        $payGateway = $this->validateAdminMarkAsPaidRequest($data, $invoice);

        $invoiceId = $invoice instanceof Invoice ? $invoice->getId() : $invoice->id;
        $gatewayId = $invoice instanceof Invoice ? $invoice->getGatewayId() : $invoice->gateway_id;

        if ((int) $payGateway->getId() !== (int) $gatewayId) {
            if ($invoice instanceof Invoice) {
                $invoice->setGatewayId((int) $payGateway->getId());
                $this->di['em']->persist($invoice);
                $this->di['em']->flush();
            } else {
                $invoice->gateway_id = (int) $payGateway->getId();
                $invoice->updated_at = date('Y-m-d H:i:s');
                $this->di['em']->persist($invoice);
            }
        }

        if ($payGateway->getGateway() === 'Custom' && $payGateway->isEnabled()) {
            $transactionService = $this->di['mod_service']('Invoice', 'Transaction');
            $invoiceTotal = $this->getTotalWithTax($invoice);
            $invoiceCurrency = $invoice instanceof Invoice ? $invoice->getCurrency() : $invoice->currency;
            $newtx = $transactionService->create([
                'invoice_id' => $invoiceId,
                'gateway_id' => $gatewayId,
                'currency' => $invoiceCurrency,
                'status' => 'received',
                'source' => 'admin',
                'post' => [
                    'invoice_id' => $invoiceId,
                    'txn_id' => $transactionId ?? null,
                ],
                'txn_id' => $transactionId ?? null,
            ]);
            $transaction = $this->getTransactionRepository()->find($newtx) ?? throw new InformationException('Transaction not found');
            if ((int) $transaction->getInvoiceId() !== (int) $invoiceId) {
                throw new InformationException('Transaction ID is already associated with another invoice.');
            }

            $result = $this->markAsPaid($invoice, false, $execute);
            if ($result) {
                $transaction->setAmount((string) $invoiceTotal);
                $transaction->setCurrency($invoiceCurrency);
                $transaction->setStatus(Transaction::STATUS_PROCESSED);
                $gatewayTitle = $payGateway->getName() ?: $payGateway->getGateway();
                $transaction->setNote(sprintf('%s transaction No: %s', $gatewayTitle, $transactionId ?? ''));
                $this->di['em']->persist($transaction);
                $this->di['em']->flush();
            }

            return $result;
        }

        return $this->markAsPaid($invoice, false, $execute);
    }

    public function validateAdminMarkAsPaidRequest(array $data, Invoice|null $invoice = null): PayGateway
    {
        $gatewayId = isset($data['gateway_id']) && !empty($data['gateway_id']) ? (int) $data['gateway_id'] : null;
        if ($gatewayId === null && $invoice !== null) {
            $gatewayId = $invoice instanceof Invoice ? $invoice->getGatewayId() : ($invoice->gateway_id ?? 0);
            $gatewayId = (int) $gatewayId;
        }
        if ($gatewayId <= 0) {
            throw new InformationException('Payment gateway is required when marking an invoice as paid.');
        }

        $payGateway = $this->getPayGatewayRepository()->find($gatewayId) ?? throw new InformationException('Payment gateway not found');
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
     * @param OrderEntity $order the client order for which to find paid invoices
     *
     * @return array An array of paid invoices. Each element in the array represents an invoice record
     *               as returned by the database, typically as an associative array or an object.
     */
    public function findPaidInvoicesForOrder(OrderEntity $order): array
    {
        $bindings = [
            ':rel_id' => $order->getId(),
            ':status' => Invoice::STATUS_PAID,
        ];

        return $this->di['em']->getConnection()->fetchAllAssociative(
            'SELECT * FROM invoice WHERE id IN (SELECT invoice_id FROM invoice_item WHERE rel_id = :rel_id) AND status = :status',
            $bindings
        );
    }

    public function getNextInvoiceNumber()
    {
        $systemService = $this->di['mod_service']('system');
        $next_nr = $systemService->getParamValue('invoice_starting_number');

        if (empty($next_nr)) {
            // In theory this code should never need to be called, but is provided as a fallback
            $r = $this->di['em']->getConnection()->fetchAssociative(
                'SELECT nr FROM invoice WHERE nr IS NOT NULL ORDER BY id DESC LIMIT 1'
            );
            if ($r && is_numeric($r['nr'])) {
                $next_nr = intval($r['nr']) + 1;
            } else {
                throw new \FOSSBilling\Exception('Unable to determine the next invoice number');
            }
        }

        $systemService->setParamValue('invoice_starting_number', intval($next_nr) + 1);

        return $next_nr;
    }

    public function countIncome(Invoice $invoice): void
    {
        $table = $this->di['mod_service']('currency');

        $total = $this->getTotal($invoice);
        $currency = $invoice instanceof Invoice ? $invoice->getCurrency() : $invoice->currency;
        $refund = $invoice instanceof Invoice ? $invoice->getRefund() : $invoice->refund;

        if ($invoice instanceof Invoice) {
            $invoice->setBaseIncome($table->toBaseCurrency($currency, $total));
            if ($refund !== null) {
                $invoice->setBaseRefund($table->toBaseCurrency($currency, $refund));
            } else {
                $invoice->setBaseRefund(null);
            }
            $this->di['em']->persist($invoice);
            $this->di['em']->flush();
        } else {
            $invoice->base_income = $table->toBaseCurrency($invoice->currency, $this->getTotal($invoice));
            if ($invoice->refund !== null) {
                $invoice->base_refund = $table->toBaseCurrency($invoice->currency, $invoice->refund);
            } else {
                $invoice->base_refund = null;
            }
            $this->di['em']->persist($invoice);
        }
    }

    public function prepareInvoice(ClientEntity $client, array $data)
    {
        $clientEntity = $this->di['em']->getRepository(ClientEntity::class)->find($client->getId());

        if (!$client->getCurrency()) {
            $currencyService = $this->di['mod_service']('currency');
            /** @var \Box\Mod\Currency\Repository\CurrencyRepository $currencyRepository */
            $currencyRepository = $currencyService->getCurrencyRepository();
            $currency = $currencyRepository->findDefault();

            if (!$currency instanceof Currency) {
                throw new \FOSSBilling\Exception('Default currency not found');
            }

            $currencyCode = $currency->getCode();
            $clientEntity->setCurrency($currencyCode);
            $this->di['em']->persist($clientEntity);
            $this->di['em']->flush();
            if (isset($this->di['logger'])) {
                $this->di['logger']->info('Client #%s currency was not defined. Set default currency %s.', $clientEntity->getId(), $currencyCode);
            }
        }

        $model = new Invoice();
        $model->setClientId($clientEntity->getId());
        $model->setStatus(Invoice::STATUS_UNPAID);
        $model->setCurrency($clientEntity->getCurrency());
        $model->setApproved(false);

        $model->setGatewayId($data['gateway_id'] ?? $model->getGatewayId());
        $model->setText1($data['text_1'] ?? $model->getText1());
        $model->setText2($data['text_2'] ?? $model->getText2());
        $model->setCreatedAt(new \DateTime());
        $model->setUpdatedAt(new \DateTime());
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
        $clientId = $model instanceof Invoice ? $model->getClientId() : $model->client_id;
        $client = $this->di['em']->getRepository(ClientEntity::class)->find($clientId);
        $seller = $systemService->getCompany();

        $buyer = $clientService->toApiArray($client);

        if ($model instanceof Invoice) {
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

            $model->setSerie($systemService->getParamValue('invoice_series'));
            $model->setNr($this->getNextInvoiceNumber());
            $model->setHash(bin2hex(random_bytes(random_int(15, 30))));
            $model->setHashExpiresAt($this->computeHashExpiration() !== null ? new \DateTime($this->computeHashExpiration()) : null);

            $taxtitle = '';
            $taxService = $this->di['mod_service']('Invoice', 'Tax');
            $tax = $taxService->getTaxRateForClient($client, $taxtitle);
            $model->setTaxname($taxtitle);
            $model->setTaxrate($tax);

            $model->setNotes($this->di['mod_service']('system')->getParamValue('invoice_default_note'));

            $this->di['em']->persist($model);
            $this->di['em']->flush();
        } else {
            $model->seller_company = $seller['name'];
            $model->seller_company_vat = $seller['vat_number'];
            $model->seller_company_number = $seller['number'];
            $model->seller_address = trim("{$seller['address_1']} {$seller['address_2']} {$seller['address_3']}");
            $model->seller_phone = $seller['tel'];
            $model->seller_email = $seller['email'];

            $model->buyer_first_name = $buyer['first_name'];
            $model->buyer_last_name = $buyer['last_name'];
            $model->buyer_company = $buyer['company'];
            $model->buyer_company_vat = $buyer['company_vat'];
            $model->buyer_company_number = $buyer['company_number'];
            $model->buyer_address = "{$buyer['address_1']} {$buyer['address_2']}";
            $model->buyer_city = $buyer['city'];
            $model->buyer_state = $buyer['state'];
            $model->buyer_country = $buyer['country'];
            $model->buyer_phone = "{$buyer['phone_cc']} {$buyer['phone']}";
            $model->buyer_email = $buyer['email'];
            $model->buyer_zip = $buyer['postcode'];

            $invoice_due_days = $systemService->getParamValue('invoice_due_days');
            if (!is_numeric($invoice_due_days)) {
                $invoice_due_days = 1;
            }
            $due_time = strtotime("+{$invoice_due_days} day");
            $model->due_at = date('Y-m-d H:i:s', $due_time);

            $model->serie = $systemService->getParamValue('invoice_series');
            $model->nr = $this->getNextInvoiceNumber();
            $model->hash = bin2hex(random_bytes(random_int(15, 30)));
            $model->hash_expires_at = $this->computeHashExpiration();

            $taxtitle = '';
            $taxService = $this->di['mod_service']('Invoice', 'Tax');
            $tax = $taxService->getTaxRateForClient($client, $taxtitle);
            $model->taxname = $taxtitle;
            $model->taxrate = $tax;

            $model->notes = $this->di['mod_service']('system')->getParamValue('invoice_default_note');

            $this->di['em']->persist($model);
        }
    }

    public function approveInvoice(Invoice $invoice, array $data): bool
    {
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminInvoiceApprove', 'params' => $this->toApiArray($invoice)]);

        if ($invoice instanceof Invoice) {
            $invoice->setApproved(true);
            $this->di['em']->persist($invoice);
            $this->di['em']->flush();
        } else {
            $invoice->approved = 1;
            $invoice->updated_at = date('Y-m-d H:i:s');
            $this->di['em']->persist($invoice);
        }

        if (isset($data['use_credits']) && $data['use_credits']) {
            $this->tryPayWithCredits($invoice);
        }

        $this->di['events_manager']->fire(['event' => 'onAfterAdminInvoiceApprove', 'params' => $this->toApiArray($invoice, true, null, true)]);

        $invoiceId = $invoice instanceof Invoice ? $invoice->getId() : $invoice->id;
        $this->di['logger']->info("Approved invoice {$invoiceId}.");

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
                'Payment amount significantly exceeds the expected invoice total. Expected :expected, received :received.',
                [':expected' => number_format($expected, 2, '.', ''), ':received' => number_format($received, 2, '.', '')]
            );
        }
    }

    public function tryPayWithCredits(Invoice $invoice): bool
    {
        $invoiceApproved = $invoice instanceof Invoice ? $invoice->isApproved() : $invoice->approved;
        if (!$invoiceApproved) {
            return false;
        }
        $invoiceStatus = $invoice instanceof Invoice ? $invoice->getStatus() : $invoice->status;
        if ($invoiceStatus == Invoice::STATUS_PAID) {
            if (DEBUG) {
                $invoiceId = $invoice instanceof Invoice ? $invoice->getId() : $invoice->id;
                $this->di['logger']->setChannel('billing')->info("Skipping credit payment for already paid invoice {$invoiceId}.");
            }

            return false;
        }

        $clientId = $invoice instanceof Invoice ? $invoice->getClientId() : $invoice->client_id;
        $client = $this->di['em']->getRepository(ClientEntity::class)->find($clientId);
        $cbrepo = $this->di['mod_service']('Client', 'Balance');
        $balance = $cbrepo->getClientBalance($client);
        $required = $this->getTotalWithTax($invoice);
        $epsilon = 0.01;
        $difference = $balance - $required;

        if ($difference >= -$epsilon) {
            // @phpstan-ignore if.alwaysFalse
            if (DEBUG) {
                $invoiceId = $invoice instanceof Invoice ? $invoice->getId() : $invoice->id;
                $this->di['logger']->setChannel('billing')->info("Setting invoice {$invoiceId} as paid with credits for the amount of {$required}.");
            }

            if ($required <= $epsilon) {
                // Nothing was actually charged against the client's balance, so don't record a $0 credit transaction.
                $this->markAsPaid($invoice, false, true);

                return true;
            }

            $invoiceId = $invoice instanceof Invoice ? $invoice->getId() : $invoice->id;
            $balanceTransaction = new ClientBalance();
            $balanceTransaction->setClientId($client->getId());
            $balanceTransaction->setType('invoice');
            $balanceTransaction->setRelId((string) $invoiceId);

            $invoiceNr = $invoice instanceof Invoice ? $invoice->getNr() : $invoice->nr;
            $invoice_identifier = $invoiceNr ?: $invoiceId;
            $balanceTransaction->setDescription("Payment for invoice #{$invoice_identifier} using account credit.");

            $balanceTransaction->setAmount((string) (-$required));
            $this->di['em']->persist($balanceTransaction);
            $this->di['em']->flush();

            $this->markAsPaid($invoice, false, true);

            return true;
        }
        // @phpstan-ignore if.alwaysFalse (DEBUG is a runtime constant that may be true during debugging)
        if (DEBUG) {
            $invoiceId = $invoice instanceof Invoice ? $invoice->getId() : $invoice->id;
            $this->di['logger']->setChannel('billing')->info("Invoice {$invoiceId} could not be paid with credits. Money in balance {$balance} Required: {$required}.");
        }

        return false;
    }

    public function getTotalWithTax(Invoice $invoice): float
    {
        return $this->getTotal($invoice) + $this->getTax($invoice);
    }

    public function getTax(Invoice $invoice): float
    {
        $taxrate = $invoice instanceof Invoice ? $invoice->getTaxrate() : $invoice->taxrate;
        if ($taxrate <= 0) {
            return 0.0;
        }

        $invoiceId = $invoice instanceof Invoice ? $invoice->getId() : $invoice->id;
        $items = $this->getInvoiceItemRepository()->findByInvoiceId($invoiceId);

        if (empty($items)) {
            return 0.0;
        }

        $taxable_subtotal = 0.0;
        foreach ($items as $item) {
            $itemTaxed = $item instanceof InvoiceItem ? $item->isTaxed() : $item->taxed;
            if ($itemTaxed) {
                $itemPrice = $item instanceof InvoiceItem ? ($item->getPrice() ?? 0) : ($item->price ?? 0);
                $itemQuantity = $item instanceof InvoiceItem ? ($item->getQuantity() ?? 1) : ($item->quantity ?? 1);
                $taxable_subtotal += ($itemPrice * $itemQuantity);
            }
        }

        if ($taxable_subtotal == 0) {
            return 0.0;
        }

        return round($taxable_subtotal * $taxrate / 100, 2);
    }

    public function getTotal(Invoice $invoice): float
    {
        $total = 0;
        $invoiceId = $invoice instanceof Invoice ? $invoice->getId() : $invoice->id;
        $invoiceItems = $this->getInvoiceItemRepository()->findByInvoiceId($invoiceId) ?? [];
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

        $invoiceId = $invoice instanceof Invoice ? $invoice->getId() : $invoice->id;
        $invoiceClientId = $invoice instanceof Invoice ? $invoice->getClientId() : $invoice->client_id;
        $invoiceCurrency = $invoice instanceof Invoice ? $invoice->getCurrency() : $invoice->currency;
        $invoiceTaxname = $invoice instanceof Invoice ? $invoice->getTaxname() : $invoice->taxname;
        $invoiceTaxrate = $invoice instanceof Invoice ? $invoice->getTaxrate() : $invoice->taxrate;
        $invoiceSellerCompany = $invoice instanceof Invoice ? $invoice->getSellerCompany() : $invoice->seller_company;
        $invoiceSellerAddress = $invoice instanceof Invoice ? $invoice->getSellerAddress() : $invoice->seller_address;
        $invoiceSellerPhone = $invoice instanceof Invoice ? $invoice->getSellerPhone() : $invoice->seller_phone;
        $invoiceSellerEmail = $invoice instanceof Invoice ? $invoice->getSellerEmail() : $invoice->seller_email;
        $invoiceBuyerFirstName = $invoice instanceof Invoice ? $invoice->getBuyerFirstName() : $invoice->buyer_first_name;
        $invoiceBuyerLastName = $invoice instanceof Invoice ? $invoice->getBuyerLastName() : $invoice->buyer_last_name;
        $invoiceBuyerCompany = $invoice instanceof Invoice ? $invoice->getBuyerCompany() : $invoice->buyer_company;
        $invoiceBuyerAddress = $invoice instanceof Invoice ? $invoice->getBuyerAddress() : $invoice->buyer_address;
        $invoiceBuyerCity = $invoice instanceof Invoice ? $invoice->getBuyerCity() : $invoice->buyer_city;
        $invoiceBuyerState = $invoice instanceof Invoice ? $invoice->getBuyerState() : $invoice->buyer_state;
        $invoiceBuyerCountry = $invoice instanceof Invoice ? $invoice->getBuyerCountry() : $invoice->buyer_country;
        $invoiceBuyerPhone = $invoice instanceof Invoice ? $invoice->getBuyerPhone() : $invoice->buyer_phone;
        $invoiceBuyerEmail = $invoice instanceof Invoice ? $invoice->getBuyerEmail() : $invoice->buyer_email;
        $invoiceBuyerZip = $invoice instanceof Invoice ? $invoice->getBuyerZip() : $invoice->buyer_zip;

        switch ($logic) {
            case 'credit_note':
            case 'negative_invoice':
                $total = $this->getTotalWithTax($invoice);
                if ($total <= 0) {
                    throw new InformationException('Cannot refund invoice with negative amount');
                }

                $new = new Invoice();
                $new->setClientId($invoiceClientId);
                $new->setHash(bin2hex(random_bytes(random_int(15, 30))));
                $new->setHashExpiresAt($this->computeHashExpiration() !== null ? new \DateTime($this->computeHashExpiration()) : null);
                $new->setStatus(Invoice::STATUS_REFUNDED);
                $new->setCurrency($invoiceCurrency);
                $new->setApproved(true);
                $new->setTaxname($invoiceTaxname);
                $new->setTaxrate($invoiceTaxrate);

                $new->setSellerCompany($invoiceSellerCompany);
                $new->setSellerAddress($invoiceSellerAddress);
                $new->setSellerPhone($invoiceSellerPhone);
                $new->setSellerEmail($invoiceSellerEmail);

                $new->setBuyerFirstName($invoiceBuyerFirstName);
                $new->setBuyerLastName($invoiceBuyerLastName);
                $new->setBuyerCompany($invoiceBuyerCompany);
                $new->setBuyerAddress($invoiceBuyerAddress);
                $new->setBuyerCity($invoiceBuyerCity);
                $new->setBuyerState($invoiceBuyerState);
                $new->setBuyerCountry($invoiceBuyerCountry);
                $new->setBuyerPhone($invoiceBuyerPhone);
                $new->setBuyerEmail($invoiceBuyerEmail);
                $new->setBuyerZip($invoiceBuyerZip);

                $new->setPaidAt(new \DateTime());
                $new->setCreatedAt(new \DateTime());
                $new->setUpdatedAt(new \DateTime());
                $this->di['em']->persist($new);
                $this->di['em']->flush();

                $invoiceItems = $this->getInvoiceItemRepository()->findByInvoiceId($invoiceId);
                foreach ($invoiceItems as $item) {
                    $pi = new InvoiceItem();
                    $pi->setInvoiceId($new->getId());
                    $pi->setType($item instanceof InvoiceItem ? $item->getType() : $item->type);
                    $pi->setRelId((string) ($item instanceof InvoiceItem ? $item->getRelId() : $item->rel_id));
                    $pi->setTask($item instanceof InvoiceItem ? $item->getTask() : $item->task);
                    $pi->setStatus(InvoiceItem::STATUS_EXECUTED);
                    $pi->setTitle($item instanceof InvoiceItem ? $item->getTitle() : $item->title);
                    $pi->setPeriod($item instanceof InvoiceItem ? $item->getPeriod() : $item->period);
                    $pi->setQuantity($item instanceof InvoiceItem ? $item->getQuantity() : $item->quantity);
                    $pi->setUnit($item instanceof InvoiceItem ? $item->getUnit() : $item->unit);
                    $pi->setCharged(true);
                    $pi->setPrice($item instanceof InvoiceItem ? -($item->getPrice() ?? 0) : -$item->price);
                    $pi->setTaxed((bool) ($item instanceof InvoiceItem ? $item->isTaxed() : $item->taxed));
                    $this->di['em']->persist($pi);
                    $this->di['em']->flush();
                }

                $this->countIncome($new);

                $newId = $new->getId();
                $this->addNote($invoice, "Refund invoice #{$newId} generated.");
                $this->addNote($new, "Refund for #{$invoiceId} invoice.");
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

        $this->di['events_manager']->fire(['event' => 'onAfterAdminInvoiceRefund', 'params' => ['id' => $invoiceId]]);

        $this->di['logger']->info("Refunded invoice #{$invoiceId}.");

        return $result;
    }

    public function updateInvoice(Invoice $model, array $data): bool
    {
        $invoiceItemService = $this->di['mod_service']('Invoice', 'InvoiceItem');
        $previousStatus = $model instanceof Invoice ? $model->getStatus() : $model->status;

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminInvoiceUpdate', 'params' => $data]);

        $isEntity = $model instanceof Invoice;

        if (!empty($data['gateway_id'])) {
            $gateway = $this->getPayGatewayRepository()->find((int) $data['gateway_id']);
            if (!$gateway instanceof PayGateway) {
                throw new InformationException('Payment gateway not found');
            }
            if (!$gateway->isEnabled()) {
                throw new InformationException('Payment gateway is not enabled');
            }
            if ($isEntity) {
                $model->setGatewayId(intval($data['gateway_id']));
            } else {
                $model->gateway_id = intval($data['gateway_id']);
            }
        } elseif (array_key_exists('gateway_id', $data) && $data['gateway_id'] === null) {
            if ($isEntity) {
                $model->setGatewayId(null);
            } else {
                $model->gateway_id = null;
            }
        }

        $text1 = $data['text_1'] ?? ($isEntity ? (empty($model->getText1()) ? null : $model->getText1()) : (empty($model->getText1()) ? null : $model->getText1()));
        $text2 = $data['text_2'] ?? ($isEntity ? (empty($model->getText2()) ? null : $model->getText2()) : (empty($model->getText2()) ? null : $model->getText2()));
        $sellerCompany = $data['seller_company'] ?? ($isEntity ? (empty($model->getSellerCompany()) ? null : $model->getSellerCompany()) : (empty($model->getSellerCompany()) ? null : $model->getSellerCompany()));
        $sellerCompanyVat = $data['seller_company_vat'] ?? ($isEntity ? (empty($model->getSellerCompanyVat()) ? null : $model->getSellerCompanyVat()) : (empty($model->getSellerCompanyVat()) ? null : $model->getSellerCompanyVat()));
        $sellerCompanyNumber = $data['seller_company_number'] ?? ($isEntity ? (empty($model->getSellerCompanyNumber()) ? null : $model->getSellerCompanyNumber()) : (empty($model->getSellerCompanyNumber()) ? null : $model->getSellerCompanyNumber()));
        $sellerAddress = $data['seller_address'] ?? ($isEntity ? (empty($model->getSellerAddress()) ? null : $model->getSellerAddress()) : (empty($model->getSellerAddress()) ? null : $model->getSellerAddress()));
        $sellerPhone = $data['seller_phone'] ?? ($isEntity ? (empty($model->getSellerPhone()) ? null : $model->getSellerPhone()) : (empty($model->getSellerPhone()) ? null : $model->getSellerPhone()));
        $sellerEmail = $data['seller_email'] ?? ($isEntity ? (empty($model->getSellerEmail()) ? null : $model->getSellerEmail()) : (empty($model->getSellerEmail()) ? null : $model->getSellerEmail()));

        $buyerFirstName = $data['buyer_first_name'] ?? ($isEntity ? (empty($model->getBuyerFirstName()) ? null : $model->getBuyerFirstName()) : (empty($model->getBuyerFirstName()) ? null : $model->getBuyerFirstName()));
        $buyerLastName = $data['buyer_last_name'] ?? ($isEntity ? (empty($model->getBuyerLastName()) ? null : $model->getBuyerLastName()) : (empty($model->getBuyerLastName()) ? null : $model->getBuyerLastName()));
        $buyerCompany = $data['buyer_company'] ?? ($isEntity ? (empty($model->getBuyerCompany()) ? null : $model->getBuyerCompany()) : (empty($model->getBuyerCompany()) ? null : $model->getBuyerCompany()));
        $buyerCompanyVat = $data['buyer_company_vat'] ?? ($isEntity ? (empty($model->getBuyerCompanyVat()) ? null : $model->getBuyerCompanyVat()) : (empty($model->getBuyerCompanyVat()) ? null : $model->getBuyerCompanyVat()));
        $buyerCompanyNumber = $data['buyer_company_number'] ?? ($isEntity ? (empty($model->getBuyerCompanyNumber()) ? null : $model->getBuyerCompanyNumber()) : (empty($model->getBuyerCompanyNumber()) ? null : $model->getBuyerCompanyNumber()));
        $buyerAddress = $data['buyer_address'] ?? ($isEntity ? (empty($model->getBuyerAddress()) ? null : $model->getBuyerAddress()) : (empty($model->getBuyerAddress()) ? null : $model->getBuyerAddress()));
        $buyerCity = $data['buyer_city'] ?? ($isEntity ? (empty($model->getBuyerCity()) ? null : $model->getBuyerCity()) : (empty($model->getBuyerCity()) ? null : $model->getBuyerCity()));
        $buyerState = $data['buyer_state'] ?? ($isEntity ? (empty($model->getBuyerState()) ? null : $model->getBuyerState()) : (empty($model->getBuyerState()) ? null : $model->getBuyerState()));
        $buyerCountry = $data['buyer_country'] ?? ($isEntity ? (empty($model->getBuyerCountry()) ? null : $model->getBuyerCountry()) : (empty($model->getBuyerCountry()) ? null : $model->getBuyerCountry()));
        $buyerZip = $data['buyer_zip'] ?? ($isEntity ? (empty($model->getBuyerZip()) ? null : $model->getBuyerZip()) : (empty($model->getBuyerZip()) ? null : $model->getBuyerZip()));
        $buyerPhone = $data['buyer_phone'] ?? ($isEntity ? (empty($model->getBuyerPhone()) ? null : $model->getBuyerPhone()) : (empty($model->getBuyerPhone()) ? null : $model->getBuyerPhone()));
        $buyerEmail = $data['buyer_email'] ?? ($isEntity ? (empty($model->getBuyerEmail()) ? null : $model->getBuyerEmail()) : (empty($model->getBuyerEmail()) ? null : $model->getBuyerEmail()));

        if ($isEntity) {
            $model->setText1($text1);
            $model->setText2($text2);
            $model->setSellerCompany($sellerCompany);
            $model->setSellerCompanyVat($sellerCompanyVat);
            $model->setSellerCompanyNumber($sellerCompanyNumber);
            $model->setSellerAddress($sellerAddress);
            $model->setSellerPhone($sellerPhone);
            $model->setSellerEmail($sellerEmail);
            $model->setBuyerFirstName($buyerFirstName);
            $model->setBuyerLastName($buyerLastName);
            $model->setBuyerCompany($buyerCompany);
            $model->setBuyerCompanyVat($buyerCompanyVat);
            $model->setBuyerCompanyNumber($buyerCompanyNumber);
            $model->setBuyerAddress($buyerAddress);
            $model->setBuyerCity($buyerCity);
            $model->setBuyerState($buyerState);
            $model->setBuyerCountry($buyerCountry);
            $model->setBuyerZip($buyerZip);
            $model->setBuyerPhone($buyerPhone);
            $model->setBuyerEmail($buyerEmail);
        } else {
            $model->text_1 = $data['text_1'] ?? (empty($model->getText1()) ? null : $model->getText1());
            $model->text_2 = $data['text_2'] ?? (empty($model->getText2()) ? null : $model->getText2());
            $model->seller_company = $data['seller_company'] ?? (empty($model->getSellerCompany()) ? null : $model->getSellerCompany());
            $model->seller_company_vat = $data['seller_company_vat'] ?? (empty($model->getSellerCompanyVat()) ? null : $model->getSellerCompanyVat());
            $model->seller_company_number = $data['seller_company_number'] ?? (empty($model->getSellerCompanyNumber()) ? null : $model->getSellerCompanyNumber());
            $model->seller_address = $data['seller_address'] ?? (empty($model->getSellerAddress()) ? null : $model->getSellerAddress());
            $model->seller_phone = $data['seller_phone'] ?? (empty($model->getSellerPhone()) ? null : $model->getSellerPhone());
            $model->seller_email = $data['seller_email'] ?? (empty($model->getSellerEmail()) ? null : $model->getSellerEmail());
            $model->buyer_first_name = $data['buyer_first_name'] ?? (empty($model->getBuyerFirstName()) ? null : $model->getBuyerFirstName());
            $model->buyer_last_name = $data['buyer_last_name'] ?? (empty($model->getBuyerLastName()) ? null : $model->getBuyerLastName());
            $model->buyer_company = $data['buyer_company'] ?? (empty($model->getBuyerCompany()) ? null : $model->getBuyerCompany());
            $model->buyer_company_vat = $data['buyer_company_vat'] ?? (empty($model->getBuyerCompanyVat()) ? null : $model->getBuyerCompanyVat());
            $model->buyer_company_number = $data['buyer_company_number'] ?? (empty($model->getBuyerCompanyNumber()) ? null : $model->getBuyerCompanyNumber());
            $model->buyer_address = $data['buyer_address'] ?? (empty($model->getBuyerAddress()) ? null : $model->getBuyerAddress());
            $model->buyer_city = $data['buyer_city'] ?? (empty($model->getBuyerCity()) ? null : $model->getBuyerCity());
            $model->buyer_state = $data['buyer_state'] ?? (empty($model->getBuyerState()) ? null : $model->getBuyerState());
            $model->buyer_country = $data['buyer_country'] ?? (empty($model->getBuyerCountry()) ? null : $model->getBuyerCountry());
            $model->buyer_zip = $data['buyer_zip'] ?? (empty($model->getBuyerZip()) ? null : $model->getBuyerZip());
            $model->buyer_phone = $data['buyer_phone'] ?? (empty($model->getBuyerPhone()) ? null : $model->getBuyerPhone());
            $model->buyer_email = $data['buyer_email'] ?? (empty($model->getBuyerEmail()) ? null : $model->getBuyerEmail());
        }

        $paid_at = $data['paid_at'] ?? ($isEntity ? ($model->getPaidAt()?->format('Y-m-d H:i:s')) : $model->getPaidAt()?->format('Y-m-d H:i:s'));
        if (empty($paid_at)) {
            if ($isEntity) {
                $model->setPaidAt(null);
            } else {
                $model->paid_at = null;
            }
        } else {
            $date = new \DateTime(date('Y-m-d H:i:s', strtotime((string) $paid_at)));
            if ($isEntity) {
                $model->setPaidAt($date);
            } else {
                $model->paid_at = $date->format('Y-m-d H:i:s');
            }
        }

        $due_at = $data['due_at'] ?? ($isEntity ? ($model->getDueAt()?->format('Y-m-d H:i:s')) : $model->getDueAt()?->format('Y-m-d H:i:s'));
        if (empty($due_at)) {
            if ($isEntity) {
                $model->setDueAt(null);
            } else {
                $model->due_at = null;
            }
        } else {
            $date = new \DateTime(date('Y-m-d H:i:s', strtotime((string) $due_at)));
            if ($isEntity) {
                $model->setDueAt($date);
            } else {
                $model->due_at = $date->format('Y-m-d H:i:s');
            }
        }

        $serie = $data['serie'] ?? ($isEntity ? (empty($model->getSerie()) ? null : $model->getSerie()) : (empty($model->getSerie()) ? null : $model->getSerie()));
        $nr = $data['nr'] ?? ($isEntity ? (empty($model->getNr()) ? null : $model->getNr()) : (empty($model->getNr()) ? null : $model->getNr()));
        $status = $data['status'] ?? ($isEntity ? (empty($model->getStatus()) ? null : $model->getStatus()) : (empty($model->getStatus()) ? null : $model->getStatus()));
        $taxrate = $data['taxrate'] ?? ($isEntity ? (empty($model->getTaxrate()) ? null : $model->getTaxrate()) : (empty($model->getTaxrate()) ? null : $model->getTaxrate()));
        $taxname = $data['taxname'] ?? ($isEntity ? (empty($model->getTaxname()) ? null : $model->getTaxname()) : (empty($model->getTaxname()) ? null : $model->getTaxname()));
        $approved = (int) ($data['approved'] ?? ($isEntity ? ($model->isApproved() === false && $model->isApproved() !== null ? null : (int) $model->isApproved()) : (empty($model->isApproved()) ? null : $model->isApproved())));
        $notes = $data['notes'] ?? ($isEntity ? (empty($model->getNotes()) ? null : $model->getNotes()) : (empty($model->getNotes()) ? null : $model->getNotes()));

        if ($isEntity) {
            $model->setSerie($serie);
            $model->setNr($nr);
            $model->setStatus($status);
            $model->setTaxrate($taxrate);
            $model->setTaxname($taxname);
            $model->setApproved((bool) $approved);
            $model->setNotes($notes);
        } else {
            $model->serie = $data['serie'] ?? (empty($model->getSerie()) ? null : $model->getSerie());
            $model->nr = $data['nr'] ?? (empty($model->getNr()) ? null : $model->getNr());
            $model->status = $data['status'] ?? (empty($model->getStatus()) ? null : $model->getStatus());
            $model->taxrate = $data['taxrate'] ?? (empty($model->getTaxrate()) ? null : $model->getTaxrate());
            $model->taxname = $data['taxname'] ?? (empty($model->getTaxname()) ? null : $model->getTaxname());
            $model->approved = (int) ($data['approved'] ?? (empty($model->isApproved()) ? null : $model->isApproved()));
            $model->notes = $data['notes'] ?? (empty($model->getNotes()) ? null : $model->getNotes());
        }

        $created_at = $data['created_at'] ?? '';
        if (!empty($created_at)) {
            $date = new \DateTime(date('Y-m-d H:i:s', strtotime((string) $created_at)));
            if ($isEntity) {
                $model->setCreatedAt($date);
            } else {
                $model->created_at = $date->format('Y-m-d H:i:s');
            }
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

        if ($isEntity) {
            $this->di['em']->persist($model);
            $this->di['em']->flush();
        } else {
            $model->updated_at = date('Y-m-d H:i:s');
            $this->di['em']->persist($model);
        }

        if ($previousStatus === Invoice::STATUS_UNPAID && $status === Invoice::STATUS_CANCELED) {
            $productService = $this->di['mod_service']('Product');
            $productService->releaseReservedPromoRedemptionsForInvoice($model, 'invoice_canceled');
        }

        $this->di['events_manager']->fire(['event' => 'onAfterAdminInvoiceUpdate', 'params' => $this->toApiArray($model)]);

        $invoiceId = $isEntity ? $model->getId() : $model->getId();
        $this->di['logger']->info("Updated invoice {$invoiceId}.");

        return true;
    }

    public function rmInvoice(Invoice $model): bool
    {
        $productService = $this->di['mod_service']('Product');
        $productService->releaseReservedPromoRedemptionsForInvoice($model, 'invoice_deleted');

        $invoiceId = $model instanceof Invoice ? $model->getId() : $model->id;

        // remove related invoice from orders
        $sql = '
            UPDATE client_order
            SET unpaid_invoice_id = NULL
            WHERE unpaid_invoice_id = :id';
        $this->di['em']->getConnection()->executeStatement($sql, ['id' => $invoiceId]);

        $invoiceItems = $this->getInvoiceItemRepository()->findByInvoiceId($invoiceId);
        foreach ($invoiceItems as $item) {
            $this->di['em']->remove($item);
            $this->di['em']->flush();
        }

        if ($model instanceof Invoice) {
            $this->di['em']->remove($model);
            $this->di['em']->flush();
        } else {
            $this->di['em']->remove($model);
        }

        return true;
    }

    public function deleteInvoiceByAdmin(Invoice $model): bool
    {
        $invoiceId = $model instanceof Invoice ? $model->getId() : $model->id;
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminInvoiceDelete', 'params' => ['id' => $invoiceId]]);

        $id = $invoiceId;
        $this->rmInvoice($model);

        $this->di['events_manager']->fire(['event' => 'onAfterAdminInvoiceDelete', 'params' => ['id' => $id]]);

        $this->di['logger']->info('Removed invoice #%s', $id);

        return true;
    }

    public function renewInvoice(OrderEntity $model, array $data)
    {
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminGenerateRenewalInvoice', 'params' => ['order_id' => $model->getId()]]);

        $due_days = isset($data['due_days']) ? (int) $data['due_days'] : null;
        $invoice = $this->generateForOrder($model, $due_days);
        $invoiceId = $invoice instanceof Invoice ? $invoice->getId() : $invoice->id;
        $this->approveInvoice($invoice, ['id' => $invoiceId, 'use_credits' => true]);

        $this->di['events_manager']->fire(['event' => 'onAfterAdminGenerateRenewalInvoice', 'params' => ['order_id' => $model->getId(), 'id' => $invoiceId]]);

        $this->di['logger']->info("Generated renewal invoice #{$invoiceId}.");

        return $invoiceId;
    }

    public function doBatchPayWithCredits(array $data): bool
    {
        $unpaid = $this->findAllUnpaid($data);
        foreach ($unpaid as $proforma) {
            try {
                $model = $this->getInvoiceRepository()->find((int) ($proforma['id'] ?? 0)) ?? throw new InformationException('Invoice not found');
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
     *
     * @return Invoice
     */
    public function generateForOrder(OrderEntity $order, $due_days = null)
    {
        // check if we do have invoice prepared already
        $unpaidInvoiceId = $order instanceof OrderEntity ? $order->getUnpaidInvoiceId() : $order->unpaid_invoice_id;
        if ($unpaidInvoiceId !== null) {
            $p = $this->getInvoiceRepository()->find($unpaidInvoiceId);
            if ($p instanceof Invoice && $p->getStatus() === Invoice::STATUS_UNPAID) {
                return $p;
            }

            if ($p instanceof Invoice && $p->getStatus() === Invoice::STATUS_UNPAID) {
                return $p;
            }

            $orderService = $this->di['mod_service']('Order');
            $orderService->unsetUnpaidInvoice($order);
        }

        $orderPrice = $order instanceof OrderEntity ? $order->getPrice() : $order->price;
        $orderQuantity = $order instanceof OrderEntity ? $order->getQuantity() : $order->quantity;
        $orderStatus = $order instanceof OrderEntity ? $order->getStatus() : $order->status;
        $orderProductId = $order instanceof OrderEntity ? $order->getProductId() : $order->product_id;
        $orderConfig = $order instanceof OrderEntity ? $order->getConfig() : $order->config;
        $orderCurrency = $order instanceof OrderEntity ? $order->getCurrency() : $order->currency;
        $orderClientId = $order instanceof OrderEntity ? $order->getClientId() : $order->client_id;
        $orderExpiresAt = $order instanceof OrderEntity ? $order->getExpiresAt() : $order->expires_at;

        $price = $orderPrice;
        $line = [
            'price' => $orderPrice,
            'quantity' => $orderQuantity,
        ];

        // Domain renewal pricing is resolved from the registrar/config rather than
        // the order, since it legitimately changes between registration and renewal.
        // Other products keep the order's own price so admin-edited prices are respected.
        if (in_array($orderStatus, [
            OrderEntity::STATUS_ACTIVE,
            OrderEntity::STATUS_FAILED_RENEW,
            OrderEntity::STATUS_SUSPENDED,
        ], true)) {
            $productService = $this->di['mod_service']('Product');
            $product = $productService->findProductById((int) $orderProductId);

            if ($productService instanceof \Box\Mod\Product\Service && $product->getType() === \Box\Mod\Product\Service::DOMAIN) {
                $config = json_decode($orderConfig ?? '', true) ?? [];
                $currencyService = $this->di['mod_service']('Currency');
                $currencyRepository = $currencyService->getCurrencyRepository();
                $rate = $currencyRepository->getRateByCode($orderCurrency);
                if ($rate === null) {
                    throw new \FOSSBilling\Exception("Currency rate for '{$orderCurrency}' is not configured");
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

        $client = $this->di['em']->getRepository(ClientEntity::class)->find((int) $orderClientId)
            ?? throw new InformationException('Client not found');

        // generate proforma after validating the resolved renewal amount
        $proforma = new Invoice();
        $proforma->setClientId($client->getId());
        $proforma->setStatus(Invoice::STATUS_UNPAID);
        $proforma->setCurrency($orderCurrency);
        $proforma->setApproved(false);
        $proforma->setCreatedAt(new \DateTime());
        $proforma->setUpdatedAt(new \DateTime());
        $this->di['em']->persist($proforma);
        $this->di['em']->flush();

        $this->setInvoiceDefaults($proforma);

        $invoiceItemService = $this->di['mod_service']('Invoice', 'InvoiceItem');
        $invoiceItemService->generateFromOrder($proforma, $order, InvoiceItem::TASK_RENEW, $price, $line);

        // invoice due date
        if ($due_days > 0) {
            $proforma->setDueAt(new \DateTime(date('Y-m-d H:i:s', strtotime('+' . $due_days . ' days'))));
            $this->di['em']->persist($proforma);
            $this->di['em']->flush();
        } elseif ($orderExpiresAt) {
            $proforma->setDueAt($orderExpiresAt instanceof \DateTime ? new \DateTime($orderExpiresAt->format('Y-m-d H:i:s')) : new \DateTime($orderExpiresAt));
            $this->di['em']->persist($proforma);
            $this->di['em']->flush();
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

        foreach ($orders as $order) {
            try {
                $model = $this->di['em']->getRepository(OrderEntity::class)->find($order['id'] ?? null);
                if (!$model instanceof OrderEntity && !$model instanceof OrderEntity) {
                    continue;
                }
                $invoice = $this->generateForOrder($model);
                $invoiceId = $invoice instanceof Invoice ? $invoice->getId() : $invoice->id;
                $this->approveInvoice($invoice, ['id' => $invoiceId, 'use_credits' => true]);
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
        foreach ($invoiceItems as $item) {
            try {
                $model = $this->getInvoiceItemRepository()->find((int) ($item['id'] ?? 0)) ?? throw new \FOSSBilling\Exception('InvoiceItem not found');
                $invoiceItemService->executeTask($model);
            } catch (\Exception $e) {
                $this->di['logger']->error($e->getMessage());
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
            // error_log('Already executed today.');
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

        $beforeDueList = $this->di['em']->getConnection()->fetchAllAssociative("SELECT id, DATEDIFF(due_at, NOW()) as days_left FROM invoice WHERE status = 'unpaid' AND approved = 1 AND due_at > NOW()");
        foreach ($beforeDueList as $params) {
            $params['reminder_intervals'] = $beforeDueReminderIntervals;
            $this->di['events_manager']->fire(['event' => 'onEventBeforeInvoiceIsDue', 'params' => $params]);
        }

        $afterDueList = $this->di['em']->getConnection()->fetchAllAssociative("SELECT id, ABS(DATEDIFF(due_at, NOW())) as days_passed FROM invoice WHERE status = 'unpaid' AND approved = 1 AND ((due_at < NOW()) OR (ABS(DATEDIFF(due_at, NOW())) = 0))");
        foreach ($afterDueList as $params) {
            $params['reminder_intervals'] = $afterDueReminderIntervals;
            $this->di['events_manager']->fire(['event' => 'onEventAfterInvoiceIsDue', 'params' => $params]);
        }
    }

    public function sendInvoiceReminder(Invoice $invoice): bool
    {
        $invoiceStatus = $invoice instanceof Invoice ? $invoice->getStatus() : $invoice->status;
        // do not send accidental reminder for paid invoices
        if ($invoiceStatus == Invoice::STATUS_PAID) {
            return true;
        }

        $invoiceId = $invoice instanceof Invoice ? $invoice->getId() : $invoice->id;
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminInvoiceSendReminder', 'params' => ['id' => $invoiceId]]);

        if ($invoice instanceof Invoice) {
            $invoice->setRemindedAt(new \DateTime());
            $this->di['em']->persist($invoice);
            $this->di['em']->flush();
        } else {
            $invoice->reminded_at = date('Y-m-d H:i:s');
            $invoice->updated_at = date('Y-m-d H:i:s');
            $this->di['em']->persist($invoice);
        }

        $this->di['events_manager']->fire(['event' => 'onAfterAdminInvoiceReminderSent', 'params' => ['id' => $invoiceId]]);

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

    public function generateFundsInvoice(ClientEntity $client, $amount)
    {
        if (!$client->getCurrency()) {
            throw new InformationException('You must have at least one active order before you can add funds so you cannot proceed at the current time!');
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
        $proforma->setClientId($client->getId());
        $proforma->setStatus(Invoice::STATUS_UNPAID);
        $proforma->setCurrency($client->getCurrency());
        $proforma->setApproved($this->_isAutoApproved());
        $proforma->setCreatedAt(new \DateTime());
        $proforma->setUpdatedAt(new \DateTime());
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

        $gtw = $this->di['em']->getRepository(PayGateway::class)->find($data['gateway_id']);
        if (!$gtw instanceof PayGateway && !$gtw instanceof PayGateway) {
            throw new InformationException('Payment method not found', null, 813);
        }

        $gtwEnabled = $gtw instanceof PayGateway ? $gtw->isEnabled() : $gtw->enabled;
        if (!$gtwEnabled) {
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
            $html = $adapter->getHtml($this->di['api_system'], $invoice->getId(), $subscribe);

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
        $this->di['logger']->info('Went to pay for invoice #%s via %s', $invoice->getId(), $gtw instanceof PayGateway ? $gtw->getGateway() : $gtw->gateway);

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
            $this->di['logger']->setChannel('email')->error('Failed to generate PDF invoice attachment: ' . $e->getMessage());

            return null;
        }
    }

    protected function renderInvoicePdfContent(Invoice $invoiceModel, array $invoice): string
    {
        $systemService = $this->di['mod_service']('system');
        $c = $systemService->getCompany();
        $document_format = $systemService->getParamValue('invoice_document_format', 'Letter');

        if ($invoiceModel instanceof Invoice) {
            $currencyCode = $invoiceModel->getCurrency();
        } elseif (isset($invoiceModel->currency)) {
            $currencyCode = $invoiceModel->currency;
        } else {
            $clientId = $invoiceModel instanceof Invoice ? $invoiceModel->getClientId() : $invoiceModel->client_id;
            $client = $this->di['em']->getRepository(ClientEntity::class)->find($clientId)
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
        if ($model instanceof Invoice) {
            $n = $model->getNotes();
            $model->setNotes($n . date('Y-m-d H:i:s') . ': ' . $note . '       ' . PHP_EOL);
            $this->di['em']->persist($model);
            $this->di['em']->flush();
        } else {
            $n = $model->notes;
            $model->notes = $n . date('Y-m-d H:i:s') . ': ' . $note . '       ' . PHP_EOL;
            $model->updated_at = date('Y-m-d H:i:s');
            $this->di['em']->persist($model);
        }

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
                    AND m.approved = 1
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

    public function findAllPaid()
    {
        return $this->getInvoiceRepository()->findPaid();
    }

    public function getUnpaidInvoicesLateFor($days_after_issue = 2)
    {
        return $this->di['em']->getConnection()->fetchAllAssociative(
            'SELECT * FROM invoice WHERE status = :status AND approved = 1 AND reminded_at IS NULL AND DATEDIFF(NOW(), created_at) > :days',
            ['status' => Invoice::STATUS_UNPAID, 'days' => $days_after_issue]
        );
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

        $invoiceId = $invoice instanceof Invoice ? $invoice->getId() : $invoice->id;
        $mpi = new \Payment_Invoice();
        $mpi->setId($invoiceId);
        $mpi->setNumber($proforma['nr']);
        $mpi->setBuyer($buyer);
        $mpi->setCurrency($proforma['currency']);
        $mpi->setTitle($title);
        $mpi->setItems($items);

        $subscribeService = $this->di['mod_service']('Invoice', 'Subscription');
        // can subscribe only if proforma has one item with defined period
        if ($subscribe && $subscribeService->isSubscribable($invoiceId)) {
            if ($invoice instanceof Invoice) {
                $items = $this->getInvoiceItemRepository()->findByInvoiceId($invoiceId);
                $subitem = $items[0] ?? null;
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
            } else {
                $subitem = $invoice->InvoiceItem->getFirst();
                $period = $this->di['period']($subitem->period);

                $bs = new \Payment_Invoice_Subscription();
                $bs->setId($proforma['id']);
                $bs->setAmount($mpi->getTotalWithTax());
                $bs->setCycle($period->getQty());
                $bs->setUnit($period->getUnit());

                $mpi->setSubscription($bs);
                $mpi->setTitle('Subscription for ' . $subitem->title);
            }
        }

        return $mpi;
    }

    public function getBuyer(Invoice $invoice): array
    {
        return [
            'first_name' => $invoice instanceof Invoice ? $invoice->getBuyerFirstName() : $invoice->buyer_first_name,
            'last_name' => $invoice instanceof Invoice ? $invoice->getBuyerLastName() : $invoice->buyer_last_name,
            'company' => $invoice instanceof Invoice ? $invoice->getBuyerCompany() : $invoice->buyer_company,
            'address' => $invoice instanceof Invoice ? $invoice->getBuyerAddress() : $invoice->buyer_address,
            'city' => $invoice instanceof Invoice ? $invoice->getBuyerCity() : $invoice->buyer_city,
            'state' => $invoice instanceof Invoice ? $invoice->getBuyerState() : $invoice->buyer_state,
            'country' => $invoice instanceof Invoice ? $invoice->getBuyerCountry() : $invoice->buyer_country,
            'phone' => $invoice instanceof Invoice ? $invoice->getBuyerPhone() : $invoice->buyer_phone,
            'phone_cc' => '',
            'email' => $invoice instanceof Invoice ? $invoice->getBuyerEmail() : $invoice->buyer_email,
            'zip' => $invoice instanceof Invoice ? $invoice->getBuyerZip() : $invoice->buyer_zip,
        ];
    }

    public function rmByClient(ClientEntity $client): void
    {
        $invoices = $this->getInvoiceRepository()->findByClientId($client->getId());
        foreach ($invoices as $invoice) {
            if ($invoice instanceof Invoice) {
                $this->rmInvoice($invoice);
            }
        }
    }

    public function isInvoiceTypeDeposit(Invoice $invoice): bool
    {
        $invoiceId = $invoice instanceof Invoice ? $invoice->getId() : $invoice->id;
        if ($invoiceId === null) {
            return false;
        }
        $invoiceItems = $this->getInvoiceItemRepository()->findByInvoiceId($invoiceId);

        foreach ($invoiceItems as $item) {
            $itemType = $item instanceof InvoiceItem ? $item->getType() : $item->type;
            if ($itemType == InvoiceItem::TYPE_DEPOSIT) {
                return true;
            }
        }

        return false;
    }

    public function exportCSV(array $headers): Response
    {
        if (!$headers) {
            $headers = ['id', 'client_id', 'nr', 'currency', 'credit', 'base_income', 'base_refund', 'refund', 'notes', 'status', 'buyer_first_name', 'buyer_last_name', 'buyer_company', 'buyer_company_vat', 'buyer_company_number', 'buyer_address', 'buyer_city', 'buyer_state', 'buyer_country', 'buyer_zip', 'buyer_phone', 'buyer_phone_cc', 'buyer_email', 'approved', 'taxname', 'taxrate', 'due_at', 'reminded_at', 'paid_at'];
        }

        return $this->di['csv_response_factory']->create('invoice', 'invoices.csv', $headers);
    }

    public function checkInvoiceAuth(Invoice $invoice, InvoiceOperation $operation = InvoiceOperation::READ): void
    {
        if ($this->di['auth']->isAdminLoggedIn() || Environment::isCLI()) {
            return;
        }

        $invoiceClientId = $invoice instanceof Invoice ? $invoice->getClientId() : $invoice->client_id;
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
    private function computeHashExpiration(): ?string
    {
        $days = (int) $this->di['mod_service']('system')->getParamValue('invoice_hash_lifetime_days', '90');
        if ($days <= 0) {
            return null;
        }

        return date('Y-m-d H:i:s', strtotime("+{$days} days"));
    }

    /**
     * Re-stamps hash_expires_at on an existing invoice using the current
     * invoice_hash_lifetime_days setting. Also self-heals invoices whose
     * hash is empty or in a legacy format by generating a fresh modern
     * hash. Called when an admin re-sends an invoice or payment reminder.
     */
    public function extendInvoiceHashLifetime(Invoice $invoice): void
    {
        $hash = $invoice instanceof Invoice ? $invoice->getHash() : $invoice->hash;
        $isModern = is_string($hash) && preg_match('/^[a-f0-9]{30,60}$/', $hash) === 1;
        $expiration = $this->computeHashExpiration();

        if ($invoice instanceof Invoice) {
            if (!$isModern) {
                $invoice->setHash(bin2hex(random_bytes(random_int(15, 30))));
            }
            $invoice->setHashExpiresAt($expiration !== null ? new \DateTime($expiration) : null);
            $this->di['em']->persist($invoice);
            $this->di['em']->flush();
        } else {
            if (!$isModern) {
                $invoice->hash = bin2hex(random_bytes(random_int(15, 30)));
            }
            $invoice->hash_expires_at = $expiration;
            $this->di['em']->persist($invoice);
        }
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

        $expiration = $this->computeHashExpiration();

        $invoice->setHash(bin2hex(random_bytes(random_int(15, 30))));
        $invoice->setHashExpiresAt($expiration !== null ? new \DateTime($expiration) : null);
        $this->di['em']->persist($invoice);
        $this->di['em']->flush();
    }

    private function isHashExpired(Invoice $invoice): bool
    {
        $expires = $invoice->getHashExpiresAt();
        if ($expires instanceof \DateTime) {
            return $expires->getTimestamp() < time();
        }
        if (empty($expires)) {
            return false;
        }

        return strtotime((string) $expires) < time();
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
        $item = $this->getInvoiceItemRepository()->findOneBy([
            'invoiceId' => $invoiceId,
            'type' => InvoiceItem::TYPE_ORDER,
        ]);

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
    public function generateRenewalInvoiceForSubscriptionPayment(string $subscriptionSid, int $clientId): Invoice|null
    {
        /** @var SubscriptionRepository $subscriptionRepo */
        $subscriptionRepo = $this->getSubscriptionRepository();
        $orderService = $this->di['mod_service']('Order');

        try {
            $subscription = $subscriptionRepo->findBySId($subscriptionSid);
            if (!$subscription instanceof Subscription) {
                return null;
            }

            if ($subscription->getRelType() !== 'invoice') {
                return null;
            }

            $originalOrderId = $this->getOrderIdFromInvoice((int) $subscription->getRelId());
            if ($originalOrderId === null) {
                return null;
            }

            $originalOrder = $this->di['em']->getRepository(OrderEntity::class)->find($originalOrderId);
            if (!$originalOrder instanceof OrderEntity) {
                return null;
            }

            // Use the original order directly. A previous approach searched for
            // any active order with the same product_id, but that is broken for
            // products like domain registrations where multiple orders share
            // the same product — it would find an unrelated order and generate
            // a renewal invoice for the wrong service.
            $originalOrderStatus = $originalOrder instanceof OrderEntity ? $originalOrder->getStatus() : $originalOrder->status;
            if ($originalOrderStatus !== OrderEntity::STATUS_ACTIVE) {
                return null;
            }

            $invoice = $this->generateForOrder($originalOrder);
            $invoiceId = $invoice->getId();
            $this->approveInvoice($invoice, ['use_credits' => false]);

            $this->di['logger']->info("Generated renewal invoice #{$invoiceId} for subscription payment (SID: {$subscriptionSid}).");

            return $invoice;
        } catch (\Exception $e) {
            $this->di['logger']->warning('Failed to generate renewal invoice for subscription payment: ' . $e->getMessage());

            return null;
        }
    }

    // End of PDF related functions

    private function getInvoiceRepository(): InvoiceRepository
    {
        if ($this->invoiceRepository === null) {
            $this->invoiceRepository = $this->di['em']->getRepository(Invoice::class);
        }

        return $this->invoiceRepository;
    }

    private function getInvoiceItemRepository(): InvoiceItemRepository
    {
        if ($this->invoiceItemRepository === null) {
            $this->invoiceItemRepository = $this->di['em']->getRepository(InvoiceItem::class);
        }

        return $this->invoiceItemRepository;
    }

    private function getTransactionRepository(): TransactionRepository
    {
        if ($this->transactionRepository === null) {
            $this->transactionRepository = $this->di['em']->getRepository(Transaction::class);
        }

        return $this->transactionRepository;
    }

    private function getSubscriptionRepository(): SubscriptionRepository
    {
        if ($this->subscriptionRepository === null) {
            $this->subscriptionRepository = $this->di['em']->getRepository(Subscription::class);
        }

        return $this->subscriptionRepository;
    }

    private function getPayGatewayRepository(): PayGatewayRepository
    {
        if ($this->payGatewayRepository === null) {
            $this->payGatewayRepository = $this->di['em']->getRepository(PayGateway::class);
        }

        return $this->payGatewayRepository;
    }

    private function getTaxRepository(): TaxRepository
    {
        if ($this->taxRepository === null) {
            $this->taxRepository = $this->di['em']->getRepository(Tax::class);
        }

        return $this->taxRepository;
    }

    private function buildRowFromEntity(Invoice $invoice): array
    {
        return [
            'id' => $invoice->getId(),
            'serie' => $invoice->getSerie(),
            'nr' => $invoice->getNr(),
            'client_id' => $invoice->getClientId(),
            'hash' => $invoice->getHash(),
            'hash_expires_at' => $invoice->getHashExpiresAt()?->format('Y-m-d H:i:s'),
            'gateway_id' => $invoice->getGatewayId(),
            'taxname' => $invoice->getTaxname(),
            'taxrate' => $invoice->getTaxrate(),
            'currency' => $invoice->getCurrency(),
            'currency_rate' => $invoice->getCurrencyRate() ?? 1,
            'status' => $invoice->getStatus(),
            'notes' => $invoice->getNotes(),
            'text_1' => $invoice->getText1(),
            'text_2' => $invoice->getText2(),
            'due_at' => $invoice->getDueAt()?->format('Y-m-d H:i:s'),
            'paid_at' => $invoice->getPaidAt()?->format('Y-m-d H:i:s'),
            'created_at' => $invoice->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $invoice->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'buyer_first_name' => $invoice->getBuyerFirstName(),
            'buyer_last_name' => $invoice->getBuyerLastName(),
            'buyer_company' => $invoice->getBuyerCompany(),
            'buyer_company_vat' => $invoice->getBuyerCompanyVat(),
            'buyer_company_number' => $invoice->getBuyerCompanyNumber(),
            'buyer_address' => $invoice->getBuyerAddress(),
            'buyer_city' => $invoice->getBuyerCity(),
            'buyer_state' => $invoice->getBuyerState(),
            'buyer_country' => $invoice->getBuyerCountry(),
            'buyer_phone' => $invoice->getBuyerPhone(),
            'buyer_phone_cc' => $invoice->getBuyerPhoneCc() ?? '',
            'buyer_email' => $invoice->getBuyerEmail(),
            'buyer_zip' => $invoice->getBuyerZip(),
            'seller_company' => $invoice->getSellerCompany(),
            'seller_company_vat' => $invoice->getSellerCompanyVat() ?? '',
            'seller_company_number' => $invoice->getSellerCompanyNumber() ?? '',
            'seller_address' => $invoice->getSellerAddress(),
            'seller_phone' => $invoice->getSellerPhone(),
            'seller_email' => $invoice->getSellerEmail(),
            'reminded_at' => $invoice->getRemindedAt()?->format('Y-m-d H:i:s'),
            'approved' => $invoice->isApproved(),
            'base_income' => $invoice->getBaseIncome() ?? 0,
            'base_refund' => $invoice->getBaseRefund() ?? 0,
            'refund' => $invoice->getRefund() ?? 0,
            'credit' => $invoice->getCredit() ?? 0,
        ];
    }
}
