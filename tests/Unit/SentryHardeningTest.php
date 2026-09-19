<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use FOSSBilling\Mail;

function sentryHardeningAppUrl(Box_App $app): string
{
    $ref = new ReflectionProperty(Box_App::class, 'url');

    return $ref->getValue($app);
}

test('FOSSBILLING-CJ0: setUrl normalizes null and empty string to /', function (): void {
    $app = new Box_AppClient();
    $app->setUrl(null);
    expect(sentryHardeningAppUrl($app))->toBe('/');

    $app->setUrl('');
    expect(sentryHardeningAppUrl($app))->toBe('/');

    $app->setUrl('//.env');
    expect(sentryHardeningAppUrl($app))->toBe('//.env');
});

test('FOSSBILLING-PJB/PJC: unknown transport still throws InformationException', function (): void {
    $mail = new Mail(
        ['email' => 'sender@example.com'],
        ['email' => 'receiver@example.com'],
        'Subject',
        '<p>Body</p>',
        'bogus-transport'
    );

    expect(fn () => $mail->send())->toThrow(FOSSBilling\InformationException::class);
});

test('FOSSBILLING-PJB/PJC: custom transport without DSN throws InformationException', function (): void {
    $mail = new Mail(
        ['email' => 'sender@example.com'],
        ['email' => 'receiver@example.com'],
        'Subject',
        '<p>Body</p>',
        'custom'
    );

    expect(fn () => $mail->send())->toThrow(FOSSBilling\InformationException::class);
});

test('FOSSBILLING-PJB/PJC: send failures surface as FOSSBilling Exception, not raw Error', function (): void {
    // .invalid never resolves, forcing a transport failure without depending
    // on disabled functions.
    $mail = new Mail(
        ['email' => 'sender@example.com'],
        ['email' => 'receiver@example.com'],
        'Subject',
        '<p>Body</p>',
        'custom',
        'smtp://unroutable.invalid:25'
    );

    expect(fn () => $mail->send())->toThrow(FOSSBilling\Exception::class);
});
