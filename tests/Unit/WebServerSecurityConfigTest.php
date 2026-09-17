<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Path;

test('theme configuration directories are protected at any nesting depth', function (string $configurationFile, string $expectedRule): void {
    $configuration = file_get_contents(Path::join(__DIR__, '..', '..', $configurationFile));

    expect($configuration)->not->toBeFalse()
        ->and($configuration)->toContain($expectedRule);
})->with([
    'Apache' => ['src/.htaccess', 'RewriteCond %{REQUEST_URI} ^/themes/(?:[^/]+/)+config/ [NC]'],
    'Nginx' => ['.ddev/nginx/fossbilling-security.conf', 'location ~* ^/themes/(?:[^/]+/)+config/ {'],
]);
