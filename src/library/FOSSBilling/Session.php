<?php
declare(strict_types=1);
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

class Session implements \FOSSBilling\InjectionAwareInterface
{
    private ?\Pimple\Container $di;
    private \PdoSessionHandler $handler;
    private string $securityMode;
    private int $cookieLifespan;
    private bool $secure;

    public function setDi(\Pimple\Container|null $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function __construct(\PdoSessionHandler $handler, string $securityMode = 'regular', int $cookieLifespan = 7200, bool $secure = true)
    {
        $this->handler = $handler;
        $this->securityMode = $securityMode;
        $this->cookieLifespan = $cookieLifespan;
        $this->secure = $secure;
    }

    public function setupSession()
    {
        if (php_sapi_name() === 'cli') {
            return;
        }

        $this->canUseSession();

        if (!headers_sent()) {
            session_set_save_handler(
                [$this->handler, 'open'],
                [$this->handler, 'close'],
                [$this->handler, 'read'],
                [$this->handler, 'write'],
                [$this->handler, 'destroy'],
                [$this->handler, 'gc']
            );
        }

        $currentCookieParams = session_get_cookie_params();
        $currentCookieParams["httponly"] = true;
        $currentCookieParams["lifetime"] = $this->cookieLifespan;
        $currentCookieParams["secure"] = $this->secure;

        $cookieParams = [
            'lifetime' => $currentCookieParams["lifetime"],
            'path' => $currentCookieParams["path"],
            'domain' => $currentCookieParams["domain"],
            'secure' => $currentCookieParams["secure"],
            'httponly' => $currentCookieParams["httponly"]
        ];

        if ($this->securityMode == 'strict') {
            $cookieParams['samesite'] = 'Strict';
        }

        session_set_cookie_params($cookieParams);
        session_start();

        $this->updateFingerprint();
    }

    public function getId(): string
    {
        return session_id();
    }

    public function delete(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function get(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function destroy(string $type = ''): bool
    {
        switch ($type) {
            case 'admin':
                $this->delete('admin');
                break;
            case 'client':
                $this->delete('client');
                $this->delete('client_id');
                break;
        }

        return session_destroy();
    }

    /**
     * Checks both the fingerprint and age of the current session to see if it can be used.
     * If the session can't be used, it's destroyed from the database, forcing a new one to be created.
     */
    private function canUseSession():void
    {
        if (empty($_COOKIE['PHPSESSID'])) {
            return;
        }

        $sessionID = $_COOKIE['PHPSESSID'];
        $maxAge = time() - $this->di['config']['security']['cookie_lifespan'];

        $fingerprint = new \FOSSBilling\Fingerprint;
        $session = $this->di['db']->findOne('session', 'id = :id', [':id' => $sessionID]);

        if (empty($session->fingerprint)) {
            return;
        }

        if (!$fingerprint->checkFingerprint(json_decode($session->fingerprint, true)) || $session->modified_at <= $maxAge) {
            $this->di['db']->trash($session);
            unset($_COOKIE['PHPSESSID']);
        }
    }

    /**
     * Depending on the specifics, this will either set or update the fingerprint associated with the current session.
     */
    private function updateFingerprint():void
    {
        $sessionID = $_COOKIE['PHPSESSID'] ?? session_id();
        $session = $this->di['db']->findOne('session', 'id = :id', [':id' => $sessionID]);

        $fingerprint = new \FOSSBilling\Fingerprint;
        $session->fingerprint = json_encode($fingerprint->fingerprint());
        $this->di['db']->store($session);
    }
}
