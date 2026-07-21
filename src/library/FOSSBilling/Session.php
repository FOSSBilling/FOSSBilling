<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

class Session implements InjectionAwareInterface
{
    private const string OBSOLETE_FLAG = 'fb_session_obsolete';
    private const string OBSOLETE_EXPIRES_AT = 'fb_session_obsolete_expires_at';
    private const int DEFAULT_REGENERATION_GRACE_PERIOD = 300;

    private ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
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

    public function setupSession(): void
    {
        if (Environment::isCLI()) {
            return;
        }

        $this->canUseSession();

        if (!headers_sent()) {
            session_set_save_handler($this->handler);
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

        if (Config::getProperty('security.mode', 'strict') === 'strict') {
            $cookieParams['samesite'] = 'Strict';
        }

        session_set_cookie_params($cookieParams);
        session_start();

        $this->handleObsoleteSession();
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

    public function regenerateId(?int $gracePeriod = null): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $gracePeriod ??= (int) Config::getProperty('security.session_regeneration_grace_period', self::DEFAULT_REGENERATION_GRACE_PERIOD);
        $gracePeriod = max(0, $gracePeriod);
        $_SESSION[self::OBSOLETE_FLAG] = true;
        $_SESSION[self::OBSOLETE_EXPIRES_AT] = time() + $gracePeriod;

        $this->rotateSessionId();

        unset($_SESSION[self::OBSOLETE_FLAG], $_SESSION[self::OBSOLETE_EXPIRES_AT]);
    }

    public function destroy(string $type = ''): bool
    {
        switch ($type) {
            case 'admin':
                $this->delete('admin');
                $this->regenerateId(0);

                return true;
            case 'client':
                $this->delete('client');
                $this->delete('client_id');
                $this->regenerateId(0);

                return true;
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
        $sessionName = session_name();
        $sessionID = session_id();
        if ($sessionID === '') {
            $sessionID = $sessionName !== false ? ($_COOKIE[$sessionName] ?? '') : '';
        }

        if ($sessionID === '') {
            return;
        }
        $maxAge = time() - Config::getProperty('security.session_lifespan', 7200);

        $connection = $this->di['dbal'];
        $session = $connection->fetchAssociative('SELECT fingerprint, created_at FROM session WHERE id = :id', ['id' => $sessionID]);

        if ($session === false || empty($session['fingerprint'])) {
            return;
        }

        if (empty($session['created_at'])) {
            $createdAt = time();
            $connection->executeStatement('UPDATE session SET created_at = :created_at WHERE id = :id', [
                'created_at' => $createdAt,
                'id' => $sessionID,
            ]);
            $session['created_at'] = $createdAt;
        }

        if (Config::getProperty('security.perform_session_fingerprinting', true)) {
            $fingerprint = new Fingerprint($this->di['request']);
            $storedFingerprint = json_decode((string) $session['fingerprint'], true);
            if (!is_array($storedFingerprint) || !$fingerprint->checkFingerprint($storedFingerprint)) {
                $invalid = true;
                error_log("Session ID $sessionID has potentially been hijacked as it failed the fingerprint check. The session has automatically been destroyed.");
            }
        }

        if ((int) $session['created_at'] <= $maxAge) {
            $invalid = true;
        }

        if ($invalid) {
            $connection->executeStatement('DELETE FROM session WHERE id = :id', ['id' => $sessionID]);
            $cookieParams = session_get_cookie_params();
            $cookieOptions = [
                'expires' => time() - 3600,
                'path' => $cookieParams['path'],
                'domain' => $cookieParams['domain'],
                'secure' => $cookieParams['secure'],
                'httponly' => $cookieParams['httponly'],
            ];
            $cookieOptions['samesite'] = $cookieParams['samesite'];
            if ($sessionName !== false) {
                setcookie($sessionName, '', $cookieOptions);
                unset($_COOKIE[$sessionName]);
            }
        }
    }

    /**
     * Depending on the specifics, this will either set or update the fingerprint associated with the current session.
     */
    private function updateFingerprint(): void
    {
        $sessionID = session_id();
        if ($sessionID === '') {
            $sessionName = session_name();
            $sessionID = $sessionName !== false ? ($_COOKIE[$sessionName] ?? '') : '';
        }

        if ($sessionID === '') {
            return;
        }

        $connection = $this->di['dbal'];
        $session = $connection->fetchAssociative('SELECT id FROM session WHERE id = :id', ['id' => $sessionID]);

        if (Config::getProperty('security.perform_session_fingerprinting', true)) {
            $updatedFingerprint = (new Fingerprint($this->di['request']))->fingerprint();
        } else {
            $updatedFingerprint = [];
        }

        // Fix for the installer which temporarily uses FS sessions before FOSSBilling is completely setup.
        if ($session !== false) {
            $connection->executeStatement('UPDATE session SET fingerprint = :fingerprint WHERE id = :id', [
                'fingerprint' => json_encode($updatedFingerprint, JSON_THROW_ON_ERROR),
                'id' => $sessionID,
            ]);
        }
    }

    private function handleObsoleteSession(): void
    {
        if (!$this->isObsoleteSession($_SESSION)) {
            return;
        }

        if ($this->isObsoleteSessionExpired($_SESSION)) {
            $this->clearAuthenticationData();
            unset($_SESSION[self::OBSOLETE_FLAG], $_SESSION[self::OBSOLETE_EXPIRES_AT]);
            $this->rotateSessionId();

            return;
        }
    }

    private function rotateSessionId(): void
    {
        session_regenerate_id(false);

        $sessionName = session_name();
        $sessionId = session_id();
        if ($sessionId !== '') {
            $params = session_get_cookie_params();

            setcookie($sessionName, $sessionId, [
                'expires' => 0,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'],
            ]);

            $_COOKIE[$sessionName] = $sessionId;
        }
    }

    private function clearAuthenticationData(): void
    {
        unset($_SESSION['admin'], $_SESSION['client'], $_SESSION['client_id']);
    }

    private function isObsoleteSession(array $sessionData): bool
    {
        return !empty($sessionData[self::OBSOLETE_FLAG]);
    }

    private function isObsoleteSessionExpired(array $sessionData, ?int $now = null): bool
    {
        $expiresAt = $sessionData[self::OBSOLETE_EXPIRES_AT] ?? null;
        if (!is_int($expiresAt)) {
            return true;
        }

        $now ??= time();

        return $expiresAt < $now;
    }

    private function shouldBeSecure(): bool
    {
        return Config::getProperty('security.force_https', true) || $this->di['request']->isSecure();
    }
}
