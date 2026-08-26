<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Security;

use FOSSBilling\Http\CookieNames;
use FOSSBilling\Container\InjectionAwareInterface;
use FOSSBilling\System\Config;
use FOSSBilling\System\Environment;
use FOSSBilling\Tools;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class Session implements InjectionAwareInterface
{
    private const string OBSOLETE_FLAG = 'fb_session_obsolete';
    private const string OBSOLETE_EXPIRES_AT = 'fb_session_obsolete_expires_at';
    private const int DEFAULT_REGENERATION_GRACE_PERIOD = 300;

    private ?\Pimple\Container $di = null;
    private ?string $legacySessionCookie = null;
    private readonly array $cookieParams;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    /**
     * @param array{path?: string, domain?: string, secure?: bool, httponly?: bool, samesite?: string|null} $cookieParams
     */
    public function __construct(private readonly SessionInterface $session, array $cookieParams = [])
    {
        $this->cookieParams = [
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => '',
            ...$cookieParams,
        ];
    }

    public function setupSession(): void
    {
        if (Environment::isCLI()) {
            return;
        }

        $this->configureCookieName();
        $this->restoreSessionFromRequest();
        $this->canUseSession();

        $this->session->start();
        $this->expireLegacySessionCookies();

        $this->handleObsoleteSession();
        $this->updateFingerprint();
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

    public function regenerateId(?int $gracePeriod = null): void
    {
        if (!$this->session->isStarted()) {
            return;
        }

        $gracePeriod ??= (int) Config::getProperty('security.session_regeneration_grace_period', self::DEFAULT_REGENERATION_GRACE_PERIOD);
        $gracePeriod = max(0, $gracePeriod);
        $this->set(self::OBSOLETE_FLAG, true);
        $this->set(self::OBSOLETE_EXPIRES_AT, time() + $gracePeriod);

        $this->rotateSessionId();

        $this->delete(self::OBSOLETE_FLAG);
        $this->delete(self::OBSOLETE_EXPIRES_AT);
    }

    public function destroy(string $type = ''): bool
    {
        switch ($type) {
            case 'admin':
                $this->delete('admin');
                $this->regenerateId();

                return true;
            case 'client':
                $this->delete('client');
                $this->delete('client_id');
                $this->regenerateId();

                return true;
        }

        return $this->session->invalidate();
    }

    /**
     * Checks both the fingerprint and age of the current session to see if it can be used.
     * If the session can't be used, it's destroyed from the database, forcing a new one to be created.
     */
    private function canUseSession(): void
    {
        $invalid = false;
        $sessionName = $this->session->getName();
        $sessionID = $this->resolveSessionId();

        if ($sessionID === '') {
            return;
        }
        $maxAge = time() - Config::getProperty('security.session_lifespan', 7200);

        $connection = $this->di['dbal'];

        try {
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
        } catch (\Doctrine\DBAL\Exception) {
            return;
        }

        if (Config::getProperty('security.perform_session_fingerprinting', true)) {
            $fingerprint = new Fingerprint($this->di['request']);
            $storedFingerprint = json_decode((string) $session['fingerprint'], true);
            if (!is_array($storedFingerprint) || !$fingerprint->checkFingerprint($storedFingerprint, $sessionID)) {
                $invalid = true;
                $this->di['logger']->withChannel('security')->warning(
                    'A session failed the fingerprint check and was automatically destroyed.',
                    ['session_id_sha256' => hash('sha256', $sessionID)],
                );
            }
        }

        if ((int) $session['created_at'] <= $maxAge) {
            $invalid = true;
        }

        if ($invalid) {
            try {
                $connection->executeStatement('DELETE FROM session WHERE id = :id', ['id' => $sessionID]);
            } catch (\Doctrine\DBAL\Exception) {
                // The cookie is still expired below so the unusable session is not reused.
            }
            setcookie($sessionName, '', $this->getSessionCookieOptions(time() - 3600));
            unset($_COOKIE[$sessionName]);
        }
    }

    /**
     * Depending on the specifics, this will either set or update the fingerprint associated with the current session.
     */
    private function updateFingerprint(): void
    {
        $sessionID = $this->resolveSessionId();

        if ($sessionID === '') {
            return;
        }

        $connection = $this->di['dbal'];

        try {
            $session = $connection->fetchAssociative('SELECT id FROM session WHERE id = :id', ['id' => $sessionID]);

            if (Config::getProperty('security.perform_session_fingerprinting', true)) {
                $updatedFingerprint = (new Fingerprint($this->di['request']))->fingerprint();
            } else {
                $updatedFingerprint = [];
            }

            // Fix for the installer which temporarily uses FS sessions before FOSSBilling is completely setup.
            if ($session === false) {
                return;
            }

            $connection->executeStatement('UPDATE session SET fingerprint = :fingerprint WHERE id = :id', [
                'fingerprint' => json_encode($updatedFingerprint, JSON_THROW_ON_ERROR),
                'id' => $sessionID,
            ]);
        } catch (\Doctrine\DBAL\Exception|\JsonException) {
            return;
        }
    }

    private function resolveSessionId(): string
    {
        $sessionID = $this->session->getId();
        if ($sessionID !== '') {
            return $sessionID;
        }

        return $_COOKIE[$this->session->getName()] ?? '';
    }

    private function configureCookieName(): void
    {
        $previousName = $this->session->getName();

        $this->session->setName(CookieNames::SESSION);

        if (
            $previousName === CookieNames::SESSION
            || !isset($_COOKIE[$previousName])
        ) {
            return;
        }

        $this->legacySessionCookie = $previousName;
        if (isset($_COOKIE[CookieNames::SESSION])) {
            return;
        }

        $sessionId = $_COOKIE[$previousName];
        if (
            is_string($sessionId)
            && $sessionId !== ''
            && preg_match('/^[A-Za-z0-9,-]+$/D', $sessionId) === 1
        ) {
            $this->session->setId($sessionId);
        }
    }

    private function restoreSessionFromRequest(): void
    {
        if ($this->di === null) {
            return;
        }

        $restoreToken = $this->di['request']->query->get('restore_token');
        if (!is_string($restoreToken)) {
            return;
        }

        $sessionId = \FOSSBilling\Security\Credential::validateSessionRestoreToken($restoreToken);
        if ($sessionId !== null) {
            $this->session->setId($sessionId);
        }
    }

    private function expireLegacySessionCookies(): void
    {
        if ($this->legacySessionCookie === null || headers_sent()) {
            return;
        }

        setcookie($this->legacySessionCookie, '', $this->getSessionCookieOptions(time() - 3600));
        unset($_COOKIE[$this->legacySessionCookie]);
    }

    private function handleObsoleteSession(): void
    {
        $sessionData = $this->session->all();
        if (!$this->isObsoleteSession($sessionData) || !$this->isObsoleteSessionExpired($sessionData)) {
            return;
        }

        $this->clearAuthenticationData();
        $this->delete(self::OBSOLETE_FLAG);
        $this->delete(self::OBSOLETE_EXPIRES_AT);
        $this->rotateSessionId();
    }

    private function rotateSessionId(): void
    {
        if (headers_sent() || !$this->session->migrate(false)) {
            return;
        }

        $sessionName = $this->session->getName();
        $sessionId = $this->session->getId();
        if ($sessionId !== '') {
            setcookie($sessionName, $sessionId, $this->getSessionCookieOptions(0));

            $_COOKIE[$sessionName] = $sessionId;
        }
    }

    /**
     * @return array{expires: int, path: string, domain: string, secure: bool, httponly: bool, samesite: string}
     */
    private function getSessionCookieOptions(int $expires): array
    {
        return [
            'expires' => $expires,
            'path' => $this->cookieParams['path'],
            'domain' => $this->cookieParams['domain'],
            'secure' => $this->cookieParams['secure'],
            'httponly' => $this->cookieParams['httponly'],
            'samesite' => $this->cookieParams['samesite'] ?? '',
        ];
    }

    private function clearAuthenticationData(): void
    {
        $this->delete('admin');
        $this->delete('client');
        $this->delete('client_id');
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
}
