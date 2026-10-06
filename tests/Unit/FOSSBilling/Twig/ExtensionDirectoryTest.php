<?php

declare(strict_types=1);

use Tests\Support\StrictTemplateRenderer;

test('extension directory renders optional icons under strict variables', function (array $iconFields): void {
    $extension = array_replace([
        'id' => 'example',
        'type' => 'mod',
        'name' => 'Example Extension',
        'description' => 'An extension without a required icon.',
        'author' => ['id' => 'Example Author'],
        'releases' => [['tag' => '1.0.0']],
    ], $iconFields);
    $admin = new class($extension) {
        public function __construct(private readonly array $extension)
        {
        }

        public function extension_get_latest(array $params): array
        {
            return [$this->extension];
        }
    };
    $html = (new StrictTemplateRenderer())->renderTemplate(
        PATH_THEMES . '/admin_default/html/partial_extensions.html.twig',
        ['admin' => $admin],
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
