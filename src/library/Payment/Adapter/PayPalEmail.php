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
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\Transaction;
use FOSSBilling\Core\System\Environment;
use Pimple\Container;

class Payment_Adapter_PayPalEmail extends Payment_AdapterAbstract implements FOSSBilling\Core\Container\InjectionAwareInterface
{
    protected ?Container $di = null;

    private ?string $restToken = null;

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Container
    {
        return $this->di;
    }

    public static function requiresManualApproval(): bool
    {
        return false;
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
                'api_client_id' => [
                    'text',
                    [
                        'label' => 'REST API client ID (live):',
                        'description' => 'PayPal: your REST app, API Credentials. Needed for saved PayPal accounts, refunds, cancelling subscriptions and the dashboard balance.',
                    ],
                ],
                'api_secret' => [
                    'password',
                    [
                        'label' => 'REST API secret (live):',
                        'secret' => true,
                    ],
                ],
                'test_api_client_id' => [
                    'text',
                    [
                        'label' => 'REST API client ID (sandbox):',
                    ],
                ],
                'test_api_secret' => [
                    'password',
                    [
                        'label' => 'REST API secret (sandbox):',
                        'secret' => true,
                    ],
                ],
                'vault' => [
                    'radio',
                    [
                        'label' => 'Saved PayPal accounts:',
                        'description' => 'Clients can save their PayPal account when they pay or on their Payment methods page, and their invoices are then paid automatically, as with saved cards. Needs the REST API keys and the Vault ("Save payment methods") on your PayPal REST app, which PayPal offers in some countries only.',
                        'multiOptions' => ['1' => __trans('On'), '0' => __trans('Off')],
                    ],
                ],
                'balance' => [
                    'radio',
                    [
                        'label' => 'PayPal balance on the dashboard:',
                        'multiOptions' => ['1' => __trans('On'), '0' => __trans('Off')],
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
        if ($subscription) {
            $data = $this->getSubscriptionFields($invoice);
        } else {
            $data = $this->getOneTimePaymentFields($invoice);
        }

        $url = $this->serviceUrl();
        // On their own invoice, a signed-in client may pay and save their PayPal account,
        // so the page waits for their choice instead of going to PayPal at once.
        $loggedInClientId = isset($this->di['loggedin_client']) ? (int) $this->di['loggedin_client']->getId() : 0;
        $save = !$subscription && $this->canVault() && $loggedInClientId > 0 && $loggedInClientId === (int) $invoiceModel->getClientId();
        if ($save) {
            $this->config['auto_redirect'] = false;
        }
        $form = $this->_generateForm($url, $data);
        $saved = $save ? $this->savedCard((int) $invoiceModel->getClientId()) : null;
        if ($saved !== null) {
            // The saved PayPal account pays at once.
            $form .= '<p class="mt-3"><button type="button" class="btn btn-primary" data-paypal-pay>' . htmlspecialchars(__trans('Pay now with :account', [':account' => $saved['card']]), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</button></p>'
                . '<script>document.querySelector("[data-paypal-pay]").addEventListener("click", function (event) {'
                . ' var button = event.currentTarget; button.disabled = true;'
                . ' FOSSBilling.api.client.post("invoice/paypal_pay", ' . json_encode(['hash' => $invoiceModel->getHash()], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ', function (result) {'
                . ' if (result.paid) { location.assign(' . json_encode((string) $this->di['url']->link('invoice/thank-you/' . $invoiceModel->getHash()), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) . '); } else { FOSSBilling.message(result.message, "info"); }'
                . ' }, function (error) { button.disabled = false; FOSSBilling.message(error.message, "error"); });'
                . ' });</script>';
        } elseif ($save) {
            $form .= sprintf(
                '<p class="mt-3"><a class="btn btn-outline-primary" href="%s">%s</a></p>',
                htmlspecialchars((string) $this->di['url']->link('invoice/paypal-save/' . $invoiceModel->getHash()), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                htmlspecialchars(__trans('Pay and save my PayPal account'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            );
        }

        return $form;
    }

    public function processTransaction($api_admin, int $id, array $data, int $gateway_id): void
    {
        // A REST payment (a saved PayPal account, "Pay and save") the return page recorded: read it back from PayPal.
        if (isset($data['get']['paypal_capture']) && ($data['source'] ?? '') !== 'ipn') {
            $this->processRestCapture($api_admin, $id, (string) $data['get']['paypal_capture']);

            return;
        }

        $post = (isset($data['post']) && is_array($data['post'])) ? $data['post'] : [];
        if ($post === []) {
            throw new Payment_Exception('PayPal payment data is missing.', [], 7021);
        }

        if (!Environment::isTesting() && !$this->_isIpnValid($data)) {
            throw new Payment_Exception('IPN is invalid');
        }

        $tx = $api_admin->invoice_transaction_get(['id' => $id]);

        // The invoice binding arrives through a buyer-editable callback URL,
        // so authenticate it before anything below trusts it. Runs outside
        // the test-mode bypass above: the binding must hold in every environment.
        $verifiedInvoiceId = $this->verifyCallbackBinding($data, $gateway_id, $tx['invoice_id'] ?? null, $id);

        $ipn = $post;

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

        // Set the invoice ID if it's not set. The value was authenticated above.
        if (!$tx['invoice_id']) {
            if ($verifiedInvoiceId === null) {
                throw new Payment_Exception('PayPal transaction is not associated with an invoice');
            }
            $tx['invoice_id'] = $verifiedInvoiceId;
            $api_admin->invoice_transaction_update(['id' => $id, 'invoice_id' => $verifiedInvoiceId]);
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
                    if (isset($tx['status'], $tx['txn_status']) && $tx['status'] === Transaction::STATUS_PROCESSED && $tx['txn_status'] === 'Completed') {
                        $d = [
                            'id' => $id,
                            'error' => '',
                            'error_code' => null,
                            'status' => Transaction::STATUS_PROCESSED,
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
                } elseif (($ipn['payment_status'] ?? '') === 'Refunded') {
                    break;
                } else {
                    $api_admin->invoice_transaction_update([
                        'id' => $id,
                        'txn_status' => (string) ($ipn['payment_status'] ?? ''),
                        'status' => Transaction::STATUS_RECEIVED,
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
                // Echoed references describe the original invoice, so check
                // them before the renewal reassignment below changes it.
                if ($invoiceDbModel instanceof Invoice) {
                    $this->verifyIpnEcho($ipn, $invoiceDbModel);
                }

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
                    // A smaller payment joins what the invoice already received instead of being refused.
                    $expected = $invoiceService->getBalanceDue($invoiceDbModel);
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
                    if (!$invoiceDbModel->isIssued()) {
                        $invoiceService->issueInvoice($invoiceDbModel, ['use_credits' => false]);
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
            // A partial refund must not auto-refund the whole invoice — leave
            // it for staff instead. Only skip when we positively know it is
            // partial; unknown totals keep the old behavior.
            $refundAmount = abs((float) ($ipn['mc_gross'] ?? 0));
            $invoiceTotal = null;

            try {
                $invoiceModel = $this->di['em']->getRepository(Invoice::class)->find((int) $invoice['id']);
                if ($invoiceModel instanceof Invoice) {
                    $invoiceTotal = (float) $this->di['mod_service']('Invoice')->getTotalWithTax($invoiceModel);
                }
            } catch (Throwable $e) {
                $this->di['logger']->warning('Could not determine invoice total for PayPal refund on invoice #{id}: {message}', ['id' => $invoice['id'], 'message' => $e->getMessage()]);
            }

            if ($refundAmount > 0 && $invoiceTotal !== null && abs($invoiceTotal - $refundAmount) > 0.01) {
                $this->di['logger']->warning('Partial PayPal refund {amount} for invoice #{id} (total {total}) left for staff instead of refunding the whole invoice', ['amount' => $refundAmount, 'id' => $invoice['id'], 'total' => $invoiceTotal]);
            } else {
                $refd = [
                    'id' => $invoice['id'],
                    'note' => 'PayPal refund ' . ($ipn['parent_txn_id'] ?? ''),
                ];
                $api_admin->invoice_refund($refd);
            }
        }

        if ($awaitingCompletion) {
            return;
        }

        $d = [
            'id' => $id,
            'error' => '',
            'error_code' => null,
            'status' => Transaction::STATUS_PROCESSED,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $api_admin->invoice_transaction_update($d);
    }

    /**
     * Resolve the invoice id from callback data, authenticating the binding.
     * Used by flows that read the invoice from the IPN before processing it.
     *
     * @param mixed $data raw callback input, which callers may not have validated yet
     */
    public function getInvoiceId($data): ?int
    {
        if (!is_array($data)) {
            return null;
        }
        $invoiceId = $data['invoice_id'] ?? $data['get']['invoice_id'] ?? null;
        if (empty($invoiceId)) {
            return null;
        }

        $gatewayId = $this->config['gateway_id'] ?? null;
        if ($gatewayId === null || $gatewayId === '') {
            return (int) $invoiceId;
        }

        return $this->verifyCallbackBinding($data, (int) $gatewayId, $invoiceId);
    }

    /**
     * Authenticate the gateway/invoice binding carried by the callback URL.
     * Returns the verified invoice id, or null when no invoice was supplied.
     *
     * Unsigned callbacks predate signing and are honored only for a stored
     * subscription on the same invoice, or for a notification continuing a
     * previously recorded payment for the same invoice (delayed completions
     * and refunds for pre-upgrade payments), so existing activity keeps
     * working: a fresh payment has no earlier transaction row to match, and
     * a tampered invoice id never matches the stored row.
     */
    private function verifyCallbackBinding(array $data, int $gatewayId, mixed $boundInvoiceId, ?int $currentTxId = null): ?int
    {
        $get = $data['get'] ?? [];
        $invoiceId = $boundInvoiceId ?: ($get['invoice_id'] ?? null);
        if (empty($invoiceId)) {
            return null;
        }
        $invoiceId = (int) $invoiceId;

        if (FOSSBilling\Core\Security\Credential::verifyCallbackSignature($gatewayId, $invoiceId, $get['sig'] ?? null)) {
            return $invoiceId;
        }

        $post = $data['post'] ?? [];
        $subscrId = (string) ($post['subscr_id'] ?? $post['recurring_payment_id'] ?? '');
        if ($subscrId !== '') {
            $storedSubscription = $this->di['em']->getRepository(Box\Mod\Invoice\Entity\Subscription::class)->findOneBy(['sid' => $subscrId]);
            if ($storedSubscription instanceof Box\Mod\Invoice\Entity\Subscription
                && $storedSubscription->getRelType() === 'invoice'
                && $storedSubscription->getRelId() !== null
                && $storedSubscription->getRelId() === $invoiceId) {
                $this->di['logger']->info('Accepted unsigned PayPal callback for stored subscription ' . $subscrId . ' on invoice ' . $invoiceId);

                return $invoiceId;
            }
        }

        // A verified IPN names PayPal-grounded transaction ids, so an
        // earlier transaction row for the same gateway payment on the same
        // invoice proves this notification continues pre-upgrade activity
        // rather than starting a fresh (unsigned) payment.
        if ($currentTxId !== null) {
            foreach ([$post['txn_id'] ?? null, $post['parent_txn_id'] ?? null] as $candidateTxnId) {
                if (!is_string($candidateTxnId) || $candidateTxnId === '') {
                    continue;
                }
                $earlier = $this->di['em']->getRepository(Transaction::class)->findActiveByTxnIdAndGatewayId($candidateTxnId, $gatewayId, $currentTxId);
                $earlierInvoice = $earlier?->getInvoice();
                if ($earlierInvoice instanceof Invoice && (int) $earlierInvoice->getId() === $invoiceId) {
                    $this->di['logger']->info('Accepted unsigned PayPal callback continuing transaction ' . $candidateTxnId . ' on invoice ' . $invoiceId);

                    return $invoiceId;
                }
            }
        }

        throw new Payment_Exception('PayPal callback signature is invalid');
    }

    /**
     * Cross-check PayPal's echoed invoice references against the invoice
     * about to be credited (defense in depth behind the callback signature).
     * Only enforced when PayPal echoes the field; several notification
     * types omit these references, and that must never fail a payment.
     *
     * item_number is frozen at signup while the invoice number can change
     * (renumbering, issuing), so a stale echo only warns; the stable
     * invoice id below remains enforced.
     */
    private function verifyIpnEcho(array $ipn, Invoice $invoice): void
    {
        $echoedNr = trim((string) ($ipn['item_number'] ?? ''));
        if ($echoedNr !== '') {
            $expectedNr = trim((string) $invoice->getNr());
            if ($expectedNr !== '' && $echoedNr !== $expectedNr) {
                $this->di['logger']->warning('PayPal item_number ' . $echoedNr . ' does not match invoice ' . $invoice->getId() . ' (expected ' . $expectedNr . '); proceeding with payment');
            }
        }

        $echoedInvoice = trim((string) ($ipn['invoice'] ?? ''));
        if ($echoedInvoice !== '' && (int) $echoedInvoice !== (int) $invoice->getId()) {
            throw new Payment_Exception('PayPal invoice reference does not match invoice ' . $invoice->getId());
        }
    }

    /**
     * Append a binding signature to a callback URL. Skipped when the adapter
     * was built without a gateway id (never the case in production).
     */
    private function signNotifyUrl(string $url, string|int $invoiceId): string
    {
        $gatewayId = $this->config['gateway_id'] ?? null;
        if ($gatewayId === null || $gatewayId === '') {
            return $url;
        }

        $sig = FOSSBilling\Core\Security\Credential::signCallbackParams((int) $gatewayId, (int) $invoiceId);
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . 'sig=' . urlencode($sig);
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

    private function _isIpnValid(array $data): bool
    {
        // use http_raw_post_data instead of post due to encoding
        parse_str((string) $data['http_raw_post_data'], $post);
        $req = 'cmd=_notify-validate';
        foreach ($post as $key => $value) {
            if (is_array($value)) {
                return false;
            }

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
        // `transaction` is a reserved word: quote it portably, or the lookup is
        // a syntax error on SQLite and every IPN looks new.
        $table = $this->di['em']->getConnection()->quoteSingleIdentifier('transaction');
        $sql = "SELECT id
                FROM {$table}
                WHERE txn_id = :transaction_id
                  AND txn_status = :transaction_status
                  AND type = :transaction_type
                  AND amount = :transaction_amount
                LIMIT 2";

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
        $data['notify_url'] = $this->signNotifyUrl($this->config['notify_url'], $invoice['id']);
        $data['business'] = $this->config['email'];

        $data['cmd'] = '_xclick-subscriptions';
        $data['rm'] = '1';

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
        $data['rm'] = '1';
        $data['return'] = $this->config['thankyou_url'];
        $data['cancel_return'] = $this->config['cancel_url'];
        $data['notify_url'] = $this->signNotifyUrl($this->config['notify_url'], $invoice['id']);
        $data['business'] = $this->config['email'];
        $data['cmd'] = '_xclick';
        $data['amount'] = $this->moneyFormat($invoice['subtotal'], $invoice['currency']);
        $data['tax'] = $this->moneyFormat($invoice['tax'], $invoice['currency']);
        // A part-paid invoice asks for its balance due.
        if (($invoice['amount_paid'] ?? 0) > 0 && ($invoice['status'] ?? null) === Invoice::STATUS_UNPAID) {
            $data['amount'] = $this->moneyFormat($invoice['balance_due'], $invoice['currency']);
            $data['tax'] = $this->moneyFormat(0, $invoice['currency']);
        }
        $data['bn'] = 'FOSSBilling_SP';
        $data['charset'] = 'utf-8';

        return $data;
    }

    /** Whether the REST API keys are set: without them there is nothing to vault, refund, cancel or total. */
    public function hasApiKeys(): bool
    {
        return $this->restKeys() !== null;
    }

    /** Checks the REST API keys when they are set (on saving the gateway). */
    public function testConnection(): void
    {
        if ($this->restKeys() !== null) {
            $this->restToken();
        }
    }

    public function cancelSubscription(string $subscriptionId): void
    {
        if ($this->restKeys() === null) {
            throw new FOSSBilling\Core\Exception\InformationException('Cancel subscription :id in PayPal: FOSSBilling marks it canceled when PayPal reports it.', [':id' => $subscriptionId]);
        }
        [$status, $body] = $this->rest('POST', '/v1/billing/subscriptions/' . rawurlencode($subscriptionId) . '/cancel', ['reason' => 'Cancelled in FOSSBilling']);
        // Already cancelled or ended at PayPal (by the client, or by an earlier try) is what was asked.
        if ($status === 422 && in_array($this->rest('GET', '/v1/billing/subscriptions/' . rawurlencode($subscriptionId))[1]['status'] ?? '', ['CANCELLED', 'EXPIRED'], true)) {
            return;
        }
        if ($status !== 204) {
            throw new FOSSBilling\Core\Exception\InformationException('PayPal did not cancel subscription :id: :error', [':id' => $subscriptionId, ':error' => $this->restError($body, $status)]);
        }
    }

    /** Whether PayPal finds the subscription and it can still be cancelled (a check before a plan change relies on it). */
    public function canCancelSubscription(string $subscriptionId): bool
    {
        try {
            [$status, $body] = $this->rest('GET', '/v1/billing/subscriptions/' . rawurlencode($subscriptionId));
        } catch (Throwable) {
            return false;
        }

        return $status === 200 && in_array($body['status'] ?? '', ['APPROVAL_PENDING', 'APPROVED', 'ACTIVE', 'SUSPENDED'], true);
    }

    /** PayPal charges a subscription again only when its period ends, so cancelling it now ends it there. */
    public function cancelSubscriptionAtPeriodEnd(string $subscriptionId): void
    {
        $this->cancelSubscription($subscriptionId);
    }

    /** Refunds $amount of a PayPal payment (its transaction ID) and returns PayPal's refund ID. */
    public function refundPayment(string $txnId, float $amount, string $currency, array $metadata = []): string
    {
        [$status, $body] = $this->rest('POST', '/v2/payments/captures/' . rawurlencode($txnId) . '/refund', [
            'amount' => ['value' => $this->moneyFormat($amount, $currency), 'currency_code' => $currency],
        ]);
        if ($status !== 201 || empty($body['id'])) {
            throw new FOSSBilling\Core\Exception\InformationException('PayPal did not refund payment :id: :error', [':id' => $txnId, ':error' => $this->restError($body, $status)]);
        }

        return (string) $body['id'];
    }

    /** [client ID, secret] of the current mode, or null. */
    private function restKeys(): ?array
    {
        $prefix = empty($this->config['test_mode']) ? '' : 'test_';
        $id = trim((string) ($this->config[$prefix . 'api_client_id'] ?? ''));
        $secret = trim((string) ($this->config[$prefix . 'api_secret'] ?? ''));

        return $id !== '' && $secret !== '' ? [$id, $secret] : null;
    }

    private function restUrl(string $path): string
    {
        return (empty($this->config['test_mode']) ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com') . $path;
    }

    private function restToken(): string
    {
        if ($this->restToken === null) {
            [$id, $secret] = $this->restKeys() ?? throw new FOSSBilling\Core\Exception\InformationException('Enter the PayPal REST API client ID and secret first.');
            $response = $this->di['http_client']->request('POST', $this->restUrl('/v1/oauth2/token'), [
                'auth_basic' => [$id, $secret],
                'body' => ['grant_type' => 'client_credentials'],
                'timeout' => 30,
            ]);
            $body = $response->toArray(false);
            if ($response->getStatusCode() !== 200 || empty($body['access_token'])) {
                throw new FOSSBilling\Core\Exception\InformationException('PayPal did not accept the REST API keys: :error', [':error' => (string) ($body['error_description'] ?? $response->getStatusCode())]);
            }
            $this->restToken = (string) $body['access_token'];
        }

        return $this->restToken;
    }

    /** [HTTP status, decoded body] of a REST call; network errors throw. */
    private function rest(string $method, string $path, ?array $json = null, array $headers = []): array
    {
        // PayPal refuses a POST without a JSON content type, also one without a body (an order capture).
        $options = ['auth_bearer' => $this->restToken(), 'timeout' => 60, 'headers' => $headers + ['Content-Type' => 'application/json']];
        if ($json !== null) {
            $options['json'] = $json;
        }
        $response = $this->di['http_client']->request($method, $this->restUrl($path), $options);
        $status = $response->getStatusCode();

        return [$status, $status === 204 ? [] : $response->toArray(false)];
    }

    private function restError(array $body, int $status): string
    {
        $detail = $body['details'][0]['description'] ?? $body['details'][0]['issue'] ?? null;

        return trim(($body['message'] ?? $body['name'] ?? 'HTTP ' . $status) . ($detail ? ' (' . $detail . ')' : ''));
    }

    // Saved PayPal accounts (Invoice/ServiceSavedCards.php) through the Vault of the REST API. A client
    // saves their account on the Payment methods page (a setup token they approve at PayPal) or with "Pay and save my
    // PayPal account" (an order stored in the vault when it is paid); the cron then charges it as a saved card.

    /** The PayPal account's balances through the REST API: [{currency, amount}]. */
    public function balances(): array
    {
        if (!$this->hasApiKeys()) {
            throw new FOSSBilling\Core\Exception\InformationException('Add the REST API keys to PayPal to see its balance.');
        }
        [$status, $body] = $this->rest('GET', '/v1/reporting/balances');
        if ($status !== 200) {
            throw new FOSSBilling\Core\Exception\InformationException('PayPal did not give the balance: :error', [':error' => $this->restError($body, $status)]);
        }

        return array_map(static fn (array $balance): array => [
            'currency' => (string) ($balance['currency'] ?? ''),
            'amount' => (float) ($balance['total_balance']['value'] ?? 0),
        ], $body['balances'] ?? []);
    }

    /** Whether clients can save their PayPal account here: the setting is on and the REST API keys are set. */
    public function canVault(): bool
    {
        return (string) ($this->config['vault'] ?? '') === '1' && $this->hasApiKeys();
    }

    public function gatewayId(): int
    {
        return (int) ($this->config['gateway_id'] ?? 0);
    }

    public function testMode(): bool
    {
        return !empty($this->config['test_mode']);
    }

    /** The client's saved PayPal account ({payment_method, card, auto_pay_at}), or null. */
    public function savedCard(int $clientId): ?array
    {
        return $this->di['mod_service']('Invoice', 'SavedCards')->saved($this->gatewayId(), $this->testMode(), $clientId);
    }

    /** Starts saving a PayPal account on the Payment methods page: {id, url}, the setup token and its approval page. */
    public function vaultSetup(string $returnUrl, string $cancelUrl): array
    {
        [$status, $body] = $this->rest('POST', '/v3/vault/setup-tokens', ['payment_source' => ['paypal' => [
            'usage_type' => 'MERCHANT',
            'experience_context' => ['return_url' => $returnUrl, 'cancel_url' => $cancelUrl, 'shipping_preference' => 'NO_SHIPPING'],
        ]]]);
        $url = $this->restLink($body, 'approve');
        if (!in_array($status, [200, 201], true) || empty($body['id']) || $url === null) {
            throw new FOSSBilling\Core\Exception\InformationException('PayPal did not start saving your account: :error', [':error' => $this->restError($body, $status)]);
        }

        return ['id' => (string) $body['id'], 'url' => $url];
    }

    /** After the client approved a setup token at PayPal: keeps that account as the one that pays their invoices. */
    public function vaultSaved(int $clientId, string $setupTokenId): void
    {
        [$status, $body] = $this->rest('POST', '/v3/vault/payment-tokens', ['payment_source' => ['token' => ['id' => $setupTokenId, 'type' => 'SETUP_TOKEN']]]);
        if (!in_array($status, [200, 201], true) || empty($body['id'])) {
            throw new FOSSBilling\Core\Exception\InformationException('PayPal did not save your account: :error', [':error' => $this->restError($body, $status)]);
        }
        $this->rememberAccount($clientId, (string) $body['id'], (string) ($body['customer']['id'] ?? ''), (string) ($body['payment_source']['paypal']['email_address'] ?? ''));
    }

    /** Removes the client's saved PayPal account, here and at PayPal. */
    public function vaultRemove(int $clientId): void
    {
        $saved = $this->savedCard($clientId);
        if ($saved !== null) {
            $this->deleteToken($saved['payment_method']);
            $this->di['mod_service']('Invoice', 'SavedCards')->forget($this->gatewayId(), $this->testMode(), $saved['payment_method']);
        }
    }

    /** "Pay and save my PayPal account": an order for the invoice's balance, stored in the vault when paid: {id, url}. */
    public function vaultOrder(Invoice $invoice, string $returnUrl, string $cancelUrl): array
    {
        [$status, $body] = $this->rest('POST', '/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [$this->purchaseUnit($invoice)],
            'payment_source' => ['paypal' => [
                'attributes' => ['vault' => ['store_in_vault' => 'ON_SUCCESS', 'usage_type' => 'MERCHANT']],
                'experience_context' => ['return_url' => $returnUrl, 'cancel_url' => $cancelUrl, 'shipping_preference' => 'NO_SHIPPING', 'user_action' => 'PAY_NOW'],
            ]],
        ]);
        $url = $this->restLink($body, 'payer-action');
        if (!in_array($status, [200, 201], true) || empty($body['id']) || $url === null) {
            throw new FOSSBilling\Core\Exception\InformationException('PayPal did not start the payment: :error', [':error' => $this->restError($body, $status)]);
        }

        return ['id' => (string) $body['id'], 'url' => $url];
    }

    /** Takes the payment of an approved "Pay and save" order and keeps its PayPal account: the capture ID. */
    public function vaultCapture(Invoice $invoice, string $orderId): string
    {
        $path = '/v2/checkout/orders/' . rawurlencode($orderId);
        // The full reply: the short one leaves out the purchase unit's custom_id.
        [$status, $body] = $this->rest('POST', $path . '/capture', null, ['PayPal-Request-Id' => 'fb-capture-' . $orderId, 'Prefer' => 'return=representation']);
        // Captured already (the client came back twice): the order says how.
        if ($status === 422) {
            [$status, $body] = $this->rest('GET', $path);
        }
        $unit = $body['purchase_units'][0] ?? [];
        $capture = $unit['payments']['captures'][0] ?? [];
        if (!in_array($status, [200, 201], true) || empty($capture['id']) || (string) ($unit['custom_id'] ?? '') !== 'fb-invoice-' . $invoice->getId()) {
            throw new FOSSBilling\Core\Exception\InformationException('PayPal did not take the payment: :error', [':error' => $this->restError($body, $status)]);
        }
        $vault = $body['payment_source']['paypal']['attributes']['vault'] ?? [];
        if (!empty($vault['id'])) {
            $this->rememberAccount((int) $invoice->getClientId(), (string) $vault['id'], (string) ($vault['customer']['id'] ?? ''), (string) ($body['payment_source']['paypal']['email_address'] ?? ''));
        }

        return (string) $capture['id'];
    }

    /**
     * Charges the client's saved PayPal account for an invoice's balance (the cron or staff). $attempt names the try,
     * so each one is its own idempotent payment. Returns the capture ID; PayPal's refusal throws InformationException.
     */
    public function chargeSavedCard(Invoice $invoice, string $attempt): string
    {
        $saved = $this->savedCard((int) $invoice->getClientId()) ?? throw new FOSSBilling\Core\Exception\InformationException('The client has no saved PayPal account.');
        [$status, $body] = $this->rest('POST', '/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [$this->purchaseUnit($invoice)],
            'payment_source' => ['paypal' => ['vault_id' => $saved['payment_method']]],
        ], ['PayPal-Request-Id' => 'fb-invoice-' . $invoice->getId() . '-' . $attempt]);
        $capture = $body['purchase_units'][0]['payments']['captures'][0] ?? [];
        if (in_array($status, [200, 201], true) && in_array($capture['status'] ?? '', ['DECLINED', 'FAILED'], true)) {
            throw new FOSSBilling\Core\Exception\InformationException('PayPal refused the payment: :error', [':error' => $capture['status']]);
        }
        if (in_array($status, [200, 201], true) && !empty($capture['id'])) {
            return (string) $capture['id'];
        }
        if ($status >= 400 && $status < 500) {
            throw new FOSSBilling\Core\Exception\InformationException('PayPal refused the payment: :error', [':error' => $this->restError($body, $status)]);
        }

        throw new Payment_Exception('PayPal answered HTTP :status to the payment of invoice #:id', [':status' => $status, ':id' => $invoice->getId()]);
    }

    /** A REST payment (capture) as PayPal reports it: recorded on its transaction and applied to its invoice once. */
    public function processRestCapture($api_admin, int $id, string $captureId): void
    {
        [$status, $capture] = $this->rest('GET', '/v2/payments/captures/' . rawurlencode($captureId));
        if ($status !== 200 || empty($capture['id'])) {
            throw new Payment_Exception('PayPal payment :id was not found', [':id' => $captureId]);
        }
        // Only orders made here name an invoice: a payment of another shop on the same PayPal account pays nothing.
        if (preg_match('/^fb-invoice-(\d+)$/', (string) ($capture['custom_id'] ?? ''), $match) !== 1) {
            throw new Payment_Exception('PayPal payment :id is not for a FOSSBilling invoice', [':id' => $captureId]);
        }
        $invoice = $this->di['em']->getRepository(Invoice::class)->find((int) $match[1]);
        if (!$invoice instanceof Invoice) {
            throw new Payment_Exception('Invoice not found');
        }
        $amount = (float) ($capture['amount']['value'] ?? 0);
        $currency = (string) ($capture['amount']['currency_code'] ?? '');
        $completed = ($capture['status'] ?? '') === 'COMPLETED';
        $api_admin->invoice_transaction_update([
            'id' => $id, 'invoice_id' => $invoice->getId(), 'txn_id' => (string) $capture['id'], 'type' => 'paypal_rest',
            'txn_status' => $completed ? 'Completed' : ucfirst(strtolower((string) ($capture['status'] ?? ''))), 'amount' => $amount, 'currency' => $currency,
        ]);
        if (!$completed) {
            $api_admin->invoice_transaction_update(['id' => $id, 'status' => Transaction::STATUS_RECEIVED, 'error' => sprintf('PayPal payment not completed: %s', (string) ($capture['status'] ?? 'unknown')), 'updated_at' => date('Y-m-d H:i:s')]);

            return;
        }
        // This transaction too: "Process again" on a payment already applied must not credit it again.
        $done = $this->di['em']->getRepository(Transaction::class)->findOneBy(['txnId' => $capture['id'], 'gateway' => $this->di['em']->getReference(PayGateway::class, $this->gatewayId()), 'status' => Transaction::STATUS_PROCESSED]);
        if ($done instanceof Transaction) {
            throw new Payment_Exception('Cannot process duplicate PayPal payment');
        }
        $this->validateCurrency($currency, $invoice->getCurrency());
        $this->applyPayment($api_admin, (int) $invoice->getClientId(), $amount, (string) $capture['id'], $invoice);
        $api_admin->invoice_transaction_update(['id' => $id, 'error' => '', 'error_code' => null, 'status' => Transaction::STATUS_PROCESSED, 'updated_at' => date('Y-m-d H:i:s')]);
    }

    /** The invoice's balance as an order's purchase unit; custom_id names the invoice (processRestCapture()). */
    private function purchaseUnit(Invoice $invoice): array
    {
        $invoiceService = $this->di['mod_service']('Invoice');

        return [
            'custom_id' => 'fb-invoice-' . $invoice->getId(),
            'description' => mb_substr($this->getInvoiceTitle($invoiceService->toApiArray($invoice)), 0, 127),
            'amount' => ['currency_code' => (string) $invoice->getCurrency(), 'value' => $this->moneyFormat($invoiceService->getBalanceDue($invoice), (string) $invoice->getCurrency())],
        ];
    }

    /** Keeps a vaulted PayPal account as the client's (the newest replaces the one saved before). */
    private function rememberAccount(int $clientId, string $tokenId, string $customerId, string $email): void
    {
        $old = $this->savedCard($clientId)['payment_method'] ?? null;
        $this->di['mod_service']('Invoice', 'SavedCards')->remember($this->gatewayId(), $this->testMode(), $clientId, $customerId, $tokenId, trim('PayPal ' . $email));
        if ($old !== null && $old !== $tokenId) {
            $this->deleteToken($old);
        }
    }

    private function deleteToken(string $tokenId): void
    {
        try {
            $this->rest('DELETE', '/v3/vault/payment-tokens/' . rawurlencode($tokenId));
        } catch (Throwable $e) {
            $this->di['logger']->warning('PayPal: the saved account {token} was not removed at PayPal: {message}', ['token' => $tokenId, 'message' => $e->getMessage()]);
        }
    }

    private function restLink(array $body, string $rel): ?string
    {
        foreach ($body['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === $rel && is_string($link['href'] ?? null)) {
                return $link['href'];
            }
        }

        return null;
    }

    /** The saved method's gateway takes the payment, with its fee (see ServiceGatewayFees). */
    private function applyPayment($api_admin, int $clientId, float $amount, string $txnId, Invoice $invoice): void
    {
        $invoiceService = $this->di['mod_service']('Invoice');
        $invoiceService->validatePaymentAmount($amount, $invoiceService->getBalanceDue($invoice));
        $api_admin->client_balance_add_funds(['id' => $clientId, 'amount' => $amount, 'description' => 'PayPal transaction ' . $txnId, 'type' => 'PayPal', 'rel_id' => $txnId]);
        if ($invoiceService->isInvoiceTypeDeposit($invoice)) {
            $invoiceService->markAsPaid($invoice);
        } else {
            if (!$invoice->isIssued()) {
                $invoiceService->issueInvoice($invoice, ['use_credits' => false]);
            }
            $api_admin->invoice_pay_with_credits(['id' => $invoice->getId()]);
        }
    }
}
