<?php

declare(strict_types=1);

use FOSSBilling\Http\CookieNames;
use Symfony\Component\HttpFoundation\Session\Session as SymfonySession;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

class Session
{
    private readonly SymfonySession $session;

    public function __construct()
    {
        $storage = new NativeSessionStorage([
            'cache_limiter' => '',
            'cookie_lifetime' => 0,
            'name' => CookieNames::SESSION,
        ]);
        $this->session = new SymfonySession($storage);

        if (!headers_sent()) {
            $this->session->start();
        }
    }

    public function getId(): string
    {
        return $this->session->getId();
    }

    public function delete(string $key): void
    {
        $this->session->remove($key);
    }

    public function get(string $key): mixed
    {
        return $this->session->get($key);
    }

    public function set(string $key, mixed $value): void
    {
        $this->session->set($key, $value);
    }

    public function destroy(): void
    {
        if ($this->session->isStarted()) {
            $this->session->invalidate();
            $this->session->save();
        }
    }
}
