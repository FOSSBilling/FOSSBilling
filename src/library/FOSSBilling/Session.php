<?php

declare(strict_types=1);
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

class Session implements InjectionAwareInterface
{
    private ?\Pimple\Container $di = null;

    public function setDi(?\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function __construct(private readonly \PdoSessionHandler $handler)
    {
    }

    public function setupSession()
    {
        if (Environment::isCLI()) {
            return;
        }

        $this->canUseSession();

        if (!headers_sent()) {
            session_set_save_handler(
                $this->handler->open(...),
                $this->handler->close(...),
                $this->handler->read(...),
                $this->handler->write(...),
                $this->handler->destroy(...),
                $this->handler->gc(...)
            );
        }

        $currentCookieParams = session_get_cookie_params();
        $currentCookieParams['httponly'] = true;
        $currentCookieParams['lifetime'] = 0;
        $currentCookieParams['secure'] = $this->shouldBeSecure();

        $cookieParams = [
            'lifetime' => $currentCookieParams['lifetime'],
            'path' => $currentCookieParams['path'],
            'domain' => $currentCookieParams['domain'],
            'secure' => $currentCookieParams['secure'],
            'httponly' => $currentCookieParams['httponly'],
        ];

        if (Config::getProperty('security.mode', 'strict') == 'strict') {
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
    private function canUseSession(): void
    {
        $invalid = false;
        if (empty($_COOKIE['PHPSESSID'])) {
            return;
        }

        $sessionID = $_COOKIE['PHPSESSID'];
        $maxAge = time() - Config::getProperty('security.session_lifespan', 7200);

        $fingerprint = new Fingerprint();
        /** @var \RedBeanPHP\OODBBean $session */
        $session = $this->di['db']->findOne('session', 'id = :id', [':id' => $sessionID]);

        if (empty($session->fingerprint)) {
            return;
        }

        if (empty($session->created_at)) {
            $session->created_at = time();
            $this->di['db']->store($session);
        }

        $storedFingerprint = json_decode($session->fingerprint, true);
        if (!$fingerprint->checkFingerprint($storedFingerprint) && Config::getProperty('security.perform_session_fingerprinting', true)) {
            // TODO: Trying to use monolog here causes a 503 error with an empty error log. Would love to find out why and use it instead of error_log
            $invalid = true;
            error_log("Session ID $sessionID has potentially been hijacked as it failed the fingerprint check. The session has automatically been destroyed.");
            // $this->di['logger']->setChannel('security')->info("Session ID $sessionID has potentially been hijacked as it failed the fingerprint check. The session has automatically been destroyed.");
        }

        if ($session->created_at <= $maxAge) {
            $invalid = true;
        }

        if ($invalid) {
            $this->di['db']->trash($session);
            unset($_COOKIE['PHPSESSID']);
        }
    }

    /**
     * Depending on the specifics, this will either set or update the fingerprint associated with the current session.
     */
    private function updateFingerprint(): void
    {
        $sessionID = $_COOKIE['PHPSESSID'] ?? session_id();
        $session = $this->di['db']->findOne('session', 'id = :id', [':id' => $sessionID]);
        $fingerprint = new Fingerprint();

        if (Config::getProperty('security.perform_session_fingerprinting', true)) {
            $updatedFingerprint = $fingerprint->fingerprint();
        } else {
            $updatedFingerprint = [];
        }

        // Fix for the installer which temporarily uses FS sessions before FOSSBilling is completely setup.
        if (!is_null($session)) {
            $session->fingerprint = json_encode($updatedFingerprint);
            $this->di['db']->store($session);
        }
    }

    private function shouldBeSecure(): bool
    {
        return Config::getProperty('security.force_https', true) || Tools::isHTTPS();
    }
}
