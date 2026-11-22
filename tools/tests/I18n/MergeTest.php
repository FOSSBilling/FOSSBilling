<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use FOSSBilling\Tools\I18n\TranslationCatalog;

test('merges templates from multiple sources into one union template', function (): void {
    $f = $this->fixture;
    $f->write('main/Service.php', "<?php __trans('Shared'); __trans('Main only');");
    $f->write('main/page.twig', "{{ 'Main twig'|trans }}");
    $f->write('legacy/Service.php', "<?php __trans('Shared'); __trans('Legacy only'); __pluralTrans(':n item', ':n items', \$n);");
    $target = $f->root . '/messages.pot';
    $result = $f->cli(['i18n:merge', $target, $f->root . '/main', $f->root . '/legacy']);
    $this->assertSame(0, $result->getExitCode(), $result->getErrorOutput());
    expect($result->getOutput())->toContain('found 5 messages');
    expect(TranslationCatalog::entries($target))->toBe([
        ':n item' => ':n items',
        'Legacy only' => null,
        'Main only' => null,
        'Main twig' => null,
        'Shared' => null,
    ]);
    // References are relative to each source tree, so the same relative
    // path from both trees dedupes to one reference (see
    // TranslationCatalog::write); the union guarantee is the msgid set
    // above, and the shared entry itself appears exactly once.
    expect(substr_count($f->fs->readFile($target), "#: Service.php:1\nmsgid \"Shared\""))->toBe(1);
});

test('supports read-only checks and rejects invalid sources before writing', function (): void {
    $f = $this->fixture;
    $f->write('main/Service.php', "<?php __trans('Shared');");
    $target = $f->write('messages.pot', 'keep this template');
    $result = $f->cli(['i18n:merge', $target, $f->root . '/main']);
    $this->assertSame(0, $result->getExitCode(), $result->getErrorOutput());
    $generated = $f->fs->readFile($target);
    expect($f->cli(['i18n:merge', '--check', $target, $f->root . '/main'])->getExitCode())->toBe(0);
    $f->write('main/Other.php', "<?php __trans('Changed');");
    $result = $f->cli(['i18n:merge', '--check', $target, $f->root . '/main']);
    expect($result->getExitCode())->toBe(1)
        ->and($result->getOutput())->toContain('Changed')
        ->and($f->fs->readFile($target))->toBe($generated);
    $result = $f->cli(['i18n:merge', $target, $f->root . '/missing']);
    expect($result->getExitCode())->toBe(1)
        ->and($f->fs->readFile($target))->toBe($generated);
});
