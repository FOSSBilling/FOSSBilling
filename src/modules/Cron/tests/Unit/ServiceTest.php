<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Cron\Service;
use Box\Mod\System\Entity\Setting;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Tools\SchemaTool;
use FOSSBilling\Doctrine\EntityManagerFactory;
use FOSSBilling\Events\Event;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

use function Tests\Helpers\container;

class CronServiceApiDouble
{
    public ?string $method = null;
    public mixed $params = null;
    public array $methods = [];
    public ?string $throwOn = null;

    public function __call(string $method, array $arguments): void
    {
        $this->method = $method;
        $this->params = $arguments[0] ?? null;
        $this->methods[] = $method;
        if ($method === $this->throwOn) {
            throw new RuntimeException("Failed to run {$method}");
        }
    }
}

test('getDi returns dependency injection container', function (): void {
    $di = container();
    $service = new Service();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toEqual($di);
});

test('getCronInfo returns cron information array', function (): void {
    $systemServiceMock = Mockery::mock(SystemService::class);
    $systemServiceMock->shouldReceive('getParamValue')
        ->atLeast()->once();

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name): Mockery\MockInterface => $systemServiceMock);
    $service = new Service();
    $service->setDi($di);

    $result = $service->getCronInfo();
    expect($result)->toBeArray();
});

test('getLastExecutionTime returns string timestamp', function (): void {
    $systemServiceMock = Mockery::mock(SystemService::class);
    $systemServiceMock->shouldReceive('getParamValue')
        ->atLeast()->once()
        ->andReturn('2012-12-12 12:12:12');

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name): Mockery\MockInterface => $systemServiceMock);
    $service = new Service();
    $service->setDi($di);

    $result = $service->getLastExecutionTime();
    expect($result)->toBeString();
});

test('isLate returns boolean indicating if cron execution is late', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getLastExecutionTime')
        ->atLeast()->once()
        ->andReturn(date('Y-m-d H:i:s'));

    $result = $serviceMock->isLate();
    expect($result)->toBeBool();
    expect($result)->toBeFalse();
});

test('exec passes empty array when cron task has no params', function (): void {
    $service = new Service();
    $api = new CronServiceApiDouble();
    $repository = Mockery::mock(Box\Mod\System\Repository\SettingRepository::class);
    $repository->shouldReceive('clearRequestCache')->once();
    $di = container();
    $di['em']->shouldReceive('getRepository')->once()->with(Setting::class)->andReturn($repository);
    $service->setDi($di);

    $method = new ReflectionMethod(Service::class, '_exec');
    ob_start();
    $method->invoke($service, $api, 'invoice_batch_pay_with_credits');
    ob_end_clean();

    expect($api->method)->toBe('invoice_batch_pay_with_credits');
    expect($api->params)->toBe([]);
});

test('runCrons clears cached setting misses between tasks', function (): void {
    $filesystem = new Filesystem();
    $databasePath = Path::join(sys_get_temp_dir(), 'fossbilling-cron-settings-' . bin2hex(random_bytes(8)) . '.sqlite');
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $databasePath]);
    $externalConnection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $databasePath]);

    try {
        $entityManager = EntityManagerFactory::create($connection);
        (new SchemaTool($entityManager))->createSchema([$entityManager->getClassMetadata(Setting::class)]);
        $connection->executeStatement('CREATE TABLE session (lifetime INTEGER, created_at INTEGER)');

        $di = container();
        $di['em'] = $entityManager;
        $di['dbal'] = $connection;
        $systemService = new Box\Mod\System\Service();
        $systemService->setDi($di);

        $api = new class($systemService, $externalConnection) {
            public mixed $firstTaskValue = null;
            public mixed $secondTaskValue = null;

            public function __construct(
                private readonly Box\Mod\System\Service $systemService,
                private readonly Doctrine\DBAL\Connection $externalConnection,
            ) {
            }

            public function invoice_batch_pay_with_credits(array $params): void
            {
                $this->firstTaskValue = $this->systemService->getParamValue('cron_task_setting');
                $this->externalConnection->insert('setting', ['param' => 'cron_task_setting', 'value' => 'visible']);
            }

            public function invoice_batch_activate_paid(array $params): void
            {
                $this->secondTaskValue = $this->systemService->getParamValue('cron_task_setting');
            }

            public function __call(string $method, array $arguments): void
            {
            }
        };

        $updateFinalization = Mockery::mock();
        $updateFinalization->shouldReceive('isRequired')->once()->andReturnFalse();
        $updateFinalization->shouldReceive('healSchemaDrift')->once()->andReturnNull();
        $di['api_system'] = $api;
        $di['logger'] = new Tests\Helpers\TestLogger();
        $di['update_finalization'] = $updateFinalization;
        $di['mod_service'] = $di->protect(fn (): Box\Mod\System\Service => $systemService);

        $service = new Service();
        $service->setDi($di);
        ob_start();
        $result = $service->runCrons();
        ob_end_clean();

        expect($result)->toBeTrue()
            ->and($api->firstTaskValue)->toBeNull()
            ->and($api->secondTaskValue)->toBe('visible');
    } finally {
        $connection->close();
        $externalConnection->close();
        $filesystem->remove($databasePath);
    }
});

test('runCrons isolates failures in core batch tasks', function (string $failedTask): void {
    $updateFinalization = Mockery::mock();
    $updateFinalization->shouldReceive('isRequired')->once()->andReturnFalse();
    $updateFinalization->shouldReceive('healSchemaDrift')->once()->andReturnNull();

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('setParamValue')
        ->once()
        ->with('last_cron_exec', Mockery::type('string'), true);

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('executeStatement')->once()->andReturn(0);

    $api = new CronServiceApiDouble();
    $api->throwOn = $failedTask;
    $eventDispatcher = new class {
        /** @var list<Event> */
        public array $events = [];

        public function dispatch(Event $event): Event
        {
            $this->events[] = $event;

            return $event;
        }
    };
    $di = container();
    $di['api_system'] = $api;
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $di['event_dispatcher'] = $eventDispatcher;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $systemService);
    $di['update_finalization'] = $updateFinalization;

    $service = new Service();
    $service->setDi($di);

    ob_start();
    $result = $service->runCrons();
    ob_end_clean();

    $positions = array_flip($api->methods);
    expect($result)->toBeFalse()
        ->and($positions['invoice_batch_generate'])
        ->toBeLessThan($positions['invoice_batch_send_reminders'])
        ->and($positions['invoice_batch_send_reminders'])
        ->toBeLessThan($positions['invoice_batch_invoke_due_event'])
        ->and($api->methods)->toContain('email_batch_sendmail')
        ->and(array_map(static fn (Event $event): string => $event::class, $eventDispatcher->events))
        ->toBe([
            Box\Mod\Cron\Event\BeforeAdminCronRunEvent::class,
            Box\Mod\Cron\Event\AfterAdminCronRunEvent::class,
        ]);
})->with([
    'invoice generation' => 'invoice_batch_generate',
    'invoice reminders' => 'invoice_batch_send_reminders',
    'invoice due events' => 'invoice_batch_invoke_due_event',
    'order suspension warning' => 'order_batch_send_suspension_warnings',
    'order suspension' => 'order_batch_suspend_expired',
    'order cancellation' => 'order_batch_cancel_suspended',
    'order cancellation (unpaid)' => 'order_batch_cancel_unpaid',
    'support ticket auto-close' => 'support_batch_ticket_auto_close',
    'password reminder expiry' => 'client_batch_expire_password_reminders',
    'cart expiry' => 'cart_batch_expire',
    'email queue' => 'email_batch_sendmail',
]);

test('runCrons restores the previous cron context when update finalization interrupts execution', function (): void {
    $updateFinalization = Mockery::mock()->shouldIgnoreMissing();
    $updateFinalization->shouldReceive('isRequired')->once()->andReturn(true);

    $di = container();
    $di['update_finalization'] = $updateFinalization;

    $service = new Service();
    $service->setDi($di);

    try {
        $service->runCrons();
    } catch (FOSSBilling\InformationException) {
        // Expected: update finalization is pending, cron tasks are skipped.
    }

    expect(isset($di['is_cron']))->toBeFalse();
});

test('runCrons still executes tasks when schema drift healing fails', function (): void {
    // Healing must never be fatal: a lock or database failure degrades to
    // running against the live schema, exactly as before the healing existed.
    // @see https://github.com/FOSSBilling/FOSSBilling/issues/4392
    $updateFinalization = Mockery::mock();
    $updateFinalization->shouldReceive('isRequired')->once()->andReturnFalse();
    $updateFinalization->shouldReceive('healSchemaDrift')->once()->andThrow(new RuntimeException('lock unavailable'));

    $systemService = Mockery::mock(SystemService::class);
    $systemService->shouldReceive('setParamValue')
        ->once()
        ->with('last_cron_exec', Mockery::type('string'), true);

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('executeStatement')->once()->andReturn(0);

    $api = new CronServiceApiDouble();
    $eventDispatcher = new class {
        public function dispatch(Event $event): Event
        {
            return $event;
        }
    };
    $logger = new Tests\Helpers\TestLogger();
    $di = container();
    $di['api_system'] = $api;
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $di['event_dispatcher'] = $eventDispatcher;
    $di['logger'] = $logger;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $systemService);
    $di['update_finalization'] = $updateFinalization;

    $service = new Service();
    $service->setDi($di);

    ob_start();
    $result = $service->runCrons();
    ob_end_clean();

    expect($result)->toBeTrue()
        ->and($api->methods)->toContain('invoice_batch_generate', 'email_batch_sendmail');

    $warnings = array_values(array_filter($logger->calls, static fn (array $call): bool => $call['method'] === 'warning'));
    expect($warnings)->not->toBe([])
        ->and($warnings[0]['params'][0])->toContain('Schema drift healing failed');
});
