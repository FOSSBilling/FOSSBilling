<?php

namespace APIHelper;

class Request
{
    /**
     * Handles making a response to the FOSSBilling server.
     *
     * @param string      $endpoint the API endpoint to call (Example: `guest/system/company`)
     * @param string      $method   set to POST to have cURL make a POST request to the server
     * @param array       $payload  when using POST, this array will be sent as the POST fields
     * @param string|null $apiKey   (optional) the API key to authenticate with
     * @param string|null $baseUrl  (optional) the base instance URL to make requests against (Example: `http://localhost/`)
     */
    public static function makeRequest(string $endpoint, array $payload = [], string $role = null, string $apiKey = null, string $method = 'POST', string $baseUrl = null): Response
    {
        $cookie = tempnam(sys_get_temp_dir(), 'cookie.txt');
        if (!$role) {
            $role = str_starts_with($endpoint, 'admin') ? 'admin' : 'client';
        }

        if (!$apiKey) {
            $apiKey = getenv('TEST_API_KEY');
        }

        if (!$baseUrl) {
            $baseUrl = getenv('APP_URL');
        }

        $ch = curl_init($baseUrl . 'api/' . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
        curl_setopt($ch, CURLOPT_USERPWD, "$role:$apiKey");

        if (strcasecmp($method, 'POST') === 0) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        }

        $output = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return new Response($httpCode, $output);
    }
}

class Response
{
    private int $code;
    private string $rawResponse;
    private array $decodedResponse = [];

    public function __construct(int $code, string $rawResponse)
    {
        $this->code = $code;
        $this->rawResponse = $rawResponse;
        if (json_validate($rawResponse)) {
            $this->decodedResponse = json_decode($rawResponse, true);
        }
    }

    public function getHttpCode(): int
    {
        return $this->code;
    }

    public function getResponse(): array
    {
        return $this->decodedResponse;
    }

    public function getRawResponse(): string
    {
        return $this->rawResponse;
    }

    public function wasSuccessful(): bool
    {
        return $this->decodedResponse && !$this->decodedResponse['error'];
    }

    public function getError(): string
    {
        $message = $this->decodedResponse['error']['message'] ?? 'None';
        $code = $this->decodedResponse['error']['code'] ?? 0;

        return "$message (Error code $code)";
    }

    public function getResult(): mixed
    {
        return $this->decodedResponse['result'] ?? '';
    }
}
