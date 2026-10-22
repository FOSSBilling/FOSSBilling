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

test('password changes keep credentials in the POST body', function (): void {
    $requests = [];
    $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests): MockResponse {
        $requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

        return new MockResponse('error=0');
    });
    $manager = createDirectadminManager($httpClient);
    $account = (new Server_Account())->setUsername('example');
    $password = 'test-password@123';

    expect($manager->changeAccountPassword($account, $password))->toBeTrue()
        ->and($requests)->toHaveCount(1)
        ->and($requests[0]['method'])->toBe('POST')
        ->and(parse_url($requests[0]['url'], PHP_URL_QUERY))->toBeNull();

    parse_str($requests[0]['options']['body'], $fields);
    expect($fields)->toBe(['username' => 'example', 'passwd' => $password, 'passwd2' => $password]);
});

test('failed password changes do not expose credentials in logs or transport errors', function (): void {
    $httpClient = new MockHttpClient(static fn (string $method, string $url): MockResponse => new MockResponse('', [
        'error' => 'Could not connect to server for "' . $url . '".',
    ]));
    $manager = createDirectadminManager($httpClient);
    $logger = new Tests\Helpers\TestLogger();
    $manager->setLog($logger);
    $password = 'test-password@123';

    try {
        $manager->changeAccountPassword((new Server_Account())->setUsername('example'), $password);
        test()->fail('Expected a transport error');
    } catch (Server_Exception $exception) {
        expect($exception->getMessage())->toContain('Could not connect to server')
            ->not->toContain($password)
            ->not->toContain(urlencode($password))
            ->not->toContain('passwd=');
    }

    $logs = json_encode($logger->calls, JSON_THROW_ON_ERROR);
    expect($logs)->not->toContain($password)
        ->not->toContain(urlencode($password))
        ->not->toContain('passwd=');
});

test('GET requests retain query parameters', function (): void {
    $requests = [];
    $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests): MockResponse {
        $requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

        return new MockResponse('suspended=no');
    });
    $manager = createDirectadminManager($httpClient);
    $method = (new ReflectionClass($manager))->getMethod('request');

    expect($method->invoke($manager, 'API_SHOW_USER_CONFIG', ['user' => 'example'], false))
        ->toBe(['suspended' => 'no'])
        ->and($requests[0]['method'])->toBe('GET')
        ->and(parse_url($requests[0]['url'], PHP_URL_QUERY))->toBe('user=example');
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

    parse_str($requests[0]['options']['body'], $fields);

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

    parse_str($requests[0]['options']['body'], $fields);

    expect($fields['dnscontrol'])->toBe('OFF')
        ->and($fields['sysinfo'])->toBe('OFF');
});

test('suspendAccount sends the suspension reason to DirectAdmin', function (): void {
    $requests = [];
    $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests): MockResponse {
        $requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

        return new MockResponse(str_contains($url, 'CMD_API_SHOW_USER_CONFIG') ? 'suspended=no' : '');
    });
    $manager = createDirectadminManager($httpClient);
    $account = (new Server_Account())
        ->setUsername('example')
        ->setNote('Non-payment');

    expect($manager->suspendAccount($account))->toBeTrue()
        ->and($requests)->toHaveCount(2);

    parse_str($requests[1]['options']['body'], $fields);

    expect($requests[1]['method'])->toBe('POST')
        ->and($requests[1]['url'])->toContain('CMD_API_SELECT_USERS')
        ->and($fields['reason'])->toBe('billing')
        ->and($fields['details'])->toBe('Non-payment');
});

test('suspendAccount maps a custom suspension note to other', function (): void {
    $requests = [];
    $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests): MockResponse {
        $requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

        return new MockResponse(str_contains($url, 'CMD_API_SHOW_USER_CONFIG') ? 'suspended=no' : '');
    });
    $manager = createDirectadminManager($httpClient);
    $account = (new Server_Account())
        ->setUsername('example')
        ->setNote('Terms of service violation');

    expect($manager->suspendAccount($account))->toBeTrue();

    parse_str($requests[1]['options']['body'], $fields);

    expect($fields['reason'])->toBe('other')
        ->and($fields['details'])->toBe('Terms of service violation');
});

test('suspendAccount omits an empty suspension reason and details', function (?string $reason): void {
    $requests = [];
    $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests): MockResponse {
        $requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

        return new MockResponse(str_contains($url, 'CMD_API_SHOW_USER_CONFIG') ? 'suspended=no' : '');
    });
    $manager = createDirectadminManager($httpClient);
    $account = (new Server_Account())
        ->setUsername('example')
        ->setNote($reason);

    expect($manager->suspendAccount($account))->toBeTrue()
        ->and($requests)->toHaveCount(2);

    parse_str($requests[1]['options']['body'], $fields);

    expect($fields)->not->toHaveKey('reason')
        ->not->toHaveKey('details');
})->with([null, '']);
