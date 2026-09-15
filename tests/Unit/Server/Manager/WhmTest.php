<?php

declare(strict_types=1);

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

function createWhmManager(HttpClientInterface $httpClient): Server_Manager_Whm
{
    return new class(['host' => 'whm.example.com', 'username' => 'admin', 'password' => 'secret'], $httpClient) extends Server_Manager_Whm {
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

function createWhmAccount(Server_Package $package, bool $reseller): Server_Account
{
    return (new Server_Account())
        ->setUsername('example')
        ->setDomain('example.com')
        ->setPassword('secret')
        ->setClient((new Server_Client())->setEmail('client@example.com'))
        ->setPackage($package)
        ->setReseller($reseller);
}

function actionOf(array $request): string
{
    return basename((string) parse_url($request['url'], PHP_URL_PATH));
}

function createWhmAccountCreationClient(array &$requests): MockHttpClient
{
    return new MockHttpClient(function (string $method, string $url, array $options) use (&$requests): MockResponse {
        $requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

        if (str_contains($url, 'listpkgs')) {
            return new MockResponse(json_encode(['package' => []]));
        }

        if (str_contains($url, 'createacct')) {
            return new MockResponse(json_encode(['result' => [['status' => 1]]]));
        }

        return new MockResponse(json_encode(['status' => 1]));
    });
}

test('createAccount sets up the reseller but does not touch the ACL list when the plan has no acl custom value', function (): void {
    $requests = [];
    $manager = createWhmManager(createWhmAccountCreationClient($requests));
    $account = createWhmAccount(new Server_Package(), true);

    expect($manager->createAccount($account))->toBeTrue();

    $actions = array_map(actionOf(...), $requests);

    // Calling "setacls" with an empty acllist would strip the initial privileges "setupreseller"
    // just granted, so it must be skipped when the plan has no 'acl' custom value.
    expect($actions)->toBe(['listpkgs', 'addpkg', 'createacct', 'setupreseller'])
        ->and($actions)->not->toContain('setacls');
});

test('createAccount assigns the plan\'s acl custom value to the reseller via setacls', function (): void {
    $requests = [];
    $manager = createWhmManager(createWhmAccountCreationClient($requests));
    $package = (new Server_Package())->setCustomValues(['acl' => 'reseller_basic']);
    $account = createWhmAccount($package, true);

    expect($manager->createAccount($account))->toBeTrue();

    $actions = array_map(actionOf(...), $requests);
    expect($actions)->toBe(['listpkgs', 'addpkg', 'createacct', 'setupreseller', 'setacls']);

    $setaclsRequest = $requests[array_search('setacls', $actions, true)];
    parse_str((string) $setaclsRequest['options']['body'], $fields);

    expect($fields)->toMatchArray([
        'reseller' => 'example',
        'acllist' => 'reseller_basic',
    ]);
});

test('createAccount never calls setupreseller or setacls for a non-reseller account', function (): void {
    $requests = [];
    $manager = createWhmManager(createWhmAccountCreationClient($requests));
    $package = (new Server_Package())->setCustomValues(['acl' => 'reseller_basic']);
    $account = createWhmAccount($package, false);

    expect($manager->createAccount($account))->toBeTrue();

    $actions = array_map(actionOf(...), $requests);
    expect($actions)->toBe(['listpkgs', 'addpkg', 'createacct']);
});
