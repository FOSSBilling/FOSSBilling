<?php

declare(strict_types=1);

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

test('Hestia rejects missing hostnames during initialization', function (array $config): void {
    expect(fn () => new Server_Manager_Hestia($config))->toThrow(
        Server_Exception::class,
        'The HestiaCP server hostname is missing. Please configure it in the server settings.',
    );
})->with([
    'missing' => [[]],
    'null' => [['host' => null]],
    'empty' => [['host' => '']],
    'whitespace' => [['host' => " \t\n"]],
    'IP without hostname' => [['ip' => '192.0.2.1']],
]);

test('Hestia connects to a configured host', function (string $host, ?int $port): void {
    $response = new MockResponse('0');
    $client = new MockHttpClient($response);
    $manager = new class($client, $host, $port) extends Server_Manager_Hestia {
        public function __construct(private readonly HttpClientInterface $httpClient, string $host, ?int $port)
        {
            parent::__construct(['host' => $host, 'port' => $port, 'username' => 'test-user', 'accesshash' => 'test-key']);
        }

        public function getHttpClient(): HttpClientInterface
        {
            return $this->httpClient;
        }
    };

    expect($manager->getLoginUrl())->toBe('https://' . $host . ':' . ($port ?? 8083) . '/')
        ->and($manager->testConnection())->toBeTrue();
    expect($response->getRequestMethod())->toBe('POST')
        ->and($response->getRequestUrl())->toBe('https://' . $host . ':' . ($port ?? 8083) . '/api/');
})->with([
    'hostname with default port' => ['hestia.example.com', null],
    'IP host with custom port' => ['192.0.2.1', 8443],
]);
