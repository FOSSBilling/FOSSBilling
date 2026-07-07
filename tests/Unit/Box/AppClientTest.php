<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request;

/**
 * Build a Box_AppClient that overrides render() with a caller-supplied
 * callback, so the get_custom_page catch-block logic can be exercised
 * without spinning up a Twig environment.
 */
function appClientWithRender(callable $render): Box_AppClient
{
    $app = new class($render) extends Box_AppClient {
        /** @var callable */
        private $renderCallback;

        public function __construct(callable $render)
        {
            $this->renderCallback = $render;
        }

        public function render($fileName, $variableArray = [], $ext = 'html.twig'): string
        {
            return ($this->renderCallback)($fileName, $variableArray, $ext);
        }
    };

    $di = new Pimple\Container();
    $di['logger'] = new class {
        public function setChannel(string $channel): self
        {
            return $this;
        }

        public function error(string|Stringable $message, array $context = []): void
        {
        }

        public function info(string|Stringable $message, array $context = []): void
        {
        }
    };
    $di['request'] = Request::create('http://localhost/test');
    $app->setDi($di);
    $app->setUrl('/test');

    return $app;
}

test('get_custom_page returns 200 on successful render', function (): void {
    $app = appClientWithRender(fn (): string => '<html>ok</html>');

    $response = $app->get_custom_page('signup');

    expect($response->getStatusCode())->toBe(200);
    expect($response->getContent())->toBe('<html>ok</html>');
});

test('get_custom_page returns XML content type for sitemap', function (): void {
    $app = appClientWithRender(fn (): string => '<urlset></urlset>');

    $response = $app->get_custom_page('sitemap.xml');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toContain('application/xml')
        ->and($response->getContent())->toBe('<urlset></urlset>');
});

test('get_custom_page returns 500 (not 404) when the template throws a RuntimeError (regression for #3818)', function (): void {
    $app = appClientWithRender(function (string $fileName): string {
        if ($fileName === 'error') {
            return 'error body';
        }

        throw new Twig\Error\RuntimeError('Variable "company" does not exist.');
    });

    $response = $app->get_custom_page('signup');

    expect($response->getStatusCode())->toBe(500);
});

test('get_custom_page returns 500 (not 404) when the template throws a SyntaxError', function (): void {
    $app = appClientWithRender(function (string $fileName): string {
        if ($fileName === 'error') {
            return 'error body';
        }

        throw new Twig\Error\SyntaxError('Unexpected token.');
    });

    $response = $app->get_custom_page('signup');

    expect($response->getStatusCode())->toBe(500);
});

test('get_custom_page returns 500 (not 404) when a nested template is missing during render', function (): void {
    $app = appClientWithRender(function (string $fileName): string {
        if ($fileName === 'error') {
            return 'error body';
        }

        throw new Twig\Error\LoaderError('Template "missing-partial.html.twig" is not defined.');
    });

    $response = $app->get_custom_page('signup');

    expect($response->getStatusCode())->toBe(500);
});

test('get_custom_page still returns 404 when the top-level template is missing', function (): void {
    $app = appClientWithRender(function (string $fileName): string {
        if ($fileName === 'error') {
            return 'error body';
        }

        throw new FOSSBilling\InformationException('Page not found', null, 404);
    });

    $response = $app->get_custom_page('signup');

    expect($response->getStatusCode())->toBe(404);
});
