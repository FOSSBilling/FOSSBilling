<?php

declare(strict_types=1);

// SPDX-License-Identifier: Apache-2.0

use Box\Mod\Client\Entity\Client;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\Transaction;
use Box\Mod\Invoice\Exception\RetryableCallbackException;
use FOSSBilling\Core\Container\InjectionAwareInterface;
use FOSSBilling\Core\Payment\JsonWebhookAuthenticatorInterface;
use Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;

/** One-time native hosted checkout; only a signed confirmation can settle an invoice. */
class Payment_Adapter_Yolfi extends Payment_AdapterAbstract implements InjectionAwareInterface, JsonWebhookAuthenticatorInterface
{
    private const API_URL = 'https://app.yolfi.com/api/checkout-sessions';
    private const CURRENCIES = ['USD', 'EUR', 'CNY', 'RUB', 'INR', 'TRY', 'KRW', 'JPY'];

    protected ?Pimple\Container $di = null;

    // Like the native providers, construction also serves settings-only validation.
    public function __construct(array $config)
    {
        $this->_config = $config;
        $this->init();
    }

    public function init(): void
    {
        foreach (['api_key', 'webhook_secret'] as $key) {
            $value = $this->getParam($key);
            if (!is_string($value) || trim($value) === '') {
                throw new Payment_Exception('Configure the Yolfi API key and endpoint signing secret.');
            }
        }
        if ($this->getParam('test_mode')) {
            throw new Payment_Exception('Yolfi test mode is not supported by this adapter.');
        }
    }

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Pimple\Container
    {
        return $this->di;
    }

    public static function getConfig(): array
    {
        $options = ['required' => true];
        $options['secret'] = true;
        $form = [];
        $form['api_key'] = ['password', $options + ['label' => 'Yolfi API key']];
        $form['webhook_secret'] = ['password', $options + ['label' => 'Yolfi webhook endpoint signing secret']];
        $form['checkout_revision'] = ['text', ['label' => 'Checkout revision (default 1; change only after reconciling expired attempts)', 'required' => false]];

        return [
            'supports_one_time_payments' => true,
            'supports_subscriptions' => false,
            'can_load_in_iframe' => false,
            'description' => 'Yolfi one-time hosted checkout. Configure a native signed webhook; redirects do not confirm payment.',
            'form' => $form,
        ];
    }

    public function getType(): string
    {
        return self::TYPE_HTML;
    }

    public function getHtml($api_admin, int $invoice_id, bool $subscription): string
    {
        if ($subscription) {
            throw new Payment_Exception('Yolfi subscriptions are not supported.');
        }
        // Only these URLs are sent to checkout; callback identity is checked separately.
        foreach (['return_url', 'cancel_url'] as $key) {
            $url = $this->getParam($key);
            $parts = is_string($url) ? parse_url($url) : false;
            if (!is_string($url) || !filter_var($url, FILTER_VALIDATE_URL)
                || !is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
                || isset($parts['user']) || isset($parts['pass'])) {
                throw new Payment_Exception('Invalid Yolfi checkout return or cancel URL.');
            }
        }
        $invoice = $this->di['em']->getRepository(Invoice::class)->find($invoice_id);
        if (!$invoice instanceof Invoice) {
            throw new Payment_Exception('Invoice not found.');
        }
        $this->assertPayable($invoice);
        $payload = [
            'amount' => $this->invoiceAmount($invoice),
            'currency' => $this->invoiceCurrency($invoice),
            'merchantOrderId' => $this->orderReference($invoice),
            'successUrl' => (string) $this->getParam('return_url'),
            'cancelUrl' => (string) $this->getParam('cancel_url'),
            'expiresInMinutes' => 60,
        ];
        $revision = $this->getParam('checkout_revision') ?: '1';
        if (!is_string($revision) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $revision)) {
            throw new Payment_Exception('Invalid Yolfi checkout revision.');
        }
        // Identical server-calculated requests keep their key, including after a timeout.
        $key = hash('sha256', json_encode([$revision, $payload], JSON_THROW_ON_ERROR));
        $session = $this->requestSession('POST', '', $payload, $key);
        $this->validateSnapshot($session, $invoice);
        if (!in_array($session['status'] ?? null, ['OPEN', 'PROCESSING', 'COMPLETED'], true)) {
            throw new Payment_Exception('Yolfi checkout expired. Reconcile the attempt before changing the checkout revision.');
        }
        $url = $session['url'] ?? null;
        $parts = is_string($url) ? parse_url($url) : false;
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
            || ($parts['host'] ?? '') !== 'pay.yolfi.com'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            throw new Payment_Exception('Yolfi returned an invalid hosted checkout URL.');
        }

        return '<a class="btn btn-primary" rel="noreferrer" href="'
            . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Pay with Yolfi</a>';
    }

    public function authenticateJsonWebhook(array $data): void
    {
        $this->verifiedEvent($data);
    }

    public function processTransaction($api_admin, int $id, array $data, int $gateway_id): void
    {
        $event = $this->verifiedEvent($data);
        $tx = $this->di['em']->getRepository(Transaction::class)->find($id);
        if (!$tx instanceof Transaction || $gateway_id !== $this->gatewayId()
            || $tx->getGateway()?->getId() !== $gateway_id) {
            throw new Payment_Exception('Yolfi transaction gateway mismatch.');
        }
        if ($event['type'] !== 'payment.confirmed') {
            // Created/overdue/cancelled events are acknowledged without settlement.
            $tx->setTxnId($event['id']);
            $tx->setTxnStatus($event['type']);
            $tx->setStatus(Transaction::STATUS_PROCESSED);
            $tx->setUpdatedAt(new DateTimeImmutable());
            $this->di['em']->flush();
            $this->setOutput('OK');

            return;
        }
        $payment = $event['data'];
        if (($payment['status'] ?? null) !== 'confirmed'
            || ($payment['paymentType'] ?? null) !== 'ONE_TIME'
            || isset($payment['subscriptionId']) || isset($payment['paylinkId'])
            || !is_string($payment['invoiceId'] ?? null)
            || !preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/Di', $payment['invoiceId'])
            || !$this->validSessionId($payment['checkoutSessionId'] ?? null)) {
            throw new Payment_Exception('Invalid Yolfi one-time payment confirmation.');
        }
        $session = $this->requestSession('GET', '/' . rawurlencode($payment['checkoutSessionId']));
        if (($session['id'] ?? null) !== $payment['checkoutSessionId']
            || ($session['status'] ?? null) !== 'COMPLETED') {
            throw new Payment_Exception('Yolfi checkout session mismatch.');
        }
        $reference = $session['merchantOrderId'] ?? null;
        if (!is_string($reference) || !preg_match('/^fossbilling:([1-9][0-9]*):([1-9][0-9]*):([A-Za-z0-9_-]{1,128})$/D', $reference, $match)
            || $match[1] !== (string) $gateway_id
            || (isset($payment['merchantOrderId']) && $payment['merchantOrderId'] !== $reference)) {
            throw new Payment_Exception('Yolfi order reference mismatch.');
        }
        $invoiceId = filter_var($match[2], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $invoice = $invoiceId === false ? null : $this->di['em']->getRepository(Invoice::class)->find($invoiceId);
        if (!$invoice instanceof Invoice || ($tx->getInvoice() !== null && $tx->getInvoice()->getId() !== $invoice->getId())) {
            throw new Payment_Exception('Yolfi invoice reference mismatch.');
        }
        $this->validateSnapshot($session, $invoice);
        if (isset($payment['currency']) && $payment['currency'] !== $session['currency']) {
            throw new Payment_Exception('Yolfi confirmation currency mismatch.');
        }
        $client = $this->di['em']->getRepository(Client::class)->find((int) $invoice->getClientId());
        if (!$client instanceof Client || $client->getCurrency() !== $session['currency']) {
            throw new Payment_Exception('Yolfi invoice client currency mismatch.');
        }
        $tx->setInvoice($invoice);
        $tx->setTxnId($event['id']);
        $tx->setTxnStatus('confirmed');
        // Webhook amount is a token receivable, NOT the invoice's fiat price.
        // The authenticated checkout snapshot is the immutable pricing basis.
        $tx->setAmount($this->decimal($session['amount']));
        $tx->setCurrency($session['currency']);
        $tx->setType('payment');
        $service = $this->di['mod_service']('Invoice');
        $created = $service->recordGatewayPayment(
            $tx,
            $client,
            'yolfi:' . $payment['invoiceId'],
            'Yolfi payment ' . $payment['invoiceId'],
            'transaction',
            (string) $tx->getId(),
            function () use ($invoice, $session): void {
                // Executed inside the existing atomic receipt/client-credit lock.
                // Separate event IDs for one payment still share the invoice receipt.
                $this->di['em']->refresh($invoice);
                $this->assertPayable($invoice);
                $this->validateSnapshot($session, $invoice);
            }
        );
        if (!$created) {
            $this->di['em']->remove($tx);
            $this->di['em']->flush();
        }
        $this->setOutput('OK');
    }

    private function verifiedEvent(array $data): array
    {
        $raw = $data['http_raw_post_data'] ?? null;
        $server = $data['server'] ?? null;
        $signature = is_array($server) ? ($server['HTTP_X_YOLFI_SIGNATURE'] ?? null) : null;
        $decoded = is_string($signature) ? base64_decode($signature, true) : false;
        if (!is_string($raw) || $raw === '' || strlen($raw) > 1048576
            || !is_string($decoded) || strlen($decoded) !== 32
            || !hash_equals(hash_hmac('sha256', $raw, $this->getParam('webhook_secret'), true), $decoded)) {
            throw new Payment_Exception('Invalid Yolfi webhook signature.');
        }
        try {
            $event = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new Payment_Exception('Invalid Yolfi webhook JSON.');
        }
        if (!is_array($event) || !is_string($event['id'] ?? null)
            || !preg_match('/^evt_[A-Za-z0-9_-]{1,128}$/D', $event['id'])
            || !is_string($event['type'] ?? null) || strlen($event['type']) > 100
            || ($event['livemode'] ?? null) !== true || !is_array($event['data'] ?? null)) {
            throw new Payment_Exception('Invalid Yolfi webhook event.');
        }

        return $event;
    }

    private function requestSession(string $method, string $suffix, ?array $payload = null, ?string $key = null): array
    {
        $options = [
            'auth_bearer' => $this->getParam('api_key'),
            'headers' => ['Accept' => 'application/json'],
            'timeout' => 10,
            'max_duration' => 15,
            'max_redirects' => 0,
        ];
        if ($payload !== null) {
            $options['json'] = $payload;
            $options['headers']['Idempotency-Key'] = $key;
        }
        $status = null;
        try {
            $response = $this->getHttpClient()->request($method, self::API_URL . $suffix, $options);
            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                throw new RuntimeException('Non-success response');
            }
            $raw = $response->getContent(false);
            if (strlen($raw) > 1048576) {
                throw new RuntimeException('Response too large');
            }
            $result = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($result) || ($result['success'] ?? null) !== true || !is_array($result['data'] ?? null)) {
                throw new RuntimeException('Invalid response envelope');
            }

            return $result['data'];
        } catch (Throwable $e) {
            // Only an authenticated confirmation GET can enter the host callback retry queue.
            // Do not expose response bodies, credentials, transport text or a previous exception.
            $message = 'Yolfi checkout request failed. Retry the same payment attempt or review its transaction.';
            if ($method === 'GET' && ($e instanceof TimeoutExceptionInterface
                || $status === 429 || ($status >= 500 && $status < 600))) {
                throw new RetryableCallbackException($message);
            }

            throw new Payment_Exception($message);
        }
    }

    private function validateSnapshot(array $session, Invoice $invoice): void
    {
        if (($session['object'] ?? null) !== 'checkout_session' || !$this->validSessionId($session['id'] ?? null)
            || ($session['merchantOrderId'] ?? null) !== $this->orderReference($invoice)
            || ($session['currency'] ?? null) !== $this->invoiceCurrency($invoice)
            || $this->decimal($session['amount'] ?? null) !== $this->invoiceAmount($invoice)) {
            throw new Payment_Exception('Yolfi checkout price or order mismatch.');
        }
    }

    private function validSessionId(mixed $id): bool
    {
        return is_string($id) && preg_match('/^ycs_[A-Za-z0-9_-]{1,128}$/D', $id) === 1;
    }

    private function gatewayId(): int
    {
        $id = filter_var($this->getParam('gateway_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new Payment_Exception('Invalid Yolfi gateway ID.');
        }

        return $id;
    }

    private function orderReference(Invoice $invoice): string
    {
        if (!$invoice->getId() || $invoice->getGateway()?->getId() !== $this->gatewayId()
            || !is_string($invoice->getHash()) || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $invoice->getHash())) {
            throw new Payment_Exception('Invalid Yolfi invoice or gateway reference.');
        }

        return 'fossbilling:' . $this->gatewayId() . ':' . $invoice->getId() . ':' . $invoice->getHash();
    }

    private function assertPayable(Invoice $invoice): void
    {
        $this->orderReference($invoice);
        if ($invoice->getStatus() !== Invoice::STATUS_UNPAID || $this->di['mod_service']('Invoice')->getAmountPaid($invoice) != 0.0) {
            throw new Payment_Exception('Yolfi requires an unpaid invoice without partial payments or applied credit.');
        }
    }

    private function invoiceCurrency(Invoice $invoice): string
    {
        $currency = $invoice->getCurrency();
        if (!in_array($currency, self::CURRENCIES, true)) {
            throw new Payment_Exception('Invoice currency is not supported by Yolfi.');
        }

        return $currency;
    }

    private function invoiceAmount(Invoice $invoice): string
    {
        $total = $this->di['mod_service']('Invoice')->getTotalWithTax($invoice);
        if (!is_finite($total) || $total < 0.01 || $total >= 1000000000000) {
            throw new Payment_Exception('Invalid Yolfi invoice total.');
        }

        return number_format(round($total, 2), 2, '.', '');
    }

    private function decimal(mixed $amount): string
    {
        if (!is_string($amount) || strlen($amount) > 64
            || !preg_match('/^(0|[1-9][0-9]{0,11})(?:\.([0-9]{1,2})0*)?$/D', $amount, $parts)) {
            throw new Payment_Exception('Invalid Yolfi pricing amount.');
        }

        return $parts[1] . '.' . str_pad($parts[2] ?? '', 2, '0');
    }
}
