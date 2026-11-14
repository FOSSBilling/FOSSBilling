<?php

declare(strict_types=1);

/**
 * PayPal billing agreements: clients agree once and their invoices are then
 * paid from their PayPal account automatically. Uses PayPal's NVP API with
 * reference transactions, for PayPal accounts that are not offered the REST
 * vault (PayPal Email's saved accounts). A client agrees once to a billing
 * agreement (B-...), when paying an invoice or on the Payment methods page.
 * It is then their saved method (Invoice/ServiceSavedCards.php): the cron
 * and staff charge it with DoReferenceTransaction, and the client can pay an
 * invoice with it. A payment PayPal holds waits as "Payment pending" until
 * PayPal's notice (IPN). An agreement cancelled at PayPal is removed. A
 * reversal (chargeback) refunds the payment and moves the renewed services'
 * due dates back. Every payment is read back from PayPal with these API
 * details, so a notice can only point at this account's payments.
 */

use Box\Mod\Client\Entity\Client;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\Transaction;
use FOSSBilling\Core\Doctrine\NamedLock;

class Payment_Adapter_PayPalAutoPay implements FOSSBilling\Core\Container\InjectionAwareInterface
{
    private const string VERSION = '119.0';
    // DoReferenceTransaction errors for an agreement the client cancelled or that is no longer valid.
    // The agreement itself is cancelled, closed or unknown; account-wide refusals (10209, 11452) keep every agreement.
    private const array AGREEMENT_GONE = ['10201', '10204', '11451'];

    protected ?Pimple\Container $di = null;

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Pimple\Container
    {
        return $this->di;
    }

    public function __construct(private array $config)
    {
        $prefix = empty($this->config['test_mode']) ? '' : 'test_';
        foreach (['api_username', 'api_password', 'api_signature'] as $key) {
            if (trim((string) ($this->config[$prefix . $key] ?? '')) === '') {
                throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'PayPal Auto-Pay', ':missing' => ($prefix === '' ? 'live' : 'sandbox') . ' API details'], 4001);
            }
        }
    }

    public static function getConfig(): array
    {
        $onOff = ['1' => __trans('On'), '0' => __trans('Off')];

        return [
            'title' => 'PayPal Auto-Pay',
            'supports_one_time_payments' => true,
            'supports_subscriptions' => false,
            'description' => __trans('PayPal billing agreements: clients agree once and their invoices are then paid from their PayPal account automatically. Needs the NVP/SOAP API details of a PayPal business account with Reference Transactions turned on (ask PayPal to turn them on).'),
            'logo' => ['logo' => 'paypal.png', 'height' => '25px', 'width' => '85px'],
            'form' => [
                'api_username' => ['text', ['label' => __trans('Live API username:'), 'description' => __trans('PayPal: Account Settings, Website payments, API access, NVP/SOAP API integration (Classic), API signature.')]],
                'api_password' => ['password', ['label' => __trans('Live API password:'), 'secret' => true]],
                'api_signature' => ['password', ['label' => __trans('Live API signature:'), 'secret' => true]],
                'test_api_username' => ['text', ['label' => __trans('Sandbox API username:'), 'description' => __trans('developer.paypal.com: Sandbox accounts, your business account, API Credentials.')]],
                'test_api_password' => ['password', ['label' => __trans('Sandbox API password:'), 'secret' => true]],
                'test_api_signature' => ['password', ['label' => __trans('Sandbox API signature:'), 'secret' => true]],
                'pay_saved' => ['radio', ['label' => __trans('Pay with the saved agreement:'), 'description' => __trans('On the invoice page, a client with a billing agreement can pay at once without going to PayPal.'), 'multiOptions' => $onOff]],
                'pay_once' => ['radio', ['label' => __trans('Pay once without an agreement:'), 'description' => __trans('Clients can also pay an invoice with PayPal without setting up automatic payments.'), 'multiOptions' => $onOff]],
                'save_only' => ['radio', ['label' => __trans('Set up on the Payment methods page:'), 'description' => __trans('Clients can set up automatic payments on their Payment methods page without paying an invoice.'), 'multiOptions' => $onOff]],
                'free_orders' => ['radio', ['label' => __trans('Free orders:'), 'description' => __trans('After a free order that renews (a free trial), a client without a saved payment method is sent to PayPal to set up automatic payments.'), 'multiOptions' => $onOff]],
                'prefill_email' => ['radio', ['label' => __trans('Fill in the client\'s email at PayPal:'), 'multiOptions' => $onOff]],
                'convert_to' => ['text', ['label' => __trans('Convert to for processing (optional):'), 'placeholder' => 'USD', 'description' => __trans('A currency code of this portal: USD (US dollar), EUR (euro) or GBP (British pound). Invoices in other currencies are charged in it at the portal\'s exchange rates. Empty: each invoice is charged in its own currency.')]],
                'logo_url' => ['text', ['label' => __trans('Logo on PayPal\'s pages (optional):'), 'description' => __trans('The address (https) of an image PayPal shows on its checkout pages. Empty: your company logo, if it is a PNG, JPG or GIF.')]],
                'balance' => ['radio', ['label' => __trans('PayPal balance on the dashboard:'), 'multiOptions' => $onOff]],
            ],
        ];
    }

    public function gatewayId(): int
    {
        return (int) ($this->config['gateway_id'] ?? 0);
    }

    public function testMode(): bool
    {
        return !empty($this->config['test_mode']);
    }

    /** Whether a setting with an On/Off choice is on ($default when it was never saved). */
    public function option(string $key, bool $default = true): bool
    {
        return (string) ($this->config[$key] ?? ($default ? '1' : '0')) === '1';
    }

    /** The API details are required, so they are always present. */
    public function hasApiKeys(): bool
    {
        return true;
    }

    /** The API details work and the account may make billing agreements (Reference Transactions). */
    public function testConnection(): void
    {
        if ($this->convertTo() !== '') {
            try {
                $this->di['mod_service']('Currency')->getBaseCurrencyRate($this->convertTo());
            } catch (FOSSBilling\Core\Exception\BaseException) {
                throw new FOSSBilling\Core\Exception\InformationException('Convert to for processing: :code is not one of the currencies of this portal.', [':code' => $this->convertTo()]);
            }
        }
        $url = (string) $this->di['url']->link('payment-methods');
        $reply = $this->call('SetExpressCheckout', [
            'PAYMENTREQUEST_0_AMT' => '0', 'PAYMENTREQUEST_0_CURRENCYCODE' => $this->convertTo() ?: 'USD', 'L_BILLINGTYPE0' => 'MerchantInitiatedBilling',
            'L_BILLINGAGREEMENTDESCRIPTION0' => 'Test', 'RETURNURL' => $url, 'CANCELURL' => $url, 'NOSHIPPING' => '1',
        ]);
        if (!$this->ok($reply)) {
            throw new FOSSBilling\Core\Exception\InformationException('PayPal refused the API details or this account cannot make billing agreements (ask PayPal to turn on Reference Transactions): :error', [':error' => $this->error($reply)]);
        }
    }

    /** The client's saved billing agreement ({payment_method, card, auto_pay_at}), or null. */
    public function savedCard(int $clientId): ?array
    {
        return $this->di['mod_service']('Invoice', 'SavedCards')->saved($this->gatewayId(), $this->testMode(), $clientId);
    }

    /**
     * The payment choices for an invoice. The signed-in owner can pay with
     * their saved agreement, or pay and set up automatic payments; anyone
     * can pay once when that is on.
     */
    public function getHtml(FOSSBilling\Core\Api\Proxy $api_admin, int $invoice_id, bool $subscription): string
    {
        $invoice = $this->di['em']->getRepository(Invoice::class)->find($invoice_id);
        if (!$invoice instanceof Invoice) {
            throw new FOSSBilling\Core\Exception\BaseException('Invoice not found');
        }
        $loggedInClientId = isset($this->di['loggedin_client']) ? (int) $this->di['loggedin_client']->getId() : 0;
        $owner = $loggedInClientId > 0 && $loggedInClientId === (int) $invoice->getClientId();
        $saved = $owner ? $this->savedCard((int) $invoice->getClientId()) : null;
        $link = fn (string $path): string => htmlspecialchars((string) $this->di['url']->link($path), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $text = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '';
        if ($saved !== null && $this->option('pay_saved')) {
            $html .= '<p><button type="button" class="btn btn-primary" data-autopay-pay>' . $text(__trans('Pay now with :account', [':account' => $saved['card']])) . '</button></p>';
            $html .= '<script>document.querySelector("[data-autopay-pay]").addEventListener("click", function (event) {'
                . ' var button = event.currentTarget; button.disabled = true;'
                . ' FOSSBilling.api.client.post("invoice/autopay_pay", ' . json_encode(['hash' => $invoice->getHash()], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ', function (result) {'
                . ' if (result.paid) { location.assign(' . json_encode((string) $this->di['url']->link('invoice/thank-you/' . $invoice->getHash()), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) . '); } else { FOSSBilling.message(result.message, "info"); }'
                . ' }, function (error) { button.disabled = false; FOSSBilling.message(error.message, "error"); });'
                . ' });</script>';
        } elseif ($owner) {
            $html .= '<p><a class="btn btn-primary" href="' . $link('invoice/paypal-autopay/' . $invoice->getHash()) . '">' . $text(__trans('Pay with PayPal and set up automatic payments')) . '</a></p>'
                . '<p class="text-muted">' . $text(__trans('Your renewal invoices are then paid from your PayPal account. You can stop this on your Payment methods page or in PayPal.')) . '</p>';
        }
        if ($this->option('pay_once')) {
            $html .= '<p><a class="btn btn-outline-primary" href="' . $link('invoice/paypal-autopay/' . $invoice->getHash() . '?once=1') . '">' . $text(__trans('Pay once with PayPal')) . '</a></p>';
        }

        return $html !== '' ? $html : '<p>' . $text(__trans('Sign in as the account owner to pay with PayPal.')) . '</p>';
    }

    /**
     * Starts paying an invoice at PayPal, with a billing agreement when $save: {token, url}. PayPal sends the client
     * back to $returnUrl with the token (checkoutPaid()).
     */
    public function checkout(Invoice $invoice, bool $save, string $returnUrl, string $cancelUrl): array
    {
        [$amount, $currency, $custom] = $this->charged($invoice, $this->di['mod_service']('Invoice')->getBalanceDue($invoice));
        $fields = [
            'PAYMENTREQUEST_0_AMT' => $amount,
            'PAYMENTREQUEST_0_CURRENCYCODE' => $currency,
            'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale',
            'PAYMENTREQUEST_0_DESC' => $this->description($invoice),
            'PAYMENTREQUEST_0_CUSTOM' => $custom,
            'PAYMENTREQUEST_0_NOTIFYURL' => $this->notifyUrl((int) $invoice->getId()),
        ];
        if ($save) {
            $fields += $this->agreementFields();
        }

        return $this->startCheckout($fields + $this->pageFields((int) $invoice->getClientId(), $returnUrl, $cancelUrl), true);
    }

    /** Starts a billing agreement without a payment (the Payment methods page, a free order): {token, url}. */
    public function agreementSetup(int $clientId, string $returnUrl, string $cancelUrl): array
    {
        return $this->startCheckout([
            'PAYMENTREQUEST_0_AMT' => '0',
            'PAYMENTREQUEST_0_CURRENCYCODE' => $this->convertTo() ?: (string) $this->di['mod_service']('Currency')->getCurrencyByClientId($clientId)->getCode(),
            'PAYMENTREQUEST_0_CUSTOM' => 'fb-client-' . $clientId,
        ] + $this->agreementFields() + $this->pageFields($clientId, $returnUrl, $cancelUrl), false);
    }

    /** Back from PayPal after agreementSetup(): keeps the agreement as the client's saved method. */
    public function agreementSaved(int $clientId, string $token): void
    {
        $details = $this->call('GetExpressCheckoutDetails', ['TOKEN' => $token]);
        if (!$this->ok($details) || ($details['PAYMENTREQUEST_0_CUSTOM'] ?? $details['CUSTOM'] ?? '') !== 'fb-client-' . $clientId) {
            throw new FOSSBilling\Core\Exception\InformationException('PayPal did not set up automatic payments: :error', [':error' => $this->error($details)]);
        }
        $reply = $this->call('CreateBillingAgreement', ['TOKEN' => $token]);
        if (!$this->ok($reply) || empty($reply['BILLINGAGREEMENTID'])) {
            throw new FOSSBilling\Core\Exception\InformationException('PayPal did not set up automatic payments: :error', [':error' => $this->error($reply)]);
        }
        $this->remember($clientId, (string) $reply['BILLINGAGREEMENTID'], (string) ($details['EMAIL'] ?? ''));
    }

    /**
     * Back from PayPal after checkout(): takes the approved payment and keeps its billing agreement, if any. Returns
     * PayPal's transaction ID; the payment is recorded from it (processTransaction()).
     */
    public function checkoutPaid(Invoice $invoice, string $token): string
    {
        $details = $this->call('GetExpressCheckoutDetails', ['TOKEN' => $token]);
        if (!$this->ok($details) || preg_match('/^fb-invoice-' . (int) $invoice->getId() . '(-|$)/', (string) ($details['PAYMENTREQUEST_0_CUSTOM'] ?? '')) !== 1) {
            throw new FOSSBilling\Core\Exception\InformationException('PayPal did not take the payment: :error', [':error' => $this->error($details)]);
        }
        // Taken already (the client came back twice): PayPal names the payment.
        if (!empty($details['PAYMENTREQUEST_0_TRANSACTIONID'])) {
            return (string) $details['PAYMENTREQUEST_0_TRANSACTIONID'];
        }
        $reply = $this->call('DoExpressCheckoutPayment', [
            'TOKEN' => $token,
            'PAYERID' => (string) ($details['PAYERID'] ?? ''),
            'PAYMENTREQUEST_0_AMT' => (string) $details['PAYMENTREQUEST_0_AMT'],
            'PAYMENTREQUEST_0_CURRENCYCODE' => (string) $details['PAYMENTREQUEST_0_CURRENCYCODE'],
            'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale',
            'PAYMENTREQUEST_0_CUSTOM' => (string) $details['PAYMENTREQUEST_0_CUSTOM'],
            'PAYMENTREQUEST_0_NOTIFYURL' => $this->notifyUrl((int) $invoice->getId()),
            'MSGSUBID' => 'fb-' . $token,
        ]);
        if (!$this->ok($reply) || empty($reply['PAYMENTINFO_0_TRANSACTIONID'])) {
            throw new FOSSBilling\Core\Exception\InformationException('PayPal did not take the payment: :error', [':error' => $this->error($reply)]);
        }
        if (!empty($reply['BILLINGAGREEMENTID'])) {
            $this->remember((int) $invoice->getClientId(), (string) $reply['BILLINGAGREEMENTID'], (string) ($details['EMAIL'] ?? ''));
        }

        return (string) $reply['PAYMENTINFO_0_TRANSACTIONID'];
    }

    /**
     * Charges the client's billing agreement for an invoice's balance (the cron, staff, or the client on the invoice
     * page). $attempt names the try, so each one is its own payment at PayPal. Returns PayPal's transaction ID; PayPal's
     * refusal throws InformationException, no answer Payment_Exception.
     */
    public function chargeSavedCard(Invoice $invoice, string $attempt): string
    {
        $saved = $this->savedCard((int) $invoice->getClientId()) ?? throw new FOSSBilling\Core\Exception\InformationException('The client has no PayPal billing agreement.');
        [$amount, $currency, $custom] = $this->charged($invoice, $this->di['mod_service']('Invoice')->getBalanceDue($invoice));
        $reply = $this->call('DoReferenceTransaction', [
            'REFERENCEID' => $saved['payment_method'],
            'PAYMENTACTION' => 'Sale',
            'AMT' => $amount,
            'CURRENCYCODE' => $currency,
            'DESC' => $this->description($invoice),
            'CUSTOM' => $custom,
            'NOTIFYURL' => $this->notifyUrl((int) $invoice->getId()),
            'MSGSUBID' => 'fb-invoice-' . $invoice->getId() . '-' . $attempt,
        ]);
        if ($this->ok($reply) && !empty($reply['TRANSACTIONID'])) {
            return (string) $reply['TRANSACTIONID'];
        }
        if (in_array((string) ($reply['L_ERRORCODE0'] ?? ''), self::AGREEMENT_GONE, true)) {
            // An agreement the client cancelled at PayPal is removed, so it is not tried again.
            $this->forget($saved['payment_method']);
        }

        throw new FOSSBilling\Core\Exception\InformationException('PayPal refused the payment: :error', [':error' => $this->error($reply)]);
    }

    /** Removes the client's billing agreement, here and at PayPal. */
    public function agreementRemove(int $clientId): void
    {
        $saved = $this->savedCard($clientId);
        if ($saved !== null) {
            $this->cancelAgreement($saved['payment_method']);
            $this->forget($saved['payment_method']);
        }
    }

    /** Sends money of a payment back (staff refunds): PayPal's refund ID. */
    public function refundPayment(string $transactionId, float $amount, string $currency): string
    {
        $payment = $this->call('GetTransactionDetails', ['TRANSACTIONID' => $transactionId]);
        if (!empty($payment['CURRENCYCODE']) && strtoupper((string) $payment['CURRENCYCODE']) !== strtoupper($currency)) {
            // Converted for processing: the same share of what PayPal took, so a later rate never refunds more.
            $recorded = (float) $this->di['em']->getConnection()->fetchOne(
                'SELECT amount FROM ' . $this->di['em']->getConnection()->quoteSingleIdentifier('transaction') . ' WHERE txn_id = ? AND gateway_id = ? AND status = ? AND txn_status = ? ORDER BY id LIMIT 1',
                [$transactionId, $this->gatewayId(), Transaction::STATUS_PROCESSED, 'Completed']
            );
            if ($recorded <= 0) {
                throw new FOSSBilling\Core\Exception\InformationException('PayPal did not record this payment here: return the money in the PayPal dashboard.');
            }
            $amount = min((float) $payment['AMT'], (float) $payment['AMT'] * $amount / $recorded);
            $currency = (string) $payment['CURRENCYCODE'];
        }
        // An amount with "Partial" also refunds the whole payment, so the amount is always the one staff chose.
        $reply = $this->call('RefundTransaction', ['TRANSACTIONID' => $transactionId, 'REFUNDTYPE' => 'Partial', 'AMT' => $this->moneyFormat($amount, $currency), 'CURRENCYCODE' => $currency]);
        if (!$this->ok($reply) || empty($reply['REFUNDTRANSACTIONID'])) {
            throw new FOSSBilling\Core\Exception\InformationException('PayPal did not make the refund: :error', [':error' => $this->error($reply)]);
        }

        return (string) $reply['REFUNDTRANSACTIONID'];
    }

    /** The PayPal account's balances: [{currency, amount}]. */
    public function balances(): array
    {
        $reply = $this->call('GetBalance', ['RETURNALLCURRENCIES' => '1']);
        if (!$this->ok($reply)) {
            throw new FOSSBilling\Core\Exception\InformationException('PayPal did not give the balance: :error', [':error' => $this->error($reply)]);
        }
        $balances = [];
        for ($i = 0; isset($reply['L_AMT' . $i]); ++$i) {
            $balances[] = ['currency' => (string) ($reply['L_CURRENCYCODE' . $i] ?? ''), 'amount' => (float) $reply['L_AMT' . $i]];
        }

        return $balances;
    }

    /** The status of a billing agreement at PayPal (Active, Canceled), or null when PayPal does not know it. */
    public function agreementStatus(string $agreementId): ?string
    {
        $reply = $this->call('BillAgreementUpdate', ['REFERENCEID' => $agreementId]);

        return $this->ok($reply) ? (string) ($reply['BILLINGAGREEMENTSTATUS'] ?? '') : null;
    }

    /**
     * A payment made here (source saved_card, get.paypal_txn) or PayPal's notice (IPN). A
     * payment is always read back from PayPal: its CUSTOM names the invoice.
     */
    public function processTransaction($api_admin, int $id, array $data, int $gateway_id): void
    {
        $id = (int) $id;
        // Only this install's own pages pass a payment ID: a request to ipn.php naming one is never trusted.
        if (isset($data['get']['paypal_txn']) && ($data['source'] ?? '') !== 'ipn') {
            $this->recordPayment($api_admin, $id, (string) $data['get']['paypal_txn']);

            return;
        }
        $post = is_array($data['post'] ?? null) ? $data['post'] : [];
        if (!FOSSBilling\Core\System\Environment::isTesting() && !$this->ipnVerified((string) ($data['http_raw_post_data'] ?? ''))) {
            throw new Payment_Exception('PayPal notice is not genuine');
        }
        $txnId = (string) ($post['txn_id'] ?? '');
        $status = (string) ($post['payment_status'] ?? '');
        $note = 'No action needed.';
        if (($post['txn_type'] ?? '') === 'mp_cancel' && !empty($post['mp_id'])) {
            $this->forget((string) $post['mp_id']);
            $note = 'Billing agreement ' . $post['mp_id'] . ' cancelled at PayPal: removed.';
        } elseif ($status === 'Reversed' && !empty($post['parent_txn_id'])) {
            $note = $this->reversal((string) $post['parent_txn_id'], $txnId, abs((float) ($post['mc_gross'] ?? 0)), (string) ($post['mc_currency'] ?? ''));
        } elseif ($status === 'Canceled_Reversal') {
            $note = 'PayPal cancelled the reversal of payment ' . ($post['parent_txn_id'] ?? '') . ' (the dispute was won): check its invoice.';
        } elseif (in_array($status, ['Completed', 'Pending'], true) && $txnId !== '' && in_array($post['txn_type'] ?? '', ['express_checkout', 'merch_pmt', 'cart'], true)) {
            $pending = $this->pendingRows($txnId);
            if ($pending === []) {
                // The first this install hears of the payment (the charge got no answer): this notice's row records it.
                $this->recordPayment($api_admin, $id, $txnId);

                return;
            }
            // A held payment completes on its own row, so it counts once and its invoice is paid now.
            if ($status === 'Completed') {
                $this->recordPayment($api_admin, (int) $pending[0], $txnId);
            }
            $note = 'Payment ' . $txnId . ' ' . strtolower($status) . ': transaction #' . $pending[0] . '.';
        } elseif (in_array($status, ['Failed', 'Denied', 'Voided', 'Expired'], true) && $txnId !== '' && $this->pendingRows($txnId) !== []) {
            $this->failPending($txnId, 'PayPal payment ' . $status . '.');
            $note = 'Pending payment ' . $txnId . ': ' . $status . '.';
        }
        $api_admin->invoice_transaction_update(['id' => $id, 'txn_id' => $txnId ?: null, 'txn_status' => $status ?: ($post['txn_type'] ?? null), 'type' => 'paypal_ipn', 'status' => Transaction::STATUS_PROCESSED, 'error' => null, 'note' => $note, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /** A payment as PayPal reports it: recorded on transaction $id and applied to its invoice once. */
    private function recordPayment($api_admin, int $id, string $txnId): void
    {
        $payment = $this->call('GetTransactionDetails', ['TRANSACTIONID' => $txnId]);
        if (!$this->ok($payment)) {
            throw new Payment_Exception('PayPal payment :id was not found: :error', [':id' => $txnId, ':error' => $this->error($payment)]);
        }
        // Only payments made here name an invoice: another shop's payment on the same PayPal account pays nothing.
        if (preg_match('/^fb-invoice-(\d+)(?:-(\d+\.\d+)-([0-9a-f]{16}))?$/', (string) ($payment['CUSTOM'] ?? ''), $match) !== 1) {
            throw new Payment_Exception('PayPal payment :id is not for a FOSSBilling invoice', [':id' => $txnId]);
        }
        $invoice = $this->di['em']->getRepository(Invoice::class)->find((int) $match[1]);
        if (!$invoice instanceof Invoice) {
            throw new Payment_Exception('Invoice not found');
        }
        $amount = (float) ($payment['AMT'] ?? 0);
        $currency = (string) ($payment['CURRENCYCODE'] ?? '');
        $status = (string) ($payment['PAYMENTSTATUS'] ?? '');
        // The invoice amount a converted payment was asked for counts only when signed here for exactly what PayPal took.
        $asked = isset($match[2]) && hash_equals($this->askedSignature((int) $match[1], $match[2], $this->moneyFormat($amount, $currency), $currency), $match[3]) ? $match[2] : '';
        $row = ['invoice_id' => $invoice->getId(), 'txn_id' => $txnId, 'type' => 'paypal_nvp', 'amount' => $amount, 'currency' => $currency, 'updated_at' => date('Y-m-d H:i:s')];
        if (in_array($status, ['Pending', 'In-Progress'], true)) {
            // As WHMCS's "Payment Pending": no other charge or reminder until PayPal's notice settles it.
            $api_admin->invoice_transaction_update($row + ['id' => $id, 'txn_status' => 'processing', 'status' => Transaction::STATUS_RECEIVED, 'error' => 'PayPal is holding the payment: ' . ($payment['PENDINGREASON'] ?? 'pending')]);

            return;
        }
        if (!in_array($status, ['Completed', 'Completed-Funds-Held', 'Processed'], true)) {
            $api_admin->invoice_transaction_update($row + ['id' => $id, 'txn_status' => $status, 'status' => Transaction::STATUS_ERROR, 'error' => 'PayPal payment not completed: ' . $status]);

            return;
        }
        // The return from PayPal and PayPal's notice may record the same payment at once.
        $lockName = 'fb:paypal-payment:' . $txnId;
        if (!NamedLock::acquire($this->di['em']->getConnection(), $lockName, 10)) {
            throw new Payment_Exception('Another request is being processed. Please try again.');
        }

        try {
            $this->applyPayment($api_admin, $id, $txnId, $invoice, $amount, $currency, $row, $asked);
        } finally {
            NamedLock::release($this->di['em']->getConnection(), $lockName);
        }
    }

    private function applyPayment($api_admin, int $id, string $txnId, Invoice $invoice, float $amount, string $currency, array $row, string $asked): void
    {
        $connection = $this->di['em']->getConnection();
        // Applied already ("Process again", the return page after PayPal's notice, or the other PayPal gateway): never twice.
        $done = $connection->fetchOne(
            'SELECT id FROM ' . $connection->quoteSingleIdentifier('transaction') . ' WHERE txn_id = ? AND status = ? AND txn_status = ? ORDER BY id LIMIT 1',
            [$txnId, Transaction::STATUS_PROCESSED, 'Completed']
        );
        if ($done !== false) {
            if ((int) $done !== $id) {
                $api_admin->invoice_transaction_update(['id' => $id, 'txn_id' => $txnId, 'status' => Transaction::STATUS_PROCESSED, 'note' => 'Payment ' . $txnId . ' was already recorded on transaction #' . $done . '.', 'updated_at' => date('Y-m-d H:i:s')]);
            }

            return;
        }
        $service = $this->di['mod_service']('Invoice');
        $due = $service->getBalanceDue($invoice);
        if (strtoupper($currency) !== strtoupper((string) $invoice->getCurrency())) {
            if (strtoupper($currency) !== $this->convertTo()) {
                throw new Payment_Exception('PayPal payment :id is in :currency, the invoice in :invoice', [':id' => $txnId, ':currency' => $currency, ':invoice' => $invoice->getCurrency()]);
            }
            // Converted for processing: worth the invoice amount it was asked for (signed CUSTOM), also after the rate changed.
            $paid = $this->moneyFormat($amount, $currency);
            $amount = $asked !== '' ? (float) $asked : round($this->convert($amount, $currency, (string) $invoice->getCurrency()), 2);
            $row = ['amount' => $amount, 'currency' => (string) $invoice->getCurrency(), 'note' => 'PayPal: ' . $paid . ' ' . $currency . '.'] + $row;
        }
        $service->validatePaymentAmount($amount, $due);
        $api_admin->invoice_transaction_update($row + ['id' => $id, 'txn_status' => 'Completed']);
        // As PayPal Email: the payment goes to the client's credit, which then pays the invoice.
        $api_admin->client_balance_add_funds(['id' => $invoice->getClientId(), 'amount' => $amount, 'description' => 'PayPal transaction ' . $txnId, 'type' => 'PayPal', 'rel_id' => $txnId]);
        if ($service->isInvoiceTypeDeposit($invoice)) {
            $service->markAsPaid($invoice);
        } else {
            if (!$invoice->isIssued()) {
                $service->issueInvoice($invoice, ['use_credits' => false]);
            }
            $api_admin->invoice_pay_with_credits(['id' => $invoice->getId()]);
        }
        $api_admin->invoice_transaction_update(['id' => $id, 'status' => Transaction::STATUS_PROCESSED, 'error' => null, 'error_code' => null, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * A chargeback: the payment is refunded on its invoice (a credit note) and the
     * services it renewed go back by their period. Returns the note for staff.
     */
    private function reversal(string $paymentId, string $reversalId, float $amount, string $currency): string
    {
        $connection = $this->di['em']->getConnection();
        $table = $connection->quoteSingleIdentifier('transaction');
        $payment = $connection->fetchAssociative(
            "SELECT invoice_id, amount FROM {$table} WHERE txn_id = ? AND gateway_id = ? AND status = ? AND txn_status = ? ORDER BY id LIMIT 1",
            [$paymentId, $this->gatewayId(), Transaction::STATUS_PROCESSED, 'Completed']
        );
        if ($payment === false || empty($payment['invoice_id'])) {
            return sprintf('PayPal reversed payment %s (%s): it is not a payment recorded here, settle it by hand.', $paymentId, $reversalId);
        }
        $invoice = $this->di['em']->getRepository(Invoice::class)->find((int) $payment['invoice_id']);
        if (!$invoice instanceof Invoice) {
            return sprintf('PayPal reversed payment %s (%s): its invoice is gone, settle it by hand.', $paymentId, $reversalId);
        }
        if ($currency !== '' && strtoupper($currency) !== strtoupper((string) $invoice->getCurrency())) {
            // Converted for processing: the same share of the payment as recorded here (all of it if PayPal does not say).
            $paid = (float) ($this->call('GetTransactionDetails', ['TRANSACTIONID' => $paymentId])['AMT'] ?? 0) ?: $amount;
            if ($paid <= 0) {
                return sprintf('PayPal reversed payment %s (%s): the reversed amount is unknown, settle it by hand.', $paymentId, $reversalId);
            }
            $amount = round((float) $payment['amount'] * $amount / $paid, 2);
        }
        $service = $this->di['mod_service']('Invoice');
        $text = sprintf('PayPal chargeback %s of payment %s.', $reversalId, $paymentId);

        try {
            $full = abs($amount - $service->getTotalWithTax($invoice)) < 0.01;
            if ($full) {
                $service->refundInvoice($invoice, $text);
            } else {
                // Arbitrary gross shares cannot map onto integer line quantities: book the exact amount on a tax-exempt custom line.
                $service->refundInvoice($invoice, $text, null, [
                    'gateway_refund' => false,
                    'custom_line' => [
                        'title' => sprintf('Partial chargeback %s of payment %s.', $reversalId, $paymentId),
                        'amount' => $amount,
                    ],
                ]);
                $result = $text . ' Partial reversal booked on a custom-line credit note.';
                $service->addNote($invoice, $result);

                return $result;
            }
        } catch (FOSSBilling\Core\Exception\InformationException $e) {
            $service->addNote($invoice, $text . ' ' . $e->getMessage());

            return $text . ' Not refunded here: ' . $e->getMessage();
        }
        $moved = [];
        $items = $connection->fetchAllAssociative(
            "SELECT rel_id, period FROM invoice_item WHERE invoice_id = ? AND type = 'order' AND task = ? AND status = 'executed'",
            [(int) $invoice->getId(), Box\Mod\Invoice\Entity\InvoiceItem::TASK_RENEW]
        );
        foreach ($items as $item) {
            $orderRow = $connection->fetchAssociative('SELECT id, expires_at, period FROM client_order WHERE id = ?', [(int) $item['rel_id']]);
            if ($orderRow === false || empty($orderRow['expires_at'])) {
                continue;
            }
            $code = (string) ($item['period'] ?: ($orderRow['period'] ?? ''));
            if ($code === '') {
                continue;
            }
            $period = new FOSSBilling\Core\Period($code);
            $unit = match ($period->getUnit()) {
                FOSSBilling\Core\Period::UNIT_DAY => 'days', FOSSBilling\Core\Period::UNIT_WEEK => 'weeks', FOSSBilling\Core\Period::UNIT_YEAR => 'years', default => 'months',
            };
            $expires = date('Y-m-d H:i:s', strtotime('-' . $period->getQty() . ' ' . $unit, strtotime((string) $orderRow['expires_at'])));
            $connection->update('client_order', ['expires_at' => $expires, 'updated_at' => date('Y-m-d H:i:s')], ['id' => (int) $orderRow['id']]);
            $moved[] = sprintf('order #%d due %s', $orderRow['id'], substr($expires, 0, 10));
        }
        $text .= $moved === [] ? '' : ' Due dates moved back: ' . implode(', ', $moved) . '.';
        $service->addNote($invoice, $text);
        $this->di['logger']->warning('Invoice #{id}: {message}', ['id' => $invoice->getId(), 'message' => $text]);

        return $text;
    }

    /** Payments of $txnId still waiting for PayPal (Payment pending). */
    private function pendingRows(string $txnId): array
    {
        $connection = $this->di['em']->getConnection();

        return $connection->fetchFirstColumn(
            'SELECT id FROM ' . $connection->quoteSingleIdentifier('transaction') . " WHERE txn_id = ? AND gateway_id = ? AND status = ? AND txn_status = 'processing'",
            [$txnId, $this->gatewayId(), Transaction::STATUS_RECEIVED]
        );
    }

    /** Ends the wait of $txnId's pending rows when PayPal did not complete the payment. */
    private function failPending(string $txnId, string $note): void
    {
        $connection = $this->di['em']->getConnection();
        $table = $connection->quoteSingleIdentifier('transaction');
        foreach ($this->pendingRows($txnId) as $rowId) {
            $connection->update($table, ['status' => Transaction::STATUS_ERROR, 'txn_status' => 'Failed', 'error' => $note, 'note' => $note, 'updated_at' => date('Y-m-d H:i:s')], ['id' => (int) $rowId]);
        }
    }

    /** Keeps a billing agreement as the client's saved method; the one saved before is cancelled at PayPal. */
    private function remember(int $clientId, string $agreementId, string $email): void
    {
        $old = $this->savedCard($clientId)['payment_method'] ?? null;
        $this->di['mod_service']('Invoice', 'SavedCards')->remember($this->gatewayId(), $this->testMode(), $clientId, '', $agreementId, trim('PayPal ' . $email));
        if ($old !== null && $old !== $agreementId) {
            $this->cancelAgreement($old);
        }
    }

    /** Removes an agreement wherever it is saved (cancelled at PayPal). */
    private function forget(string $agreementId): void
    {
        $this->di['mod_service']('Invoice', 'SavedCards')->forget($this->gatewayId(), $this->testMode(), $agreementId);
    }

    private function cancelAgreement(string $agreementId): void
    {
        try {
            $reply = $this->call('BillAgreementUpdate', ['REFERENCEID' => $agreementId, 'BILLINGAGREEMENTSTATUS' => 'Canceled']);
            if (!$this->ok($reply)) {
                $this->di['logger']->warning('PayPal: billing agreement {agreement} was not cancelled at PayPal: {message}', ['agreement' => $agreementId, 'message' => $this->error($reply)]);
            }
        } catch (Throwable $e) {
            $this->di['logger']->warning('PayPal: billing agreement {agreement} was not cancelled at PayPal: {message}', ['agreement' => $agreementId, 'message' => $e->getMessage()]);
        }
    }

    private function description(Invoice $invoice): string
    {
        return mb_substr(__trans('Invoice :number', [':number' => $invoice->getSerie() . $invoice->getNr()]), 0, 127);
    }

    private function agreementFields(): array
    {
        return [
            'L_BILLINGTYPE0' => 'MerchantInitiatedBilling',
            'L_BILLINGAGREEMENTDESCRIPTION0' => mb_substr(__trans('Automatic payments to :company', [':company' => (string) ($this->di['mod_service']('system')->getCompany()['name'] ?? 'us')]), 0, 127),
        ];
    }

    private function pageFields(int $clientId, string $returnUrl, string $cancelUrl): array
    {
        $fields = ['RETURNURL' => $returnUrl, 'CANCELURL' => $cancelUrl, 'NOSHIPPING' => '1'];
        if ($this->option('prefill_email')) {
            $client = $this->di['em']->getRepository(Client::class)->find($clientId);
            $fields['EMAIL'] = (string) ($client?->getEmail() ?? '');
        }
        // Empty: the company logo; PayPal takes https images of up to 127 characters, and no SVG.
        $logo = (string) (($this->config['logo_url'] ?? '') ?: ($this->di['mod_service']('system')->getCompany()['logo_url'] ?? ''));
        if (str_starts_with($logo, 'https://') && strlen($logo) <= 127 && !str_ends_with(strtolower($logo), '.svg')) {
            $fields['LOGOIMG'] = $logo;
        }

        return $fields;
    }

    /** {token, url}: PayPal's page where the client approves the checkout. */
    private function startCheckout(array $fields, bool $payNow): array
    {
        $reply = $this->call('SetExpressCheckout', $fields);
        if (!$this->ok($reply) || empty($reply['TOKEN'])) {
            throw new FOSSBilling\Core\Exception\InformationException('PayPal did not start the payment: :error', [':error' => $this->error($reply)]);
        }
        $url = 'https://www.' . ($this->testMode() ? 'sandbox.' : '') . 'paypal.com/cgi-bin/webscr?' . http_build_query(['cmd' => '_express-checkout', 'token' => $reply['TOKEN']] + ($payNow ? ['useraction' => 'commit'] : []));

        return ['token' => (string) $reply['TOKEN'], 'url' => $url];
    }

    private function notifyUrl(int $invoiceId): string
    {
        return SYSTEM_URL . 'ipn.php?' . http_build_query(['gateway_id' => $this->gatewayId(), 'invoice_id' => $invoiceId]);
    }

    /** PayPal's NVP API: the reply's fields. No answer (or not one PayPal could make) throws Payment_Exception. */
    private function call(string $method, array $fields): array
    {
        $prefix = $this->testMode() ? 'test_' : '';
        $response = $this->di['http_client']->request('POST', 'https://api-3t.' . ($this->testMode() ? 'sandbox.' : '') . 'paypal.com/nvp', [
            'timeout' => 60,
            'body' => ['METHOD' => $method, 'VERSION' => self::VERSION, 'USER' => trim((string) $this->config[$prefix . 'api_username']), 'PWD' => trim((string) $this->config[$prefix . 'api_password']), 'SIGNATURE' => trim((string) $this->config[$prefix . 'api_signature'])] + $fields,
        ]);

        try {
            $body = $response->getContent();
        } catch (Throwable $e) {
            throw new Payment_Exception('PayPal did not answer :method: :error', [':method' => $method, ':error' => $e->getMessage()]);
        }
        parse_str($body, $reply);
        if (!isset($reply['ACK'])) {
            throw new Payment_Exception('PayPal did not answer :method', [':method' => $method]);
        }

        return array_map(static fn ($value): string => is_string($value) ? $value : '', $reply);
    }

    private function ok(array $reply): bool
    {
        return in_array(strtoupper($reply['ACK'] ?? ''), ['SUCCESS', 'SUCCESSWITHWARNING'], true);
    }

    private function error(array $reply): string
    {
        return trim(($reply['L_LONGMESSAGE0'] ?? $reply['L_SHORTMESSAGE0'] ?? 'unknown error') . ' (' . ($reply['L_ERRORCODE0'] ?? '-') . ')');
    }

    /** PayPal confirms it sent this notice (cmd=_notify-validate on the exact body). */
    private function ipnVerified(string $raw): bool
    {
        if ($raw === '') {
            return false;
        }
        $response = $this->di['http_client']->request('POST', 'https://ipnpb.' . ($this->testMode() ? 'sandbox.' : '') . 'paypal.com/cgi-bin/webscr', [
            'timeout' => 60,
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body' => 'cmd=_notify-validate&' . $raw,
        ]);

        return trim($response->getContent()) === 'VERIFIED';
    }

    /** The currency PayPal charges in, empty for each invoice's own. */
    private function convertTo(): string
    {
        return strtoupper(trim((string) ($this->config['convert_to'] ?? '')));
    }

    /**
     * An amount of the invoice's currency as PayPal charges it: [amount, currency, CUSTOM]. CUSTOM names the invoice and,
     * when converted, the invoice amount asked for with its signature (fb-invoice-5-500.00-<16 hex>).
     */
    private function charged(Invoice $invoice, float $amount): array
    {
        $to = $this->convertTo();
        $custom = 'fb-invoice-' . $invoice->getId();
        if ($to === '' || $to === strtoupper((string) $invoice->getCurrency())) {
            return [$this->moneyFormat($amount, (string) $invoice->getCurrency()), (string) $invoice->getCurrency(), $custom];
        }

        $charged = $this->moneyFormat($this->convert($amount, (string) $invoice->getCurrency(), $to), $to);
        $asked = number_format($amount, 2, '.', '');

        return [$charged, $to, $custom . '-' . $asked . '-' . $this->askedSignature((int) $invoice->getId(), $asked, $charged, $to)];
    }

    /** Payers write CUSTOM on payments of their own: the asked amount is signed with this install's secret. */
    private function askedSignature(int $invoiceId, string $asked, string $charged, string $currency): string
    {
        return substr(hash_hmac('sha256', $invoiceId . '|' . $asked . '|' . $charged . '|' . strtoupper($currency), 'fossbilling-paypal-autopay|' . (string) FOSSBilling\Core\System\Config::getProperty('info.salt', '')), 0, 16);
    }

    /** At the portal's exchange rates, which are to the default currency. */
    private function convert(float $amount, string $from, string $to): float
    {
        $currency = $this->di['mod_service']('Currency');

        return $currency->toBaseCurrency($from, $amount) / $currency->getBaseCurrencyRate($to);
    }

    private function moneyFormat(float $amount, string $currency): string
    {
        $digits = Symfony\Component\Intl\Currencies::exists($currency) ? Symfony\Component\Intl\Currencies::getFractionDigits($currency) : 2;

        return number_format($amount, $digits, '.', '');
    }
}
