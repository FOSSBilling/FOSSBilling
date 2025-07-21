<?php

class Payment_Adapter_Airwallex extends Payment_AdapterAbstract
{
    protected ?Pimple\Container $di = null;
    private array $config = [];

    public function setDi(Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Pimple\Container
    {
        return $this->di;
    }

    public function __construct($config)
    {
        $this->config = $config;
        if (!isset($this->config['client_id'])) {
            throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'AirWallex', ':missing' => 'Client ID']);
        }
        if (!isset($this->config['webhook_secret'])) {
            throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'AirWallex', ':missing' => 'Webhook Secret']);
        }
        if (!isset($this->config['api_key'])) {
            throw new Payment_Exception('The ":pay_gateway" payment gateway is not fully configured. Please configure the :missing', [':pay_gateway' => 'AirWallex', ':missing' => 'API Key']);
        }
    }

    /**
     * Return payment gateway type
     * 
     * @return string Gateway type
     */
    public function getType(): string
    {
        return Payment_AdapterAbstract::TYPE_FORM;
    }

    /**
     * Return human readable gateway name
     * 
     * @return string Gateway name
     */
    public function getServiceName()
    {
        return 'Airwallex';
    }

    /**
     * Return payment gateway URL
     * 
     * @return string Gateway URL
     */
    public function getServiceURL()
    {
        return 'https://www.airwallex.com/';
    }

    // Airwallex API endpoints
    const API_BASE_URL_SANDBOX = 'https://api-demo.airwallex.com';
    const API_BASE_URL_LIVE = 'https://api.airwallex.com';

    /**
     * Get configuration form fields for admin panel
     * 
     * @return array Configuration form fields
     */
    public static function getConfig(): array
    {
        return [
            'supports_one_time_payments' => true,
            'supports_subscriptions' => true,
            'description' => 'Airwallex payment gateway - Accept payments globally with competitive FX rates',
            'logo' => [
                'logo' => 'airwallex.png',
                'height' => '35px',
                'width' => '100px'
            ],
            'form' => [
                'client_id' => [
                    'text',
                    [
                        'label' => 'Client ID',
                        'required' => true,
                        'description' => 'Your Airwallex Client ID'
                    ]
                ],
                'api_key' => [
                    'text',
                    [
                        'label' => 'API Key',
                        'required' => true,
                        'description' => 'Your Airwallex API Key'
                    ]
                ],
                'webhook_secret' => [
                    'text',
                    [
                        'label' => 'Webhook Secret',
                        'required' => true,
                        'description' => 'Webhook secret for validating callbacks'
                    ]
                ],
                'test_mode' => [
                    'radio',
                    [
                        'multiOptions' => [
                            1 => 'Test Mode',
                            0 => 'Live Mode'
                        ],
                        'label' => 'Mode',
                        'required' => true,
                        'description' => 'Select Test Mode for development'
                    ]
                ]
            ]
        ];
    }

    /**
     * Get access token from Airwallex
     * @return string|false Access token or false on failure
     */
    private function getAccessToken()
    {
        $baseUrl = $this->config['test_mode'] ? self::API_BASE_URL_SANDBOX : self::API_BASE_URL_LIVE;
        $url = $baseUrl . '/api/v1/authentication/login';
        try {
            $response = $this->getHttpClient()->request('POST', $url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-client-id' => $this->config['client_id'],
                    'x-api-key' => $this->config['api_key']
                ],
                'body' => ''
            ]);
            $httpCode = $response->getStatusCode();
            $body = (string) $response->getContent();
            if ($httpCode !== 201) {
                error_log('Airwallex Auth Error - HTTP ' . $httpCode . ': ' . $body);
                return false;
            }
            $data = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('Airwallex Auth Error: Invalid JSON response');
                return false;
            }
            return $data['token'] ?? false;
        } catch (\Throwable $e) {
            error_log('Airwallex authentication failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Test connection to Airwallex API
     * 
     * @return bool True if connection is successful
     */
    public function testConnection()
    {
        try {
            $accessToken = $this->getAccessToken();
            return !empty($accessToken);
        } catch (Exception $e) {
            error_log('Airwallex connection test failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create payment intent with Airwallex API
     * 
     * @param array $invoice Invoice data
     * @return array Payment intent response
     * https://www.airwallex.com/docs/api#/Payment_Acceptance/Payment_Intents/
     */
    private function createPaymentIntent($invoice)
    {
        $accessToken = $this->getAccessToken();
        
        if (!$accessToken) {
            throw new Payment_Exception('Failed to obtain access token from Airwallex');
        }

        $baseUrl = $this->config['test_mode'] ? self::API_BASE_URL_SANDBOX : self::API_BASE_URL_LIVE;
        $url = $baseUrl . '/api/v1/pa/payment_intents/create';
        
        $paymentData = [
            'request_id' => uniqid('fossbilling_' . $invoice['id'] . '_'),
            'amount' => (int)($invoice['total'] * 100), // Amount in cents
            'currency' => $invoice['currency'], // https://www.airwallex.com/docs/payments__supported-currencies
            'merchant_order_id' => 'INV-' . $invoice['id'],
            'order' => [
                'type' => 'digital_goods',
                'products' => [
                    [
                        'name' => 'Invoice #' . $invoice['nr'],
                        'desc' => 'Payment for invoice #' . $invoice['nr'],
                        'quantity' => 1,
                        'unit_price' => (int)($invoice['total'] * 100),
                        'type' => 'digital_goods'
                    ]
                ]
            ],
            'descriptor' => 'Invoice #' . $invoice['nr'],
            'metadata' => [
                'integration' => 'fossbilling'
            ]
        ];

        // Add customer information if available
        if (isset($invoice['buyer'])) {
            $paymentData['customer'] = [
                'address' => [
                    'city' => $invoice['buyer']['city'] ?? '',
                    'state' => $invoice['buyer']['state'] ?? '',
                    'postcode' => $invoice['buyer']['zip'] ?? '',
                    'street' => $invoice['buyer']['street'] ?? '',
                    'country_code' => $invoice['buyer']['country'] ?? ''
                ],
                'email' => $invoice['buyer']['email'] ?? '',
                'first_name' => $invoice['buyer']['first_name'] ?? '',
                'last_name' => $invoice['buyer']['last_name'] ?? '',
                'merchant_customer_id' => $invoice['client']['id'],
            ];
        }

        $response = $this->makeApiCall($url, $paymentData, $accessToken);
        
        return $response;
    }

    /**
     * Make API call to Airwallex (improved version)
     * 
     * @param string $url API endpoint URL
     * @param array $data Request data
     * @param string $accessToken Access token
     * @return array Response data
     */
    private function makeApiCall($url, $data, $accessToken)
    {
        try {
            $response = $this->getHttpClient()->request('POST', $url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $accessToken
                ],
                'body' => json_encode($data)
            ]);
            error_log('Airwallex API Request Payload: ' . json_encode($data));
            $httpCode = $response->getStatusCode();
            $body = (string) $response->getContent();
            if ($httpCode !== 201 && $httpCode !== 200) {
                error_log('Airwallex API Error - HTTP ' . $httpCode . ': ' . $body);
                throw new Payment_Exception('Airwallex API request failed with HTTP ' . $httpCode . ': ' . $body);
            }
            $responseData = json_decode($body, true);
            return $responseData;
        } catch (\Throwable $e) {
            error_log('Airwallex API request failed: ' . $e->getMessage());
            error_log('Code: ' . $e->getCode());
            error_log('File: ' . $e->getFile() . ' Line: ' . $e->getLine());
            error_log('Trace: ' . $e->getTraceAsString());
            throw new Payment_Exception('Airwallex API request failed: ' . $e->getMessage());
        }
    }

    /**
     * Generate HTML form for payment
     * 
     * @param Api_Admin $api_admin FOSSBilling API
     * @param int $invoice_id Invoice ID
     * @param bool $subscription Whether this is a subscription
     * @return string HTML form
     */
    public function getHtml($api_admin, $invoice_id, $subscription)
    {
        $invoice = $api_admin->invoice_get(['id' => $invoice_id]);
        $buyer = $invoice['buyer'];
        
        // Create payment intent with Airwallex
        $paymentIntent = $this->createPaymentIntent($invoice);
        
        if (!$paymentIntent || !isset($paymentIntent['client_secret'])) {
            throw new Payment_Exception('Failed to create payment intent with Airwallex');
        }

        $form = $this->generatePaymentForm($paymentIntent, $invoice, $buyer);
        
        return $form;
    }

    /**
     * Generate payment form HTML
     * 
     * @param array $paymentIntent Payment intent data
     * @param array $invoice Invoice data
     * @param array $buyer Buyer data
     * @return string HTML form
     */
    private function generatePaymentForm($paymentIntent, $invoice, $buyer)
    {
        $clientSecret = $paymentIntent['client_secret'];
        $testMode = $this->config['test_mode'] ? 'true' : 'false';
        
        $html = '
        <div id="airwallex-payment-container">
            <div id="payment-loading" style="text-align: center; padding: 20px;">
                <span>{% trans \'Loading payment form...\' %}</span>
            </div>
            
            <div id="payment-form" style="display: none;">
                <div id="card-element" style="margin-bottom: 20px;"></div>
                <div id="payment-error" style="display: none; color: red; margin-top: 10px;"></div>
                <button type="button" id="submit-payment" style="
                    background: #2563eb; 
                    color: white; 
                    border: none; 
                    padding: 12px 24px; 
                    border-radius: 6px; 
                    cursor: pointer; 
                    font-size: 16px; 
                    margin-top: 20px; 
                    width: 100%;
                ">
                    Pay ' . $invoice['currency'] . ' ' . number_format($invoice['total'], 2) . '
                </button>
            </div>
        </div>

        <script src="https://checkout.airwallex.com/assets/elements.bundle.min.js"></script>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const testMode = ' . $testMode . ';
                const env = testMode ? "demo" : "prod";
                
                console.log("Airwallex environment:", env);
                console.log("Client Secret:", "' . $clientSecret . '");
                console.log("Payment Intent ID:", "' . $paymentIntent['id'] . '");
                console.log("Test mode:", testMode);
                
                try {
                    // Initialize Airwallex with current API
                    Airwallex.init({
                        env: env,
                        origin: window.location.origin,
                    });
                    
                    console.log("Airwallex initialized successfully");
                    
                    // Create card element with current API
                    const cardElement = Airwallex.createElement("card", {
                        intent: {
                            id: "' . $paymentIntent['id'] . '",
                            client_secret: "' . $clientSecret . '"
                        }
                    });
                    
                    console.log("Card element created");
                    
                    // Mount the card element
                    cardElement.mount("card-element");
                    
                    console.log("Card element mounted");
                    
                    // Hide loading, show form
                    document.getElementById("payment-loading").style.display = "none";
                    document.getElementById("payment-form").style.display = "block";
                    
                    // Handle form submission
                    document.getElementById("submit-payment").addEventListener("click", async function(event) {
                        event.preventDefault();
                        
                        console.log("Payment button clicked");
                        
                        const submitButton = document.getElementById("submit-payment");
                        const errorDiv = document.getElementById("payment-error");
                        
                        // Disable submit button and show loading
                        submitButton.disabled = true;
                        submitButton.textContent = "Processing...";
                        errorDiv.style.display = "none";
                        
                        try {
                            // Confirm payment with current API
                            const result = await Airwallex.confirmPaymentIntent({
                                element: cardElement,
                                id: "' . $paymentIntent['id'] . '",
                                client_secret: "' . $clientSecret . '"
                            });
                            
                            console.log("Payment confirmation result:", result);
                            
                            if (result.error) {
                                console.error("Payment failed:", result.error);
                                errorDiv.textContent = result.error.message || "Payment failed. Please try again.";
                                errorDiv.style.display = "block";
                                
                                // Re-enable submit button
                                submitButton.disabled = false;
                                submitButton.textContent = "Pay ' . $invoice['currency'] . ' ' . number_format($invoice['total'], 2) . '";
                            } else {
                                console.log("Payment successful:", result);
                                
                                // Payment successful - you can redirect or show success message
                                document.getElementById("payment-form").innerHTML = 
                                    `<div style="text-align: center; color: green; padding: 20px;">
                                        <h3>Payment Successful!</h3>
                                        <p>Your payment has been processed successfully.</p>
                                        <p>Transaction ID: ${result.id}</p>
                                    </div>`;
                                
                                // Optionally redirect after a delay
                                setTimeout(() => {
                                    window.location.reload();
                                }, 3000);
                            }
                        } catch (error) {
                            console.error("Payment error:", error);
                            errorDiv.textContent = "An unexpected error occurred. Please try again.";
                            errorDiv.style.display = "block";
                            
                            // Re-enable submit button
                            submitButton.disabled = false;
                            submitButton.textContent = "Pay ' . $invoice['currency'] . ' ' . number_format($invoice['total'], 2) . '";
                        }
                    });
                } catch (error) {
                    console.error("Error initializing Airwallex:", error);
                    document.getElementById("payment-loading").innerHTML = 
                        `<p style="color: red;">Error loading payment form: ${error.message}</p>`;
                }
            });
        </script>';

        return $html;
    }

    /**
     * Validate webhook signature
     * 
     * @param array $data Webhook data
     * @return bool True if signature is valid
     */
    private function validateWebhookSignature($data)
    {
        if (!isset($this->config['webhook_secret']) || empty($this->config['webhook_secret'])) {
            return true; // Skip validation if no secret is configured
        }

        $headers = getallheaders();
        $signature = $headers['x-signature'] ?? '';
        
        if (empty($signature)) {
            return false;
        }

        $payload = file_get_contents('php://input');
        $expectedSignature = hash_hmac('sha256', $payload, $this->config['webhook_secret']);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Process IPN (Instant Payment Notification) from Airwallex
     * 
     * @param Api_Admin $api_admin FOSSBilling API
     * @param int $invoice_id Invoice ID
     * @param array $data POST data from webhook
     */
    public function processTransaction($api_admin, $invoice_id, $data)
    {
        // Validate webhook signature
        if (!$this->validateWebhookSignature($data)) {
            throw new Payment_Exception('Invalid webhook signature');
        }

        $invoice = $api_admin->invoice_get(['id' => $invoice_id]);
        
        // Handle different event types
        // https://www.airwallex.com/docs/developer-tools__listen-for-webhook-events__event-types
        switch ($data['name']) {
            case 'payment_intent.created':
                $this->handleSuccessfulPayment($api_admin, $invoice_id, $data);
                break;
                
            case 'payment_attempt.failed_to_process	':
                $this->handleFailedPayment($api_admin, $invoice_id, $data);
                break;
                
            case 'payment_intent.cancelled':
                $this->handleCancelledPayment($api_admin, $invoice_id, $data);
                break;
                
            default:
                error_log('Airwallex: Unhandled webhook event: ' . $data['name']);
        }
    }

    /**
     * Handle successful payment
     * 
     * @param Api_Admin $api_admin FOSSBilling API
     * @param int $invoice_id Invoice ID
     * @param array $data Webhook data
     */
    private function handleSuccessfulPayment($api_admin, $invoice_id, $data)
    {
        $paymentIntent = $data['data']['object'];
        $paymentAttempt = $paymentIntent['latest_payment_attempt'] ?? null;
        
        if (!$paymentAttempt) {
            throw new Payment_Exception('No payment attempt data found');
        }

        $txn = [
            'id' => $invoice_id,
            'type' => 'payment',
            'txn_status' => 'complete',
            'txn_id' => $paymentAttempt['id'],
            'amount' => $paymentIntent['amount'] / 100, // Convert from cents
            'currency' => $paymentIntent['currency'],
            'gateway' => 'Airwallex',
            'gateway_txn' => json_encode($paymentIntent),
            'note' => 'Payment processed via Airwallex'
        ];

        $api_admin->invoice_transaction_create($txn);
        
        // Mark invoice as paid
        $api_admin->invoice_mark_as_paid(['id' => $invoice_id]);
    }

    /**
     * Handle failed payment
     * 
     * @param Api_Admin $api_admin FOSSBilling API
     * @param int $invoice_id Invoice ID
     * @param array $data Webhook data
     */
    private function handleFailedPayment($api_admin, $invoice_id, $data)
    {
        $paymentIntent = $data['data']['object'];
        $paymentAttempt = $paymentIntent['latest_payment_attempt'] ?? null;
        
        $txn = [
            'id' => $invoice_id,
            'type' => 'payment',
            'txn_status' => 'failed',
            'txn_id' => $paymentAttempt['id'] ?? 'unknown',
            'amount' => $paymentIntent['amount'] / 100,
            'currency' => $paymentIntent['currency'],
            'gateway' => 'Airwallex',
            'gateway_txn' => json_encode($paymentIntent),
            'note' => 'Payment failed: ' . ($paymentAttempt['payment_method']['failure_reason'] ?? 'Unknown reason')
        ];

        $api_admin->invoice_transaction_create($txn);
    }

    /**
     * Handle cancelled payment
     * 
     * @param Api_Admin $api_admin FOSSBilling API
     * @param int $invoice_id Invoice ID
     * @param array $data Webhook data
     */
    private function handleCancelledPayment($api_admin, $invoice_id, $data)
    {
        $paymentIntent = $data['data']['object'];
        
        $txn = [
            'id' => $invoice_id,
            'type' => 'payment',
            'txn_status' => 'cancelled',
            'txn_id' => $paymentIntent['id'],
            'amount' => $paymentIntent['amount'] / 100,
            'currency' => $paymentIntent['currency'],
            'gateway' => 'Airwallex',
            'gateway_txn' => json_encode($paymentIntent),
            'note' => 'Payment cancelled by user'
        ];

        $api_admin->invoice_transaction_create($txn);
    }
}
