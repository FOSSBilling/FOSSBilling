<?php

declare(strict_types=1);

use FOSSBilling\Core\System\Environment;

test('getCurrentEnvironment defaults to production when APP_ENV is unset', function (): void {
    withAppEnv(null, function (): void {
        expect(Environment::getCurrentEnvironment())->toBe(Environment::PRODUCTION);
    });
});

test('getCurrentEnvironment defaults to production when APP_ENV holds an unrecognized value', function (): void {
    withAppEnv('staging', function (): void {
        expect(Environment::getCurrentEnvironment())->toBe(Environment::PRODUCTION);
    });
});

test('getCurrentEnvironment honors an explicit dev or test value', function (): void {
    withAppEnv('dev', function (): void {
        expect(Environment::getCurrentEnvironment())->toBe(Environment::DEVELOPMENT);
    });
    withAppEnv('test', function (): void {
        expect(Environment::getCurrentEnvironment())->toBe(Environment::TESTING);
    });
});

test('isProduction treats an unset or unrecognized APP_ENV as production', function (): void {
    withAppEnv(null, function (): void {
        expect(Environment::isProduction())->toBeTrue();
    });
    withAppEnv('bogus', function (): void {
        expect(Environment::isProduction())->toBeTrue();
    });
});

test('isExplicitlyProduction is false when APP_ENV is unset', function (): void {
    withAppEnv(null, function (): void {
        expect(Environment::isExplicitlyProduction())->toBeFalse();
    });
});

test('isExplicitlyProduction is false when APP_ENV holds an unrecognized value', function (): void {
    withAppEnv('staging', function (): void {
        expect(Environment::isExplicitlyProduction())->toBeFalse();
    });
});

test('isExplicitlyProduction is false when APP_ENV is dev or test', function (): void {
    withAppEnv('dev', function (): void {
        expect(Environment::isExplicitlyProduction())->toBeFalse();
    });
    withAppEnv('test', function (): void {
        expect(Environment::isExplicitlyProduction())->toBeFalse();
    });
});

test('isExplicitlyProduction is true only when APP_ENV is explicitly prod', function (): void {
    withAppEnv('prod', function (): void {
        expect(Environment::isExplicitlyProduction())->toBeTrue();
    });
});
