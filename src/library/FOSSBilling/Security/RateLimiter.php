<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Security;

use FOSSBilling\Config;
use FOSSBilling\InjectionAwareInterface;
use Psr\Cache\CacheItemPoolInterface;
use Pimple\Container;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

class RateLimiter implements InjectionAwareInterface
{
    private const string IP_INDEX_CACHE_KEY = 'rate_limiter_ip_index';

    protected ?Container $di = null;

    /** @var array<string, RateLimiterFactory> */
    private array $factories = [];

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Container
    {
        return $this->di;
    }

    public static function getDefaultConfig(): array
    {
        return [
            'enabled' => true,
            'whitelist_ips' => [],
            'policies' => [
                'api_guest' => ['policy' => 'token_bucket', 'limit' => 100, 'interval' => '60 seconds'],
                'api_authenticated_ip' => ['policy' => 'token_bucket', 'limit' => 1000, 'interval' => '1 hour'],
                'api_authenticated_account' => ['policy' => 'token_bucket', 'limit' => 1000, 'interval' => '1 hour'],
                'api_login' => ['policy' => 'fixed_window', 'limit' => 10, 'interval' => '1 hour'],
                'client_password_reset_ip' => ['policy' => 'fixed_window', 'limit' => 10, 'interval' => '1 hour'],
                'client_password_reset_email' => ['policy' => 'fixed_window', 'limit' => 3, 'interval' => '1 hour'],
                'client_password_reset_confirm_ip' => ['policy' => 'fixed_window', 'limit' => 20, 'interval' => '60 seconds'],
                'client_password_reset_confirm_post_ip' => ['policy' => 'fixed_window', 'limit' => 20, 'interval' => '60 seconds'],
                'client_email_confirm_ip' => ['policy' => 'fixed_window', 'limit' => 20, 'interval' => '60 seconds'],
                'staff_password_reset_ip' => ['policy' => 'fixed_window', 'limit' => 5, 'interval' => '1 hour'],
                'staff_password_reset_email' => ['policy' => 'fixed_window', 'limit' => 3, 'interval' => '1 hour'],
                'staff_password_reset_confirm_ip' => ['policy' => 'fixed_window', 'limit' => 20, 'interval' => '60 seconds'],
                'staff_password_reset_confirm_post_ip' => ['policy' => 'fixed_window', 'limit' => 20, 'interval' => '60 seconds'],
                'client_signup' => ['policy' => 'fixed_window', 'limit' => 5, 'interval' => '1 hour'],
                'guest_ticket_create' => ['policy' => 'fixed_window', 'limit' => 3, 'interval' => '1 hour'],
                'order_generation_ip' => ['policy' => 'fixed_window', 'limit' => 15, 'interval' => '1 hour'],
                'domain_lookup_ip' => ['policy' => 'fixed_window', 'limit' => 60, 'interval' => '1 hour'],
                'invoice_payment_ip' => ['policy' => 'fixed_window', 'limit' => 10, 'interval' => '1 hour'],
                'invoice_payment_hash' => ['policy' => 'fixed_window', 'limit' => 10, 'interval' => '1 hour'],
                'invoice_pdf_ip' => ['policy' => 'fixed_window', 'limit' => 10, 'interval' => '1 hour'],
                'invoice_pdf_hash' => ['policy' => 'fixed_window', 'limit' => 10, 'interval' => '1 hour'],
                'invoice_get_ip' => ['policy' => 'fixed_window', 'limit' => 10, 'interval' => '1 hour'],
                'invoice_get_hash' => ['policy' => 'fixed_window', 'limit' => 30, 'interval' => '1 hour'],
                'cart_promo_apply_ip' => ['policy' => 'fixed_window', 'limit' => 10, 'interval' => '1 hour'],
                'client_email_verification_resend_ip' => ['policy' => 'fixed_window', 'limit' => 30, 'interval' => '1 hour'],
                'client_email_verification_resend_account' => ['policy' => 'fixed_window', 'limit' => 3, 'interval' => '1 hour'],
                'client_email_resend_ip' => ['policy' => 'fixed_window', 'limit' => 30, 'interval' => '1 hour'],
                'client_email_resend_account' => ['policy' => 'fixed_window', 'limit' => 5, 'interval' => '1 hour'],
                'profile_password_change_ip' => ['policy' => 'fixed_window', 'limit' => 30, 'interval' => '1 hour'],
                'profile_password_change_account' => ['policy' => 'fixed_window', 'limit' => 5, 'interval' => '1 hour'],
            ],
        ];
    }

    public function consume(string $policyName, string $subject, int $tokens = 1): RateLimitResult
    {
        $config = $this->getConfig();
        $policy = $config['policies'][$policyName] ?? null;

        if (($config['enabled'] ?? true) === false) {
            return new RateLimitResult($policyName, false, null, null, null, RateLimitResult::REASON_DISABLED);
        }

        if (!is_array($policy)) {
            throw new \FOSSBilling\Exception('Rate limiter policy :policy is not defined or invalid', [':policy' => $policyName]);
        }

        $clientIp = (string) $this->di['request']->getClientIp();
        if ($clientIp !== '' && $this->isWhitelisted($clientIp)) {
            return new RateLimitResult($policyName, false, null, null, null, RateLimitResult::REASON_WHITELISTED);
        }

        $factory = $this->getFactory($policyName, $policy);
        $limit = $factory->create($this->hashSubject($subject))->consume($tokens);
        $limited = !$limit->isAccepted();
        $result = new RateLimitResult(
            $policyName,
            $limited,
            $limit->getLimit(),
            $limit->getRemainingTokens(),
            $limit->getRetryAfter(),
            $limited ? RateLimitResult::REASON_LIMITED : RateLimitResult::REASON_ALLOWED,
        );

        if ($tokens > 0 && $result->isLimited() && $this->isIpAddress($subject)) {
            $this->trackIpSubject($policyName, $subject);
        }

        return $result;
    }

    public function consumeOrThrow(string $policyName, string $subject, int $tokens = 1): RateLimitResult
    {
        $result = $this->consume($policyName, $subject, $tokens);
        if ($result->isLimited()) {
            throw new RateLimitException($result);
        }

        return $result;
    }

    protected function getConfig(): array
    {
        $defaults = self::getDefaultConfig();
        $config = Config::getProperty('rate_limiter', []);

        if (!is_array($config)) {
            return $defaults;
        }

        return array_replace_recursive($defaults, $config);
    }

    public function isEnabled(): bool
    {
        return ($this->getConfig()['enabled'] ?? true) !== false;
    }

    public function listIpCounters(?string $ip = null): array
    {
        if ($ip !== null && !$this->isIpAddress($ip)) {
            throw new \InvalidArgumentException('The provided input was not a valid IP address.');
        }

        $index = $this->getIpIndex();
        $result = [];
        $changed = false;

        foreach ($index as $key => $entry) {
            if (!is_array($entry) || !isset($entry['policy'], $entry['ip'])) {
                unset($index[$key]);
                $changed = true;
                continue;
            }

            if ($ip !== null && $entry['ip'] !== $ip) {
                continue;
            }

            try {
                $state = $this->getRateLimitState((string) $entry['policy'], (string) $entry['ip']);
            } catch (\Throwable) {
                unset($index[$key]);
                $changed = true;
                continue;
            }

            if (!$state['limited']) {
                unset($index[$key]);
                $changed = true;
                continue;
            }

            $result[] = array_merge($entry, [
                'limit' => $state['limit'],
                'remaining' => $state['remaining'],
                'limited' => $state['limited'],
                'retry_after' => $state['retry_after'],
                'retry_after_seconds' => $state['retry_after_seconds'],
            ]);
        }

        if ($changed) {
            $this->saveIpIndex($index);
        }

        usort($result, static fn (array $a, array $b): int => [$a['ip'], $a['policy']] <=> [$b['ip'], $b['policy']]);

        return $result;
    }

    public function resetIp(string $ip, ?string $policyName = null): int
    {
        if (!$this->isIpAddress($ip)) {
            throw new \InvalidArgumentException('The provided input was not a valid IP address.');
        }

        $policies = $policyName === null ? array_keys($this->getConfig()['policies'] ?? []) : [$policyName];
        foreach ($policies as $policy) {
            $this->resetPolicySubject((string) $policy, $ip);
        }

        $index = $this->getIpIndex();
        $removed = 0;
        foreach ($index as $key => $entry) {
            if (!is_array($entry) || ($entry['ip'] ?? null) !== $ip) {
                continue;
            }

            if ($policyName !== null && ($entry['policy'] ?? null) !== $policyName) {
                continue;
            }

            unset($index[$key]);
            ++$removed;
        }

        $this->saveIpIndex($index);

        return $removed;
    }

    public function resetAll(): bool
    {
        return $this->getRateLimitCache()->clear();
    }

    private function getFactory(string $policyName, array $policy): RateLimiterFactory
    {
        if (isset($this->factories[$policyName])) {
            return $this->factories[$policyName];
        }

        $factoryConfig = [
            'id' => 'fossbilling_' . $policyName,
            'policy' => $policy['policy'] ?? 'token_bucket',
            'limit' => (int) ($policy['limit'] ?? 60),
        ];

        if ($factoryConfig['policy'] === 'token_bucket') {
            $factoryConfig['rate'] = [
                'amount' => (int) ($policy['limit'] ?? 60),
                'interval' => (string) ($policy['interval'] ?? '1 minute'),
            ];
        } elseif ($factoryConfig['policy'] !== 'no_limit') {
            $factoryConfig['interval'] = (string) ($policy['interval'] ?? '1 minute');
        }

        $this->factories[$policyName] = new RateLimiterFactory($factoryConfig, new CacheStorage($this->di['rate_limit_cache']));

        return $this->factories[$policyName];
    }

    private function resetPolicySubject(string $policyName, string $subject): void
    {
        $policy = $this->getConfig()['policies'][$policyName] ?? null;
        if (!is_array($policy)) {
            return;
        }

        $this->getFactory($policyName, $policy)->create($this->hashSubject($subject))->reset();
    }

    private function getRateLimitState(string $policyName, string $subject): array
    {
        $policy = $this->getConfig()['policies'][$policyName] ?? null;
        if (!is_array($policy)) {
            throw new \FOSSBilling\Exception('Rate limiter policy :policy is not defined or invalid', [':policy' => $policyName]);
        }

        $limit = $this->getFactory($policyName, $policy)->create($this->hashSubject($subject))->consume(0);
        $limited = $limit->getRemainingTokens() < 1;
        $result = new RateLimitResult(
            $policyName,
            $limited,
            $limit->getLimit(),
            $limit->getRemainingTokens(),
            $limit->getRetryAfter(),
            $limited ? RateLimitResult::REASON_LIMITED : RateLimitResult::REASON_ALLOWED,
        );

        return [
            'limit' => $result->getLimit(),
            'remaining' => $result->getRemaining(),
            'limited' => $result->isLimited(),
            'retry_after' => $result->getRetryAfter()?->format(\DateTimeInterface::ATOM),
            'retry_after_seconds' => $result->getRetryAfterSeconds(),
        ];
    }

    private function trackIpSubject(string $policyName, string $ip): void
    {
        $index = $this->getIpIndex();
        $key = $this->getIpIndexKey($policyName, $ip);
        $index[$key] = [
            'policy' => $policyName,
            'ip' => $ip,
            'last_seen' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];

        $this->saveIpIndex($index);
    }

    private function getIpIndexKey(string $policyName, string $ip): string
    {
        return hash('sha256', $policyName . "\0" . $ip);
    }

    private function getIpIndex(): array
    {
        $item = $this->getRateLimitCache()->getItem(self::IP_INDEX_CACHE_KEY);
        $index = $item->get();

        return is_array($index) ? $index : [];
    }

    private function saveIpIndex(array $index): void
    {
        $item = $this->getRateLimitCache()->getItem(self::IP_INDEX_CACHE_KEY);
        $item->set($index);
        $this->getRateLimitCache()->save($item);
    }

    private function getRateLimitCache(): CacheItemPoolInterface
    {
        return $this->di['rate_limit_cache'];
    }

    private function hashSubject(string $subject): string
    {
        return hash_hmac('sha256', strtolower(trim($subject)), $this->getHashKey());
    }

    private function getHashKey(): string
    {
        return (string) (Config::getProperty('info.salt') ?? Config::getProperty('info.instance_id') ?? 'fossbilling-rate-limiter');
    }

    private function isWhitelisted(string $subject): bool
    {
        $whitelist = $this->getConfig()['whitelist_ips'] ?? [];

        return \Symfony\Component\HttpFoundation\IpUtils::checkIp($subject, $whitelist);
    }

    private function isIpAddress(string $subject): bool
    {
        return filter_var($subject, FILTER_VALIDATE_IP) !== false;
    }
}
