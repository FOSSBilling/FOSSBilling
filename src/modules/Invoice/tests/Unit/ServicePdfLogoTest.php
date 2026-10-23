<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;

use function Tests\Helpers\container;

function pdfLogoService(MockHttpClient $client): Box\Mod\Invoice\Service
{
    $di = container();
    $di['http_client'] = $client;
    $di['request'] = Request::create('/');
    $di['request']->server->set('DOCUMENT_ROOT', PATH_ROOT);
    $service = new class extends Box\Mod\Invoice\Service {
        public function logo(string $url): array
        {
            return $this->getPdfLogoSource($url);
        }
    };
    $service->setDi($di);

    return $service;
}

function pdfLogoPng(): string
{
    return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=');
}

test('PDF logo rejects internal destinations without issuing a request', function (string $url): void {
    $client = new MockHttpClient(static fn () => throw new LogicException('Network must not be reached'));
    expect(pdfLogoService($client)->logo($url))->toBe(['', false]);
})->with([
    'http://127.0.0.1/logo.png', 'http://10.1.2.3/logo.png', 'http://172.16.0.1/logo.png',
    'http://192.168.1.1/logo.png', 'http://169.254.169.254/logo.png', 'http://0.0.0.0/logo.png',
    'http://[::1]/logo.png', 'http://[fe80::1]/logo.png', 'http://[::ffff:127.0.0.1]/logo.png',
    'http://localhost/logo.png', 'file:///outside/logo.png', 'https://',
]);

test('PDF logo embeds a public raster response while disabling renderer network access', function (): void {
    $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
        expect($method)->toBe('GET')->and($options['max_redirects'])->toBe(0)
            ->and($options['timeout'])->toBe(5.0)->and($options['max_duration'])->toBe(10.0);

        return new MockResponse(pdfLogoPng(), ['response_headers' => ['content-type: image/png']]);
    });
    expect(pdfLogoService($client)->logo('https://8.8.8.8/logo.png'))
        ->toBe(['data:image/png;base64,' . base64_encode(pdfLogoPng()), false]);
});

test('PDF logo rejects a redirect to an internal destination', function (): void {
    $client = new MockHttpClient([new MockResponse('', [
        'http_code' => 302,
        'response_headers' => ['location: http://127.0.0.1/logo.png'],
        'redirect_url' => 'http://127.0.0.1/logo.png',
    ])]);
    expect(pdfLogoService($client)->logo('https://8.8.8.8/logo.png'))->toBe(['', false]);
});

test('PDF logo rejects a changed connection address', function (): void {
    $client = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
        $options['on_progress'](0, 0, ['primary_ip' => '127.0.0.1', 'url' => $url]);

        return new MockResponse(pdfLogoPng(), ['response_headers' => ['content-type: image/png']]);
    });
    expect(pdfLogoService($client)->logo('https://8.8.8.8/logo.png'))->toBe(['', false]);
});

test('PDF logo rejects unsafe response content', function (string $body, string $mime, int $status): void {
    $client = new MockHttpClient(new MockResponse($body, ['http_code' => $status, 'response_headers' => ['content-type: ' . $mime]]));
    expect(pdfLogoService($client)->logo('https://8.8.8.8/logo.png'))->toBe(['', false]);
})->with([
    'HTML' => ['<html>secret</html>', 'text/html', 200],
    'invalid image' => ['secret', 'image/png', 200],
    'SVG external references' => ['<svg><image href="http://127.0.0.1/"/></svg>', 'image/svg+xml', 200],
    'too large' => [str_repeat('x', 2 * 1024 * 1024 + 1), 'image/png', 200],
    'error response' => ['error', 'image/png', 500],
]);

test('PDF logo preserves the shipped local SVG without network access', function (): void {
    $client = new MockHttpClient(static fn () => throw new LogicException('Network must not be reached'));
    [$source, $remote] = pdfLogoService($client)->logo('https://billing.example/public/branding/logo.svg');
    expect($source)->toStartWith('data:image/svg+xml;base64,')->and($remote)->toBeFalse();
});

test('PDF logo preserves a self contained remote SVG', function (): void {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><defs><linearGradient id="g"/></defs><rect width="10" height="10" fill="url(#g)"/></svg>';
    $client = new MockHttpClient(new MockResponse($svg, ['response_headers' => ['content-type: image/svg+xml']]));
    expect(pdfLogoService($client)->logo('https://8.8.8.8/logo.svg'))
        ->toBe(['data:image/svg+xml;base64,' . base64_encode($svg), false]);
});

test('PDF logo rejects SVG secondary resources and entities', function (string $svg): void {
    $client = new MockHttpClient(new MockResponse($svg, ['response_headers' => ['content-type: image/svg+xml']]));
    expect(pdfLogoService($client)->logo('https://8.8.8.8/logo.svg'))->toBe(['', false]);
})->with([
    '<svg><image href="file:///etc/passwd"/></svg>',
    '<svg><use href="http://127.0.0.1/logo.svg#x"/></svg>',
    '<svg><style>@import "http://127.0.0.1/";</style></svg>',
    '<svg><rect fill="&#117;rl(http://127.0.0.1/)"/></svg>',
    '<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg>&x;</svg>',
]);

test('PDF logo preserves public redirects', function (): void {
    $client = new MockHttpClient([
        new MockResponse('', ['http_code' => 302, 'response_headers' => ['location: https://1.1.1.1/logo.png'], 'redirect_url' => 'https://1.1.1.1/logo.png']),
        new MockResponse(pdfLogoPng(), ['response_headers' => ['content-type: image/png']]),
    ]);
    expect(pdfLogoService($client)->logo('https://8.8.8.8/logo.png'))
        ->toBe(['data:image/png;base64,' . base64_encode(pdfLogoPng()), false]);
});

test('PDF logo recognizes raster bytes with generic or missing content type', function (string $mime): void {
    $client = new MockHttpClient(new MockResponse(pdfLogoPng(), ['response_headers' => $mime === '' ? [] : ['content-type: ' . $mime]]));
    expect(pdfLogoService($client)->logo('https://8.8.8.8/logo.png'))
        ->toBe(['data:image/png;base64,' . base64_encode(pdfLogoPng()), false]);
})->with(['', 'application/octet-stream']);

test('PDF logo preserves an SVG with an embedded raster image', function (): void {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><image href="data:image/png;base64,' . base64_encode(pdfLogoPng()) . '"/></svg>';
    $client = new MockHttpClient(new MockResponse($svg, ['response_headers' => ['content-type: image/svg+xml']]));
    expect(pdfLogoService($client)->logo('https://8.8.8.8/logo.svg'))
        ->toBe(['data:image/svg+xml;base64,' . base64_encode($svg), false]);
});

test('PDF logo omits transport failures without interrupting invoice generation', function (): void {
    $client = new MockHttpClient(static fn () => throw new Symfony\Component\HttpClient\Exception\TransportException('timeout'));
    expect(pdfLogoService($client)->logo('https://8.8.8.8/logo.png'))->toBe(['', false]);
});
