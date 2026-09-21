<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Box\Mod\Invoice\Entity\Invoice;
use FOSSBilling\Environment;
use Pimple\Container;

class Payment_Adapter_PayPalEmail extends Payment_AdapterAbstract implements FOSSBilling\InjectionAwareInterface
{
    protected ?Container $di = null;

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Container
    {
        return $this->di;
    }

    public function __construct(private $config)
    {
        if (!isset($this->config['email'])) {
            throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'PayPal', ':missing' => 'PayPal Email address'], 4001);
        }
    }

    public static function getConfig(): array
    {
        return [
            'supports_one_time_payments' => true,
            'supports_subscriptions' => true,
            'description' => 'Enter your PayPal email to start accepting payments by PayPal.',
            'logo' => [
                'logo' => 'paypal.png',
                'height' => '25px',
                'width' => '85px',
            ],
            'form' => [
                'email' => [
                    'text',
                    [
                        'label' => 'PayPal email address for payments',
                        'validators' => ['EmailAddress'],
                    ],
                ],
            ],
        ];
    }

    public function getHtml($api_admin, $invoice_id, $subscription): string
    {
        $invoiceModel = $this->di['em']->getRepository(Invoice::class)->find($invoice_id);
        if (!$invoiceModel instanceof Invoice) {
            throw new Payment_Exception('Invoice not found');
        }

        $invoiceService = $this->di['mod_service']('Invoice');
        $invoice = $invoiceService->toApiArray($invoiceModel, true);

        $data = [];
        if ($subscription) {
            $data = $this->getSubscriptionFields($invoice);
        } else {
            $data = $this->getOneTimePaymentFields($invoice);
        }

        $url = $this->serviceUrl();

        return $this->_generateForm($url, $data);
    }

    public function processTransaction($api_admin, $id, $data, $gateway_id): void
    {
        if (!Environment::isTesting() && !$this->_isIpnValid($data)) {
            throw new Payment_Exception('IPN is invalid');
        }

        $ipn = $data['post'] ?? [];

        // PayPal's newer subscription flow uses recurring_payment* names for the
        // same concepts: normalize them once so every path below just works.
        if (!isset($ipn['subscr_id']) && isset($ipn['recurring_payment_id'])) {
            $ipn['subscr_id'] = $ipn['recurring_payment_id'];
        }
        if (!isset($ipn['mc_gross']) && isset($ipn['amount'])) {
            $ipn['mc_gross'] = $ipn['amount'];
        }
        if (!isset($ipn['amount3']) && isset($ipn['amount'])) {
            $ipn['amount3'] = $ipn['amount'];
        }
        if (!isset($ipn['mc_currency']) && isset($ipn['amount_currency'])) {
            $ipn['mc_currency'] = $ipn['amount_currency'];
        }

        $tx = $api_admin->invoice_transaction_get(['id' => $id]);

        // Set the invoice ID if it's not set
        if (!$tx['invoice_id']) {
            $invoiceID = $data['get']['invoice_id'] ?? null;
            if (empty($invoiceID)) {
                throw new Payment_Exception('PayPal transaction is not associated with an invoice');
            }
            $tx['invoice_id'] = $invoiceID;
            $api_admin->invoice_transaction_update(['id' => $id, 'invoice_id' => $invoiceID]);
        }

        if (!$tx['type'] && isset($ipn['txn_type'])) {
            $api_admin->invoice_transaction_update(['id' => $id, 'type' => $ipn['txn_type']]);
        }

        if (!$tx['txn_id'] && isset($ipn['txn_id'])) {
            $api_admin->invoice_transaction_update(['id' => $id, 'txn_id' => $ipn['txn_id']]);
        }

        if (!$tx['txn_status'] && isset($ipn['payment_status'])) {
            $api_admin->invoice_transaction_update(['id' => $id, 'txn_status' => $ipn['payment_status']]);
        }

        if (!$tx['amount'] && isset($ipn['mc_gross'])) {
            $api_admin->invoice_transaction_update(['id' => $id, 'amount' => $ipn['mc_gross']]);
        }

        if (!$tx['currency'] && isset($ipn['mc_currency'])) {
            $api_admin->invoice_transaction_update(['id' => $id, 'currency' => $ipn['mc_currency']]);
        }

        $invoice = $api_admin->invoice_get(['id' => $tx['invoice_id']]);
        $client_id = $invoice['client']['id'];

        $txnType = $ipn['txn_type'] ?? '';
        // Incomplete payments must stay received with their error instead of
        // being overwritten as cleanly processed by the tail update below.
        $awaitingCompletion = false;
        switch ($txnType) {
            case 'web_accept':
            case 'subscr_payment':
            case 'recurring_payment':
                if (($ipn['payment_status'] ?? '') === 'Completed') {
                    // Skip only if we've already processed a Completed payment for this transaction
                    if (isset($tx['status'], $tx['txn_status']) && $tx['status'] === Box\Mod\Invoice\Entity\Transaction::STATUS_PROCESSED && $tx['txn_status'] === 'Completed') {
                        $d = [
                            'id' => $id,
                            'error' => '',
                            'error_code' => null,
                            'status' => Box\Mod\Invoice\Entity\Transaction::STATUS_PROCESSED,
                            'updated_at' => date('Y-m-d H:i:s'),
                        ];
                        $api_admin->invoice_transaction_update($d);

                        return;
                    }

                    if (!isset($ipn['mc_gross'], $ipn['txn_id'])) {
                        throw new Payment_Exception('PayPal payment is missing transaction details');
                    }
                    $this->validateCurrency($ipn['mc_currency'] ?? null, $invoice['currency'] ?? null);
                    $isSubscriptionPayment = in_array($txnType, ['subscr_payment', 'recurring_payment'], true);
                    if ($isSubscriptionPayment && !isset($ipn['subscr_id'])) {
                        throw new Payment_Exception('PayPal subscription payment is missing the subscription ID');
                    }
                    if ($isSubscriptionPayment) {
                        $paymentSubscription = $this->di['em']->getRepository(Box\Mod\Invoice\Entity\Subscription::class)->findOneBy(['sid' => (string) $ipn['subscr_id']]);
                        if ($paymentSubscription instanceof Box\Mod\Invoice\Entity\Subscription
                            && $paymentSubscription->getRelType() === 'invoice'
                            && $paymentSubscription->getRelId() !== null
                            && $paymentSubscription->getRelId() !== (int) $tx['invoice_id']) {
                            throw new Payment_Exception('PayPal subscription ' . $ipn['subscr_id'] . ' is not linked to invoice ' . $tx['invoice_id']);
                        }
                    }

                    // Claim transaction for processing
                    // Prevents race conditions when multiple Completed IPNs arrive simultaneously
                    if (!$api_admin->invoice_transaction_claim_for_processing(['id' => $id])) {
                        $this->di['logger']->warning('Skipping PayPal transaction ' . $id . ': already being processed');

                        return;
                    }
                } elseif (($ipn['payment_status'] ?? '') === 'Refunded') {
                    break;
                } else {
                    $api_admin->invoice_transaction_update([
                        'id' => $id,
                        'txn_status' => (string) ($ipn['payment_status'] ?? ''),
                        'status' => Box\Mod\Invoice\Entity\Transaction::STATUS_RECEIVED,
                        'error' => sprintf('PayPal payment not completed: %s', (string) ($ipn['payment_status'] ?? 'unknown')),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    $this->di['logger']->info('PayPal payment for transaction ' . $id . ' not completed: ' . ($ipn['payment_status'] ?? 'unknown'));
                    $awaitingCompletion = true;

                    break;
                }

                // Reload transaction to get updated status
                $tx = $api_admin->invoice_transaction_get(['id' => $id]);

                // Update to Completed so transaction record reflects final PayPal status
                if (!isset($tx['txn_status']) || $tx['txn_status'] !== 'Completed') {
                    $api_admin->invoice_transaction_update(['id' => $id, 'txn_status' => 'Completed']);
                    $tx['txn_status'] = 'Completed';
                }

                if ($this->isIpnDuplicate($ipn)) {
                    throw new Payment_Exception('Cannot process duplicate IPN');
                }

                $invoiceService = $this->di['mod_service']('Invoice');
                $invoiceDbModel = null;
                if (!empty($tx['invoice_id'])) {
                    $invoiceDbModel = $this->di['em']->getRepository(Invoice::class)->find($tx['invoice_id']);
                }

                // For subscription renewals, generate a renewal invoice so
                // we validate against the correct amount. Skip this for the
                // initial payment (original invoice still unpaid) — that
                // payment should go to the original invoice.
                if ($isSubscriptionPayment && isset($ipn['subscr_id'])) {
                    $originalAlreadyPaid = $invoiceDbModel instanceof Invoice
                        && $invoiceDbModel->getStatus() === Invoice::STATUS_PAID;

                    if ($originalAlreadyPaid) {
                        $renewalInvoice = $invoiceService->generateRenewalInvoiceForSubscriptionPayment($ipn['subscr_id'], $client_id);
                        if (!$renewalInvoice instanceof Invoice || $renewalInvoice->getId() === null) {
                            throw new Payment_Exception('Unable to generate a renewal invoice for subscription payment');
                        }

                        $api_admin->invoice_transaction_update(['id' => $id, 'invoice_id' => $renewalInvoice->getId()]);
                        $tx['invoice_id'] = $renewalInvoice->getId();
                        $invoiceDbModel = $renewalInvoice;
                    }
                }

                if ($invoiceDbModel instanceof Invoice) {
                    $expected = $invoiceService->getTotalWithTax($invoiceDbModel);
                    $invoiceService->validatePaymentAmount((float) $ipn['mc_gross'], $expected);
                }

                $bd = [
                    'id' => $client_id,
                    'amount' => $ipn['mc_gross'],
                    'description' => 'PayPal transaction ' . $ipn['txn_id'],
                    'type' => 'PayPal',
                    'rel_id' => $ipn['txn_id'],
                ];

                $api_admin->client_balance_add_funds($bd);

                if (!empty($tx['invoice_id']) && $invoiceDbModel instanceof Invoice && !$invoiceService->isInvoiceTypeDeposit($invoiceDbModel)) {
                    if (!$invoiceDbModel->isApproved()) {
                        $invoiceService->approveInvoice($invoiceDbModel, ['use_credits' => false]);
                    }
                    $api_admin->invoice_pay_with_credits(['id' => $tx['invoice_id']]);
                } elseif (!empty($tx['invoice_id']) && $invoiceDbModel instanceof Invoice && $invoiceService->isInvoiceTypeDeposit($invoiceDbModel)) {
                    $invoiceService->markAsPaid($invoiceDbModel);
                } elseif (empty($tx['invoice_id'])) {
                    $api_admin->invoice_batch_pay_with_credits(['client_id' => $client_id]);
                }
                $this->di['logger']->info('Applied PayPal transaction ' . $ipn['txn_id'] . ' to invoice ' . $tx['invoice_id']);

                break;

            case 'subscr_signup':
            case 'recurring_payment_profile_created':
                $subscrId = (string) ($ipn['subscr_id'] ?? '');
                if ($subscrId === '') {
                    throw new Payment_Exception('PayPal subscription signup is missing the subscription ID');
                }
                $this->validateCurrency($ipn['mc_currency'] ?? null, $invoice['currency'] ?? null);
                $subscrPeriod = str_replace(' ', '', (string) ($ipn['period3'] ?? ''));
                if ($subscrPeriod === '') {
                    // Newer-flow IPNs carry no period: derive it from the linked
                    // invoice, preserving null when the invoice has no recurring period.
                    $subscriptionService = $this->di['mod_service']('Invoice', 'Subscription');
                    $periodInvoice = $this->di['em']->getRepository(Invoice::class)->find($tx['invoice_id']);
                    if ($periodInvoice instanceof Invoice) {
                        $subscrPeriod = $subscriptionService->getSubscriptionPeriod($periodInvoice);
                    }
                }

                $existingSubscription = $this->di['em']->getRepository(Box\Mod\Invoice\Entity\Subscription::class)->findOneBy(['sid' => $subscrId]);

                if (!$existingSubscription instanceof Box\Mod\Invoice\Entity\Subscription) {
                    $sd = [
                        'client_id' => $client_id,
                        'gateway_id' => $gateway_id,
                        'currency' => (string) ($ipn['mc_currency'] ?? ''),
                        'sid' => $subscrId,
                        'status' => 'active',
                        'period' => $subscrPeriod,
                        'amount' => (string) ($ipn['amount3'] ?? ''),
                        'rel_type' => 'invoice',
                        'rel_id' => $invoice['id'],
                    ];
                    $api_admin->invoice_subscription_create($sd);
                    $this->di['logger']->info('Stored PayPal subscription ' . $subscrId . ' from ' . $txnType . ' IPN for transaction ' . $id);
                }

                $t = [
                    'id' => $id,
                    's_id' => $subscrId,
                    's_period' => $subscrPeriod,
                ];
                $api_admin->invoice_transaction_update($t);

                break;

            case 'subscr_modify':
                $modifySid = (string) ($ipn['subscr_id'] ?? '');
                if ($modifySid === '') {
                    throw new Payment_Exception('PayPal subscription update is missing the subscription ID');
                }
                $modifiedSubscription = $this->di['em']->getRepository(Box\Mod\Invoice\Entity\Subscription::class)->findOneBy(['sid' => $modifySid]);
                if (!$modifiedSubscription instanceof Box\Mod\Invoice\Entity\Subscription) {
                    $this->di['logger']->warning('Ignoring PayPal subscr_modify IPN for unknown subscription ' . $modifySid . ' on transaction ' . $id);

                    break;
                }
                $modifyData = ['id' => $modifiedSubscription->getId()];
                if (isset($ipn['amount3'])) {
                    $modifyData['amount'] = (string) $ipn['amount3'];
                }
                if (isset($ipn['period3'])) {
                    $modifyData['period'] = str_replace(' ', '', (string) $ipn['period3']);
                }
                $api_admin->invoice_subscription_update($modifyData);
                $api_admin->invoice_transaction_update([
                    'id' => $id,
                    's_id' => $modifySid,
                    's_period' => $modifyData['period'] ?? $modifiedSubscription->getPeriod(),
                ]);
                $this->di['logger']->info('Updated subscription ' . $modifySid . ' from PayPal subscr_modify IPN for transaction ' . $id);

                break;

            case 'recurring_payment_suspended_due_to_max_failed_payment':
            case 'recurring_payment_profile_cancel':
            case 'recurring_payment_suspended':
            case 'recurring_payment_expired':
            case 'subscr_eot':
            case 'subscr_cancel':
                $cancelSid = (string) ($ipn['subscr_id'] ?? '');
                if ($cancelSid === '') {
                    throw new Payment_Exception('PayPal subscription update is missing the subscription ID');
                }

                $storedCancellation = $this->di['em']->getRepository(Box\Mod\Invoice\Entity\Subscription::class)->findOneBy(['sid' => $cancelSid]);
                if (!$storedCancellation instanceof Box\Mod\Invoice\Entity\Subscription) {
                    // Nothing to cancel for an unknown subscription, so acknowledge
                    // the notification instead of failing the IPN.
                    $this->di['logger']->warning('Ignoring PayPal ' . $txnType . ' IPN for unknown subscription ' . $cancelSid . ' on transaction ' . $id);

                    break;
                }
                $api_admin->invoice_subscription_update(['id' => $storedCancellation->getId(), 'status' => 'canceled']);
                $this->di['logger']->info('Canceled subscription ' . $cancelSid . ' from PayPal ' . $txnType . ' IPN for transaction ' . $id);

                break;

            case 'recurring_payment_failed':
            case 'subscr_failed':
                $failedSid = (string) ($ipn['subscr_id'] ?? '');
                if ($failedSid === '') {
                    throw new Payment_Exception('PayPal subscription update is missing the subscription ID');
                }
                $failedSubscription = $this->di['em']->getRepository(Box\Mod\Invoice\Entity\Subscription::class)->findOneBy(['sid' => $failedSid]);
                if (!$failedSubscription instanceof Box\Mod\Invoice\Entity\Subscription) {
                    $this->di['logger']->warning('Ignoring PayPal ' . $txnType . ' IPN for unknown subscription ' . $failedSid . ' on transaction ' . $id);

                    break;
                }
                // A failed charge does not end the subscription: PayPal reattempts
                // it and later payments still apply, so link the transaction and
                // leave the subscription status untouched.
                $api_admin->invoice_transaction_update(['id' => $id, 's_id' => $failedSid]);
                $this->di['logger']->warning('Recorded failed PayPal payment for subscription ' . $failedSid . ' on transaction ' . $id);

                break;

            case 'recurring_payment_skipped':
                $skippedSid = (string) ($ipn['subscr_id'] ?? '');
                if ($skippedSid === '') {
                    throw new Payment_Exception('PayPal subscription update is missing the subscription ID');
                }
                // A skipped cycle leaves the subscription itself in place, so
                // there is nothing to update — just leave a trace of the missed payment.
                $this->di['logger']->warning('Acknowledged skipped PayPal payment for subscription ' . $skippedSid . ' on transaction ' . $id);

                break;

            default:
                $this->di['logger']->error('Unknown PayPal transaction ' . $id);

                break;
        }

        if (
            isset($ipn['payment_status'], $ipn['txn_type'])
            && $ipn['payment_status'] == 'Refunded'
            && in_array($ipn['txn_type'], ['web_accept', 'subscr_payment', 'recurring_payment'], true)
        ) {
            $refd = [
                'id' => $invoice['id'],
                'note' => 'PayPal refund ' . ($ipn['parent_txn_id'] ?? ''),
            ];
            $api_admin->invoice_refund($refd);
        }

        if ($awaitingCompletion) {
            return;
        }

        $d = [
            'id' => $id,
            'error' => '',
            'error_code' => null,
            'status' => Box\Mod\Invoice\Entity\Transaction::STATUS_PROCESSED,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $api_admin->invoice_transaction_update($d);
    }

    private function validateCurrency(mixed $received, mixed $expected): void
    {
        $received = trim((string) $received);
        $expected = trim((string) $expected);
        if ($received === '' || $expected === '') {
            throw new Payment_Exception('PayPal payment is missing currency details');
        }
        if (strcasecmp($received, $expected) !== 0) {
            throw new Payment_Exception(sprintf('PayPal payment currency %s does not match invoice currency %s', $received, $expected));
        }
    }

    private function serviceUrl(): string
    {
        if ($this->config['test_mode']) {
            return 'https://www.sandbox.paypal.com/cgi-bin/webscr';
        }

        return 'https://www.paypal.com/cgi-bin/webscr';
    }

    private function _isIpnValid($data): bool
    {
        // use http_raw_post_data instead of post due to encoding
        parse_str((string) $data['http_raw_post_data'], $post);
        $req = 'cmd=_notify-validate';
        foreach ($post as $key => $value) {
            // No stripslashes() here: that undid PHP's long-removed magic_quotes_gpc
            // escaping. Applied unconditionally it corrupts any value containing a
            // backslash, so the re-posted request no longer matches what PayPal sent.
            $value = urlencode((string) $value);
            $req .= "&$key=$value";
        }

        if ($this->download($this->serviceUrl(), $req) !== 'VERIFIED') {
            return false;
        }

        // VERIFIED only proves PayPal originated the notification; verify the
        // payee so a payment to a different account cannot be replayed here.
        $configured = strtolower(trim((string) $this->config['email']));
        $payees = [
            strtolower(trim((string) ($post['receiver_email'] ?? ''))),
            strtolower(trim((string) ($post['business'] ?? ''))),
        ];

        foreach ($payees as $payee) {
            if ($payee !== '' && hash_equals($configured, $payee)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $url
     */
    private function download($url, array|string|null $post_vars = null): string
    {
        $post_contents = '';
        if ($post_vars !== null) {
            if (is_array($post_vars)) {
                foreach ($post_vars as $key => $val) {
                    $post_contents .= ($post_contents ? '&' : '') . urlencode((string) $key) . '=' . urlencode((string) $val);
                }
            } else {
                $post_contents = $post_vars;
            }
        }

        $httpClient = $this->di['http_client']->withOptions([
            'timeout' => 600,
        ]);
        $response = $httpClient->request('POST', $url, [
            'body' => $post_contents,
        ]);

        return $response->getContent();
    }

    /**
     * @param string $url
     */
    private function _generateForm($url, $data, $method = 'post'): string
    {
        $form = '';
        $safeUrl = htmlspecialchars((string) $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeMethod = htmlspecialchars((string) $method, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $form .= '<form name="payment_form" action="' . $safeUrl . '" method="' . $safeMethod . '">' . PHP_EOL;
        foreach ($data as $key => $value) {
            $safeKey = htmlspecialchars((string) $key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeValue = htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $form .= sprintf('<input type="hidden" name="%s" value="%s" />', $safeKey, $safeValue) . PHP_EOL;
        }
        $form .= '<input class="btn btn-primary" type="submit" value="Pay with PayPal" id="payment_button"/>' . PHP_EOL;
        $form .= '</form>' . PHP_EOL . PHP_EOL;

        if (isset($this->config['auto_redirect']) && $this->config['auto_redirect']) {
            $form .= sprintf('<h2>%s</h2>', __trans('Redirecting to PayPal.com'));
            $form .= "<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('payment_button').style.display = 'none';
    document.forms['payment_form'].submit();
});
</script>";
        }

        return $form;
    }

    public function isIpnDuplicate(array $ipn): bool
    {
        $sql = 'SELECT id
                FROM transaction
                WHERE txn_id = :transaction_id
                  AND txn_status = :transaction_status
                  AND type = :transaction_type
                  AND amount = :transaction_amount
                LIMIT 2';

        $bindings = [
            'transaction_id' => $ipn['txn_id'] ?? null,
            'transaction_status' => $ipn['payment_status'] ?? null,
            'transaction_type' => $ipn['txn_type'] ?? null,
            'transaction_amount' => $ipn['mc_gross'] ?? null,
        ];

        $rows = $this->di['em']->getConnection()->fetchAllAssociative($sql, $bindings);

        return count($rows) > 1;
    }

    public function getInvoiceTitle(array $invoice): string
    {
        $p = [
            ':id' => sprintf('%05d', (int) $invoice['nr']),
            ':serie' => $invoice['serie'],
            ':title' => $invoice['lines'][0]['title'],
        ];

        return __trans('Payment for invoice :serie:id [:title]', $p);
    }

    public function getSubscriptionFields(array $invoice): array
    {
        $data = [];
        $subs = $invoice['subscription'];

        $data['item_name'] = $this->getInvoiceTitle($invoice);
        $data['item_number'] = $invoice['nr'];
        $data['no_shipping'] = '1';
        $data['no_note'] = '1'; // Do not prompt payers to include a note with their payments. Allowable values for Subscribe buttons:
        $data['currency_code'] = $invoice['currency'];
        $data['return'] = $this->config['thankyou_url'];
        $data['cancel_return'] = $this->config['cancel_url'];
        $data['notify_url'] = $this->config['notify_url'];
        $data['business'] = $this->config['email'];

        $data['cmd'] = '_xclick-subscriptions';
        $data['rm'] = '2';

        $data['invoice'] = $invoice['id'];

        // Recurrence info
        $data['a3'] = $this->moneyFormat($invoice['total'], $invoice['currency']); // Regular subscription price.
        $data['p3'] = $subs['cycle']; // Subscription duration. Specify an integer value in the allowable range for the units of duration that you specify with t3.

        /*
         * t3: Regular subscription units of duration. Allowable values:
         *  D – for days; allowable range for p3 is 1 to 90
         *  W – for weeks; allowable range for p3 is 1 to 52
         *  M – for months; allowable range for p3 is 1 to 24
         *  Y – for years; allowable range for p3 is 1 to 5
         */
        $data['t3'] = $subs['unit'];

        $data['src'] = 1; // Recurring payments. Subscription payments recur unless subscribers cancel their subscriptions before the end of the current billing cycle or you limit the number of times that payments recur with the value that you specify for srt.
        $data['sra'] = 1; // Reattempt on failure. If a recurring payment fails, PayPal attempts to collect the payment two more times before canceling the subscription.
        $data['charset'] = 'UTF-8'; // Sets the character encoding for the billing information/log-in page, for the information you send to PayPal in your HTML button code, and for the information that PayPal returns to you as a result of checkout processes initiated by the payment button. The default is based on the character encoding settings in your account profile.

        // client data
        $buyer = $invoice['buyer'];
        $data['address1'] = $buyer['address'];
        $data['city'] = $buyer['city'];
        $data['email'] = $buyer['email'];
        $data['first_name'] = $buyer['first_name'];
        $data['last_name'] = $buyer['last_name'];
        $data['zip'] = $buyer['zip'];
        $data['state'] = $buyer['state'];
        $data['bn'] = 'FOSSBilling_SP';

        return $data;
    }

    public function getOneTimePaymentFields(array $invoice): array
    {
        $data = [];
        $data['item_name'] = $this->getInvoiceTitle($invoice);
        $data['item_number'] = $invoice['nr'];
        $data['no_shipping'] = '1';
        $data['no_note'] = '1';
        $data['currency_code'] = $invoice['currency'];
        $data['rm'] = '2';
        $data['return'] = $this->config['thankyou_url'];
        $data['cancel_return'] = $this->config['cancel_url'];
        $data['notify_url'] = $this->config['notify_url'];
        $data['business'] = $this->config['email'];
        $data['cmd'] = '_xclick';
        $data['amount'] = $this->moneyFormat($invoice['subtotal'], $invoice['currency']);
        $data['tax'] = $this->moneyFormat($invoice['tax'], $invoice['currency']);
        $data['bn'] = 'FOSSBilling_SP';
        $data['charset'] = 'utf-8';

        return $data;
    }
}
