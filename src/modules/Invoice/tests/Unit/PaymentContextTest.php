<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Invoice\PaymentContext;

use function Tests\Helpers\container;

test('url delegates to tools', function (): void {
    $tools = Mockery::mock(FOSSBilling\Tools::class);
    $tools->shouldReceive('url')->once()->with('/foo')->andReturn('https://example.com/foo');

    $di = container();
    $di['tools'] = $tools;

    $context = new PaymentContext($di);

    expect($context->url('/foo'))->toBe('https://example.com/foo');
});

test('logger delegates to the extension logger', function (): void {
    $logger = Mockery::mock(Psr\Log\LoggerInterface::class);

    $di = container();
    $di['extension_logger'] = $logger;

    $context = new PaymentContext($di);

    expect($context->logger())->toBe($logger);
});

test('httpClient delegates to the http client', function (): void {
    $client = Mockery::mock(Symfony\Contracts\HttpClient\HttpClientInterface::class);

    $di = container();
    $di['http_client'] = $client;

    $context = new PaymentContext($di);

    expect($context->httpClient())->toBe($client);
});

test('renderTemplate delegates to the System module', function (): void {
    $systemService = Mockery::mock();
    $systemService->shouldReceive('renderAdapterTplString')
        ->once()
        ->with('Hello :name', ['name' => 'World'])
        ->andReturn('Hello World');

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $systemService);

    $context = new PaymentContext($di);

    expect($context->renderTemplate('Hello :name', ['name' => 'World']))->toBe('Hello World');
});
