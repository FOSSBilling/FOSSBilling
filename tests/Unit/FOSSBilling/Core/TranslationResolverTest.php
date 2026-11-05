<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/** Write a small GNU MO fixture without requiring the external gettext tools. */
function writeTranslationMo(string $path, array $entries, string $plurals = 'nplurals=2; plural=(n > 1);'): void
{
    $entries[''] = "Content-Type: text/plain; charset=UTF-8\nPlural-Forms: $plurals\n";
    ksort($entries, SORT_STRING);
    $offset = 28 + count($entries) * 16;
    $originalTable = $translationTable = $originals = $translations = '';
    foreach ($entries as $msgid => $translation) {
        $originalTable .= pack('V2', strlen($msgid), $offset + strlen($originals));
        $originals .= $msgid . "\0";
    }
    $offset += strlen($originals);
    foreach ($entries as $translation) {
        $translationTable .= pack('V2', strlen($translation), $offset + strlen($translations));
        $translations .= $translation . "\0";
    }
    $header = pack('V7', 0x950412DE, 0, count($entries), 28, 28 + count($entries) * 8, 0, 0);
    (new Filesystem())->dumpFile($path, $header . $originalTable . $translationTable . $originals . $translations);
}

/**
 * Pest.php predeclares English-only __trans stubs. Exercise the real production
 * helpers in a clean process, also isolating MoTranslator's static locale/cache.
 */
function runTranslationRuntime(string $root, string $script, bool $debug = false): mixed
{
    $bootstrap = '<?php declare(strict_types=1);' . "\n";
    foreach ([
        'DEBUG' => $debug,
        'PATH_ROOT' => PATH_ROOT,
        'PATH_MODS' => Path::join($root, 'modules'),
        'PATH_LANGS' => Path::join($root, 'locale'),
        'PATH_CONFIG' => Path::join($root, 'config.php'),
    ] as $name => $value) {
        $bootstrap .= 'define(' . var_export($name, true) . ', ' . var_export($value, true) . ");\n";
    }
    $bootstrap .= 'require ' . var_export(Path::join(PATH_VENDOR, 'autoload.php'), true) . ";\n";
    $bootstrap .= 'require ' . var_export(Path::join(PATH_LIBRARY, 'TranslationFunctions.php'), true) . ";\n";
    $bootstrap .= '(new FOSSBilling\Core\I18n\Translator())->setLocale("fr_FR")->setup();' . "\n";

    $process = proc_open([PHP_BINARY, '-d', 'display_errors=stderr'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start the translation runtime fixture.');
    }
    fwrite($pipes[0], $bootstrap . $script . "\necho json_encode(\$result, JSON_THROW_ON_ERROR);\n");
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    expect($status)->toBe(0, $errors)->and($errors)->toBe('');

    return json_decode($output, true, flags: JSON_THROW_ON_ERROR);
}

beforeEach(function (): void {
    $filesystem = new Filesystem();
    $this->translationRoot = $filesystem->tempnam(sys_get_temp_dir(), 'fossbilling-i18n-');
    $filesystem->remove($this->translationRoot);
    $filesystem->mkdir($this->translationRoot);
    $filesystem->dumpFile(Path::join($this->translationRoot, 'config.php'), '<?php return [];');

    writeTranslationMo(Path::join($this->translationRoot, 'locale/fr_FR/LC_MESSAGES/messages.mo'), [
        'Save' => 'Enregistrer',
        'Shared' => 'Commun',
        'Same English' => 'Texte du noyau',
        'Zero' => 'Zéro',
        'Empty' => 'Repli du noyau',
        'Empty everywhere' => '',
        'Hello :name' => 'Bonjour :name',
        ':count item' . "\0" . ':count items' => ':count élément' . "\0" . ':count éléments',
        ':count token' . "\0" . ':count tokens' => ':count jeton' . "\0" . ':count jetons',
        'One English' . "\0" . 'Many English' => 'Un traduit' . "\0" . 'Plusieurs traduits',
        'Singular only' => 'Singulier',
    ]);
    writeTranslationMo(Path::join($this->translationRoot, 'modules/Example/locale/fr_FR/LC_MESSAGES/example.mo'), [
        'Save' => 'Sauvegarder',
        'Same English' => 'Same English',
        'Zero' => '0',
        'Empty' => '',
        'Empty everywhere' => '',
        'Hello :name' => 'Salut :name',
        ':count item' . "\0" . ':count items' => ':count objet' . "\0" . ':count objets',
        ':count token' . "\0" . ':count tokens' => ':count unité' . "\0",
        'One English' . "\0" . 'Many English' => 'One English' . "\0" . 'Many English',
        'Singular only' => 'Module singulier',
    ]);
    writeTranslationMo(Path::join($this->translationRoot, 'locale/de_DE/LC_MESSAGES/messages.mo'), [
        'Save' => 'Speichern', 'Shared' => 'Gemeinsam',
    ]);
    writeTranslationMo(Path::join($this->translationRoot, 'modules/Example/locale/de_DE/LC_MESSAGES/example.mo'), [
        'Save' => 'Sichern',
    ]);
    writeTranslationMo(Path::join($this->translationRoot, 'modules/Other/locale/fr_FR/LC_MESSAGES/other.mo'), [
        'Save' => 'Autre traduction', 'Other only' => 'Autre module',
    ]);
    writeTranslationMo(Path::join($this->translationRoot, 'modules/Englishonly/locale/en_US/LC_MESSAGES/englishonly.mo'), [
        'Save' => 'English module wording',
    ]);
    writeTranslationMo(Path::join($this->translationRoot, 'modules/Messages/locale/fr_FR/LC_MESSAGES/messages.mo'), [
        'Save' => 'Module messages',
    ]);
    $filesystem->mkdir(Path::join($this->translationRoot, 'modules/Untranslated'));
});

afterEach(function (): void {
    (new Filesystem())->remove($this->translationRoot);
});

test('the unit test translation stubs accept the production from interface', function (): void {
    expect(__trans('Hello :name', [':name' => 'Ada'], from: 'example'))->toBe('Hello Ada')
        ->and(__pluralTrans(':count item', ':count items', 1, [':count' => 1], from: 'example'))->toBe('1 item');
});

test('existing PHP calls use core translations and substitute values after translation', function (): void {
    expect(runTranslationRuntime($this->translationRoot, <<<'PHP'
        $result = [
            __trans('Save'),
            __trans('Hello :name', [':name' => 'Ada']),
            __pluralTrans(':count item', ':count items', 0, [':count' => 0]),
            __pluralTrans(':count item', ':count items', 2, [':count' => 2]),
        ];
        PHP))->toBe(['Enregistrer', 'Bonjour Ada', '0 élément', '2 éléments']);
});

test('module translations override core including translations equal to English or zero', function (): void {
    expect(runTranslationRuntime($this->translationRoot, <<<'PHP'
        $result = [
            __trans('Save', from: 'example'),
            __trans('Same English', from: 'example'),
            __trans('Zero', from: 'example'),
            __trans('Hello :name', [':name' => 'Ada'], from: 'example'),
        ];
        PHP))->toBe(['Sauvegarder', 'Same English', '0', 'Salut Ada']);
});

test('missing module entries fall back to core and then English', function (): void {
    expect(runTranslationRuntime($this->translationRoot, <<<'PHP'
        $result = [
            __trans('Shared', from: 'example'),
            __trans('Missing :name', [':name' => 'Ada'], from: 'example'),
            __trans('Other only', from: 'example'),
            __trans('Empty', from: 'example'),
            __trans('Empty everywhere', from: 'example'),
            __trans('Empty everywhere'),
        ];
        PHP))->toBe(['Commun', 'Missing Ada', 'Other only', 'Repli du noyau', 'Empty everywhere', 'Empty everywhere']);
});

test('missing sources and locale files use core before English', function (string $from): void {
    $source = var_export($from, true);
    expect(runTranslationRuntime($this->translationRoot, '$result = [__trans("Save", from: ' . $source . '), __trans("Missing", from: ' . $source . ')];'))
        ->toBe(['Enregistrer', 'Missing']);
})->with(['untranslated', 'englishonly', 'unknown', '../Example', '/Example', '', 'Example', 'example/../Other']);

test('plural lookups fall back per selected form and preserve intentional English translations', function (): void {
    expect(runTranslationRuntime($this->translationRoot, <<<'PHP'
        $result = [
            __pluralTrans(':count item', ':count items', 1, [':count' => 1], from: 'example'),
            __pluralTrans(':count item', ':count items', 2, [':count' => 2], from: 'example'),
            __pluralTrans(':count token', ':count tokens', 1, [':count' => 1], from: 'example'),
            __pluralTrans(':count token', ':count tokens', 2, [':count' => 2], from: 'example'),
            __pluralTrans(':count token', ':count tokens', 2, [':count' => 2], from: 'untranslated'),
            __pluralTrans('One English', 'Many English', 2, from: 'example'),
            __pluralTrans('Singular only', 'Plural missing', 2, from: 'example'),
            __pluralTrans(':count absent', ':count absent things', 1, [':count' => 1], from: 'example'),
            __pluralTrans(':count absent', ':count absent things', 0, [':count' => 0], from: 'example'),
        ];
        PHP))->toBe(['1 objet', '2 objets', '1 unité', '2 jetons', '2 jetons', 'Many English', 'Plural missing', '1 absent', '0 absent things']);
});

test('plural fallback uses the selected sources plural rules for languages with three forms', function (): void {
    $plurals = 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);';
    $key = 'item' . "\0" . 'items';
    writeTranslationMo(Path::join($this->translationRoot, 'locale/ru_RU/LC_MESSAGES/messages.mo'), [$key => 'core one' . "\0" . 'core few' . "\0" . 'core many'], $plurals);
    writeTranslationMo(Path::join($this->translationRoot, 'modules/Example/locale/ru_RU/LC_MESSAGES/example.mo'), [$key => 'module one' . "\0\0" . 'module many'], $plurals);

    expect(runTranslationRuntime($this->translationRoot, <<<'PHP'
        (new FOSSBilling\Core\I18n\Translator())->setLocale('ru_RU')->setup();
        $result = [];
        foreach ([1, 2, 5, 21, 22, 11] as $number) {
            $result[] = __pluralTrans('item', 'items', $number, from: 'example');
        }
        PHP))->toBe(['module one', 'core few', 'module many', 'module one', 'core few', 'module many']);
});

test('module lookups are isolated and never change core bindings or the active locale', function (): void {
    expect(runTranslationRuntime($this->translationRoot, <<<'PHP'
        $before = _setlocale(LC_MESSAGES, '');
        $result = [
            __trans('Save', from: 'example'),
            __trans('Save'),
            __trans('Save', from: 'other'),
            __trans('Save', from: 'messages'),
            _dgettext('messages', 'Save'),
            __trans('Save', from: 'example'),
            _setlocale(LC_MESSAGES, '') === $before,
        ];
        PHP))->toBe(['Sauvegarder', 'Enregistrer', 'Autre traduction', 'Module messages', 'Enregistrer', 'Sauvegarder', true]);
});

test('translation caches keep locales separate when switching languages in one process', function (): void {
    expect(runTranslationRuntime($this->translationRoot, <<<'PHP'
        $result = [];
        foreach (['fr_FR', 'de_DE', 'es_ES', 'fr_FR'] as $locale) {
            (new FOSSBilling\Core\I18n\Translator())->setLocale($locale)->setup();
            $result[] = [__trans('Save', from: 'example'), __trans('Shared', from: 'example'), __trans('Save')];
        }
        PHP))->toBe([
        ['Sauvegarder', 'Commun', 'Enregistrer'],
        ['Sichern', 'Gemeinsam', 'Speichern'],
        ['Save', 'Shared', 'Save'],
        ['Sauvegarder', 'Commun', 'Enregistrer'],
    ]);
});

test('legacy default domains remain supported while module fallback explicitly uses core', function (): void {
    writeTranslationMo(Path::join($this->translationRoot, 'locale/fr_FR/LC_MESSAGES/custom.mo'), ['Save' => 'Custom save', 'Shared' => 'Custom shared']);
    expect(runTranslationRuntime($this->translationRoot, <<<'PHP'
        (new FOSSBilling\Core\I18n\Translator())->setLocale('fr_FR')->setDomain('custom')->setup();
        $result = [__trans('Save'), __trans('Shared', from: 'example'), __trans('Save', from: 'core'), __trans('Save')];
        PHP))->toBe(['Custom save', 'Commun', 'Enregistrer', 'Custom save']);
});

test('translated exceptions support from without changing variables codes or existing arguments', function (): void {
    expect(runTranslationRuntime($this->translationRoot, <<<'PHP'
        $module = new FOSSBilling\Core\Exception\InformationException('Hello :name', [':name' => 'Ada'], 42, true, from: 'example');
        $fallback = new FOSSBilling\Core\Exception\InformationException('Shared', from: 'example');
        $legacy = new FOSSBilling\Core\Exception\InformationException('Hello :name', [':name' => 'Ada'], 7);
        $result = [$module->getMessage(), $module->getCode(), $fallback->getMessage(), $legacy->getMessage(), $legacy->getCode()];
        PHP))->toBe(['Salut Ada', 42, 'Commun', 'Bonjour Ada', 7]);
});

test('adapter exceptions forward from while preserving legacy registrar codes', function (): void {
    expect(runTranslationRuntime($this->translationRoot, <<<'PHP'
        $result = [];
        foreach ([Payment_Exception::class, Registrar_Exception::class, Server_Exception::class] as $class) {
            $exception = new $class('Hello :name', [':name' => 'Ada'], 42, from: 'example');
            $result[] = [$exception->getMessage(), $exception->getCode()];
        }
        $legacy = new Registrar_Exception('Shared', 17, from: 'example');
        $result[] = [$legacy->getMessage(), $legacy->getCode()];
        PHP))->toBe([['Salut Ada', 42], ['Salut Ada', 42], ['Salut Ada', 42], ['Commun', 17]]);
});

test('the upstream boolean plural fix selects the correct core and module forms', function (string $rule, array $expected): void {
    $key = ':count token' . "\0" . ':count tokens';
    writeTranslationMo(
        Path::join($this->translationRoot, 'locale/fr_FR/LC_MESSAGES/messages.mo'),
        [$key => 'core one' . "\0" . 'core many'],
        $rule,
    );
    writeTranslationMo(
        Path::join($this->translationRoot, 'modules/Example/locale/fr_FR/LC_MESSAGES/example.mo'),
        [$key => 'module one' . "\0" . 'module many'],
        $rule,
    );
    expect(runTranslationRuntime($this->translationRoot, <<<'PHP'
        $result = [];
        foreach ([0, 1, 2] as $number) {
            $result[] = [
                __pluralTrans(':count token', ':count tokens', $number),
                __pluralTrans(':count token', ':count tokens', $number, from: 'example'),
            ];
        }
        PHP))->toBe($expected);
})->with([
    'English rules' => ['nplurals=2; plural=(n != 1);', [
        ['core many', 'module many'], ['core one', 'module one'], ['core many', 'module many'],
    ]],
    'French rules' => ['nplurals=2; plural=(n > 1);', [
        ['core one', 'module one'], ['core one', 'module one'], ['core many', 'module many'],
    ]],
]);

test('the real Twig filter accepts named from and keeps core lookups and escaping intact', function (bool $sandbox): void {
    $script = <<<'PHP'
        $twig = new Twig\Environment(new Twig\Loader\ArrayLoader([
            'layout' => '{{ "Save"|trans }}|{% block content %}{% endblock %}|{{ "Save"|trans }}',
            'module' => '{% extends "layout" %}{% block content %}{{ "Save"|trans(from: "example") }}|{{ "Shared"|trans(from: "example") }}|{{ "Hello :name"|trans({":name": name}, from: "example") }}{% endblock %}',
        ]), ['autoescape' => 'html']);
        $twig->addExtension(new FOSSBilling\Core\Twig\Extension\TranslationExtension());
        $twig->addExtension(new Twig\Extension\AttributeExtension(FOSSBilling\Core\Twig\Extension\FOSSBillingExtension::class));
        $twig->addRuntimeLoader(new Twig\RuntimeLoader\FactoryRuntimeLoader([
            FOSSBilling\Core\Twig\Extension\FOSSBillingExtension::class => fn () => new FOSSBilling\Core\Twig\Extension\FOSSBillingExtension(new Pimple\Container()),
        ]));
        PHP;
    if ($sandbox) {
        $script .= <<<'PHP'
            $twig->addExtension(new Twig\Extension\SandboxExtension(new Twig\Sandbox\SecurityPolicy(['extends', 'block'], ['trans', 'escape']), true));
            PHP;
    }
    $script .= '$result = $twig->render("module", ["name" => "<Ada>"]);';
    expect(runTranslationRuntime($this->translationRoot, $script))->toBe('Enregistrer|Sauvegarder|Commun|Salut &lt;Ada&gt;|Enregistrer');
})->with([false, true]);

test('Twig escaped literals resolve module translation keys', function (): void {
    writeTranslationMo(Path::join($this->translationRoot, 'modules/Example/locale/fr_FR/LC_MESSAGES/example.mo'), [
        "Hello\n:name" => 'Bonjour :name',
        "Quote's" => 'Citation',
        'é' => 'Accent',
        'Literal #{label}' => 'Texte littéral',
    ]);
    expect(runTranslationRuntime($this->translationRoot, <<<'PHP'
        $twig = new Twig\Environment(new Twig\Loader\ArrayLoader([
            'page' => <<<'TWIG'
                {{ 'Hello\n:name'|trans({':name': 'Ada'}, from: 'example') }}|{{ "Quote\'s"|trans(from: 'example') }}|{{ '\xC3\xA9'|trans(from: 'ex\x61mple') }}|{{ "Literal \#{label}"|trans(from: 'example') }}
                TWIG,
        ]));
        $twig->addExtension(new Twig\Extension\AttributeExtension(FOSSBilling\Core\Twig\Extension\FOSSBillingExtension::class));
        $twig->addRuntimeLoader(new Twig\RuntimeLoader\FactoryRuntimeLoader([
            FOSSBilling\Core\Twig\Extension\FOSSBillingExtension::class => fn () => new FOSSBilling\Core\Twig\Extension\FOSSBillingExtension(new Pimple\Container()),
        ]));
        $result = $twig->render('page');
        PHP))->toBe('Bonjour Ada|Citation|Accent|Texte littéral');
});

test('Twig infers each templates source including inherited layouts includes and macros', function (): void {
    $filesystem = new Filesystem();
    $templates = [
        'templates/layout.twig' => '{{ "Save"|trans }}|{% block content %}{% endblock %}|{{ "Save"|trans }}',
        'templates/shared.twig' => '{{ "Save"|trans }}',
        'modules/Example/templates/macros.twig' => '{% macro save() %}{{ "Save"|trans }}{% endmacro %}',
        'modules/Other/templates/partial.twig' => '{{ "Save"|trans }}',
        'modules/Example/templates/page.twig' => <<<'TWIG'
            {% extends 'layout.twig' %}
            {% import '@example/macros.twig' as buttons %}
            {% block content %}{{ 'Save'|trans }}|{{ 'Shared'|trans }}|{{ 'Hello :name'|trans(values: {':name': name}) }}|{{ buttons.save() }}|{% include 'shared.twig' %}|{% include '@other/partial.twig' %}|{{ 'Save'|trans(from: 'core') }}|{{ 'Save'|trans(from: 'other') }}|{{ 'Save'|trans(null, 'other') }}|{{ 'Save'|trans(from: selected_source) }}{% endblock %}
            TWIG,
    ];
    foreach ($templates as $path => $source) {
        $filesystem->dumpFile(Path::join($this->translationRoot, $path), $source);
    }

    expect(runTranslationRuntime($this->translationRoot, <<<'PHP'
        $root = dirname(PATH_MODS);
        $loader = new Twig\Loader\FilesystemLoader($root . '/templates');
        $loader->addPath(PATH_MODS . '/Example/templates', 'example');
        $loader->addPath(PATH_MODS . '/Other/templates', 'other');
        $twig = new Twig\Environment($loader, ['autoescape' => 'html', 'cache' => $root . '/twig-cache']);
        $twig->addExtension(new FOSSBilling\Core\Twig\Extension\TranslationExtension());
        $twig->addExtension(new Twig\Extension\AttributeExtension(FOSSBilling\Core\Twig\Extension\FOSSBillingExtension::class));
        $twig->addRuntimeLoader(new Twig\RuntimeLoader\FactoryRuntimeLoader([
            FOSSBilling\Core\Twig\Extension\FOSSBillingExtension::class => fn () => new FOSSBilling\Core\Twig\Extension\FOSSBillingExtension(new Pimple\Container()),
        ]));
        $first = $twig->render('@example/page.twig', ['name' => '<Ada>', 'selected_source' => 'core']);
        $second = $twig->render('@example/page.twig', ['name' => '<Ada>', 'selected_source' => 'core']);
        $result = [$first, $first === $second, __trans('Save')];
        PHP))->toBe([
        'Enregistrer|Sauvegarder|Commun|Salut &lt;Ada&gt;|Sauvegarder|Enregistrer|Autre traduction|Enregistrer|Autre traduction|Autre traduction|Enregistrer|Enregistrer',
        true,
        'Enregistrer',
    ]);
});

test('Twig inference is registered in every factory environment', function (string $method): void {
    (new Filesystem())->dumpFile(Path::join($this->translationRoot, 'modules/Example/templates/page.twig'), '{{ "Save"|trans }}|{{ "Save"|trans(from: "core") }}');
    $script = <<<'PHP'
        $di = new Pimple\Container();
        $di['request'] = Symfony\Component\HttpFoundation\Request::create('/');
        $di['cookie_queue'] = new FOSSBilling\Core\Http\CookieQueue();
        $di['session'] = new class {
            public function get($name) { return $name === 'csrf_token' ? 'test-token' : null; }
        };
        $di['api_guest'] = new class {
            public function system_company() { return []; }
        };
        $di['mod'] = $di->protect(fn ($module) => new class {
            public function getConfig() { return []; }
        });
        $factory = new FOSSBilling\Core\Twig\TwigFactory($di);
        PHP;
    $script .= '$twig = $factory->' . $method . '();';
    $script .= <<<'PHP'
        $twig->setLoader(new Twig\Loader\FilesystemLoader(PATH_MODS . '/Example/templates'));
        $result = $twig->render('page.twig');
        PHP;

    expect(runTranslationRuntime($this->translationRoot, $script))->toBe('Sauvegarder|Enregistrer');
})->with(['createBaseEnvironment', 'createAdapterEnvironment', 'createThemeSettingsEnvironment', 'createEmailEnvironment']);

test('Twig does not infer module ownership from logical names or paths outside the modules directory', function (): void {
    (new Filesystem())->dumpFile(Path::join($this->translationRoot, 'modules-copy/Example/page.twig'), '{{ "Save"|trans }}|{{ "Save"|trans(from: "example") }}');
    expect(runTranslationRuntime($this->translationRoot, <<<'PHP'
        $di = new Pimple\Container();
        $di['request'] = Symfony\Component\HttpFoundation\Request::create('/');
        $di['cookie_queue'] = new FOSSBilling\Core\Http\CookieQueue();
        $twig = (new FOSSBilling\Core\Twig\TwigFactory($di))->createThemeSettingsEnvironment();
        $twig->setLoader(new Twig\Loader\ChainLoader([
            new Twig\Loader\ArrayLoader(['@example/inline.twig' => '{{ "Save"|trans }}']),
            new Twig\Loader\FilesystemLoader(dirname(PATH_MODS) . '/modules-copy/Example'),
        ]));
        $result = [$twig->render('@example/inline.twig'), $twig->render('page.twig')];
        PHP))->toBe(['Enregistrer', 'Enregistrer|Sauvegarder']);
});
