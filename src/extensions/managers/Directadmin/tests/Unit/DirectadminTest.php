<?php

declare(strict_types=1);

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

function invokeDirectadminParseResponse(FOSSBilling\Extension\Manager\Directadmin\Directadmin $manager, string $data): array
{
    $reflection = new ReflectionClass($manager);
    $method = $reflection->getMethod('parseResponse');

    return $method->invokeArgs($manager, [$data]);
}

function createDirectadminManager(HttpClientInterface $httpClient): FOSSBilling\Extension\Manager\Directadmin\Directadmin
{
    return new class(['host' => 'directadmin.example.com', 'username' => 'admin', 'password' => 'secret'], $httpClient) extends FOSSBilling\Extension\Manager\Directadmin\Directadmin {
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
    $this->manager = new FOSSBilling\Extension\Manager\Directadmin\Directadmin([
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

test('suspendAccount sends the suspension reason to DirectAdmin', function (): void {
    $requests = [];
    $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests): MockResponse {
        $requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

        return new MockResponse(str_contains($url, 'CMD_API_SHOW_USER_CONFIG') ? 'suspended=no' : '');
    });
    $manager = createDirectadminManager($httpClient);
    $account = (new FOSSBilling\Extension\Contract\Server\Account())
        ->setUsername('example')
        ->setNote('Non-payment');

    expect($manager->suspendAccount($account))->toBeTrue()
        ->and($requests)->toHaveCount(2);

    parse_str((string) parse_url($requests[1]['url'], PHP_URL_QUERY), $fields);

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
    $account = (new FOSSBilling\Extension\Contract\Server\Account())
        ->setUsername('example')
        ->setNote('Terms of service violation');

    expect($manager->suspendAccount($account))->toBeTrue();

    parse_str((string) parse_url($requests[1]['url'], PHP_URL_QUERY), $fields);

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
    $account = (new FOSSBilling\Extension\Contract\Server\Account())
        ->setUsername('example')
        ->setNote($reason);

    expect($manager->suspendAccount($account))->toBeTrue()
        ->and($requests)->toHaveCount(2);

    parse_str((string) parse_url($requests[1]['url'], PHP_URL_QUERY), $fields);

    expect($fields)->not->toHaveKey('reason')
        ->not->toHaveKey('details');
})->with([null, '']);
