<?php

declare(strict_types=1);

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

beforeEach(function (): void {
    foreach (['msgfmt', 'msgcat', 'msgattrib'] as $name) {
        if ((new ExecutableFinder())->find($name) === null) {
            $this->markTestSkipped('GNU gettext is required');
        }
    }
    $this->fixture->setupModules();
    $result = $this->fixture->extract();
    $this->assertSame(0, $result->getExitCode(), $result->getErrorOutput());
});

test('compiles gettext messages in the runtime module and locale layout', function (): void {
    $f = $this->fixture;
    $po = $f->po([['Hello :name', null, ['Bonjour :name'], false], [':n item', ':n items', [':n objet', ':n objets'], false]]);
    $before = (new Symfony\Component\Filesystem\Filesystem())->readFile($po);
    $result = $f->translate('compile');
    $this->assertSame(0, $result->getExitCode(), $result->getErrorOutput());
    $entries = $f->readMo($f->mo());
    expect($entries['Hello :name'])->toBe('Bonjour :name')->and($entries[":n item\0:n items"])->toBe(":n objet\0:n objets");
    expect((new Symfony\Component\Filesystem\Filesystem())->exists($f->build . '/Other'))->toBeFalse()->and((new Symfony\Component\Filesystem\Filesystem())->readFile($po))->toBe($before);
});

test('validates without output and omits fuzzy and empty translations', function (): void {
    $f = $this->fixture;
    $f->po([['Hello :name', null, ['incorrect :placeholder'], true]]);
    $f->po([['Hello :name', null, [''], false]], 'de_DE');
    $before = translationSnapshot($f->modules);
    expect($f->translate('validate')->getExitCode())->toBe(0)->and((new Symfony\Component\Filesystem\Filesystem())->exists($f->build))->toBeFalse();
    expect($f->translate('compile')->getExitCode())->toBe(0)->and((new Symfony\Component\Filesystem\Filesystem())->exists($f->build))->toBeFalse()->and(translationSnapshot($f->modules))->toBe($before);
});

test('preserves intentional English and placeholders attached to words', function (string $translation): void {
    $f = $this->fixture;
    $f->po([['Hello :name', null, [$translation], false]]);
    $result = $f->translate('compile');
    $this->assertSame(0, $result->getExitCode(), $result->getErrorOutput());
    expect($f->readMo($f->mo())['Hello :name'])->toBe($translation);
})->with(['你好:name', 'Hello :name', ':names Profil']);

test('rejects placeholder mismatches without publishing previously validated files', function (string $message, string $translation): void {
    $f = $this->fixture;
    $f->write('modules/Example/Pair.php', "<?php __trans('Pair :name :names', from: 'example');");
    expect($f->extract()->getExitCode())->toBe(0);
    $f->po([['Hello :name', null, ['Hallo :name'], false]], 'de_DE');
    $f->po([[$message, null, [$translation], false]]);
    $result = $f->translate('compile');
    expect($result->getExitCode())->toBe(1)->and($result->getErrorOutput())->toContain('placeholders do not match')->and((new Symfony\Component\Filesystem\Filesystem())->exists($f->build))->toBeFalse();
})->with([['Hello :name', 'Bonjour :person'], ['Pair :name :names', ':names'], ['Hello :name', '0']]);

test('accepts wrapped fields and ignores obsolete entries', function (): void {
    $f = $this->fixture;
    $po = $f->po([['Hello :name', null, ['Bonjour :name'], false]]);
    $text = str_replace(['msgid "Hello :name"', 'msgstr "Bonjour :name"'], ["msgid \"\"\n\"Hello \"\n\":name\"", "msgstr \"\"\n\"Bonjour \"\n\":name\""], (new Symfony\Component\Filesystem\Filesystem())->readFile($po));
    $f->fs->dumpFile($po, $text . "\n#~ msgid \"Removed\"\n#~ msgstr \"Supprimé\"\n");
    $result = $f->translate('compile');
    $this->assertSame(0, $result->getExitCode(), $result->getErrorOutput());
    expect($f->readMo($f->mo()))->toHaveKey('Hello :name')->not->toHaveKey('Removed');
});

test('preserves Unicode and escaped translations', function (): void {
    $f = $this->fixture;
    $value = "你好 :name \"quoted\" \\path\nnext\tcolumn\x08\f\v\u{2028}end";
    $f->po([['Hello :name', null, [$value], false]]);
    $result = $f->translate('compile');
    $this->assertSame(0, $result->getExitCode(), $result->getErrorOutput());
    expect($f->readMo($f->mo())['Hello :name'])->toBe($value);
});

test('ignores fuzzy plurals even with empty first forms and fuzzy headers', function (array $forms): void {
    $f = $this->fixture;
    $po = $f->po([[':n item', ':n items', $forms, true], ['Hello :name', null, ['Bonjour :name'], false]]);
    $f->fs->dumpFile($po, "#, fuzzy\n" . (new Symfony\Component\Filesystem\Filesystem())->readFile($po));
    $before = (new Symfony\Component\Filesystem\Filesystem())->readFile($po);
    foreach (['validate', 'compile'] as $command) {
        $result = $f->translate($command);
        $this->assertSame(0, $result->getExitCode(), $result->getErrorOutput());
    }
    $entries = $f->readMo($f->mo());
    expect($entries)->not->toHaveKey(":n item\0:n items");
    expect($entries['Hello :name'])->toBe('Bonjour :name')->and((new Symfony\Component\Filesystem\Filesystem())->readFile($po))->toBe($before);
})->with([[['', 'incorrect :placeholder']], [['incorrect :placeholder', '']], [['incorrect', 'incorrect']]]);

test('requires current template messages and supported contexts even for fuzzy entries', function (bool $fuzzy, bool $context): void {
    $f = $this->fixture;
    $message = $context ? ':n item' : 'Removed';
    $po = $f->po([[$message, ':n items', ['', ':n objets'], $fuzzy]]);
    if ($context) {
        $f->fs->dumpFile($po, str_replace('msgid ":n item"', "msgctxt \"module\"\nmsgid \":n item\"", (new Symfony\Component\Filesystem\Filesystem())->readFile($po)));
    }
    $result = $f->translate('compile');
    expect($result->getExitCode())->toBe(1)->and($result->getErrorOutput())->toContain($context ? 'message contexts are not supported' : 'stale or mismatched entry')->and((new Symfony\Component\Filesystem\Filesystem())->exists($f->build))->toBeFalse();
})->with([[false, false], [true, false], [false, true], [true, true]]);

test('rejects stale plural pairs and malformed PO input', function (bool $malformed): void {
    $f = $this->fixture;
    $po = $f->po([[':n item', ':n things', [':n objet', ':n objets'], false]]);
    if ($malformed) {
        $f->fs->dumpFile($po, 'this is not a PO file');
    }
    $result = $f->translate('compile');
    expect($result->getExitCode())->toBe(1)->and($result->getErrorOutput())->toContain($malformed ? 'msgfmt failed' : 'stale or mismatched entry')->and((new Symfony\Component\Filesystem\Filesystem())->exists($f->build))->toBeFalse();
})->with([false, true]);

test('rejects invalid plural rules and slot counts before writing output', function (string $rule, array $forms): void {
    $f = $this->fixture;
    $po = $f->po([[':n item', ':n items', $forms, false]], rule: $rule);
    $before = (new Symfony\Component\Filesystem\Filesystem())->readFile($po);
    foreach (['validate', 'compile'] as $command) {
        $result = $f->translate($command);
        expect($result->getExitCode())->toBe(1)->and((new Symfony\Component\Filesystem\Filesystem())->exists($f->build))->toBeFalse()->and((new Symfony\Component\Filesystem\Filesystem())->readFile($po))->toBe($before);
    }
})->with([
    ['nplurals=2; plural=(n > 1);', [':n objet']], ['nplurals=2; plural=(n >);', [':n objet', ':n objets']],
    ['nplurals=2; plural=(n / 0);', [':n objet', ':n objets']], ['nplurals=2; plural=(n / 0);', ['', ':n objets']],
    ['nplurals=2; plural=5;', [':n objet', ':n objets']], ['nplurals=3; plural=(n % 3);', ['', ':n objets']],
]);

test('rejects incomplete non-fuzzy plurals but allows wholly untranslated entries', function (array $forms, bool $valid): void {
    $f = $this->fixture;
    $f->po([[':n item', ':n items', $forms, false], ['Hello :name', null, ['Bonjour :name'], false]]);
    foreach (['validate', 'compile'] as $command) {
        $result = $f->translate($command);
        expect($result->getExitCode())->toBe($valid ? 0 : 1);
        if (!$valid) {
            expect($result->getErrorOutput())->toContain('incomplete plural translation')->and((new Symfony\Component\Filesystem\Filesystem())->exists($f->build))->toBeFalse();
        }
    }
    if ($valid) {
        expect($f->readMo($f->mo()))->not->toHaveKey(":n item\0:n items");
    }
})->with([[[':n objet', ''], false], [['', ':n objets'], false], [['', ''], true]]);

test('checks gettext format flags before compilation', function (): void {
    $f = $this->fixture;
    $f->write('modules/Example/Format.php', "<?php __trans('Value %s', from: 'example');");
    expect($f->extract()->getExitCode())->toBe(0);
    $po = $f->po([['Value %s', null, ['Valeur %d'], false]]);
    $f->fs->dumpFile($po, str_replace('msgid "Value %s"', "#, c-format\nmsgid \"Value %s\"", (new Symfony\Component\Filesystem\Filesystem())->readFile($po)));
    $result = $f->translate('compile');
    expect($result->getExitCode())->toBe(1)->and($result->getErrorOutput())->toContain('msgfmt failed')->and((new Symfony\Component\Filesystem\Filesystem())->exists($f->build))->toBeFalse();
});

test('retains complete plural entries for one, two, three and six form languages', function (int $count, string $rule): void {
    $f = $this->fixture;
    $forms = array_map(static fn ($n) => ':n form' . $n, range(0, $count - 1));
    $f->po([[':n item', ':n items', $forms, false]], rule: "nplurals=$count; plural=($rule);");
    $result = $f->translate('compile');
    $this->assertSame(0, $result->getExitCode(), $result->getErrorOutput());
    expect($f->readMo($f->mo())[":n item\0:n items"])->toBe(implode("\0", $forms));
})->with([[1, '0'], [2, 'n != 1'], [3, 'n % 3'], [6, 'n == 0 ? 0 : n == 1 ? 1 : n == 2 ? 2 : n % 100 >= 3 && n % 100 <= 10 ? 3 : n % 100 >= 11 ? 4 : 5']]);

test('preserves a literal zero translation', function (): void {
    $f = $this->fixture;
    $f->write('modules/Example/Zero.php', "<?php __trans('Zero', from: 'example');");
    expect($f->extract()->getExitCode())->toBe(0);
    $f->po([['Zero', null, ['0'], false]]);
    $result = $f->translate('compile');
    $this->assertSame(0, $result->getExitCode(), $result->getErrorOutput());
    expect($f->readMo($f->mo())['Zero'])->toBe('0');
});

test('rejects wrong translation filenames and nonempty build directories', function (bool $filename): void {
    $f = $this->fixture;
    $po = $f->po([['Hello :name', null, ['Bonjour :name'], false]]);
    if ($filename) {
        $f->fs->rename($po, dirname($po) . '/messages.po');
    } else {
        $f->fs->dumpFile($f->build . '/.keep', 'keep');
    }
    $result = $f->translate('compile');
    expect($result->getExitCode())->toBe(1)->and($result->getErrorOutput())->toContain($filename ? 'expected locale/<locale>' : 'new or empty staging directory');
    if (!$filename) {
        expect((new Symfony\Component\Filesystem\Filesystem())->readFile($f->build . '/.keep'))->toBe('keep');
    }
})->with([false, true]);

test('reports missing gettext executables clearly', function (): void {
    $result = $this->fixture->translate('validate', ['PATH' => '']);
    expect($result->getExitCode())->toBe(1)->and($result->getErrorOutput())->toContain('GNU gettext msgfmt, msgcat, and msgattrib');
});

test('always compiles UTF-8 bytes even with pre-0.22 gettext encoding behavior', function (): void {
    $f = $this->fixture;
    $value = 'Salut :name, été';
    $po = $f->po([['Hello :name', null, [$value], false]]);
    $bytes = mb_convert_encoding(str_replace('charset=UTF-8', 'charset=ISO-8859-1', (new Symfony\Component\Filesystem\Filesystem())->readFile($po)), 'ISO-8859-1', 'UTF-8');
    $f->fs->dumpFile($po, $bytes);
    $compiler = (new ExecutableFinder())->find('msgfmt');
    $help = new Process([$compiler, '--help']);
    $help->mustRun();
    $options = str_contains($help->getOutput(), '--no-convert') ? ['--no-convert'] : [];
    $bootstrap = dirname(__DIR__) . '/bootstrap.php';
    $wrapper = '#!' . PHP_BINARY . "\n<?php\nrequire " . var_export($bootstrap, true) . ";\n\$p = new Symfony\\Component\\Process\\Process(array_merge(" . var_export([$compiler, ...$options], true) . ", array_slice(\$argv, 1)));\n\$p->run(); echo \$p->getOutput(); fwrite(STDERR, \$p->getErrorOutput()); exit(\$p->getExitCode());\n";
    $f->write('legacy-bin/msgfmt', $wrapper);
    $f->fs->chmod($f->root . '/legacy-bin/msgfmt', 0o755);
    $env = ['PATH' => $f->root . '/legacy-bin' . PATH_SEPARATOR . getenv('PATH')];
    $control = $f->root . '/control.mo';
    $process = new Process([$f->root . '/legacy-bin/msgfmt', $po, '-o', $control]);
    $process->mustRun();
    expect($f->readMo($control)['Hello :name'])->toBe(mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8'));
    $result = $f->translate('compile', $env);
    $this->assertSame(0, $result->getExitCode(), $result->getErrorOutput());
    $entries = $f->readMo($f->mo());
    expect($entries['Hello :name'])->toBe($value)->and($entries[''])->toContain('charset=UTF-8')->and((new Symfony\Component\Filesystem\Filesystem())->readFile($po))->toBe($bytes);
});

test('rejects staging directories containing only version-control metadata', function (): void {
    $f = $this->fixture;
    $f->po([['Hello :name', null, ['Bonjour :name'], false]]);
    $f->fs->mkdir($f->build . '/.git');
    $result = $f->translate('compile');
    expect($result->getExitCode())->toBe(1);
    expect($result->getErrorOutput())->toContain('new or empty staging directory');
    expect(is_dir($f->build . '/.git'))->toBeTrue();
    expect($f->fs->exists($f->mo()))->toBeFalse();
});
