<?php

declare(strict_types=1);

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

test('Internetbs normalizes stored phone numbers for every contact role', function (string $operation): void {
    $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
        parse_str($options['body'], $params);
        foreach (['Registrant', 'Admin', 'Technical', 'Billing'] as $role) {
            expect($params[$role . '_PhoneNumber'])->toBe('+225.0100000000');
        }

        return new MockResponse("status=SUCCESS\nproduct_0_status=SUCCESS");
    });
    $adapter = new class($httpClient) extends Registrar_Adapter_Internetbs {
        public function __construct(private readonly HttpClientInterface $httpClient)
        {
            parent::__construct(['apikey' => 'test-key', 'password' => 'test-password']);
        }

        public function getHttpClient(): HttpClientInterface
        {
            return $this->httpClient;
        }
    };
    $contact = (new Registrar_Domain_Contact())
        ->setTelCc('225')
        ->setTel("\u{202A}010 000-0000\u{202C}");
    $domain = (new Registrar_Domain())
        ->setSld('example')
        ->setTld('.com')
        ->setRegistrationPeriod(1)
        ->setContactRegistrar($contact);

    expect($adapter->{$operation}($domain))->toBeTrue()
        ->and($httpClient->getRequestsCount())->toBe(1);
})->with(['registerDomain', 'transferDomain', 'modifyContact']);
