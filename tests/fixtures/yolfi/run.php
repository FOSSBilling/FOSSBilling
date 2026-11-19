<?php

declare(strict_types=1);

// Standalone offline contract tests. Only HTTP, DI, Client, ORM repositories and
// the invoice settlement service are doubles; the adapter and invoice entities are real.
namespace Pimple {
    class Container extends \ArrayObject
    {
    }
}
namespace Symfony\Contracts\HttpClient {
    interface HttpClientInterface
    {
        public function request(string $method, string $url, array $options = []): object;
    }
}
namespace Box\Mod\Client\Entity {
    class Client
    {
        public string $currency = 'USD';
        public function getId(): int
        {
            return 7;
        }
        public function getCurrency(): string
        {
            return $this->currency;
        }
    }
}
namespace {
    use Box\Mod\Invoice\Entity\Invoice;
    use Box\Mod\Invoice\Entity\PayGateway;
    use Box\Mod\Invoice\Entity\Transaction;
    $root = dirname(__DIR__, 3);
    define('DEBUG', false);
    define('PATH_CONFIG', __DIR__ . '/host-settings.php');
    // Load only the declared Composer dependencies used by this standalone fixture.
    // Avoid application bootstrap/autoload side effects; HTTP and DI remain doubles.
    foreach ([
        Symfony\Component\Filesystem\Filesystem::class => 'filesystem/Filesystem.php',
        Symfony\Contracts\HttpClient\Exception\ExceptionInterface::class => 'http-client-contracts/Exception/ExceptionInterface.php',
        Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface::class => 'http-client-contracts/Exception/TransportExceptionInterface.php',
        Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface::class => 'http-client-contracts/Exception/TimeoutExceptionInterface.php',
    ] as $symbol => $dependency) {
        if (class_exists($symbol) || interface_exists($symbol)) {
            continue;
        }
        $file = $root . '/src/vendor/symfony/' . $dependency;
        if (!is_file($file)) {
            throw new RuntimeException('Offline Yolfi fixture requires the upstream Composer dependencies in src/vendor; see composer.json and the Yolfi test guide.');
        }
        require_once $file;
    }
    if (!function_exists('proc_open')) {
        throw new RuntimeException('Offline Yolfi fixture requires PHP proc_open plus Python 3 with stdlib sqlite3 (test-only); see the Yolfi test guide.');
    }
    $python = getenv('YOLFI_TEST_PYTHON');
    define('YOLFI_TEST_PYTHON', $python === false || $python === '' ? 'python3' : $python);
    $probe = @proc_open([YOLFI_TEST_PYTHON, '-c', 'import sys, sqlite3; assert sys.version_info.major == 3; sqlite3.connect(":memory:").execute("SELECT 1")'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($probe)) {
        throw new RuntimeException('Offline Yolfi fixture requires Python 3 with stdlib sqlite3; set YOLFI_TEST_PYTHON to its executable (not a shell command).');
    }
    fclose($pipes[0]);
    stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($probe) !== 0) {
        throw new RuntimeException('Offline Yolfi fixture requires Python 3 with stdlib sqlite3; set YOLFI_TEST_PYTHON to its executable (not a shell command).');
    }
    require $root . '/src/core/System/Config.php';
    require $root . '/src/core/Exception/BaseException.php';
    require $root . '/src/core/Exception/InformationException.php';
    require $root . '/src/library/Payment/Exception.php';
    require $root . '/src/core/Doctrine/TimestampInterface.php';
    require $root . '/src/core/Doctrine/TimestampTrait.php';
    require $root . '/src/core/Container/InjectionAwareInterface.php';
    require $root . '/src/core/Payment/JsonWebhookAuthenticatorInterface.php';
    require $root . '/src/library/Payment/AdapterAbstract.php';
    foreach (['Invoice', 'PayGateway', 'Transaction'] as $entity) {
        require $root . '/src/modules/Invoice/Entity/' . $entity . '.php';
    }
    if (!is_file($root . '/src/library/Payment/Adapter/Yolfi.php')) {
        throw new RuntimeException('RED: missing Yolfi payment adapter');
    }
    require $root . '/src/library/Payment/Adapter/Yolfi.php';
    require $root . '/src/modules/Invoice/ServicePayGateway.php';
    require $root . '/src/modules/Invoice/ServiceTransaction.php';
    require $root . '/src/modules/Invoice/Exception/RetryableCallbackException.php';
    class SyntheticTimeout extends RuntimeException implements Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface
    {
    }

    function check(bool $value, string $message): void
    {
        if (!$value) {
            throw new RuntimeException($message);
        }
    }
    function rejects(callable $operation, string $message = 'Expected payment rejection'): void
    {
        try {
            $operation();
        } catch (Payment_Exception $e) {
            return;
        }
        throw new RuntimeException($message);
    }
    function identify(object $entity, int $id): object
    {
        (new ReflectionProperty($entity, 'id'))->setValue($entity, $id);
        return $entity;
    }
    class FakeHttp implements Symfony\Contracts\HttpClient\HttpClientInterface
    {
        public array $calls = [];
        public array $queue = [];
        public function request(string $method, string $url, array $options = []): object
        {
            $this->calls[] = [$method, $url, $options];
            $next = array_shift($this->queue);
            if ($next instanceof Throwable) {
                throw $next;
            }
            check(is_array($next), 'Unexpected HTTP request');
            return new class($next) {
                public function __construct(private array $fixture)
                {
                }
                public function getStatusCode(): int
                {
                    if (($this->fixture['status_error'] ?? null) instanceof Throwable) {
                        throw $this->fixture['status_error'];
                    }
                    return $this->fixture['status'] ?? 200;
                }
                public function getContent(bool $throw = true): string
                {
                    if (($this->fixture['content_error'] ?? null) instanceof Throwable) {
                        throw $this->fixture['content_error'];
                    }
                    return $this->fixture['raw'] ?? json_encode(['success' => true, 'data' => $this->fixture['data']], JSON_THROW_ON_ERROR);
                }
            };
        }
    }
    class TestAdapter extends Payment_Adapter_Yolfi
    {
        public FakeHttp $http;
        public function getHttpClient(): Symfony\Contracts\HttpClient\HttpClientInterface
        {
            return $this->http;
        }
    }
    class FakeEntityManager
    {
        public array $entities = [];
        public array $removed = [];
        public function getRepository(string $class): object
        {
            return new class($this, $class) {
                public function __construct(private FakeEntityManager $em, private string $class)
                {
                }
                public function find(int $id): ?object
                {
                    return $this->em->entities[$this->class][$id] ?? null;
                }
            };
        }
        public function refresh(object $entity): void
        {
        }
        public function flush(): void
        {
        }
        public function remove(object $entity): void
        {
            $this->removed[] = $entity;
        }
    }
    class FakeInvoiceService
    {
        public float $total = 24.99;
        public float $amountPaid = 0.0;
        public array $receipts = [];
        public array $calls = [];
        public int $credits = 0;
        public bool $failSettlement = false;
        public function getTotalWithTax(Invoice $invoice): float
        {
            return $this->total;
        }
        public function getAmountPaid(Invoice $invoice): float
        {
            return $this->amountPaid;
        }
        // Models only the inspected recordGatewayPayment contract, not its database implementation.
        public function recordGatewayPayment(Transaction $tx, object $client, string $reference, string $description, string $legacyType, ?string $legacyRelId = null, ?callable $prepareSettlement = null): bool
        {
            $this->calls[] = [$tx, $reference, $description, $legacyType];
            if (isset($this->receipts[$reference])) {
                return $this->receipts[$reference] === $tx->getId();
            }
            if ($prepareSettlement !== null) {
                $prepareSettlement();
            }
            if ($this->failSettlement) {
                throw new Payment_Exception('Synthetic settlement failure');
            }
            $this->receipts[$reference] = $tx->getId();
            ++$this->credits;
            $tx->getInvoice()->setStatus(Invoice::STATUS_PAID);
            $tx->setStatus(Transaction::STATUS_PROCESSED);
            return true;
        }
    }
    function fixture(array $config = []): array
    {
        $gateway = identify((new PayGateway())->setGateway('Yolfi'), 3);
        $invoice = identify((new Invoice())->setCurrency('USD')->setClientId(7)->setHash('test-invoice-hash')->setGateway($gateway), 42);
        $tx = identify((new Transaction())->setGateway($gateway), 81);
        $em = new FakeEntityManager();
        $em->entities = [Invoice::class => [42 => $invoice], Transaction::class => [81 => $tx], Box\Mod\Client\Entity\Client::class => [7 => new Box\Mod\Client\Entity\Client()]];
        $service = new FakeInvoiceService();
        $defaults = [
        'gateway_id' => 3,
        'return_url' => 'https://example.com/return', 'cancel_url' => 'https://example.com/cancel',
        'notify_url' => 'https://example.com/ipn.php?gateway_id=3', 'redirect_url' => 'https://example.com/return',
        ];
        $defaults['api_key'] = 'EXAMPLE_API_KEY';
        $defaults['webhook_secret'] = 'EXAMPLE_WEBHOOK_SECRET';
        $adapter = new TestAdapter(array_replace($defaults, $config));
        $adapter->http = new FakeHttp();
        $adapter->setDi(new Pimple\Container(['em' => $em, 'mod_service' => fn ($module) => $service]));
        return [$adapter, $invoice, $tx, $service, $em];
    }
    function snapshot(array $overrides = []): array
    {
        return array_replace([
        'id' => 'ycs_test_session', 'object' => 'checkout_session', 'status' => 'COMPLETED',
        'amount' => '24.99', 'currency' => 'USD', 'merchantOrderId' => 'fossbilling:3:42:test-invoice-hash',
        'url' => 'https://pay.yolfi.com/ycs_test_session',
        ], $overrides);
    }
    function event(array $overrides = [], array $data = []): array
    {
        return array_replace([
        'id' => 'evt_test_confirmation', 'type' => 'payment.confirmed', 'livemode' => true,
        'data' => array_replace([
        'checkoutSessionId' => 'ycs_test_session', 'invoiceId' => '550e8400-e29b-41d4-a716-446655440000',
        'merchantOrderId' => 'fossbilling:3:42:test-invoice-hash', 'status' => 'confirmed', 'paymentType' => 'ONE_TIME',
        // This token amount and USD conversion deliberately differ from the fiat price.
        'amount' => '0.00042', 'amountUsd' => '26.00', 'currency' => 'USD',
        ], $data),
        ], $overrides);
    }
    function signed(array $event, string $secret = 'EXAMPLE_WEBHOOK_SECRET'): array
    {
        $raw = json_encode($event, JSON_THROW_ON_ERROR);
        return ['http_raw_post_data' => $raw, 'server' => ['HTTP_X_YOLFI_SIGNATURE' => base64_encode(hash_hmac('sha256', $raw, $secret, true))]];
    }
    // Test-only Python SQLite transport: execute the real host SQL unchanged, without PDO.
    class NativeSqliteConnection
    {
        private string $path;
        public function __construct()
        {
            $this->path = tempnam(sys_get_temp_dir(), 'yolfi-sqlite-');
            $this->executeStatement('CREATE TABLE "transaction" (id INTEGER PRIMARY KEY, status TEXT, gateway_completion_state INTEGER, error TEXT, error_code INTEGER, updated_at TEXT, processing_token TEXT)');
            $this->executeStatement('INSERT INTO "transaction" (id, status, gateway_completion_state, processing_token) VALUES (81, ?, 0, ?)', ['processing', str_repeat('a', 32)]);
        }
        public function __destruct()
        {
            unlink($this->path);
        }
        public function quoteSingleIdentifier(string $name): string
        {
            return '"' . str_replace('"', '""', $name) . '"';
        }
        private function query(string $sql, array $params): array
        {
            $process = proc_open([YOLFI_TEST_PYTHON, __DIR__ . '/sqlite_bridge.py', $this->path], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
            check(is_resource($process), 'SQLite bridge did not start');
            fwrite($pipes[0], json_encode(['sql' => $sql, 'params' => $params], JSON_THROW_ON_ERROR));
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            check(proc_close($process) === 0, 'SQLite bridge failed: ' . $error);
            return json_decode($output, true, 32, JSON_THROW_ON_ERROR);
        }
        public function executeStatement(string $sql, array $params = []): int
        {
            return $this->query($sql, $params)['rowcount'];
        }
        public function fetchAllAssociative(string $sql, array $params = []): array
        {
            return $this->query($sql, $params)['rows'];
        }
    }
    function hostErrorState(Throwable $error): array
    {
        $db = new NativeSqliteConnection();
        $em = new class($db) {
            public function __construct(private NativeSqliteConnection $db)
            {
            }
            public function getConnection(): NativeSqliteConnection
            {
                return $this->db;
            }
            public function isOpen(): bool
            {
                return false;
            }
        };
        $host = new Box\Mod\Invoice\ServiceTransaction();
        $host->setDi(new Pimple\Container(['em' => $em, 'logger' => new class {
            public function error(string $message, array $context): void
            {
            }
        }]));
        (new ReflectionProperty($host, 'processingTokens'))->setValue($host, [81 => str_repeat('a', 32)]);
        (new ReflectionMethod($host, 'markTransactionError'))->invoke($host, 81, $error);
        $row = $db->fetchAllAssociative('SELECT * FROM "transaction" WHERE id = 81')[0];
        return [$row, $host->getReceived()];
    }
    function confirmationFailure(TestAdapter $a, ?array $input = null): Throwable
    {
        try {
            $a->processTransaction(null, 81, $input ?? signed(event()), 3);
        } catch (FOSSBilling\Core\Exception\BaseException $error) {
            check(!str_contains($error->getMessage(), 'EXAMPLE_API_KEY') && !str_contains($error->getMessage(), 'EXAMPLE_WEBHOOK_SECRET'), 'Credential in callback error');
            check($error->getPrevious() === null, 'Raw transport exception retained');
            return $error;
        }
        throw new RuntimeException('Expected callback failure');
    }
    $tests = [];
    $tests['native callback error SQL keeps only transient authenticated lookups retry eligible'] = function (): void {
        foreach ([new SyntheticTimeout('EXAMPLE_API_KEY EXAMPLE_WEBHOOK_SECRET'), ['status_error' => new SyntheticTimeout('EXAMPLE_API_KEY')], ['content_error' => new SyntheticTimeout('EXAMPLE_WEBHOOK_SECRET')], ['status' => 429], ['status' => 500], ['status' => 503], ['status' => 599]] as $reply) {
            [$a, , , $service] = fixture();
            $a->http->queue = [$reply];
            $error = confirmationFailure($a);
            [$row, $received] = hostErrorState($error);
            check($row['status'] === 'received' && array_column($received, 'id') === [81], 'Transient confirmation failure must remain in native getReceived retry selection; got ' . $row['status']);
            check($error instanceof Box\Mod\Invoice\Exception\RetryableCallbackException && $error->getCode() === 503, 'Missing native retryable exception contract');
            check($service->credits === 0 && $a->http->calls[0][0] === 'GET' && $a->http->calls[0][2]['auth_bearer'] === 'EXAMPLE_API_KEY', 'Retry failure was not an authenticated GET without settlement effects');
            check($row['error'] === $error->getMessage() && $row['error_code'] === 503, 'Native retry row lost sanitized diagnostics');
            $a->http->queue = [['data' => snapshot()]];
            $a->processTransaction(null, 81, signed(event()), 3);
            check($service->credits === 1, 'Eligible retry could not recover');
        }
    };
    $tests['native callback error SQL excludes permanent failures and unverified callbacks'] = function (): void {
        $cases = [['status' => 400], ['status' => 401], ['status' => 403], ['status' => 404], ['status' => 408], ['status' => 409], ['status' => 422], ['status' => 302], ['raw' => 'not-json'], ['raw' => '{"success":false,"data":{}}'], new RuntimeException('EXAMPLE_API_KEY'), ['data' => snapshot(['merchantOrderId' => 'wrong'])], ['data' => snapshot(['amount' => '25.00'])], ['data' => snapshot(['currency' => 'EUR'])], ['data' => snapshot(['id' => 'ycs_other'])], ['bad_signature' => true]];
        foreach ($cases as $reply) {
            [$a, , , $service] = fixture();
            $a->http->queue = [$reply];
            $input = signed(event(), (is_array($reply) && isset($reply['bad_signature'])) ? 'wrong-secret' : 'EXAMPLE_WEBHOOK_SECRET');
            $error = confirmationFailure($a, $input);
            [$row, $received] = hostErrorState($error);
            check($error instanceof Payment_Exception && !($error instanceof Box\Mod\Invoice\Exception\RetryableCallbackException), 'Permanent failure made retryable');
            check($row['status'] === 'error' && $received === [], 'Permanent failure left in native retry queue');
            check($service->credits === 0, 'Permanent failure credited');
            if (is_array($reply) && isset($reply['bad_signature'])) {
                check($a->http->calls === [], 'Unauthenticated callback performed confirmation GET');
            }
        }
    };
    $tests['checkout POST failures never become retryable callback exceptions'] = function (): void {
        foreach ([new SyntheticTimeout('EXAMPLE_API_KEY'), ['status' => 429], ['status' => 503]] as $reply) {
            [$a] = fixture();
            $a->http->queue = [$reply];
            rejects(fn () => $a->getHtml(null, 42, false));
        }
    };
    $tests['native settings-only validator accepts credentials without runtime values'] = function (): void {
        $service = new Box\Mod\Invoice\ServicePayGateway();
        $validate = new ReflectionMethod($service, 'validateGatewayConfig');
        $gateway = (new PayGateway())->setGateway('Yolfi');
        $settings = [];
        $settings['api_key'] = 'EXAMPLE_API_KEY';
        $settings['webhook_secret'] = 'EXAMPLE_WEBHOOK_SECRET';
        try {
            $validate->invoke($service, $gateway, $settings, false);
        } catch (Throwable $e) {
            throw new RuntimeException('Native settings-only validation must not require runtime URLs or gateway_id; got: ' . $e->getMessage());
        }
        $emptyApi = [];
        $emptyApi['api_key'] = '';
        $blankHook = [];
        $blankHook['webhook_secret'] = ' ';
        $invalidApi = [];
        $invalidApi['api_key'] = [];
        $missingHook = [];
        $missingHook['webhook_secret'] = null;
        foreach ([$emptyApi, $blankHook, $invalidApi, $missingHook, ['test_mode' => true]] as $bad) {
            try {
                $validate->invoke($service, $gateway, array_replace($settings, $bad), $bad['test_mode'] ?? false);
            } catch (FOSSBilling\Core\Exception\BaseException $e) {
                check($e->getCode() === 819, 'Native validator did not preserve configuration error contract');
                continue;
            }
            throw new RuntimeException('Native validator accepted invalid settings');
        }
    };
    $tests['checkout runtime URLs and gateway ID are required where used'] = function (): void {
        foreach (['return_url', 'cancel_url'] as $key) {
            foreach ([null, '', [], 'javascript:alert(1)', 'relative/path', 'https://user:pass@example.com/'] as $bad) {
                [$a] = fixture([$key => $bad]);
                rejects(fn () => $a->getHtml(null, 42, false));
                check($a->http->calls === [], 'Invalid runtime URL reached HTTP');
            }
        }
        foreach ([null, 0, -1, 'invalid', []] as $id) {
            [$a] = fixture(['gateway_id' => $id]);
            rejects(fn () => $a->getHtml(null, 42, false));
            rejects(fn () => $a->processTransaction(null, 81, signed(event()), 3));
            check($a->http->calls === [], 'Invalid runtime gateway reached HTTP');
        }
    };
    $tests['discovery capabilities and secret masking'] = function (): void {
        $config = Payment_Adapter_Yolfi::getConfig();
        check($config['supports_one_time_payments'] === true && $config['supports_subscriptions'] === false, 'Incorrect capabilities');
        foreach (['api_key', 'webhook_secret'] as $key) {
            check($config['form'][$key][1]['secret'] === true, 'Secret field not protected');
        }
        [$a] = fixture();
        check($a instanceof FOSSBilling\Core\Payment\JsonWebhookAuthenticatorInterface, 'Missing intake authentication');
        check($a->getType() === Payment_AdapterAbstract::TYPE_HTML, 'Must render hosted checkout link');
    };
    $tests['checkout uses native envelope, fiat major units and stable retry key'] = function (): void {
        [$a] = fixture();
        $a->http->queue = [['data' => snapshot(['status' => 'OPEN'])], ['data' => snapshot(['status' => 'OPEN'])]];
        $html = $a->getHtml(null, 42, false);
        $a->getHtml(null, 42, false);
        [$method, $url, $options] = $a->http->calls[0];
        check($method === 'POST' && $url === 'https://app.yolfi.com/api/checkout-sessions', 'Wrong native endpoint');
        check($options['json']['amount'] === '24.99' && $options['json']['currency'] === 'USD', 'Wrong fiat units');
        check($options['json']['merchantOrderId'] === 'fossbilling:3:42:test-invoice-hash', 'Missing order reference');
        check($options['json']['successUrl'] === 'https://example.com/return' && $options['json']['cancelUrl'] === 'https://example.com/cancel', 'Wrong return routing');
        check(!isset($options['json']['customerEmail'], $options['json']['customerName']), 'Unnecessary buyer data');
        $key = $options['headers']['Idempotency-Key'];
        check(strlen($key) >= 16 && strlen($key) <= 200 && $key === $a->http->calls[1][2]['headers']['Idempotency-Key'], 'Unstable idempotency');
        check($options['timeout'] > 0 && $options['max_duration'] > 0 && $options['max_redirects'] === 0, 'Missing HTTP bounds');
        check(str_contains($html, 'https://pay.yolfi.com/ycs_test_session'), 'Missing checkout link');
    };
    $tests['confirmed webhook credits fiat snapshot and delegates durable receipt deduplication'] = function (): void {
        [$a, $invoice, $tx, $service, $em] = fixture();
        $a->http->queue = [['data' => snapshot()], ['data' => snapshot()], ['data' => snapshot()], ['data' => snapshot()]];
        $a->processTransaction(null, 81, signed(event()), 3);
        check($tx->getInvoice() === $invoice && $tx->getAmount() === '24.99' && $tx->getCurrency() === 'USD', 'Incorrect payment basis or target');
        check($tx->getTxnId() === 'evt_test_confirmation', 'Signed event ID not recorded');
        check($service->credits === 1 && $service->calls[0][1] === 'yolfi:550e8400-e29b-41d4-a716-446655440000', 'Not using stable invoice receipt');
        $a->processTransaction(null, 81, signed(event()), 3);
        check($service->credits === 1 && $em->removed === [], 'Canonical retry must remain available for completion recovery');
        $duplicate = identify((new Transaction())->setGateway($tx->getGateway()), 82);
        $em->entities[Transaction::class][82] = $duplicate;
        $a->processTransaction(null, 82, signed(event()), 3);
        check($service->credits === 1 && $em->removed === [$duplicate], 'Same signed event was applied twice');
        $em->removed = [];
        $a->processTransaction(null, 82, signed(event(['id' => 'evt_test_redelivery'])), 3);
        check($service->credits === 1 && $em->removed === [$duplicate], 'Duplicate receipt was not suppressed');
    };
    $tests['raw body signature enforced before HTTP or persistence'] = function (): void {
        foreach (['missing', 'wrong', 'tampered', 'array', 'malformed'] as $case) {
            [$a, , , $service] = fixture();
            $input = signed(event());
            if ($case === 'missing') {
                unset($input['server']['HTTP_X_YOLFI_SIGNATURE']);
            }
            if ($case === 'wrong') {
                $input = signed(event(), 'wrong-test-secret');
            }
            if ($case === 'tampered') {
                $input['http_raw_post_data'] .= ' ';
            }
            if ($case === 'array') {
                $input['server']['HTTP_X_YOLFI_SIGNATURE'] = [];
            }
            if ($case === 'malformed') {
                $input['server']['HTTP_X_YOLFI_SIGNATURE'] = 'not-base64';
            }
            rejects(fn () => $a->processTransaction(null, 81, $input, 3));
            check($a->http->calls === [] && $service->credits === 0, 'Unverified event had side effects');
        }
    };
    $tests['non-payment events including overdue do not mark invoices paid'] = function (): void {
        foreach (['invoice.created', 'invoice.overdue', 'subscription.cancelled'] as $type) {
            [$a, $invoice, $tx, $service] = fixture();
            $a->processTransaction(null, 81, signed(event(['type' => $type])), 3);
            check($service->credits === 0 && $invoice->getStatus() === Invoice::STATUS_UNPAID && $a->http->calls === [], 'Non-payment event credited');
            check($tx->getStatus() === Transaction::STATUS_PROCESSED, 'Ignored callback not acknowledged');
        }
    };
    $tests['confirmation rejects wrong status, mode, reference and recurring events'] = function (): void {
        foreach ([event([], ['status' => 'pending']), event(['livemode' => false]), event([], ['merchantOrderId' => 'other-order']), event([], ['paymentType' => 'RECURRING']), event([], ['invoiceId' => 'invalid']), event(['id' => ''])] as $bad) {
            [$a, , , $service] = fixture();
            $a->http->queue = [['data' => snapshot()]];
            rejects(fn () => $a->processTransaction(null, 81, signed($bad), 3));
            check($service->credits === 0, 'Rejected confirmation credited');
        }
    };
    $tests['snapshot amount currency session order and gateway mismatches fail closed'] = function (): void {
        foreach ([['amount' => '25.00'], ['amount' => '24.991'], ['amount' => 24.99], ['currency' => 'EUR'], ['id' => 'ycs_other'], ['merchantOrderId' => 'fossbilling:4:42:test-invoice-hash'], ['merchantOrderId' => 'fossbilling:3:43:test-invoice-hash'], ['merchantOrderId' => 'fossbilling:3:42:wrong-hash'], ['status' => 'OPEN'], ['object' => 'other']] as $bad) {
            [$a, , , $service] = fixture();
            $a->http->queue = [['data' => snapshot($bad)]];
            rejects(fn () => $a->processTransaction(null, 81, signed(event()), 3));
            check($service->credits === 0, 'Snapshot mismatch credited');
        }
    };
    $tests['query binding and configured gateway cannot override signed target'] = function (): void {
        [$a, , $tx, $service] = fixture();
        $tx->setInvoice(identify(new Invoice(), 43));
        $a->http->queue = [['data' => snapshot()]];
        rejects(fn () => $a->processTransaction(null, 81, signed(event()), 3));
        check($service->credits === 0, 'Conflicting prebound invoice credited');
        [$a] = fixture();
        rejects(fn () => $a->processTransaction(null, 81, signed(event()), 4));
    };
    $tests['HTTP errors malformed responses and timeouts are sanitized'] = function (): void {
        foreach ([['status' => 401], ['status' => 409], ['status' => 500], ['raw' => 'not-json'], ['raw' => '{"success":false,"data":{}}'], new RuntimeException('EXAMPLE_API_KEY EXAMPLE_WEBHOOK_SECRET')] as $reply) {
            foreach (['checkout', 'confirmation'] as $flow) {
                [$a, , , $service] = fixture();
                $a->http->queue = [$reply];
                try {
                    if ($flow === 'checkout') {
                        $a->getHtml(null, 42, false);
                    } else {
                        $a->processTransaction(null, 81, signed(event()), 3);
                    }
                    throw new RuntimeException('HTTP failure accepted');
                } catch (Payment_Exception|Box\Mod\Invoice\Exception\RetryableCallbackException $e) {
                    check(!str_contains($e->getMessage(), 'EXAMPLE_API_KEY') && !str_contains($e->getMessage(), 'EXAMPLE_WEBHOOK_SECRET'), 'Credential in error');
                }
                check($service->credits === 0, 'HTTP failure credited');
            }
        }
    };
    $tests['unsupported inputs and browser returns never create credits'] = function (): void {
        [$a] = fixture();
        rejects(fn () => $a->getHtml(null, 42, true));
        rejects(fn () => $a->processTransaction(null, 81, ['get' => ['paid' => true]], 3));
        foreach ([array_fill_keys(['api_key'], ''), array_fill_keys(['webhook_secret'], ''), ['test_mode' => true]] as $bad) {
            rejects(fn () => fixture($bad));
        }
        foreach ([0.0, -1.0, INF, NAN, 1.0e13] as $total) {
            [$a, , , $service] = fixture();
            $service->total = $total;
            rejects(fn () => $a->getHtml(null, 42, false));
        }
        [$a, $invoice] = fixture();
        $invoice->setCurrency('THB');
        rejects(fn () => $a->getHtml(null, 42, false));
        foreach (['paid', 'canceled', 'refunded'] as $status) {
            [$a, $invoice] = fixture();
            $invoice->setStatus($status);
            rejects(fn () => $a->getHtml(null, 42, false));
        }
    };
    $tests['new receipt refuses partial credits canceled or already paid invoice'] = function (): void {
        foreach (['partial', 'canceled', 'paid'] as $condition) {
            [$a, $invoice, , $service] = fixture();
            if ($condition === 'partial') {
                $service->amountPaid = 1.0;
            } else {
                $invoice->setStatus($condition);
            }
            $a->http->queue = [['data' => snapshot()]];
            rejects(fn () => $a->processTransaction(null, 81, signed(event()), 3));
            check($service->credits === 0, 'Nonpayable invoice credited');
        }
    };
    $tests['settlement failure can retry without local acknowledgement'] = function (): void {
        [$a, , , $service] = fixture();
        $service->failSettlement = true;
        $a->http->queue = [['data' => snapshot()], ['data' => snapshot()]];
        rejects(fn () => $a->processTransaction(null, 81, signed(event()), 3));
        check($service->credits === 0, 'Failed settlement credited');
        $service->failSettlement = false;
        $a->processTransaction(null, 81, signed(event()), 3);
        check($service->credits === 1, 'Retry could not settle');
    };
    $tests['hosted URL and create snapshot validation'] = function (): void {
        foreach ([['url' => 'javascript:alert(1)'], ['url' => 'https://example.com/checkout'], ['url' => 'https://pay.yolfi.com@evil.test/'], ['status' => 'EXPIRED'], ['amount' => '1.00'], ['merchantOrderId' => 'wrong']] as $bad) {
            [$a] = fixture();
            $a->http->queue = [['data' => snapshot(array_replace(['status' => 'OPEN'], $bad))]];
            rejects(fn () => $a->getHtml(null, 42, false));
        }
    };
    $tests['JPY uses major units and a changed revision starts a different attempt'] = function (): void {
        [$a, $invoice, , $service] = fixture();
        $invoice->setCurrency('JPY');
        $service->total = 2400.0;
        $a->http->queue = [['data' => snapshot(['status' => 'OPEN', 'amount' => '2400.00', 'currency' => 'JPY'])]];
        $a->getHtml(null, 42, false);
        check($a->http->calls[0][2]['json']['amount'] === '2400.00', 'JPY was converted to minor units');
        [$a] = fixture();
        [$other] = fixture(['checkout_revision' => '2']);
        foreach ([$a, $other] as $adapter) {
            $adapter->http->queue = [['data' => snapshot(['status' => 'OPEN'])]];
            $adapter->getHtml(null, 42, false);
        }
        check($a->http->calls[0][2]['headers']['Idempotency-Key'] !== $other->http->calls[0][2]['headers']['Idempotency-Key'], 'Revision did not identify a new attempt');
    };
    $tests['non-USD fiat confirmation ignores token amounts and USD conversions'] = function (): void {
        [$a, $invoice, $tx, $service, $em] = fixture();
        $invoice->setCurrency('EUR');
        $em->entities[Box\Mod\Client\Entity\Client::class][7]->currency = 'EUR';
        $a->http->queue = [['data' => snapshot(['currency' => 'EUR', 'amount' => '24.9900'])]];
        $a->processTransaction(null, 81, signed(event([], ['currency' => 'EUR'])), 3);
        check($service->credits === 1 && $tx->getAmount() === '24.99' && $tx->getCurrency() === 'EUR', 'Wrong money basis for non-USD checkout');
    };
    foreach ($tests as $name => $test) {
        $test();
        print('PASS ' . $name . "\n");
    }
    print(count($tests) . " test groups passed (offline fixtures; no live payments)\n");
}
