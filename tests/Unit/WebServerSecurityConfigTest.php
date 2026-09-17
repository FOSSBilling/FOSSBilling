<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Path;

test('theme configuration directories are protected at any nesting depth', function (): void {
    // Only the shipped Apache configuration is asserted here.
    // .ddev/nginx/fossbilling-security.conf is dev-only and isn't copied into the CI test image.
    $configuration = file_get_contents(Path::join(__DIR__, '..', '..', 'src/.htaccess'));

    expect($configuration)->not->toBeFalse()
        ->and($configuration)->toContain('RewriteCond %{REQUEST_URI} ^/themes/(?:[^/]+/)+config/ [NC]');
});

test('cron entry point refuses non-CLI execution before bootstrapping', function (): void {
    $cron = file_get_contents(Path::join(__DIR__, '..', '..', 'src/cron.php'));

    expect($cron)->not->toBeFalse()
        ->and($cron)->toContain("if (php_sapi_name() !== 'cli')");

    $guardPosition = strpos((string) $cron, 'php_sapi_name()');
    $bootstrapPosition = strpos((string) $cron, 'require_once __DIR__');

    expect($guardPosition)->not->toBeFalse()
        ->and($bootstrapPosition)->not->toBeFalse()
        ->and($guardPosition)->toBeLessThan($bootstrapPosition);
});
