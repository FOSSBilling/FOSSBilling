<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request;

function createApiDispatcherDi(bool $extensionActive = true, bool $moduleHasService = true): Pimple\Container
{
    $extensionService = new class($extensionActive) {
        public function __construct(private readonly bool $extensionActive)
        {
        }

        public function isExtensionActive(string $type, string $mod): bool
        {
            return $this->extensionActive;
        }
    };

    $extensionModule = new class($extensionService) {
        public function __construct(private readonly object $extensionService)
        {
        }

        public function getService(): object
        {
            return $this->extensionService;
        }

        public function hasService(): bool
        {
            return true;
        }
    };

    $module = new class($moduleHasService) {
        public function __construct(private readonly bool $moduleHasService)
        {
        }

        public function hasService(): bool
        {
            return $this->moduleHasService;
        }
    };

    $systemService = new class {
        public function getPeriod(string $code): string
        {
            return 'Period ' . $code;
        }

        public function getPublicParamValue(string $key): string
        {
            return 'value:' . $key;
        }
    };

    $staffService = new class {
        public function hasPermission(Model_Admin $identity, string $mod): bool
        {
            return true;
        }
    };

    $di = new Pimple\Container();
    $di['request'] = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
    $di['mod'] = $di->protect(fn (string $name): object => strtolower($name) === 'extension' ? $extensionModule : $module);
    $di['mod_service'] = $di->protect(fn (string $name): object => strtolower($name) === 'staff' ? $staffService : $systemService);

    return $di;
}

function createApiDispatcher(Pimple\Container $di): Api_Dispatcher
{
    $dispatcher = new Api_Dispatcher();
    $dispatcher->setDi($di);

    return $dispatcher;
}

test('dispatches an API endpoint with an initialized module API object', function (): void {
    $dispatcher = createApiDispatcher(createApiDispatcherDi());

    $result = $dispatcher->dispatch(new Model_Guest(), 'system_period_title', ['code' => '1M']);

    expect($result)->toBe('Period 1M');
});

test('rejects inactive modules before resolving the API class', function (): void {
    $dispatcher = createApiDispatcher(createApiDispatcherDi(extensionActive: false));

    expect(fn (): mixed => $dispatcher->dispatch(new Model_Guest(), 'system_period_title', ['code' => '1M']))
        ->toThrow(FOSSBilling\Exception::class, 'FOSSBilling module system is not installed/activated');
});

test('reports missing API classes as missing API calls', function (): void {
    $dispatcher = createApiDispatcher(createApiDispatcherDi(moduleHasService: false));

    expect(fn (): mixed => $dispatcher->dispatch(new Model_Guest(), 'missingmodule_get', []))
        ->toThrow(FOSSBilling\Exception::class, 'Guest API call get does not exist in module missingmodule');
});

test('reports missing API methods unless the API class implements __call', function (): void {
    $dispatcher = createApiDispatcher(createApiDispatcherDi());

    expect(fn (): mixed => $dispatcher->dispatch(new Model_Guest(), 'system_missing_method', []))
        ->toThrow(FOSSBilling\Exception::class, 'Guest API call missing_method does not exist in module system');
});

test('validates required API parameters before dispatching', function (): void {
    $dispatcher = createApiDispatcher(createApiDispatcherDi());

    expect(fn (): mixed => $dispatcher->dispatch(new Model_Guest(), 'system_param', []))
        ->toThrow(FOSSBilling\InformationException::class, '"key" parameter was not passed');
});

test('dispatches positional arguments for in-process API calls', function (): void {
    $dispatcher = createApiDispatcher(createApiDispatcherDi());

    $result = $dispatcher->dispatchWithArguments(new Model_Guest(), 'extension_languages', [true]);

    expect($result)
        ->toBeArray()
        ->and($result[0])
        ->toHaveKeys(['locale', 'title']);
});

test('does not pass empty HTTP data into optional scalar API parameters', function (): void {
    $dispatcher = createApiDispatcher(createApiDispatcherDi());

    $result = $dispatcher->dispatch(new Model_Guest(), 'extension_languages');

    expect($result)
        ->toBeArray()
        ->and($result[0])
        ->toBeString();
});

test('api handler requires the dispatcher service instead of creating one itself', function (): void {
    $handler = new Api_Handler(new Model_Guest());

    expect(fn (): mixed => $handler->call('system_period_title', ['code' => '1M']))
        ->toThrow(LogicException::class, 'API handler requires the api_dispatcher service');
});
