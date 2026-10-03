<?php

declare(strict_types=1);

use FOSSBilling\SentryHelper;
use Symfony\Component\Filesystem\Path;

function invokeIsThirdPartyAdapter(string $path): bool
{
    $method = new ReflectionMethod(SentryHelper::class, 'isThirdPartyAdapter');

    return $method->invoke(null, $path);
}

function shippedFileNames(string $subdirectory): array
{
    $files = glob(Path::join(PATH_LIBRARY, $subdirectory, '*.php')) ?: [];

    return array_map(fn (string $file): string => pathinfo($file, PATHINFO_FILENAME), $files);
}

test('errors from unknown registrar adapters are treated as third-party', function (): void {
    foreach (['DomainNameApi', 'HostAfrica', 'Liquid'] as $adapter) {
        expect(invokeIsThirdPartyAdapter(Path::join(PATH_LIBRARY, 'Registrar', 'Adapter', $adapter . '.php')))->toBeTrue();
    }
});

test('errors from unknown server managers are treated as third-party', function (): void {
    expect(invokeIsThirdPartyAdapter(Path::join(PATH_LIBRARY, 'Server', 'Manager', 'SomeCustomPanel.php')))->toBeTrue();
});

test('errors from shipped adapters and managers are not treated as third-party', function (): void {
    foreach (shippedFileNames('Registrar/Adapter') as $adapter) {
        expect(invokeIsThirdPartyAdapter(Path::join(PATH_LIBRARY, 'Registrar', 'Adapter', $adapter . '.php')))->toBeFalse();
    }

    foreach (shippedFileNames('Server/Manager') as $manager) {
        expect(invokeIsThirdPartyAdapter(Path::join(PATH_LIBRARY, 'Server', 'Manager', $manager . '.php')))->toBeFalse();
    }
});

test('errors from outside the adapter directories are not treated as third-party', function (): void {
    expect(invokeIsThirdPartyAdapter(Path::join(PATH_LIBRARY, 'Box', 'App.php')))->toBeFalse();
    expect(invokeIsThirdPartyAdapter(Path::join(PATH_LIBRARY, 'Registrar', 'AdapterAbstract.php')))->toBeFalse();
});

test('the adapter allowlists match the files shipped on disk', function (): void {
    $reflection = new ReflectionClass(SentryHelper::class);

    $adapters = $reflection->getConstant('ALLOWED_REGISTRAR_ADAPTERS');
    sort($adapters);
    $onDiskAdapters = shippedFileNames('Registrar/Adapter');
    sort($onDiskAdapters);
    expect($adapters)->toBe($onDiskAdapters);

    $managers = $reflection->getConstant('ALLOWED_SERVER_MANAGERS');
    sort($managers);
    $onDiskManagers = shippedFileNames('Server/Manager');
    sort($onDiskManagers);
    expect($managers)->toBe($onDiskManagers);
});
