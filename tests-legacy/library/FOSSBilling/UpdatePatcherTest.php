<?php

declare(strict_types=1);

use FOSSBilling\UpdatePatcher;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class UpdatePatcherTest extends PHPUnit\Framework\TestCase
{
    public function testSetDiDoesNotRequireDbalWhenUpdatingWithLegacyContainer(): void
    {
        $di = new Pimple\Container();

        $patcher = new UpdatePatcher();
        $patcher->setDi($di);

        self::assertFalse($di->offsetExists('dbal'));
    }

    public function testDatabaseAccessUsesPdoWhenLegacyContainerWasInjectedBeforehand(): void
    {
        $di = new Pimple\Container();
        $di['pdo'] = new PDO('sqlite::memory:');
        $patcher = new UpdatePatcher();

        $diProperty = new ReflectionProperty(UpdatePatcher::class, 'di');
        $diProperty->setValue($patcher, $di);

        $method = new ReflectionMethod(UpdatePatcher::class, 'getPdo');
        $pdo = $method->invoke($patcher);

        self::assertSame($di['pdo'], $pdo);
        self::assertFalse($di->offsetExists('dbal'));
    }

    public function testSpamcheckerPatchRemovesExtensionRecordWithoutUninstallingModule(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE extension (type TEXT, name TEXT)');
        $pdo->exec('CREATE TABLE extension_meta (extension TEXT, rel_type TEXT, rel_id TEXT, meta_key TEXT, meta_value TEXT)');
        $pdo->exec("INSERT INTO extension (type, name) VALUES ('mod', 'spamchecker')");

        $extensionService = new class {
            public array $savedConfig = [];

            public function getConfig(string $extension): array
            {
                return match ($extension) {
                    'mod_spamchecker' => [
                        'ext' => 'mod_spamchecker',
                        'captcha_enabled' => true,
                    ],
                    'mod_antispam' => [
                        'ext' => 'mod_antispam',
                    ],
                    default => ['ext' => $extension],
                };
            }

            public function setConfig(array $data): bool
            {
                $this->savedConfig = $data;

                return true;
            }

            public function __call(string $name, array $arguments): mixed
            {
                throw new RuntimeException("Unexpected extension service call: {$name}");
            }
        };

        $hookService = new class {
            public function batchConnect(string $module): void
            {
            }
        };

        $cache = new class {
            public function delete(string $key): void
            {
            }
        };

        $di = new Pimple\Container();
        $di['pdo'] = $pdo;
        $di['mod_service'] = $di->protect(fn (string $module): object => match ($module) {
            'extension' => $extensionService,
            'hook' => $hookService,
        });
        $di['cache'] = $cache;

        $patcher = new UpdatePatcher();
        $patcher->setDi($di);

        $method = new ReflectionMethod(UpdatePatcher::class, 'patch55');
        $method->invoke($patcher);

        $remainingSpamcheckerExtensions = $pdo
            ->query("SELECT COUNT(*) FROM extension WHERE type = 'mod' AND name = 'spamchecker'")
            ->fetchColumn();

        self::assertSame(0, (int) $remainingSpamcheckerExtensions);
        self::assertSame('mod_antispam', $extensionService->savedConfig['ext']);
        self::assertTrue($extensionService->savedConfig['captcha_enabled']);
    }
}
