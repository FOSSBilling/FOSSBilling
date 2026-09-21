<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request;

function appAdminWithController(object $controller): Box_AppAdmin
{
    $mod = Mockery::mock();
    $mod->shouldReceive('getAdminController')->andReturn($controller);

    $app = new class extends Box_AppAdmin {
        public function setMod(string $mod): void
        {
            $this->mod = $mod;
        }
    };
    $di = new Pimple\Container();
    $di['request'] = Request::create('http://localhost/test');
    $di['mod'] = $di->protect(static fn (): object => $mod);
    $app->setDi($di);
    $app->setMod('example');

    return $app;
}

test('init skips controllers without a register method', function (): void {
    appAdminWithController(new stdClass())->init();

    expect(true)->toBeTrue();
});

test('init registers controllers that have a register method', function (): void {
    $controller = new class {
        public bool $registered = false;

        public function register(object $app): void
        {
            $this->registered = true;
        }
    };

    appAdminWithController($controller)->init();

    expect($controller->registered)->toBeTrue();
});
