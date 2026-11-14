<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Invoice\Controller;

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\PendingPayments;
use FOSSBilling\Core\Doctrine\NamedLock;
use FOSSBilling\Core\Exception\InformationException;
use FOSSBilling\Core\System\Config;
use Symfony\Component\HttpFoundation\Response;

class Client implements \FOSSBilling\Core\Container\InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function register(\FOSSBilling\Core\Http\App &$app): void
    {
        $app->get('/invoice', 'get_invoices', [], static::class);
        $app->post('/invoice', 'get_invoices', [], static::class);
        $app->get('/invoice/:hash', 'get_invoice', ['hash' => '[a-z0-9]+'], static::class);
        $app->post('/invoice/:hash', 'get_invoice', ['hash' => '[a-z0-9]+'], static::class);
        $app->get('/invoice/print/:hash', 'get_invoice_print', ['hash' => '[a-z0-9]+'], static::class);
        $app->post('/invoice/print/:hash', 'get_invoice_print', ['hash' => '[a-z0-9]+'], static::class);
        $app->get('/invoice/banklink/:hash/:id', 'get_banklink', ['id' => '[0-9]+', 'hash' => '[a-z0-9]+'], static::class);
        $app->get('/invoice/thank-you/:hash', 'get_thankyoupage', ['hash' => '[a-z0-9]+'], static::class);
        $app->post('/invoice/thank-you/:hash', 'get_thankyoupage', ['hash' => '[a-z0-9]+'], static::class);
        $app->get('/invoice/pdf/:hash', 'get_pdf', ['hash' => '[a-z0-9]+'], static::class);
        $app->get('/payment-methods', 'get_payment_methods', [], static::class);
        $app->get('/payment-methods/paypal-autopay', 'get_autopay_save', [], static::class);
        $app->get('/payment-methods/paypal-autopay/saved', 'get_autopay_saved', [], static::class);
        $app->get('/invoice/paypal-autopay/:hash', 'get_invoice_autopay', ['hash' => '[a-z0-9]+'], static::class);
        $app->get('/invoice/paypal-autopay-paid', 'get_invoice_autopay_paid', [], static::class);
    }

    public function get_invoices(\FOSSBilling\Core\Http\App $app): string
    {
        $this->di['is_client_logged'];

        return $app->render('mod_invoice_index');
    }

    public function get_payment_methods(\FOSSBilling\Core\Http\App $app): string
    {
        $this->di['is_client_logged'];
        $api = $this->di['api_client'];

        return $app->render('mod_invoice_payment_methods', ['cards' => $api->invoice_card_list()]);
    }

    public function get_autopay_save(\FOSSBilling\Core\Http\App $app): Response
    {
        $this->di['is_client_logged'];
        $adapter = $this->di['mod_service']('Invoice', 'SavedCards')->autopay();
        if ($adapter === null || !$adapter->option('save_only')) {
            throw new InformationException('Automatic payments with PayPal are not available.');
        }
        $next = (string) $app->getRequest()->query->get('next', '');
        $next = preg_match('#^order/service/manage/\d+$#', $next) === 1 ? $next : 'payment-methods';
        $setup = $adapter->agreementSetup($this->clientId(), (string) $this->di['url']->link('payment-methods/paypal-autopay/saved'), (string) $this->di['url']->link($next));
        $this->paypalBind($setup['token'], ['client' => $this->clientId(), 'next' => $next]);

        return $app->redirectUrl($setup['url']);
    }

    /** Back from PayPal: keeps the agreement for the client who started it, in this browser. */
    public function get_autopay_saved(\FOSSBilling\Core\Http\App $app): Response
    {
        $this->keepSessionCookie();
        $token = (string) $app->getRequest()->query->get('token');
        $flow = $this->paypalFlow($token);
        $adapter = $this->di['mod_service']('Invoice', 'SavedCards')->autopay();
        if ($flow === null || $adapter === null) {
            throw new InformationException('Automatic payments were not set up. Please try again.');
        }
        $adapter->agreementSaved((int) $flow['client'], $token);
        $this->di['cache']->deleteItem(self::paypalKey($token));

        return $this->movesOn((string) $flow['next']);
    }

    /** Pays an unpaid invoice at PayPal: with a billing agreement for its signed-in owner, or once (?once=1). */
    public function get_invoice_autopay(\FOSSBilling\Core\Http\App $app, string $hash): Response
    {
        $adapter = $this->di['mod_service']('Invoice', 'SavedCards')->autopay()
            ?? throw new InformationException('PayPal is not available.');
        $once = (string) $app->getRequest()->query->get('once', '') === '1';
        if ($once && !$adapter->option('pay_once')) {
            throw new InformationException('PayPal is not available.');
        }
        $invoice = $this->di['em']->getRepository(Invoice::class)->findOneBy(['hash' => $hash, 'status' => Invoice::STATUS_UNPAID]);
        if (!$invoice instanceof Invoice || (!$once && (int) $invoice->getClientId() !== $this->clientId())) {
            return $app->redirect('invoice');
        }
        $checkout = $adapter->checkout($invoice, !$once, (string) $this->di['url']->link('invoice/paypal-autopay-paid'), (string) $this->di['url']->link('invoice/' . $invoice->getHash()));
        $this->paypalBind($checkout['token'], ['invoice' => (int) $invoice->getId()]);

        return $app->redirectUrl($checkout['url']);
    }

    /** Back from PayPal: takes the approved payment, records it and keeps the agreement. */
    public function get_invoice_autopay_paid(\FOSSBilling\Core\Http\App $app): Response
    {
        $this->keepSessionCookie();
        $token = (string) $app->getRequest()->query->get('token');
        $flow = $this->paypalFlow($token);
        $adapter = $this->di['mod_service']('Invoice', 'SavedCards')->autopay();
        if ($flow === null || $adapter === null) {
            return $this->movesOn('invoice');
        }
        $invoice = $this->di['em']->getRepository(Invoice::class)->find((int) $flow['invoice']);
        if (!$invoice instanceof Invoice) {
            $this->di['cache']->deleteItem(self::paypalKey($token));

            return $this->movesOn('invoice');
        }
        // Paid another way meanwhile: the approved checkout is left to expire, never taken as well.
        if ($invoice->getStatus() !== Invoice::STATUS_UNPAID) {
            $this->di['cache']->deleteItem(self::paypalKey($token));

            return $this->movesOn('invoice/' . $invoice->getHash());
        }
        // Checked again and held under the charge lock, so a cron charge of the agreement and this payment never both happen.
        $connection = $this->di['em']->getConnection();
        $lockName = 'fb:card-charge:' . (int) $invoice->getId();
        if (!NamedLock::acquire($connection, $lockName, 10)) {
            return $this->movesOn('invoice/' . $invoice->getHash());
        }

        try {
            $state = $connection->fetchOne('SELECT status FROM invoice WHERE id = ?', [(int) $invoice->getId()]);
            $pending = PendingPayments::forInvoice($connection, (int) $invoice->getId()) !== null;
            if ($state !== Invoice::STATUS_UNPAID || $pending) {
                $txnId = null;
            } else {
                $txnId = $adapter->checkoutPaid($invoice, $token);
                // Taken: the cron never charges the agreement for it, also while PayPal holds the payment.
                $this->di['mod_service']('Invoice', 'SavedCards')->hold((int) $invoice->getId(), 'Payment ' . $txnId . ' is not completed yet.');
            }
        } finally {
            NamedLock::release($connection, $lockName);
        }
        $this->di['cache']->deleteItem(self::paypalKey($token));
        if ($txnId === null) {
            return $this->movesOn('invoice/' . $invoice->getHash());
        }
        $this->di['mod_service']('Invoice', 'Transaction')->createAndProcess([
            'invoice_id' => (int) $invoice->getId(),
            'gateway_id' => $adapter->gatewayId(),
            'source' => 'paypal_autopay_return',
            'get' => ['paypal_txn' => $txnId, 'invoice_id' => (int) $invoice->getId()],
        ]);

        return $this->movesOn('invoice/thank-you/' . $invoice->getHash());
    }

    /** The signed-in client's id. */
    private function clientId(): int
    {
        $this->di['is_client_logged'];

        return (int) $this->di['loggedin_client']->getId();
    }

    private const string PAYPAL_COOKIE = 'paypal_return';
    private const int PAYPAL_SECONDS = 10800;

    private static function paypalKey(string $token): string
    {
        return 'fossbilling_paypal_' . hash('sha256', $token);
    }

    private function paypalBind(string $token, array $flow): void
    {
        $bind = bin2hex(random_bytes(32));
        $item = $this->di['cache']->getItem(self::paypalKey($token));
        $item->set($flow + ['bind' => hash('sha256', $bind)]);
        $item->expiresAfter(self::PAYPAL_SECONDS);
        $this->di['cache']->save($item);
        $secure = (bool) Config::getProperty('security.force_https', true) || $this->di['request']->isSecure();
        $this->di['cookie_queue']->queue(self::PAYPAL_COOKIE, $bind, time() + self::PAYPAL_SECONDS, '/', null, $secure, true, 'Lax');
    }

    /** The flow PayPal's token belongs to when this browser started it, or null. */
    private function paypalFlow(string $token): ?array
    {
        $cookie = (string) $this->di['request']->cookies->get(self::PAYPAL_COOKIE, '');
        $item = $this->di['cache']->getItem(self::paypalKey($token));
        $flow = $item->isHit() ? $item->get() : null;

        return $token !== '' && $cookie !== '' && is_array($flow) && hash_equals((string) $flow['bind'], hash('sha256', $cookie)) ? $flow : null;
    }

    /** Without the session cookie this request has a new, empty session: its cookie must not replace the client's. */
    private function keepSessionCookie(): void
    {
        if (!$this->di['request']->cookies->has(session_name())) {
            header_remove('Set-Cookie');
        }
    }

    /** A page that moves on, not a redirect: a redirect would still come from PayPal and again miss the session cookie. */
    private function movesOn(string $path): Response
    {
        $next = (string) $this->di['url']->link($path);
        $html = htmlspecialchars($next, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $js = json_encode($next, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
        $continue = htmlspecialchars(__trans('Continue'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return new Response(
            '<!DOCTYPE html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=' . $html . '"></head>'
            . '<body><p><a href="' . $html . '">' . $continue . '</a></p>'
            . '<script>location.replace(' . $js . ');</script></body></html>'
        );
    }

    public function get_invoice(\FOSSBilling\Core\Http\App $app, $hash): string|Response
    {
        $data = [
            'hash' => $hash,
        ];
        $invoice = $this->getInvoiceOrRedirect($app, $data);
        if ($invoice instanceof Response) {
            return $invoice;
        }

        return $app->render('mod_invoice_invoice', ['invoice' => $invoice]);
    }

    public function get_invoice_print(\FOSSBilling\Core\Http\App $app, $hash): string|Response
    {
        $data = [
            'hash' => $hash,
        ];
        $invoice = $this->getInvoiceOrRedirect($app, $data);
        if ($invoice instanceof Response) {
            return $invoice;
        }

        return $app->render('mod_invoice_print', ['invoice' => $invoice]);
    }

    public function get_thankyoupage(\FOSSBilling\Core\Http\App $app, $hash): string|Response
    {
        $data = [
            'hash' => $hash,
        ];
        $invoice = $this->getInvoiceOrRedirect($app, $data);
        if ($invoice instanceof Response) {
            return $invoice;
        }

        return $app->render('mod_invoice_thankyou', ['invoice' => $invoice]);
    }

    public function get_banklink(\FOSSBilling\Core\Http\App $app, $hash, $id): string|Response
    {
        $data = [
            'allow_subscription' => $app->getRequest()->query->getBoolean('allow_subscription', true),
            'hash' => $hash,
            'gateway_id' => $id,
            'auto_redirect' => true,
        ];

        $api = $this->di['api_guest'];
        $invoice = $this->getInvoiceOrRedirect($app, $data);
        if ($invoice instanceof Response) {
            return $invoice;
        }

        try {
            $payment = $api->invoice_payment($data);
        } catch (InformationException $e) {
            if ($this->isInvoiceAccessDenied($e)) {
                return $app->redirect('invoice');
            }

            throw $e;
        }

        return $app->render('mod_invoice_banklink', ['payment' => $payment, 'invoice' => $invoice]);
    }

    public function get_pdf(\FOSSBilling\Core\Http\App $app, $hash): Response
    {
        $api = $this->di['api_guest'];
        $data = [
            'hash' => $hash,
        ];

        try {
            $response = $api->invoice_pdf($data);
        } catch (InformationException $e) {
            if ($this->isInvoiceAccessDenied($e)) {
                return $app->redirect('invoice');
            }

            throw $e;
        }

        if (!$response instanceof Response) {
            throw new \FOSSBilling\Core\Exception\BaseException('Invoice PDF response could not be generated');
        }

        return $response;
    }

    private function getInvoiceOrRedirect(\FOSSBilling\Core\Http\App $app, array $data): array|Response
    {
        $api = $this->di['api_guest'];

        try {
            return $api->invoice_get($data);
        } catch (InformationException $e) {
            if ($this->isInvoiceAccessDenied($e)) {
                return $app->redirect('invoice');
            }

            throw $e;
        }
    }

    private function isInvoiceAccessDenied(InformationException $e): bool
    {
        return (int) $e->getCode() === 403;
    }
}
