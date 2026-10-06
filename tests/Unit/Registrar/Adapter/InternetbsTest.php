<?php

declare(strict_types=1);

// cspell:words dotfrcontactentityname expirationdate phonenumber postalcode

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

function createInternetbsAdapter(HttpClientInterface $httpClient): Registrar_Adapter_Internetbs
{
    return new class($httpClient) extends Registrar_Adapter_Internetbs {
        public function __construct(private readonly HttpClientInterface $httpClient)
        {
            parent::__construct(['apikey' => 'test-key', 'password' => 'test-password']);
        }

        public function getHttpClient(): HttpClientInterface
        {
            return $this->httpClient;
        }
    };
}

test('Internetbs reads French registrant names with and without leading TLD dots', function (string $tld, ?string $entityName, string $expectedName): void {
    $fields = ['status=SUCCESS', 'expirationdate=2030-10-06', 'contacts_registrant_firstname=Test', 'contacts_registrant_lastname=Registrant'];
    if ($entityName !== null) {
        $fields[] = 'contacts_registrant_dotfrcontactentityname=' . $entityName;
    }
    $adapter = createInternetbsAdapter(new MockHttpClient(new MockResponse(implode("\n", $fields))));
    $domain = (new Registrar_Domain())->setSld('example')->setTld($tld);

    expect($adapter->getDomainDetails($domain)->getContactRegistrar()->getName())->toBe($expectedName);
})->with([
    'dotted French TLD' => ['.fr', 'French Entity', 'French Entity'],
    'French TLD without leading dot' => ['fr', 'French Entity', 'French Entity'],
    'missing French entity name' => ['.fr', null, 'Test Registrant'],
    'other TLD uses ordinary name' => ['.com', 'French Entity', 'Test Registrant'],
]);

test('Internetbs normalizes stored phone numbers for every contact role', function (string $operation): void {
    $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
        parse_str($options['body'], $params);
        foreach (['Registrant', 'Admin', 'Technical', 'Billing'] as $role) {
            expect($params[$role . '_PhoneNumber'])->toBe('+225.0100000000');
        }

        return new MockResponse("status=SUCCESS\nproduct_0_status=SUCCESS");
    });
    $adapter = createInternetbsAdapter($httpClient);
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

test('Internetbs reads expiration dates when registrant fields are missing', function (array $contactFields): void {
    $response = implode("\n", ['status=SUCCESS', 'expirationdate=2030-10-06', 'nameserver_0=ns1.example.com']);
    foreach ($contactFields as $field => $value) {
        $response .= "\ncontacts_registrant_{$field}={$value}";
    }
    $adapter = createInternetbsAdapter(new MockHttpClient(new MockResponse($response)));
    $domain = (new Registrar_Domain())->setSld('example')->setTld('.com');

    set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    try {
        $result = $adapter->getDomainDetails($domain);
    } finally {
        restore_error_handler();
    }
    $contact = $result->getContactRegistrar();

    expect($result)->toBe($domain)
        ->and($result->getExpirationTime())->toBe(strtotime('2030-10-06'))
        ->and($result->getNs1())->toBe('ns1.example.com')
        ->and($contact->getAddress2())->toBe('')
        ->and($contact->getAddress3())->toBe('')
        ->and($contact->getCountry())->toBe('')
        ->and($contact->getEmail())->toBe($contactFields['email'] ?? '')
        ->and($contact->getTel())->toBe(($contactFields['phonenumber'] ?? '') === '+225.0100000000' ? '0100000000' : '');
})->with([
    'missing optional address fields' => [['firstname' => 'Test', 'email' => 'test@example.com', 'phonenumber' => '+225.0100000000', 'street' => 'Example Street', 'city' => 'Example City', 'postalcode' => '12345']],
    'missing all contact fields' => [[]],
    'phone without separator' => [['phonenumber' => '+225']],
]);
