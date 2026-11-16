<?php

declare(strict_types=1);

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Service;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Process\Process;

use function Tests\Helpers\container;

test('shared invoice authorization enforces staff view permissions under the web SAPI', function (): void {
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $server = new Process([PHP_BINARY, '-S', $address, PATH_TESTS . '/Fixtures/invoice-auth.php'], dirname(PATH_TESTS));
    $server->start();

    try {
        expect($server->waitUntil(fn ($type, $output): bool => str_contains($output, 'Development Server')))->toBeTrue();
        $http = HttpClient::create();
        foreach (['read', 'payment'] as $operation) {
            foreach ([
                [['role' => 'staff', 'allowed' => '0', 'hash' => '1'], 403, 1],
                [['role' => 'staff', 'allowed' => '1'], 200, 1],
                [['role' => 'owner', 'expired' => '1'], 200, 0],
                [['role' => 'guest', 'hash' => '1'], 200, 0],
                [['role' => 'guest', 'hash' => '1', 'expired' => '1'], 403, 0],
                [['role' => 'guest', 'hash' => '0'], 403, 0],
            ] as [$query, $status, $checks]) {
                $response = $http->request('GET', 'http://' . $address, ['query' => $query + ['operation' => $operation]]);
                $this->assertSame(200, $response->getStatusCode(), $server->getErrorOutput() . $response->getContent(false));
                $result = $response->toArray();
                expect($result)->toBe(['status' => $status, 'checks' => $checks, 'cli' => false]);
            }
        }
    } finally {
        $server->stop();
    }
});

test('shared invoice authorization retains the CLI exemption', function (): void {
    $service = new Service();
    $service->setDi(container());
    $service->checkInvoiceAuth(new Invoice());
    expect(FOSSBilling\Core\System\Environment::isCLI())->toBeTrue();
});
