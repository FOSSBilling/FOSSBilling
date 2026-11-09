<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use FOSSBilling\Tools\Manifest\Command\ValidateCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

const MANIFEST_VALID_MODULE = [
    'manifest_version' => 1,
    'type' => 'module',
    'id' => 'example',
    'name' => 'Example',
    'version' => '1.0.0',
    'description' => 'An example module.',
    'author' => ['name' => 'FOSSBilling', 'url' => 'https://fossbilling.org'],
    'license' => 'Apache-2.0',
    'requires' => ['fossbilling' => '>=1.0.0', 'php' => '>=8.3'],
];

beforeEach(function (): void {
    $this->root = Path::join(sys_get_temp_dir(), 'fossbilling-manifest-test-' . bin2hex(random_bytes(10)));
    (new Filesystem())->mkdir($this->root);
});

afterEach(function (): void {
    (new Filesystem())->remove($this->root);
});

function manifestValidatorCommand(string $root): CommandTester
{
    $command = new ValidateCommand(
        Path::join($root, 'modules'),
        Path::join(dirname(__DIR__, 2), 'resources', 'manifest.schema.json'),
    );

    return new CommandTester($command);
}

function manifestValidatorWriteModule(string $root, string $directory, array $manifest): void
{
    $filesystem = new Filesystem();
    $filesystem->mkdir(Path::join($root, 'modules', $directory));
    $filesystem->dumpFile(Path::join($root, 'modules', $directory, 'manifest.json'), json_encode($manifest, JSON_THROW_ON_ERROR));
}

test('validates a correct module manifest', function (): void {
    manifestValidatorWriteModule($this->root, 'Example', MANIFEST_VALID_MODULE);
    $tester = manifestValidatorCommand($this->root);

    $status = $tester->execute(['modules' => Path::join($this->root, 'modules')]);

    expect($status)->toBe(0)
        ->and($tester->getDisplay())->toContain('PASS Example')
        ->and($tester->getDisplay())->toContain('OK: 1 package(s) valid');
});

test('rejects a manifest whose id does not match its directory', function (): void {
    manifestValidatorWriteModule($this->root, 'Example', [...MANIFEST_VALID_MODULE, 'id' => 'other']);
    $tester = manifestValidatorCommand($this->root);

    $status = $tester->execute(['modules' => Path::join($this->root, 'modules')]);

    expect($status)->toBe(1)
        ->and($tester->getDisplay())->toContain('FAIL Example')
        ->and($tester->getDisplay())->toContain('id "other" must match directory "Example" (case-insensitive)');
});

test('rejects a "v" prefixed version', function (): void {
    manifestValidatorWriteModule($this->root, 'Example', [...MANIFEST_VALID_MODULE, 'version' => 'v1.0.0']);
    $tester = manifestValidatorCommand($this->root);

    $status = $tester->execute(['modules' => Path::join($this->root, 'modules')]);

    expect($status)->toBe(1)
        ->and($tester->getDisplay())->toContain('version must not have a "v" prefix');
});

test('rejects a manifest missing a schema-required field', function (): void {
    $manifest = MANIFEST_VALID_MODULE;
    unset($manifest['name']);
    manifestValidatorWriteModule($this->root, 'Example', $manifest);
    $tester = manifestValidatorCommand($this->root);

    $status = $tester->execute(['modules' => Path::join($this->root, 'modules')]);

    expect($status)->toBe(1)
        ->and($tester->getDisplay())->toContain('FAIL Example')
        ->and($tester->getDisplay())->toContain('[name] The property name is required');
});

test('rejects a module directory without manifest.json', function (): void {
    (new Filesystem())->mkdir(Path::join($this->root, 'modules', 'Example'));
    $tester = manifestValidatorCommand($this->root);

    $status = $tester->execute(['modules' => Path::join($this->root, 'modules')]);

    expect($status)->toBe(1)
        ->and($tester->getDisplay())->toContain('manifest.json is missing');
});

test('fails when the modules directory contains no modules', function (): void {
    (new Filesystem())->mkdir(Path::join($this->root, 'modules'));
    $tester = manifestValidatorCommand($this->root);

    expect(fn (): int => $tester->execute(['modules' => Path::join($this->root, 'modules')]))
        ->toThrow(RuntimeException::class, 'No module directories found under');
});
