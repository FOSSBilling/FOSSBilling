<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Extension\Contract\Payment;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

abstract class AdapterAbstract
{
    /**
     * Are we in test mode?
     */
    public bool $testMode = false;

    private ?LoggerInterface $logger = null;

    // Stub function that can be overridden by a registrar
    public function init()
    {
    }

    /**
     * Constructs a new payment adapter.
     *
     * @param array $_config The configuration for the payment adapter as configured within the admin panel
     *
     * @throws Exception
     */
    public function __construct(protected $_config)
    {
        /*
         * Redirect client after successful payment, usually to invoice
         */
        if (!$this->getParam('return_url')) {
            throw new Exception('Return URL for the payment gateway was not set', [], 6001);
        }

        /*
         * URL to redirect client if payment process was canceled
         */
        if (!$this->getParam('cancel_url')) {
            throw new Exception('Cancel URL for the payment gateway was not set', [], 6002);
        }

        /*
         * IPN notification url. Payment gateway posts data to this URL
         * to inform FOSSBilling about payment
         */
        if (!$this->getParam('notify_url')) {
            throw new Exception('IPN Notification URL for the payment gateway was not set', [], 6003);
        }

        /*
         * If payment gateway has only one callback url, this url should be
         * used. It is equal to return_url + notify_url combined.
         * Client gets redirected to redirect_url, POST, GET data are considered
         * as IPN data, and client gets redirected to invoice page.
         */
        if (!$this->getParam('redirect_url')) {
            throw new Exception('IPN redirect URL for the payment gateway was not set', [], 6004);
        }

        $this->init();
    }

    /**
     * Return gateway configuration options.
     *
     * @return array
     */
    abstract public static function getConfig();

    /**
     * Returns invoice id from callback IPN.
     *
     * This method is called before transaction processing to determine
     * invoice id from IPN.
     *
     * @param array $data - Contains $_GET, $_POST, $HTTP_RAW_POST_DATA
     *                    "php://input" in format like:
     *                    $data = array(
     *                    'get'=>$_GET,
     *                    'post'=>$_POST,
     *                    'http_raw_post_data'=>$HTTP_RAW_POST_DATA
     *                    );
     *
     * @return int - invoice id
     */
    public function getInvoiceId($data)
    {
        return $data['invoice_id'] ?? null;
    }

    public function setLog(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function getLog(): LoggerInterface
    {
        return $this->logger ??= new NullLogger();
    }

    /**
     * Creates and returns an interface for the Symfony HTTP client.
     */
    public function getHttpClient(): \Symfony\Contracts\HttpClient\HttpClientInterface
    {
        return \Symfony\Component\HttpClient\HttpClient::create(['bindto' => BIND_TO]);
    }

    /**
     * Get config parameter.
     *
     * @param string $param the parameter name to retrieve from the config
     *
     * @return mixed|null The associated config parameter or null if it's not defined
     */
    public function getParam($param)
    {
        return $this->_config[$param] ?? null;
    }

    /**
     * Convert money amount to Gateway money format.
     *
     * @param float  $amount   The amount
     * @param string $currency The currency code (e.g. USD, JPY)
     *
     * @return string The formatted money string
     */
    public function moneyFormat($amount, $currency = null)
    {
        $fractionDigits = $currency !== null && \Symfony\Component\Intl\Currencies::exists($currency)
            ? \Symfony\Component\Intl\Currencies::getFractionDigits($currency)
            : 2;

        return number_format((float) $amount, $fractionDigits, '.', '');
    }

    /**
     * Toggles test mode.
     *
     * @return AdapterAbstract
     */
    public function setTestMode(bool $bool)
    {
        $this->testMode = $bool;

        return $this;
    }

    public function getTestMode(): bool
    {
        return $this->testMode;
    }
}
