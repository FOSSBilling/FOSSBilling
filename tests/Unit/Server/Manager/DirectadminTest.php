<?php

declare(strict_types=1);

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

function invokeDirectadminParseResponse(Server_Manager_Directadmin $manager, string $data): array
{
    $reflection = new ReflectionClass($manager);
    $method = $reflection->getMethod('parseResponse');

    return $method->invokeArgs($manager, [$data]);
}

function createDirectadminManager(HttpClientInterface $httpClient): Server_Manager_Directadmin
{
    return new class(['host' => 'directadmin.example.com', 'username' => 'admin', 'password' => 'secret'], $httpClient) extends Server_Manager_Directadmin {
        public function __construct(array $options, private readonly HttpClientInterface $httpClient)
        {
            parent::__construct($options);
        }

        public function getHttpClient(): HttpClientInterface
        {
            return $this->httpClient;
        }
    };
}

beforeEach(function (): void {
    $this->manager = new Server_Manager_Directadmin([
        'host' => 'directadmin.example.com',
        'username' => 'admin',
        'password' => 'secret',
    ]);
});

test('parseResponse decodes the fully-terminated apostrophe entity without a trailing semicolon', function (): void {
    $result = invokeDirectadminParseResponse($this->manager, 'name=O&#39;Brien');

    expect($result['name'])->toBe("O'Brien");
});

test('parseResponse decodes the legacy unterminated apostrophe entity', function (): void {
    $result = invokeDirectadminParseResponse($this->manager, 'name=O&#39Brien');

    expect($result['name'])->toBe("O'Brien");
});

test('modifyAccount sends custom package values to DirectAdmin', function (): void {
    $requests = [];
    $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests): MockResponse {
        $requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

        return new MockResponse('');
    });
    $manager = createDirectadminManager($httpClient);
    $package = (new Server_Package())
        ->setBandwidth('1024')
        ->setQuota('2048')
        ->setMaxDomains('3')
        ->setMaxSubdomains('4')
        ->setMaxParkedDomains('5')
        ->setMaxFtp('6')
        ->setMaxSql('7')
        ->setMaxPop('8')
        ->setCustomValues([
            'aftp' => '1',
            'catchall' => 'false',
            'cgi' => 'yes',
            'cron' => 'true',
            'nemailf' => '5',
            'nemailml' => 'unlimited',
            'nemailr' => '7',
            'php' => 'on',
            'spam' => 'false',
            'ssh' => '1',
            'ssl' => 'yes',
        ]);
    $account = (new Server_Account())
        ->setUsername('example')
        ->setNs1('ns1.example.com')
        ->setNs2('ns2.example.com')
        ->setPackage($package);

    expect($manager->modifyAccount($account))->toBeTrue()
        ->and($requests)->toHaveCount(1);

    parse_str((string) parse_url($requests[0]['url'], PHP_URL_QUERY), $fields);

    expect($fields)->toMatchArray([
        'action' => 'customize',
        'aftp' => 'ON',
        'catchall' => 'OFF',
        'cgi' => 'ON',
        'cron' => 'ON',
        'dnscontrol' => 'ON',
        'nemailf' => '5',
        'nemailml' => 'unlimited',
        'nemailr' => '7',
        'php' => 'ON',
        'spam' => 'OFF',
        'ssh' => 'ON',
        'ssl' => 'ON',
        'sysinfo' => 'ON',
        'unemailml' => 'ON',
        'user' => 'example',
    ]);
});

test('modifyAccount honors explicit false DNS and system information permissions', function (): void {
    $requests = [];
    $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests): MockResponse {
        $requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

        return new MockResponse('');
    });
    $manager = createDirectadminManager($httpClient);
    $package = (new Server_Package())->setCustomValues([
        'dnscontrol' => 'false',
        'sysinfo' => '0',
    ]);
    $account = (new Server_Account())
        ->setUsername('example')
        ->setPackage($package);

    expect($manager->modifyAccount($account))->toBeTrue();

    parse_str((string) parse_url($requests[0]['url'], PHP_URL_QUERY), $fields);

    expect($fields['dnscontrol'])->toBe('OFF')
        ->and($fields['sysinfo'])->toBe('OFF');
});
