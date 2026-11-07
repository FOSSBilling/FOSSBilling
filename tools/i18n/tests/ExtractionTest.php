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
use FOSSBilling\Tools\I18n\Extractor;

dataset('extraction', function (): iterable {
    foreach (require __DIR__ . '/fixtures/extraction.php' as $name => $fixture) {
        yield $name => [$fixture];
    }
});

test('extracts messages, plural pairs, references and sources and rejects invalid inputs', function (array $fixture): void {
    foreach ($fixture['files'] as $relative => $content) {
        $this->fixture->write('input/' . $relative, $content);
    }
    $result = Extractor::extract($this->fixture->root . '/input', $fixture['module']);
    $expected = $fixture['occurrences'];
    $actual = $result['occurrences'];
    sort($expected);
    sort($actual);
    expect($actual)->toBe($expected);
    if ($fixture['error'] === null) {
        expect($result['source_errors'])->toBe([]);
    } else {
        expect(implode("\n", $result['source_errors']))->toContain($fixture['error']);
    }
})->with('extraction');

test('rejects invalid Twig and unknown extension tags without overwriting templates', function (string $code): void {
    $this->fixture->write('input/page.twig', $code);
    $target = $this->fixture->write('messages.pot', 'keep this template');
    $result = $this->fixture->cli(['extract', $this->fixture->root . '/input', $target]);
    expect($result->getExitCode())->toBe(1)->and($result->getErrorOutput())->not->toBeEmpty()->and($this->fixture->fs->readFile($target))->toBe('keep this template');
})->with(["{% if flag %}{{ 'Save'|trans }}", "{% custom_tag %}{{ 'Save'|trans }}"]);

test('extracts parenthesized and binary string literals', function (): void {
    $this->fixture->write('input/page.twig', "{{ ('Save')|trans }} {{ 'Keep'|trans }}");
    $this->fixture->write('input/Case.php', "<?php __trans(b'Save'); __trans(('Save')); __trans(B\"Binary\");");
    $result = Extractor::extract($this->fixture->root . '/input');
    expect(array_column($result['occurrences'], 0))->toBe(['Save', 'Save', 'Binary', 'Save', 'Keep']);
});

test('decodes PHP byte and Unicode escapes', function (): void {
    $this->fixture->write('input/Case.php', <<<'PHP'
        <?php
        __trans("\xC3\xA9");
        __trans("\303\251");
        __trans("\u{1F680}");
        __trans("Keep \q");
        PHP);
    $result = Extractor::extract($this->fixture->root . '/input');
    expect($result['source_errors'])->toBe([]);
    expect(array_column($result['occurrences'], 0))->toBe(['é', 'é', '🚀', 'Keep \\q']);
});

test('accepts parenthesized constant source arguments', function (): void {
    $this->fixture->write('input/Case.php', "<?php __trans('PHP module', from: ('example')); __trans('PHP core', from: (null));");
    $this->fixture->write('input/page.twig', "{{ ('Twig module')|trans(from: ('example')) }} {{ 'Twig core'|trans(from: (null)) }}");
    $result = Extractor::extract($this->fixture->root . '/input');
    expect($result['source_errors'])->toBe([]);
    expect(array_column($result['occurrences'], 3))->toBe(['example', 'core', 'example', 'core']);
});

test('includes embedded templates and keeps duplicate AST nodes out of generated references', function (): void {
    $this->fixture->write('input/page.twig', "{% embed 'base.twig' %}{% block content %}{{ value|default('Embedded'|trans) }}{% endblock %}{% endembed %}");
    $result = Extractor::extract($this->fixture->root . '/input');
    $pot = $this->fixture->root . '/messages.pot';
    Catalogue::write($result['occurrences'], $pot);
    expect(Catalogue::entries($pot))->toBe(['Embedded' => null]);
    expect(substr_count($this->fixture->fs->readFile($pot), 'page.twig:1'))->toBe(1);
});

test('does not execute PHP or template callables', function (): void {
    $marker = $this->fixture->root . '/executed';
    $this->fixture->write('input/Case.php', '<?php file_put_contents(' . var_export($marker, true) . ", 'bad'); __trans('PHP');");
    $this->fixture->write('input/page.twig', "{{ dangerous('bad') }} {{ 'Twig'|trans }}");
    $result = Extractor::extract($this->fixture->root . '/input');
    expect($result['source_errors'])->toBe([])->and($this->fixture->fs->exists($marker))->toBeFalse();
    expect(array_column($result['occurrences'], 0))->toBe(['PHP', 'Twig']);
});

test('rejects ambiguous Twig translation arguments before AST normalization', function (string $arguments): void {
    $this->fixture->write('input/page.twig', "{{ 'Message'|trans($arguments) }}");
    $result = Extractor::extract($this->fixture->root . '/input');
    expect($result['source_errors'])->not->toBeEmpty();
})->with(["from: 'example', from: 'other'", "{}, 'example', from: 'other'", '...args', "from: 'example', {}"]);
