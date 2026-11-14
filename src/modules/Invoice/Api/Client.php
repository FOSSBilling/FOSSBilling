<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 *Invoice management.
 */

namespace Box\Mod\Invoice\Api;

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Order\Entity\Order;
use FOSSBilling\Core\Pagination\Options;
use FOSSBilling\Core\Validation\Api\RequiredParams;

class Client extends \FOSSBilling\Core\Api\AbstractApi
{
    /**
     * Get paginated list of invoices.
     *
     * @return array
     */
    public function get_list($data)
    {
        $data['client_id'] = $this->getIdentity()->getId();
        $data['issued'] = true;

        $service = $this->getService();
        $qb = $service->getInvoiceRepository()->getSearchQueryBuilder($data);

        return $this->getDi()['pager']->paginateMappedQuery(
            $qb,
            Options::fromArray($data),
            static fn ($invoice): array => $service->toApiArray($invoice),
        );
    }

    /**
     * Get invoice details.
     *
     * @return array
     *
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['hash' => 'Invoice hash was not passed'])]
    public function get($data)
    {
        $identity = $this->getIdentity();
        $model = $this->getService()->getInvoiceRepository()->findOneBy(['hash' => $data['hash'], 'clientId' => $identity->getId()]);
        if (!$model) {
            throw new \FOSSBilling\Core\Exception\InformationException('Invoice was not found');
        }

        $result = $this->getService()->toApiArray($model, true, $identity);
        $result['debited_by_invoice_ids'] = $this->getService()->getDebitingInvoiceIds($model);
        $result['related_invoices'] = $this->getService()->getRelatedInvoiceReferences($model);

        return $result;
    }

    /**
     * Generates new invoice for selected order. If unpaid invoice for selected order
     * already exists, new invoice will not be generated, and old invoice hash
     * is returned.
     *
     * @return string - invoice hash
     *
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['order_id' => 'Order ID (order_id) was not passed'])]
    public function renewal_invoice($data)
    {
        $model = $this->getDi()['em']->getRepository(Order::class)->findOneBy(['clientId' => $this->getIdentity()->getId(), 'id' => $data['order_id']]);
        if (!$model instanceof Order) {
            throw new \FOSSBilling\Core\Exception\InformationException('Order not found');
        }
        $service = $this->getService();
        $invoice = $service->generateForOrder($model);
        $service->issueInvoice($invoice, ['id' => $invoice->getId(), 'use_credits' => true]);
        $this->getDi()['logger']->info('Generated new renewal invoice #{invoice_id}', ['invoice_id' => $invoice->getId()]);

        return $invoice->getHash();
    }

    /**
     * Deposit money in advance. Generates new invoice for depositing money.
     * Clients currency must be defined.
     *
     * @return string - invoice hash
     */
    #[RequiredParams(['amount' => 'Amount is required'])]
    public function funds_invoice($data)
    {
        if (!is_numeric($data['amount'])) {
            throw new \FOSSBilling\Core\Exception\InformationException('You need to enter numeric value');
        }

        $service = $this->getService();
        $invoice = $service->generateFundsInvoice($this->getIdentity(), $data['amount']);
        $service->issueInvoice($invoice, ['id' => $invoice->getId()]);
        $this->getDi()['logger']->info('Generated add funds invoice #{invoice_id}', ['invoice_id' => $invoice->getId()]);

        return $invoice->getHash();
    }

    /**
     * Apply part or all of the account credit to an unpaid invoice.
     *
     * @return bool
     */
    #[RequiredParams(['hash' => 'Invoice hash was not passed', 'amount' => 'Amount is missing'])]
    public function apply_credit($data)
    {
        $model = $this->getService()->getInvoiceRepository()->findOneBy(['hash' => $data['hash'], 'clientId' => $this->getIdentity()->getId()]);
        if (!$model instanceof Invoice) {
            throw new \FOSSBilling\Core\Exception\InformationException('Invoice was not found');
        }

        return $this->getService()->applyCredit($model, (float) $data['amount']);
    }

    /**
     * Get paginated list of transactions.
     *
     * @optional string $invoice_hash - filter transactions by invoice hash
     * @optional int $gateway_id - filter transactions by payment gateway id
     * @optional string $status - filter transactions by status
     * @optional string $currency - filter transactions by currency code
     * @optional string $date_from - filter transactions by date
     * @optional string $date_to - filter transactions by date
     *
     * @return array
     */
    public function transaction_get_list($data)
    {
        $data['client_id'] = $this->getIdentity()->getId();
        $data['status'] = 'processed';
        $transactionService = $this->getDi()['mod_service']('Invoice', 'Transaction');
        $qb = $transactionService->getTransactionRepository()->getSearchQueryBuilder($data);

        return $this->getDi()['pager']->paginateMappedQuery(
            $qb,
            Options::fromArray($data),
            static fn ($row): array => $transactionService->transactionResultToApiArray($row[0], $row['gateway'] ?? null, $row['gateway_code'] ?? null),
        );
    }

    public function get_tax_rate()
    {
        $service = $this->getDi()['mod_service']('Invoice', 'Tax');

        return $service->getTaxRateForClient($this->getIdentity());
    }

    /**
     * The client's saved payment methods for the Payment methods page:
     * {available, cards: [{id, card, default}]}. PayPal and other gateways
     * fill their own keys once ported; default marks the method that pays
     * the invoices.
     */
    public function card_list(): array
    {
        $clientId = (int) $this->getIdentity()->getId();
        $savedCards = $this->getDi()['mod_service']('Invoice', 'SavedCards');
        $stripe = $savedCards->stripe();
        $paying = $savedCards->method($clientId)['adapter'] ?? null;
        $cards = $stripe?->cards($clientId) ?? [];
        foreach ($cards as &$card) {
            $card['default'] = $card['default'] && $paying instanceof \Payment_Adapter_Stripe;
        }
        $autopay = $savedCards->autopay();
        $agreement = $autopay?->savedCard($clientId);
        $paypal = $savedCards->paypal();
        $account = $paypal?->savedCard($clientId);

        return [
            'available' => $stripe !== null,
            'bank' => $stripe?->bankDebit($clientId) ?? false,
            'cards' => $cards,
            'paypal' => $paypal === null ? null : ['account' => $account['card'] ?? null, 'default' => $account !== null && $paying instanceof \Payment_Adapter_PayPalEmail],
            'autopay' => $autopay === null ? null : ['account' => $agreement['card'] ?? null, 'default' => $agreement !== null && $paying instanceof \Payment_Adapter_PayPalAutoPay, 'add' => $autopay->option('save_only')],
        ];
    }

    /** Whether a saved payment method can be used here, for the client menu. */
    public function card_available(): bool
    {
        return $this->getDi()['mod_service']('Invoice', 'SavedCards')->adapters() !== [];
    }

    /** Starts adding a card: {client_secret, pub_key} for Stripe's card form. */
    public function card_setup(): array
    {
        return $this->cardAdapter()->cardSetup((int) $this->getIdentity()->getId());
    }

    /** Keeps the card of a confirmed setup, the default one when the client has none. */
    #[RequiredParams(['setup_intent' => 'Setup ID was not passed'])]
    public function card_added($data): bool
    {
        $this->cardAdapter()->cardAdded((int) $this->getIdentity()->getId(), (string) $data['setup_intent']);

        return true;
    }

    /** Makes a saved card the one invoices are charged to. */
    #[RequiredParams(['id' => 'Card ID was not passed'])]
    public function card_default($data): bool
    {
        $this->cardAdapter()->cardDefault((int) $this->getIdentity()->getId(), (string) $data['id']);

        return true;
    }

    /** Removes a saved card. */
    #[RequiredParams(['id' => 'Card ID was not passed'])]
    public function card_remove($data): bool
    {
        $this->cardAdapter()->cardRemove((int) $this->getIdentity()->getId(), (string) $data['id']);

        return true;
    }

    /** The "Save this card" choice (and "Use this card for automatic payments") for the card payment of the client's own unpaid invoice. */
    #[RequiredParams(['hash' => 'Invoice hash was not passed', 'gateway_id' => 'Payment gateway ID was not passed', 'intent' => 'Payment ID was not passed'])]
    public function card_save_choice($data): bool
    {
        $clientId = (int) $this->getIdentity()->getId();
        $invoice = $this->getDi()['em']->getRepository(Invoice::class)->findOneBy([
            'hash' => $data['hash'],
            'clientId' => $clientId,
            'status' => Invoice::STATUS_UNPAID,
        ]);
        if (!$invoice instanceof Invoice) {
            throw new \FOSSBilling\Core\Exception\InformationException('This invoice is already paid or cancelled.');
        }
        $gateway = $this->getDi()['em']->getRepository(PayGateway::class)->findOneBy(['id' => (int) $data['gateway_id'], 'gateway' => 'Stripe', 'enabled' => true]);
        $adapter = $gateway instanceof PayGateway
            ? $this->getDi()['mod_service']('Invoice', 'PayGateway')->getPaymentAdapter($gateway)
            : null;
        if (!$adapter instanceof \Payment_Adapter_Stripe) {
            throw new \FOSSBilling\Core\Exception\InformationException('Saved cards are not available.');
        }
        $adapter->cardSaveChoice($clientId, (int) $invoice->getId(), (string) $data['intent'], !empty($data['save']), !empty($data['make_default']));

        return true;
    }

    /** Makes the billing agreement the method that pays the client's invoices. */
    public function autopay_default(): bool
    {
        $adapter = $this->autopayAdapter();
        if ($adapter->savedCard((int) $this->getIdentity()->getId()) === null) {
            throw new \FOSSBilling\Core\Exception\InformationException('You have no PayPal billing agreement.');
        }
        $this->getDi()['mod_service']('Invoice', 'SavedCards')->choose($adapter->gatewayId(), $adapter->testMode(), (int) $this->getIdentity()->getId());

        return true;
    }

    /** Removes the client's billing agreement, here and at PayPal. */
    public function autopay_remove(): bool
    {
        $this->autopayAdapter()->agreementRemove((int) $this->getIdentity()->getId());

        return true;
    }

    /** Pays the client's own unpaid invoice at once with their billing agreement. */
    #[RequiredParams(['hash' => 'Invoice hash was not passed'])]
    public function autopay_pay($data): array
    {
        $adapter = $this->autopayAdapter();
        $clientId = (int) $this->getIdentity()->getId();
        if (!$adapter->option('pay_saved') || $adapter->savedCard($clientId) === null) {
            throw new \FOSSBilling\Core\Exception\InformationException('You cannot pay this invoice with a saved PayPal account.');
        }
        $invoice = $this->getDi()['em']->getRepository(Invoice::class)->findOneBy([
            'hash' => $data['hash'],
            'clientId' => $clientId,
            'status' => Invoice::STATUS_UNPAID,
        ]);
        if (!$invoice instanceof Invoice) {
            throw new \FOSSBilling\Core\Exception\InformationException('This invoice is already paid or cancelled.');
        }
        $result = $this->getDi()['mod_service']('Invoice', 'SavedCards')->charge($invoice, $adapter, true, true);

        return ['paid' => $result['paid'], 'message' => (string) ($result['error'] ?? '')];
    }

    private function autopayAdapter(): \Payment_Adapter_PayPalAutoPay
    {
        return $this->getDi()['mod_service']('Invoice', 'SavedCards')->autopay()
            ?? throw new \FOSSBilling\Core\Exception\InformationException('Automatic payments with PayPal are not available.');
    }

    /** Makes the saved PayPal account the method that pays the client's invoices. */
    public function paypal_default(): bool
    {
        $adapter = $this->paypalAdapter();
        if ($adapter->savedCard((int) $this->getIdentity()->getId()) === null) {
            throw new \FOSSBilling\Core\Exception\InformationException('You have no saved PayPal account.');
        }
        $this->getDi()['mod_service']('Invoice', 'SavedCards')->choose($adapter->gatewayId(), $adapter->testMode(), (int) $this->getIdentity()->getId());

        return true;
    }

    /** Removes the client's saved PayPal account, here and at PayPal. */
    public function paypal_remove(): bool
    {
        $this->paypalAdapter()->vaultRemove((int) $this->getIdentity()->getId());

        return true;
    }

    /** Pays the client's own unpaid invoice at once with their saved PayPal account. */
    #[RequiredParams(['hash' => 'Invoice hash was not passed'])]
    public function paypal_pay($data): array
    {
        $adapter = $this->paypalAdapter();
        $clientId = (int) $this->getIdentity()->getId();
        if ($adapter->savedCard($clientId) === null) {
            throw new \FOSSBilling\Core\Exception\InformationException('You cannot pay this invoice with a saved PayPal account.');
        }
        $invoice = $this->getDi()['em']->getRepository(Invoice::class)->findOneBy([
            'hash' => $data['hash'],
            'clientId' => $clientId,
            'status' => Invoice::STATUS_UNPAID,
        ]);
        if (!$invoice instanceof Invoice) {
            throw new \FOSSBilling\Core\Exception\InformationException('This invoice is already paid or cancelled.');
        }
        $result = $this->getDi()['mod_service']('Invoice', 'SavedCards')->charge($invoice, $adapter, true, true);

        return ['paid' => $result['paid'], 'message' => (string) ($result['error'] ?? '')];
    }

    private function paypalAdapter(): \Payment_Adapter_PayPalEmail
    {
        return $this->getDi()['mod_service']('Invoice', 'SavedCards')->paypal()
            ?? throw new \FOSSBilling\Core\Exception\InformationException('Saved PayPal accounts are not available.');
    }

    private function cardAdapter(): \Payment_Adapter_Stripe
    {
        return $this->getDi()['mod_service']('Invoice', 'SavedCards')->stripe()
            ?? throw new \FOSSBilling\Core\Exception\InformationException('Saved cards are not available.');
    }
}
