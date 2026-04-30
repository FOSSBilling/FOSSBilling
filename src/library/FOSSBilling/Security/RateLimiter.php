<?php

declare(strict_types=1);

namespace FOSSBilling\Security;

use FOSSBilling\Config;
use FOSSBilling\InjectionAwareInterface;
use Pimple\Container;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

class RateLimiter implements InjectionAwareInterface
{
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
                'api_guest' => ['policy' => 'token_bucket', 'limit' => 300, 'interval' => '60 seconds'],
                'api_authenticated' => ['policy' => 'token_bucket', 'limit' => 1000, 'interval' => '1 hour'],
                'api_login' => ['policy' => 'token_bucket', 'limit' => 20, 'interval' => '60 seconds'],
                'client_password_reset_ip' => ['policy' => 'fixed_window', 'limit' => 10, 'interval' => '1 hour'],
                'client_password_reset_email' => ['policy' => 'fixed_window', 'limit' => 3, 'interval' => '1 hour'],
                'staff_password_reset_ip' => ['policy' => 'fixed_window', 'limit' => 5, 'interval' => '1 hour'],
                'staff_password_reset_email' => ['policy' => 'fixed_window', 'limit' => 3, 'interval' => '1 hour'],
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

        if ($this->isWhitelisted($subject, $config['whitelist_ips'] ?? [])) {
            return new RateLimitResult($policyName, false, null, null, null, RateLimitResult::REASON_WHITELISTED);
        }

        $factory = $this->getFactory($policyName, $policy);
        $limit = $factory->create($this->hashSubject($subject))->consume($tokens);
        $limited = !$limit->isAccepted();

        return new RateLimitResult(
            $policyName,
            $limited,
            $limit->getLimit(),
            $limit->getRemainingTokens(),
            $limit->getRetryAfter(),
            $limited ? RateLimitResult::REASON_LIMITED : RateLimitResult::REASON_ALLOWED,
        );
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

        if (($factoryConfig['policy'] ?? null) === 'token_bucket') {
            $factoryConfig['rate'] = [
                'amount' => (int) ($policy['limit'] ?? 60),
                'interval' => (string) ($policy['interval'] ?? '1 minute'),
            ];
        } elseif (($factoryConfig['policy'] ?? null) !== 'no_limit') {
            $factoryConfig['interval'] = (string) ($policy['interval'] ?? '1 minute');
        }

        $this->factories[$policyName] = new RateLimiterFactory($factoryConfig, new CacheStorage($this->di['rate_limit_cache']));

        return $this->factories[$policyName];
    }

    private function hashSubject(string $subject): string
    {
        return hash_hmac('sha256', strtolower(trim($subject)), $this->getHashKey());
    }

    private function getHashKey(): string
    {
        return (string) (Config::getProperty('info.salt') ?? Config::getProperty('info.instance_id') ?? 'fossbilling-rate-limiter');
    }

    private function isWhitelisted(string $subject, array $whitelist): bool
    {
        return \Symfony\Component\HttpFoundation\IpUtils::checkIp($subject, $whitelist);
    }
}
