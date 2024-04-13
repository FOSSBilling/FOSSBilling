<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */
abstract class Payment_AdapterAbstract
{
    final public const TYPE_HTML = 'html';
    final public const TYPE_FORM = 'form';
    final public const TYPE_API = 'api';

    /**
     * Response text for notify_url
     * This value is set after IPN is received and validated.
     */
    protected ?string $output = null;

    /**
     * Are we in test mode?
     */
    public bool $testMode = false;

    /**
     * Log object.
     */
    private ?Box_Log $_log = null;

    // Stub function that can be overridden by a registrar
    public function init()
    {
    }

    /**
     * Constructs a new Payment_Adapter object.
     *
     * @param array $_config The configuration for the payment adapter as configured within the admin panel
     *
     * @throws Payment_Exception
     */
    public function __construct(protected $_config)
    {
        /*
         * Redirect client after successful payment, usually to invoice
         */
        if (!$this->getParam('return_url')) {
            throw new Payment_Exception('Return URL for the payment gateway was not set', [], 6001);
        }

        /*
         * URL to redirect client if payment process was canceled
         */
        if (!$this->getParam('cancel_url')) {
            throw new Payment_Exception('Cancel URL for the payment gateway was not set', [], 6002);
        }

        /*
         * IPN notification url. Payment gateway posts data to this URL
         * to inform FOSSBilling about payment
         */
        if (!$this->getParam('notify_url')) {
            throw new Payment_Exception('IPN Notification URL for the payment gateway was not set', [], 6003);
        }

        /*
         * If payment gateway has only one callback url, this url should be
         * used. It is equal to return_url + notify_url combined.
         * Client gets redirected to redirect_url, POST, GET data are considered
         * as IPN data, and client gets redirected to invoice page.
         */
        if (!$this->getParam('redirect_url')) {
            throw new Payment_Exception('IPN redirect URL for the payment gateway was not set', [], 6004);
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
     * Return payment gateway type (TYPE_HTML, TYPE_FORM, TYPE_API).
     */
    public function getType(): string
    {
        return Payment_AdapterAbstract::TYPE_FORM;
    }

    /**
     * Payment gateway endpoint.
     *
     * @return string
     */
    public function getServiceUrl()
    {
        return '';
    }

    /**
     * Returns invoice id from callback IPN.
     *
     * This method is called before transaction processing to determine
     * invoice id from IPN.
     *
     * @param array $data - Contains $_GET, $_POST, $HTTP_RAW_POST_DATA
     *                    (or file_get_contents("php://input")) in format like:
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

    public function setLog(Box_Log $log)
    {
        $this->_log = $log;
    }

    public function getLog()
    {
        $log = $this->_log;
        if (!$log instanceof Box_Log) {
            $log = new Box_Log();
        }

        return $log;
    }

    /**
     * Creates and returns an interface for the Symfony HTTP client.
     */
    public function getHttpClient(): Symfony\Contracts\HttpClient\HttpClientInterface
    {
        return Symfony\Component\HttpClient\HttpClient::create(['bindto' => BIND_TO]);
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
     * @param string $currency The currency (unused currently)
     *
     * @return string The formatted money string
     */
    public function moneyFormat($amount, $currency = null)
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * Toggles test mode.
     *
     * @return Payment_AdapterAbstract
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

    /**
     * Set custom response text to be printed when IPN is received
     * Used only by payment gateways who care about notify_url response.
     */
    public function setOutput(string $response): void
    {
        $this->output = $response;
    }

    public function getOutput(): string
    {
        return $this->output;
    }
}
