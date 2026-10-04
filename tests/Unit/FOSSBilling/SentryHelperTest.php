<?php

declare(strict_types=1);

use FOSSBilling\Extension\ExtensionType;
use FOSSBilling\SentryHelper;
use Symfony\Component\Filesystem\Path;

function invokeIsThirdPartyAdapter(string $path): bool
{
    $method = new ReflectionMethod(SentryHelper::class, 'isThirdPartyAdapter');

    return $method->invoke(null, $path);
}

/**
 * The IDs of the extensions of a type that ship on disk.
 *
 * @return list<string>
 */
function shippedExtensionIds(ExtensionType $type): array
{
    $ids = [];
    foreach (new DirectoryIterator($type->directory()) as $entry) {
        if (!$entry->isDot() && $entry->isDir()) {
            $ids[] = $entry->getFilename();
        }
    }

    return $ids;
}

function extensionClassFile(ExtensionType $type, string $id): string
{
    return Path::join($type->directory(), $id, $id . '.php');
}

test('errors from unknown registrar adapters are treated as third-party', function (): void {
    foreach (['DomainNameApi', 'HostAfrica', 'Liquid'] as $adapter) {
        expect(invokeIsThirdPartyAdapter(extensionClassFile(ExtensionType::Registrar, $adapter)))->toBeTrue();
    }
});

test('errors from unknown server managers are treated as third-party', function (): void {
    expect(invokeIsThirdPartyAdapter(extensionClassFile(ExtensionType::Manager, 'SomeCustomPanel')))->toBeTrue();
});

test('errors from shipped adapters and managers are not treated as third-party', function (): void {
    foreach (shippedExtensionIds(ExtensionType::Registrar) as $adapter) {
        expect(invokeIsThirdPartyAdapter(extensionClassFile(ExtensionType::Registrar, $adapter)))->toBeFalse();
    }

    foreach (shippedExtensionIds(ExtensionType::Manager) as $manager) {
        expect(invokeIsThirdPartyAdapter(extensionClassFile(ExtensionType::Manager, $manager)))->toBeFalse();
    }
});

test('errors from outside the adapter directories are not treated as third-party', function (): void {
    expect(invokeIsThirdPartyAdapter(Path::join(PATH_LIBRARY, 'Box', 'App.php')))->toBeFalse();
    expect(invokeIsThirdPartyAdapter(Path::join(PATH_LIBRARY, 'FOSSBilling', 'Extension', 'Contract', 'Registrar', 'AdapterAbstract.php')))->toBeFalse();
});

test('the adapter allowlists match the files shipped on disk', function (): void {
    $reflection = new ReflectionClass(SentryHelper::class);

    $adapters = $reflection->getConstant('ALLOWED_REGISTRAR_ADAPTERS');
    sort($adapters);
    $onDiskAdapters = shippedExtensionIds(ExtensionType::Registrar);
    sort($onDiskAdapters);
    expect($adapters)->toBe($onDiskAdapters);

    $managers = $reflection->getConstant('ALLOWED_SERVER_MANAGERS');
    sort($managers);
    $onDiskManagers = shippedExtensionIds(ExtensionType::Manager);
    sort($onDiskManagers);
    expect($managers)->toBe($onDiskManagers);
});

test('an error inside a shipped extension\'s own vendored code is attributed to that extension', function (): void {
    $vendored = Path::join(ExtensionType::Registrar->directory(), 'Namecheap', 'vendor', 'acme', 'lib', 'Client.php');
    $thirdParty = Path::join(ExtensionType::Registrar->directory(), 'Unknown', 'vendor', 'acme', 'lib', 'Client.php');

    expect(invokeIsThirdPartyAdapter($vendored))->toBeFalse()
        ->and(invokeIsThirdPartyAdapter($thirdParty))->toBeTrue();
});
