<?php

declare(strict_types=1);

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

function createCwpManager(string $body, int $statusCode = 200): Server_Manager_CWP
{
    $client = new MockHttpClient(new MockResponse($body, ['http_code' => $statusCode]));

    return new class($client) extends Server_Manager_CWP {
        public function __construct(private readonly HttpClientInterface $httpClient)
        {
            parent::__construct(['ip' => '127.0.0.1', 'host' => 'cwp.example.com', 'accesshash' => 'test-key']);
            $this->setLog(new Psr\Log\NullLogger());
        }

        public function getHttpClient(): HttpClientInterface
        {
            return $this->httpClient;
        }
    };
}

test('CWP rejects unsuccessful or incomplete account details', function (array $response): void {
    $manager = createCwpManager(json_encode($response));
    $account = (new Server_Account())->setUsername('example')->setSuspended(true)->setPackage((new Server_Package())->setName('existing'));

    set_error_handler(static function (int $severity, string $message): never {
        throw new ErrorException($message, 0, $severity);
    });

    try {
        expect(fn () => $manager->synchronizeAccount($account))->toThrow(Server_Exception::class, 'CWP did not return valid account details');
    } finally {
        restore_error_handler();
    }

    expect($account->getSuspended())->toBeTrue()
        ->and($account->getPackage()->getName())->toBe('existing');
})->with([
    'API error' => [['status' => 'Error', 'msg' => 'Account unavailable']],
    'null result' => [['status' => 'OK', 'result' => null]],
    'missing account' => [['status' => 'OK', 'result' => []]],
    'missing state' => [['status' => 'OK', 'result' => ['account_info' => ['package_name' => 'basic']]]],
    'missing package' => [['status' => 'OK', 'result' => ['account_info' => ['state' => 'active']]]],
    'invalid package' => [['status' => 'OK', 'result' => ['account_info' => ['state' => 'active', 'package_name' => []]]]],
]);

test('CWP reports invalid JSON as a server error', function (string $body): void {
    $manager = createCwpManager($body);

    expect(fn () => $manager->testConnection())->toThrow(Server_Exception::class, 'The CWP server returned an invalid JSON response');
})->with(['empty' => '', 'HTML' => '<html>Not found</html>']);

test('CWP substitutes the action and server name when suspension fails', function (): void {
    $manager = createCwpManager(json_encode(['status' => 'Error', 'msg' => 'Account unavailable']));
    $account = (new Server_Account())->setUsername('example');

    expect(fn () => $manager->suspendAccount($account))->toThrow(
        Server_Exception::class,
        'Failed to suspend account on the CWP server, check the error logs for further details',
    );
});

test('CWP reports HTTP errors separately from invalid JSON', function (int $statusCode): void {
    $manager = createCwpManager('<html>Request failed</html>', $statusCode);

    expect(fn () => $manager->testConnection())->toThrow(Server_Exception::class, 'The CWP server returned HTTP status ' . $statusCode);
})->with([403, 503]);

test('CWP synchronizes valid account details', function (string $state, bool $suspended): void {
    $manager = createCwpManager(json_encode(['status' => 'OK', 'result' => ['account_info' => [
        'state' => $state, 'package_name' => 'basic', 'reseller' => '1',
    ]]]));
    $account = (new Server_Account())->setUsername('example');

    $updated = $manager->synchronizeAccount($account);

    expect($updated)->not->toBe($account)
        ->and($updated->getSuspended())->toBe($suspended)
        ->and($updated->getPackage()->getName())->toBe('basic')
        ->and($updated->getReseller())->toBeTrue();
})->with([['active', false], ['suspended', true]]);
