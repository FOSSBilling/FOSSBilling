<?php

declare(strict_types=1);

use Tests\Support\StrictTemplateRenderer;

function extensionDirectoryAdmin(array $extension): object
{
    return new readonly class($extension) {
        public function __construct(private array $extension)
        {
        }

        public function extension_get_latest(array $params): array
        {
            return [$this->extension];
        }
    };
}

test('extension directory renders optional icons under strict variables', function (array $iconFields): void {
    $extension = array_replace([
        'id' => 'example',
        'type' => 'module',
        'name' => 'Example Extension',
        'description' => 'An extension without a required icon.',
        'author' => ['name' => 'Example Author'],
        'version' => '1.0.0',
    ], $iconFields);
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_THEMES . '/default/admin/html/partial_extensions.html.twig',
        ['admin' => extensionDirectoryAdmin($extension)],
    );

    expect($html)->toContain('Example Extension')->toContain('1.0.0');
    $iconUrl = $iconFields['icon_url'] ?? '';
    if (str_starts_with($iconUrl, 'https://') || str_starts_with($iconUrl, 'http://')) {
        expect($html)->toContain('src="' . $iconUrl . '"')->not->toContain('href="#settings"');
    } else {
        expect($html)->toContain('href="#settings"')->not->toContain('<img');
    }
})->with([
    'omitted icon' => [[]],
    'null icon' => [['icon_url' => null]],
    'empty icon' => [['icon_url' => '']],
    'package-relative icon' => [['icon_url' => 'icon.svg']],
    'provided icon' => [['icon_url' => 'https://example.com/icon.svg']],
    'provided http icon' => [['icon_url' => 'http://cdn.example.net/icon.png']],
]);

test('extension directory degrades gracefully with sparse directory metadata', function (array $extension, array $present, array $absent): void {
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_THEMES . '/default/admin/html/partial_extensions.html.twig',
        ['admin' => extensionDirectoryAdmin($extension)],
    );

    foreach ($present as $needle) {
        expect($html)->toContain($needle);
    }
    foreach ($absent as $needle) {
        expect($html)->not->toContain($needle);
    }
})->with([
    'package author without URL' => [
        [
            'id' => 'example-registrar',
            'type' => 'domain-registrar',
            'name' => 'Example Registrar Add-on',
            'description' => 'Registrar add-on.',
            'author' => ['type' => 'registrar', 'name' => 'Example Registrar', 'id' => 'Example Registrar'],
            'version' => '1.0.0',
        ],
        ['Example Registrar Add-on', 'by Example Registrar', '1.0.0', 'href="#settings"'],
        ['<img'],
    ],
    'missing author falls back to unknown' => [
        [
            'id' => 'example',
            'type' => 'module',
            'name' => 'Example Extension',
            'description' => 'An extension.',
            'version' => '1.0.0',
        ],
        ['Example Extension', 'by Unknown', 'href="#settings"'],
        ['<img'],
    ],
    'author name used when id is missing' => [
        [
            'id' => 'example',
            'type' => 'module',
            'name' => 'Example Extension',
            'description' => 'An extension.',
            'author' => ['name' => 'Example Author'],
            'version' => '1.0.0',
        ],
        ['by Example Author', 'href="#settings"'],
        ['<img'],
    ],
    'empty version and missing description' => [
        [
            'id' => 'example',
            'type' => 'module',
            'name' => 'Example Extension',
            'author' => ['name' => 'Example Author'],
            'version' => '',
        ],
        ['Example Extension', 'by Example Author', 'href="#settings"'],
        ['<img'],
    ],
    'missing version, id, and type' => [
        [
            'name' => 'Example Extension',
            'description' => 'An extension.',
            'author' => ['name' => 'Example Author'],
        ],
        ['Example Extension', 'by Example Author', 'href="#settings"'],
        ['<img'],
    ],
]);

test('installed extensions expose the manifest correction action', function (string $status, bool $visible): void {
    $admin = new readonly class($status) {
        public function __construct(private string $status)
        {
        }

        public function extension_get_list(): array
        {
            return [[
                'id' => 'example', 'type' => 'module', 'name' => 'Example', 'version' => '1.0.0',
                'author' => 'Author', 'author_url' => '', 'description' => 'Example module',
                'project_url' => 'https://example.test', 'icon_url' => '', 'has_settings' => false,
                'status' => $this->status,
            ]];
        }

        public function extension_get_latest(array $params): array
        {
            return [];
        }
    };
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_MODS . '/Extension/templates/admin/mod_extension_index.html.twig',
        ['admin' => $admin, 'request' => ['ajax' => true]],
    );
    if ($visible) {
        expect($html)->toContain('Apply manifest correction')->toContain('does not update extension code.');
    } else {
        expect($html)->not->toContain('Apply manifest correction');
    }
})->with([['installed', true], ['core', false], ['', false]]);
