<?php

declare(strict_types=1);

use FOSSBilling\Security\RateLimitResult;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;

function createRateLimiter(string $requestIp, array $whitelist = [], ?bool $enabled = null): FOSSBilling\Security\RateLimiter
{
    $di = new Pimple\Container();
    $di['rate_limit_cache'] = new ArrayAdapter();
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('getClientIp')->andReturn($requestIp);
    $di['request'] = $request;

    $limiter = new class($whitelist, $enabled) extends FOSSBilling\Security\RateLimiter {
        public function __construct(private readonly array $whitelist, private readonly ?bool $enabled)
        {
        }

        protected function getConfig(): array
        {
            $config = self::getDefaultConfig();
            $config['whitelist_ips'] = $this->whitelist;
            if ($this->enabled !== null) {
                $config['enabled'] = $this->enabled;
            }

            return $config;
        }
    };
    $limiter->setDi($di);

    return $limiter;
}

test('consume allows until limit is reached', function (): void {
    $limiter = createRateLimiter(requestIp: '1.1.1.1');
    $subject = 'subject-' . uniqid('', true);

    $first = $limiter->consume('api_guest', $subject, 100);
    $second = $limiter->consume('api_guest', $subject);

    expect($first->isLimited())->toBeFalse();
    expect($first->getReason())->toBe(RateLimitResult::REASON_ALLOWED);
    expect($second->isLimited())->toBeTrue();
    expect($second->getReason())->toBe(RateLimitResult::REASON_LIMITED);
});

test('password reset policy reports limited result', function (): void {
    $limiter = createRateLimiter(requestIp: '1.1.1.1');
    $subject = 'subject-' . uniqid('', true);

    $limiter->consume('client_password_reset_email', $subject, 3);
    $second = $limiter->consume('client_password_reset_email', $subject);

    expect($second->isLimited())->toBeTrue();
    expect($second->getReason())->toBe(RateLimitResult::REASON_LIMITED);
});

test('consume or throw returns allowed result', function (): void {
    $limiter = createRateLimiter(requestIp: '1.1.1.1');

    $result = $limiter->consumeOrThrow('api_guest', 'subject-' . uniqid('', true));

    expect($result->isLimited())->toBeFalse();
    expect($result->getReason())->toBe(RateLimitResult::REASON_ALLOWED);
});

test('consume or throw raises rate limit exception', function (): void {
    $limiter = createRateLimiter(requestIp: '1.1.1.1');
    $subject = 'subject-' . uniqid('', true);
    $limiter->consume('client_password_reset_email', $subject, 3);

    try {
        $limiter->consumeOrThrow('client_password_reset_email', $subject);
        expect(true)->toBeFalse('Expected rate limit exception was not thrown.');
    } catch (FOSSBilling\Security\RateLimitException $exception) {
        expect($exception->getCode())->toBe(429);
        expect($exception->getMessage())->toBe('Rate limit exceeded. Please try again later.');
        expect($exception->getRateLimitResult()->getPolicy())->toBe('client_password_reset_email');
        expect($exception->hasRetryAfter())->toBeTrue();
        expect($exception->getRetryAfterSeconds())->toBeGreaterThan(0);
        expect($exception->getRetryAfterSeconds())->toBeLessThanOrEqual(3600);
    }
});

test('unknown policy throws exception', function (): void {
    $limiter = createRateLimiter(requestIp: '1.1.1.1');

    expect(fn (): RateLimitResult => $limiter->consume('unknown_policy', 'subject'))
        ->toThrow(FOSSBilling\Exception::class, 'Rate limiter policy unknown_policy is not defined or invalid');
});

test('CIDR whitelist bypasses limiter', function (): void {
    $limiter = createRateLimiter(requestIp: '10.0.0.5', whitelist: ['10.0.0.0/8']);

    $result = $limiter->consume('api_guest', '10.0.0.5');

    expect($result->isLimited())->toBeFalse();
    expect($result->isBypassed())->toBeTrue();
    expect($result->getReason())->toBe(RateLimitResult::REASON_WHITELISTED);
});

test('whitelist uses request IP for authenticated subjects', function (): void {
    $limiter = createRateLimiter(requestIp: '10.0.0.5', whitelist: ['10.0.0.0/8']);

    $result = $limiter->consume('api_authenticated_account', 'client:123');

    expect($result->isLimited())->toBeFalse();
    expect($result->isBypassed())->toBeTrue();
    expect($result->getReason())->toBe(RateLimitResult::REASON_WHITELISTED);
});

test('non-whitelisted request IP limits authenticated subject', function (): void {
    $limiter = createRateLimiter(requestIp: '1.1.1.1', whitelist: ['10.0.0.0/8']);

    $limiter->consume('client_email_verification_resend_account', 'client:123', 3);
    $second = $limiter->consume('client_email_verification_resend_account', 'client:123');

    expect($second->isLimited())->toBeTrue();
    expect($second->isBypassed())->toBeFalse();
    expect($second->getReason())->toBe(RateLimitResult::REASON_LIMITED);
});

test('does not track IP counters before they are limited', function (): void {
    $limiter = createRateLimiter(requestIp: '1.1.1.1');

    $limiter->consume('api_guest', '1.1.1.1');

    expect($limiter->listIpCounters())->toBe([]);
});

test('lists limited IP counters with retry information', function (): void {
    $limiter = createRateLimiter(requestIp: '1.1.1.1');

    $limiter->consume('client_password_reset_ip', '1.1.1.1', 10);
    $limiter->consume('client_password_reset_ip', '1.1.1.1');

    $counters = $limiter->listIpCounters('1.1.1.1');
    expect($counters)->toHaveCount(1);
    expect($counters[0]['limited'])->toBeTrue();
    expect($counters[0]['remaining'])->toBe(0);
    expect($counters[0]['retry_after'])->toBeString();
    expect($counters[0]['retry_after_seconds'])->toBeGreaterThan(0);
});

test('reset IP clears tracked counters and limiter state', function (): void {
    $limiter = createRateLimiter(requestIp: '1.1.1.1');

    $limiter->consume('client_password_reset_ip', '1.1.1.1', 10);
    expect($limiter->consume('client_password_reset_ip', '1.1.1.1')->isLimited())->toBeTrue();

    $removed = $limiter->resetIp('1.1.1.1');

    expect($removed)->toBe(1);
    expect($limiter->listIpCounters())->toBe([]);
    expect($limiter->consume('client_password_reset_ip', '1.1.1.1')->isLimited())->toBeFalse();
});

test('reset all clears tracked counters and limiter state', function (): void {
    $limiter = createRateLimiter(requestIp: '1.1.1.1');

    $limiter->consume('client_password_reset_ip', '1.1.1.1', 10);
    $limiter->consume('client_password_reset_ip', '1.1.1.1');
    expect($limiter->listIpCounters())->toHaveCount(1);

    expect($limiter->resetAll())->toBeTrue();

    expect($limiter->listIpCounters())->toBe([]);
    expect($limiter->consume('client_password_reset_ip', '1.1.1.1')->isLimited())->toBeFalse();
});

test('disabled limiter does not track IP counters', function (): void {
    $limiter = createRateLimiter(requestIp: '1.1.1.1', enabled: false);

    $result = $limiter->consume('api_guest', '1.1.1.1');

    expect($result->getReason())->toBe(RateLimitResult::REASON_DISABLED);
    expect($limiter->listIpCounters())->toBe([]);
});

test('whitelisted IP does not track counters', function (): void {
    $limiter = createRateLimiter(requestIp: '10.0.0.5', whitelist: ['10.0.0.0/8']);

    $result = $limiter->consume('api_guest', '10.0.0.5');

    expect($result->getReason())->toBe(RateLimitResult::REASON_WHITELISTED);
    expect($limiter->listIpCounters())->toBe([]);
});
