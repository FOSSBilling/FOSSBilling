<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Box\Mod\Staff\Controller\Admin;
use Symfony\Component\HttpFoundation\Request;

use function Tests\Helpers\container;

function staffAdminControllerTestEventDispatcher(): object
{
    return new class {
        /** @var list<FOSSBilling\Events\Event> */
        public array $dispatched = [];

        public function dispatch(FOSSBilling\Events\Event $event): FOSSBilling\Events\Event
        {
            $this->dispatched[] = $event;

            return $event;
        }
    };
}

test('get update password dispatches the confirmation event after rate limiting', function (): void {
    $controller = new Admin();
    $eventDispatcher = staffAdminControllerTestEventDispatcher();
    $moduleMock = Mockery::mock(FOSSBilling\Module::class);
    $moduleMock->shouldReceive('getConfig')->once()->andReturnUsing(function () use ($eventDispatcher): array {
        expect($eventDispatcher->dispatched)->toHaveCount(1);
        expect($eventDispatcher->dispatched[0])->toBeInstanceOf(Box\Mod\Staff\Event\BeforeStaffPasswordResetConfirmationEvent::class);

        return ['public' => ['reset_pw' => '0']];
    });

    $rateLimiter = Mockery::mock(FOSSBilling\Security\RateLimiter::class);
    $rateLimiter->shouldReceive('consume')
        ->once()
        ->with('staff_password_reset_confirm_ip', '192.0.2.14')
        ->andReturn(new FOSSBilling\Security\RateLimitResult('staff_password_reset_confirm_ip', false, 5, 4));

    $di = container();
    $di['event_dispatcher'] = $eventDispatcher;
    $di['rate_limiter'] = $rateLimiter;
    $di['request'] = Request::create('http://localhost', server: ['REMOTE_ADDR' => '192.0.2.14']);
    $di['mod'] = $di->protect(fn (string $name): FOSSBilling\Module => $moduleMock);
    $controller->setDi($di);

    $app = Mockery::mock('\\Box_App');
    $app->shouldReceive('render')->once()->with('mod_staff_password_reset')->andReturn('reset page');

    expect($controller->get_updatepassword($app, 'private-reset-code'))->toBe('reset page');
    expect($eventDispatcher->dispatched)->toHaveCount(1);
    expect($eventDispatcher->dispatched[0])->toEqual(new Box\Mod\Staff\Event\BeforeStaffPasswordResetConfirmationEvent('192.0.2.14'));
    expect(get_object_vars($eventDispatcher->dispatched[0]))->toBe(['ip' => '192.0.2.14']);
});
