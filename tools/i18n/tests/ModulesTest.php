<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use FOSSBilling\Tools\I18n\Catalogue;

test('extracts separate module templates without modifying module files', function (): void {
    $f = $this->fixture;
    $f->setupModules();
    $before = $f->snapshot($f->modules);
    $result = $f->extract();
    $this->assertSame(0, $result->getExitCode(), $result->getErrorOutput());
    expect(Catalogue::entries($f->template()))->toBe([':n item' => ':n items', 'Hello :name' => null]);
    expect(Catalogue::entries($f->template('Other')))->toBe(['Hello :name' => null, 'Other' => null]);
    expect($f->fs->readFile($f->template()))->toContain('Project-Id-Version: FOSSBilling module example', 'Example/templates/page.twig:1');
    expect($f->snapshot($f->modules))->toBe($before);
});

test('keeps unchanged templates and performs read-only stale checks', function (): void {
    $f = $this->fixture;
    $f->setupModules();
    $this->assertSame(0, $f->extract()->getExitCode());
    $f->fs->dumpFile($f->template(), str_replace('POT-Creation-Date:', 'POT-Creation-Date: old', $f->fs->readFile($f->template())));
    $before = $f->fs->readFile($f->template());
    expect($f->extract()->getExitCode())->toBe(0)->and($f->fs->readFile($f->template()))->toBe($before);
    expect($f->extract(['--check'])->getExitCode())->toBe(0);
    $f->write('modules/Example/templates/page.twig', "{{ 'Changed'|trans }}");
    $result = $f->extract(['--check']);
    expect($result->getExitCode())->toBe(1)->and($result->getOutput())->toContain('Hello :name', 'Changed')->and($f->fs->readFile($f->template()))->toBe($before);
});

test('routes cross-module translations to the requested source', function (): void {
    $f = $this->fixture;
    $f->setupModules();
    $f->write('modules/Example/templates/cross.twig', "{{ 'Owned by Other'|trans(from: 'other') }}");
    expect($f->extract()->getExitCode())->toBe(0);
    expect(Catalogue::entries($f->template()))->not->toHaveKey('Owned by Other');
    expect(Catalogue::entries($f->template('Other')))->toHaveKey('Owned by Other');
});

test('requires exact core messages and plural pairs before writing templates', function (string $code): void {
    $f = $this->fixture;
    $f->setupModules();
    $f->write('modules/Example/Service.php', '<?php ' . $code);
    $result = $f->extract();
    expect($result->getExitCode())->toBe(1)->and($result->getErrorOutput())->toContain('not a matching shared core entry')->and(is_dir($f->templates))->toBeFalse();
})->with(["__trans('New module message');", "__pluralTrans('Shared item', 'Wrong plural', \$n);", "__trans('Shared item');"]);

test('rejects invalid sources before changing any templates', function (string $from): void {
    $f = $this->fixture;
    $f->setupModules();
    expect($f->extract()->getExitCode())->toBe(0);
    $before = $f->snapshot($f->templates);
    $f->write('modules/Other/templates/page.twig', "{{ 'Changed'|trans(from: $from) }}");
    $result = $f->extract();
    expect($result->getExitCode())->toBe(1)->and($result->getErrorOutput())->toContain('Other/templates/page.twig:1')->and($f->snapshot($f->templates))->toBe($before);
})->with(["'unknown'", 'source']);

test('rejects conflicting plurals before publishing any template', function (): void {
    $f = $this->fixture;
    $f->setupModules();
    $f->write('modules/Other/Service.php', "<?php __pluralTrans('One', 'Many', \$n, from: 'other'); __pluralTrans('One', 'Others', \$n, from: 'other');");
    $result = $f->extract();
    expect($result->getExitCode())->toBe(1)->and($result->getErrorOutput())->toContain('conflicting plurals')->and(is_dir($f->templates))->toBeFalse();
});

test('rejects missing roots, missing core snapshots and invalid manifests', function (string $scenario): void {
    $f = $this->fixture;
    $f->setupModules();
    $root = null;
    if ($scenario === 'root') {
        $root = $f->root . '/missing';
    } elseif ($scenario === 'core') {
        $f->fs->remove($f->core);
    } elseif ($scenario === 'id') {
        $f->write('modules/Example/manifest.json', '{"manifest_version":1,"type":"module","id":"../example"}');
    } elseif ($scenario === 'directory') {
        $f->write('modules/Example/manifest.json', '{"manifest_version":1,"type":"module","id":"different"}');
    } else {
        $f->write('modules/Example/manifest.json', '{broken');
    }
    expect($f->extract([], $root)->getExitCode())->not->toBe(0)->and(is_dir($f->templates))->toBeFalse();
})->with(['root', 'core', 'id', 'directory', 'json']);

test('requires manifests for module-like directories', function (string $layout): void {
    $f = $this->fixture;
    $f->setupModules();
    if ($layout === 'existing') {
        $f->fs->remove($f->modules . '/Other/manifest.json');
    } else {
        $f->write("modules/Undeclared/$layout/Client.php", "<?php __trans('Save');");
    }
    $result = $f->extract();
    expect($result->getExitCode())->toBe(1)->and($result->getErrorOutput())->toContain('missing module manifest')->and(is_dir($f->templates))->toBeFalse();
})->with(['existing', 'Api', 'Controller']);

test('supports single modules and leaves other committed templates untouched', function (): void {
    $f = $this->fixture;
    $f->setupModules();
    expect($f->extract([], $f->modules . '/Example')->getExitCode())->toBe(0);
    expect(is_file($f->template('Other')))->toBeFalse();
    expect($f->extract()->getExitCode())->toBe(0);
    $other = $f->fs->readFile($f->template('Other'));
    foreach ([[], ['--check']] as $options) {
        expect($f->extract($options, $f->modules . '/Example')->getExitCode())->toBe(0)->and($f->fs->readFile($f->template('Other')))->toBe($other);
    }
});

test('requires review of orphaned templates instead of deleting them', function (): void {
    $f = $this->fixture;
    $f->setupModules();
    expect($f->extract()->getExitCode())->toBe(0);
    $f->fs->remove($f->modules . '/Other');
    $result = $f->extract();
    expect($result->getExitCode())->toBe(1)->and($result->getErrorOutput())->toContain('review its removal manually')->and(is_file($f->template('Other')))->toBeTrue();
});

test('extracts without GNU gettext on PATH', function (): void {
    $f = $this->fixture;
    $f->setupModules();
    $result = $f->cli(['extract-modules', $f->modules, $f->core, $f->templates], env: ['PATH' => '']);
    $this->assertSame(0, $result->getExitCode(), $result->getErrorOutput());
});

test('preserves combined extraction, date-insensitive checks and diff diagnostics', function (): void {
    $f = $this->fixture;
    $source = "<?php __trans('Original'); __pluralTrans('One', 'Many', \$n);";
    $f->write('input/Service.php', $source);
    $f->write('input/modules/Example/page.twig', "{{ 'Module'|trans }}");
    $pot = $f->root . '/messages.pot';
    expect($f->cli(['extract', $f->root . '/input', $pot])->getExitCode())->toBe(0);
    expect(Catalogue::entries($pot))->toHaveKeys(['Original', 'One', 'Module']);
    $f->fs->dumpFile($pot, str_replace('POT-Creation-Date:', 'POT-Creation-Date: old', $f->fs->readFile($pot)));
    $before = $f->fs->readFile($pot);
    expect($f->cli(['extract', $f->root . '/input', $pot, '--check'])->getExitCode())->toBe(0)->and($f->fs->readFile($pot))->toBe($before);
    foreach ([str_replace('Original', 'Changed', $source), str_replace('Many', 'Changed plurals', $source), str_replace('<?php ', "<?php\n\n", $source)] as $changed) {
        $f->write('input/Service.php', $changed);
        $result = $f->cli(['extract', $f->root . '/input', $pot, '--check']);
        expect($result->getExitCode())->toBe(1)->and($f->fs->readFile($pot))->toBe($before);
    }
    $new = $f->root . '/new.pot';
    Catalogue::write([['original', null, 'new.php:1', 'core'], ['One', 'More', 'new.php:2', 'core']], $new);
    $diff = $f->cli(['diff', $pot, $new]);
    expect($diff->getExitCode())->toBe(0)->and($diff->getOutput())->toContain('casing-only: 1', 'Casing-only changes', 'Changed plural forms');
});

test('does not generate empty templates for missing source roots', function (): void {
    $f = $this->fixture;
    $target = $f->root . '/messages.pot';
    expect($f->cli(['extract', $f->root . '/missing', $target])->getExitCode())->toBe(1)->and($f->fs->exists($target))->toBeFalse();
});

test('reads CRLF core templates and checks catalogues without newline-only changes', function (): void {
    $f = $this->fixture;
    $f->setupModules();
    $lf = $f->fs->readFile($f->core);
    $f->fs->dumpFile($f->core, str_replace("\n", "\r\n", $lf));
    expect(Catalogue::entries($f->core))->toBe(['Save' => null, 'Shared item' => 'Shared items']);
    $result = $f->extract();
    $this->assertSame(0, $result->getExitCode(), $result->getErrorOutput());
    foreach ([$f->template(), $f->template('Other')] as $path) {
        $f->fs->dumpFile($path, str_replace("\n", "\r\n", $f->fs->readFile($path)));
    }
    $before = $f->snapshot($f->templates);
    expect($f->extract(['--check'])->getExitCode())->toBe(0);
    expect($f->snapshot($f->templates))->toBe($before);
    $f->write('input/Service.php', "<?php __trans('Hello');");
    $target = $f->root . '/messages.pot';
    expect($f->cli(['extract', $f->root . '/input', $target])->getExitCode())->toBe(0);
    $lf = $f->fs->readFile($target);
    $f->fs->dumpFile($target, str_replace("\n", "\r\n", $lf));
    $before = $f->fs->readFile($target);
    expect($f->cli(['extract', $f->root . '/input', $target, '--check'])->getExitCode())->toBe(0);
    expect($f->fs->readFile($target))->toBe($before);
});
