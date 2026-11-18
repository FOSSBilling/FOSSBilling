<?php

declare(strict_types=1);

test('warning checks detect warnings even when an operation catches exceptions', function (): void {
    expect(fn () => withoutPhpWarnings(static function (): void {
        try {
            trigger_error('Warning regression probe', E_USER_WARNING);
        } catch (Throwable) {
        }
    }))->toThrow(PHPUnit\Framework\AssertionFailedError::class, 'Warning regression probe');
});

test('warning checks restore the previous handler after success or failure', function (bool $throws): void {
    $handler = static fn (): bool => false;
    set_error_handler($handler);

    try {
        $callback = static function () use ($throws): string {
            if ($throws) {
                throw new RuntimeException('Callback failed');
            }

            return 'complete';
        };
        if ($throws) {
            expect(fn () => withoutPhpWarnings($callback))->toThrow(RuntimeException::class, 'Callback failed');
        } else {
            expect(withoutPhpWarnings($callback))->toBe('complete');
        }
        $restored = set_error_handler($handler);
        restore_error_handler();
        expect($restored)->toBe($handler);
    } finally {
        restore_error_handler();
    }
})->with([false, true]);
