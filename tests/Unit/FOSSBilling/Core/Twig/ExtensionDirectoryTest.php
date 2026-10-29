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
        'type' => 'mod',
        'name' => 'Example Extension',
        'description' => 'An extension without a required icon.',
        'author' => ['id' => 'Example Author'],
        'releases' => [['tag' => '1.0.0']],
    ], $iconFields);
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_THEMES . '/default/admin/html/partial_extensions.html.twig',
        ['admin' => extensionDirectoryAdmin($extension)],
    );

    expect($html)->toContain('Example Extension')->toContain('1.0.0');
    if (!empty($iconFields['icon_url'])) {
        expect($html)->toContain('src="https://example.com/icon.svg"')->not->toContain('href="#settings"');
    } else {
        expect($html)->toContain('href="#settings"')->not->toContain('<img');
    }
})->with([
    'omitted icon' => [[]],
    'null icon' => [['icon_url' => null]],
    'empty icon' => [['icon_url' => '']],
    'provided icon' => [['icon_url' => 'https://example.com/icon.svg']],
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
    'registrar author shape without URL' => [
        [
            'id' => 'example-registrar',
            'type' => 'domain-registrar',
            'name' => 'Example Registrar Add-on',
            'description' => 'Registrar add-on.',
            'author' => ['type' => 'registrar', 'name' => 'Example Registrar', 'id' => 'Example Registrar'],
            'releases' => [['tag' => '1.0.0']],
        ],
        ['Example Registrar Add-on', 'by Example Registrar', '1.0.0', 'href="#settings"'],
        ['<img'],
    ],
    'missing author falls back to unknown' => [
        [
            'id' => 'example',
            'type' => 'mod',
            'name' => 'Example Extension',
            'description' => 'An extension.',
            'releases' => [['tag' => '1.0.0']],
        ],
        ['Example Extension', 'by Unknown', 'href="#settings"'],
        ['<img'],
    ],
    'author name used when id is missing' => [
        [
            'id' => 'example',
            'type' => 'mod',
            'name' => 'Example Extension',
            'description' => 'An extension.',
            'author' => ['name' => 'Example Author'],
            'releases' => [['tag' => '1.0.0']],
        ],
        ['by Example Author', 'href="#settings"'],
        ['<img'],
    ],
    'empty releases and missing description' => [
        [
            'id' => 'example',
            'type' => 'mod',
            'name' => 'Example Extension',
            'author' => ['id' => 'Example Author'],
            'releases' => [],
        ],
        ['Example Extension', 'by Example Author', 'href="#settings"'],
        ['<img'],
    ],
    'missing releases, id, and type' => [
        [
            'name' => 'Example Extension',
            'description' => 'An extension.',
            'author' => ['id' => 'Example Author'],
        ],
        ['Example Extension', 'by Example Author', 'href="#settings"'],
        ['<img'],
    ],
]);
