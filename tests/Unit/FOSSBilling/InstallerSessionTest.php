<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\Request;

require_once Path::join(PATH_ROOT, 'install', 'session.php');

test('installer session uses secure cookie attributes over HTTPS', function (): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $originalName = session_name();
    $originalCookieParams = session_get_cookie_params();
    $session = null;

    try {
        $session = new Session(Request::create('https://billing.example.com/install/install.php'));
        $cookieParams = session_get_cookie_params();

        expect($cookieParams['secure'])->toBeTrue()
            ->and($cookieParams['httponly'])->toBeTrue()
            ->and($cookieParams['samesite'])->toBe('lax')
            ->and(ini_get('session.serialize_handler'))->toBe('php');
    } finally {
        if ($session instanceof Session) {
            $session->destroy();
        } elseif (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        session_name($originalName);
        session_set_cookie_params($originalCookieParams);
    }
});
