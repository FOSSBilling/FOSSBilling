<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Box\Mod\Client\Entity\Client;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\Transaction;

/**
 * Nano (XNO) payment gateway using the `nano:mainnet` exact scheme.
 *
 * Nano has no issuer, no fee and no account-freezing function, and the payer
 * holds its own key - which suits an agent that must settle without asking a
 * third party. The gateway adds no dependency and does nothing until an
 * operator enables it and sets a receiving account, a node URL and a price, so
 * an install that leaves it unconfigured behaves exactly as before.
 *
 * It holds no key and no seed and moves no funds. A payer publishes a confirmed
 * send to the configured account and presents the block hash; the adapter reads
 * that block from a Nano node the operator names and credits the invoice only
 * when the send is confirmed, pays the exact amount in raw, and carries this
 * invoice's order commitment in its representative field (binding option C of
 * the xno-binding v1 standard). Each credited block hash is written to a
 * fsynced claims file, so one send pays one invoice once even across a restart.
 *
 * The pure pieces this wires together - address, amount, binding, verifier and
 * the claims ledger - live beside it under Payment/Nano/ and each unit-tests on
 * its own with no dependency and no network.
 */
class Payment_Adapter_Nano implements FOSSBilling\Core\Container\InjectionAwareInterface
{
    protected ?Pimple\Container $di = null;

    public function __construct(private $config)
    {
        foreach (['nano_address', 'rpc_url'] as $required) {
            if (empty($this->config[$required])) {
                throw new Payment_Exception('Payment gateway "Nano" is not fully configured. Please configure :field.', [':field' => $required], 5901);
            }
        }

        if (!Payment_Nano_Address::isValid((string) $this->config['nano_address'])) {
            throw new Payment_Exception('The configured Nano account is not a valid, checksummed address.', [], 5902);
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

    /**
     * A Nano send is verified against the node, never approved by hand.
     */
    public static function requiresManualApproval(): bool
    {
        return false;
    }

    public static function getConfig(): array
    {
        return [
            'supports_one_time_payments' => true,
            'supports_subscriptions' => false,
            'description' => 'Accept Nano (XNO) payments. The buyer pays a confirmed send to your account and the gateway verifies it against a Nano node you run or trust - it stores no key and moves no funds.',
            'logo' => [
                'logo' => 'nano.png',
                'height' => '50px',
                'width' => '50px',
            ],
            'form' => [
                'nano_address' => [
                    'text', [
                        'label' => 'Your Nano account (nano_...) that invoices are paid to',
                        'required_when' => ['enabled' => true],
                    ],
                ],
                'rpc_url' => [
                    'text', [
                        'label' => 'Nano node JSON-RPC URL used to read blocks (https, or http only for a loopback node)',
                        'required_when' => ['enabled' => true, 'test_mode' => false],
                    ],
                ],
                'test_rpc_url' => [
                    'text', [
                        'label' => 'Test Nano node JSON-RPC URL',
                        'required_when' => ['enabled' => true, 'test_mode' => true],
                    ],
                ],
                'xno_price' => [
                    'text', [
                        'label' => 'Price in XNO for one unit of your invoice currency (e.g. 3.2 means 1.00 = 3.2 XNO)',
                        'required_when' => ['enabled' => true],
                    ],
                ],
                'replay_window_minutes' => [
                    'text', [
                        'label' => 'How many minutes a send stays acceptable after it confirms (default 60)',
                    ],
                ],
            ],
        ];
    }

    public function getHtml(FOSSBilling\Core\Api\Proxy $api_admin, int $invoice_id, bool $subscription): string
    {
        $invoiceModel = $this->di['em']->getRepository(Invoice::class)->find($invoice_id);
        if (!$invoiceModel instanceof Invoice) {
            throw new Payment_Exception('Invoice not found');
        }

        $payTo = (string) $this->config['nano_address'];
        $priceRaw = $this->priceRaw($invoiceModel);
        $representative = Payment_Nano_Binding::representativeAccount($invoiceModel->getHash(), $payTo, $priceRaw);
        $serviceUrl = $this->getServiceUrl($invoice_id);
        $invoiceUrl = $this->di['url']->link('invoice/' . $invoiceModel->getHash());

        $lines = [
            __trans('Pay this invoice with Nano (XNO).'),
            __trans('Send exactly :xno XNO (:raw raw)', ['xno' => $this->priceXno($invoiceModel), 'raw' => $priceRaw]),
            __trans('To account: :account', ['account' => $payTo]),
            __trans('With the block representative set to: :rep', ['rep' => $representative]),
            __trans('Then paste the send block hash below.'),
        ];
        $info = '<ul><li>' . implode('</li><li>', array_map('htmlspecialchars', $lines)) . '</li></ul>';

        // The payer publishes its own confirmed send, then submits the block
        // hash. The adapter re-reads the block from the operator's node; nothing
        // here signs or holds a key.
        return $info . "
            <form id='fossbilling-nano-form' onsubmit='return false;'>
                <input type='text' id='fossbilling-nano-hash' placeholder='" . __trans('64-character block hash') . "' autocomplete='off' />
                <button type='submit' id='fossbilling-nano-submit'>" . __trans('I have paid') . "</button>
            </form>
            <script>
                document.getElementById('fossbilling-nano-form').addEventListener('submit', function () {
                    var hash = document.getElementById('fossbilling-nano-hash').value.trim();
                    FOSSBilling.api.makeRequest('POST', '$serviceUrl', { block_hash: hash }, function () {
                        window.location.href = '$invoiceUrl';
                    }, function (error) {
                        console.error('Nano payment verification failed:', error);
                    }, false);
                });
            </script>";
    }

    public function getInvoiceId($data)
    {
        return $data['get']['invoice_id'] ?? ($data['post']['invoice_id'] ?? null);
    }

    public function getServiceUrl($invoice_id = 0)
    {
        $gatewayModel = $this->di['em']->getRepository(PayGateway::class)->findEnabledByGateway('Nano');
        if (!$gatewayModel instanceof PayGateway) {
            throw new Payment_Exception('Nano gateway is not enabled', null, 5903);
        }

        $invoiceModel = $this->di['em']->getRepository(Invoice::class)->find($invoice_id);
        if (!$invoiceModel instanceof Invoice) {
            throw new Payment_Exception('Invoice not found');
        }

        $gatewayService = $this->di['mod_service']('Invoice', 'PayGateway');

        return $gatewayService->getCallbackUrl($gatewayModel, $invoiceModel);
    }

    public function processTransaction($api_admin, int $id, array $data, int $gateway_id): bool
    {
        $tx = $this->di['em']->getRepository(Transaction::class)->find($id);
        if (!$tx instanceof Transaction) {
            throw new Payment_Exception('Transaction not found');
        }

        $invoice = $tx->getInvoice();
        $get = (isset($data['get']) && is_array($data['get'])) ? $data['get'] : [];
        $post = (isset($data['post']) && is_array($data['post'])) ? $data['post'] : [];
        if (!$invoice instanceof Invoice) {
            $invoice = $this->di['em']->getRepository(Invoice::class)->find((int) ($get['invoice_id'] ?? $post['invoice_id'] ?? 0));
            if ($invoice instanceof Invoice) {
                $tx->setInvoice($invoice);
            }
        }
        if (!$invoice instanceof Invoice) {
            throw new Payment_Exception('Invoice not found');
        }

        if (($invoice->getGateway()?->getId() ?? 0) !== $gateway_id) {
            throw new Payment_Exception('Invoice is not configured to use this payment gateway.');
        }

        $hash = strtoupper(trim((string) ($post['block_hash'] ?? $get['block_hash'] ?? '')));
        if (preg_match('/^[0-9A-F]{64}$/', $hash) !== 1) {
            $this->failTransaction($tx, 'A Nano block hash is 64 hex characters.');

            throw new Payment_Exception('A Nano block hash is 64 hex characters.');
        }

        $payTo = (string) $this->config['nano_address'];
        $priceRaw = $this->priceRaw($invoice);
        $representative = Payment_Nano_Binding::representativeAccount($invoice->getHash(), $payTo, $priceRaw);

        $blockInfo = $this->readBlock($hash);
        if ($blockInfo === null) {
            $this->failTransaction($tx, 'The Nano node does not know this block.');

            throw new Payment_Exception('The Nano node does not know this block.');
        }

        $result = Payment_Nano_Verifier::verify($blockInfo, $payTo, $priceRaw, $representative, $this->replayWindowSeconds(), time());
        if (!$result['ok']) {
            $this->failTransaction($tx, $result['error']);

            throw new Payment_Exception($result['error']);
        }

        // Durable, restart-proof replay refusal: one block pays one invoice once.
        $claims = new Payment_Nano_ClaimsFile($this->claimsPath($gateway_id));
        $fresh = $claims->claim($hash, $invoice->getHash());

        $invoiceService = $this->di['mod_service']('Invoice');
        $amountXno = $this->rawToXno((string) $result['amount']);

        // The on-chain amount is XNO, but it was derived to equal this invoice's
        // total, so the invoice is settled in its own currency; the block hash
        // and the XNO paid are kept on the transaction for audit.
        $expected = (float) $invoiceService->getTotalWithTax($invoice);

        $tx->setTxnId($hash);
        $tx->setTxnStatus('confirmed');
        $tx->setType(Payment_Transaction::TXTYPE_PAYMENT);
        $tx->setAmount((string) $expected);
        $tx->setCurrency($invoice->getCurrency());
        $tx->setNote('Nano payment ' . $hash . ' (' . $amountXno . ' XNO)');
        $tx->setError('');
        $tx->setErrorCode(null);

        if ($fresh && $invoice->getStatus() !== Invoice::STATUS_PAID) {
            $client = $this->di['em']->getRepository(Client::class)->find($invoice->getClientId())
                ?? throw new FOSSBilling\Core\Exception\InformationException('Client not found');

            $bd = [
                'amount' => $expected,
                'description' => 'Nano payment ' . $hash,
                'type' => 'transaction',
                'rel_id' => $tx->getId(),
            ];

            $this->di['mod_service']('Client')->addFunds($client, $bd['amount'], $bd['description'], $bd);

            if (!$invoiceService->isInvoiceTypeDeposit($invoice)) {
                if (!$invoice->isIssued()) {
                    $invoiceService->issueInvoice($invoice, ['use_credits' => false]);
                }
                $invoiceService->payInvoiceWithCredits($invoice);
            } else {
                $invoiceService->markAsPaid($invoice);
            }
        }

        $tx->setStatus(Transaction::STATUS_PROCESSED);
        $tx->setUpdatedAt(new DateTime());
        $this->di['em']->flush();

        return true;
    }

    private function failTransaction(Transaction $tx, string $error): void
    {
        $tx->setStatus(Transaction::STATUS_ERROR);
        $tx->setError($error);
        $tx->setUpdatedAt(new DateTime());
        $this->di['em']->flush();
    }

    /**
     * The exact amount owed, in raw, derived from the invoice total and the
     * operator's price. getHtml and processTransaction both call this, so the
     * payer is shown the same raw the adapter later checks.
     */
    private function priceRaw(Invoice $invoice): string
    {
        return Payment_Nano_Amount::xnoToRaw($this->priceXno($invoice));
    }

    private function priceXno(Invoice $invoice): string
    {
        $invoiceService = $this->di['mod_service']('Invoice');
        $total = (float) $invoiceService->getTotalWithTax($invoice);
        $totalString = rtrim(rtrim(sprintf('%.8F', $total), '0'), '.');
        if ($totalString === '' || $totalString === '-0') {
            $totalString = '0';
        }

        $price = trim((string) ($this->config['xno_price'] ?? ''));
        if (preg_match('/^\d+(?:\.\d+)?$/', $price) !== 1 || (float) $price <= 0) {
            throw new Payment_Exception('The Nano gateway price (xno_price) is not a positive number.', [], 5904);
        }

        return Payment_Nano_Amount::multiplyDecimals($totalString, $price);
    }

    private function rawToXno(string $raw): string
    {
        $raw = Payment_Nano_Amount::normalizeRaw($raw);
        $raw = str_pad($raw, Payment_Nano_Amount::RAW_DECIMALS + 1, '0', STR_PAD_LEFT);
        $intPart = ltrim(substr($raw, 0, -Payment_Nano_Amount::RAW_DECIMALS), '0');
        $fracPart = rtrim(substr($raw, -Payment_Nano_Amount::RAW_DECIMALS), '0');
        $intPart = $intPart === '' ? '0' : $intPart;

        return $fracPart === '' ? $intPart : $intPart . '.' . $fracPart;
    }

    private function replayWindowSeconds(): int
    {
        $minutes = (int) ($this->config['replay_window_minutes'] ?? 0);
        if ($minutes <= 0) {
            $minutes = 60;
        }

        return $minutes * 60;
    }

    private function claimsPath(int $gateway_id): string
    {
        $base = defined('PATH_DATA') ? PATH_DATA : sys_get_temp_dir();

        return $base . DIRECTORY_SEPARATOR . 'nano' . DIRECTORY_SEPARATOR . 'claims-' . $gateway_id . '.log';
    }

    private function rpcUrl(): string
    {
        $url = (bool) ($this->config['test_mode'] ?? false)
            ? (string) ($this->config['test_rpc_url'] ?? $this->config['rpc_url'] ?? '')
            : (string) ($this->config['rpc_url'] ?? '');

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $isLoopback = in_array($host, ['127.0.0.1', '::1', 'localhost'], true);
        if ($scheme !== 'https' && !($scheme === 'http' && $isLoopback)) {
            throw new Payment_Exception('The Nano node URL must be https (or http only for a loopback node).', [], 5905);
        }

        return $url;
    }

    /**
     * Read one confirmed block from the operator's node. No key is used and no
     * block is broadcast - a confirmed Nano send needs neither.
     *
     * @return array<string, mixed>|null null when the node has never seen the block
     */
    private function readBlock(string $hash): ?array
    {
        $response = $this->rpcCall(['action' => 'block_info', 'json_block' => 'true', 'hash' => $hash]);

        $error = $response['error'] ?? null;
        if ($error === 'Block not found') {
            return null;
        }
        if ($error !== null) {
            throw new Payment_Exception('The Nano node could not read the block: :error', [':error' => is_string($error) ? $error : 'unknown error']);
        }

        return $response;
    }

    /**
     * @param array<string, string> $action
     *
     * @return array<string, mixed>
     */
    protected function rpcCall(array $action): array
    {
        $client = Symfony\Component\HttpClient\HttpClient::create(['bindto' => BIND_TO]);

        try {
            $response = $client->request('POST', $this->rpcUrl(), [
                'json' => $action,
                'timeout' => 10,
                'max_duration' => 15,
                'headers' => ['Accept' => 'application/json'],
            ]);

            return $response->toArray(false);
        } catch (Symfony\Contracts\HttpClient\Exception\ExceptionInterface $e) {
            throw new Payment_Exception('Could not reach the Nano node: :error', [':error' => $e->getMessage()]);
        }
    }
}
